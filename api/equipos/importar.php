<?php
// ============================================================
// SIGTI - Importacion masiva de equipos (desde Excel/CSV)
// Recibe JSON: {filas: [{tipo, marca, modelo, serie, activo_fijo,
//   imei, mac, condicion, fecha_compra, proveedor, costo, garantia,
//   ubicacion, cpu, ram, disco, so, accesorios}]}
// Inserta en UNA transaccion; devuelve insertados + errores por fila.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('equipos', 'crear');

 $input = json_decode((string) file_get_contents('php://input'), true);
 $filas = $input['filas'] ?? [];

if (!is_array($filas) || count($filas) === 0) {
    Response::validation(['archivo' => 'No hay filas validas para importar.']);
}
if (count($filas) > 500) {
    Response::error('Maximo 500 equipos por lote. Divida el archivo.');
}

// ---- catalogo de tipos (match por nombre, minusculas) ----
 $tipos = [];
foreach (Database::get('SELECT id, nombre FROM tipo_equipos') as $t) {
    $tipos[mb_strtolower(trim($t['nombre']))] = (int) $t['id'];
}

 $condMap = [
    'nuevo' => 'nuevo', 'bueno' => 'bueno', 'regular' => 'regular',
    'danado' => 'danado', 'danado' => 'danado', 'irreparable' => 'irreparable',
];

 $normFecha = function (string $f): ?string {
    $f = trim($f);
    if ($f === '') return null;
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $f);
        if ($d && $d->format($fmt) === $f) return $d->format('Y-m-d');
    }
    return null;   // no reconocida -> se ignora
};

 $errores = [];
 $validas = [];
 $seriesLote = [];
 $imeisLote = [];

foreach ($filas as $i => $f) {
    $n = $i + 2;   // fila del Excel (1 = encabezado)
    $err = [];

    $tipoNombre = mb_strtolower(trim((string)($f['tipo'] ?? '')));
    $tipoId = $tipos[$tipoNombre] ?? null;
    if ($tipoNombre === '')                $err[] = 'Falta el Tipo de Equipo.';
    elseif ($tipoId === null)              $err[] = 'Tipo «' . trim((string)$f['tipo']) . '" no existe en el catalogo.';

    $marca = trim((string)($f['marca'] ?? ''));
    if ($marca === '') $err[] = 'Falta la Marca.';
    if (mb_strlen($marca) > 60) $marca = mb_substr($marca, 0, 60);

    // serie / imei: unicos contra BD y dentro del lote
    $serie = trim((string)($f['serie'] ?? ''));
    if ($serie !== '') {
        if (isset($seriesLote[mb_strtolower($serie)])) {
            $err[] = 'Serie «' . $serie . '" repetida en el archivo.';
        } elseif (Database::getValue('SELECT id FROM equipos WHERE nro_serie = ?', [$serie])) {
            $err[] = 'Serie «' . $serie . '" ya registrada en el inventario.';
        }
    }
    $imei = trim((string)($f['imei'] ?? ''));
    if ($imei !== '') {
        if (isset($imeisLote[mb_strtolower($imei)])) {
            $err[] = 'IMEI «' . $imei . '" repetido en el archivo.';
        } elseif (Database::getValue('SELECT id FROM equipos WHERE imei = ?', [$imei])) {
            $err[] = 'IMEI «' . $imei . '" ya registrado en el inventario.';
        }
    }

    $condTxt = mb_strtolower(trim((string)($f['condicion'] ?? '')));
    $condicion = $condMap[$condTxt] ?? 'bueno';   // vacio -> bueno

    $costoTxt = trim((string)($f['costo'] ?? ''));
    $costo = null;
    if ($costoTxt !== '') {
        $costoTxt = preg_replace('/[^0-9.,]/', '', $costoTxt);
        if (strpos($costoTxt, ',') !== false && strpos($costoTxt, '.') !== false) {
            $costoTxt = str_replace(',', '', $costoTxt);
        } elseif (strpos($costoTxt, ',') !== false) {
            $costoTxt = str_replace(',', '.', $costoTxt);
        }
        $costo = is_numeric($costoTxt) ? (float) $costoTxt : null;
    }

    // especificaciones
    $specs = [];
    foreach (['cpu', 'ram', 'disco', 'so'] as $k) {
        $v = trim((string)($f[$k] ?? ''));
        if ($v !== '' && mb_strlen($v) <= 120) $specs[$k] = $v;
    }

    // accesorios: texto separado por comas
    $accesorios = [];
    $accTxt = trim((string)($f['accesorios'] ?? ''));
    if ($accTxt !== '') {
        foreach (preg_split('/[,;]/', $accTxt) as $a) {
            $a = trim($a);
            if ($a !== '' && mb_strlen($a) <= 80 && !in_array($a, $accesorios, true)) {
                $accesorios[] = $a;
            }
            if (count($accesorios) >= 15) break;
        }
    }

    if ($err) {
        $errores[] = ['fila' => $n, 'error' => implode(' ', $err)];
        continue;
    }

    $seriesLote[mb_strtolower($serie)] = true;
    if ($imei !== '') $imeisLote[mb_strtolower($imei)] = true;

    $validas[] = [
        'tipo_equipo_id' => $tipoId,
        'marca'          => $marca,
        'modelo'         => mb_substr(trim((string)($f['modelo'] ?? '')), 0, 80) ?: null,
        'nro_serie'      => $serie ?: null,
        'activo_fijo'    => mb_substr(trim((string)($f['activo_fijo'] ?? '')), 0, 40) ?: null,
        'imei'           => $imei ?: null,
        'mac'            => mb_substr(trim((string)($f['mac'] ?? '')), 0, 20) ?: null,
        'condicion'      => $condicion,
        'especificaciones' => $specs ? json_encode($specs, JSON_UNESCAPED_UNICODE) : null,
        'fecha_compra'   => $normFecha((string)($f['fecha_compra'] ?? '')),
        'proveedor_compra' => mb_substr(trim((string)($f['proveedor'] ?? '')), 0, 100) ?: null,
        'costo'          => $costo,
        'garantia_hasta' => $normFecha((string)($f['garantia'] ?? '')),
        'ubicacion'      => mb_substr(trim((string)($f['ubicacion'] ?? '')), 0, 100) ?: null,
        'accesorios'     => $accesorios,
    ];
}

if (!$validas) {
    Response::error('Ninguna fila paso la validacion. Corrija los errores e intente de nuevo.', $errores);
}

Database::begin();
try {
    $insertados = 0;
    foreach ($validas as $v) {
        $accesorios = $v['accesorios'];
        unset($v['accesorios']);

        $codigo = Correlativo::generar('equipo');
        $v['codigo'] = $codigo;
        $v['estado'] = 'en_stock';

        $nuevoId = Database::insert('equipos', $v);

        foreach ($accesorios as $nombre) {
            Database::insert('equipo_accesorios', ['equipo_id' => $nuevoId, 'nombre' => $nombre]);
        }
        Database::insert('equipo_historial', [
            'equipo_id' => $nuevoId, 'usuario_id' => Auth::userId(),
            'evento' => 'creado',
            'detalle' => 'Importacion masiva desde Excel' . ($accesorios ? ' (' . count($accesorios) . ' accesorio(s))' : ''),
        ]);
        $insertados++;
    }

    Database::commit();
    Auditoria::registrar('crear', 'equipos', null, null,
        ['importacion' => $insertados . ' equipos', 'errores' => count($errores)]);

    $msg = "$insertados equipo(s) importados correctamente.";
    if ($errores) $msg .= ' ' . count($errores) . ' fila(s) con error (revise el detalle).';

    Response::ok([
        'insertados' => $insertados,
        'errores'    => $errores,
        'total_filas' => count($filas),
    ], $msg);

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al importar (se revirtio todo el lote): ' . $e->getMessage());
}