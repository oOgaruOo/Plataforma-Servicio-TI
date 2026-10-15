<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Descargar un reporte en CSV o Excel (el mismo rango de fechas que se ve en pantalla)
// GET ?modulo=tickets|mantenimiento|inventario|software|asignaciones|personal|rrhh&desde=&hasta=&formato=csv|excel&csrf_token=
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
$alc = Reportes::alcance();
if (!$alc) Response::forbidden('No tiene permiso para ver reportes.');

$tokenGet = (string)($_GET['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenGet)) {
    Response::errorCsrf();
}

try {
    $modulo = (string)($_GET['modulo'] ?? '');
    if ($modulo === 'resumen' || !in_array($modulo, Reportes::MODULOS, true)) Response::error('Ese reporte no se puede descargar.');
    if (!in_array($modulo, $alc['modulos'], true)) Response::forbidden('Su permiso solo cubre los reportes de su área.');
    $formato = ($_GET['formato'] ?? 'csv') === 'excel' ? 'excel' : 'csv';
    $r = Reportes::rango($_GET['desde'] ?? '', $_GET['hasta'] ?? '');
    $res = Reportes::filas($modulo, $r, $alc['area']);
    if (!$res) Response::error('Ese reporte no se puede descargar.');
    [$titulo, $cab, $datos] = $res;

    Auditoria::registrar('exportar', 'reportes', null, null,
        ['modulo' => $modulo, 'formato' => $formato, 'filas' => count($datos), 'desde' => $r['desde'], 'hasta' => $r['hasta']],
        ['accion_detalle' => 'reporte_exportado', 'submodulo' => 'reportes', 'modulo' => 'reportes']);

    $nombre = 'reporte_' . $modulo . '_' . $r['desde'] . '_' . $r['hasta'];
    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $cab, ';');
        foreach ($datos as $fila) {
            // evita que Excel interprete un texto como formula (los numeros se dejan tal cual)
            $fila = array_map(fn($v) => is_string($v) && $v !== '' && strpos('=+-@', $v[0]) !== false && !is_numeric($v) ? "'" . $v : $v, $fila);
            fputcsv($out, $fila, ';');
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '.xls"');
    header('Cache-Control: no-store');
    echo '<html><head><meta charset="utf-8"></head><body><table border="1">';
    echo '<tr><th colspan="' . count($cab) . '" style="background:#1e2732;color:#fff;font-size:14pt;font-family:Arial;padding:8px;">'
       . htmlspecialchars(mb_strtoupper($titulo)) . ' — del ' . $r['desde'] . ' al ' . $r['hasta'] . ' — ' . count($datos) . ' registro(s) — ' . date('d/m/Y H:i') . '</th></tr>';
    echo '<tr style="background:#e8ecf1;font-family:Arial;font-size:10pt;">';
    foreach ($cab as $h) echo '<th>' . htmlspecialchars((string)$h) . '</th>';
    echo '</tr>';
    foreach ($datos as $fila) {
        echo '<tr style="font-family:Arial;font-size:10pt;">';
        foreach ($fila as $v) {
            $num = is_int($v) || is_float($v);
            echo '<td' . ($num ? '' : ' style="mso-number-format:\'\\@\';"') . '>' . htmlspecialchars((string)$v) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
} catch (Throwable $e) {
    error_log('[SIGTI] reportes/exportar: ' . $e->getMessage());
    Response::error('No se pudo generar el reporte.', null, 500);
}
