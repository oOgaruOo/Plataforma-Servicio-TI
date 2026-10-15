<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Equipos: alta (codigo EQ) + edicion
// especificaciones llega como JSON con los campos de la FAMILIA
// accesorios llega como JSON: [{"nombre":"Cargador"},...]
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
 $id      = (int)($_POST['id'] ?? 0);
 $esNuevo = ($id === 0);

// alta = equipos.crear · edicion = equipos.editar (antes la edicion pedia "crear")
Auth::requirePermission('equipos', $esNuevo ? 'crear' : 'editar');

 $v = Validator::make($_POST, [
    'tipo_equipo_id'  => 'required|integer',
    'marca'           => 'required|maxlen:60',
    'modelo'          => 'maxlen:80',
    'nro_serie'       => 'maxlen:80',
    'activo_fijo'     => 'maxlen:40',
    'imei'            => 'maxlen:20',
    'mac'             => 'maxlen:20',
    'condicion'       => 'required|in:nuevo,bueno,regular,danado,irreparable',
    'fecha_compra'    => 'date',
    'proveedor_compra'=> 'maxlen:100',
    'costo'           => 'numeric|max:99999999',
    'garantia_hasta'  => 'date',
    'ubicacion'       => 'maxlen:100',
    'observaciones'   => 'maxlen:500',
    'estado'          => 'in:en_stock,en_revision,obsoleto',
], [
    'tipo_equipo_id' => 'Tipo de equipo', 'marca' => 'Marca', 'modelo' => 'Modelo',
    'nro_serie' => 'N de serie', 'activo_fijo' => 'Codigo de activo fijo',
    'condicion' => 'Condicion', 'costo' => 'Costo',
    'garantia_hasta' => 'Garantia hasta', 'proveedor_compra' => 'Proveedor de compra',
    'imei' => 'IMEI', 'mac' => 'MAC',
]);
if ($v->fails()) Response::validation($v->errors());

// ---- MAC: se guarda siempre como AA:BB:CC:DD:EE:FF (asi se compara con la que reporta el equipo) ----
 $macIn = trim((string)($_POST['mac'] ?? ''));
if ($macIn !== '') {
    $macNorm = Rastreo::normalizarMac($macIn);
    if ($macNorm === null) Response::validation(['mac' => 'La MAC debe tener 12 caracteres hexadecimales. Ejemplo: AA:BB:CC:DD:EE:FF']);
    $_POST['mac'] = $macNorm;
}

// ---- Tipo valido + familia ----
 $tipoEq = Database::getOne(
    'SELECT id, familia FROM tipo_equipos WHERE id = ?', [(int)$_POST['tipo_equipo_id']]);
if (!$tipoEq) Response::validation(['tipo_equipo_id' => 'El tipo de equipo no existe.']);

 $nulo = fn($k) => (trim((string)($_POST[$k] ?? '')) === '') ? null : trim((string)$_POST[$k]);

 $serie = $nulo('nro_serie');
if ($serie && Database::getValue('SELECT id FROM equipos WHERE nro_serie = ? AND id <> ?', [$serie, $id])) {
    Response::validation(['nro_serie' => "La serie «{$serie}» ya esta registrada en otro equipo."]);
}
 $imei = $nulo('imei');
if ($imei && Database::getValue('SELECT id FROM equipos WHERE imei = ? AND id <> ?', [$imei, $id])) {
    Response::validation(['imei' => "El IMEI «{$imei}» ya esta registrado en otro equipo."]);
}

// ---- Especificaciones dinamicas (JSON de la familia) ----
 $espJson = trim((string)($_POST['especificaciones'] ?? '{}'));
 $esp = json_decode($espJson, true);
if (!is_array($esp)) $esp = [];
 $limpio = [];
foreach ($esp as $k => $val) {
    $k = preg_replace('/[^a-zA-Z0-9_]/', '', substr((string)$k, 0, 40));
    if ($k === '') continue;
    $val = trim((string)$val);
    if ($val === '' || mb_strlen($val) > 120) continue;
    $limpio[$k] = $val;
}
 $especificaciones = $limpio ? json_encode($limpio, JSON_UNESCAPED_UNICODE) : null;

 $datos = [
    'tipo_equipo_id'  => (int)$_POST['tipo_equipo_id'],
    'marca'           => trim($_POST['marca']),
    'modelo'          => $nulo('modelo'),
    'nro_serie'       => $serie,
    'activo_fijo'     => $nulo('activo_fijo'),
    'imei'            => $imei,
    'mac'             => $nulo('mac'),
    'condicion'       => $_POST['condicion'],
    'especificaciones'=> $especificaciones,
    'fecha_compra'    => $nulo('fecha_compra'),
    'proveedor_compra'=> $nulo('proveedor_compra'),
    'costo'           => $nulo('costo') !== null ? (float)$_POST['costo'] : null,
    'garantia_hasta'  => $nulo('garantia_hasta'),
    'ubicacion'       => $nulo('ubicacion'),
    'observaciones'   => $nulo('observaciones'),
];

// ---- Accesorios (JSON de nombres: del catalogo o libres) ----
 $accesorios = [];
 $jsonAcc = trim((string)($_POST['accesorios'] ?? '[]'));
if ($jsonAcc !== '') {
    $dec = json_decode($jsonAcc, true);
    if (is_array($dec)) {
        foreach ($dec as $a) {
            $nombre = trim((string)($a['nombre'] ?? ''));
            if ($nombre !== '' && !in_array($nombre, $accesorios, true)) {
                $accesorios[] = mb_substr($nombre, 0, 80);
            }
        }
    }
}

Database::begin();
try {
    if ($esNuevo) {
        $datos['estado'] = 'en_stock';
        $codigo = Correlativo::generar('equipo');
        $datos['codigo'] = $codigo;

        $nuevoId = Database::insert('equipos', $datos);

        foreach ($accesorios as $nombre) {
            Database::insert('equipo_accesorios', ['equipo_id' => $nuevoId, 'nombre' => $nombre]);
        }

        Database::insert('equipo_historial', [
            'equipo_id'  => $nuevoId,
            'usuario_id' => Auth::userId(),
            'evento'     => 'creado',
            'detalle'    => "Alta en inventario ($codigo)" . ($accesorios ? ' con ' . count($accesorios) . ' accesorio(s)' : ''),
        ]);

        Database::commit();
        // --- BITACORA DE UBICACION (seguimiento de computo) ---
        $esComputo = Database::getValue(
            "SELECT COUNT(*) FROM tipo_equipos WHERE id = ? AND familia = 'computo'",
            [(int)$_POST['tipo_equipo_id']]);
        if ($esComputo) {
            Database::insert('equipo_ubicaciones', [
                'equipo_id'  => $nuevoId,
                'ubicacion'  => 'Almacen TI',
                'tipo'       => 'alta',
                'observaciones' => 'Alta en inventario',
            ]);
            Database::update('equipos',
                ['ubicacion' => 'Almacen TI'], 'id = ?', [$nuevoId]);
        }
        Auditoria::registrar('crear', 'equipos', $nuevoId, null, $datos);

        // ---- Software instalado: [{"software_id":3,"version":"22H2","licencia":"auto"},...] (se guarda en el modulo Software) ----
        $msgSw = '';
        $jsonSw = trim((string)($_POST['software'] ?? ''));
        if ($jsonSw !== '' && Auth::can('software', 'registrar')) {
            $decSw = json_decode($jsonSw, true);
            $okSw = 0; $sinLic = 0; $falloSw = [];
            foreach (is_array($decSw) ? array_slice($decSw, 0, 100) : [] as $it) {
                try {
                    $lic = $it['licencia'] ?? '';
                    $r = Software::instalar($nuevoId, (int)($it['software_id'] ?? 0), (string)($it['version'] ?? ''),
                        $lic === 'auto' ? 'auto' : (int)$lic, null, '', Auth::userId());
                    $okSw++;
                    if ($r['aviso']) $sinLic++;
                } catch (InvalidArgumentException $exSw) {
                    $falloSw[] = $exSw->getMessage();
                }
            }
            if ($okSw) $msgSw .= " Software registrado: $okSw programa(s)" . ($sinLic ? " ($sinLic sin licencia asignada)" : '') . '.';
            if ($falloSw) $msgSw .= ' No se pudo registrar: ' . implode(' ', array_slice($falloSw, 0, 3));
        }

        Response::ok(['id' => $nuevoId, 'codigo' => $codigo, 'abrir_detalle' => true,
                       'mensaje' => "Equipo $codigo registrado." . $msgSw],
            "Equipo $codigo registrado y disponible en stock." . $msgSw);
    }

    $antes = Database::getOne('SELECT * FROM equipos WHERE id = ?', [$id]);
    if (!$antes) { Database::rollback(); Response::error('El equipo no existe.'); }

    if ($antes['estado'] === 'dado_de_baja') {
        Database::rollback();
        Response::error('Un equipo dado de baja es historico: no puede editarse.');
    }

    $estadoBloqueado = in_array($antes['estado'], ['asignado','en_prestamo','en_mantenimiento',
                                                   'en_reparacion_externa'], true);
    $nuevoEstado = $_POST['estado'] ?? '';
    $estadosManuales = ['en_stock','en_revision','obsoleto'];
    if (!$estadoBloqueado && in_array($nuevoEstado, $estadosManuales, true)) {
        $datos['estado'] = $nuevoEstado;
    }

    Database::update('equipos', $datos, 'id = ?', [$id]);

    $actuales = Database::get('SELECT id, nombre, entregado FROM equipo_accesorios WHERE equipo_id = ?', [$id]);
    $nombresActuales = array_map(fn($a) => $a['nombre'], $actuales);

    foreach ($accesorios as $nombre) {
        if (!in_array($nombre, $nombresActuales, true)) {
            Database::insert('equipo_accesorios', ['equipo_id' => $id, 'nombre' => $nombre]);
            Database::insert('equipo_historial', [
                'equipo_id' => $id, 'usuario_id' => Auth::userId(),
                'evento' => 'accesorio_agregado', 'detalle' => $nombre,
            ]);
        }
    }
    foreach ($actuales as $a) {
        if (!in_array($a['nombre'], $accesorios, true) && (int)$a['entregado'] === 0) {
            Database::delete('equipo_accesorios', 'id = ?', [$a['id']]);
            Database::insert('equipo_historial', [
                'equipo_id' => $id, 'usuario_id' => Auth::userId(),
                'evento' => 'accesorio_retirado', 'detalle' => $a['nombre'],
            ]);
        }
    }

    $cambios = [];
    foreach ($datos as $k => $nuevoVal) {
        $antiguo = $antes[$k] ?? null;
        if ((string)$antiguo !== (string)$nuevoVal) {
            $cambios[] = "$k: «{$antiguo}» → «{$nuevoVal}»";
        }
    }
    if ($cambios) {
        Database::insert('equipo_historial', [
            'equipo_id'  => $id,
            'usuario_id' => Auth::userId(),
            'evento'     => 'actualizado',
            'detalle'    => mb_substr(implode('; ', $cambios), 0, 250),
        ]);
    }

    Database::commit();
    Auditoria::registrar('actualizar', 'equipos', $id, $antes, $datos);

    $msg = 'Equipo actualizado correctamente.';
    if ($estadoBloqueado && $nuevoEstado && $nuevoEstado !== $antes['estado']) {
        $msg .= ' El estado no cambio: se modifica solo por devolucion/mantenimiento.';
    }
    Response::ok(['id' => $id], $msg);

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al guardar el equipo: ' . $e->getMessage());
}