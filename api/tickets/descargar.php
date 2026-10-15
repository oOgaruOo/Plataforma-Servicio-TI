<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Descarga segura de adjuntos de tickets
// Valida sesion y permiso ANTES de servir el archivo.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $id = (int)($_GET['id'] ?? 0);
if ($id === 0) Response::error('Adjunto no especificado.');

 $adj = Database::getOne(
    "SELECT ta.id, ta.archivo, ta.nombre_original, t.solicitante_personal_id
     FROM ticket_adjuntos ta
     INNER JOIN tickets t ON t.id = ta.ticket_id
     WHERE ta.id = ?", [$id]);
if (!$adj) Response::error('El adjunto no existe.');

// permiso: soporte (ver todos) o el propio solicitante
 $esSoporte = Auth::can('tickets', 'ver');
if (!$esSoporte) {
    $miPersonal = Database::getValue('SELECT personal_id FROM usuarios WHERE id = ?', [Auth::userId()]);
    if ((int)$miPersonal !== (int)$adj['solicitante_personal_id']) {
        Response::forbidden('No tiene acceso a este archivo.');
    }
}

 $ruta = STORAGE_PATH . '/adjuntos-tickets/' . $adj['archivo'];
if (!is_file($ruta)) Response::error('El archivo fisico no existe en el servidor.', null, 404);

 $ext  = strtolower(pathinfo($adj['nombre_original'], PATHINFO_EXTENSION));
 $mimes = [
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif'  => 'image/gif',  'pdf'  => 'application/pdf', 'txt' => 'text/plain',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip'  => 'application/zip',
];
 $mime = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($adj['nombre_original']) . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, max-age=0');
readfile($ruta);
exit;