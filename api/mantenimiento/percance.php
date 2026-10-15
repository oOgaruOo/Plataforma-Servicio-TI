<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Registrar un PERCANCE en un mantenimiento
// (falta de repuesto, demora del proveedor, daño adicional...)
// Queda en el detalle del mantenimiento y en el historial del equipo,
// y sirve de respaldo cuando el trabajo termina con atraso.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('mantenimiento', 'cambiar_estado');

 $id = (int)($_POST['id'] ?? 0);
 $m = Database::getOne('SELECT * FROM mantenimientos WHERE id = ?', [$id]);
if (!$m) Response::error('El mantenimiento no existe.');
if (in_array($m['estado'], MantenimientoFlujo::TERMINALES, true)) {
    Response::error('Este mantenimiento ya está ' . $m['estado'] . ': el percance se registra al finalizarlo.');
}

 $tipo = trim((string)($_POST['tipo'] ?? ''));
 $detalle = trim((string)($_POST['detalle'] ?? ''));
 $err = [];
if (!isset(MantenimientoFlujo::PERCANCES[$tipo])) $err['tipo'] = 'Seleccione el tipo de percance.';
if ($detalle === '') $err['detalle'] = 'Describa brevemente qué ocurrió.';
elseif (mb_strlen($detalle) > 250) $err['detalle'] = 'Máximo 250 caracteres.';
if ($err) Response::validation($err);

MantenimientoFlujo::asegurar();            // DDL antes de la transaccion

 $ahora = date('Y-m-d H:i:s');
Database::begin();
try {
    $pid = Database::insert('mantenimiento_percances', [
        'mantenimiento_id' => $id, 'tipo' => $tipo, 'detalle' => $detalle,
        'usuario_id' => Auth::userId(), 'created_at' => $ahora,
    ]);
    Database::insert('equipo_historial', [
        'equipo_id' => $m['equipo_id'], 'usuario_id' => Auth::userId(), 'evento' => 'mantenimiento',
        'detalle' => "MT {$m['codigo']}: percance · " . MantenimientoFlujo::PERCANCES[$tipo] . ' — ' . mb_substr($detalle, 0, 150),
    ]);
    if (!Auditoria::registrar('crear', 'mantenimiento_percances', $id, null,
        ['tipo' => $tipo, 'detalle' => $detalle, 'mantenimiento' => $m['codigo']])) {
        Database::rollback();
        Response::error('No se pudo registrar la auditoría; el percance no se guardó.', null, 500);
    }
    Database::commit();
} catch (Throwable $e) {
    Database::rollback();
    Response::error('No se pudo registrar el percance: ' . $e->getMessage());
}

Response::ok([
    'id' => (int)$pid,
    'mensaje' => 'Percance registrado en ' . $m['codigo'] . ': ' . MantenimientoFlujo::PERCANCES[$tipo] . '.',
]);
