<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();

    $existe = Database::getValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='usuario_permisos'");
    if ((int)$existe === 0) {
        Database::run("CREATE TABLE IF NOT EXISTS usuario_permisos (
            usuario_id INT UNSIGNED NOT NULL, permiso_id SMALLINT UNSIGNED NOT NULL,
            concedido TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (usuario_id, permiso_id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id === 0) $id = Auth::userId();
    if ($id !== Auth::userId() && !Auth::can('usuarios', 'gestionar')) {
        Response::forbidden('Solo puede ver sus propios permisos.');
    }

    $u = Database::getOne(
        "SELECT u.id, u.usuario, u.nombre_completo, u.rol_id, r.nombre AS rol
         FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE u.id = ?", [$id]);
    if (!$u) Response::error('El usuario no existe.');

    $filas = Database::get(
        "SELECT p.id, p.modulo, p.accion, p.descripcion,
               CASE
                 WHEN up.permiso_id IS NOT NULL AND up.concedido = 1 THEN 'concedido'
                 WHEN up.permiso_id IS NOT NULL AND up.concedido = 0 THEN 'revocado'
                 WHEN rp.permiso_id IS NOT NULL THEN 'rol'
                 ELSE 'ninguno'
               END AS origen
         FROM permisos p
         LEFT JOIN rol_permisos rp ON rp.permiso_id = p.id AND rp.rol_id = ?
         LEFT JOIN usuario_permisos up ON up.permiso_id = p.id AND up.usuario_id = ?
         ORDER BY p.modulo, p.id", [(int)$u['rol_id'], $id]);

    $esSuper = ($u['rol'] === 'super_admin');
    $efectivos = 0;
    $resultado = [];
    foreach ($filas as $f) {
        $activo = $esSuper || $f['origen'] === 'concedido' || $f['origen'] === 'rol';
        if ($activo) $efectivos++;
        $origenTxt = '-';
        if ($esSuper) $origenTxt = 'super_admin';
        elseif ($f['origen'] === 'rol') $origenTxt = 'Rol';
        elseif ($f['origen'] === 'concedido') $origenTxt = 'Concedido';
        elseif ($f['origen'] === 'revocado') $origenTxt = 'REVOCADO';
        $resultado[] = ['permiso' => $f['modulo'].'.'.$f['accion'], 'modulo' => $f['modulo'],
            'descripcion' => $f['descripcion'], 'activo' => $activo, 'origen' => $origenTxt];
    }

    Response::ok([
        'usuario' => ['id'=>$u['id'],'usuario'=>$u['usuario'],'nombre'=>$u['nombre_completo'],'rol'=>$u['rol']],
        'total_permisos' => count($resultado), 'efectivos' => $efectivos, 'permisos' => $resultado]);
} catch (Throwable $ex) {
    Response::error('permisos-efectivos: ' . $ex->getMessage());
}