<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Catalogo de destinos de envio (CRUD)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lista = Database::get(
        "SELECT id, nombre AS texto, tipo, estado FROM destinos_envio
         WHERE estado = 'activo' ORDER BY tipo, nombre");
    Response::ok($lista);
}

Csrf::validate();
Auth::requirePermission('configuracion', 'gestionar');

 $accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $tipo = $_POST['tipo'] ?? 'distrito';
    if (!in_array($tipo, ['distrito','provincia','departamento','otro'], true)) $tipo = 'distrito';
    if ($nombre === '') Response::validation(['nombre' => 'Indique el nombre.']);
    if (Database::getValue('SELECT id FROM destinos_envio WHERE nombre = ? AND id <> ?', [$nombre, $id])) {
        Response::validation(['nombre' => 'Ya existe ese destino.']);
    }
    if ($id > 0) {
        Database::update('destinos_envio', ['nombre' => $nombre, 'tipo' => $tipo], 'id = ?', [$id]);
        Response::ok(null, 'Destino actualizado.');
    }
    $nuevo = Database::insert('destinos_envio', ['nombre' => $nombre, 'tipo' => $tipo]);
    Response::ok(null, 'Destino creado.');
} elseif ($accion === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $f = Database::getOne('SELECT id, estado FROM destinos_envio WHERE id = ?', [$id]);
    if (!$f) Response::error('No existe.');
    $nuevo = $f['estado'] === 'activo' ? 'inactivo' : 'activo';
    Database::update('destinos_envio', ['estado' => $nuevo], 'id = ?', [$id]);
    Response::ok(null, $nuevo === 'activo' ? 'Activado.' : 'Desactivado.');
} else {
    Response::error('Accion invalida.');
}