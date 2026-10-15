<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Chat: catalogo de stickers
//
//  GET                    -> lista los stickers activos (cualquiera que tenga acceso al chat)
//  POST accion=subir      -> agrega un sticker nuevo (imagen); solo Sistemas (usuarios.gestionar)
//  POST accion=eliminar   -> lo quita del catalogo (ya no se puede enviar); los mensajes que ya lo
//                            usaron lo siguen mostrando. Solo Sistemas.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Chat::asegurar();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filas = Database::get("SELECT id, nombre FROM chat_stickers WHERE activo = 1 ORDER BY id DESC");
    foreach ($filas as &$f) $f['id'] = (int)$f['id'];
    Response::ok($filas);
}

Csrf::validate();
if (!Chat::esSistemas()) Response::forbidden('Solo el área de Sistemas administra los stickers.');
$accion = $_POST['accion'] ?? '';

if ($accion === 'subir') {
    if (!isset($_FILES['archivo']) || (int)($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        Response::validation(['archivo' => 'Elija una imagen.']);
    }
    try {
        $g = Chat::guardarSticker($_FILES['archivo']);
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    }
    $id = Database::insert('chat_stickers', [
        'archivo' => $g['archivo'], 'nombre' => $g['nombre'], 'creado_por' => (int)Auth::userId(),
    ]);
    Auditoria::registrar('crear', 'chat_stickers', $id, null, ['nombre' => $g['nombre']]);
    Response::ok(['id' => $id, 'mensaje' => 'Sticker agregado.']);
}

if ($accion === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    $f = Database::getOne('SELECT id, nombre, activo FROM chat_stickers WHERE id = ?', [$id]);
    if (!$f) Response::error('El sticker ya no existe.');
    if ((int)$f['activo'] === 0) Response::ok(['mensaje' => 'Ese sticker ya estaba quitado.']);
    Database::update('chat_stickers', ['activo' => 0], 'id = ?', [$id]);
    Auditoria::registrar('eliminar', 'chat_stickers', $id, ['nombre' => $f['nombre']], null);
    Response::ok(['mensaje' => 'Sticker quitado del catálogo.']);
}

Response::error('Acción inválida.');
