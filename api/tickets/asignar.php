<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('tickets', 'asignar');

 $id      = (int)($_POST['id'] ?? 0);
 $tecnico = (int)($_POST['tecnico_id'] ?? 0);
 $nivel   = (int)($_POST['nivel'] ?? 1);

 $t = Database::getOne('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$t) Response::error('El ticket no existe.');
if (in_array($t['estado'], ['cerrado','cancelado'], true)) {
    Response::error('No se puede asignar un ticket cerrado o cancelado.');
}
if (!in_array($nivel, [1,2,3], true)) $nivel = 1;

 $u = Database::getOne('SELECT id, nombre_completo, estado FROM usuarios WHERE id = ?', [$tecnico]);
if (!$u || $u['estado'] !== 'activo') {
    Response::validation(['tecnico_id' => 'El tecnico seleccionado no existe o no esta activo.']);
}

 $antes = $t['tecnico_usuario_id'];

Database::update('tickets', [
    'tecnico_usuario_id' => $tecnico,
    'estado'             => 'asignado',
    'nivel_atencion'     => $nivel,
    'fecha_asignacion'   => date('Y-m-d H:i:s'),
    'fecha_primera_respuesta' => $t['fecha_primera_respuesta'] ?: date('Y-m-d H:i:s'),
], 'id = ?', [$id]);

Database::insert('ticket_historial', [
    'ticket_id' => $id, 'usuario_id' => Auth::userId(),
    'evento' => 'asignado',
    'detalle' => 'A ' . $u['nombre_completo'] . ' (N' . $nivel . ')'
               . ($antes ? ' - reasignacion' : ''),
]);
Auditoria::registrar('actualizar', 'tickets', $id,
    ['tecnico' => $antes], ['tecnico' => $tecnico, 'nivel' => $nivel], [
        'modulo' => 'tickets', 'submodulo' => 'asignacion',
        'accion_detalle' => $antes ? 'ticket_reasignado' : 'ticket_asignado',
        'resultado' => 'exito',
    ]);

Notificador::avisar($tecnico, 'ticket',
    'Ticket asignado a ti',
    'Se te asignó un ticket (N' . $nivel . '). Revísalo y empieza la atención.',
    'tickets/lista');

// avisar al solicitante que su ticket ya tiene tecnico
try {
    $solUsu = Database::getOne(
        "SELECT id FROM usuarios WHERE personal_id = ? AND estado = 'activo'",
        [(int)$t['solicitante_personal_id']]);
    if ($solUsu && (int)$solUsu['id'] !== Auth::userId() && (int)$solUsu['id'] !== $tecnico) {
        Notificador::avisar((int)$solUsu['id'], 'ticket',
            'Tu ticket ' . $t['codigo'] . ' ya tiene técnico',
            'Asignado a ' . $u['nombre_completo'] . ' (N' . $nivel . ').',
            'tickets/lista');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] notif asignacion solicitante: ' . $e->getMessage());
}
Response::ok(null, 'Ticket asignado a ' . $u['nombre_completo'] . '.');
