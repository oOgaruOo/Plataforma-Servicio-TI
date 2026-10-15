<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Chat: descarga de un adjunto (api/chat/archivo.php?id=<mensaje>[&dl=1])
// Solo quien tiene acceso a la conversacion (o Sistemas supervisando chats directos).
// Las imagenes se muestran en pantalla; el resto se descarga. Nunca se ejecuta nada del archivo.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Chat::asegurar();

$m = Database::getOne('SELECT * FROM chat_mensajes WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$m || (int)$m['eliminado'] === 1 || empty($m['archivo'])) Response::error('El archivo ya no está disponible.', null, 404);

$sala = Chat::sala((int)$m['sala_id']);
if (!$sala || !Chat::acceso($sala, (int)Auth::userId(), Chat::esSistemas())) Response::forbidden('No tiene acceso a este archivo.');

$ruta = Chat::rutaArchivo((string)$m['archivo']);
if (!is_file($ruta)) Response::error('El archivo ya no está disponible.', null, 404);

$ext = strtolower(pathinfo((string)$m['archivo'], PATHINFO_EXTENSION));
$tipo = Chat::EXT_ARCHIVO[$ext] ?? 'application/octet-stream';
$imagen = strpos($tipo, 'image/') === 0;
$audio = strpos($tipo, 'audio/') === 0;
$inline = ($imagen || $audio) && empty($_GET['dl']);
$nombre = preg_replace('/[^\w.\- ()áéíóúÁÉÍÓÚñÑ]/u', '_', (string)$m['archivo_nombre']) ?: ('archivo.' . $ext);

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: ' . $tipo);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"" . $nombre . "\"; filename*=UTF-8''" . rawurlencode($nombre));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, max-age=3600');
readfile($ruta);
exit;
