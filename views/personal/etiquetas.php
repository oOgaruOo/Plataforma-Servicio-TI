<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Impresion de etiquetas del PERSONAL y de sus LOCKERS
// URL: views/personal/etiquetas.php?ids=1,2,3            (personas elegidas)
//      views/personal/etiquetas.php?area=2[&activos=0]   (todo el personal de un area)
//      &objeto=personal|locker   &plantilla=ID
// Las plantillas se disenan en Inventario > Diseñador de etiquetas.
// Locker: solo sale quien tiene un locker asignado.
// ============================================================
require_once __DIR__ . '/../../core/guard_vista.php';

if (!Auth::can('personal', 'ver')) {
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:Arial;padding:30px;color:#666">No tiene permisos para imprimir etiquetas del personal.</body>';
    exit;
}

Apellidos::asegurar();
CodigoUsuario::asegurar();
EtiquetasPlantillas::sembrar();

 $objeto = (($_GET['objeto'] ?? '') === 'locker') ? 'locker' : 'personal';
 $plantillaSel = (int)($_GET['plantilla'] ?? 0);
 $idsRaw = trim((string)($_GET['ids'] ?? ''));
 $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw)), fn($i) => $i > 0));
 $areaId = (int)($_GET['area'] ?? 0);
 $soloActivos = (($_GET['activos'] ?? '1') !== '0');
const ETQ_MAX = 300;

 $where = []; $params = [];
if ($ids) {
    $ids = array_slice($ids, 0, ETQ_MAX);
    $where[] = 'p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $params = $ids;
} else {
    if ($areaId > 0) { $where[] = 'p.area_id = ?'; $params[] = $areaId; }
    if ($soloActivos) $where[] = "p.estado IN ('pre_ingreso','activo','cese_programado','en_proceso_cese')";
}
 $personas = Database::get(
    "SELECT p.id, p.codigo_usuario, p.nombres, p.apellidos, p.apellido_paterno, p.apellido_materno,
            p.dni, p.cargo, p.locker, p.tipo_personal, p.estado,
            a.nombre AS area, a.codigo AS area_codigo
     FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY a.nombre, p.apellidos, p.nombres LIMIT ' . ETQ_MAX,
    $params);

 $total = count($personas);
 $sinLocker = 0;
if ($objeto === 'locker') {
    $conLocker = array_values(array_filter($personas, fn($p) => trim((string)$p['locker']) !== ''));
    $sinLocker = $total - count($conLocker);
    $personas = $conLocker;
}

 $plantillas = Database::get(
    "SELECT id, nombre, ancho_mm, alto_mm, elementos
     FROM etiquetas_plantillas WHERE activa = 1 AND objeto = ? ORDER BY id", [$objeto]);
 $pElegida = null;
foreach ($plantillas as $p) {
    if ((int)$p['id'] === $plantillaSel) { $pElegida = $p; break; }
}
if (!$pElegida && $plantillas) { $pElegida = $plantillas[0]; }
 $elementos = $pElegida ? (json_decode($pElegida['elementos'], true) ?: []) : [];
 $anchoMm = $pElegida ? (int)$pElegida['ancho_mm'] : 60;
 $altoMm  = $pElegida ? (int)$pElegida['alto_mm'] : 30;

function etq_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Imprimir etiquetas — <?= $objeto === 'locker' ? 'Lockers' : 'Personal' ?></title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<style>
  @page { size: <?= $anchoMm ?>mm <?= $altoMm ?>mm; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { width: <?= $anchoMm ?>mm; background: #fff; font-family: Arial, sans-serif; }
  .etq { width: <?= $anchoMm ?>mm; height: <?= $altoMm ?>mm; position: relative; overflow: hidden; page-break-after: always; background: #fff; }
  .etq:last-child { page-break-after: auto; }
  .el-texto { position: absolute; color: #000; line-height: 1.1; overflow: hidden; white-space: nowrap; }
  .el-barcode { position: absolute; display: flex; align-items: center; justify-content: center; }
  .el-barcode svg { width: 100%; height: 100%; }
  .el-qr { position: absolute; display: flex; align-items: center; justify-content: center; font-size: 8pt; color: #000; text-align: center; border: 1px solid #000; }

  .barra-control { position: sticky; top: 0; z-index: 10; background: #1e2732; padding: 10px 16px; display: flex; gap: 10px;
    justify-content: center; align-items: center; flex-wrap: wrap; border-radius: 10px; margin-bottom: 10px; width: auto !important; }
  .barra-control button { padding: 8px 18px; border: 0; border-radius: 6px; cursor: pointer; font-size: 11pt; color: #fff; }
  .barra-control select { padding: 8px; border-radius: 6px; border: 0; font-size: 10pt; }
  .barra-control span { color: #cbd5e1; font-size: 10pt; }
  .aviso { background: #fff7e0; border: 1px solid #f1c40f; color: #6b5200; padding: 8px 14px; border-radius: 8px; font-size: 10pt; max-width: 560px; text-align: center; }
  @media print { .barra-control, .aviso { display: none !important; } }

  body.modo-edicion { background: #e9edf2; padding: 15px; display: flex; flex-direction: column; align-items: center; gap: 12px; }
  body.modo-edicion .etq { outline: 2px dashed #94a3b8; outline-offset: 2px; box-shadow: 0 2px 10px rgba(0,0,0,.12); }
  @media print { body.modo-edicion { background: #fff; padding: 0; display: block; } body.modo-edicion .etq { outline: 0; box-shadow: none; } }
</style>
</head>
<body class="modo-edicion">

<div class="barra-control">
  <select id="sel-objeto" onchange="recargar('objeto', this.value)">
    <option value="personal" <?= $objeto === 'personal' ? 'selected' : '' ?>>Etiquetas del personal</option>
    <option value="locker" <?= $objeto === 'locker' ? 'selected' : '' ?>>Etiquetas de lockers</option>
  </select>
  <select id="sel-plantilla" onchange="recargar('plantilla', this.value)">
    <?php foreach ($plantillas as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)($pElegida['id'] ?? 0) ? 'selected' : '' ?>>
        <?= etq_h($p['nombre']) ?> (<?= (int)$p['ancho_mm'] ?>×<?= (int)$p['alto_mm'] ?>mm)
      </option>
    <?php endforeach; ?>
  </select>
  <button style="background:#16a34a" onclick="window.print()">&#128424; Imprimir (<?= count($personas) ?>)</button>
  <span>El diálogo detecta todas sus impresoras (XP-237B, oficina, PDF)</span>
</div>

<?php if (!$plantillas): ?>
  <div class="aviso">No hay plantillas de «<?= $objeto === 'locker' ? 'Lockers' : 'Personal' ?>». Créelas en Inventario › Diseñador de etiquetas.</div>
<?php endif; ?>
<?php if ($objeto === 'locker' && $sinLocker > 0): ?>
  <div class="aviso"><?= $sinLocker ?> persona(s) no tienen locker asignado y no salen. El locker se registra al editar la persona.</div>
<?php endif; ?>
<?php if ($total >= ETQ_MAX): ?>
  <div class="aviso">Se muestran las primeras <?= ETQ_MAX ?> etiquetas. Filtre por área para imprimir el resto.</div>
<?php endif; ?>

<?php foreach ($personas as $per):
    $vars = EtiquetasPlantillas::valoresPersonal($per); ?>
<div class="etq">
  <?php foreach ($elementos as $el):
      $contenido = str_replace(array_keys($vars), array_values($vars), (string)($el['contenido'] ?? ''));
      $x = (int)($el['x'] ?? 2); $y = (int)($el['y'] ?? 2);
      $w = (int)($el['w'] ?? 36); $h = (int)($el['h'] ?? 5);
      $fs = (int)($el['fontSize'] ?? 8);
      $align = in_array($el['align'] ?? 'center', ['left', 'center', 'right'], true) ? $el['align'] : 'center';
      $negrita = !empty($el['negrita']);
      $tipoEl = $el['tipo'] ?? 'texto';
  ?>
    <?php if ($tipoEl === 'texto'): ?>
    <div class="el-texto" style="left:<?= $x ?>mm;top:<?= $y ?>mm;width:<?= $w ?>mm;height:<?= $h ?>mm;font-size:<?= $fs ?>pt;font-weight:<?= $negrita ? '700' : '400' ?>;text-align:<?= $align ?>"><?= etq_h($contenido) ?></div>
    <?php elseif ($tipoEl === 'barcode'): ?>
    <div class="el-barcode" style="left:<?= $x ?>mm;top:<?= $y ?>mm;width:<?= $w ?>mm;height:<?= $h ?>mm"><svg class="bc" data-cod="<?= etq_h(str_replace(' ', '', $contenido)) ?>"></svg></div>
    <?php elseif ($tipoEl === 'qr'): ?>
    <div class="el-qr" style="left:<?= $x ?>mm;top:<?= $y ?>mm;width:<?= $w ?>mm;height:<?= $h ?>mm">QR:<?= etq_h(mb_substr($contenido, 0, 20)) ?></div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (!$personas): ?>
<div style="text-align:center;color:#666;padding:30px">No hay etiquetas para imprimir con esta selección.</div>
<?php endif; ?>

<script>
document.querySelectorAll('svg.bc').forEach(function (svg) {
  var cod = svg.getAttribute('data-cod');
  if (!cod || cod === '—') return;
  try { JsBarcode(svg, cod, { format: 'CODE128', width: 2, height: 60, displayValue: false, margin: 0, flat: true }); }
  catch (e) { console.error(e); }
});
function recargar(clave, valor) {
  var url = new URL(window.location.href);
  url.searchParams.set(clave, valor);
  if (clave === 'objeto') url.searchParams.delete('plantilla');
  window.location.href = url.toString();
}
</script>
</body>
</html>
