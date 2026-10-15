<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('tickets', 'cambiar_estado');

 $id       = (int)($_POST['id'] ?? 0);
 $nuevo    = $_POST['estado'] ?? '';
 $motivo   = trim((string)($_POST['motivo'] ?? ''));
 $solucion = trim((string)($_POST['solucion'] ?? ''));

 $t = Database::getOne('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$t) Response::error('El ticket no existe.');

 $FLUJO = TicketFlujo::FLUJO;
TicketFlujo::asegurar();

if (!isset($FLUJO[$t['estado']])) Response::error('Estado actual invalido.');
if (!in_array($nuevo, $FLUJO[$t['estado']], true)) {
    Response::error("Transicion no permitida: {$t['estado']} -> {$nuevo}.");
}

 $datos = ['estado' => $nuevo];
switch ($nuevo) {
    case 'cancelado':
        if ($motivo === '') Response::validation(['motivo' => 'El motivo de cancelacion es obligatorio.']);
        $datos['motivo_cancelacion'] = mb_substr($motivo, 0, 250);
        break;
    case 'resuelto':
        if ($solucion === '') Response::validation(['solucion' => 'Describa la solucion aplicada.']);
        $datos['solucion'] = $solucion;
        $datos['fecha_resolucion'] = date('Y-m-d H:i:s');
        $datos['resuelto_por'] = Auth::userId();
        $atraso = TicketFlujo::atrasoMin($t);
        if ($atraso > 0) {
            $motivoRet = trim((string)($_POST['motivo_retraso'] ?? ''));
            if ($motivoRet === '') {
                Response::validation(['motivo_retraso' => 'Se resuelve con ' . TicketFlujo::texto($atraso)
                    . ' de retraso sobre el SLA. Indique el motivo del retraso.']);
            }
            $datos['motivo_retraso'] = mb_substr($motivoRet, 0, 250);
        }
        break;
    case 'pendiente_usuario':
    case 'pendiente_proveedor':
        if ($motivo === '') Response::validation(['motivo' => 'Indique el motivo de la espera.']);
        break;
    case 'cerrado':
        $datos['fecha_cierre'] = date('Y-m-d H:i:s');
        $datos['cerrado_por'] = Auth::userId();
        break;
    case 'en_atencion':
        if (in_array($t['estado'], ['resuelto','cerrado'], true)) {
            if ($motivo === '') Response::validation(['motivo' => 'Indique el motivo de la reapertura.']);
            // se cuenta la reapertura solo si el cambio es valido (antes se sumaba aunque faltara el motivo)
            $datos['reaperturas'] = (int)$t['reaperturas'] + 1;
        }
        break;
}

Database::update('tickets', $datos, 'id = ?', [$id]);

Database::insert('ticket_historial', [
    'ticket_id' => $id, 'usuario_id' => Auth::userId(),
    'evento' => 'cambio_estado',
    'detalle' => "{$t['estado']} -> {$nuevo}" . ($motivo !== '' ? ' - ' . mb_substr($motivo, 0, 180) : ''),
]);

// ---- evidencias de la solucion (al resolver) ----
if ($nuevo === 'resuelto') TicketFlujo::guardarEvidencias($id);

Auditoria::registrar('cambiar_estado', 'tickets', $id,
    ['estado' => $t['estado']], ['estado' => $nuevo], [
        'modulo' => 'tickets', 'submodulo' => 'ciclo_vida',
        'accion_detalle' => $nuevo === 'resuelto' ? 'ticket_resuelto'
            : ($nuevo === 'cerrado' ? 'ticket_cerrado'
                : ($nuevo === 'en_atencion' && in_array($t['estado'], ['resuelto','cerrado'], true)
                    ? 'ticket_reapertura_actual'
                    : ($nuevo === 'en_atencion' ? 'ticket_atencion_iniciada' : 'ticket_estado_cambiado'))),
        'resultado' => 'exito', 'motivo' => $motivo !== '' ? $motivo : null,
    ]);

 $mensajes = [
    'asignado' => 'Ticket asignado.',
    'en_atencion' => 'Ticket en atencion.',
    'pendiente_usuario' => 'Ticket en espera del usuario.',
    'pendiente_proveedor' => 'Ticket en espera de proveedor.',
    'resuelto' => 'Ticket RESUELTO.',
    'cerrado' => 'Ticket CERRADO definitivamente.',
    'cancelado' => 'Ticket cancelado.',
];

// ---- notificaciones automaticas: solicitante + tecnico asignado ----
 $EST_TXT = [
    'abierto'=>'Abierto','asignado'=>'Asignado','en_atencion'=>'En atención',
    'pendiente_usuario'=>'Pendiente del usuario','pendiente_proveedor'=>'Pendiente del proveedor',
    'resuelto'=>'RESUELTO','cerrado'=>'CERRADO','cancelado'=>'Cancelado',
];
try {
    $solUsu = Database::getOne(
        "SELECT id FROM usuarios WHERE personal_id = ? AND estado = 'activo'",
        [(int)$t['solicitante_personal_id']]);
    if ($solUsu && (int)$solUsu['id'] !== Auth::userId()) {
        Notificador::avisar((int)$solUsu['id'], 'ticket',
            'Tu ticket ' . $t['codigo'] . ' cambió de estado',
            'Estado actual: ' . ($EST_TXT[$nuevo] ?? $nuevo) . '.',
            'tickets/lista');
    }
    if (!empty($t['tecnico_usuario_id']) && (int)$t['tecnico_usuario_id'] !== Auth::userId()) {
        Notificador::avisar((int)$t['tecnico_usuario_id'], 'ticket',
            'Ticket ' . $t['codigo'] . ': ' . ($EST_TXT[$nuevo] ?? $nuevo),
            'El ticket a tu cargo pasó de ' . $t['estado'] . ' a ' . $nuevo . '.',
            'tickets/lista');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] notif estado ticket: ' . $e->getMessage());
}

// al resolver / cerrar: si el equipo sigue en taller o con el proveedor, se avisa para finalizar su mantenimiento
 $mtAbiertos = [];
if (in_array($nuevo, ['resuelto', 'cerrado'], true)) {
    try { $mtAbiertos = MantenimientoFlujo::abiertosDeTicket((int)$id); } catch (Throwable $e) { /* opcional */ }
}
Response::ok(['estado' => $nuevo, 'mantenimientos_abiertos' => $mtAbiertos,
    'puede_finalizar_mt' => Auth::can('mantenimiento', 'cambiar_estado')], $mensajes[$nuevo] ?? 'Estado actualizado.');
