<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Eliminar mantenimiento (permiso eliminar)
// Restaura el estado del equipo si estaba en mantenimiento
// y limpia repuestos/evidencias (archivos incluidos).
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('mantenimiento', 'eliminar');

 $id = (int)($_POST['id'] ?? 0);
 $motivo = trim((string)($_POST['motivo'] ?? ''));

 $m = Database::getOne('SELECT * FROM mantenimientos WHERE id = ?', [$id]);
if (!$m) Response::error('El mantenimiento no existe.');

MantenimientoFlujo::asegurar();            // DDL antes de la transaccion
Programados::asegurar();
Database::begin();
try {
    $evidencias = Database::get(
        'SELECT archivo FROM mantenimiento_evidencias WHERE mantenimiento_id = ?', [$id]);
    foreach ($evidencias as $ev) {
        $ruta = STORAGE_PATH . '/evidencias-mantenimiento/' . $ev['archivo'];
        if ($ev['archivo'] && is_file($ruta)) @unlink($ruta);
    }

    Database::delete('mantenimiento_repuestos',  'mantenimiento_id = ?', [$id]);
    Database::delete('mantenimiento_evidencias', 'mantenimiento_id = ?', [$id]);
    Database::delete('mantenimiento_percances',  'mantenimiento_id = ?', [$id]);
    Programados::alEliminar($m);      // libera la programacion preventiva vinculada
    Database::delete('mantenimientos',           'id = ?', [$id]);

    // si el equipo estaba bloqueado por este mantenimiento y no tiene otro, liberarlo
    $otro = Database::getValue(
        "SELECT id FROM mantenimientos WHERE equipo_id = ?
         AND estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')",
        [$m['equipo_id']]);
    // (vuelve a su destino si estaba asignado; antes quedaba "en stock" con la asignacion vigente)
    if (!$otro && !in_array($m['estado'], MantenimientoFlujo::SIN_TRABAJO, true)) {
        $eqEstado = Database::getValue('SELECT estado FROM equipos WHERE id = ?', [$m['equipo_id']]);
        if ($eqEstado && $eqEstado !== 'dado_de_baja') {
            Database::update('equipos', ['estado' => MantenimientoFlujo::estadoAlVolver((int)$m['equipo_id'])[0]],
                'id = ?', [$m['equipo_id']]);
        }
    }

    Database::commit();

    Auditoria::registrar('eliminar', 'mantenimientos', $id, $m, [
        'motivo' => $motivo !== '' ? mb_substr($motivo, 0, 250) : '(no indicado)',
    ]);

    Response::ok(null, 'Mantenimiento ' . $m['codigo'] . ' eliminado (queda en auditoría).');

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al eliminar: ' . $e->getMessage());
}
