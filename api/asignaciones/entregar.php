<?php
// ============================================================
// SIGTI - Registrar ENTREGA / PRESTAMO / CAMBIO de equipo
// v2: bugfix -> la condicion/obs del equipo viejo del CAMBIO
//     se pasan como parametros al armar el acta (antes $GLOBALS).
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('asignaciones', 'crear');

 $operacion = $_POST['operacion'] ?? '';
if (!in_array($operacion, ['entrega','prestamo','cambio'], true)) {
    Response::validation(['operacion' => 'Operacion invalida.']);
}

 $personalId = (int)($_POST['personal_id'] ?? 0);
 $p = Database::getOne(
    "SELECT p.*, a.nombre AS area FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id WHERE p.id = ?", [$personalId]);
if (!$p) Response::validation(['personal_id' => 'El colaborador no existe.']);
if (in_array($p['estado'], ['cesado','en_proceso_cese'], true)) {
    Response::validation(['personal_id' => 'El colaborador esta cesado: no puede recibir equipos.']);
}

 $equipoId = (int)($_POST['equipo_id'] ?? 0);
 $eq = Database::getOne(
    "SELECT e.*, t.nombre AS tipo, t.familia FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id WHERE e.id = ?", [$equipoId]);
if (!$eq) Response::validation(['equipo_id' => 'El equipo no existe.']);
if ($eq['estado'] !== 'en_stock') {
    Response::validation(['equipo_id' => "El equipo {$eq['codigo']} no esta en stock (estado: {$eq['estado']})."]);
}
if (in_array($eq['condicion'], ['danado','irreparable'], true)) {
    Response::validation(['equipo_id' => "El equipo esta en condicion «{$eq['condicion']}»: no debe asignarse."]);
}

 $tipoAsig = 'permanente';
 $retorno = null;
if ($operacion === 'prestamo') {
    $tipoAsig = 'prestamo';
    $retorno = trim((string)($_POST['fecha_retorno'] ?? ''));
    if ($retorno === '') Response::validation(['fecha_retorno' => 'Indique la fecha esperada de retorno.']);
    $d = DateTime::createFromFormat('Y-m-d', $retorno);
    if (!$d || $d->format('Y-m-d') !== $retorno) {
        Response::validation(['fecha_retorno' => 'Fecha invalida.']);
    }
}

 $equipoViejoId = null;
 $asigVieja = null;
 $condDevViejo = '';
 $obsDevViejo = '';
 $motivoCambio = '';
if ($operacion === 'cambio') {
    $motivoCambio = $_POST['motivo'] ?? 'renovacion';
    $equipoViejoId = (int)($_POST['equipo_viejo_id'] ?? 0);
    $asigVieja = Database::getOne(
        "SELECT * FROM asignaciones WHERE equipo_id = ? AND personal_id = ? AND estado IN ('activa','vencida')",
        [$equipoViejoId, $personalId]);
    if (!$asigVieja) {
        Response::validation(['equipo_viejo_id' => 'El colaborador no tiene ese equipo asignado.']);
    }
    $condDevViejo = trim((string)($_POST['cond_dev'] ?? ''));
    $obsDevViejo  = trim((string)($_POST['obs_dev'] ?? ''));
}

Database::begin();
try {
    if ($operacion === 'cambio') {
        Database::update('asignaciones', [
            'estado'               => 'devuelta',
            'fecha_devolucion_real'=> date('Y-m-d H:i:s'),
            'condicion_devolucion' => $condDevViejo ?: 'bueno',
            'obs_devolucion'       => $obsDevViejo ?: null,
        ], 'id = ?', [$asigVieja['id']]);

        Database::update('equipos', ['estado' => 'en_revision'], 'id = ?', [$equipoViejoId]);
        Database::insert('equipo_historial', [
            'equipo_id' => $equipoViejoId, 'usuario_id' => Auth::userId(),
            'evento' => 'devuelto', 'detalle' => "Cambio de equipo ({$eq['codigo']})",
        ]);
    }

    $asigId = Database::insert('asignaciones', [
        'equipo_id'                 => $equipoId,
        'personal_id'              => $personalId,
        'tipo'                     => $tipoAsig,
        'estado'                   => 'activa',
        'fecha_entrega'            => date('Y-m-d H:i:s'),
        'fecha_devolucion_esperada'=> $retorno,
        'condicion_entrega'        => $eq['condicion'],
    ]);

    Database::update('equipos', ['estado' => $tipoAsig === 'prestamo' ? 'en_prestamo' : 'asignado'],
        'id = ?', [$equipoId]);
    Database::run("UPDATE equipo_accesorios SET entregado = 1 WHERE equipo_id = ?", [$equipoId]);
    Database::insert('equipo_historial', [
        'equipo_id' => $equipoId, 'usuario_id' => Auth::userId(),
        'evento' => 'asignado',
        'detalle' => 'A ' . $p['nombres'] . ' ' . $p['apellidos'] .
            ($tipoAsig === 'prestamo' ? ' (prestamo hasta ' . $retorno . ')' : '') .
            " · $tipoAsig",
    ]);


    // --- BITACORA DE UBICACION: asignacion ---
    Database::insert('equipo_ubicaciones', [
        'equipo_id'  => $equipoId,
        'ubicacion'  => 'Con ' . $p['nombres'] . ' ' . $p['apellidos'] .
                        ' (' . ($p['cargo'] ?: '') . ')',
        'tipo'       => 'asignacion',
        'personal_id'=> $personalId,
        'usuario_id' => Auth::userId(),
    ]);
    Database::update('equipos',
        ['ubicacion' => 'Con ' . $p['nombres'] . ' ' . $p['apellidos']],
        'id = ?', [$equipoId]);    // ---- ACTA LUMAT AUTOMATICA ----
    $armarEquipo = function (array $e, bool $dev, string $condDev = '', string $obsDev = ''): array {
        $acc = Database::get('SELECT nombre FROM equipo_accesorios WHERE equipo_id = ? ORDER BY id', [$e['id']]);
        return [
            'id' => (int)$e['id'], 'codigo' => $e['codigo'], 'tipo' => $e['tipo'],
            'familia' => $e['familia'], 'marca' => $e['marca'], 'modelo' => $e['modelo'] ?: '',
            'serie' => $e['nro_serie'] ?: '', 'imei' => $e['imei'] ?: '',
            'specs' => $e['especificaciones'] ? json_decode($e['especificaciones'], true) : [],
            'accesorios' => array_column($acc, 'nombre'),
            'condicion' => ucfirst($e['condicion']),
            'condicion_devolucion' => $dev ? $condDev : '',
            'obs_devolucion' => $dev ? $obsDev : '',
        ];
    };

    $tipoActa = $operacion === 'cambio' ? 'cambio' : 'entrega';
    $formato = $tipoActa === 'cambio'
        ? (Database::getValue("SELECT valor FROM configuracion WHERE clave='acta_formato_cambio'") ?: 'LUMAT-TI-FOR-002')
        : ($eq['familia'] === 'movil'
            ? (Database::getValue("SELECT valor FROM configuracion WHERE clave='acta_formato_entrega_movil'") ?: 'LUMAT-TI-FOR-004')
            : (Database::getValue("SELECT valor FROM configuracion WHERE clave='acta_formato_entrega'") ?: 'LUMAT-TI-FOR-003'));

    $eqViejo = null;
    if ($operacion === 'cambio') {
        $eqViejo = Database::getOne(
            "SELECT e.*, t.nombre AS tipo, t.familia FROM equipos e
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id WHERE e.id = ?", [$equipoViejoId]);
    }

    $contenido = [
        'tipo' => $tipoActa,
        'formato_codigo' => $formato,
        'familia' => $eq['familia'],
        'colaborador' => [
            'nombre' => $p['nombres'] . ' ' . $p['apellidos'],
            'dni' => $p['dni'], 'cargo' => $p['cargo'] ?: '—',
            'area' => $p['area'] ?: '—', 'jefe' => '—',
            'fecha_cese' => $p['fecha_cese'] ?: '—',
        ],
        'motivo' => $motivoCambio,
        'motivo_texto' => trim((string)($_POST['motivo_texto'] ?? '')),
        'equipos_entregados' => [$armarEquipo($eq, false)],
        'equipos_devueltos' => $eqViejo
            ? [$armarEquipo($eqViejo, true, $condDevViejo, $obsDevViejo)]
            : [],
        'validacion_ti' => json_decode((string)($_POST['validacion_ti'] ?? '[]'), true) ?: [],
        'verificacion_accesos' => [],
        'observaciones' => trim((string)($_POST['observaciones'] ?? '')),
        'fecha' => date('Y-m-d H:i:s'),
        'fecha_formal' => date('d') . ' / ' . date('m') . ' / ' . date('Y'),
    ];

    $codigoActa = Correlativo::generar('acta');
    $token = bin2hex(random_bytes(32));
    Database::insert('actas', [
        'codigo' => $codigoActa, 'tipo' => $tipoActa, 'personal_id' => $personalId,
        'responsable_usuario_id' => Auth::userId(), 'fecha' => date('Y-m-d H:i:s'),
        'contenido' => json_encode($contenido, JSON_UNESCAPED_UNICODE),
        'asignacion_id' => $asigId, 'token' => $token, 'firmado' => 0,
    ]);

    Auditoria::registrar('crear', 'asignaciones', $asigId, null,
        ['equipo' => $eq['codigo'], 'persona' => $p['dni'], 'operacion' => $operacion]);

    Database::commit();

    Response::ok([
        'asignacion' => $asigId,
        'acta' => $codigoActa,
        'acta_url' => BASE_URL . 'acta.php?token=' . $token,
    ], "Equipo {$eq['codigo']} entregado a {$p['nombres']}. Acta $codigoActa generada.");

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al registrar la entrega: ' . $e->getMessage());
}