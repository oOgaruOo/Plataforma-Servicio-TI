<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    Response::error('Metodo no permitido.', null, 405);
}
Csrf::validate();
Auth::requirePermission('usuarios', 'gestionar');

 $id = (int)($_POST['id'] ?? 0);

 $v = Validator::make($_POST, ['password' => 'required|minlen:8|maxlen:100'],
    ['password' => 'Nueva contrasena']);
if ($v->fails()) Response::validation($v->errors());

try {
    Database::begin();

    $u = Database::getOne('SELECT id, usuario FROM usuarios WHERE id = ?', [$id]);
    if (!$u) {
        Database::rollback();
        Response::error('El usuario no existe.');
    }

    $actualizadas = Database::update('usuarios', [
        'password_hash'     => password_hash($_POST['password'], HASH_ALGO),
        'intentos_fallidos' => 0,
    ], 'id = ?', [$id]);
    if ($actualizadas !== 1) {
        Database::rollback();
        Response::error('No se pudo actualizar la contraseña.', null, 500);
    }

    $registrada = Auditoria::registrar('actualizar', 'usuarios', $id, null,
        ['credencial' => 'contraseña restablecida'], [
            'modulo' => 'seguridad',
            'accion_detalle' => 'password_restablecida',
        ]);
    if ($registrada !== true) {
        Database::rollback();
        Response::error('No se pudo registrar la operación de seguridad. No se modificó la contraseña.', null, 500);
    }

    Database::commit();
    Response::ok(null, "Contrasena de «{$u['usuario']}» actualizada correctamente.");
} catch (Throwable $e) {
    try {
        Database::rollback();
    } catch (Throwable $rollbackError) {
        // No exponer detalles de infraestructura al cliente.
    }

    error_log('[SIGTI][SEGURIDAD] Fallo controlado al restablecer contraseña.');
    Response::error('No se pudo completar el restablecimiento de contraseña.', null, 500);
}
