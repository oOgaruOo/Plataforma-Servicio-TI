<?php
// ============================================================
// SIGTI - Accesorios: agregar / quitar (AJAX puntual)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('equipos', 'editar');

 $accion    = $_POST['accion'] ?? '';
 $equipoId  = (int)($_POST['equipo_id'] ?? 0);
 $accesorioId = (int)($_POST['accesorio_id'] ?? 0);

if (!Database::getValue('SELECT id FROM equipos WHERE id = ?', [$equipoId])) {
    Response::error('El equipo no existe.');
}
 $equipo = Database::getOne('SELECT id, estado, codigo FROM equipos WHERE id = ?', [$equipoId]);
if ($equipo['estado'] === 'dado_de_baja') {
    Response::error('Equipo dado de baja: registro histórico.');
}

if ($accion === 'agregar') {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    if ($nombre === '') Response::validation(['nombre' => 'Indique el nombre del accesorio.']);
    if (mb_strlen($nombre) > 80) Response::validation(['nombre' => 'Máximo 80 caracteres.']);
    if (Database::getValue('SELECT id FROM equipo_accesorios WHERE equipo_id = ? AND nombre = ?', [$equipoId, $nombre])) {
        Response::validation(['nombre' => 'Ese accesorio ya existe en el equipo.']);
    }
    $id = Database::insert('equipo_accesorios', ['equipo_id' => $equipoId, 'nombre' => $nombre]);
    Database::insert('equipo_historial', [
        'equipo_id' => $equipoId, 'usuario_id' => Auth::userId(),
        'evento' => 'accesorio_agregado', 'detalle' => $nombre,
    ]);
    Auditoria::registrar('crear', 'equipo_accesorios', $id, null, ['equipo_id'=>$equipoId,'nombre'=>$nombre]);
    Response::ok(['id' => $id, 'nombre' => $nombre], 'Accesorio agregado.');

} elseif ($accion === 'quitar') {
    $acc = Database::getOne('SELECT id, nombre, entregado FROM equipo_accesorios WHERE id = ? AND equipo_id = ?', [$accesorioId, $equipoId]);
    if (!$acc) Response::error('El accesorio no existe en ese equipo.');
    if ((int)$acc['entregado'] === 1) {
        Response::error('El accesorio figura como entregado con el equipo: retirelo al registrar la devolución (Paso 9).');
    }
    Database::delete('equipo_accesorios', 'id = ?', [$accesorioId]);
    Database::insert('equipo_historial', [
        'equipo_id' => $equipoId, 'usuario_id' => Auth::userId(),
        'evento' => 'accesorio_retirado', 'detalle' => $acc['nombre'],
    ]);
    Auditoria::registrar('eliminar', 'equipo_accesorios', $accesorioId, $acc, null);
    Response::ok(null, 'Accesorio retirado.');
} else {
    Response::error('Acción inválida.');
}