<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Registrar mantenimiento v2:
// - ticket_id (dropdown, no codigo)
// - destino_envio (distrito/departamento)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('mantenimiento', 'crear');

 $v = Validator::make($_POST, [
    'equipo_id'   => 'required|integer',
    'tipo'        => 'required|in:correctivo,preventivo,limpieza,actualizacion',
    'diagnostico' => 'maxlen:2000',
    'destino_envio' => 'maxlen:150',
], ['equipo_id' => 'Equipo', 'tipo' => 'Tipo', 'diagnostico' => 'Diagnóstico',
    'destino_envio' => 'Destino de envío']);
if ($v->fails()) Response::validation($v->errors());

 $equipoId = (int)$_POST['equipo_id'];
 $eq = Database::getOne('SELECT id, codigo, estado FROM equipos WHERE id = ?', [$equipoId]);
if (!$eq) Response::validation(['equipo_id' => 'El equipo no existe.']);
if ($eq['estado'] === 'dado_de_baja') {
    Response::validation(['equipo_id' => 'El equipo está dado de baja.']);
}

// un solo mantenimiento activo por equipo
 $enCurso = Database::getValue(
    "SELECT id FROM mantenimientos
     WHERE equipo_id = ? AND estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')",
    [$equipoId]);
if ($enCurso) {
    Response::validation(['equipo_id' => 'Ese equipo ya tiene un mantenimiento en curso.']);
}

// ticket opcional (por ID, validado)
 $ticketId = null;
 $tic = trim((string)($_POST['ticket_id'] ?? ''));
if ($tic !== '') {
    $ticketId = (int)$tic;
    $tk = Database::getOne(
        'SELECT id, estado, equipo_id FROM tickets WHERE id = ?', [$ticketId]);
    if (!$tk) {
        Response::validation(['ticket_id' => 'El ticket seleccionado no existe.']);
    }
    if (in_array($tk['estado'], ['resuelto', 'cerrado', 'cancelado'], true)) {
        Response::validation(['ticket_id' => 'El ticket seleccionado ya está ' . $tk['estado'] . '.']);
    }
    if ($tk['equipo_id'] && (int)$tk['equipo_id'] !== $equipoId) {
        Response::validation(['ticket_id' => 'El ticket seleccionado es de otro equipo.']);
    }
    if (Database::getValue(
            "SELECT id FROM mantenimientos WHERE ticket_id = ? AND estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock') LIMIT 1",
            [$ticketId])) {
        Response::validation(['ticket_id' => 'Ese ticket ya tiene un mantenimiento en curso.']);
    }
}

// tecnico opcional (personal interno)
 $tecnicoId = null;
if (trim((string)($_POST['tecnico_id'] ?? '')) !== '') {
    $tecnicoId = (int)$_POST['tecnico_id'];
    if (!Database::getValue('SELECT id FROM usuarios WHERE id = ? AND estado = "activo"', [$tecnicoId])) {
        Response::validation(['tecnico_id' => 'El técnico no existe o está inactivo.']);
    }
}

// proveedor opcional (mantenimiento externo) - mutuamente excluyente con tecnico
 $proveedorId = null;
if (trim((string)($_POST['proveedor_id'] ?? '')) !== '') {
    $proveedorId = (int)$_POST['proveedor_id'];
    if (!Database::getValue("SELECT id FROM proveedores WHERE id = ? AND estado = 'activo'", [$proveedorId])) {
        Response::validation(['proveedor_id' => 'El proveedor no existe o está inactivo.']);
    }
}
if ($tecnicoId && $proveedorId) {
    Response::validation(['proveedor_id' => 'Elige solo uno: personal interno O proveedor externo.']);
}

 $destino = trim((string)($_POST['destino_envio'] ?? '')) ?: null;

// fecha estimada de retorno (solo si va a un proveedor externo)
 $retorno = null;
if ($proveedorId) {
    $rt = trim((string)($_POST['fecha_retorno'] ?? ''));
    if ($rt !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $rt);
        if (!$dt || $dt->format('Y-m-d') !== $rt) {
            Response::validation(['fecha_retorno' => 'Fecha de retorno inválida.']);
        }
        $retorno = $rt;
    }
}

// SLA interna por tipo de mantenimiento (dias)
 $SLA_DIAS = ['correctivo' => 5, 'preventivo' => 10, 'limpieza' => 5, 'actualizacion' => 30];
 $slaDias = $SLA_DIAS[$_POST['tipo']] ?? 5;
 $slaLimite = (new DateTime('now'))->add(new DateInterval('P' . $slaDias . 'D'))->format('Y-m-d 23:59:59');

Database::begin();
try {
    $codigo = Correlativo::generar('mantenimiento');

    $nuevoId = Database::insert('mantenimientos', [
        'codigo'         => $codigo,
        'equipo_id'      => $equipoId,
        'ticket_id'      => $ticketId,
        'tipo'           => $_POST['tipo'],
        'origen'         => $ticketId ? 'ticket' : 'solicitud',
        'tecnico_usuario_id' => $tecnicoId,
        'proveedor_id'   => $proveedorId,
        'estado'         => $proveedorId ? 'enviado_proveedor' : 'solicitado',
        'fecha_envio_proveedor'  => $proveedorId ? date('Y-m-d H:i:s') : null,
        'fecha_retorno_estimada' => $retorno,
        'diagnostico'    => trim((string)($_POST['diagnostico'] ?? '')) ?: null,
        'fecha_inicio'   => date('Y-m-d H:i:s'),
        'sla_limite'     => $slaLimite,
        'costo_mano_obra'=> 0,
        'costo_repuestos'=> 0,
        'costo_total'    => 0,
        'destino_envio'  => $destino,
    ]);

    // con proveedor externo el equipo sale de la empresa
    Database::update('equipos', ['estado' => $proveedorId ? 'en_reparacion_externa' : 'en_mantenimiento'], 'id = ?', [$equipoId]);
    Database::insert('equipo_historial', [
        'equipo_id' => $equipoId, 'usuario_id' => Auth::userId(),
        'evento' => 'mantenimiento',
        'detalle' => "Ingresó a mantenimiento ($codigo)" .
            ($destino ? ' · destino: ' . $destino : ''),
    ]);

    Database::commit();
    Auditoria::registrar('crear', 'mantenimientos', $nuevoId, null,
        ['codigo' => $codigo, 'equipo' => $eq['codigo'], 'tipo' => $_POST['tipo'],
         'asignado' => $proveedorId ? 'proveedor' : ($tecnicoId ? 'interno' : 'sin asignar'),
         'sla_limite' => $slaLimite, 'destino' => $destino]);

    Response::ok(['id' => $nuevoId, 'codigo' => $codigo, 'abrir_detalle' => true],
        "Mantenimiento $codigo registrado (SLA interno: $slaDias días, límite " .
        substr($slaLimite, 0, 10) . "). Equipo {$eq['codigo']} bloqueado para asignaciones.");

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al registrar el mantenimiento: ' . $e->getMessage());
}