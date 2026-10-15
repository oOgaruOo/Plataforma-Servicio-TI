<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();

 $id      = (int)($_POST['id'] ?? 0);
 $mensaje = trim((string)($_POST['mensaje'] ?? ''));
 $interno = (int)($_POST['interno'] ?? 0) === 1;

 $v = Validator::make($_POST, ['mensaje' => 'required|minlen:2|maxlen:3000'],
    ['mensaje' => 'Mensaje']);
if ($v->fails()) Response::validation($v->errors());

 $t = Database::getOne('SELECT id, codigo, estado, solicitante_personal_id, tecnico_usuario_id FROM tickets WHERE id = ?', [$id]);
if (!$t) Response::error('El ticket no existe.');
if (in_array($t['estado'], ['cerrado','cancelado'], true)) {
    Response::error('El ticket esta cerrado/cancelado: no admite comentarios (reabralo primero).');
}

 $esSoporte = Auth::can('tickets', 'ver');
if ($interno && !$esSoporte) $interno = 0;

 $rol = Auth::rol();
if ($rol === 'usuario_final' && !$esSoporte) {
    $miPersonal = Database::getValue('SELECT personal_id FROM usuarios WHERE id = ?', [Auth::userId()]);
    if ((int)$miPersonal !== (int)$t['solicitante_personal_id']) {
        Response::forbidden('Solo puede comentar en sus propios tickets.');
    }
}

 $cid = Database::insert('ticket_comentarios', [
    'ticket_id'  => $id,
    'usuario_id' => Auth::userId(),
    'mensaje'    => $mensaje,
    'interno'    => $interno ? 1 : 0,
]);

if ($esSoporte) {
    Database::run(
        "UPDATE tickets SET fecha_primera_respuesta = COALESCE(fecha_primera_respuesta, NOW())
         WHERE id = ?", [$id]);
}

Auditoria::registrar('crear', 'ticket_comentarios', $cid, null, [
    'ticket_id' => $id,
    'interno'   => $interno ? 1 : 0,
    'longitud'  => mb_strlen($mensaje),
], [
    'modulo' => 'tickets', 'submodulo' => 'comentarios',
    'accion_detalle' => $interno ? 'comentario_tecnico_interno' : 'comentario_ticket',
    'resultado' => 'exito',
]);

Database::insert('ticket_historial', [
    'ticket_id' => $id, 'usuario_id' => Auth::userId(),
    'evento' => 'comentario', 'detalle' => $interno ? '(interno)' : '(visible)',
]);

// ---- notificaciones cruzadas: soporte <-> solicitante <-> tecnico ----
try {
    $vista = $interno ? 'observación interna' : 'nuevo comentario';
    $txt = 'Ticket ' . $t['codigo'] . ': ' . $vista;
    if ($esSoporte) {
        // soporte comenta -> avisar al solicitante (si no es interno) y al tecnico
        if (!$interno) {
            $solUsu = Database::getOne(
                "SELECT id FROM usuarios WHERE personal_id = ? AND estado = 'activo'",
                [(int)$t['solicitante_personal_id']]);
            if ($solUsu && (int)$solUsu['id'] !== Auth::userId()) {
                Notificador::avisar((int)$solUsu['id'], 'ticket', $txt,
                    mb_substr($mensaje, 0, 140), 'tickets/lista');
            }
        }
        if (!empty($t['tecnico_usuario_id']) && (int)$t['tecnico_usuario_id'] !== Auth::userId()) {
            Notificador::avisar((int)$t['tecnico_usuario_id'], 'ticket', $txt,
                mb_substr($mensaje, 0, 140), 'tickets/lista');
        }
    } else {
        // el usuario comenta -> avisar al tecnico; si no hay, a quienes pueden asignar
        $tecnico = !empty($t['tecnico_usuario_id']) ? [(int)$t['tecnico_usuario_id']] : [];
        if ($tecnico) {
            foreach ($tecnico as $uid) {
                if ($uid !== Auth::userId()) {
                    Notificador::avisar($uid, 'ticket', $txt,
                        mb_substr($mensaje, 0, 140), 'tickets/lista');
                }
            }
        } else {
            Notificador::avisarPermiso('tickets', 'asignar', 'ticket', $txt,
                mb_substr($mensaje, 0, 140), 'tickets/lista');
        }
    }
} catch (Throwable $e) {
    error_log('[SIGTI] notif comentario ticket: ' . $e->getMessage());
}

Response::ok(['id' => $cid], 'Comentario agregado.');
