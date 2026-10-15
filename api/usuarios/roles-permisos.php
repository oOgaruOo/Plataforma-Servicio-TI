<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Permisos de un ROL
// GET  ?rol=ID                       -> rol + todos los permisos (en_rol, sensible, etiqueta del modulo)
// POST accion=guardar rol_id permisos=[ids]  -> reemplaza los permisos del rol
// Permiso usuarios.gestionar. super_admin siempre tiene todo (no se edita).
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('usuarios', 'gestionar');
    Roles::asegurar();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rolId = (int)($_GET['rol'] ?? 0);
        if ($rolId === 0) Response::error('Rol no especificado.');

        $rol = Database::getOne(
            'SELECT id, nombre, descripcion, es_sistema FROM roles WHERE id = ?', [$rolId]);
        if (!$rol) Response::error('El rol no existe.');
        $rol['id'] = (int)$rol['id'];
        $rol['protegido'] = Roles::protegido($rol) ? 1 : 0;
        $rol['n_usuarios'] = (int)Database::getValue('SELECT COUNT(*) FROM usuarios WHERE rol_id = ?', [$rolId]);
        $esSuper = ($rol['nombre'] === 'super_admin');

        $permisos = Database::get(
            "SELECT p.id, p.modulo, p.accion, p.descripcion,
                    (rp.permiso_id IS NOT NULL) AS en_rol
             FROM permisos p
             LEFT JOIN rol_permisos rp ON rp.permiso_id = p.id AND rp.rol_id = ?
             ORDER BY p.modulo, p.id", [$rolId]);
        foreach ($permisos as &$p) {
            $p['id']       = (int)$p['id'];
            $p['en_rol']   = ($esSuper || (int)$p['en_rol'] === 1) ? 1 : 0;
            $p['sensible'] = Roles::esSensible($p['modulo'], $p['accion']) ? 1 : 0;
            $p['modulo_nombre'] = Roles::etiquetaModulo($p['modulo']);
        }
        unset($p);

        Response::ok(['rol' => $rol, 'super' => $esSuper, 'permisos' => $permisos]);
    }

    // ---------- guardar ----------
    Csrf::validate();
    if (($_POST['accion'] ?? '') !== 'guardar') Response::error('Accion invalida.');

    $rolId = (int)($_POST['rol_id'] ?? 0);
    $rol = Database::getOne('SELECT * FROM roles WHERE id = ?', [$rolId]);
    if (!$rol) Response::error('El rol no existe.');
    if ($rol['nombre'] === 'super_admin') Response::error('super_admin siempre tiene todos los permisos.');

    $ids = json_decode((string)($_POST['permisos'] ?? '[]'), true);
    if (!is_array($ids)) Response::validation(['permisos' => 'Datos invalidos.']);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));

    // solo ids que existen
    $validos = array_map('intval', array_column(Database::get('SELECT id FROM permisos'), 'id'));
    $ids = array_values(array_intersect($ids, $validos));

    // no puede quitarse a si mismo la entrada a esta pantalla (se quedaria sin poder corregirlo)
    $miRol = Database::getValue('SELECT rol_id FROM usuarios WHERE id = ?', [Auth::userId()]);
    if ((int)$miRol === $rolId && Auth::rol() !== 'super_admin') {
        $gestionar = (int)Database::getValue("SELECT id FROM permisos WHERE modulo = 'usuarios' AND accion = 'gestionar'");
        if ($gestionar && !in_array($gestionar, $ids, true)) {
            Response::error('No puede quitarle «usuarios.gestionar» a su propio rol: perderia el acceso a esta pantalla.');
        }
    }

    $antes = array_map('intval', array_column(
        Database::get('SELECT permiso_id FROM rol_permisos WHERE rol_id = ?', [$rolId]), 'permiso_id'));
    $agregar = array_values(array_diff($ids, $antes));
    $quitar  = array_values(array_diff($antes, $ids));

    Database::begin();
    try {
        // solo se tocan las diferencias (no se borra y recrea todo)
        foreach ($quitar as $pid) Database::delete('rol_permisos', 'rol_id = ? AND permiso_id = ?', [$rolId, $pid]);
        foreach ($agregar as $pid) Database::insert('rol_permisos', ['rol_id' => $rolId, 'permiso_id' => $pid]);
        Database::commit();
    } catch (Throwable $e) {
        Database::rollback();
        Response::error('Error al guardar: ' . $e->getMessage());
    }

    $nombres = function (array $lista): array {
        if (!$lista) return [];
        $in = implode(',', array_fill(0, count($lista), '?'));
        return array_column(Database::get("SELECT CONCAT(modulo, '.', accion) AS n FROM permisos WHERE id IN ($in) ORDER BY modulo, accion", $lista), 'n');
    };
    Auditoria::registrar('actualizar', 'roles', $rolId, null, [
        'rol' => $rol['nombre'], 'permisos_total' => count($ids),
        'agregados' => $nombres($agregar), 'quitados' => $nombres($quitar)]);

    $usuarios = (int)Database::getValue('SELECT COUNT(*) FROM usuarios WHERE rol_id = ?', [$rolId]);
    Response::ok(['total' => count($ids), 'agregados' => count($agregar), 'quitados' => count($quitar), 'usuarios' => $usuarios],
        'Permisos de «' . $rol['nombre'] . '» guardados (' . count($ids) . ').'
        . ($usuarios ? " Se aplican a $usuarios usuario(s) en menos de un minuto." : ''));

} catch (Throwable $ex) {
    Response::error('roles-permisos: ' . $ex->getMessage());
}
