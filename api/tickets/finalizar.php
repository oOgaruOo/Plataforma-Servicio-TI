<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - FINALIZAR un ticket (boton «Finalizar / Terminar»)
//
// En un solo paso el tecnico deja constancia de:
//   - QUE hizo (solucion) y COMO se resolvio (tipo de solucion)
//   - CUANTO tiempo le tomo (opcional)
//   - CUANDO termino (fecha y hora) y QUIEN lo hizo
//   - si termino fuera del plazo (SLA): el MOTIVO del retraso es obligatorio
// y el ticket pasa a RESUELTO (o a CERRADO si marca «cerrar de una vez»).
// Si el ticket no tenia tecnico, queda a nombre de quien lo finaliza.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('tickets', 'cambiar_estado');

 $id = (int)($_POST['id'] ?? 0);
 $t = Database::getOne('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$t) Response::error('El ticket no existe.');

if (!TicketFlujo::finalizable($t['estado'])) {
    $txt = [
        'resuelto'  => 'Este ticket ya está RESUELTO. Use «Cerrar definitivamente» o «Reabrir».',
        'cerrado'   => 'Este ticket ya está CERRADO.',
        'cancelado' => 'Este ticket está cancelado y no se puede finalizar.',
    ];
    Response::error($txt[$t['estado']] ?? 'El ticket no se puede finalizar desde «' . $t['estado'] . '».');
}

 $solucion = trim((string)($_POST['solucion'] ?? ''));
 $tipo = trim((string)($_POST['tipo_solucion'] ?? ''));
 $tiempoTxt = trim((string)($_POST['tiempo_min'] ?? ''));
 $motivoRet = trim((string)($_POST['motivo_retraso'] ?? ''));
 $cerrar = ($_POST['cerrar'] ?? '0') === '1';

 $err = [];
if ($solucion === '') $err['solucion'] = 'Describa la solución aplicada.';
elseif (mb_strlen($solucion) > 2000) $err['solucion'] = 'La solución admite hasta 2000 caracteres.';
if (!isset(TicketFlujo::TIPOS_SOLUCION[$tipo])) $err['tipo_solucion'] = 'Indique cómo se resolvió.';
 $tiempo = null;
if ($tiempoTxt !== '') {
    if (!ctype_digit($tiempoTxt) || (int)$tiempoTxt > 99999) $err['tiempo_min'] = 'El tiempo invertido debe ser un número de minutos.';
    else $tiempo = (int)$tiempoTxt;
}
if (mb_strlen($motivoRet) > 250) $err['motivo_retraso'] = 'El motivo admite hasta 250 caracteres.';
if ($err) Response::validation($err);

 $ahora = time();
 $fecha = date('Y-m-d H:i:s', $ahora);
 $atraso = TicketFlujo::atrasoMin($t, $ahora);
if ($atraso > 0 && $motivoRet === '') {
    Response::validation(['motivo_retraso' => 'Este ticket se finaliza con ' . TicketFlujo::texto($atraso)
        . ' de retraso sobre el SLA. Indique el motivo del retraso.']);
}

TicketFlujo::asegurar();                  // DDL antes de la transaccion

 $uid = Auth::userId();
 $nuevo = $cerrar ? 'cerrado' : 'resuelto';
 $datos = [
    'estado'               => $nuevo,
    'solucion'             => $solucion,
    'tipo_solucion'        => $tipo,
    'tiempo_invertido_min' => $tiempo,
    'fecha_resolucion'     => $fecha,
    'resuelto_por'         => $uid,
    'motivo_retraso'       => $atraso > 0 ? mb_substr($motivoRet, 0, 250) : null,
    'fecha_primera_respuesta' => $t['fecha_primera_respuesta'] ?: $fecha,
];
if ($cerrar) { $datos['fecha_cierre'] = $fecha; $datos['cerrado_por'] = $uid; }
 $autoAsignado = empty($t['tecnico_usuario_id']);
if ($autoAsignado) {
    $datos['tecnico_usuario_id'] = $uid;
    $datos['fecha_asignacion'] = $t['fecha_asignacion'] ?: $fecha;
}

 $quien = (string)Database::getValue('SELECT nombre_completo FROM usuarios WHERE id = ?', [$uid]);

Database::begin();
try {
    Database::update('tickets', $datos, 'id = ?', [$id]);

    if ($autoAsignado) {
        Database::insert('ticket_historial', [
            'ticket_id' => $id, 'usuario_id' => $uid, 'evento' => 'asignado',
            'detalle' => 'A ' . $quien . ' (tomó el ticket al finalizarlo)',
        ]);
    }
    Database::insert('ticket_historial', [
        'ticket_id' => $id, 'usuario_id' => $uid, 'evento' => 'finalizado',
        'detalle' => "{$t['estado']} -> {$nuevo} · " . TicketFlujo::TIPOS_SOLUCION[$tipo]
            . ($tiempo !== null ? ' · ' . TicketFlujo::texto($tiempo) . ' de trabajo' : '')
            . ($atraso > 0 ? ' · CON RETRASO de ' . TicketFlujo::texto($atraso) . ': ' . mb_substr($motivoRet, 0, 120) : ' · dentro del SLA'),
    ]);

    if (!Auditoria::registrar('cambiar_estado', 'tickets', $id,
        ['estado' => $t['estado']],
        ['estado' => $nuevo, 'tipo_solucion' => $tipo, 'tiempo_min' => $tiempo, 'atraso_min' => $atraso,
         'motivo_retraso' => $atraso > 0 ? $motivoRet : null], [
            'modulo' => 'tickets', 'submodulo' => 'ciclo_vida',
            'accion_detalle' => $cerrar ? 'ticket_finalizado_y_cerrado' : 'ticket_finalizado',
            'resultado' => 'exito',
        ])) {
        Database::rollback();
        Response::error('No se pudo registrar la auditoría; el ticket no se finalizó.', null, 500);
    }
    Database::commit();
} catch (Throwable $e) {
    Database::rollback();
    Response::error('No se pudo finalizar el ticket: ' . $e->getMessage());
}

// evidencias (fuera de la transaccion: son archivos)
 $nEv = 0;
try { $nEv = TicketFlujo::guardarEvidencias($id); } catch (Throwable $e) { error_log('[SIGTI] evidencias finalizar: ' . $e->getMessage()); }

// avisos al solicitante y al tecnico
try {
    $solUsu = Database::getOne("SELECT id FROM usuarios WHERE personal_id = ? AND estado = 'activo'",
        [(int)$t['solicitante_personal_id']]);
    if ($solUsu && (int)$solUsu['id'] !== $uid) {
        Notificador::avisar((int)$solUsu['id'], 'ticket', 'Tu ticket ' . $t['codigo'] . ' fue ' . ($cerrar ? 'cerrado' : 'resuelto'),
            'Solución: ' . mb_substr($solucion, 0, 150), 'tickets/lista');
    }
    if (!$autoAsignado && (int)$t['tecnico_usuario_id'] !== $uid) {
        Notificador::avisar((int)$t['tecnico_usuario_id'], 'ticket', 'Ticket ' . $t['codigo'] . ' finalizado',
            $quien . ' finalizó el ticket a tu cargo.', 'tickets/lista');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] notif finalizar ticket: ' . $e->getMessage());
}

// el equipo sigue en taller / con el proveedor mientras su mantenimiento este abierto
 $mtAbiertos = [];
try { $mtAbiertos = MantenimientoFlujo::abiertosDeTicket((int)$id); } catch (Throwable $e) { /* opcional */ }

Response::ok([
    'mantenimientos_abiertos' => $mtAbiertos,
    'puede_finalizar_mt'      => Auth::can('mantenimiento', 'cambiar_estado'),
    'estado'     => $nuevo,
    'fecha'      => $fecha,
    'a_tiempo'   => $atraso === 0,
    'atraso_min' => $atraso,
    'evidencias' => $nEv,
    'mensaje'    => 'Ticket ' . $t['codigo'] . ($cerrar ? ' FINALIZADO y CERRADO' : ' FINALIZADO (resuelto)')
        . ($atraso > 0 ? ' con ' . TicketFlujo::texto($atraso) . ' de retraso.' : ' dentro del SLA.'),
]);
