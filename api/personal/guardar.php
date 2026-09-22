<?php
// ============================================================
// SIGTI - Personal: alta de nueva persona + edicion
// Al crear: copia la plantilla de checklist de INGRESO
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('personal', 'crear');

 $id      = (int)($_POST['id'] ?? 0);
 $esNuevo = ($id === 0);

 $v = Validator::make($_POST, [
    'dni'               => 'required|dni',
    'nombres'           => 'required|maxlen:80|alpha_spaces',
    'apellidos'         => 'required|maxlen:80|alpha_spaces',
    'correo_personal'   => 'maxlen:120|email',
    'correo_corporativo'=> 'maxlen:120|email',
    'telefono'          => 'maxlen:20|telefono',
    'area_id'           => 'required|integer',
    'cargo'             => 'required|maxlen:80',
    'jefe_id'           => 'integer',
    'fecha_ingreso'     => 'date',
    'fecha_cese'        => 'date',
    'tipo_personal'     => 'required|in:empleado,contratista,practicante,tercero',
    'estado'            => 'required|in:pre_ingreso,activo,cese_programado,en_proceso_cese,cesado',
    'observaciones'     => 'maxlen:500',
], [
    'dni' => 'DNI', 'nombres' => 'Nombres', 'apellidos' => 'Apellidos',
    'area_id' => 'Área', 'cargo' => 'Cargo',
    'correo_personal' => 'Correo personal', 'correo_corporativo' => 'Correo corporativo',
    'telefono' => 'Teléfono', 'jefe_id' => 'Jefe inmediato',
    'fecha_ingreso' => 'Fecha de ingreso', 'fecha_cese' => 'Fecha de cese',
    'tipo_personal' => 'Tipo de personal', 'estado' => 'Estado',
]);
if ($v->fails()) Response::validation($v->errors());

// ---- Unicidad de DNI (excluyéndose a sí mismo) ----
if (Database::getValue('SELECT id FROM personal WHERE dni = ? AND id <> ?', [$_POST['dni'], $id])) {
    Response::validation(['dni' => "El DNI {$_POST['dni']} ya está registrado."]);
}

// ---- Área válida y activa ----
 $area = Database::getOne("SELECT id FROM areas WHERE id = ? AND estado = 'activo'", [(int)$_POST['area_id']]);
if (!$area) Response::validation(['area_id' => 'El área seleccionada no existe o está inactiva.']);

// ---- Jefe: si viene, debe existir y no ser uno mismo ----
 $jefeId = null;
if (trim((string)($_POST['jefe_id'] ?? '')) !== '') {
    $jefeId = (int)$_POST['jefe_id'];
    if ($jefeId === $id) {
        Response::validation(['jefe_id' => 'Una persona no puede ser su propio jefe.']);
    }
    if (!Database::getValue('SELECT id FROM personal WHERE id = ?', [$jefeId])) {
        Response::validation(['jefe_id' => 'El jefe seleccionado no existe.']);
    }
}

// ---- Coherencia de fechas ----
 $fechaIngreso = trim((string)($_POST['fecha_ingreso'] ?? ''));
 $fechaCese    = trim((string)($_POST['fecha_cese'] ?? ''));
if ($fechaIngreso && $fechaCese && $fechaCese < $fechaIngreso) {
    Response::validation(['fecha_cese' => 'La fecha de cese no puede ser anterior a la de ingreso.']);
}

// ---- Coherencia estado/fecha de cese ----
 $estado = $_POST['estado'];
if (in_array($estado, ['cese_programado','en_proceso_cese','cesado'], true) && !$fechaCese) {
    Response::validation(['fecha_cese' => 'Indique la fecha de cese para ese estado.']);
}

 $datos = [
    'dni'                => trim($_POST['dni']),
    'nombres'            => trim($_POST['nombres']),
    'apellidos'          => trim($_POST['apellidos']),
    'correo_personal'    => trim((string)($_POST['correo_personal'] ?? '')) ?: null,
    'correo_corporativo'=> trim((string)($_POST['correo_corporativo'] ?? '')) ?: null,
    'telefono'           => trim((string)($_POST['telefono'] ?? '')) ?: null,
    'area_id'            => (int)$_POST['area_id'],
    'cargo'              => trim($_POST['cargo']),
    'jefe_id'            => $jefeId,
    'fecha_ingreso'      => $fechaIngreso ?: null,
    'fecha_cese'         => $fechaCese ?: null,
    'tipo_personal'      => $_POST['tipo_personal'],
    'estado'             => $estado,
    'observaciones'      => trim((string)($_POST['observaciones'] ?? '')) ?: null,
];

// ==================== CREAR ====================
if ($esNuevo) {

    Database::begin();
    try {
        $nuevoId = Database::insert('personal', $datos);

        // Copia la plantilla de checklist de INGRESO activa
        $items = Database::get(
            "SELECT ci.id, ci.item, ci.orden
             FROM checklist_items ci
             INNER JOIN checklist_plantillas cp ON cp.id = ci.plantilla_id
             WHERE cp.tipo = 'ingreso' AND cp.activo = 1 AND ci.activo = 1
             ORDER BY ci.orden"
        );
        foreach ($items as $item) {
            Database::insert('personal_checklist', [
                'personal_id' => $nuevoId,
                'item_id'     => $item['id'],
            ]);
        }

        Database::commit();
        Auditoria::registrar('crear', 'personal', $nuevoId, null, $datos);

        Response::ok([
            'id'            => $nuevoId,
            'checklist'     => count($items),
            'abrir_detalle' => true,
        ], 'Persona registrada. Se generó el checklist de ingreso con ' . count($items) . ' ítems.');

    } catch (Throwable $e) {
        Database::rollback();
        Response::error('Error al registrar: ' . $e->getMessage());
    }
}

// ==================== EDITAR ====================
 $antes = Database::getOne('SELECT * FROM personal WHERE id = ?', [$id]);
if (!$antes) Response::error('La persona no existe.');

// Reglas de edición:
if ($antes['estado'] === 'cesado') {
    Response::error('No se puede editar el registro de una persona cesada (histórico).');
}
if ($antes['estado'] !== 'cese_programado' && $estado === 'cese_programado' && !$fechaCese) {
    Response::validation(['fecha_cese' => 'Indique la fecha de cese programada.']);
}
if ($antes['dni'] !== $datos['dni'] && $antes['estado'] === 'cesado') {
    Response::error('No se puede modificar el DNI de un registro histórico.');
}

Database::update('personal', $datos, 'id = ?', [$id]);
Auditoria::registrar('actualizar', 'personal', $id, $antes, $datos);

Response::ok(['id' => $id], 'Datos actualizados correctamente.');