<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: descargar reportes (CSV o Excel) para auditoria / gerencia
// GET ?reporte=instalaciones|licencias|cumplimiento|equipos&formato=csv|excel&csrf_token=
//     instalaciones acepta f_estado (instalado|desinstalado|todos) f_software f_categoria f_alerta
// La clave de licencia completa solo sale con software.gestionar.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('software', 'ver');
Destinos::asegurar();
Software::asegurar();

$tokenGet = (string)($_GET['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenGet)) {
    Response::errorCsrf();
}

try {
    $reporte = (string)($_GET['reporte'] ?? 'instalaciones');
    $formato = ($_GET['formato'] ?? 'csv') === 'excel' ? 'excel' : 'csv';
    $verClave = Auth::can('software', 'gestionar');
    $hoy = date('Y-m-d');
    $fecha = date('Ymd_His');
    $SI = fn($v) => (int)$v ? 'Sí' : 'No';
    $clave = function ($c) use ($verClave) {
        $c = (string)$c;
        if ($c === '') return '';
        return $verClave ? $c : '…' . mb_substr($c, -4);
    };
    $AUT = Software::AUTORIZACION;

    if ($reporte === 'instalaciones') {
        $titulo = 'SOFTWARE INSTALADO POR EQUIPO';
        $cab = ['Equipo', 'Tipo', 'Marca / Modelo', 'Asignado a', 'Programa', 'Categoría', 'Fabricante', 'Versión', 'Autorización',
                'Tipo de licencia', 'Clave', 'Vence', 'Fecha de instalación', 'Fecha de retiro', 'Instalado por', 'Estado', 'Alertas'];
        $where = []; $par = [];
        $fEstado = (string)($_GET['f_estado'] ?? 'instalado');
        if ($fEstado === 'desinstalado') $where[] = "es.estado = 'desinstalado'";
        elseif ($fEstado !== 'todos') { $where[] = "es.estado = 'instalado'"; $where[] = "eq.estado <> 'dado_de_baja'"; }
        if ((int)($_GET['f_software'] ?? 0) > 0) { $where[] = 'es.software_id = ?'; $par[] = (int)$_GET['f_software']; }
        if (isset(Software::CATEGORIAS[(string)($_GET['f_categoria'] ?? '')])) { $where[] = 's.categoria = ?'; $par[] = $_GET['f_categoria']; }
        $alerta = (string)($_GET['f_alerta'] ?? '');
        $cNoAut = "s.autorizacion = 'prohibido'";
        $cSin = "(s.requiere_licencia = 1 AND es.licencia_id IS NULL)";
        $cVen = "(l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento < '$hoy')";
        if ($alerta === 'no_autorizado') $where[] = $cNoAut;
        elseif ($alerta === 'sin_licencia') $where[] = $cSin;
        elseif ($alerta === 'licencia_vencida') $where[] = $cVen;
        elseif ($alerta === 'cualquiera') $where[] = "($cNoAut OR $cSin OR $cVen)";
        $cond = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $filas = Database::get(
            "SELECT eq.codigo, t.nombre AS tipo, eq.marca, eq.modelo, " . Destinos::SQL_NOMBRE . " AS asignado_a,
                    s.nombre AS programa, s.categoria, s.fabricante, s.autorizacion, s.requiere_licencia,
                    es.version, es.licencia_id, l.tipo AS lic_tipo, l.clave, l.fecha_vencimiento,
                    es.fecha_instalacion, es.fecha_desinstalacion, es.estado, u.nombre_completo AS instalado_por
             FROM equipo_software es
             INNER JOIN equipos eq ON eq.id = es.equipo_id
             INNER JOIN tipo_equipos t ON t.id = eq.tipo_equipo_id
             INNER JOIN software_catalogo s ON s.id = es.software_id
             LEFT JOIN software_licencias l ON l.id = es.licencia_id
             LEFT JOIN usuarios u ON u.id = es.instalado_por
             LEFT JOIN asignaciones a ON a.id = (SELECT MAX(av.id) FROM asignaciones av WHERE av.equipo_id = eq.id AND av.estado IN ('activa','vencida'))"
             . Destinos::SQL_JOIN . "$cond ORDER BY eq.codigo, s.nombre LIMIT 20000", $par);
        $datos = [];
        foreach ($filas as $f) {
            $al = [];
            if ($f['autorizacion'] === 'prohibido') $al[] = 'No autorizado';
            if ((int)$f['requiere_licencia'] === 1 && !$f['licencia_id']) $al[] = 'Sin licencia';
            if ($f['fecha_vencimiento'] && $f['fecha_vencimiento'] < $hoy) $al[] = 'Licencia vencida';
            $datos[] = [$f['codigo'], $f['tipo'], trim(($f['marca'] ?? '') . ' ' . ($f['modelo'] ?? '')), $f['asignado_a'] ?? '',
                $f['programa'], Software::etiquetaCategoria($f['categoria']), $f['fabricante'] ?? '', $f['version'] ?? '',
                $AUT[$f['autorizacion']] ?? $f['autorizacion'],
                $f['lic_tipo'] ? (Software::TIPOS_LIC[$f['lic_tipo']] ?? $f['lic_tipo']) : ((int)$f['requiere_licencia'] ? '' : 'No requiere'),
                $clave($f['clave']), $f['fecha_vencimiento'] ?? '', $f['fecha_instalacion'] ?? '', $f['fecha_desinstalacion'] ?? '',
                $f['instalado_por'] ?? '', $f['estado'] === 'instalado' ? 'Instalado' : 'Desinstalado', implode(', ', $al)];
        }

    } elseif ($reporte === 'licencias') {
        $titulo = 'LICENCIAS DE SOFTWARE';
        $cab = ['Programa', 'Tipo', 'Clave', 'Puestos', 'En uso', 'Libres', 'Fecha de compra', 'Vencimiento', 'Estado', 'Costo (S/)', 'Proveedor', 'Documento', 'Observaciones'];
        $uso = Software::usoPorLicencia();
        $EST = ['vigente' => 'Vigente', 'por_vencer' => 'Por vencer', 'vencida' => 'Vencida', 'sin_vencimiento' => 'Sin vencimiento', 'baja' => 'Dada de baja'];
        $datos = [];
        foreach (Database::get("SELECT l.*, s.nombre AS programa FROM software_licencias l INNER JOIN software_catalogo s ON s.id = l.software_id ORDER BY s.nombre, l.id") as $l) {
            $u = $uso[(int)$l['id']] ?? 0;
            $est = (int)$l['activo'] === 1 ? Software::estadoLicencia($l['fecha_vencimiento'], $hoy) : 'baja';
            $datos[] = [$l['programa'], Software::TIPOS_LIC[$l['tipo']] ?? $l['tipo'], $clave($l['clave']), (int)$l['puestos'], $u,
                max(0, (int)$l['puestos'] - $u), $l['fecha_compra'] ?? '', $l['fecha_vencimiento'] ?? '', $EST[$est] ?? $est,
                $l['costo'] !== null ? number_format((float)$l['costo'], 2, '.', '') : '', $l['proveedor'] ?? '', $l['documento'] ?? '', $l['observaciones'] ?? ''];
        }

    } elseif ($reporte === 'cumplimiento') {
        $titulo = 'CUMPLIMIENTO DE LICENCIAS POR PROGRAMA';
        $cab = ['Programa', 'Categoría', 'Autorización', 'Requiere licencia', 'Instalaciones', 'Con licencia', 'Sin licencia', 'Con licencia vencida',
                'Puestos vigentes', 'Faltan', 'Sobran', 'Costo licencias (S/)', 'Situación'];
        $SIT = ['ok' => 'En regla', 'deficit' => 'FALTAN LICENCIAS', 'sin_uso' => 'Sin uso', 'libre' => 'No requiere licencia'];
        $datos = [];
        foreach (Software::cumplimiento() as $c) {
            $datos[] = [$c['nombre'], Software::etiquetaCategoria($c['categoria']), $AUT[$c['autorizacion']] ?? $c['autorizacion'], $SI($c['requiere_licencia']),
                $c['instaladas'], $c['con_licencia'], $c['sin_licencia'], $c['con_licencia_vencida'], $c['puestos_vigentes'], $c['deficit'], $c['sobrantes'],
                number_format($c['costo'], 2, '.', ''), $SIT[$c['estado']] ?? $c['estado']];
        }

    } elseif ($reporte === 'equipos') {
        $titulo = 'CONTROL DE SOFTWARE POR EQUIPO';
        $cab = ['Equipo', 'Tipo', 'Marca / Modelo', 'Estado del equipo', 'Asignado a', 'Programas instalados', 'Con alertas', 'Inventario confirmado el'];
        $corte = date('Y-m-d H:i:s', strtotime($hoy . ' -' . Software::DIAS_VERIFICACION . ' days'));
        $datos = [];
        foreach (Database::get(
            "SELECT e.codigo, t.nombre AS tipo, e.marca, e.modelo, e.estado, e.software_verificado_en, " . Destinos::SQL_NOMBRE . " AS asignado_a,
                    (SELECT COUNT(*) FROM equipo_software es WHERE es.equipo_id = e.id AND es.estado = 'instalado') AS n,
                    (SELECT COUNT(*) FROM equipo_software es INNER JOIN software_catalogo s ON s.id = es.software_id
                       LEFT JOIN software_licencias l ON l.id = es.licencia_id
                      WHERE es.equipo_id = e.id AND es.estado = 'instalado'
                        AND (s.autorizacion = 'prohibido' OR (s.requiere_licencia = 1 AND es.licencia_id IS NULL)
                             OR (l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento < '$hoy'))) AS al
             FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN asignaciones a ON a.id = (SELECT MAX(av.id) FROM asignaciones av WHERE av.equipo_id = e.id AND av.estado IN ('activa','vencida'))"
             . Destinos::SQL_JOIN . " WHERE e.estado <> 'dado_de_baja' ORDER BY e.codigo LIMIT 20000") as $e) {
            $datos[] = [$e['codigo'], $e['tipo'], trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? '')), str_replace('_', ' ', $e['estado']),
                $e['asignado_a'] ?? '', (int)$e['n'], (int)$e['al'], $e['software_verificado_en'] ? substr($e['software_verificado_en'], 0, 10) : ((int)$e['n'] ? 'Nunca' : '')];
        }
    } else {
        Response::error('Reporte desconocido.');
    }

    Auditoria::registrar('exportar', 'equipo_software', null, null, ['reporte' => $reporte, 'formato' => $formato, 'filas' => count($datos)],
        ['accion_detalle' => 'software_exportado', 'submodulo' => 'reportes']);

    $nombreArch = 'software_' . $reporte . '_' . $fecha;
    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArch . '.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $cab, ';');
        foreach ($datos as $fila) {
            // evita que Excel interprete una celda como formula
            $fila = array_map(fn($v) => is_string($v) && $v !== '' && strpos('=+-@', $v[0]) !== false ? "'" . $v : $v, $fila);
            fputcsv($out, $fila, ';');
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArch . '.xls"');
    header('Cache-Control: no-store');
    echo '<html><head><meta charset="utf-8"></head><body><table border="1">';
    echo '<tr><th colspan="' . count($cab) . '" style="background:#1e2732;color:#fff;font-size:14pt;font-family:Arial;padding:8px;">'
       . htmlspecialchars($titulo) . ' — ' . count($datos) . ' registro(s) — ' . date('d/m/Y H:i') . '</th></tr>';
    echo '<tr style="background:#e8ecf1;font-family:Arial;font-size:10pt;">';
    foreach ($cab as $h) echo '<th>' . htmlspecialchars($h) . '</th>';
    echo '</tr>';
    foreach ($datos as $fila) {
        echo '<tr style="font-family:Arial;font-size:10pt;">';
        foreach ($fila as $v) echo '<td style="mso-number-format:\'\\@\';">' . htmlspecialchars((string)$v) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;

} catch (Throwable $e) {
    error_log('[SIGTI] software/exportar: ' . $e->getMessage());
    Response::error('No se pudo generar el reporte.', null, 500);
}
