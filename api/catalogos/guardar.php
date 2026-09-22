<?php
// ============================================================
// SIGTI - Crear / editar registros de catalogo
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('configuracion', 'gestionar');

 $tipo = $_POST['tipo'] ?? '';
 $id   = (int)($_POST['id'] ?? 0);

 $CONFIG = [
    'areas' => [
        'tabla'  => 'areas',
        'campos' => ['nombre', 'descripcion', 'estado'],
        'reglas' => [
            'nombre'      => 'required|maxlen:80',
            'descripcion' => 'maxlen:200',
            'estado'      => 'required|in:activo,inactivo',
        ],
        'labels' => ['nombre' => 'Nombre', 'descripcion' => 'Descripcion', 'estado' => 'Estado'],
        'unico'  => ['nombre'],
        'enum'   => true,
    ],
    'tipos' => [
        'tabla'  => 'tipo_equipos',
        'campos' => ['nombre', 'activo'],
        'reglas' => ['nombre' => 'required|maxlen:60'],
        'labels' => ['nombre' => 'Nombre del tipo'],
        'unico'  => ['nombre'],
        'bool'   => ['activo'],
    ],
    'categorias' => [
        'tabla'  => 'categorias',
        'campos' => ['nombre', 'descripcion', 'activo'],
        'reglas' => ['nombre' => 'required|maxlen:60', 'descripcion' => 'maxlen:200'],
        'labels' => ['nombre' => 'Nombre de la categoria'],
        'unico'  => ['nombre'],
        'bool'   => ['activo'],
    ],
    'subcategorias' => [
        'tabla'  => 'subcategorias',
        'campos' => ['categoria_id', 'nombre', 'activo'],
        'reglas' => ['categoria_id' => 'required|integer', 'nombre' => 'required|maxlen:80'],
        'labels' => ['categoria_id' => 'Categoria', 'nombre' => 'Nombre de la subcategoria'],
        'bool'   => ['activo'],
        'existe' => ['categoria_id' => 'categorias'],
    ],
    'prioridades' => [
        'tabla'  => 'prioridades',
        'campos' => ['nombre', 'nivel', 'color', 'sla_horas', 'activo'],
        'reglas' => [
            'nombre'    => 'required|maxlen:20',
            'nivel'     => 'required|integer|min:1|max:99',
            'color'     => 'required|maxlen:7',
            'sla_horas' => 'required|integer|min:1|max:2000',
        ],
        'labels' => ['nombre' => 'Nombre', 'nivel' => 'Nivel', 'color' => 'Color', 'sla_horas' => 'SLA (horas)'],
        'unico'  => ['nombre'],
        'bool'   => ['activo'],
    ],
    'proveedores' => [
        'tabla'  => 'proveedores',
        'campos' => ['ruc', 'nombre', 'contacto', 'telefono', 'correo', 'especialidad', 'estado'],
        'reglas' => [
            'nombre'      => 'required|maxlen:120',
            'ruc'         => 'maxlen:15',
            'contacto'    => 'maxlen:100',
            'telefono'    => 'maxlen:30|telefono',
            'correo'      => 'maxlen:120|email',
            'especialidad'=> 'maxlen:120',
            'estado'      => 'required|in:activo,inactivo',
        ],
        'labels' => ['nombre' => 'Razon social / nombre', 'ruc' => 'RUC', 'telefono' => 'Telefono', 'correo' => 'Correo'],
        'enum'   => true,
    ],
];

if (!isset($CONFIG[$tipo])) Response::error('Catalogo desconocido.');
 $c = $CONFIG[$tipo];

 $datos = [];
foreach ($c['campos'] as $campo) {
    $datos[$campo] = trim((string)($_POST[$campo] ?? ''));
}
foreach ($c['bool'] ?? [] as $campo) {
    $datos[$campo] = (int)(($_POST[$campo] ?? '0') === '1');
}
if (!empty($c['enum'])) {
    $datos['estado'] = ($datos['estado'] === 'inactivo') ? 'inactivo' : 'activo';
}

 $v = Validator::make($_POST, $c['reglas'], $c['labels'] ?? []);
if ($v->fails()) Response::validation($v->errors());

if ($tipo === 'prioridades' && !preg_match('/^#[0-9a-fA-F]{6}$/', $datos['color'])) {
    Response::validation(['color' => 'El color debe tener formato hexadecimal (#RRGGBB).']);
}

foreach ($c['unico'] ?? [] as $campo) {
    $existe = Database::getValue(
        "SELECT id FROM {$c['tabla']} WHERE $campo = ? AND id <> ?",
        [$datos[$campo], $id]
    );
    if ($existe) {
        Response::validation([$campo => "Ya existe un registro con el valor «{$datos[$campo]}»."]);
    }
}

foreach ($c['existe'] ?? [] as $campo => $tablaRef) {
    if (!Database::getValue("SELECT id FROM $tablaRef WHERE id = ?", [(int)$datos[$campo]])) {
        Response::validation([$campo => 'El valor seleccionado ya no existe (fue desactivado o eliminado).']);
    }
}

if ($id > 0) {
    $antes = Database::getOne("SELECT * FROM {$c['tabla']} WHERE id = ?", [$id]);
    if (!$antes) Response::error('El registro no existe.');

    Database::update($c['tabla'], $datos, 'id = ?', [$id]);
    Auditoria::registrar('actualizar', $c['tabla'], $id, $antes, $datos);
    Response::ok(['id' => $id], 'Registro actualizado correctamente.');
}

 $nuevoId = Database::insert($c['tabla'], $datos);
Auditoria::registrar('crear', $c['tabla'], $nuevoId, null, $datos);
Response::ok(['id' => $nuevoId], 'Registro creado correctamente.');