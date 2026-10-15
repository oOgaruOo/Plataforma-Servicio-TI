<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Exportar mantenimientos (respeta filtros + buscador de la vista)
// ?formato=csv|excel&f_estado=&f_tipo=&f_proveedor=&f_atrasados=&f_buscar=&csrf_token=
// Nota: valida CSRF por GET (descarga directa por enlace), igual que equipos/exportar.php.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('mantenimiento', 'ver');
MantenimientoFlujo::asegurar();

// ---- CSRF via GET (la descarga es un enlace, no POST) ----
$tokenGet = (string)($_GET['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenGet)) {
    Response::errorCsrf();
}

// ---- mismos filtros que listar.php ----
$fEstado    = $_GET['f_estado'] ?? '';
$fTipo      = $_GET['f_tipo'] ?? '';
$fProveedor = (int)($_GET['f_proveedor'] ?? 0);
$fAtrasados = (int)($_GET['f_atrasados'] ?? 0) === 1;
$fBuscar    = trim((string)($_GET['f_buscar'] ?? ''));

$where  = [];
$params = [];

$estadosValidos = ['solicitado','en_evaluacion','en_proceso','enviado_proveedor','cotizado',
                   'aprobado','rechazado','en_reparacion','recibido_reparado','listo',
                   'entregado','devuelto_stock','cerrado','cancelado','no_reparable'];
if (in_array($fEstado, $estadosValidos, true)) { $where[] = 'm.estado = ?'; $params[] = $fEstado; }
elseif ($fEstado === 'en_curso')   { $where[] = "m.estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')"; }
elseif ($fEstado === 'por_cerrar') { $where[] = "m.estado IN ('listo','entregado','devuelto_stock')"; }
elseif ($fEstado === 'finalizados'){ $where[] = "m.estado IN ('cerrado','cancelado','no_reparable')"; }

$tiposValidos = ['correctivo','preventivo','limpieza','actualizacion'];
if (in_array($fTipo, $tiposValidos, true)) { $where[] = 'm.tipo = ?'; $params[] = $fTipo; }

if ($fProveedor > 0) { $where[] = 'm.proveedor_id = ?'; $params[] = $fProveedor; }

if ($fAtrasados) {
    $where[] = "m.fecha_retorno_estimada IS NOT NULL AND m.fecha_retorno_estimada < CURDATE()
                AND m.estado IN ('enviado_proveedor','cotizado','aprobado','en_reparacion')";
}

if ($fBuscar !== '') {
    $busca = ['m.codigo','e.codigo','e.marca','e.modelo','t.nombre','pr.nombre','u.nombre_completo','tk.codigo','m.diagnostico'];
    $partes = [];
    foreach ($busca as $col) { $partes[] = "$col LIKE ?"; $params[] = '%' . $fBuscar . '%'; }
    $where[] = '(' . implode(' OR ', $partes) . ')';
}

$cond = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT m.codigo, m.tipo, m.estado, m.diagnostico,
               m.fecha_inicio, m.fecha_envio_proveedor, m.fecha_retorno_estimada, m.fecha_fin,
               m.costo_mano_obra, m.costo_repuestos, m.costo_total, m.destino_envio,
               m.resultado_final, m.atraso_min, m.motivo_atraso,
               e.codigo AS equipo_codigo, e.marca AS equipo_marca, e.modelo AS equipo_modelo,
               t.nombre AS equipo_tipo,
               pr.nombre AS proveedor,
               CONCAT(u.nombre_completo, '') AS tecnico,
               tk.codigo AS ticket_codigo
        FROM mantenimientos m
        INNER JOIN equipos e ON e.id = m.equipo_id
        INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
        LEFT JOIN proveedores pr ON pr.id = m.proveedor_id
        LEFT JOIN usuarios u ON u.id = m.tecnico_usuario_id
        LEFT JOIN tickets tk ON tk.id = m.ticket_id" . $cond . "
        ORDER BY m.fecha_inicio DESC
        LIMIT 10000";
$filas = Database::get($sql, $params);

$EST_TXT = ['solicitado'=>'Solicitado','en_evaluacion'=>'En evaluacion','en_proceso'=>'En proceso',
    'enviado_proveedor'=>'Enviado a proveedor','cotizado'=>'Cotizado','aprobado'=>'Aprobado','rechazado'=>'Rechazado',
    'en_reparacion'=>'En reparacion','recibido_reparado'=>'Recibido reparado','listo'=>'Listo','entregado'=>'Entregado',
    'devuelto_stock'=>'Devuelto a stock','cerrado'=>'Cerrado','cancelado'=>'Cancelado','no_reparable'=>'No reparable'];

$formato = ($_GET['formato'] ?? 'csv') === 'excel' ? 'excel' : 'csv';
$fecha   = date('Ymd_His');

$ENCABEZADOS = ['Codigo', 'Equipo', 'Tipo equipo', 'Marca', 'Modelo', 'Tipo mantenimiento', 'Estado',
                'Proveedor', 'Tecnico', 'Ticket', 'Inicio', 'Envio a proveedor', 'Retorno estimado',
                'Fin', 'Costo mano de obra', 'Costo repuestos', 'Costo total', 'Destino de envio',
                'Atraso (min)', 'Motivo de atraso', 'Resultado final', 'Diagnostico'];

$filaCeldas = function (array $m) use ($EST_TXT) {
    return [
        $m['codigo'], $m['equipo_codigo'], $m['equipo_tipo'], $m['equipo_marca'], $m['equipo_modelo'],
        ucfirst($m['tipo']), $EST_TXT[$m['estado']] ?? $m['estado'],
        $m['proveedor'] ?: '', $m['tecnico'] ?: '', $m['ticket_codigo'] ?: '',
        $m['fecha_inicio'], $m['fecha_envio_proveedor'], $m['fecha_retorno_estimada'], $m['fecha_fin'],
        $m['costo_mano_obra'] !== null ? number_format((float)$m['costo_mano_obra'], 2) : '',
        $m['costo_repuestos'] !== null ? number_format((float)$m['costo_repuestos'], 2) : '',
        $m['costo_total'] !== null ? number_format((float)$m['costo_total'], 2) : '',
        $m['destino_envio'] ?: '',
        (int)$m['atraso_min'] > 0 ? $m['atraso_min'] : '',
        $m['motivo_atraso'] ?: '', $m['resultado_final'] ?: '', $m['diagnostico'] ?: '',
    ];
};

// ==================== CSV (UTF-8 BOM + ';' -> Excel espanol) ====================
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mantenimientos_' . $fecha . '.csv"');
    header('Cache-Control: no-store');

    echo "\xEF\xBB\xBF";   // BOM: Excel reconoce UTF-8
    $out = fopen('php://output', 'w');
    fputcsv($out, $ENCABEZADOS, ';');
    foreach ($filas as $m) { fputcsv($out, $filaCeldas($m), ';'); }
    fclose($out);
    exit;
}

// ==================== EXCEL (HTML .xls con formato) ====================
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="mantenimientos_' . $fecha . '.xls"');
header('Cache-Control: no-store');

echo '<html><head><meta charset="utf-8"></head><body>';
echo '<table border="1">';
echo '<tr><th colspan="' . count($ENCABEZADOS) . '" style="background:#1e2732;color:#fff;font-size:14pt;'
   . 'font-family:Arial;padding:8px;">MANTENIMIENTOS — '
   . count($filas) . ' registro(s) — ' . date('d/m/Y H:i') . '</th></tr>';
echo '<tr style="background:#e8ecf1;font-family:Arial;font-size:10pt;">';
foreach ($ENCABEZADOS as $h) { echo '<th>' . htmlspecialchars($h) . '</th>'; }
echo '</tr>';

$colores = ['listo' => '#dcfce7', 'entregado' => '#dcfce7', 'devuelto_stock' => '#dcfce7',
            'cerrado' => '#f1f5f9', 'cancelado' => '#fee2e2', 'no_reparable' => '#fee2e2',
            'enviado_proveedor' => '#fef3c7', 'cotizado' => '#fef3c7', 'en_reparacion' => '#dbeafe'];

foreach ($filas as $m) {
    $bg = $colores[$m['estado']] ?? '#ffffff';
    echo '<tr style="font-family:Arial;font-size:9.5pt;background:' . $bg . '">';
    foreach ($filaCeldas($m) as $c) { echo '<td>' . htmlspecialchars((string)$c) . '</td>'; }
    echo '</tr>';
}
echo '</table></body></html>';
exit;
