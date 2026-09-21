<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Csrf::validate();

 $v = Validator::make($_POST, [
    'usuario'  => 'required|maxlen:50',
    'password' => 'required|maxlen:100',
], ['usuario' => 'Usuario', 'password' => 'Contrasena']);

if ($v->fails()) Response::validation($v->errors());

 $res = Auth::attempt($_POST['usuario'], $_POST['password']);

if (!$res['success']) {
    Response::error($res['message'], null, 401);
}

Response::ok([
    'usuario'  => Auth::info(),
    'permisos' => $_SESSION['usuario']['permisos'] ?? [],
], $res['message']);