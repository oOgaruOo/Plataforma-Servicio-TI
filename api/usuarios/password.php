<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('usuarios', 'gestionar');

 $id = (int)($_POST['id'] ?? 0);

 $v = Validator::make($_POST, ['password' => 'required|minlen:8|maxlen:100'],
    ['password' => 'Nueva contrasena']);
if ($v->fails()) Response::validation($v->errors());

 $u = Database::getOne('SELECT id, usuario FROM usuarios WHERE id = ?', [$id]);
if (!$u) Response::error('El usuario no existe.');

Database::update('usuarios', [
    'password_hash'     => password_hash($_POST['password'], HASH_ALGO),
    'intentos_fallidos' => 0,
], 'id = ?', [$id]);

Auditoria::registrar('actualizar', 'usuarios', $id, null,
    ['password' => '(restablecida por ' . Auth::nombre() . ')']);

Response::ok(null, "Contrasena de «{$u['usuario']}» actualizada correctamente.");