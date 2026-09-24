<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lista = Database::get(
        "SELECT id, nombre AS texto, tipo, estado FROM ubicaciones_fisicas
         WHERE estado = 'activo' ORDER BY tipo, nombre");
    Response::ok($lista);
}

Csrf::validate();
Auth::requirePermission('configuracion', 'gestionar');

 $accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $tipo   = $_POST['tipo'] ?? 'oficina';

    if ($nombre === '') Response::validation(['nombre' => 'Indique el nombre.']);
    if (!in_array($tipo, ['sede','piso','oficina','almacen','remoto','otro'], true)) $tipo = 'oficina';

    if (Database::getValue('SELECT id FROM ubicaciones_fisicas WHERE nombre = ? AND id <> ?', [$nombre, $id])) {
        Response::validation(['nombre' => 'Ya existe esa ubicacion.']);
    }

    if ($id > 0) {
        Database::update('ubicaciones_fisicas', ['nombre' => $nombre, 'tipo' => $tipo], 'id = ?', [$id]);
        Auditoria::registrar('actualizar', 'ubicaciones_fisicas', $id, null, ['nombre' => $nombre]);
        Response::ok(null, 'Ubicacion actualizada.');
    }
    $nuevo = Database::insert('ubicaciones_fisicas', ['nombre' => $nombre, 'tipo' => $tipo]);
    Auditoria::registrar('crear', 'ubicaciones_fisicas', $nuevo, null, ['nombre' => $nombre]);
    Response::ok(null, 'Ubicacion creada.');

} elseif ($accion === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $f = Database::getOne('SELECT id, estado FROM ubicaciones_fisicas WHERE id = ?', [$id]);
    if (!$f) Response::error('No existe.');
    $nuevo = $f['estado'] === 'activo' ? 'inactivo' : 'activo';
    Database::update('ubicaciones_fisicas', ['estado' => $nuevo], 'id = ?', [$id]);
    Response::ok(null, $nuevo === 'activo' ? 'Activada.' : 'Desactivada.');
} else {
    Response::error('Accion invalida.');
}