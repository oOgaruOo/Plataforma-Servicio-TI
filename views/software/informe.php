<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Informe imprimible de control de software (auditoria / gerencia)
// URL: views/software/informe.php   (se abre en otra pestana; Ctrl+P o el boton Imprimir)
// ============================================================
require_once __DIR__ . '/../../core/guard_vista.php';
Auth::requirePermission('software', 'ver');
Destinos::asegurar();
Software::asegurar();

function sw_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sw_cfg(string $k, string $def = ''): string
{
    $v = Database::getValue('SELECT valor FROM configuracion WHERE clave = ?', [$k]);
    return ($v !== null && $v !== false && $v !== '') ? (string)$v : $def;
}
function sw_fecha($f): string { $f = substr((string)$f, 0, 10); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? substr($f, 8, 2) . '/' . substr($f, 5, 2) . '/' . substr($f, 0, 4) : ''; }

$logo = '';
foreach (['png', 'jpg', 'jpeg'] as $ext) {
    $f = STORAGE_PATH . '/branding/logo.' . $ext;
    if (is_file($f)) { $logo = 'data:' . ($ext === 'png' ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string)file_get_contents($f)); break; }
}
$empresa = Empresa::nombre();
$ruc = trim(sw_cfg('empresa_ruc'));
$r = Software::resumen();
$hoy = date('Y-m-d');
$uso = Software::usoPorLicencia();
$AUT = Software::AUTORIZACION;
$persona = Auth::info()['nombre'] ?? '';

$cumpl = array_values(array_filter($r['cumplimiento'], fn($c) => $c['instaladas'] > 0 || $c['licencias'] > 0));
usort($cumpl, fn($a, $b) => (($b['estado'] === 'deficit') <=> ($a['estado'] === 'deficit')) ?: $b['instaladas'] <=> $a['instaladas']);
$lics = Database::get(
    "SELECT l.*, s.nombre AS programa FROM software_licencias l INNER JOIN software_catalogo s ON s.id = l.software_id
     WHERE l.activo = 1 ORDER BY s.nombre, l.fecha_vencimiento");
$sinSw = Database::get(
    "SELECT e.codigo, t.nombre AS tipo, e.marca, e.modelo, " . Destinos::SQL_NOMBRE . " AS asignado_a
     FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     LEFT JOIN asignaciones a ON a.id = (SELECT MAX(av.id) FROM asignaciones av WHERE av.equipo_id = e.id AND av.estado IN ('activa','vencida'))"
     . Destinos::SQL_JOIN . "
     WHERE t.familia = 'computo' AND e.estado <> 'dado_de_baja'
       AND NOT EXISTS (SELECT 1 FROM equipo_software es WHERE es.equipo_id = e.id AND es.estado = 'instalado')
     ORDER BY e.codigo LIMIT 60");
$noAut = Database::get(
    "SELECT eq.codigo, s.nombre AS programa, es.version, " . Destinos::SQL_NOMBRE . " AS asignado_a
     FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id
     INNER JOIN software_catalogo s ON s.id = es.software_id
     LEFT JOIN asignaciones a ON a.id = (SELECT MAX(av.id) FROM asignaciones av WHERE av.equipo_id = eq.id AND av.estado IN ('activa','vencida'))"
     . Destinos::SQL_JOIN . "
     WHERE " . Software::INST_VIG . " AND s.autorizacion = 'prohibido' ORDER BY eq.codigo LIMIT 60");

Auditoria::registrar('exportar', 'equipo_software', null, null, ['reporte' => 'informe_gerencia', 'formato' => 'impresion'],
    ['accion_detalle' => 'software_informe', 'submodulo' => 'reportes']);
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Informe de control de software</title>
<style>
  *{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;color:#1f2937;margin:0;background:#f3f4f6;font-size:11pt}
  .hoja{max-width:210mm;margin:12px auto;background:#fff;padding:16mm 14mm;box-shadow:0 1px 6px rgba(0,0,0,.15)}
  .enc{display:flex;align-items:center;gap:14px;border-bottom:3px solid #1e3a8a;padding-bottom:10px;margin-bottom:14px}
  .enc img{max-height:56px;max-width:140px}
  .enc .emp{font-size:14pt;font-weight:800;color:#1e3a8a;letter-spacing:1px;text-transform:uppercase}
  .enc .sub{font-size:9pt;color:#6b7280}
  h1{font-size:15pt;margin:0 0 2px} h2{font-size:11.5pt;margin:18px 0 6px;color:#1e3a8a;border-bottom:1px solid #d1d5db;padding-bottom:3px}
  table{width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:6px} th,td{border:1px solid #d1d5db;padding:4px 6px;text-align:left;vertical-align:top}
  th{background:#e8ecf1} td.n,th.n{text-align:center}
  .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:8px 0}
  .kpi{border:1px solid #d1d5db;border-radius:6px;padding:8px;text-align:center} .kpi b{display:block;font-size:16pt;color:#1e3a8a} .kpi span{font-size:8.5pt;color:#6b7280}
  .kpi.mal b{color:#b91c1c}
  .ok{color:#15803d;font-weight:700}.mal{color:#b91c1c;font-weight:700}.amb{color:#b45309;font-weight:700}
  .firmas{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:46px;text-align:center;font-size:9pt}
  .firmas div{border-top:1px solid #111;padding-top:4px}
  .nota{font-size:8.5pt;color:#6b7280;margin-top:10px}
  .barra{position:sticky;top:0;background:#1e3a8a;color:#fff;padding:8px 14px;display:flex;justify-content:space-between;align-items:center;font-size:10pt}
  .barra button{background:#fff;color:#1e3a8a;border:0;border-radius:4px;padding:6px 14px;font-weight:700;cursor:pointer}
  @media print{body{background:#fff}.barra{display:none}.hoja{box-shadow:none;margin:0;max-width:none;padding:0}h2{break-after:avoid}tr{break-inside:avoid}}
</style></head><body>
<div class="barra"><span>Informe de control de software · vista previa</span><button onclick="window.print()">Imprimir / guardar como PDF</button></div>
<div class="hoja">
  <div class="enc">
    <?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php endif; ?>
    <div><div class="emp"><?= sw_h($empresa) ?></div><?php if ($ruc): ?><div class="sub">RUC <?= sw_h($ruc) ?></div><?php endif; ?><div class="sub">Área de Sistemas · Tecnologías de la Información</div></div>
  </div>
  <h1>Informe de control de software instalado</h1>
  <div class="sub" style="font-size:9pt;color:#6b7280">Corte al <?= date('d/m/Y H:i') ?> · Preparado por <?= sw_h($persona) ?></div>

  <h2>1. Resumen</h2>
  <div class="kpis">
    <div class="kpi"><b><?= (int)$r['equipos_con'] ?></b><span>Equipos con software registrado</span></div>
    <div class="kpi"><b><?= (int)$r['instalaciones'] ?></b><span>Programas instalados (total)</span></div>
    <div class="kpi"><b><?= (int)$r['cobertura'] ?>%</b><span>Cobertura de equipos de cómputo (<?= (int)($r['equipos_computo'] - $r['equipos_computo_sin']) ?> de <?= (int)$r['equipos_computo'] ?>)</span></div>
    <div class="kpi"><b><?= (int)$r['puestos'] ?></b><span>Puestos de licencia vigentes</span></div>
    <div class="kpi"><b>S/ <?= number_format((float)$r['inversion'], 2) ?></b><span>Inversión registrada en licencias</span></div>
    <div class="kpi <?= $r['programas_deficit'] ? 'mal' : '' ?>"><b><?= (int)$r['programas_deficit'] ?></b><span>Programas con licencias insuficientes</span></div>
    <div class="kpi <?= $r['no_autorizado'] ? 'mal' : '' ?>"><b><?= (int)$r['no_autorizado'] ?></b><span>Instalaciones no autorizadas</span></div>
    <div class="kpi <?= ($r['lic_vencidas'] + $r['lic_por_vencer']) ? 'mal' : '' ?>"><b><?= (int)($r['lic_vencidas'] + $r['lic_por_vencer']) ?></b><span>Licencias vencidas o por vencer (<?= (int)Software::DIAS_ALERTA ?> días)</span></div>
  </div>

  <h2>2. Observaciones</h2>
  <table>
    <tr><th>Observación</th><th class="n" style="width:70px">Cantidad</th></tr>
    <?php
    $obs = [
        ['Instalaciones de programas NO autorizados', $r['no_autorizado']],
        ['Instalaciones que requieren licencia y no la tienen asignada', $r['sin_licencia']],
        ['Instalaciones con la licencia vencida', $r['licencia_vencida']],
        ['Programas con menos puestos vigentes que instalaciones (faltan ' . (int)$r['puestos_faltantes'] . ' licencias)', $r['programas_deficit']],
        ['Licencias ya vencidas', $r['lic_vencidas']],
        ['Licencias que vencen en los próximos ' . (int)Software::DIAS_ALERTA . ' días', $r['lic_por_vencer']],
        ['Equipos de cómputo sin software registrado', $r['equipos_computo_sin']],
        ['Equipos con el software sin confirmar en ' . (int)Software::DIAS_VERIFICACION . ' días', $r['sin_verificar']],
    ];
    foreach ($obs as [$t, $n]): ?>
      <tr><td><?= sw_h($t) ?></td><td class="n <?= (int)$n ? 'mal' : 'ok' ?>"><?= (int)$n ?></td></tr>
    <?php endforeach; ?>
  </table>

  <h2>3. Cumplimiento por programa</h2>
  <table>
    <tr><th>Programa</th><th>Categoría</th><th>Autorización</th><th class="n">Instalados</th><th class="n">Puestos vigentes</th><th class="n">Sin licencia</th><th>Situación</th></tr>
    <?php foreach ($cumpl as $c):
        $sit = ['ok' => ['En regla', 'ok'], 'deficit' => ['Faltan ' . $c['deficit'], 'mal'], 'sin_uso' => ['Sin uso', ''], 'libre' => ['No requiere licencia', '']][$c['estado']]; ?>
      <tr><td><?= sw_h($c['nombre']) ?></td><td><?= sw_h(Software::etiquetaCategoria($c['categoria'])) ?></td><td><?= sw_h($AUT[$c['autorizacion']] ?? '') ?></td>
          <td class="n"><?= (int)$c['instaladas'] ?></td><td class="n"><?= $c['requiere_licencia'] ? (int)$c['puestos_vigentes'] : '—' ?></td>
          <td class="n"><?= $c['requiere_licencia'] ? (int)$c['sin_licencia'] : '—' ?></td><td class="<?= $sit[1] ?>"><?= sw_h($sit[0]) ?></td></tr>
    <?php endforeach; if (!$cumpl): ?><tr><td colspan="7">Todavía no hay software registrado.</td></tr><?php endif; ?>
  </table>

  <h2>4. Licencias vigentes en el inventario</h2>
  <table>
    <tr><th>Programa</th><th>Tipo</th><th class="n">Puestos</th><th class="n">En uso</th><th>Vencimiento</th><th>Proveedor / documento</th></tr>
    <?php foreach ($lics as $l):
        $est = Software::estadoLicencia($l['fecha_vencimiento'], $hoy); ?>
      <tr><td><?= sw_h($l['programa']) ?></td><td><?= sw_h(Software::TIPOS_LIC[$l['tipo']] ?? $l['tipo']) ?></td><td class="n"><?= (int)$l['puestos'] ?></td>
          <td class="n"><?= (int)($uso[(int)$l['id']] ?? 0) ?></td>
          <td class="<?= $est === 'vencida' ? 'mal' : ($est === 'por_vencer' ? 'amb' : '') ?>"><?= $l['fecha_vencimiento'] ? sw_h(sw_fecha($l['fecha_vencimiento'])) . ($est === 'vencida' ? ' (vencida)' : ($est === 'por_vencer' ? ' (por vencer)' : '')) : 'No vence' ?></td>
          <td><?= sw_h(trim(($l['proveedor'] ?? '') . ' ' . ($l['documento'] ? '· ' . $l['documento'] : ''))) ?></td></tr>
    <?php endforeach; if (!$lics): ?><tr><td colspan="6">No hay licencias registradas.</td></tr><?php endif; ?>
  </table>

  <?php if ($noAut): ?>
  <h2>5. Programas no autorizados encontrados</h2>
  <table><tr><th>Equipo</th><th>Programa</th><th>Versión</th><th>Asignado a</th></tr>
    <?php foreach ($noAut as $x): ?><tr><td><?= sw_h($x['codigo']) ?></td><td><?= sw_h($x['programa']) ?></td><td><?= sw_h($x['version']) ?></td><td><?= sw_h($x['asignado_a']) ?></td></tr><?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php if ($sinSw): ?>
  <h2><?= $noAut ? '6' : '5' ?>. Equipos de cómputo sin software registrado<?= count($sinSw) >= 60 ? ' (primeros 60)' : '' ?></h2>
  <table><tr><th>Equipo</th><th>Tipo</th><th>Marca / modelo</th><th>Asignado a</th></tr>
    <?php foreach ($sinSw as $x): ?><tr><td><?= sw_h($x['codigo']) ?></td><td><?= sw_h($x['tipo']) ?></td><td><?= sw_h(trim(($x['marca'] ?? '') . ' ' . ($x['modelo'] ?? ''))) ?></td><td><?= sw_h($x['asignado_a'] ?? '') ?></td></tr><?php endforeach; ?>
  </table>
  <?php endif; ?>

  <div class="firmas"><div>Elaborado por<br><?= sw_h($persona) ?></div><div>Revisado por<br>Jefatura de Sistemas</div><div>V°B° Gerencia</div></div>
  <div class="nota">Este informe resume lo registrado en el sistema SIGTI. Las claves de licencia no se imprimen. Los programas retirados y los cambios de cada equipo quedan en su historial y en el módulo de Auditoría.</div>
</div>
</body></html>
