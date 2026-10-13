<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Guia imprimible: views/guias/ver.php?id=<guia>
// Se abre en otra pestana; Ctrl+P o el boton para imprimir / guardar como PDF.
// ============================================================
require_once __DIR__ . '/../../core/guard_vista.php';

function gv_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$g = Guias::buscar((string)($_GET['id'] ?? ''));
if (!$g || !Guias::visible($g)) { http_response_code(404); echo 'Guía no disponible.'; exit; }
if (!empty($g['url'])) { header('Location: ' . BASE_URL . $g['url']); exit; }
$empresa = (string)(Database::getValue("SELECT valor FROM configuracion WHERE clave = 'empresa_nombre'")
    ?: Database::getValue("SELECT valor FROM configuracion WHERE clave = 'nombre_empresa'") ?: '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Guía — <?= gv_h($g['titulo']) ?></title>
<style>
  @page { size: A4; margin: 18mm; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11pt; line-height: 1.55; color: #1e293b; background: #f4f6f9; margin: 0; }
  .pagina { max-width: 210mm; margin: 0 auto; background: #fff; padding: 16mm 18mm; }
  h1 { color: #0D2E5C; border-bottom: 3px solid #F5C518; padding-bottom: 8px; margin: 0 0 6px; font-size: 20pt; }
  .meta { color: #64748b; font-size: 10pt; margin-bottom: 14px; }
  h2 { color: #1B4F9C; border-left: 4px solid #F5C518; padding-left: 10px; margin: 20px 0 8px; font-size: 13pt; page-break-after: avoid; }
  ol { padding-left: 0; list-style: none; counter-reset: p; margin: 0; }
  ol li { counter-increment: p; display: flex; gap: 10px; margin: 6px 0; page-break-inside: avoid; }
  ol li::before { content: counter(p); flex: 0 0 24px; height: 24px; border-radius: 50%; background: #0D2E5C; color: #F5C518; font-weight: 700; font-size: 9pt; text-align: center; line-height: 24px; }
  .nota { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 8px 12px; margin: 8px 0; font-size: 10pt; border-radius: 4px; }
  .alerta { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 8px 12px; margin: 8px 0; font-size: 10pt; border-radius: 4px; }
  .pie { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 9pt; color: #94a3b8; }
  .noprint { position: fixed; top: 10px; right: 10px; }
  .noprint button { padding: 10px 20px; background: #0D2E5C; color: #F5C518; border: 0; border-radius: 6px; font-size: 12pt; cursor: pointer; }
  @media print { .noprint { display: none; } body { background: #fff; } .pagina { padding: 0; max-width: none; } }
</style>
</head>
<body>
<div class="noprint"><button onclick="window.print()">&#128424; Imprimir / PDF</button></div>
<div class="pagina">
  <h1><?= gv_h($g['titulo']) ?></h1>
  <div class="meta"><?= gv_h($g['categoria']) ?> · Dirigida a: <?= gv_h($g['para']) ?><?= $empresa !== '' ? ' · ' . gv_h($empresa) : '' ?></div>
  <p><?= gv_h($g['resumen']) ?></p>
<?php foreach ($g['secciones'] as $s): ?>
  <h2><?= gv_h($s['titulo']) ?></h2>
  <ol>
<?php foreach ($s['pasos'] as $p): ?>
    <li><span><?= gv_h($p) ?></span></li>
<?php endforeach; ?>
  </ol>
<?php if (!empty($s['nota'])): ?>  <div class="nota"><b>Consejo:</b> <?= gv_h($s['nota']) ?></div>
<?php endif; ?>
<?php if (!empty($s['alerta'])): ?>  <div class="alerta"><b>Cuidado:</b> <?= gv_h($s['alerta']) ?></div>
<?php endif; ?>
<?php endforeach; ?>
  <div class="pie">SIGTI — Plataforma de Servicio TI · Guía impresa el <?= date('d/m/Y H:i') ?></div>
</div>
</body>
</html>
