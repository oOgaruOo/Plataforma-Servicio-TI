<?php
// ============================================================
// SIGTI - Exportar inventario (respeta filtros de la vista)
// ?formato=csv|excel&f_estado=&f_tipo=&f_garantia=&csrf_token=
// Nota: valida CSRF por GET (descarga directa por enlace).
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('equipos', 'ver');

// ---- CSRF via GET (la descarga es un enlace, no POST) ----
 $tokenGet = (string)($_GET['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenGet)) {
    Response::errorCsrf();
}

// ---- mismos filtros que listar.php ----
 $fEstado   = $_GET['f_estado'] ?? '';
 $fTipo     = (int)($_GET['f_tipo'] ?? 0);
 $fGarantia = (int)($_GET['f_garantia'] ?? 0);

 $where  = [];
 $params = [];
 $estadosValidos = ['en_stock','asignado','en_prestamo','en_revision','en_mantenimiento',
                   'en_reparacion_externa','obsoleto','dado_de_baja'];
if (in_array($fEstado, $estadosValidos, true)) { $where[] = 'e.estado = ?'; $params[] = $fEstado; }
if ($fTipo > 0) { $where[] = 'e.tipo_equipo_id = ?'; $params[] = $fTipo; }
if ($fGarantia === 1) {
    $where[] = 'e.garantia_hasta IS NOT NULL AND e.garantia_hasta >= CURDATE() '
             . 'AND e.garantia_hasta <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) '
             . "AND e.estado <> 'dado_de_baja'";
}
 $cond = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

 $sql = "SELECT e.codigo, t.nombre AS tipo, e.marca, e.modelo, e.nro_serie,
               e.activo_fijo, e.imei, e.mac, e.estado, e.condicion,
               e.especificaciones, e.costo, e.fecha_compra, e.proveedor_compra,
               e.garantia_hasta, e.ubicacion,
               CONCAT(p.nombres, ' ', p.apellidos) AS asignado_a
        FROM equipos e
        INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
        LEFT JOIN (
            SELECT asig.equipo_id, per.nombres, per.apellidos
            FROM asignaciones asig
            INNER JOIN personal per ON per.id = asig.personal_id
            WHERE asig.estado IN ('activa','vencida')
        ) p ON p.equipo_id = e.id" . $cond . "
        ORDER BY e.codigo ASC
        LIMIT 10000";
 $equipos = Database::get($sql, $params);

 $COND_TXT = ['nuevo' => 'Nuevo', 'bueno' => 'Bueno', 'regular' => 'Regular',
             'danado' => 'Danado', 'irreparable' => 'Irreparable'];
 $EST_TXT  = ['en_stock' => 'En stock', 'asignado' => 'Asignado', 'en_prestamo' => 'En prestamo',
             'en_revision' => 'En revision', 'en_mantenimiento' => 'En mantenimiento',
             'en_reparacion_externa' => 'Reparacion externa', 'obsoleto' => 'Obsoleto',
             'dado_de_baja' => 'Dado de baja'];

 $formato = ($_GET['formato'] ?? 'csv') === 'excel' ? 'excel' : 'csv';
 $fecha   = date('Ymd_His');

// ==================== CSV (UTF-8 BOM + ';' -> Excel espanol) ====================
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventario_equipos_' . $fecha . '.csv"');
    header('Cache-Control: no-store');

    echo "\xEF\xBB\xBF";   // BOM: Excel reconoce UTF-8
    $out = fopen('php://output', 'w');

    fputcsv($out, ['Codigo', 'Tipo', 'Marca', 'Modelo', 'N Serie', 'Activo Fijo', 'IMEI', 'MAC',
                   'Estado', 'Condicion', 'Procesador', 'RAM', 'Disco', 'Sistema Operativo',
                   'Costo', 'Fecha Compra', 'Proveedor', 'Garantia Hasta', 'Ubicacion',
                   'Asignado a'], ';');

    foreach ($equipos as $e) {
        $s = $e['especificaciones'] ? json_decode($e['especificaciones'], true) : [];
        fputcsv($out, [
            $e['codigo'], $e['tipo'], $e['marca'], $e['modelo'], $e['nro_serie'],
            $e['activo_fijo'], $e['imei'], $e['mac'],
            $EST_TXT[$e['estado']] ?? $e['estado'],
            $COND_TXT[$e['condicion']] ?? $e['condicion'],
            $s['cpu'] ?? '', $s['ram'] ?? '', $s['disco'] ?? '', $s['so'] ?? '',
            $e['costo'], $e['fecha_compra'], $e['proveedor_compra'],
            $e['garantia_hasta'], $e['ubicacion'], $e['asignado_a'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

// ==================== EXCEL (HTML .xls con formato) ====================
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="inventario_equipos_' . $fecha . '.xls"');
header('Cache-Control: no-store');

echo '<html><head><meta charset="utf-8"></head><body>';
echo '<table border="1">';
echo '<tr><th colspan="20" style="background:#1e2732;color:#fff;font-size:14pt;'
   . 'font-family:Arial;padding:8px;">INVENTARIO DE EQUIPOS TI — '
   . count($equipos) . ' equipo(s) — ' . date('d/m/Y H:i') . '</th></tr>';
echo '<tr style="background:#e8ecf1;font-family:Arial;font-size:10pt;">';
foreach (['Codigo','Tipo','Marca','Modelo','N Serie','Activo Fijo','IMEI','MAC','Estado',
          'Condicion','Procesador','RAM','Disco','S.O.','Costo','Fecha Compra','Proveedor',
          'Garantia','Ubicacion','Asignado a'] as $h) {
    echo '<th>' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';

 $colores = ['en_stock' => '#dcfce7', 'asignado' => '#dbeafe', 'en_prestamo' => '#cffafe',
            'en_mantenimiento' => '#fef3c7', 'en_reparacion_externa' => '#fef3c7',
            'en_revision' => '#fef3c7', 'obsoleto' => '#f1f5f9', 'dado_de_baja' => '#e5e7eb'];

foreach ($equipos as $e) {
    $s = $e['especificaciones'] ? json_decode($e['especificaciones'], true) : [];
    $bg = $colores[$e['estado']] ?? '#ffffff';
    echo '<tr style="font-family:Arial;font-size:9.5pt;background:' . $bg . '">';
    $celdas = [
        $e['codigo'], $e['tipo'], $e['marca'], $e['modelo'], $e['nro_serie'],
        $e['activo_fijo'], $e['imei'], $e['mac'],
        $EST_TXT[$e['estado']] ?? $e['estado'],
        $COND_TXT[$e['condicion']] ?? $e['condicion'],
        $s['cpu'] ?? '', $s['ram'] ?? '', $s['disco'] ?? '', $s['so'] ?? '',
        $e['costo'] !== null ? number_format((float)$e['costo'], 2) : '',
        $e['fecha_compra'], $e['proveedor_compra'], $e['garantia_hasta'],
        $e['ubicacion'], $e['asignado_a'] ?? '',
    ];
    foreach ($celdas as $c) {
        echo '<td>' . htmlspecialchars((string)$c) . '</td>';
    }
    echo '</tr>';
}
echo '</table></body></html>';
exit;