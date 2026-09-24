<?php
// GET: lista solicitudes. POST accion=crear|aprobar|rechazar|cancelar
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Auth::requirePermission('asignaciones', 'ver');

    $pendientes = Database::get(
        "SELECT s.*, CONCAT(p.nombres,' ',p.apellidos) AS solicitante,
                t.nombre AS tipo_equipo, ar.nombre AS area
         FROM solicitudes_equipo s
         INNER JOIN personal p ON p.id = s.solicitante_personal_id
         INNER JOIN areas ar ON ar.id = s.area_id
         INNER JOIN tipo_equipos t ON t.id = s.tipo_equipo_id
         ORDER BY FIELD(s.estado,'pendiente','aprobada','atendida','rechazada','cancelada'), s.fecha_solicitud DESC");

    Response::ok($pendientes);
}

Csrf::validate();

 $accion = $_POST['accion'] ?? '';

if ($accion === 'crear') {
    Auth::requirePermission('asignaciones', 'solicitar');

    $v = Validator::make($_POST, [
        'solicitante_id' => 'required|integer',
        'tipo_equipo_id' => 'required|integer',
        'justificacion'  => 'required|minlen:10|maxlen:300',
    ], ['solicitante_id' => 'Solicitante', 'tipo_equipo_id' => 'Tipo de equipo',
        'justificacion' => 'Justificacion']);
    if ($v->fails()) Response::validation($v->errors());

    $sol = Database::getOne('SELECT id, area_id, estado FROM personal WHERE id = ?', [(int)$_POST['solicitante_id']]);
    if (!$sol) Response::validation(['solicitante_id' => 'El solicitante no existe.']);
    if (!Database::getValue('SELECT id FROM tipo_equipos WHERE id = ? AND activo = 1', [(int)$_POST['tipo_equipo_id']])) {
        Response::validation(['tipo_equipo_id' => 'El tipo de equipo no existe o esta inactivo.']);
    }

    $codigo = Correlativo::generar('solicitud');
    $id = Database::insert('solicitudes_equipo', [
        'codigo' => $codigo,
        'solicitante_personal_id' => (int)$_POST['solicitante_id'],
        'area_id' => (int)$sol['area_id'],
        'tipo_equipo_id' => (int)$_POST['tipo_equipo_id'],
        'cantidad' => max(1, min(10, (int)($_POST['cantidad'] ?? 1))),
        'justificacion' => trim($_POST['justificacion']),
        'estado' => 'pendiente',
    ]);
    Auditoria::registrar('crear', 'solicitudes_equipo', $id, null, ['codigo' => $codigo]);

    Response::ok(['id' => $id, 'codigo' => $codigo],
        "Solicitud $codigo registrada. Pendiente de aprobacion.");

} elseif ($accion === 'aprobar' || $accion === 'rechazar') {
    Auth::requirePermission('asignaciones', 'aprobar');

    $id = (int)($_POST['id'] ?? 0);
    $s = Database::getOne('SELECT * FROM solicitudes_equipo WHERE id = ?', [$id]);
    if (!$s) Response::error('La solicitud no existe.');
    if ($s['estado'] !== 'pendiente') {
        Response::error('La solicitud ya fue procesada (estado: ' . $s['estado'] . ').');
    }

    $nuevo = $accion === 'aprobar' ? 'aprobada' : 'rechazada';
    Database::update('solicitudes_equipo', [
        'estado' => $nuevo,
        'aprobado_por' => Auth::userId(),
        'fecha_aprobacion' => date('Y-m-d H:i:s'),
        'observaciones' => trim((string)($_POST['observaciones'] ?? '')) ?: null,
    ], 'id = ?', [$id]);

    Auditoria::registrar('aprobar', 'solicitudes_equipo', $id,
        ['estado' => 'pendiente'], ['estado' => $nuevo]);

    Response::ok(null, 'Solicitud ' . $s['codigo'] . ' ' . $nuevo . '.');

} elseif ($accion === 'cancelar') {
    $id = (int)($_POST['id'] ?? 0);
    $s = Database::getOne('SELECT * FROM solicitudes_equipo WHERE id = ?', [$id]);
    if (!$s) Response::error('La solicitud no existe.');
    if (!in_array($s['estado'], ['pendiente','aprobada'], true)) {
        Response::error('Solo se pueden cancelar solicitudes pendientes o aprobadas.');
    }
    Database::update('solicitudes_equipo', ['estado' => 'cancelada'], 'id = ?', [$id]);
    Auditoria::registrar('cambiar_estado', 'solicitudes_equipo', $id, $s, ['estado' => 'cancelada']);
    Response::ok(null, 'Solicitud cancelada.');

} else {
    Response::error('Accion invalida.');
}