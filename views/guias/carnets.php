<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Guía — Carnets de Trabajadores</title>
<style>
  @page { size: A4; margin: 20mm; }
  body { font-family: 'Segoe UI', Arial; font-size: 11pt; line-height: 1.6; color: #1e293b; background: #f4f6f9; }
  .pagina { max-width: 210mm; margin: 0 auto; background: #fff; padding: 20mm; }
  h1 { color: #0D2E5C; border-bottom: 3px solid #F5C518; padding-bottom: 10px; }
  h2 { color: #1B4F9C; border-left: 4px solid #F5C518; padding-left: 10px; margin-top: 20px; }
  .paso { background: #f0f4fa; border-radius: 8px; padding: 15px; margin: 10px 0; }
  .paso-num { display: inline-block; background: #0D2E5C; color: #F5C518; width: 30px; height: 30px; border-radius: 50%; text-align: center; line-height: 30px; font-weight: 700; margin-right: 10px; }
  .noprint { position: fixed; top: 10px; right: 10px; }
  .noprint button { padding: 10px 20px; background: #0D2E5C; color: #F5C518; border: 0; border-radius: 6px; font-size: 12pt; cursor: pointer; }
  @media print { .noprint { display: none; } body { background: #fff; } .pagina { box-shadow: none; } }
</style>
</head>
<body>
<div class="noprint"><button onclick="window.print()">&#128424; Imprimir / PDF</button></div>
<div class="pagina">
<h1>&#129481; Guía: Carnets de Trabajadores</h1>

<h2>Flujo del carnet</h2>
<div class="paso">
  <span class="paso-num">1</span>
  <p><b>Personal → Editar</b>: suba la <b>foto del carnet</b> (PNG o JPG, se recorta en cuadrado). Si la persona tiene cuenta de usuario y no sube otra, se usa la de su cuenta. El <b>código de usuario</b> (ej. SIS-0001) lo asigna el sistema según el área y es el que lleva el código de barras.</p>
</div>
<div class="paso">
  <span class="paso-num">2</span>
  <p><b>Personal → Carnets</b>: aparece todo el personal con su estado. Marque personas <i>sin carnet</i> y pulse <b>"Generar"</b>: se crea el registro (CR-AAAA-NNNNN) en estado <b>Pendiente</b>. Puede filtrar por estado, área o buscar por código, DNI, nombre o cargo.</p>
</div>
<div class="paso">
  <span class="paso-num">3</span>
  <p><b>"Imprimir"</b>: sin marcar a nadie imprime todos los <b>Pendientes</b> del filtro; marcando personas imprime solo a ellas (o use &#128424; en la fila). Se abre la hoja (frente + reverso, 85.6×54 mm CR80) y el diálogo del navegador.</p>
</div>
<div class="paso">
  <span class="paso-num">4</span>
  <p>Al cerrar la impresión el sistema pregunta si salió bien y ofrece marcarlos como <b>Impreso</b>. También puede usar "Marcar impresos" o &#10003; en la fila.</p>
</div>
<div class="paso">
  <span class="paso-num">5</span>
  <p>Al entregarlo, marque <b>Entregado</b> (solo se puede entregar un carnet ya impreso).</p>
</div>

<h2>Cuándo hay que reimprimir</h2>
<p>Si cambian datos que van impresos (nombre, DNI, cargo, área, código de usuario o foto) y el carnet ya estaba impreso o entregado, aparece la etiqueta <b>"Reimprimir"</b>. El botón &#8635; lo devuelve a Pendiente con la misma vigencia. Cuando pasa la vigencia aparece <b>"Vencido"</b> y el mismo botón lo renueva con una vigencia nueva.</p>

<h2>Estados del carnet</h2>
<table style="width:100%;border-collapse:collapse">
<tr style="background:#0D2E5C;color:#fff"><th style="padding:8px">Estado</th><th>Significado</th><th>Acción</th></tr>
<tr><td style="padding:6px;border:1px solid #ccc">Sin carnet</td><td>No tiene registro</td><td>Generar</td></tr>
<tr style="background:#f1f5f9"><td style="padding:6px;border:1px solid #ccc">Pendiente</td><td>Generado pero no impreso</td><td>Imprimir</td></tr>
<tr><td style="padding:6px;border:1px solid #ccc">Impreso</td><td>Impreso pero no entregado</td><td>Entregar</td></tr>
<tr style="background:#f1f5f9"><td style="padding:6px;border:1px solid #ccc">Entregado</td><td>En uso</td><td>—</td></tr>
<tr><td style="padding:6px;border:1px solid #ccc">Vencido</td><td>Pasó la vigencia (24 meses)</td><td>Renovar (&#8635;)</td></tr>
</table>

<h2>Revertir</h2>
<p>Si te equivocaste de estado, usa el botón <b>&#8617;</b> para retroceder: Entregado→Impreso→Pendiente.</p>

<p style="text-align:center;color:#64748b;font-size:9pt;margin-top:30px">SIGTI — Documento interno — Área de Sistemas</p>
</div>
</body>
</html>