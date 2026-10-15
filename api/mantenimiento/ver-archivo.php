<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Ver evidencia (o cotizacion) de un mantenimiento
// Valida sesion y permiso ANTES de servir el archivo.
//   ?id=<evidencia_id>                   -> foto de evidencia
//   ?tipo=cotizacion&id=<mantenimiento>  -> cotizacion del proveedor
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('mantenimiento', 'ver');

$id   = (int)($_GET['id'] ?? 0);
$tipo = (string)($_GET['tipo'] ?? 'evidencia');
if ($id === 0) Response::error('Archivo no especificado.');

if ($tipo === 'cotizacion') {
    $archivo   = Database::getValue('SELECT cotizacion_archivo FROM mantenimientos WHERE id = ?', [$id]);
    $subcarpeta = 'cotizaciones';
} else {
    $archivo   = Database::getValue('SELECT archivo FROM mantenimiento_evidencias WHERE id = ?', [$id]);
    $subcarpeta = 'evidencias-mantenimiento';
}
if (!$archivo) Response::error('El archivo no existe.', null, 404);

// basename() impide salir de la carpeta (../) aunque el dato en BD estuviera alterado
$base = realpath(STORAGE_PATH . '/' . $subcarpeta);
$ruta = $base ? realpath($base . '/' . basename((string)$archivo)) : false;
if (!$ruta || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($ruta)) {
    Response::error('El archivo fisico no existe en el servidor.', null, 404);
}

$mimes = [
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
    'pdf' => 'application/pdf',
];
$ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
if (!isset($mimes[$ext])) Response::error('Tipo de archivo no permitido.', null, 415);

while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: ' . $mimes[$ext]);
header('Content-Disposition: inline; filename="' . basename($ruta) . '"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0');
readfile($ruta);
exit;
