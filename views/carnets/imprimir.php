<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Carnet v6: barcode ancho + firma amplia
// ============================================================
require_once __DIR__ . '/../../core/guard_vista.php';

 $idsRaw = trim((string)($_GET['ids'] ?? ''));
 $ids = array_values(array_filter(
    array_map('intval', explode(',', $idsRaw)), fn($i) => $i > 0));

 Carnets::asegurar();
 $personal = $ids ? Database::get(
    "SELECT p.id, p.dni, p.nombres, p.apellidos, p.cargo, p.correo_corporativo,
            p.codigo_usuario, p.locker, a.nombre AS area, p.fecha_ingreso,
            COALESCE(p.foto, (SELECT u.foto FROM usuarios u WHERE u.personal_id = p.id AND u.estado <> 'inactivo' ORDER BY u.id LIMIT 1)) AS foto,
            (SELECT c.codigo_carnet FROM carnets c WHERE c.personal_id = p.id
             AND c.estado IN ('pendiente','impreso','entregado')
             ORDER BY c.id DESC LIMIT 1) AS carnet_codigo,
            (SELECT c.fecha_vigencia FROM carnets c WHERE c.personal_id = p.id
             AND c.estado IN ('pendiente','impreso','entregado')
             ORDER BY c.id DESC LIMIT 1) AS carnet_vigencia,
            (SELECT c.fecha_emision FROM carnets c WHERE c.personal_id = p.id
             AND c.estado IN ('pendiente','impreso','entregado')
             ORDER BY c.id DESC LIMIT 1) AS carnet_emision
     FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id
     WHERE p.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
     ORDER BY p.apellidos", $ids) : [];

 $empresa = Empresa::nombre();
 $empresaCorta = mb_substr(explode(' ', trim($empresa))[0], 0, 14);

 $col = ColoresLogo::obtener();
 $cP  = $col['primario'];
 $cPl = $col['primario_claro'];
 $cS  = $col['secundario'];
 $cT  = $col['texto_claro'] ? '#ffffff' : '#1e293b';
 $cTA = $col['texto_acento'];

// hex a rgb
 $hexL = ltrim($cP, '#');
 $rH = hexdec(substr($hexL, 0, 2));
 $gH = hexdec(substr($hexL, 2, 2));
 $bH = hexdec(substr($hexL, 4, 2));

 $ancho = 85.6; $alto = 54;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Carnet — <?= htmlspecialchars($empresaCorta) ?></title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<style>
  @page { size: <?= $ancho ?>mm <?= $alto ?>mm; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { width: <?= $ancho ?>mm; background: #fff; }

  .carnet {
    width: <?= $ancho ?>mm; height: <?= $alto ?>mm;
    page-break-after: always;
    overflow: hidden; position: relative;
    font-family: 'Segoe UI', Arial, sans-serif;
  }
  .carnet:last-child { page-break-after: auto; }

  /* ============================================
     FRENTE
     ============================================ */
  .car-frente {
    background: linear-gradient(160deg, <?= $cPl ?> 0%, <?= $cP ?> 60%);
    color: <?= $cT ?>;
    display: flex; flex-direction: column;
  }
  .car-franja {
    height: 9mm;
    background: linear-gradient(180deg, rgba(255,255,255,.15) 0%, transparent 40%),
                var(--franja-color, #F5C518);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 3mm;
    border-bottom: 0.3mm solid rgba(0,0,0,.08);
    box-shadow: inset 0 -0.5mm 1mm rgba(0,0,0,.06);
  }

  .car-franja-left {
    display: flex; align-items: center; gap: 2mm;
    background: rgba(255,255,255,.92);
    border-radius: 1.5mm;
    padding: 0.5mm 2mm;
    box-shadow: 0 0.3mm 1mm rgba(0,0,0,.12), inset 0 0.3mm 0.5mm rgba(255,255,255,.8);
    border: 0.15mm solid rgba(255,255,255,.6);
  }

  .car-franja-left img {
    height: 6mm; width: auto; object-fit: contain;
    filter: contrast(1.05) saturate(1.02);
  }

  .car-empresa {
    font-size: 10pt; font-weight: 800;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: #1e293b;
    text-shadow: 0 0.15mm 0.15mm rgba(255,255,255,.5);
  }

  .car-tipo {
    font-size: 5.5pt; font-weight: 600;
    color: #1e293b;
    text-transform: uppercase; letter-spacing: 0.8px;
    opacity: .75;
    margin-right: 1mm;
  }
  .car-franja-left { display: flex; align-items: center; gap: 2.5mm; }
  .car-logo-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.95);
        border-radius: 1.2mm;
        padding: 0.5mm 1.5mm;
        height: 6.5mm;
        box-shadow: 0 0.3mm 1mm rgba(0,0,0,.15);
      }
      .car-logo-chip img {
        height: 5mm;
        width: auto;
        object-fit: contain;
        max-width: 20mm;
      }
      .car-franja img { height: 5mm; width: auto; object-fit: contain; }
  .car-empresa {
    font-size: 10pt; font-weight: 800;
    letter-spacing: 2px; text-transform: uppercase;
    color: <?= $cTA ?>;
  }
  .car-tipo {
    font-size: 5.5pt; font-weight: 600;
    color: <?= $cTA ?>;
    text-transform: uppercase; letter-spacing: .8px; opacity: .8;
  }
  .car-body {
    flex: 1;
    display: flex; gap: 4mm;
    padding: 3mm 5mm;
    align-items: center;
  }
  .car-foto {
    width: 20mm; height: 26mm;
    background: rgba(255,255,255,.15);
    border: 0.5mm solid <?= $cS ?>;
    border-radius: 1.5mm;
    display: flex; align-items: center; justify-content: center;
    font-size: 6pt; color: rgba(255,255,255,.5);
    overflow: hidden; flex-shrink: 0;
  }
  .car-foto img { width: 100%; height: 100%; object-fit: cover; }
  .car-datos {
    flex: 1; display: flex; flex-direction: column;
    justify-content: center; gap: 0.5mm;
  }
  .car-nombre {
    font-size: 10.5pt; font-weight: 700; line-height: 1.1;
    color: <?= $cT ?>;
    text-shadow: 0 0 3px rgba(0,0,0,.25);
  }
  .car-cargo {
    font-size: 7pt; color: <?= $cS ?>;
    font-weight: 600; text-transform: uppercase;
  }
  .car-area { font-size: 6.5pt; color: rgba(255,255,255,.75); }
  .car-dni {
    font-size: 8.5pt; font-weight: 700;
    background: <?= $cS ?>;
    color: <?= $cTA ?>;
    padding: 0.6mm 3mm;
    border-radius: 1mm;
    display: inline-block; width: fit-content;
    margin-top: 1mm;
  }
  .car-cod {
    font-size: 7pt; font-weight: 700; font-family: 'Courier New', monospace;
    color: <?= $cT ?>; margin-top: 0.6mm; letter-spacing: .5px;
  }
  .car-pie {
    height: 5mm;
    display: flex; justify-content: space-between; align-items: center;
    padding: 0 4mm;
    font-size: 5pt; color: rgba(255,255,255,.85);
    border-top: 0.3mm solid <?= $cS ?>;
  }

  /* ============================================
     REVERSO — barcode GRANDE + FIRMA AMPLIA
     ============================================ */
  .car-reverso {
    background:
      repeating-linear-gradient(45deg,
        rgba(<?= $rH ?>,<?= $gH ?>,<?= $bH ?>, 0.03) 0px,
        rgba(<?= $rH ?>,<?= $gH ?>,<?= $bH ?>, 0.03) 10px,
        rgba(<?= $rH ?>,<?= $gH ?>,<?= $bH ?>, 0.06) 10px,
        rgba(<?= $rH ?>,<?= $gH ?>,<?= $bH ?>, 0.06) 20px
      ),
      #ffffff;
    display: flex; flex-direction: column;
    align-items: center;
    padding: 3mm 3mm 8mm 3mm;
    color: #1e293b;
    position: relative;
    overflow: hidden;
  }
  .rev-top-marco {
    position: absolute; top: 0; left: 0; right: 0;
    height: 3mm; background: <?= $cP ?>;
  }
  .rev-bottom-marco {
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 2.5mm;
    background: linear-gradient(90deg, <?= $cP ?> 70%, <?= $cS ?> 70%);
  }

  .rev-header {
    font-size: 7pt; font-weight: 700; color: <?= $cP ?>;
    text-transform: uppercase; letter-spacing: 1px;
    margin-top: 2.5mm; margin-bottom: 1.5mm;
  }

  /* ===== BARCODE: OCUPA CASI TODO EL ANCHO ===== */
  .rev-barcode {
    width: 100%;
    display: flex; flex-direction: column;
    align-items: center;
    margin-bottom: 1.5mm;
  }
  .rev-barcode svg {
    width: 78mm;      /* ANCHO: casi todo el carnet (85.6 - margenes) */
    height: 16mm;     /* ALTO: más alto que antes (era 13mm) */
  }
  .rev-codigo {
    font-size: 8pt; font-weight: 700;
    font-family: 'Courier New', monospace;
    color: #000;
    margin-top: 0.8mm;
  }

  .rev-info {
    font-size: 5.5pt; color: #475569;
    text-align: center; margin-top: 1mm; line-height: 1.4;
  }

  /* ===== FIRMA: ESPACIO AMPLIO ===== */
  .rev-firma-zona {
    margin-top: auto;
    width: 70mm;
    text-align: center;
  }
  .rev-firma-linea {
    border-bottom: 0.4mm solid #1e293b;
    height: 9mm;          /* espacio vertical para firmar */
    width: 100%;
  }
  .rev-firma-texto {
    font-size: 6pt; color: #475569;
    margin-top: 1mm;
    font-weight: 600;
    letter-spacing: .5px;
  }

  /* ===== BARRA DE CONTROL ===== */
  .barra-control {
    position: sticky; top: 0; z-index: 10;
    background: <?= $cP ?>;
    padding: 10px 16px;
    display: flex; gap: 10px; justify-content: center; align-items: center;
    flex-wrap: wrap; border-radius: 10px; margin-bottom: 10px;
    width: auto !important;
  }
  .barra-control button {
    padding: 8px 18px; border: 0; border-radius: 6px;
    cursor: pointer; font-size: 11pt; font-weight: 600;
    color: <?= $cTA ?>; background: <?= $cS ?>;
  }
  .barra-control span { color: rgba(255,255,255,.85); font-size: 10pt; }
  @media print { .barra-control { display: none !important; } }

  body.modo-edicion {
    background: #e9edf2; padding: 15px;
    display: flex; flex-direction: column; align-items: center; gap: 10px;
  }
  body.modo-edicion .carnet {
    outline: 2px dashed #94a3b8; outline-offset: 2px;
    box-shadow: 0 4px 18px rgba(0,0,0,.2);
  }
  @media print {
    body.modo-edicion { background: #fff; padding: 0; display: block; }
    body.modo-edicion .carnet { outline: 0; box-shadow: none; }
  }
</style>
</head>
<body class="modo-edicion">

<div class="barra-control">
  <button onclick="imprimirCarnets()">&#128424; Imprimir (<?= count($personal) ?>)</button>
  <span><?= htmlspecialchars($empresaCorta) ?> &middot; CR80 <?= $ancho ?>&times;<?= $alto ?>mm</span>
</div>

<?php foreach ($personal as $p):
    $nombre = $p['nombres'] . ' ' . $p['apellidos'];
    $codCarnet = $p['carnet_codigo'] ?: 'CR-' . date('Y') . '-' . str_pad($p['id'], 5, '0', STR_PAD_LEFT);
    $vigencia = $p['carnet_vigencia'] ?: date('Y-m-d', strtotime('+2 years'));
    $emision = $p['carnet_emision'] ?: date('Y-m-d');

    // el codigo de barras lleva el codigo de usuario (SIS-0001); sin el, el numero de carnet
    $codUsuario = trim((string)($p['codigo_usuario'] ?? ''));
    $datosBarcode = $codUsuario !== '' ? $codUsuario : $codCarnet;
?>
<!-- ===== FRENTE ===== -->
<div class="carnet car-frente">
  <div class="car-franja">
    <div class="car-franja-left">
      <div class="car-logo-chip">
        <img src="<?= BASE_URL ?>api/configuracion/logo-publico.php?t=<?= time() ?>"
             alt="logo" onerror="this.parentNode.style.display='none'">
      </div>
      <span class="car-empresa"><?= htmlspecialchars($empresaCorta) ?></span>
    </div>
    <span class="car-tipo">Carnet Institucional</span>
  </div>
  <div class="car-body">
    <div class="car-foto">
      <img src="<?= BASE_URL ?>api/personal/foto.php?id=<?= (int)$p['id'] ?>&v=<?= urlencode((string)($p['foto'] ?? '')) ?>"
           alt="foto" onerror="this.parentNode.innerHTML='SIN<br>FOTO'">
    </div>
    <div class="car-datos">
      <div class="car-nombre"><?= htmlspecialchars($nombre) ?></div>
      <div class="car-cargo"><?= htmlspecialchars($p['cargo'] ?: '—') ?></div>
      <div class="car-area"><?= htmlspecialchars($p['area'] ?: '—') ?></div>
      <div class="car-dni">DNI: <?= htmlspecialchars($p['dni']) ?></div>
      <?php if ($codUsuario !== ''): ?><div class="car-cod">C&oacute;digo: <?= htmlspecialchars($codUsuario) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="car-pie">
    <span>Ingreso: <?= htmlspecialchars($p['fecha_ingreso'] ?: '—') ?></span>
    <span>Vigente: <?= htmlspecialchars($vigencia) ?></span>
  </div>
</div>

<!-- ===== REVERSO (barcode grande + firma amplia) ===== -->
<div class="carnet car-reverso">
  <div class="rev-top-marco"></div>

  <div class="rev-header"><?= htmlspecialchars($empresaCorta) ?> &mdash; Control de Acceso</div>

  <!-- BARCODE: ocupa casi todo el ancho -->
  <div class="rev-barcode">
    <svg class="bc" data-cod="<?= htmlspecialchars($datosBarcode) ?>"></svg>
  </div>
  <div class="rev-codigo"><?= htmlspecialchars($datosBarcode) ?></div>

  <div class="rev-info">
    Carnet <?= htmlspecialchars($codCarnet) ?> &middot; Emisi&oacute;n: <?= htmlspecialchars($emision) ?> &middot; Vigencia: <?= htmlspecialchars($vigencia) ?>
  </div>

  <!-- FIRMA: espacio amplio con l&iacute;nea -->
  <div class="rev-firma-zona">
    <div class="rev-firma-linea"></div>
    <div class="rev-firma-texto">FIRMA DEL TRABAJADOR</div>
  </div>

  <div class="rev-bottom-marco"></div>
</div>
<?php endforeach; ?>

<?php if (!$personal): ?>
<div style="text-align:center;color:#666;padding:30px;font-family:Arial">Sin personas.</div>
<?php endif; ?>

<script>
// generar barcode con barras GRUESAS y ANCHAS
document.querySelectorAll('svg.bc').forEach(function (svg) {
  var cod = svg.getAttribute('data-cod');
  if (!cod) return;
  try {
    JsBarcode(svg, cod, {
      format: 'CODE128',
      width: 2,          // barras gruesas
      height: 60,        // alto generoso (el CSS lo escala a 16mm)
      displayValue: false,
      margin: 0,
      flat: true
    });
  } catch (e) { console.error(e); }
});

// imprime y avisa a la pantalla de Carnets para ofrecer "marcar como impreso"
var IDS_IMPRESOS = <?= json_encode(array_map(fn($p) => (int)$p['id'], $personal)) ?>;
function imprimirCarnets() {
  window.print();
  try { if (window.opener && window.opener.Carnets) window.opener.Carnets.despuesDeImprimir(IDS_IMPRESOS); } catch (e) {}
}
</script>
</body>
</html>