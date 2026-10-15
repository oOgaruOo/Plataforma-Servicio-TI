<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Registrar / editar un PROYECTO u OBRA
//   El plazo (inicio y fin) es obligatorio: los equipos que se envien se asignan por un tiempo
//   estipulado dentro de ese plazo.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('asignaciones', 'crear');
Destinos::asegurar();

 $id = (int)($_POST['id'] ?? 0);

 $v = Validator::make($_POST, [
    'clase'        => 'required|in:proyecto,obra',
    'nombre'       => 'required|maxlen:120',
    'codigo'       => 'maxlen:20',
    'cliente'      => 'maxlen:120',
    'ubicacion'    => 'maxlen:150',
    'descripcion'  => 'maxlen:250',
    'fecha_inicio' => 'required|date',
    'fecha_fin'    => 'required|date',
], ['clase' => 'Tipo', 'nombre' => 'Nombre', 'codigo' => 'Código', 'cliente' => 'Cliente', 'ubicacion' => 'Ubicación',
    'descripcion' => 'Descripción', 'fecha_inicio' => 'Fecha de inicio', 'fecha_fin' => 'Fecha de fin']);
if ($v->fails()) Response::validation($v->errors());

 $clase = $_POST['clase'];
 $quien = $clase === 'obra' ? 'la obra' : 'el proyecto';
 $nombre = trim($_POST['nombre']);
 $ini = $_POST['fecha_inicio'];
 $fin = $_POST['fecha_fin'];
if ($fin < $ini) Response::validation(['fecha_fin' => 'La fecha de fin no puede ser anterior a la de inicio.']);

 $antes = $id > 0 ? Database::getOne('SELECT * FROM proyectos WHERE id = ?', [$id]) : null;
if ($id > 0 && !$antes) Response::error('El proyecto u obra no existe.');

if ($antes && $antes['tipo'] !== $clase
    && Database::getValue('SELECT COUNT(*) FROM asignaciones WHERE proyecto_id = ?', [$id])) {
    Response::validation(['clase' => 'No se puede cambiar el tipo: ya tuvo equipos asignados como '
        . ($antes['tipo'] === 'obra' ? 'obra' : 'proyecto') . '.']);
}

 $codigo = strtoupper(preg_replace('/[^A-Za-z0-9\-_.]/', '', trim((string)($_POST['codigo'] ?? ''))));
if ($codigo === '') {
    $codigo = ($antes && $antes['tipo'] === $clase && $antes['codigo'] !== '') ? $antes['codigo'] : Destinos::codigoNuevo($clase);
}
if ($errCod = Destinos::errorCodigo($codigo)) Response::validation(['codigo' => $errCod]);
if (Database::getValue('SELECT id FROM proyectos WHERE codigo = ? AND id <> ?', [$codigo, $id])) {
    Response::validation(['codigo' => "El código $codigo ya lo usa otro registro."]);
}
if (Database::getValue('SELECT id FROM proyectos WHERE nombre = ? AND tipo = ? AND id <> ?', [$nombre, $clase, $id])) {
    Response::validation(['nombre' => "Ya existe $quien «$nombre»."]);
}

 $respId = (int)($_POST['responsable_id'] ?? 0);
if ($respId > 0 && !Database::getValue('SELECT id FROM personal WHERE id = ?', [$respId])) {
    Response::validation(['responsable_id' => 'El responsable ya no existe.']);
}

// el plazo no puede dejar fuera a un equipo que ya esta enviado
if ($antes) {
    $tarde = Database::getOne(
        "SELECT e.codigo, a.fecha_devolucion_esperada AS fecha FROM asignaciones a
         INNER JOIN equipos e ON e.id = a.equipo_id
         WHERE a.proyecto_id = ? AND a.estado IN ('activa','vencida') AND a.fecha_devolucion_esperada > ?
         ORDER BY a.fecha_devolucion_esperada DESC LIMIT 1", [$id, $fin]);
    if ($tarde) {
        Response::validation(['fecha_fin' => 'El equipo ' . $tarde['codigo'] . ' debe devolverse el '
            . substr((string)$tarde['fecha'], 0, 10) . ', después del nuevo fin. Ajuste primero su devolución en Asignaciones.']);
    }
}

 $datos = [
    'codigo'         => $codigo,
    'nombre'         => $nombre,
    'tipo'           => $clase,
    'cliente'        => trim((string)($_POST['cliente'] ?? '')) ?: null,
    'ubicacion'      => trim((string)($_POST['ubicacion'] ?? '')) ?: null,
    'descripcion'    => trim((string)($_POST['descripcion'] ?? '')) ?: null,
    'responsable_id' => $respId > 0 ? $respId : null,
    'fecha_inicio'   => $ini,
    'fecha_fin'      => $fin,
];

if ($antes) {
    Database::update('proyectos', $datos, 'id = ?', [$id]);
    Auditoria::registrar('actualizar', 'proyectos', $id, $antes, $datos);
    $msg = ($clase === 'obra' ? 'Obra' : 'Proyecto') . ' actualizado.';
    Response::ok(['id' => $id, 'codigo' => $codigo, 'mensaje' => $msg], $msg);
}

 $datos['estado'] = 'activo';
 $nuevo = Database::insert('proyectos', $datos);
Auditoria::registrar('crear', 'proyectos', $nuevo, null, $datos);
 $msg = ($clase === 'obra' ? 'Obra' : 'Proyecto') . " $codigo registrado. Ya puede enviarle equipos desde Asignaciones.";
Response::ok(['id' => (int)$nuevo, 'codigo' => $codigo, 'mensaje' => $msg], $msg);
