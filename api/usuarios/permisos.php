<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();

    // auto-crear tabla si no existe
    $existe = Database::getValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='usuario_permisos'");
    if ((int)$existe === 0) {
        Database::run("CREATE TABLE IF NOT EXISTS usuario_permisos (
            usuario_id INT UNSIGNED NOT NULL, permiso_id SMALLINT UNSIGNED NOT NULL,
            concedido TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (usuario_id, permiso_id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
    if ($id === 0) Response::error('Usuario no especificado.');

    $u = Database::getOne(
        "SELECT u.id, u.usuario, u.rol_id, r.nombre AS rol
         FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE u.id = ?", [$id]);
    if (!$u) Response::error('El usuario no existe.');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($id !== Auth::userId() && !Auth::can('usuarios', 'gestionar')) {
            Response::forbidden('Solo puede ver sus propios permisos.');
        }
        $permisos = Database::get(
            "SELECT p.id, p.modulo, p.accion, p.descripcion,
                   (rp.permiso_id IS NOT NULL) AS en_rol, up.concedido AS override
             FROM permisos p
             LEFT JOIN rol_permisos rp ON rp.permiso_id = p.id AND rp.rol_id = ?
             LEFT JOIN usuario_permisos up ON up.permiso_id = p.id AND up.usuario_id = ?
             ORDER BY p.modulo, p.id", [(int)$u['rol_id'], $id]);
        Response::ok(['rol' => $u['rol'], 'permisos' => $permisos]);
    }

    Csrf::validate();
    Auth::requirePermission('usuarios', 'gestionar');
    if ($id === Auth::userId()) Response::error('No puede modificar sus propios permisos.');
    if (($_POST['accion'] ?? '') !== 'guardar') Response::error('Accion invalida.');

    $lista = json_decode((string)($_POST['permisos'] ?? '[]'), true);
    if (!is_array($lista)) Response::error('Datos invalidos.');

    Database::begin();
    Database::delete('usuario_permisos', 'usuario_id = ?', [$id]);
    $n = 0;
    foreach ($lista as $item) {
        $pid = (int)($item['permiso_id'] ?? 0);
        $estado = (string)($item['estado'] ?? 'heredado');
        if ($estado === 'heredado') continue;
        if (!in_array($estado, ['concedido','revocado'], true)) continue;
        if (!Database::getValue('SELECT id FROM permisos WHERE id = ?', [$pid])) continue;
        Database::insert('usuario_permisos', [
            'usuario_id' => $id, 'permiso_id' => $pid,
            'concedido' => $estado === 'concedido' ? 1 : 0]);
        $n++;
    }
    Database::commit();
    Response::ok(null, "Permisos actualizados ($n ajuste(s)).");

} catch (Throwable $ex) {
    Response::error('usuarios/permisos: ' . $ex->getMessage());
}