<?php
// ============================================================
// SIGTI - Hoja de etiquetas con CODIGO DE BARRAS (CODE128)
// URL: views/equipos/etiquetas.php?ids=1,2,3
// A4 vertical - grid 2x10 (20 etiquetas/hoja) de 90x29mm
// ============================================================
require_once __DIR__ . '/../../core/guard_vista.php';

 $idsRaw = trim((string)($_GET['ids'] ?? ''));
 $ids = array_values(array_filter(
    array_map('intval', explode(',', $idsRaw)),
    fn($i) => $i > 0
));

 $equipos = $ids ? Database::get(
    "SELECT e.id, e.codigo, e.marca, e.modelo, e.nro_serie, e.imei,
            e.activo_fijo, t.nombre AS tipo
     FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE e.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
     ORDER BY e.codigo",
    $ids
) : [];

 $empresa = Database::getValue("SELECT valor FROM configuracion WHERE clave = 'empresa_nombre'") ?: 'LUMAT CONSTRUCCIONES';

 $mapaJs = [];
foreach ($equipos as $e) { $mapaJs[(int)$e['id']] = $e['codigo']; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Etiquetas de inventario — <?= htmlspecialchars($empresa) ?></title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<style>
  @page { size: A4 portrait; margin: 9mm 10mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; color: #111; background: #f4f6f9; }

  .hoja { width: 190mm; margin: 0 auto; background: #fff; padding: 6mm; }

  .grid { display: grid; grid-template-columns: repeat(2, 90mm); grid-auto-rows: 29mm;
          gap: 2.5mm 4mm; justify-content: center; }

  .etq { border: 0.4mm dashed #bbb; border-radius: 1.5mm; padding: 1.5mm 2.5mm;
         display: flex; flex-direction: column; justify-content: space-between;
         page-break-inside: avoid; overflow: hidden; }

  .etq-top { display: flex; justify-content: space-between; align-items: center; gap: 2mm; }
  .etq-empresa { font-size: 6.5pt; font-weight: 700; color: #1e2732; letter-spacing: 0.3px;
                 text-transform: uppercase; line-height: 1.1; max-width: 55mm; }
  .etq-prop { font-size: 5.5pt; color: #555; text-align: right; line-height: 1.2; }

  .etq-mid { display: flex; align-items: center; gap: 2mm; }
  .etq-barcode { flex: 0 0 52mm; }
  .etq-barcode svg { width: 100%; height: 11mm; }
  .etq-datos { flex: 1; font-size: 6pt; line-height: 1.35; color: #333; min-width: 0; }
  .etq-datos b { font-size: 6.5pt; }

  .etq-bottom { text-align: center; }
  .etq-codigo { font-size: 8pt; font-weight: 700; letter-spacing: 0.5px; font-family: Consolas, monospace; }

  .noprint { position: sticky; top: 0; z-index: 10; background: #1e2732; padding: 10px;
             display: flex; gap: 10px; justify-content: center; align-items: center; margin-bottom: 12px;
             flex-wrap: wrap; }
  .noprint button { padding: 8px 18px; border: 0; border-radius: 6px; cursor: pointer;
                    font-size: 11pt; font-family: inherit; color: #fff; }
  .noprint span { color: #fff; font-size: 10pt; }
  @media print { .noprint { display: none !important; } body { background: #fff; } .hoja { padding: 0; } }
</style>
</head>
<body>

<div class="noprint">
  <button style="background:#2563eb" onclick="window.print()">&#128424; Imprimir etiquetas (<?= count($equipos) ?>)</button>
  <button style="background:#16a34a" onclick="generar()">&#128260; Regenerar codigos</button>
  <span>Hoja A4 · 20 etiquetas de 90×29mm · etiquetas adhesivas estandar</span>
</div>

<div class="hoja">
  <div class="grid">
    <?php foreach ($equipos as $e): ?>
    <div class="etq">
      <div class="etq-top">
        <div class="etq-empresa"><?= htmlspecialchars($empresa) ?></div>
        <div class="etq-prop">PROPIEDAD DE<br><?= htmlspecialchars($empresa) ?></div>
      </div>
      <div class="etq-mid">
        <div class="etq-barcode">
          <svg class="bc" data-cod="<?= htmlspecialchars($e['codigo']) ?>"></svg>
        </div>
        <div class="etq-datos">
          <b><?= htmlspecialchars($e['tipo']) ?></b><br>
          <?= htmlspecialchars($e['marca'] . ' ' . $e['modelo']) ?><br>
          <?php if ($e['nro_serie']): ?>S/N: <?= htmlspecialchars($e['nro_serie']) ?><br><?php endif; ?>
          <?php if ($e['activo_fijo']): ?>AF: <?= htmlspecialchars($e['activo_fijo']) ?><?php endif; ?>
        </div>
      </div>
      <div class="etq-bottom">
        <span class="etq-codigo"><?= htmlspecialchars($e['codigo']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$equipos): ?>
    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666">
      No se seleccionaron equipos para imprimir etiquetas.
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function generar() {
  document.querySelectorAll('svg.bc').forEach(function (svg) {
    var cod = svg.getAttribute('data-cod');
    if (!cod) return;
    try {
      JsBarcode(svg, cod, {
        format: 'CODE128',
        width: 1.35,
        height: 42,
        displayValue: false,
        margin: 0
      });
    } catch (e) { console.error('barcode error', e); }
  });
}

generar();
</script>
</body>
</html>