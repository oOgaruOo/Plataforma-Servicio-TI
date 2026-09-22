<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('usuarios', 'gestionar');

 $id     = (int)($_POST['id'] ?? 0);
 $estado = $_POST['estado'] ?? '';

if (!in_array($estado, ['activo', 'bloqueado', 'inactivo'], true)) Response::error('Estado invalido.');
if ($id === 0)              Response::error('Usuario no especificado.');
if ($id === Auth::userId()) Response::error('No puede cambiar el estado de su propia cuenta.');

 $antes = Database::getOne('SELECT id, usuario, estado FROM usuarios WHERE id = ?', [$id]);
if (!$antes) Response::error('El usuario no existe.');

 $datos = ['estado' => $estado];
if ($estado === 'activo') $datos['intentos_fallidos'] = 0;

Database::update('usuarios', $datos, 'id = ?', [$id]);
Auditoria::registrar('cambiar_estado', 'usuarios', $id, $antes, ['estado' => $estado]);

Response::ok(null, "Usuario «{$antes['usuario']}» ahora esta: {$estado}.");