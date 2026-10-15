<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Eliminar ticket (hard delete, solo con permiso)
// Registra TODO en auditoria antes de borrar (no se pierde el rastro)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('tickets', 'eliminar');

 $id = (int)($_POST['id'] ?? 0);
 $motivo = trim((string)($_POST['motivo'] ?? ''));

 $t = Database::getOne('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$t) Response::error('El ticket no existe.');

Database::begin();
try {
    // capturar todo lo que se va a borrar, para la auditoria
    $comentarios = Database::get('SELECT * FROM ticket_comentarios WHERE ticket_id = ?', [$id]);
    $adjuntos    = Database::get('SELECT id, archivo, nombre_original FROM ticket_adjuntos WHERE ticket_id = ?', [$id]);

    Database::delete('ticket_comentarios', 'ticket_id = ?', [$id]);
    Database::delete('ticket_adjuntos',    'ticket_id = ?', [$id]);
    Database::delete('ticket_historial',   'ticket_id = ?', [$id]);
    Database::delete('tickets',            'id = ?', [$id]);

    $auditado = Auditoria::registrar('eliminar', 'tickets', $id, $t, [
        'motivo'      => $motivo !== '' ? mb_substr($motivo, 0, 250) : '(no indicado)',
        'comentarios' => count($comentarios),
        'adjuntos'    => count($adjuntos),
    ], [
        'modulo' => 'tickets', 'submodulo' => 'eliminacion',
        'accion_detalle' => 'ticket_eliminado_fisicamente_pendiente_h01',
        'resultado' => 'exito',
        'motivo' => $motivo !== '' ? $motivo : null,
    ]);
    if (!$auditado) {
        Database::rollback();
        Response::error('No se pudo registrar la eliminación en auditoría.', null, 500);
    }

    Database::commit();

    // Los ficheros se retiran solo después de confirmar la transacción SQL.
    foreach ($adjuntos as $a) {
        $ruta = STORAGE_PATH . '/adjuntos-tickets/' . $a['archivo'];
        if ($a['archivo'] && is_file($ruta)) @unlink($ruta);
    }

    Response::ok(null, 'Ticket ' . $t['codigo'] . ' eliminado (queda registrado en auditoría).');

} catch (Throwable $e) {
    Database::rollback();
    $codigo = $e instanceof PDOException
        ? (string)($e->errorInfo[0] ?? $e->getCode())
        : (string)$e->getCode();
    error_log('[SIGTI][TICKETS] Eliminación fallida; codigo=' . preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $codigo));
    Response::error('No se pudo completar la eliminación del ticket.', null, 500);
}
