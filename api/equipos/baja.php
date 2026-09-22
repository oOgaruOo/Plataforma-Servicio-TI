<?php
// ============================================================
// SIGTI - Baja logica de equipo (nunca se borra fisicamente)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('equipos', 'baja');

 $id = (int)($_POST['id'] ?? 0);

 $v = Validator::make($_POST, [
    'motivo'        => 'required|in:obsolescencia,danado_irreparable,robo,venta,perdida,otros',
    'fecha'         => 'required|date',
    'destino'       => 'maxlen:100',
    'observaciones' => 'maxlen:300',
], ['motivo' => 'Motivo', 'fecha' => 'Fecha de baja', 'destino' => 'Destino']);
if ($v->fails()) Response::validation($v->errors());

 $e = Database::getOne('SELECT * FROM equipos WHERE id = ?', [$id]);
if (!$e) Response::error('El equipo no existe.');

if ($e['estado'] === 'dado_de_baja') Response::error('El equipo ya está dado de baja.');

// Regla critica: no dar de baja un equipo asignado/prestado
if (in_array($e['estado'], ['asignado','en_prestamo'], true)) {
    Response::error('El equipo está ASIGNADO o en PRÉSTAMO. Registre primero la devolución (Paso 9) y luego la baja.');
}

Database::begin();
try {
    Database::insert('bajas_equipos', [
        'equipo_id'      => $id,
        'motivo'         => $_POST['motivo'],
        'autorizado_por' => Auth::userId(),
        'fecha'          => $_POST['fecha'],
        'destino'        => trim((string)($_POST['destino'] ?? '')) ?: null,
        'observaciones'  => trim((string)($_POST['observaciones'] ?? '')) ?: null,
    ]);

    Database::update('equipos', ['estado' => 'dado_de_baja'], 'id = ?', [$id]);

    Database::insert('equipo_historial', [
        'equipo_id'  => $id,
        'usuario_id' => Auth::userId(),
        'evento'     => 'baja',
        'detalle'    => 'Motivo: ' . $_POST['motivo']
                      . (trim((string)($_POST['destino'] ?? '')) ? ' · Destino: ' . trim($_POST['destino']) : ''),
    ]);

    Database::commit();
    Auditoria::registrar('cambiar_estado', 'equipos', $id, ['estado' => $e['estado']], ['estado' => 'dado_de_baja']);

    Response::ok(null, "Equipo {$e['codigo']} dado de baja. El registro queda como histórico (auditoría/contable).");

} catch (Throwable $ex) {
    Database::rollback();
    Response::error('Error al dar de baja: ' . $ex->getMessage());
}