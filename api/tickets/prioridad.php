<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Cambiar prioridad de un ticket (recalcula SLA)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('tickets', 'cambiar_estado');

 $id = (int)($_POST['id'] ?? 0);
 $nuevaPrio = (int)($_POST['prioridad_id'] ?? 0);
 $motivo = trim((string)($_POST['motivo'] ?? ''));

 $t = Database::getOne('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$t) Response::error('El ticket no existe.');
if (in_array($t['estado'], ['cerrado','cancelado'], true)) {
    Response::error('Un ticket cerrado/cancelado no cambia de prioridad.');
}

 $pri = Database::getOne(
    'SELECT id, nombre, sla_horas, activo FROM prioridades WHERE id = ?', [$nuevaPrio]);
if (!$pri || (int)$pri['activo'] !== 1) {
    Response::validation(['prioridad_id' => 'Prioridad invalida o inactiva.']);
}

if ((int)$t['prioridad_id'] === $nuevaPrio) {
    Response::error('El ticket ya tiene esa prioridad.');
}

 $anterior = Database::getOne('SELECT nombre FROM prioridades WHERE id = ?', [$t['prioridad_id']]);

 $sla = (new DateTime('now'))->add(new DateInterval('PT' . (int)$pri['sla_horas'] . 'H'))->format('Y-m-d H:i:s');

Database::begin();
try {
    Database::update('tickets', [
        'prioridad_id' => $nuevaPrio,
        'sla_limite'   => $sla,
    ], 'id = ?', [$id]);

    Database::insert('ticket_historial', [
        'ticket_id' => $id, 'usuario_id' => Auth::userId(),
        'evento' => 'prioridad_cambiada',
        'detalle' => $anterior['nombre'] . ' -> ' . $pri['nombre'] .
            ' · SLA recalculado: ' . $sla .
            ($motivo !== '' ? ' · ' . mb_substr($motivo, 0, 150) : ''),
    ]);

    Database::commit();
    Auditoria::registrar('actualizar', 'tickets', $id,
        ['prioridad' => $t['prioridad_id']], ['prioridad' => $nuevaPrio, 'sla' => $sla], [
            'modulo' => 'tickets', 'submodulo' => 'prioridad',
            'accion_detalle' => 'ticket_prioridad_cambiada', 'resultado' => 'exito',
            'motivo' => $motivo !== '' ? $motivo : null,
        ]);

    Response::ok(['sla' => $sla],
        "Prioridad cambiada a {$pri['nombre']}. Nuevo SLA: resolver antes de $sla.");

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error: ' . $e->getMessage());
}
