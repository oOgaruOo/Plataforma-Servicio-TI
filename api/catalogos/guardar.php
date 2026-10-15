<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Crear / editar registros de catalogo
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../core/crearRolesArea.php';   // funciones (no es una clase: no se autocarga)

Auth::check();
Csrf::validate();
// Normalizar estado (el JS puede enviar 'Activo' con mayuscula)
if (isset($_POST['estado'])) {
    $_POST['estado'] = strtolower(trim($_POST['estado']));
    if ($_POST['estado'] === 'active') $_POST['estado'] = 'activo';
    if ($_POST['estado'] === 'inactive') $_POST['estado'] = 'inactivo';
}

 $tipo = $_POST['tipo'] ?? '';
 $id   = (int)($_POST['id'] ?? 0);

// Catalogos: solo quien gestiona la configuracion. Excepcion: el proveedor tambien lo puede
// crear/editar quien registra mantenimientos (alta rapida de proveedor desde Mantenimiento).
if ($tipo === 'proveedores' && Auth::can('mantenimiento', $id > 0 ? 'editar' : 'crear')) {
    // permitido
} else {
    Auth::requirePermission('configuracion', 'gestionar');
}

 $CONFIG = [
    'areas' => [
        'tabla'  => 'areas',
        'campos' => ['nombre', 'codigo', 'descripcion', 'estado'],
        'reglas' => [
            'nombre'      => 'required|maxlen:80',
            'codigo'      => 'maxlen:6',
            'descripcion' => 'maxlen:200',
            'estado'      => 'required|in:activo,inactivo',
        ],
        'labels' => ['nombre' => 'Nombre', 'codigo' => 'Codigo del area', 'descripcion' => 'Descripcion', 'estado' => 'Estado'],
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
    'proyectos' => [
        'tabla'  => 'proyectos',
        // OJO: en el formulario "tipo" ya nombra al catalogo, por eso proyecto/obra viaja como "clase"
        'campos' => ['codigo', 'nombre', 'cliente', 'ubicacion', 'responsable_id', 'fecha_inicio', 'fecha_fin', 'estado'],
        'reglas' => [
            'clase'        => 'required|in:proyecto,obra',
            'codigo'       => 'maxlen:20',
            'nombre'       => 'required|maxlen:120',
            'cliente'      => 'maxlen:120',
            'ubicacion'    => 'maxlen:150',
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
            'estado'       => 'required|in:activo,inactivo',
        ],
        'labels' => ['clase' => 'Tipo', 'codigo' => 'Codigo', 'nombre' => 'Nombre', 'cliente' => 'Cliente',
                     'ubicacion' => 'Ubicacion', 'fecha_inicio' => 'Fecha de inicio', 'fecha_fin' => 'Fecha de fin'],
        'enum'   => true,
    ],
    'proveedores' => [
        'tabla'  => 'proveedores',
        'campos' => ['tipo_documento', 'ruc', 'nombre', 'direccion', 'contacto', 'telefono', 'correo', 'especialidad', 'estado'],
        'reglas' => [
            'nombre'      => 'required|maxlen:120',
            'ruc'         => 'maxlen:15',
            'direccion'   => 'maxlen:200',
            'contacto'    => 'maxlen:100',
            'telefono'    => 'maxlen:30|telefono',
            'correo'      => 'maxlen:120|email',
            'especialidad'=> 'maxlen:120',
            'estado'      => 'required|in:activo,inactivo',
        ],
        'labels' => ['nombre' => 'Razon social / nombre', 'ruc' => 'Documento', 'direccion' => 'Direccion', 'telefono' => 'Telefono', 'correo' => 'Correo'],
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

// ---- Areas: prefijo del codigo de usuario del personal (SIS, RH, ...) ----
if ($tipo === 'areas') {
    CodigoUsuario::asegurarAreas();
    $datos['codigo'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $datos['codigo']));
    $antesArea = $id > 0 ? Database::getOne('SELECT nombre, codigo FROM areas WHERE id = ?', [$id]) : null;
    $usados = array_column(Database::get("SELECT codigo FROM areas WHERE codigo IS NOT NULL AND codigo <> '' AND id <> ?", [$id]), 'codigo');
    // el prefijo sigue al nombre cuando era el automatico: si renombran el area y el codigo no se toco a mano, se rearma
    $sigueNombre = $antesArea && $datos['codigo'] === (string)$antesArea['codigo']
        && $datos['nombre'] !== $antesArea['nombre']
        && (string)$antesArea['codigo'] === CodigoUsuario::prefijoDeNombre($antesArea['nombre'], $usados);
    if ($datos['codigo'] === '' || $sigueNombre) {
        // sin codigo escrito (o automatico): se arma desde el nombre actual; el personal del area lo toma
        $datos['codigo'] = CodigoUsuario::prefijoDeNombre($datos['nombre'], $usados);
    } elseif (!CodigoUsuario::prefijoValido($datos['codigo'])) {
        Response::validation(['codigo' => 'El codigo debe tener de 2 a 6 letras o numeros (ej: SIS, RH, ADM).']);
    }
    $codActual = $id > 0 ? (string)Database::getValue('SELECT codigo FROM areas WHERE id = ?', [$id]) : '';
    if (CodigoUsuario::reservado($datos['codigo']) && $datos['codigo'] !== $codActual) {
        Response::validation(['codigo' => 'Los prefijos PRY y OBR estan reservados para los codigos de Proyectos y Obras. Use otro (ej: PROY, OBRA).']);
    }
    $choque = Database::getValue('SELECT nombre FROM areas WHERE codigo = ? AND id <> ?', [$datos['codigo'], $id]);
    if ($choque) {
        Response::validation(['codigo' => "El codigo {$datos['codigo']} ya lo usa el area «{$choque}»."]);
    }
}

// ---- Proveedores: el documento (RUC o DNI) se valida y no se repite ----
if ($tipo === 'proveedores') {
    Esquema::columna('proveedores', 'tipo_documento', "VARCHAR(3) NOT NULL DEFAULT 'ruc'");
    Esquema::columna('proveedores', 'direccion', 'VARCHAR(200) NULL');

    $datos['tipo_documento'] = ($datos['tipo_documento'] === 'dni') ? 'dni' : 'ruc';
    $datos['ruc'] = Documento::limpiar($datos['ruc']);     // solo digitos

    if ($datos['ruc'] !== '') {
        // si no lo cambiaron (dato antiguo), no se les bloquea la edicion de otros campos
        $antesDoc = $id > 0 ? Database::getOne('SELECT ruc, tipo_documento FROM proveedores WHERE id = ?', [$id]) : null;
        $igual = $antesDoc && $antesDoc['ruc'] === $datos['ruc'] && ($antesDoc['tipo_documento'] ?? 'ruc') === $datos['tipo_documento'];
        if (!$igual) {
            $err = $datos['tipo_documento'] === 'dni'
                ? (Documento::dniValido($datos['ruc']) ? null : 'El DNI debe tener 8 dígitos.')
                : Documento::rucError($datos['ruc']);
            if ($err !== null) Response::validation(['ruc' => $err]);
        }
        $dup = Database::getOne(
            'SELECT id, nombre FROM proveedores WHERE ruc = ? AND tipo_documento = ? AND id <> ? LIMIT 1',
            [$datos['ruc'], $datos['tipo_documento'], $id]);
        if ($dup) {
            Response::validation(['ruc' => 'Ya existe el proveedor «' . $dup['nombre'] . '» con este '
                . strtoupper($datos['tipo_documento']) . '.']);
        }
    }
}

// ---- Proyectos y obras: codigo propio o automatico, sin repetir ----
if ($tipo === 'proyectos') {
    Destinos::asegurar();
    $datos['tipo'] = (($_POST['clase'] ?? '') === 'obra') ? 'obra' : 'proyecto';
    $datos['codigo'] = strtoupper(preg_replace('/[^A-Za-z0-9\-_.]/', '', $datos['codigo']));
    $quien = $datos['tipo'] === 'obra' ? 'la obra' : 'el proyecto';

    $antes = $id > 0 ? Database::getOne('SELECT tipo, codigo FROM proyectos WHERE id = ?', [$id]) : null;
    if ($id > 0 && !$antes) Response::error('El registro no existe.');
    if ($antes && $antes['tipo'] !== $datos['tipo']
        && Database::getValue('SELECT COUNT(*) FROM asignaciones WHERE proyecto_id = ?', [$id])) {
        Response::validation(['clase' => 'No se puede cambiar el tipo: ya tuvo equipos asignados como '
            . ($antes['tipo'] === 'obra' ? 'obra' : 'proyecto') . '.']);
    }
    if ($datos['codigo'] === '') {
        $datos['codigo'] = ($antes && $antes['tipo'] === $datos['tipo'] && $antes['codigo'] !== '')
            ? $antes['codigo'] : Destinos::codigoNuevo($datos['tipo']);
    }
    if ($errCod = Destinos::errorCodigo($datos['codigo'])) Response::validation(['codigo' => $errCod]);
    if (Database::getValue('SELECT id FROM proyectos WHERE codigo = ? AND id <> ?', [$datos['codigo'], $id])) {
        Response::validation(['codigo' => "El codigo {$datos['codigo']} ya lo usa otro registro."]);
    }
    if (Database::getValue('SELECT id FROM proyectos WHERE nombre = ? AND tipo = ? AND id <> ?',
                           [$datos['nombre'], $datos['tipo'], $id])) {
        Response::validation(['nombre' => "Ya existe $quien «{$datos['nombre']}»."]);
    }
    $datos['responsable_id'] = (int)$datos['responsable_id'] > 0 ? (int)$datos['responsable_id'] : null;
    if ($datos['responsable_id'] && !Database::getValue('SELECT id FROM personal WHERE id = ?', [$datos['responsable_id']])) {
        Response::validation(['responsable_id' => 'El responsable ya no existe.']);
    }
    foreach (['cliente', 'ubicacion', 'fecha_inicio', 'fecha_fin'] as $campoOpc) {
        if ($datos[$campoOpc] === '') $datos[$campoOpc] = null;
    }
    if ($datos['fecha_inicio'] && $datos['fecha_fin'] && $datos['fecha_fin'] < $datos['fecha_inicio']) {
        Response::validation(['fecha_fin' => 'La fecha de fin no puede ser anterior a la de inicio.']);
    }
    // un registro con equipos asignados no se puede dejar inactivo desde el formulario
    if ($id > 0 && $datos['estado'] === 'inactivo') {
        $n = (int)Database::getValue("SELECT COUNT(*) FROM asignaciones WHERE proyecto_id = ? AND estado IN ('activa','vencida')", [$id]);
        if ($n > 0) {
            Response::validation(['estado' => "Tiene $n equipo(s) asignados: devuelvalos o trasladelos antes de desactivar."]);
        }
    }
}

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

    if ($tipo === 'areas') {
        // area + codigos del personal + roles automaticos: todo o nada
        Database::begin();
        try {
            Database::update($c['tabla'], $datos, 'id = ?', [$id]);
            $sync = CodigoUsuario::sincronizarArea($id, $antes, $datos);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            Response::error('No se pudo actualizar el area: ' . $e->getMessage());
        }
        Auditoria::registrar('actualizar', $c['tabla'], $id, $antes, $datos + ['sincronizado' => $sync]);
        $msg = 'Registro actualizado correctamente.';
        if ($sync['codigo_cambio']) {
            $msg = 'Area actualizada. Prefijo ' . ($antes['codigo'] ?: '—') . ' → ' . $datos['codigo'] . ': '
                . ($sync['personas'] ? $sync['personas'] . ' persona(s) pasaron a su nuevo codigo' : 'no habia personal vigente que recodificar')
                . ($sync['carnets'] ? ' (' . $sync['carnets'] . ' carnet(s) marcados para reimprimir)' : '') . '.';
        }
        if ($sync['roles']) $msg .= ' Se renombraron ' . $sync['roles'] . ' rol(es) del area.';
        Response::ok($sync + ['id' => $id, 'mensaje' => $msg], $msg);
    }

    Database::update($c['tabla'], $datos, 'id = ?', [$id]);
    Auditoria::registrar('actualizar', $c['tabla'], $id, $antes, $datos);
    Response::ok(['id' => $id], 'Registro actualizado correctamente.');
}

 $nuevoId = Database::insert($c['tabla'], $datos);
Auditoria::registrar('crear', $c['tabla'], $nuevoId, null, $datos);
// ---- ROLES AUTOMATICOS: jefe + personal por area nueva ----
            if ($tipo === 'areas') {
                crearRolesArea($nuevoId, trim($_POST['nombre']));
            }

            Response::ok(['id' => $nuevoId], 'Registro creado correctamente.');