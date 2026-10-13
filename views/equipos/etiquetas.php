<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Impresión de etiquetas (plantillas + diálogo de impresora)
// URL: views/equipos/etiquetas.php?ids=1,2,3[&plantilla=X]
// El diálogo de impresión del navegador detecta TODAS las impresoras
// instaladas (XP-237B, impresoras de oficina, PDF, etc.)
// ============================================================
require_once __DIR__ . '/../../core/guard_vista.php';

 $idsRaw = trim((string)($_GET['ids'] ?? ''));
 $plantillaSel = (int)($_GET['plantilla'] ?? 0);
 $ids = array_values(array_filter(
    array_map('intval', explode(',', $idsRaw)),
    fn($i) => $i > 0
));

 $equipos = $ids ? Database::get(
    "SELECT e.id, e.codigo, e.marca, e.modelo, e.nro_serie, e.activo_fijo,
            e.ubicacion, t.nombre AS tipo
     FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE e.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
     ORDER BY e.codigo",
    $ids
) : [];

 EtiquetasPlantillas::asegurar();
 $plantillas = Database::get(
    "SELECT id, nombre, ancho_mm, alto_mm, elementos
     FROM etiquetas_plantillas WHERE activa = 1 AND (objeto = 'equipo' OR objeto IS NULL) ORDER BY id");

// decodificar elementos de la plantilla seleccionada (o la primera)
 $pElegida = null;
foreach ($plantillas as $p) {
    if ((int)$p['id'] === $plantillaSel) { $pElegida = $p; break; }
}
if (!$pElegida && $plantillas) { $pElegida = $plantillas[0]; }
 $elementos = $pElegida ? (json_decode($pElegida['elementos'], true) ?: []) : [];

 $anchoMm = $pElegida ? (int)$pElegida['ancho_mm'] : 40;
 $altoMm  = $pElegida ? (int)$pElegida['alto_mm'] : 30;

function resolverVars(string $txt, array $eq): string {
    $vars = [
        '{codigo}'         => $eq['codigo'],
        '{codigo_compacto}' => str_replace(' ', '', $eq['codigo']),
        '{serie}'          => $eq['nro_serie'] ?: '—',
        '{tipo}'           => $eq['tipo'],
        '{marca}'          => $eq['marca'] ?: '—',
        '{modelo}'         => $eq['modelo'] ?: '—',
        '{activo_fijo}'    => $eq['activo_fijo'] ?: '—',
        '{ubicacion}'      => $eq['ubicacion'] ?: '—',
        '{area}'           => '—',
        '{piso}'           => '—',
    ];
    return str_replace(array_keys($vars), array_values($vars), $txt);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Imprimir etiquetas</title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<style>
  @page { size: <?= $anchoMm ?>mm <?= $altoMm ?>mm; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { width: <?= $anchoMm ?>mm; background: #fff; }

  .etq {
    width: <?= $anchoMm ?>mm; height: <?= $altoMm ?>mm;
    position: relative;
    overflow: hidden;
    page-break-after: always;
    background: #fff;
  }
  .etq:last-child { page-break-after: auto; }

  .el-texto {
    position: absolute;
    color: #000;
    line-height: 1.1;
    overflow: hidden;
    white-space: nowrap;
  }
  .el-barcode {
    position: absolute;
    display: flex; align-items: center; justify-content: center;
  }
  .el-barcode svg { width: 100%; height: 100%; }
  .el-qr {
    position: absolute;
    display: flex; align-items: center; justify-content: center;
    font-size: 8pt; color: #000;
  }

  /* ===== BARRA DE CONTROL (modo edición) ===== */
  .barra-control {
    position: sticky; top: 0; z-index: 10;
    background: #1e2732; padding: 10px 16px;
    display: flex; gap: 10px; justify-content: center; align-items: center;
    flex-wrap: wrap; border-radius: 10px; margin-bottom: 10px;
    width: auto !important;
  }
  .barra-control button {
    padding: 8px 18px; border: 0; border-radius: 6px;
    cursor: pointer; font-size: 11pt; color: #fff;
  }
  .barra-control select {
    padding: 8px; border-radius: 6px; border: 0;
    font-size: 10pt;
  }
  .barra-control span { color: #cbd5e1; font-size: 10pt; }
  @media print {
    .barra-control { display: none !important; }
  }

  body.modo-edicion {
    background: #e9edf2;
    padding: 15px;
    display: flex; flex-direction: column; align-items: center; gap: 12px;
  }
  body.modo-edicion .etq {
    outline: 2px dashed #94a3b8; outline-offset: 2px;
    box-shadow: 0 2px 10px rgba(0,0,0,.12);
  }
  @media print {
    body.modo-edicion { background: #fff; padding: 0; display: block; }
    body.modo-edicion .etq { outline: 0; box-shadow: none; }
  }
</style>
</head>
<body class="modo-edicion">

<div class="barra-control">
  <select id="sel-plantilla" onchange="cambiarPlantilla()">
    <?php foreach ($plantillas as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)($pElegida['id'] ?? 0) ? 'selected' : '' ?>>
        <?= htmlspecialchars($p['nombre']) ?> (<?= (int)$p['ancho_mm'] ?>×<?= (int)$p['alto_mm'] ?>mm)
      </option>
    <?php endforeach; ?>
  </select>
  <button style="background:#16a34a" onclick="window.print()">
    &#128424; Imprimir (<?= count($equipos) ?>)
  </button>
  <span>El diálogo detecta todas sus impresoras (XP-237B, oficina, PDF)</span>
</div>

<?php foreach ($equipos as $eq): ?>
<div class="etq">
  <?php foreach ($elementos as $el):
      $contenido = resolverVars($el['contenido'] ?? '', $eq);
      $x = (int)($el['x'] ?? 2); $y = (int)($el['y'] ?? 2);
      $w = (int)($el['w'] ?? 36); $h = (int)($el['h'] ?? 5);
      $fs = (int)($el['fontSize'] ?? 8);
      $align = in_array(($el['align'] ?? ''), ['left', 'center', 'right'], true) ? $el['align'] : 'center';
      $negrita = !empty($el['negrita']);
  ?>
    <?php if ($el['tipo'] === 'texto'): ?>
    <div class="el-texto" style="left:<?= $x ?>mm;top:<?= $y ?>mm;width:<?= $w ?>mm;height:<?= $h ?>mm;
         font-size:<?= $fs ?>pt;font-weight:<?= $negrita ? '700' : '400' ?>;text-align:<?= $align ?>;
         width:<?= $w ?>mm">
      <?= htmlspecialchars($contenido) ?>
    </div>
    <?php elseif ($el['tipo'] === 'barcode'): ?>
    <div class="el-barcode" style="left:<?= $x ?>mm;top:<?= $y ?>mm;width:<?= $w ?>mm;height:<?= $h ?>mm">
      <svg class="bc" data-cod="<?= htmlspecialchars(str_replace(' ', '', $contenido)) ?>"></svg>
    </div>
    <?php elseif ($el['tipo'] === 'qr'): ?>
    <div class="el-qr" style="left:<?= $x ?>mm;top:<?= $y ?>mm;width:<?= $w ?>mm;height:<?= $h ?>mm;
         text-align:center;border:1px solid #000">
      QR:<?= htmlspecialchars(mb_substr($contenido, 0, 20)) ?>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (!$equipos): ?>
<div style="text-align:center;color:#666;padding:30px;font-family:Arial">No se seleccionaron equipos.</div>
<?php endif; ?>

<script>
function generar() {
  document.querySelectorAll('svg.bc').forEach(function (svg) {
    var cod = svg.getAttribute('data-cod');
    if (!cod) return;
    try {
      JsBarcode(svg, cod, {
        format: 'CODE128', width: 2, height: 60,
        displayValue: false, margin: 0, flat: true
      });
    } catch (e) { console.error(e); }
  });
}
generar();

function cambiarPlantilla() {
  var id = document.getElementById('sel-plantilla').value;
  var url = new URL(window.location.href);
  url.searchParams.set('plantilla', id);
  window.location.href = url.toString();
}
</script>
</body>
</html>