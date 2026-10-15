<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Vacaciones: solicitar, aprobar, rechazar y cancelar  (rrhh.gestionar)
//   POST accion = crear | aprobar | rechazar | cancelar
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', null, 405);
    Csrf::validate();
    Auth::requirePermission('rrhh', 'gestionar');
    Vacaciones::asegurar();

    $accion = (string)($_POST['accion'] ?? '');
    $uid = Auth::userId();

    if ($accion === 'crear') {
        $v = Validator::make($_POST, [
            'personal_id'  => 'required|integer',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date',
            'observaciones'=> 'maxlen:200',
        ], ['personal_id' => 'Persona', 'fecha_inicio' => 'Fecha de inicio', 'fecha_fin' => 'Fecha de fin', 'observaciones' => 'Observaciones']);
        if ($v->fails()) Response::validation($v->errors());
        $personalId = (int)$_POST['personal_id'];
        if (!Database::getValue('SELECT id FROM personal WHERE id = ?', [$personalId])) Response::validation(['personal_id' => 'La persona no existe.']);
        $forzar = !empty($_POST['forzar']);
        $r = Vacaciones::crear($personalId, (string)$_POST['fecha_inicio'], (string)$_POST['fecha_fin'], $_POST['observaciones'] ?? null, $uid, $forzar);
        if (!$r['ok']) Response::error($r['error']);
        Auditoria::registrar('crear', 'rrhh_vacaciones', $r['id'], null, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'vacaciones']);
        Response::ok(['id' => $r['id']], 'Solicitud de vacaciones registrada.');

    } elseif ($accion === 'aprobar') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT * FROM rrhh_vacaciones WHERE id = ?', [$id]);
        if (!$antes) Response::error('La solicitud ya no existe.');
        if (!Vacaciones::aprobar($id, $uid)) Response::error('Solo se aprueba una solicitud Pendiente.');
        Auditoria::registrar('cambiar_estado', 'rrhh_vacaciones', $id, $antes, ['estado' => 'aprobada'], ['modulo' => 'rrhh', 'submodulo' => 'vacaciones']);
        Response::ok(null, 'Solicitud aprobada.');

    } elseif ($accion === 'rechazar') {
        $id = (int)($_POST['id'] ?? 0);
        $v = Validator::make($_POST, ['motivo' => 'required|maxlen:200'], ['motivo' => 'Motivo']);
        if ($v->fails()) Response::validation($v->errors());
        $antes = Database::getOne('SELECT * FROM rrhh_vacaciones WHERE id = ?', [$id]);
        if (!$antes) Response::error('La solicitud ya no existe.');
        if (!Vacaciones::rechazar($id, trim((string)$_POST['motivo']), $uid)) Response::error('Solo se rechaza una solicitud Pendiente.');
        Auditoria::registrar('cambiar_estado', 'rrhh_vacaciones', $id, $antes, ['estado' => 'rechazada'], ['modulo' => 'rrhh', 'submodulo' => 'vacaciones']);
        Response::ok(null, 'Solicitud rechazada.');

    } elseif ($accion === 'cancelar') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT * FROM rrhh_vacaciones WHERE id = ?', [$id]);
        if (!$antes) Response::error('La solicitud ya no existe.');
        if (!Vacaciones::cancelar($id)) Response::error('Solo se cancela una solicitud Pendiente o Aprobada.');
        Auditoria::registrar('cambiar_estado', 'rrhh_vacaciones', $id, $antes, ['estado' => 'cancelada'], ['modulo' => 'rrhh', 'submodulo' => 'vacaciones']);
        Response::ok(null, 'Solicitud cancelada.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] vacaciones/gestionar: ' . $e->getMessage());
    Response::error('No se pudo completar la operación.', null, 500);
}
