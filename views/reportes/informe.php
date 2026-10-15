<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Informe imprimible (gerencia / auditoria)
// URL: views/reportes/informe.php?modulo=resumen|tickets|mantenimiento|inventario|software|asignaciones|personal|rrhh&desde=&hasta=
// Se abre en otra pestana; Ctrl+P o el boton para imprimir / guardar como PDF.
// ============================================================
require_once __DIR__ . '/../../core/guard_vista.php';

$alc = Reportes::alcance();
if (!$alc) { http_response_code(403); echo 'No tiene permiso para ver reportes.'; exit; }
$modulo = (string)($_GET['modulo'] ?? 'resumen');
if (!in_array($modulo, $alc['modulos'], true)) { http_response_code(403); echo 'Su permiso solo cubre los reportes de su área.'; exit; }
$r = Reportes::rango($_GET['desde'] ?? '', $_GET['hasta'] ?? '');
$area = $alc['area'];

function rp_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rp_cfg(string $k, string $def = ''): string
{
    $v = Database::getValue('SELECT valor FROM configuracion WHERE clave = ?', [$k]);
    return ($v !== null && $v !== false && $v !== '') ? (string)$v : $def;
}
function rp_f($f): string { $f = substr((string)$f, 0, 10); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? substr($f, 8, 2) . '/' . substr($f, 5, 2) . '/' . substr($f, 0, 4) : (string)$f; }
function rp_s($v): string { return 'S/ ' . number_format((float)$v, 2); }
function rp_mes(string $ym): string
{
    static $M = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
    return ($M[substr($ym, 5, 2)] ?? substr($ym, 5, 2)) . ' ' . substr($ym, 2, 2);
}
/** Tarjetas: [[valor, etiqueta, 'mal'|'ok'|''], ...] */
function rp_kpis(array $k): void
{
    echo '<div class="kpis">';
    foreach ($k as $x) echo '<div class="kpi ' . rp_h($x[2] ?? '') . '"><b>' . rp_h($x[0]) . '</b><span>' . rp_h($x[1]) . '</span></div>';
    echo '</div>';
}
function rp_tabla(array $cab, array $filas, string $vacio = 'Sin datos en el período.'): void
{
    echo '<table><tr>';
    foreach ($cab as $c) echo '<th' . (is_array($c) ? ' class="' . $c[1] . '"' : '') . '>' . rp_h(is_array($c) ? $c[0] : $c) . '</th>';
    echo '</tr>';
    foreach ($filas as $f) {
        echo '<tr>';
        foreach ($f as $i => $v) {
            $cls = is_array($v) ? $v[1] : (is_array($cab[$i] ?? null) && strpos($cab[$i][1], 'n') !== false ? 'n' : '');
            echo '<td' . ($cls ? ' class="' . $cls . '"' : '') . '>' . rp_h(is_array($v) ? $v[0] : $v) . '</td>';
        }
        echo '</tr>';
    }
    if (!$filas) echo '<tr><td colspan="' . count($cab) . '">' . rp_h($vacio) . '</td></tr>';
    echo '</table>';
}
/** Barras horizontales sencillas (se imprimen bien): $items = [[etiqueta, valor], ...] */
function rp_barras(array $items, string $fmt = 'n'): void
{
    $max = 0; foreach ($items as $i) $max = max($max, (float)$i[1]);
    if (!$items || $max <= 0) { echo '<div class="nota">Sin datos en el período.</div>'; return; }
    echo '<div class="bars">';
    foreach ($items as $i) {
        $pct = (int) round(100 * (float)$i[1] / $max);
        echo '<div class="bar"><span class="bl">' . rp_h($i[0]) . '</span><span class="bt"><i style="width:' . $pct . '%"></i></span><span class="bv">'
           . rp_h($fmt === 's' ? rp_s($i[1]) : number_format((float)$i[1], 0)) . '</span></div>';
    }
    echo '</div>';
}
function rp_serie(array $meses, array $vals, string $fmt = 'n'): array
{
    $o = []; foreach ($meses as $i => $m) $o[] = [rp_mes($m), $vals[$i] ?? 0]; return $o;
}
$ETK = ['abierto' => 'Abierto', 'asignado' => 'Asignado', 'en_atencion' => 'En atención', 'pendiente_usuario' => 'Pendiente usuario', 'pendiente_proveedor' => 'Pendiente proveedor', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado', 'cancelado' => 'Cancelado'];
$EMT = ['solicitado' => 'Solicitado', 'en_evaluacion' => 'En evaluación', 'en_proceso' => 'En proceso', 'enviado_proveedor' => 'En proveedor', 'cotizado' => 'Cotizado', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado', 'en_reparacion' => 'En reparación', 'recibido_reparado' => 'Recibido', 'listo' => 'Listo', 'entregado' => 'Entregado', 'devuelto_stock' => 'A stock', 'cerrado' => 'Cerrado', 'cancelado' => 'Cancelado', 'no_reparable' => 'No reparable'];
$EEQ = ['en_stock' => 'En stock', 'asignado' => 'Asignado', 'en_prestamo' => 'En préstamo', 'en_revision' => 'En revisión', 'en_mantenimiento' => 'En mantenimiento', 'en_reparacion_externa' => 'En reparación externa', 'obsoleto' => 'Obsoleto', 'dado_de_baja' => 'De baja'];
$TDEST = ['persona' => 'Personas', 'area' => 'Áreas', 'proyecto' => 'Proyectos', 'obra' => 'Obras'];

$logo = '';
foreach (['png', 'jpg', 'jpeg'] as $ext) {
    $f = STORAGE_PATH . '/branding/logo.' . $ext;
    if (is_file($f)) { $logo = 'data:' . ($ext === 'png' ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string)file_get_contents($f)); break; }
}
$empresa = Empresa::nombre();
$ruc = trim(rp_cfg('empresa_ruc'));
$persona = Auth::info()['nombre'] ?? '';

// ---- datos del modulo ----
switch ($modulo) {
    case 'resumen':       $d = Reportes::resumen($r, $area); break;
    case 'tickets':       $d = Reportes::tickets($r, $area); break;
    case 'mantenimiento': $d = Reportes::mantenimiento($r); break;
    case 'inventario':    $d = Reportes::inventario($r); break;
    case 'software':      $d = Reportes::software($r); break;
    case 'asignaciones':  $d = Reportes::asignaciones($r); break;
    case 'rrhh':          $d = Reportes::rrhh($r, $area); break;
    default:              $d = Reportes::personal($r, $area);
}
Auditoria::registrar('exportar', 'reportes', null, null, ['modulo' => $modulo, 'formato' => 'impresion', 'desde' => $r['desde'], 'hasta' => $r['hasta']],
    ['accion_detalle' => 'reporte_impreso', 'submodulo' => 'reportes', 'modulo' => 'reportes']);
$titulo = Reportes::TITULOS[$modulo] ?? $modulo;
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Informe — <?= rp_h($titulo) ?></title>
<style>
  *{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;color:#1f2937;margin:0;background:#f3f4f6;font-size:11pt}
  .hoja{max-width:210mm;margin:12px auto;background:#fff;padding:16mm 14mm;box-shadow:0 1px 6px rgba(0,0,0,.15)}
  .enc{display:flex;align-items:center;gap:14px;border-bottom:3px solid #1e3a8a;padding-bottom:10px;margin-bottom:14px}
  .enc img{max-height:56px;max-width:140px}
  .enc .emp{font-size:14pt;font-weight:800;color:#1e3a8a;letter-spacing:1px;text-transform:uppercase}
  .sub{font-size:9pt;color:#6b7280}
  h1{font-size:15pt;margin:0 0 2px} h2{font-size:11.5pt;margin:18px 0 6px;color:#1e3a8a;border-bottom:1px solid #d1d5db;padding-bottom:3px}
  table{width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:6px} th,td{border:1px solid #d1d5db;padding:4px 6px;text-align:left;vertical-align:top}
  th{background:#e8ecf1} td.n,th.n{text-align:center} td.r,th.r{text-align:right}
  .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:8px 0}
  .kpi{border:1px solid #d1d5db;border-radius:6px;padding:8px;text-align:center} .kpi b{display:block;font-size:15pt;color:#1e3a8a} .kpi span{font-size:8.5pt;color:#6b7280}
  .kpi.mal b{color:#b91c1c} .kpi.ok b{color:#15803d}
  .ok{color:#15803d;font-weight:700}.mal{color:#b91c1c;font-weight:700}.amb{color:#b45309;font-weight:700}
  .bars{margin:4px 0 8px}.bar{display:flex;align-items:center;gap:8px;font-size:9pt;margin:2px 0}
  .bl{width:34%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bt{flex:1;background:#eef2f7;border-radius:3px;height:11px;display:block}
  .bt i{display:block;height:11px;background:#2563eb;border-radius:3px}.bv{width:80px;text-align:right}
  .dos{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .alertas div{padding:5px 8px;margin:3px 0;border-left:4px solid #d97706;background:#fffbeb;font-size:9.5pt}.alertas div.rojo{border-color:#b91c1c;background:#fef2f2}
  .firmas{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:46px;text-align:center;font-size:9pt}
  .firmas div{border-top:1px solid #111;padding-top:4px}
  .nota{font-size:8.5pt;color:#6b7280;margin-top:10px}
  .barra{position:sticky;top:0;background:#1e3a8a;color:#fff;padding:8px 14px;display:flex;justify-content:space-between;align-items:center;font-size:10pt}
  .barra button{background:#fff;color:#1e3a8a;border:0;border-radius:4px;padding:6px 14px;font-weight:700;cursor:pointer}
  @media print{body{background:#fff}.barra{display:none}.hoja{box-shadow:none;margin:0;max-width:none;padding:0}h2{break-after:avoid}tr{break-inside:avoid}}
</style></head><body>
<div class="barra"><span>Informe · vista previa</span><button onclick="window.print()">Imprimir / guardar como PDF</button></div>
<div class="hoja">
  <div class="enc">
    <?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php endif; ?>
    <div><div class="emp"><?= rp_h($empresa) ?></div><?php if ($ruc): ?><div class="sub">RUC <?= rp_h($ruc) ?></div><?php endif; ?><div class="sub">Área de Sistemas · Tecnologías de la Información</div></div>
  </div>
  <h1>Informe: <?= rp_h($titulo) ?></h1>
  <div class="sub">Período: del <?= rp_f($r['desde']) ?> al <?= rp_f($r['hasta']) ?> (<?= (int)$r['dias'] ?> días) · Emitido el <?= date('d/m/Y H:i') ?> por <?= rp_h($persona) ?><?= $area ? ' · Solo su área' : '' ?></div>

<?php if ($modulo === 'resumen'):
    $t = $d['tickets']['tarjetas']; ?>
  <h2>1. Mesa de ayuda</h2>
  <?php rp_kpis([
      [$t['total'], 'Tickets creados' . ($t['variacion'] !== null ? ' (' . ($t['variacion'] >= 0 ? '+' : '') . $t['variacion'] . '% vs. período anterior)' : '')],
      [$t['sla_pct'] === null ? '—' : $t['sla_pct'] . '%', 'Cumplimiento del SLA', $t['sla_pct'] !== null && $t['sla_pct'] < 80 ? 'mal' : 'ok'],
      [$t['prom_resolucion_h'] === null ? '—' : $t['prom_resolucion_h'] . ' h', 'Tiempo promedio de resolución'],
      [$t['abiertos_ahora'], 'Abiertos ahora (' . $t['sin_asignar'] . ' sin asignar)', $t['sla_vencidos_ahora'] ? 'mal' : ''],
  ]); ?>
  <?php rp_barras(rp_serie($d['meses'], $d['tickets']['creados'])); ?>
<?php if (isset($d['mantenimiento'])): $m = $d['mantenimiento']['tarjetas']; $i = $d['inventario']['tarjetas']; $s = $d['software']['tarjetas']; $a = $d['asignaciones']['tarjetas']; ?>
  <h2>2. Mantenimiento</h2>
  <?php rp_kpis([
      [rp_s($m['costo_total']), 'Costo del período' . ($m['variacion_costo'] !== null ? ' (' . ($m['variacion_costo'] >= 0 ? '+' : '') . $m['variacion_costo'] . '%)' : '')],
      [$m['total'], 'Mantenimientos'], [$m['activos'], 'En curso ahora'], [$m['atrasados'], 'Con retorno atrasado', $m['atrasados'] ? 'mal' : 'ok'],
  ]); ?>
  <?php rp_barras(rp_serie($d['meses'], $d['mantenimiento']['costos']), 's'); ?>
  <h2>3. Inventario de equipos</h2>
  <?php rp_kpis([
      [$i['total'], 'Equipos en inventario'], [rp_s($i['valorizado']), 'Valor del inventario'],
      [$i['asignados'], 'Asignados / en préstamo'], [$i['garantia90'], 'Garantía por vencer (90 días)', $i['garantia90'] ? 'mal' : ''],
  ]); ?>
  <?php rp_barras(array_map(fn($e) => [$EEQ[$e['estado']] ?? $e['estado'], $e['n']], $d['inventario']['estados'])); ?>
  <h2>4. Software y licencias</h2>
  <?php rp_kpis([
      [$s['cobertura'] . '%', 'Cobertura de equipos de cómputo'], [$s['instalaciones'], 'Instalaciones registradas'],
      [$s['no_autorizado'] + $s['sin_licencia'] + $s['licencia_vencida'], 'Instalaciones observadas', ($s['no_autorizado'] + $s['sin_licencia'] + $s['licencia_vencida']) ? 'mal' : 'ok'],
      [$s['lic_por_vencer'] + $s['lic_vencidas'], 'Licencias vencidas o por vencer', ($s['lic_por_vencer'] + $s['lic_vencidas']) ? 'mal' : ''],
  ]); ?>
  <h2>5. Asignaciones y actas</h2>
  <?php rp_kpis([
      [$a['activas'], 'Equipos entregados vigentes'], [$a['entregas'], 'Entregas en el período'],
      [$a['vencidas'], 'Devoluciones vencidas', $a['vencidas'] ? 'mal' : 'ok'], [$a['actas_pendientes'], 'Actas pendientes de firma', $a['actas_pendientes'] ? 'mal' : 'ok'],
  ]); endif; ?>
  <h2><?= isset($d['mantenimiento']) ? '6' : '2' ?>. Personal</h2>
  <?php $p = $d['personal']['tarjetas']; rp_kpis([[$p['activos'], 'Personal activo'], [$p['con_equipos'], 'Con equipos asignados'], [$p['sin_equipo'], 'Activos sin equipo'], [$p['pre_ingreso'], 'Por ingresar']]); ?>
  <h2>Observaciones para atender</h2>
  <div class="alertas">
    <?php foreach ($d['alertas'] as $al): ?><div class="<?= rp_h($al['nivel']) ?>"><?= rp_h($al['texto']) ?></div><?php endforeach;
    if (!$d['alertas']): ?><div style="border-color:#15803d;background:#f0fdf4">Sin observaciones pendientes. Todo en regla.</div><?php endif; ?>
  </div>

<?php elseif ($modulo === 'tickets'): $t = $d['tarjetas']; ?>
  <h2>1. Indicadores</h2>
  <?php rp_kpis([
      [$t['total'], 'Tickets creados' . ($t['variacion'] !== null ? ' (' . ($t['variacion'] >= 0 ? '+' : '') . $t['variacion'] . '% vs. anterior)' : '')],
      [$t['resueltos'], 'Resueltos en el período'],
      [$t['sla_pct'] === null ? '—' : $t['sla_pct'] . '%', 'Cumplimiento del SLA', $t['sla_pct'] !== null && $t['sla_pct'] < 80 ? 'mal' : 'ok'],
      [$t['prom_resolucion_h'] === null ? '—' : $t['prom_resolucion_h'] . ' h', 'Resolución promedio'],
      [$t['prom_respuesta_h'] === null ? '—' : $t['prom_respuesta_h'] . ' h', 'Primera respuesta promedio'],
      [$t['abiertos_ahora'], 'Abiertos ahora'], [$t['sla_vencidos_ahora'], 'SLA vencidos ahora', $t['sla_vencidos_ahora'] ? 'mal' : 'ok'], [$t['reabiertos'], 'Reabiertos'],
  ]); ?>
  <h2>2. Tickets por mes</h2><?php rp_barras(rp_serie($d['meses'], $d['creados'])); ?>
  <div class="dos">
    <div><h2>3. Por estado</h2><?php rp_barras(array_map(fn($e) => [$ETK[$e['estado']] ?? $e['estado'], $e['n']], $d['estados'])); ?></div>
    <div><h2>4. Por prioridad</h2><?php rp_barras(array_map(fn($e) => [$e['nombre'], $e['n']], $d['prioridades'])); ?></div>
  </div>
  <h2>5. Categorías más frecuentes</h2><?php rp_barras(array_map(fn($e) => [$e['nombre'], $e['n']], $d['categorias'])); ?>
  <h2>6. Atención por técnico</h2>
  <?php rp_tabla(['Técnico', ['Asignados', 'n'], ['Resueltos', 'n']], array_map(fn($x) => [$x['tecnico'], $x['asignados'], $x['resueltos']], $d['tecnicos'])); ?>
  <h2>7. Áreas con más tickets</h2><?php rp_barras(array_map(fn($e) => [$e['nombre'], $e['n']], $d['areas'])); ?>

<?php elseif ($modulo === 'mantenimiento'): $m = $d['tarjetas']; ?>
  <h2>1. Indicadores</h2>
  <?php rp_kpis([
      [rp_s($m['costo_total']), 'Costo total' . ($m['variacion_costo'] !== null ? ' (' . ($m['variacion_costo'] >= 0 ? '+' : '') . $m['variacion_costo'] . '% vs. anterior)' : '')],
      [$m['total'], 'Mantenimientos'], [rp_s($m['costo_promedio']), 'Costo promedio'], [$m['no_reparables'], 'No reparables'],
      [$m['activos'], 'En curso ahora'], [$m['atrasados'], 'Retorno atrasado', $m['atrasados'] ? 'mal' : 'ok'], [$m['preventivos_vencidos'], 'Preventivos vencidos', $m['preventivos_vencidos'] ? 'mal' : 'ok'],
  ]); ?>
  <h2>2. Costo por mes</h2><?php rp_barras(rp_serie($d['meses'], $d['costos']), 's'); ?>
  <div class="dos">
    <div><h2>3. Por estado</h2><?php rp_barras(array_map(fn($e) => [$EMT[$e['estado']] ?? $e['estado'], $e['n']], $d['estados'])); ?></div>
    <div><h2>4. Costo por tipo de equipo</h2><?php rp_barras(array_map(fn($e) => [$e['nombre'], $e['costo']], $d['por_tipo_equipo']), 's'); ?></div>
  </div>
  <h2>5. Equipos con más mantenimientos</h2>
  <?php rp_tabla(['Equipo', 'Descripción', ['Veces', 'n'], ['Costo', 'r']], array_map(fn($x) => [$x['codigo'], trim($x['equipo']), $x['n'], rp_s($x['costo'])], $d['top_equipos'])); ?>
  <h2>6. Proveedores</h2>
  <?php rp_tabla(['Proveedor', ['Servicios', 'n'], ['Costo', 'r']], array_map(fn($x) => [$x['nombre'], $x['n'], rp_s($x['costo'])], $d['proveedores'])); ?>

<?php elseif ($modulo === 'inventario'): $i = $d['tarjetas']; ?>
  <h2>1. Indicadores</h2>
  <?php rp_kpis([
      [$i['total'], 'Equipos en inventario'], [rp_s($i['valorizado']), 'Valor del inventario'], [$i['stock'], 'En stock'], [$i['asignados'], 'Asignados / en préstamo'],
      [$i['en_mantenimiento'], 'En mantenimiento o revisión'], [$i['obsoletos'], 'Obsoletos'], [$i['garantia90'], 'Garantía por vencer (90 días)', $i['garantia90'] ? 'mal' : ''], [$i['garantia_vencida'], 'Garantía ya vencida'],
  ]); ?>
  <div class="dos">
    <div><h2>2. Por estado</h2><?php rp_barras(array_map(fn($e) => [$EEQ[$e['estado']] ?? $e['estado'], $e['n']], $d['estados'])); ?></div>
    <div><h2>3. Antigüedad</h2><?php rp_barras(array_map(fn($e) => [$e['rango'], $e['n']], $d['edades'])); ?></div>
  </div>
  <h2>4. Por tipo de equipo</h2>
  <?php rp_tabla(['Tipo', ['Cantidad', 'n'], ['Valor', 'r']], array_map(fn($x) => [$x['nombre'], $x['n'], rp_s($x['valor'])], $d['por_tipo'])); ?>
  <h2>5. Verificación de ubicación (equipos de cómputo)</h2>
  <?php rp_kpis([[$d['seguimiento']['verde'], 'Al día (30 días)', 'ok'], [$d['seguimiento']['ambar'], 'De 30 a 90 días'], [$d['seguimiento']['rojo'], 'Más de 90 días o nunca', $d['seguimiento']['rojo'] ? 'mal' : 'ok']]); ?>
  <?php if ($d['por_proyecto']): ?><h2>6. Equipos en proyectos y obras</h2>
  <?php rp_tabla(['Código', 'Proyecto / obra', 'Tipo', ['Equipos', 'n'], ['Valor', 'r']], array_map(fn($x) => [$x['codigo'], $x['nombre'], $x['tipo'] === 'obra' ? 'Obra' : 'Proyecto', $x['n'], rp_s($x['valor'])], $d['por_proyecto'])); endif; ?>

<?php elseif ($modulo === 'software'): $s = $d['tarjetas']; ?>
  <h2>1. Indicadores</h2>
  <?php rp_kpis([
      [$s['cobertura'] . '%', 'Cobertura de equipos de cómputo'], [$s['instalaciones'], 'Instalaciones registradas'], [$s['puestos'], 'Puestos de licencia vigentes'], [rp_s($s['inversion']), 'Inversión en licencias'],
      [$s['no_autorizado'], 'No autorizadas', $s['no_autorizado'] ? 'mal' : 'ok'], [$s['sin_licencia'], 'Sin licencia', $s['sin_licencia'] ? 'mal' : 'ok'],
      [$s['licencia_vencida'], 'Con licencia vencida', $s['licencia_vencida'] ? 'mal' : 'ok'], [$s['lic_por_vencer'], 'Licencias por vencer', $s['lic_por_vencer'] ? 'mal' : ''],
  ]); ?>
  <h2>2. Cumplimiento por programa</h2>
  <?php rp_tabla(['Programa', ['Instalados', 'n'], ['Puestos', 'n'], ['Situación', '']],
      array_map(fn($c) => [$c['nombre'], $c['instaladas'], $c['requiere_licencia'] ? $c['puestos_vigentes'] : '—',
          [['ok' => 'En regla', 'deficit' => 'Faltan ' . $c['deficit'], 'sin_uso' => 'Sin uso', 'libre' => 'No requiere licencia'][$c['estado']], $c['estado'] === 'deficit' ? 'mal' : ($c['estado'] === 'ok' ? 'ok' : '')]],
          array_values(array_filter($d['cumplimiento'], fn($c) => $c['instaladas'] > 0 || $c['licencias'] > 0)))); ?>
  <h2>3. Licencias</h2>
  <?php rp_tabla(['Programa', 'Tipo', ['Puestos', 'n'], 'Vencimiento'],
      array_map(fn($l) => [$l['software'], Software::TIPOS_LIC[$l['tipo']] ?? $l['tipo'], $l['puestos'],
          [$l['fecha_vencimiento'] ? rp_f($l['fecha_vencimiento']) . ($l['estado'] === 'vencida' ? ' (vencida)' : ($l['estado'] === 'por_vencer' ? ' (por vencer)' : '')) : 'No vence',
           $l['estado'] === 'vencida' ? 'mal' : ($l['estado'] === 'por_vencer' ? 'amb' : '')]], $d['licencias']), 'No hay licencias registradas.'); ?>

<?php elseif ($modulo === 'asignaciones'): $a = $d['tarjetas']; ?>
  <h2>1. Indicadores</h2>
  <?php rp_kpis([
      [$a['activas'], 'Equipos entregados vigentes'], [$a['prestamos'], 'En préstamo'], [$a['entregas'], 'Entregas del período'], [$a['devoluciones'], 'Devoluciones del período'],
      [$a['vencidas'], 'Devoluciones vencidas', $a['vencidas'] ? 'mal' : 'ok'], [$a['actas'], 'Actas del período'],
      [$a['actas_pendientes'], 'Actas pendientes de firma', $a['actas_pendientes'] ? 'mal' : 'ok'], [$a['solicitudes_pendientes'], 'Solicitudes de equipo abiertas'],
  ]); ?>
  <h2>2. Entregas por mes</h2><?php rp_barras(rp_serie($d['meses'], $d['entregas'])); ?>
  <div class="dos">
    <div><h2>3. Destino de los equipos</h2><?php rp_barras(array_map(fn($e) => [$TDEST[$e['tipo']] ?? $e['tipo'], $e['n']], $d['por_destino'])); ?></div>
    <div><h2>4. Actas por tipo</h2><?php rp_barras(array_map(fn($e) => [$e['tipo'], $e['n']], $d['actas_tipo'])); ?></div>
  </div>
  <h2>5. Devoluciones vencidas</h2>
  <?php rp_tabla(['Equipo', 'Destino', 'Debía devolverse', ['Atraso', 'n']], array_map(fn($x) => [$x['equipo'] . ' ' . trim($x['equipo_desc']), $x['destino'], rp_f($x['fecha_devolucion_esperada']), [$x['dias_atraso'] . ' días', 'n mal']], $d['prestamos_vencidos']), 'No hay devoluciones vencidas.'); ?>
  <h2>6. Actas pendientes de firma</h2>
  <?php rp_tabla(['Acta', 'Tipo', 'Destino', 'Fecha', ['Días', 'n']], array_map(fn($x) => [$x['codigo'], $x['tipo'], $x['destino'], rp_f($x['fecha']), $x['dias']], $d['actas_pendientes']), 'No hay actas pendientes.'); ?>

<?php elseif ($modulo === 'rrhh'): $q = $d['tarjetas']; ?>
  <h2>1. Indicadores</h2>
  <?php rp_kpis([
      [$q['en_curso'], 'Vacantes pendientes', $q['atrasados'] ? 'mal' : ''], [$q['pendientes'], 'Pendientes de tomar'], [$q['en_proceso'], 'En proceso'],
      [$q['cubiertos'], 'Cubiertas', 'ok'], [$q['atrasados'], 'Atrasadas (pasó la fecha límite)', $q['atrasados'] ? 'mal' : 'ok'],
      [$q['promedio_dias_cobertura'] === null ? '—' : $q['promedio_dias_cobertura'] . ' días', 'Tiempo promedio de cobertura'],
  ]); ?>
  <div class="dos">
    <div><h2>2. Solicitadas por mes</h2><?php rp_barras(rp_serie($d['meses'], $d['creados'])); ?></div>
    <div><h2>3. Cubiertas por mes</h2><?php rp_barras(rp_serie($d['meses'], $d['cubiertos'])); ?></div>
  </div>
  <h2>4. Por estado</h2><?php rp_barras(array_map(fn($e) => [Requerimientos::ESTADOS[$e['estado']][0] ?? $e['estado'], $e['n']], $d['por_estado'])); ?>
  <h2>5. Por área</h2><?php rp_barras(array_map(fn($e) => [$e['nombre'], $e['n']], $d['por_area'])); ?>

<?php else: $p = $d['tarjetas']; ?>
  <h2>1. Indicadores</h2>
  <?php rp_kpis([[$p['activos'], 'Personal activo'], [$p['pre_ingreso'], 'Por ingresar'], [$p['cesados'], 'Cesados'], [$p['con_equipos'], 'Con equipos asignados'], [$p['sin_equipo'], 'Activos sin equipo']]); ?>
  <div class="dos">
    <div><h2>2. Ingresos por mes</h2><?php rp_barras(rp_serie($d['meses'], $d['altas'])); ?></div>
    <div><h2>3. Ceses por mes</h2><?php rp_barras(rp_serie($d['meses'], $d['ceses'])); ?></div>
  </div>
  <h2>4. Personal por área</h2><?php rp_barras(array_map(fn($e) => [$e['nombre'], $e['n']], $d['por_area'])); ?>
<?php endif; ?>

  <div class="firmas"><div>Elaborado por<br><?= rp_h($persona) ?></div><div>Revisado por<br>Jefatura de Sistemas</div><div>V°B° Gerencia</div></div>
  <div class="nota">Informe generado con los datos registrados en SIGTI al <?= date('d/m/Y H:i') ?>. Las cifras de "ahora" corresponden al momento de la emisión; las demás, al período indicado.</div>
</div>
</body></html>
