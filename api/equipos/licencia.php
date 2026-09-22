<?php
// ============================================================
// SIGTI - Licencias de software: agregar / quitar
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('equipos', 'editar');

 $accion     = $_POST['accion'] ?? '';
 $equipoId   = (int)($_POST['equipo_id'] ?? 0);
 $licenciaId = (int)($_POST['licencia_id'] ?? 0);

 $equipo = Database::getOne('SELECT id, estado, codigo FROM equipos WHERE id = ?', [$equipoId]);
if (!$equipo)  Response::error('El equipo no existe.');
if ($equipo['estado'] === 'dado_de_baja') Response::error('Equipo dado de baja: registro histórico.');

if ($accion === 'agregar') {
    $v = Validator::make($_POST, [
        'software' => 'required|maxlen:80',
        'clave'    => 'maxlen:150',
        'tipo'     => 'in:perpetua,suscripcion,oem',
        'fecha_inicio'      => 'date',
        'fecha_vencimiento' => 'date',
        'costo'             => 'numeric|max:9999999',
    ], ['software' => 'Software', 'clave' => 'Clave/Licencia']);
    if ($v->fails()) Response::validation($v->errors());

    $fi = trim((string)($_POST['fecha_inicio'] ?? ''));
    $fv = trim((string)($_POST['fecha_vencimiento'] ?? ''));
    if ($fi && $fv && $fv < $fi) {
        Response::validation(['fecha_vencimiento' => 'El vencimiento no puede ser anterior al inicio.']);
    }

    $id = Database::insert('equipo_licencias', [
        'equipo_id'         => $equipoId,
        'software'          => trim($_POST['software']),
        'clave'             => trim((string)($_POST['clave'] ?? '')) ?: null,
        'tipo'              => ($_POST['tipo'] ?? '') ?: 'perpetua',
        'fecha_inicio'      => $fi ?: null,
        'fecha_vencimiento' => $fv ?: null,
        'costo'             => trim((string)($_POST['costo'] ?? '')) !== '' ? (float)$_POST['costo'] : null,
    ]);
    Database::insert('equipo_historial', [
        'equipo_id' => $equipoId, 'usuario_id' => Auth::userId(),
        'evento' => 'licencia_agregada', 'detalle' => mb_substr(trim($_POST['software']), 0, 200),
    ]);
    Auditoria::registrar('crear', 'equipo_licencias', $id, null, ['equipo_id'=>$equipoId,'software'=>trim($_POST['software'])]);
    Response::ok(['id' => $id], 'Licencia registrada.');

} elseif ($accion === 'quitar') {
    $lic = Database::getOne('SELECT * FROM equipo_licencias WHERE id = ? AND equipo_id = ?', [$licenciaId, $equipoId]);
    if (!$lic) Response::error('La licencia no existe en ese equipo.');

    Database::delete('equipo_licencias', 'id = ?', [$licenciaId]);
    Database::insert('equipo_historial', [
        'equipo_id' => $equipoId, 'usuario_id' => Auth::userId(),
        'evento' => 'licencia_retirada', 'detalle' => $lic['software'],
    ]);
    Auditoria::registrar('eliminar', 'equipo_licencias', $licenciaId, $lic, null);
    Response::ok(null, 'Licencia retirada.');
} else {
    Response::error('Acción inválida.');
}