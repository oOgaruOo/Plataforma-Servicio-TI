<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('asignaciones', 'devolver');

 $asigId = (int)($_POST['asignacion_id'] ?? 0);
 $a = Database::getOne('SELECT * FROM asignaciones WHERE id = ?', [$asigId]);
if (!$a) Response::error('La asignacion no existe.');
if (!in_array($a['estado'], ['activa','vencida'], true)) {
    Response::error('La asignacion ya esta cerrada.');
}

 $condicion = $_POST['condicion'] ?? '';
if (!in_array($condicion, ['nuevo','bueno','regular','danado','faltante'], true)) {
    Response::validation(['condicion' => 'Seleccione la condicion del equipo devuelto.']);
}
 $obs = trim((string)($_POST['observaciones'] ?? ''));

 $equipo = Database::getOne(
    "SELECT e.*, t.nombre AS tipo FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id WHERE e.id = ?", [$a['equipo_id']]);

 $estadoEquipo = 'en_stock';
 $condEquipo = $equipo['condicion'];
if ($condicion === 'danado')    { $estadoEquipo = 'en_revision'; }
if ($condicion === 'faltante')  { $estadoEquipo = 'en_revision'; $condEquipo = 'danado'; }
if ($condicion === 'regular')   { $condEquipo = 'regular'; }

Database::begin();
try {
    Database::update('asignaciones', [
        'estado' => 'devuelta',
        'fecha_devolucion_real' => date('Y-m-d H:i:s'),
        'condicion_devolucion' => $condicion,
        'obs_devolucion' => $obs ?: null,
    ], 'id = ?', [$asigId]);

    Database::update('equipos', [
        'estado' => $estadoEquipo,
        'condicion' => $condEquipo,
    ], 'id = ?', [$a['equipo_id']]);

    Database::insert('equipo_historial', [
        'equipo_id' => $a['equipo_id'], 'usuario_id' => Auth::userId(),
        'evento' => 'devuelto',
        'detalle' => 'Condicion: ' . $condicion . ($obs ? ' · ' . $obs : ''),
    ]);


    // --- BITACORA DE UBICACION: devolucion ---
    Database::insert('equipo_ubicaciones', [
        'equipo_id'  => (int)$a['equipo_id'],
        'ubicacion'  => 'Almacen TI',
        'tipo'       => 'devolucion',
        'observaciones' => 'Condicion: ' . $condicion,
        'usuario_id' => Auth::userId(),
    ]);
    Database::update('equipos',
        ['ubicacion' => 'Almacen TI'],
        'id = ?', [(int)$a['equipo_id']]);    Auditoria::registrar('cambiar_estado', 'asignaciones', $asigId,
        ['estado' => $a['estado']], ['estado' => 'devuelta', 'condicion' => $condicion]);

    Database::commit();

    $msg = "Equipo {$equipo['codigo']} recibido (condicion: $condicion).";
    if ($condicion === 'danado' || $condicion === 'faltante') {
        $msg .= ' Quedo EN REVISION: evalue enviarlo a mantenimiento.';
    }

    Response::ok(['equipo' => $equipo['codigo'], 'estado_equipo' => $estadoEquipo], $msg);

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al registrar la devolucion: ' . $e->getMessage());
}