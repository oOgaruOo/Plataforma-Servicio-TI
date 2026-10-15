<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Chat: imagen de un sticker (api/chat/sticker_archivo.php?id=<sticker>)
// No es sensible (es el catalogo, no un mensaje): basta con estar autenticado.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Chat::asegurar();

$st = Database::getOne('SELECT * FROM chat_stickers WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$st) Response::error('El sticker ya no está disponible.', null, 404);

$ruta = Chat::rutaSticker((string)$st['archivo']);
if (!is_file($ruta)) Response::error('El sticker ya no está disponible.', null, 404);

$ext = strtolower(pathinfo((string)$st['archivo'], PATHINFO_EXTENSION));
$tipo = Chat::EXT_STICKER[$ext] ?? 'application/octet-stream';

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: ' . $tipo);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="sticker.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, max-age=86400');
readfile($ruta);
exit;
