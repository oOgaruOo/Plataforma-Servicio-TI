<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Auth::requirePermission('configuracion', 'gestionar');
        $permisos = Database::get(
            "SELECT p.id, p.modulo, p.accion, p.descripcion,
                    (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.permiso_id = p.id) AS n_roles
             FROM permisos p ORDER BY p.modulo, p.accion");
        Response::ok($permisos);
    }

    Csrf::validate();
    Auth::requirePermission('configuracion', 'gestionar');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $modulo = trim((string)($_POST['modulo'] ?? ''));
        $accionP = trim((string)($_POST['accion'] ?? ''));
        $desc = trim((string)($_POST['descripcion'] ?? ''));
        if ($modulo === '' || $accionP === '') Response::validation(['modulo' => 'Complete modulo y accion.']);
        if (!preg_match('/^[a-z_]{2,40}$/', $modulo)) Response::validation(['modulo' => 'Solo minusculas y _']);
        if (!preg_match('/^[a-z_]{2,40}$/', $accionP)) Response::validation(['accion' => 'Solo minusculas y _']);
        if (Database::getValue('SELECT id FROM permisos WHERE modulo=? AND accion=? AND id<>?', [$modulo,$accionP,$id])) {
            Response::validation(['accion' => "Ya existe {$modulo}.{$accionP}"]);
        }
        if ($id > 0) {
            Database::update('permisos', ['descripcion' => $desc ?: null], 'id = ?', [$id]);
            Response::ok(null, 'Permiso actualizado.');
        }
        $nuevo = Database::insert('permisos', ['modulo'=>$modulo,'accion'=>$accionP,'descripcion'=>$desc ?: null]);
        Response::ok(['id'=>$nuevo], "Permiso {$modulo}.{$accionP} creado.");
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $p = Database::getOne('SELECT * FROM permisos WHERE id = ?', [$id]);
        if (!$p) Response::error('No existe.');
        Database::begin();
        try {
            Database::delete('rol_permisos', 'permiso_id = ?', [$id]);
            Database::delete('usuario_permisos', 'permiso_id = ?', [$id]);
            Database::delete('permisos', 'id = ?', [$id]);
            Database::commit();
            Response::ok(null, "Permiso {$p['modulo']}.{$p['accion']} eliminado.");
        } catch (Throwable $e) { Database::rollback(); Response::error($e->getMessage()); }
    } else { Response::error('Accion invalida.'); }
} catch (Throwable $ex) {
    Response::error('permisos: ' . $ex->getMessage());
}