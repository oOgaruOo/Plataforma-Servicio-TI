<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Personal: alta de nueva persona + edicion
// Al crear: copia la plantilla de checklist de INGRESO
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();

 $id      = (int)($_POST['id'] ?? 0);
 $esNuevo = ($id === 0);

// alta = permiso "crear"; edicion = permiso "editar"
Auth::requirePermission('personal', $esNuevo ? 'crear' : 'editar');
Esquema::columna('personal', 'fecha_nacimiento', 'DATE NULL');
Apellidos::asegurar();
CodigoUsuario::asegurar();

// pantalla antigua en cache: manda un solo campo "apellidos" -> se reparte en paterno y materno
if (!isset($_POST['apellido_paterno']) && isset($_POST['apellidos'])) {
    [$_POST['apellido_paterno'], $_POST['apellido_materno']] = Apellidos::dividir((string)$_POST['apellidos']);
}

 $v = Validator::make($_POST, [
    'dni'               => 'required|dni',
    'nombres'           => 'required|maxlen:80|alpha_spaces',
    'apellido_paterno'  => 'required|maxlen:39|alpha_spaces',
    'apellido_materno'  => 'maxlen:39|alpha_spaces',
    'correo_personal'   => 'maxlen:120|email',
    'correo_corporativo'=> 'maxlen:120|email',
    'telefono'          => 'maxlen:20|telefono',
    'area_id'           => 'required|integer',
    'cargo'             => 'required|maxlen:80',
    'jefe_id'           => 'integer',
    'fecha_ingreso'     => 'date',
    'fecha_cese'        => 'date',
    'fecha_nacimiento'  => ($esNuevo ? 'required|' : '') . 'date',
    'tipo_personal'     => 'required|in:empleado,contratista,practicante,tercero',
    'genero'            => 'in:masculino,femenino',
    'estado'            => 'required|in:pre_ingreso,activo,cese_programado,en_proceso_cese,cesado',
    'observaciones'     => 'maxlen:500',
    'locker'            => 'maxlen:20',
], [
    'dni' => 'DNI', 'nombres' => 'Nombres',
    'apellido_paterno' => 'Apellido paterno', 'apellido_materno' => 'Apellido materno',
    'area_id' => 'Area', 'cargo' => 'Cargo',
    'correo_personal' => 'Correo personal', 'correo_corporativo' => 'Correo corporativo',
    'telefono' => 'Telefono', 'jefe_id' => 'Jefe inmediato',
    'fecha_ingreso' => 'Fecha de ingreso', 'fecha_cese' => 'Fecha de cese',
    'fecha_nacimiento' => 'Fecha de cumpleanos (nacimiento)',
    'tipo_personal' => 'Tipo de personal', 'estado' => 'Estado', 'genero' => 'Genero',
]);
if ($v->fails()) Response::validation($v->errors());

 $locker = trim((string)($_POST['locker'] ?? ''));
if ($locker !== '' && !preg_match('/^[\p{L}\p{N}][\p{L}\p{N} \-\/\.]*$/u', $locker)) {
    Response::validation(['locker' => 'El locker solo admite letras, números, espacios, guion, punto y barra.']);
}

if (Database::getValue('SELECT id FROM personal WHERE dni = ? AND id <> ?', [$_POST['dni'], $id])) {
    Response::validation(['dni' => "El DNI {$_POST['dni']} ya esta registrado."]);
}

 $area = Database::getOne("SELECT id FROM areas WHERE id = ? AND estado = 'activo'", [(int)$_POST['area_id']]);
// al editar se permite conservar la MISMA area aunque ya este inactiva (asi no se bloquea editar otros datos)
if (!$area && !$esNuevo) {
    $area = Database::getOne('SELECT id FROM personal WHERE id = ? AND area_id = ?', [$id, (int)$_POST['area_id']]);
}
if (!$area) Response::validation(['area_id' => 'El area seleccionada no existe o esta inactiva.']);

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

 $fechaIngreso = trim((string)($_POST['fecha_ingreso'] ?? ''));
 $fechaCese    = trim((string)($_POST['fecha_cese'] ?? ''));
if ($fechaIngreso && $fechaCese && $fechaCese < $fechaIngreso) {
    Response::validation(['fecha_cese' => 'La fecha de cese no puede ser anterior a la de ingreso.']);
}

 // cumpleanos: fecha real, no futura y edad razonable
 $fechaNac = trim((string)($_POST['fecha_nacimiento'] ?? ''));
if ($fechaNac !== '') {
    $nac = Cumpleanos::fecha($fechaNac);
    $hoy = new DateTimeImmutable('today');
    if (!$nac) {
        Response::validation(['fecha_nacimiento' => 'La fecha de nacimiento no es valida.']);
    } elseif ($nac > $hoy) {
        Response::validation(['fecha_nacimiento' => 'La fecha de nacimiento no puede ser futura.']);
    } else {
        $edad = Cumpleanos::edad($fechaNac, $hoy);
        if ($edad < 14)  Response::validation(['fecha_nacimiento' => 'La persona debe tener al menos 14 anios.']);
        if ($edad > 100) Response::validation(['fecha_nacimiento' => 'Revise la fecha de nacimiento (mas de 100 anios).']);
    }
}

 $estado = $_POST['estado'];
if (in_array($estado, ['cese_programado','en_proceso_cese','cesado'], true) && !$fechaCese) {
    Response::validation(['fecha_cese' => 'Indique la fecha de cese para ese estado.']);
}

 $nulo = fn($k) => (trim((string)($_POST[$k] ?? '')) === '') ? null : trim((string)$_POST[$k]);

 $datos = [
    'dni'                => trim($_POST['dni']),
    'nombres'            => trim($_POST['nombres']),
    'apellido_paterno'   => Apellidos::unir($_POST['apellido_paterno'], ''),
    'apellido_materno'   => $nulo('apellido_materno') === null ? null : Apellidos::unir($_POST['apellido_materno'], ''),
    // "paterno materno" juntos: lo leen actas, tickets, reportes, etc.
    'apellidos'          => Apellidos::unir($_POST['apellido_paterno'], $_POST['apellido_materno'] ?? ''),
    'correo_personal'    => $nulo('correo_personal'),
    'correo_corporativo'=> $nulo('correo_corporativo'),
    'telefono'           => $nulo('telefono'),
    'area_id'            => (int)$_POST['area_id'],
    'cargo'              => trim($_POST['cargo']),
    'jefe_id'            => $jefeId,
    'fecha_ingreso'      => $fechaIngreso ?: null,
    'fecha_cese'         => $fechaCese ?: null,
    'tipo_personal'      => $_POST['tipo_personal'],
    'genero'             => $nulo('genero'),
    'fecha_nacimiento'   => $fechaNac ?: null,
    'estado'             => $estado,
    'observaciones'      => $nulo('observaciones'),
    'locker'             => $locker === '' ? null : $locker,
];

// ==================== CREAR ====================
if ($esNuevo) {

    Database::begin();
    try {
        $nuevoId = Database::insert('personal', $datos);
        // codigo de usuario segun el area: SIS-0001, RH-0001, ...
        $codigo = CodigoUsuario::asignar($nuevoId, (int)$datos['area_id']);

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
        Auditoria::registrar('crear', 'personal', $nuevoId, null, $datos + ['codigo_usuario' => $codigo]);

        Response::ok([
            'id'             => $nuevoId,
            'codigo_usuario' => $codigo,
            'checklist'      => count($items),
            'abrir_detalle'  => true,
        ], "Persona registrada con codigo $codigo. Se genero el checklist de ingreso con " . count($items) . ' items.');

    } catch (Throwable $e) {
        Database::rollback();
        Response::error('Error al registrar: ' . $e->getMessage());
    }
}

// ==================== EDITAR ====================
 $antes = Database::getOne('SELECT * FROM personal WHERE id = ?', [$id]);
if (!$antes) Response::error('La persona no existe.');

if ($antes['estado'] === 'cesado') {
    Response::error('No se puede editar el registro de una persona cesada (historico).');
}

// pantalla antigua en cache que no envia la fecha: se conserva la actual
if (!array_key_exists('fecha_nacimiento', $_POST)) unset($datos['fecha_nacimiento']);
if (!array_key_exists('locker', $_POST)) unset($datos['locker']);

Database::update('personal', $datos, 'id = ?', [$id]);

// el codigo acompana al area: si la persona cambia de area se le asigna el siguiente codigo de la nueva
 $codigo = (string)($antes['codigo_usuario'] ?? '');
 $codigoCambio = false;
if ($codigo === '' || (int)$antes['area_id'] !== (int)$datos['area_id']) {
    $codigo = CodigoUsuario::asignar($id, (int)$datos['area_id']);
    $codigoCambio = ($codigo !== (string)($antes['codigo_usuario'] ?? ''));
}
Auditoria::registrar('actualizar', 'personal', $id, $antes, $datos + ['codigo_usuario' => $codigo]);

// lo que va impreso en el carnet (nombre, cargo, area, DNI, codigo): si cambia y el carnet ya se imprimio, se marca para reimprimir
 $carnetReimprimir = false;
 $cambioCarnet = [];
foreach (['dni', 'nombres', 'apellidos', 'cargo'] as $k) {
    if (isset($datos[$k]) && (string)$datos[$k] !== (string)($antes[$k] ?? '')) $cambioCarnet[] = $k;
}
if ((int)$antes['area_id'] !== (int)$datos['area_id']) $cambioCarnet[] = 'area';
if ($codigoCambio) $cambioCarnet[] = 'codigo_usuario';
if ($cambioCarnet) $carnetReimprimir = Carnets::datosCambiaron($id, implode(',', $cambioCarnet));

Response::ok(['id' => $id, 'codigo_usuario' => $codigo, 'codigo_cambio' => $codigoCambio, 'carnet_reimprimir' => $carnetReimprimir],
    ($codigoCambio ? "Datos actualizados. Por el cambio de area su nuevo codigo es $codigo." : 'Datos actualizados correctamente.')
    . ($carnetReimprimir ? ' Su carnet ya impreso quedo marcado para reimprimir.' : ''));