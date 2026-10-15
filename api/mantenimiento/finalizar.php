<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - FINALIZAR un mantenimiento / soporte en un solo paso
//
// Antes habia que ir cambiando el estado a mano (Listo -> Entregado -> Cerrado).
// Aqui el tecnico indica el trabajo realizado y el sistema:
//   1. recorre los estados que falten hasta "Listo"
//   2. devuelve el equipo a su destino (persona, area, proyecto u obra) o a stock
//   3. cierra el mantenimiento con su fecha de fin
//   4. opcionalmente resuelve el ticket de soporte vinculado
// No salta cotizaciones ni aprobaciones de costo (enviado_proveedor / cotizado / aprobado).
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('mantenimiento', 'cambiar_estado');

 $id = (int)($_POST['id'] ?? 0);
 $m = Database::getOne('SELECT * FROM mantenimientos WHERE id = ?', [$id]);
if (!$m) Response::error('El mantenimiento no existe.');

if (in_array($m['estado'], MantenimientoFlujo::TERMINALES, true)) {
    Response::error('Este mantenimiento ya está ' . $m['estado'] . ' y no admite cambios.');
}
$tkEstado = $m['ticket_id'] ? Database::getValue('SELECT estado FROM tickets WHERE id = ?', [$m['ticket_id']]) : null;
if (!MantenimientoFlujo::finalizableConTicket($m['estado'], $tkEstado ?: null)) {
    Response::error('Todavía no se puede finalizar desde «' . $m['estado'] . '»: falta la cotización o la aprobación '
        . 'del costo. Continúe con «Cambiar estado» hasta que el trabajo esté en reparación.');
}
// con el proveedor y el ticket ya atendido: se finaliza sin completar la cotizacion
$sinCotizar = !MantenimientoFlujo::finalizable($m['estado']);

 $v = Validator::make($_POST, [
    'solucion'        => 'required|maxlen:2000',
    'costo_mano_obra' => 'numeric|max:999999',
    'observaciones'   => 'maxlen:500',
], ['solucion' => 'Trabajo realizado', 'costo_mano_obra' => 'Mano de obra', 'observaciones' => 'Observaciones']);
if ($v->fails()) Response::validation($v->errors());

 $solucion = trim($_POST['solucion']);
 $quiereTicket = ($_POST['resolver_ticket'] ?? '0') === '1';

// ---- constancia del cierre: resultado, percance (opcional) y motivo del atraso ----
MantenimientoFlujo::asegurar();            // DDL antes de abrir la transaccion
TicketFlujo::asegurar();
Programados::asegurar();
 $resultado = trim((string)($_POST['resultado'] ?? 'completado'));
if (!isset(MantenimientoFlujo::RESULTADOS[$resultado])) {
    Response::validation(['resultado' => 'Indique cómo terminó el trabajo.']);
}
 $pTipo = trim((string)($_POST['percance_tipo'] ?? ''));
 $pDetalle = trim((string)($_POST['percance_detalle'] ?? ''));
if ($pTipo !== '' || $pDetalle !== '') {
    if (!isset(MantenimientoFlujo::PERCANCES[$pTipo])) Response::validation(['percance_tipo' => 'Seleccione el tipo de percance.']);
    if ($pDetalle === '') Response::validation(['percance_detalle' => 'Describa brevemente el percance.']);
    if (mb_strlen($pDetalle) > 250) Response::validation(['percance_detalle' => 'Máximo 250 caracteres.']);
}
 $cierre = MantenimientoFlujo::datosCierre($m, $resultado, (string)($_POST['motivo_atraso'] ?? ''));

 $eq = Database::getOne('SELECT id, codigo, estado FROM equipos WHERE id = ?', [$m['equipo_id']]);
if (!$eq) Response::error('El equipo del mantenimiento ya no existe.');

// ---- pasos que se recorren ----
 $pasos = MantenimientoFlujo::rutaFinalizar($m['estado']);
 [$estadoEquipo, $asig] = MantenimientoFlujo::estadoAlVolver((int)$m['equipo_id']);
 $destinoTxt = MantenimientoFlujo::destinoTexto((int)$m['equipo_id']);
if ($m['estado'] !== 'entregado' && $m['estado'] !== 'devuelto_stock') {
    $pasos[] = $asig ? 'entregado' : 'devuelto_stock';
}
 $pasos[] = 'cerrado';

 $datos = [
    'estado'    => 'cerrado',
    'solucion'  => $solucion,
    'fecha_fin' => date('Y-m-d H:i:s'),
] + $cierre;
 $obs = trim((string)($_POST['observaciones'] ?? ''));
if ($obs !== '') {
    $datos['observaciones'] = mb_substr(trim((string)$m['observaciones'] . "\n" . $obs), 0, 1000);
}
if (trim((string)($_POST['costo_mano_obra'] ?? '')) !== '') {
    $datos['costo_mano_obra'] = (float)$_POST['costo_mano_obra'];
    $datos['costo_total'] = $datos['costo_mano_obra'] + (float)$m['costo_repuestos'];
}
// quien lo finaliza queda como tecnico si el trabajo fue interno y no habia uno asignado
if (!$m['tecnico_usuario_id'] && !$m['proveedor_id']) {
    $datos['tecnico_usuario_id'] = Auth::userId();
}

 $ticketMsg = null;
 $ticketResuelto = false;
 $prog = null;     // programacion preventiva a la que pertenece (si la hay)

Database::begin();
try {
    Database::update('mantenimientos', $datos, 'id = ?', [$id]);

    if ($eq['estado'] !== 'dado_de_baja') {
        Database::update('equipos', ['estado' => $estadoEquipo], 'id = ?', [$m['equipo_id']]);
    }
    if ($pTipo !== '') {
        Database::insert('mantenimiento_percances', [
            'mantenimiento_id' => $id, 'tipo' => $pTipo, 'detalle' => $pDetalle,
            'usuario_id' => Auth::userId(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::insert('equipo_historial', [
            'equipo_id' => $m['equipo_id'], 'usuario_id' => Auth::userId(), 'evento' => 'mantenimiento',
            'detalle' => "MT {$m['codigo']}: percance · " . MantenimientoFlujo::PERCANCES[$pTipo] . ' — ' . mb_substr($pDetalle, 0, 150),
        ]);
    }
    Database::insert('equipo_historial', [
        'equipo_id' => $m['equipo_id'], 'usuario_id' => Auth::userId(),
        'evento' => 'mantenimiento',
        'detalle' => "MT {$m['codigo']} finalizado ({$m['estado']} → cerrado) · " . MantenimientoFlujo::RESULTADOS[$resultado]
            . ($cierre['atraso_min'] > 0 ? ' · CON ATRASO de ' . MantenimientoFlujo::textoAtraso($cierre['atraso_min']) : ' · a tiempo')
            . " · equipo → {$estadoEquipo}"
            . ($asig ? " · vuelve a: $destinoTxt" : ' · vuelve a stock')
            . ($sinCotizar ? " · el ticket vinculado ya estaba {$tkEstado}: se finaliza sin completar la cotización" : ''),
    ]);

    // ---- preventivo programado: se anota la fecha real y se calcula SOLA la proxima ----
    $prog = Programados::alCerrar($m, $datos['fecha_fin']);

    // ---- ticket de soporte vinculado ----
    if ($quiereTicket && $m['ticket_id']) {
        $t = Database::getOne('SELECT id, codigo, estado, solicitante_personal_id FROM tickets WHERE id = ?', [$m['ticket_id']]);
        if (!$t) {
            $ticketMsg = 'El ticket vinculado ya no existe.';
        } elseif (!Auth::can('tickets', 'cambiar_estado')) {
            $ticketMsg = "No tiene permiso para resolver tickets: resuelva {$t['codigo']} desde Tickets.";
        } elseif (in_array($t['estado'], ['en_atencion', 'pendiente_usuario', 'pendiente_proveedor'], true)) {
            $atrTk = TicketFlujo::atrasoMin(Database::getOne('SELECT sla_limite FROM tickets WHERE id = ?', [$t['id']]) ?: []);
            Database::update('tickets', [
                'estado' => 'resuelto', 'solucion' => $solucion, 'fecha_resolucion' => date('Y-m-d H:i:s'),
                'tipo_solucion' => 'reparacion', 'resuelto_por' => Auth::userId(),
                'motivo_retraso' => $atrTk > 0 ? mb_substr("Resuelto al finalizar el mantenimiento {$m['codigo']}"
                    . ($cierre['motivo_atraso'] ? ': ' . $cierre['motivo_atraso'] : ''), 0, 250) : null,
            ], 'id = ?', [$t['id']]);
            Database::insert('ticket_historial', [
                'ticket_id' => $t['id'], 'usuario_id' => Auth::userId(), 'evento' => 'cambio_estado',
                'detalle' => "{$t['estado']} -> resuelto - resuelto al finalizar el mantenimiento {$m['codigo']}",
            ]);
            $ticketResuelto = true;
            $ticketMsg = "Ticket {$t['codigo']} marcado como RESUELTO.";
        } elseif (in_array($t['estado'], ['resuelto', 'cerrado'], true)) {
            $ticketMsg = "El ticket {$t['codigo']} ya estaba {$t['estado']}.";
        } else {
            $ticketMsg = "El ticket {$t['codigo']} sigue «{$t['estado']}»: tómelo en atención desde Tickets para poder resolverlo.";
        }
    }

    Database::commit();
} catch (Throwable $e) {
    Database::rollback();
    Response::error('No se pudo finalizar el mantenimiento: ' . $e->getMessage());
}

Auditoria::registrar('cambiar_estado', 'mantenimientos', $id,
    ['estado' => $m['estado']],
    ['estado' => 'cerrado', 'pasos' => implode(' → ', $pasos), 'equipo_estado' => $estadoEquipo,
     'ticket_resuelto' => $ticketResuelto, 'resultado' => $resultado, 'atraso_min' => $cierre['atraso_min'],
     'percance' => $pTipo !== '' ? $pTipo : null]);

// aviso a quien recibe el equipo de vuelta (si tiene usuario en el sistema)
try {
    if ($asig) {
        $d = Database::getOne('SELECT COALESCE(responsable_id, personal_id) AS quien FROM asignaciones WHERE id = ?', [$asig['id']]);
        $u = $d && $d['quien'] ? Database::getOne(
            "SELECT id FROM usuarios WHERE personal_id = ? AND estado = 'activo'", [(int)$d['quien']]) : null;
        if ($u && (int)$u['id'] !== Auth::userId()) {
            Notificador::avisar((int)$u['id'], 'sistema',
                'Equipo ' . $eq['codigo'] . ' listo',
                'El mantenimiento ' . $m['codigo'] . ' terminó y el equipo vuelve a usted.', 'equipos/lista');
        }
    }
} catch (Throwable $e) {
    // el aviso es opcional
}

$mensaje = "Mantenimiento {$m['codigo']} FINALIZADO"
    . ($cierre['atraso_min'] > 0 ? ' con ' . MantenimientoFlujo::textoAtraso($cierre['atraso_min']) . ' de atraso' : ' a tiempo')
    . ". Equipo {$eq['codigo']} "
    . ($asig ? "vuelve a: $destinoTxt." : 'devuelto a stock.')
    . ($ticketMsg ? ' ' . $ticketMsg : '')
    . ($prog ? ' Próximo mantenimiento programado (' . $prog['frecuencia'] . '): ' . $prog['proxima'] . '.' : '');

// "mensaje" va dentro de data porque el JS de la app solo recibe data
Response::ok([
    'estado'        => 'cerrado',
    'equipo_estado' => $estadoEquipo,
    'destino'       => $asig ? $destinoTxt : 'stock',
    'resultado'     => $resultado,
    'atraso_min'    => $cierre['atraso_min'],
    'a_tiempo'      => $cierre['atraso_min'] === 0,
    'ticket'        => $ticketMsg,
    'programado'    => $prog,
    'mensaje'       => $mensaje,
], $mensaje);
