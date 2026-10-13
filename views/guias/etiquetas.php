<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Guía — Impresión de Etiquetas XP-237B</title>
<style>
  @page { size: A4; margin: 20mm; }
  body { font-family: 'Segoe UI', Arial; font-size: 11pt; line-height: 1.6; color: #1e293b; background: #f4f6f9; }
  .pagina { max-width: 210mm; margin: 0 auto; background: #fff; padding: 20mm; }
  h1 { color: #0D2E5C; border-bottom: 3px solid #F5C518; padding-bottom: 10px; }
  h2 { color: #1B4F9C; border-left: 4px solid #F5C518; padding-left: 10px; margin-top: 20px; }
  .paso { background: #f0f4fa; border-radius: 8px; padding: 15px; margin: 10px 0; }
  .paso-num { display: inline-block; background: #0D2E5C; color: #F5C518; width: 30px; height: 30px; border-radius: 50%; text-align: center; line-height: 30px; font-weight: 700; margin-right: 10px; }
  .comando { background: #1e293b; color: #4ade80; padding: 10px 15px; border-radius: 6px; font-family: Consolas; font-size: 10pt; margin: 8px 0; }
  .alerta { background: #fef3c7; border: 1px solid #fde047; border-radius: 8px; padding: 12px; margin: 10px 0; }
  .ok { background: #dcfce7; border: 1px solid #16a34a; border-radius: 8px; padding: 12px; margin: 10px 0; }
  .noprint { position: fixed; top: 10px; right: 10px; }
  .noprint button { padding: 10px 20px; background: #0D2E5C; color: #F5C518; border: 0; border-radius: 6px; font-size: 12pt; cursor: pointer; }
  @media print { .noprint { display: none; } body { background: #fff; } .pagina { box-shadow: none; } }
</style>
</head>
<body>
<div class="noprint"><button onclick="window.print()">&#128424; Imprimir / PDF</button></div>
<div class="pagina">
<h1>&#128424; Guía: Impresión de Etiquetas — Xprinter XP-237B</h1>

<h2>Configuración de la impresora</h2>
<div class="paso">
  <span class="paso-num">1</span>
  <p>Conecta la <b>Xprinter XP-237B</b> por USB e instala el driver oficial (incluido en el CD o descargable de xprinter.net).</p>
</div>
<div class="paso">
  <span class="paso-num">2</span>
  <p>Coloca el rollo de etiquetas <b>40×30mm</b> térmicas directas.</p>
  <p>Si usas otro tamaño (ej: 58×40), actualízalo en el sistema:</p>
  <div class="comando">phpMyAdmin → sigti → configuracion → etiqueta_ancho_mm / etiqueta_alto_mm</div>
</div>

<h2>Imprimir desde el sistema</h2>
<div class="paso">
  <span class="paso-num">3</span>
  <p>En SIGTI → <b>Equipos</b> → marca los equipos con los checkboxes → botón <b>"Etiquetas (N)"</b>.</p>
</div>
<div class="paso">
  <span class="paso-num">4</span>
  <p>Se abre la vista previa con las etiquetas apiladas verticalmente.</p>
  <p>Clic en <b>"Imprimir"</b> → aparece el diálogo del navegador.</p>
</div>
<div class="paso">
  <span class="paso-num">5</span>
  <p>En el diálogo de impresión:</p>
  <ul>
    <li><b>Impresora:</b> Xprinter XP-237B</li>
    <li><b>Tamaño de papel:</b> 40×30mm (o el que corresponda)</li>
    <li><b>Escala:</b> 100% (NO "ajustar a página")</li>
    <li><b>Márgenes:</b> Ninguno</li>
  </ul>
</div>
<div class="ok">&#9989; Cada equipo sale en una etiqueta individual con código de barras CODE128.</div>

<h2>Problemas comunes</h2>
<div class="alerta">
  <b>Etiqueta sale muy clara:</b> Es térmica directa — solo imprime negro puro. Si sale tenue, el papel puede ser de baja calidad o la cabeza térmica está sucia.<br><br>
  <b>Se come la etiqueta:</b> Verifica que el tamaño del papel en el driver coincida con el rollo (40×30).<br><br>
  <b>Código de barras ilegible:</b> Usa barras gruesas (el sistema ya lo configura automáticamente).
</div>

<p style="text-align:center;color:#64748b;font-size:9pt;margin-top:30px">SIGTI — Documento interno — Área de Sistemas</p>
</div>
</body>
</html>