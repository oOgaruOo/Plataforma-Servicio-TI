<?php
// Editar diagnostico / solucion / mano de obra / tecnico / observaciones
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('mantenimiento', 'editar');

 $id = (int)($_POST['id'] ?? 0);
 $m = Database::getOne('SELECT * FROM mantenimientos WHERE id = ?', [$id]);
if (!$m) Response::error('El mantenimiento no existe.');
if (in_array($m['estado'], ['cerrado','cancelado','no_reparable'], true)) {
    Response::error('Registro histórico: no admite ediciones.');
}

 $v = Validator::make($_POST, [
    'diagnostico'     => 'maxlen:2000',
    'solucion'        => 'maxlen:2000',
    'costo_mano_obra' => 'numeric|max:999999',
    'observaciones'   => 'maxlen:1000',
], ['diagnostico' => 'Diagnóstico', 'solucion' => 'Solución',
    'costo_mano_obra' => 'Mano de obra', 'observaciones' => 'Observaciones']);
if ($v->fails()) Response::validation($v->errors());

 $tecnicoId = $m['tecnico_usuario_id'];
if (trim((string)($_POST['tecnico_id'] ?? '')) !== '') {
    $tecnicoId = (int)$_POST['tecnico_id'];
    if (!Database::getValue('SELECT id FROM usuarios WHERE id = ? AND estado = "activo"', [$tecnicoId])) {
        Response::validation(['tecnico_id' => 'El técnico no existe o está inactivo.']);
    }
}

 $manoObra = trim((string)($_POST['costo_mano_obra'] ?? '')) !== ''
    ? (float)$_POST['costo_mano_obra'] : (float)$m['costo_mano_obra'];
 $costoTotal = $manoObra + (float)$m['costo_repuestos'];

 $nuevos = [
    'diagnostico'      => trim((string)($_POST['diagnostico'] ?? '')) ?: null,
    'solucion'         => trim((string)($_POST['solucion'] ?? '')) ?: null,
    'costo_mano_obra'  => $manoObra,
    'costo_total'      => $costoTotal,
    'tecnico_usuario_id' => $tecnicoId,
    'observaciones'    => trim((string)($_POST['observaciones'] ?? '')) ?: null,
];

Database::update('mantenimientos', $nuevos, 'id = ?', [$id]);
Auditoria::registrar('actualizar', 'mantenimientos', $id, $m, $nuevos);

Response::ok(['costo_total' => $costoTotal], 'Mantenimiento actualizado.');