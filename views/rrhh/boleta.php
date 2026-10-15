<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Planillas: boleta imprimible (referencial, SIN validez tributaria/legal)
// URL: views/rrhh/boleta.php?id=NN   (se abre en otra pestana; Ctrl+P o el boton Imprimir)
// ============================================================
require_once __DIR__ . '/../../core/guard_vista.php';
Auth::requirePermission('rrhh', 'ver');
Planillas::asegurar();

function pla_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pla_money($n): string { return 'S/ ' . number_format((float)$n, 2); }

$id = (int)($_GET['id'] ?? 0);
$p = Planillas::detalle($id);
if (!$p) { http_response_code(404); echo '<p>La planilla no existe.</p>'; exit; }

$logo = '';
foreach (['png', 'jpg', 'jpeg'] as $ext) {
    $f = STORAGE_PATH . '/branding/logo.' . $ext;
    if (is_file($f)) { $logo = 'data:' . ($ext === 'png' ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string)file_get_contents($f)); break; }
}
$empresa = Empresa::nombre();
[$anio, $mes] = array_pad(explode('-', $p['periodo']), 2, '');
$MESES = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$nombreMes = $MESES[(int)$mes] ?? $mes;

Auditoria::registrar('exportar', 'rrhh_planillas', $id, null, ['formato' => 'impresion'], ['modulo' => 'rrhh', 'submodulo' => 'planillas', 'accion_detalle' => 'boleta']);
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Boleta — <?= pla_h($p['nombres'] . ' ' . $p['apellidos']) ?></title>
<style>
  *{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;color:#1f2937;margin:0;background:#f3f4f6;font-size:11pt}
  .hoja{max-width:180mm;margin:12px auto;background:#fff;padding:14mm 12mm;box-shadow:0 1px 6px rgba(0,0,0,.15)}
  .enc{display:flex;align-items:center;gap:14px;border-bottom:3px solid #1e3a8a;padding-bottom:10px;margin-bottom:14px}
  .enc img{max-height:50px;max-width:130px}
  .enc .emp{font-size:13pt;font-weight:800;color:#1e3a8a;letter-spacing:1px;text-transform:uppercase}
  .enc .sub{font-size:9pt;color:#6b7280}
  h1{font-size:13pt;margin:0 0 10px}
  .datos{display:grid;grid-template-columns:1fr 1fr;gap:4px 18px;font-size:9.5pt;margin-bottom:14px}
  .datos b{color:#374151}
  table{width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:10px} th,td{border:1px solid #d1d5db;padding:4px 8px;text-align:left}
  th{background:#e8ecf1} td.n,th.n{text-align:right}
  tfoot td{font-weight:700;background:#f8fafc}
  .neto{margin-top:10px;text-align:right;font-size:12pt}
  .neto b{color:#1e3a8a}
  .nota{font-size:8pt;color:#6b7280;margin-top:16px}
  .firma{margin-top:40px;text-align:center;font-size:9pt;width:60mm}
  .firma div{border-top:1px solid #111;padding-top:4px}
  .barra{position:sticky;top:0;background:#1e3a8a;color:#fff;padding:8px 14px;display:flex;justify-content:space-between;align-items:center;font-size:10pt}
  .barra button{background:#fff;color:#1e3a8a;border:0;border-radius:4px;padding:6px 14px;font-weight:700;cursor:pointer}
  @media print{body{background:#fff}.barra{display:none}.hoja{box-shadow:none;margin:0;max-width:none;padding:0}}
</style></head><body>
<div class="barra"><span>Boleta referencial · vista previa</span><button onclick="window.print()">Imprimir / guardar como PDF</button></div>
<div class="hoja">
  <div class="enc">
    <?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php endif; ?>
    <div><div class="emp"><?= pla_h($empresa) ?></div><div class="sub">Boleta de pago referencial — Uso interno</div></div>
  </div>
  <h1>Boleta de pago — <?= pla_h($nombreMes . ' ' . $anio) ?></h1>
  <div class="datos">
    <div><b>Trabajador:</b> <?= pla_h($p['nombres'] . ' ' . $p['apellidos']) ?></div>
    <div><b>DNI:</b> <?= pla_h($p['dni']) ?></div>
    <div><b>Cargo:</b> <?= pla_h($p['cargo'] ?? '—') ?></div>
    <div><b>Área:</b> <?= pla_h($p['area'] ?? '—') ?></div>
    <div><b>Estado:</b> <?= $p['estado'] === 'cerrada' ? 'Cerrada' : 'Borrador' ?></div>
  </div>
  <table>
    <thead><tr><th>Concepto</th><th class="n">Monto</th></tr></thead>
    <tbody>
      <tr><td>Sueldo base</td><td class="n"><?= pla_money($p['sueldo_base']) ?></td></tr>
      <?php foreach ($p['detalle'] as $d): ?>
      <tr><td><?= pla_h($d['nombre']) ?> <span style="color:#6b7280">(<?= $d['tipo'] === 'ingreso' ? 'ingreso' : 'descuento' ?>)</span></td>
          <td class="n"><?= $d['tipo'] === 'descuento' ? '-' : '' ?><?= pla_money($d['monto']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td>Total ingresos</td><td class="n"><?= pla_money($p['sueldo_base'] + $p['total_ingresos']) ?></td></tr>
      <tr><td>Total descuentos</td><td class="n">-<?= pla_money($p['total_descuentos']) ?></td></tr>
    </tfoot>
  </table>
  <div class="neto">Neto a pagar: <b><?= pla_money($p['neto']) ?></b></div>
  <div class="firma"><div>Firma del trabajador</div></div>
  <div class="nota">Documento referencial de gestión interna, generado por SIGTI. No constituye un comprobante de pago ni una boleta electrónica con validez tributaria o legal.</div>
</div>
</body></html>
