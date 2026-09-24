<?php
// ============================================================
// SIGTI - EDITAR asignacion activa: modalidad y fecha de retorno
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('asignaciones', 'crear');

 $id = (int)($_POST['id'] ?? 0);
 $a = Database::getOne('SELECT * FROM asignaciones WHERE id = ?', [$id]);
if (!$a) Response::error('La asignacion no existe.');
if (!in_array($a['estado'], ['activa','vencida'], true)) {
    Response::error('Solo se editan asignaciones activas (esta: ' . $a['estado'] . ').');
}

 $tipo = $_POST['tipo'] ?? '';
if (!in_array($tipo, ['permanente','prestamo'], true)) {
    Response::validation(['tipo' => 'Modalidad invalida.']);
}

 $retorno = null;
if ($tipo === 'prestamo') {
    $retorno = trim((string)($_POST['fecha_retorno'] ?? ''));
    if ($retorno === '') Response::validation(['fecha_retorno' => 'Indique la fecha de retorno del prestamo.']);
    $d = DateTime::createFromFormat('Y-m-d', $retorno);
    if (!$d || $d->format('Y-m-d') !== $retorno) {
        Response::validation(['fecha_retorno' => 'Fecha invalida.']);
    }
    if ($retorno < date('Y-m-d')) {
        Response::validation(['fecha_retorno' => 'El retorno no puede ser en el pasado.']);
    }
}

Database::begin();
try {
    Database::update('asignaciones', [
        'tipo'                     => $tipo,
        'fecha_devolucion_esperada'=> $retorno,
    ], 'id = ?', [$id]);

    // ajustar estado del equipo si cambia la modalidad
    $estadoEquipo = $tipo === 'prestamo' ? 'en_prestamo' : 'asignado';
    $eq = Database::getOne('SELECT id, estado FROM equipos WHERE id = ?', [$a['equipo_id']]);
    if ($eq && in_array($eq['estado'], ['asignado','en_prestamo'], true)) {
        Database::update('equipos', ['estado' => $estadoEquipo], 'id = ?', [$a['equipo_id']]);
    }

    Database::commit();
    Auditoria::registrar('actualizar', 'asignaciones', $id,
        ['tipo' => $a['tipo'], 'retorno' => $a['fecha_devolucion_esperada']],
        ['tipo' => $tipo, 'retorno' => $retorno]);

    Response::ok(null, 'Asignación actualizada: modalidad «' . $tipo . '»' .
        ($retorno ? ' · retorno el ' . $retorno : '') . '.');

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al editar: ' . $e->getMessage());
}