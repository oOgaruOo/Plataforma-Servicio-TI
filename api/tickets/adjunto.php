<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();

 $id = (int)($_POST['id'] ?? 0);

 $t = Database::getOne('SELECT id, estado, solicitante_personal_id FROM tickets WHERE id = ?', [$id]);
if (!$t) Response::error('El ticket no existe.');
if (in_array($t['estado'], ['cerrado','cancelado'], true)) {
    Response::error('Ticket cerrado/cancelado: no admite adjuntos.');
}

 $esSoporte = Auth::can('tickets', 'ver');
if (!$esSoporte) {
    $miPersonal = Database::getValue('SELECT personal_id FROM usuarios WHERE id = ?', [Auth::userId()]);
    if ((int)$miPersonal !== (int)$t['solicitante_personal_id']) {
        Response::forbidden('Solo puede adjuntar en sus propios tickets.');
    }
}

if (empty($_FILES['archivo']['name'])) {
    Response::validation(['archivo' => 'Seleccione un archivo.']);
}

try {
    $up = Upload::guardar($_FILES['archivo'], 'adjuntos-tickets');
    $adjuntoId = Database::insert('ticket_adjuntos', [
        'ticket_id' => $id, 'usuario_id' => Auth::userId(),
        'archivo' => $up['archivo'], 'nombre_original' => $up['nombre_original'],
        'tamano_kb' => $up['tamano_kb'],
    ]);
    Database::insert('ticket_historial', [
        'ticket_id' => $id, 'usuario_id' => Auth::userId(),
        'evento' => 'adjunto', 'detalle' => $up['nombre_original'],
    ]);
    Auditoria::registrar('crear', 'ticket_adjuntos', $adjuntoId, null, [
        'ticket_id' => $id, 'tamano_kb' => $up['tamano_kb'],
    ], [
        'modulo' => 'tickets', 'submodulo' => 'adjuntos',
        'accion_detalle' => 'ticket_adjunto_cargado', 'resultado' => 'exito',
    ]);
    Response::ok(['nombre' => $up['nombre_original']], 'Adjunto cargado: ' . $up['nombre_original']);
} catch (Throwable $e) {
    $codigo = $e instanceof PDOException
        ? (string)($e->errorInfo[0] ?? $e->getCode())
        : (string)$e->getCode();
    error_log('[SIGTI][TICKETS][ADJUNTO] Operación fallida; codigo=' . preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $codigo));
    Response::error('No se pudo cargar el adjunto.', null, 500);
}
