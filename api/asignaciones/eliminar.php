<?php
// ============================================================
// SIGTI - ELIMINAR/CANCELAR asignacion (registro erroneo):
// la asignacion queda cancelada y el equipo vuelve a stock.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('asignaciones', 'crear');

 $id = (int)($_POST['id'] ?? 0);
 $a = Database::getOne('SELECT * FROM asignaciones WHERE id = ?', [$id]);
if (!$a) Response::error('La asignacion no existe.');
if (!in_array($a['estado'], ['activa','vencida'], true)) {
    Response::error('Solo se cancelan asignaciones activas. Para cerrar una real use Devolución.');
}

 $motivo = trim((string)($_POST['motivo'] ?? ''));
if ($motivo === '') Response::validation(['motivo' => 'Indique el motivo de la cancelación.']);

Database::begin();
try {
    Database::update('asignaciones', [
        'estado'               => 'cancelada',
        'fecha_devolucion_real'=> date('Y-m-d H:i:s'),
        'obs_devolucion'       => 'CANCELADA: ' . mb_substr($motivo, 0, 250),
    ], 'id = ?', [$id]);

    // liberar el equipo SOLO si no esta en mantenimiento/reparacion
    $eq = Database::getOne('SELECT id, estado, codigo FROM equipos WHERE id = ?', [$a['equipo_id']]);
    if ($eq && in_array($eq['estado'], ['asignado','en_prestamo'], true)) {
        Database::update('equipos', ['estado' => 'en_stock'], 'id = ?', [$a['equipo_id']]);
        Database::run("UPDATE equipo_accesorios SET entregado = 0 WHERE equipo_id = ?", [$a['equipo_id']]);

        // bitacora de seguimiento
        Database::insert('equipo_ubicaciones', [
            'equipo_id'     => $a['equipo_id'],
            'ubicacion'     => 'Almacén TI',
            'tipo'          => 'devolucion',
            'observaciones' => 'Asignación cancelada: ' . mb_substr($motivo, 0, 200),
            'usuario_id'    => Auth::userId(),
        ]);
        Database::update('equipos', ['ubicacion' => 'Almacén TI'], 'id = ?', [$a['equipo_id']]);

        Database::insert('equipo_historial', [
            'equipo_id' => $a['equipo_id'], 'usuario_id' => Auth::userId(),
            'evento' => 'devuelto', 'detalle' => 'Asignación CANCELADA: ' . mb_substr($motivo, 0, 200),
        ]);
    }

    Database::commit();
    Auditoria::registrar('eliminar', 'asignaciones', $id, $a, ['estado' => 'cancelada', 'motivo' => $motivo]);

    Response::ok(null, 'Asignación cancelada. Equipo ' . ($eq['codigo'] ?? '') . ' devuelto a stock.');

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al cancelar: ' . $e->getMessage());
}