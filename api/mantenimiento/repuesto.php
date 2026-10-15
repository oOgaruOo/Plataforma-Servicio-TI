<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// Repuestos usados: agregar / quitar (recalcula costos)
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('mantenimiento', 'editar');

 $accion = $_POST['accion'] ?? '';
 $id     = (int)($_POST['mantenimiento_id'] ?? 0);

 $m = Database::getOne('SELECT * FROM mantenimientos WHERE id = ?', [$id]);
if (!$m) Response::error('El mantenimiento no existe.');
if (in_array($m['estado'], ['cerrado','cancelado','no_reparable'], true)) {
    Response::error('Registro histórico: no admite repuestos.');
}

function recalcularCostos(int $mantId): array {
    $rep = (float) (Database::getValue(
        "SELECT COALESCE(SUM(cantidad * costo_unitario), 0)
         FROM mantenimiento_repuestos WHERE mantenimiento_id = ?", [$mantId]) ?? 0);
    $mo = (float) Database::getValue('SELECT costo_mano_obra FROM mantenimientos WHERE id = ?', [$mantId]);
    $total = $mo + $rep;
    Database::update('mantenimientos',
        ['costo_repuestos' => $rep, 'costo_total' => $total], 'id = ?', [$mantId]);
    return ['repuestos' => $rep, 'mano_obra' => $mo, 'total' => $total];
}

if ($accion === 'agregar') {
    $v = Validator::make($_POST, [
        'descripcion'    => 'required|maxlen:150',
        'cantidad'       => 'required|numeric|min:0.01|max:9999',
        'costo_unitario' => 'required|numeric|min:0|max:999999',
    ], ['descripcion' => 'Descripción', 'cantidad' => 'Cantidad', 'costo_unitario' => 'Costo unitario']);
    if ($v->fails()) Response::validation($v->errors());

    $provId = null;
    if (trim((string)($_POST['proveedor_id'] ?? '')) !== '') {
        $provId = (int)$_POST['proveedor_id'];
        if (!Database::getValue('SELECT id FROM proveedores WHERE id = ?', [$provId])) {
            Response::validation(['proveedor_id' => 'El proveedor no existe.']);
        }
    }

    Database::insert('mantenimiento_repuestos', [
        'mantenimiento_id' => $id,
        'descripcion'      => trim($_POST['descripcion']),
        'cantidad'         => (float)$_POST['cantidad'],
        'costo_unitario'   => (float)$_POST['costo_unitario'],
        'proveedor_id'     => $provId,
    ]);
    Auditoria::registrar('crear', 'mantenimiento_repuestos', $id, null, $_POST);

    $costos = recalcularCostos($id);
    Response::ok($costos, 'Repuesto agregado. Costos recalculados.');

} elseif ($accion === 'quitar') {
    $rid = (int)($_POST['repuesto_id'] ?? 0);
    $r = Database::getOne(
        'SELECT id FROM mantenimiento_repuestos WHERE id = ? AND mantenimiento_id = ?', [$rid, $id]);
    if (!$r) Response::error('El repuesto no existe en ese mantenimiento.');

    Database::delete('mantenimiento_repuestos', 'id = ?', [$rid]);
    Auditoria::registrar('eliminar', 'mantenimiento_repuestos', $rid, null, ['mantenimiento_id' => $id]);

    $costos = recalcularCostos($id);
    Response::ok($costos, 'Repuesto retirado. Costos recalculados.');
} else {
    Response::error('Acción inválida.');
}