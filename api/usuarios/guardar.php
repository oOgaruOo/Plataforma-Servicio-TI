<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('usuarios', 'gestionar');

 $id      = (int)($_POST['id'] ?? 0);
 $esNuevo = ($id === 0);

 $v = Validator::make($_POST, [
    'usuario'         => 'required|maxlen:50',
    'nombre_completo' => 'required|maxlen:120',
    'correo'          => 'maxlen:120|email',
    'rol_id'          => 'required|integer',
    'estado'          => 'required|in:activo,bloqueado,inactivo',
    'password'        => $esNuevo ? 'required|minlen:8|maxlen:100' : 'maxlen:100',
], [
    'usuario' => 'Usuario', 'nombre_completo' => 'Nombre completo', 'correo' => 'Correo',
    'rol_id' => 'Rol', 'estado' => 'Estado', 'password' => 'Contrasena',
]);
if ($v->fails()) Response::validation($v->errors());

 $usuario = trim($_POST['usuario']);
if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $usuario)) {
    Response::validation(['usuario' => 'Solo letras, numeros, puntos, guiones y guiones bajos (3 a 50 caracteres).']);
}

if (!$esNuevo && $id === Auth::userId()) {
    $actual = Database::getOne('SELECT usuario, rol_id, estado FROM usuarios WHERE id = ?', [$id]);
    if ($actual && ($_POST['usuario'] !== $actual['usuario']
        || (int)$_POST['rol_id'] !== (int)$actual['rol_id']
        || $_POST['estado'] !== $actual['estado'])) {
        Response::error('No puede cambiar su propio usuario, rol o estado desde aqui.');
    }
}

if (Database::getValue('SELECT id FROM usuarios WHERE usuario = ? AND id <> ?', [$usuario, $id])) {
    Response::validation(['usuario' => "El usuario «{$usuario}» ya existe."]);
}
if (!Database::getValue('SELECT id FROM roles WHERE id = ?', [(int)$_POST['rol_id']])) {
    Response::validation(['rol_id' => 'El rol seleccionado no existe.']);
}

 $personalId = null;
if (trim((string)($_POST['personal_id'] ?? '')) !== '') {
    $personalId = (int)$_POST['personal_id'];
    if (!Database::getValue('SELECT id FROM personal WHERE id = ?', [$personalId])) {
        Response::validation(['personal_id' => 'La persona seleccionada no existe.']);
    }
}

 $datos = [
    'usuario'         => $usuario,
    'nombre_completo' => trim($_POST['nombre_completo']),
    'correo'          => trim($_POST['correo']) !== '' ? trim($_POST['correo']) : null,
    'rol_id'          => (int)$_POST['rol_id'],
    'estado'          => $_POST['estado'],
    'personal_id'     => $personalId,
];

if ($esNuevo) {
    $datos['password_hash'] = password_hash($_POST['password'], HASH_ALGO);
    $nuevoId = Database::insert('usuarios', $datos);
    Auditoria::registrar('crear', 'usuarios', $nuevoId, null,
        ['usuario' => $usuario, 'rol_id' => $datos['rol_id'], 'estado' => $datos['estado']]);
    Response::ok(['id' => $nuevoId], "Usuario «{$usuario}» creado correctamente.");
}

 $antes = Database::getOne(
    'SELECT id, usuario, nombre_completo, correo, rol_id, estado, personal_id FROM usuarios WHERE id = ?',
    [$id]
);
if (!$antes) Response::error('El usuario no existe.');

 $password = trim((string)$_POST['password']);
if ($password !== '') $datos['password_hash'] = password_hash($password, HASH_ALGO);
if ($datos['estado'] === 'activo') $datos['intentos_fallidos'] = 0;

Database::update('usuarios', $datos, 'id = ?', [$id]);
Auditoria::registrar('actualizar', 'usuarios', $id, $antes, $datos);

 $msg = 'Usuario actualizado correctamente.';
if ((int)$datos['rol_id'] !== (int)$antes['rol_id']) {
    $msg .= ' Nota: si tiene sesion abierta, debera cerrarla y reingresar para aplicar el nuevo rol.';
}
if ($password !== '') $msg .= ' Contrasena actualizada.';
Response::ok(['id' => $id], $msg);