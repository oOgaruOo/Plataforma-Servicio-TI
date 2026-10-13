<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Guía de Instalación — Rastreo de Equipos</title>
<style>
  @page { size: A4; margin: 20mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; font-size: 11pt; line-height: 1.6; background: #f4f6f9; }
  .pagina { max-width: 210mm; margin: 0 auto; background: #fff; padding: 20mm; box-shadow: 0 4px 20px rgba(0,0,0,.1); }

  h1 { font-size: 20pt; color: #0D2E5C; border-bottom: 3px solid #F5C518; padding-bottom: 10px; margin-bottom: 20px; }
  h2 { font-size: 14pt; color: #0D2E5C; margin-top: 25px; margin-bottom: 10px; border-left: 4px solid #F5C518; padding-left: 10px; }
  h3 { font-size: 12pt; color: #1B4F9C; margin-top: 15px; margin-bottom: 8px; }
  p { margin-bottom: 10px; }

  .paso { background: #f0f4fa; border: 1px solid #c5d5e8; border-radius: 8px; padding: 15px; margin: 15px 0; }
  .paso-num { display: inline-block; background: #0D2E5C; color: #F5C518; width: 30px; height: 30px;
    border-radius: 50%; text-align: center; line-height: 30px; font-weight: 700; font-size: 14pt; margin-right: 10px; }

  .comando { background: #1e293b; color: #4ade80; padding: 12px 15px; border-radius: 6px;
    font-family: Consolas, monospace; font-size: 10pt; margin: 10px 0; white-space: pre-wrap; }
  .comando .comentario { color: #94a3b8; }

  .alerta { background: #fef3c7; border: 1px solid #fde047; border-radius: 8px; padding: 12px; margin: 10px 0; }
  .alerta-rojo { background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px; padding: 12px; margin: 10px 0; }
  .ok { background: #dcfce7; border: 1px solid #16a34a; border-radius: 8px; padding: 12px; margin: 10px 0; }

  table { width: 100%; border-collapse: collapse; margin: 10px 0; }
  th { background: #0D2E5C; color: #fff; padding: 8px 12px; text-align: left; font-size: 10pt; }
  td { border: 1px solid #cbd5e1; padding: 8px 12px; font-size: 10pt; }
  tr:nth-child(even) { background: #f1f5f9; }

  .noprint { position: fixed; top: 10px; right: 10px; z-index: 100; }
  .noprint button { padding: 10px 20px; background: #0D2E5C; color: #F5C518; border: 0;
    border-radius: 6px; font-size: 12pt; cursor: pointer; font-weight: 600; }
  @media print { .noprint { display: none; } body { background: #fff; } .pagina { box-shadow: none; } }

  .flujo { text-align: center; margin: 20px 0; }
  .flujo-caja { display: inline-block; background: #e0f2fe; border: 2px solid #0284c7; border-radius: 8px;
    padding: 8px 15px; margin: 5px; font-size: 10pt; font-weight: 600; color: #0c4a6e; }
  .flujo-flecha { color: #0284c7; font-size: 18pt; font-weight: 700; vertical-align: middle; }
</style>
</head>
<body>

<div class="noprint">
  <button onclick="window.print()">&#128424; Imprimir / Guardar PDF</button>
</div>

<div class="pagina">

<h1>&#128225; Guía — Rastreo de Equipos TI</h1>
<p><b>Sistema:</b> SIGTI — Plataforma de Servicio TI &middot; <b>Área:</b> Sistemas</p>

<div class="alerta">
  <b>&#9888; CONFIDENCIAL:</b> el rastreo se activa <b>solo</b> en equipos de propiedad de la empresa y su información
  debe limitarse al personal autorizado del Área de Sistemas. Solo se capturan datos de red y ubicación
  (IP, MAC, nombre del equipo, Wi-Fi y posición): <b>no</b> se captura pantalla, archivos ni actividad personal.
</div>

<h2>&#127760; ¿Cómo funciona?</h2>
<div class="flujo">
  <span class="flujo-caja">Equipo encendido<br>con internet</span>
  <span class="flujo-flecha">&rarr;</span>
  <span class="flujo-caja">Agente SIGTI<br>reporta cada 5 min</span>
  <span class="flujo-flecha">&rarr;</span>
  <span class="flujo-caja">SIGTI guarda IP, MAC,<br>Wi-Fi y ubicación</span>
  <span class="flujo-flecha">&rarr;</span>
  <span class="flujo-caja">Equipos &rarr; Seguimiento<br>&rarr; <b>Rastreo en vivo</b></span>
</div>

<table>
  <tr><th>Origen de la ubicación</th><th>Precisión</th><th>Cuándo se usa</th></tr>
  <tr><td><b>GPS / Wi-Fi de Windows</b></td><td>Decenas de metros</td><td>El equipo tiene <i>Ubicación</i> activada (Configuración &rsaquo; Privacidad &rsaquo; Ubicación)</td></tr>
  <tr><td><b>Sede conocida</b></td><td>La oficina / obra</td><td>La red del equipo coincide con una sede registrada en <i>Sedes y redes</i></td></tr>
  <tr><td><b>Aproximada (Windows sin GPS)</b></td><td>Cientos de metros a varios km</td><td>Windows informa una posición con margen mayor a 1,5 km (laptops sin GPS). Se muestra como <i>Aproximada</i>; si la red está en <i>Sedes y redes</i> con coordenadas, se usa la sede. <b>Registre la sede de cada oficina</b> para ver la dirección correcta</td></tr>
  <tr><td><b>IP pública</b></td><td>Solo aproximada (ciudad)</td><td>No hay posición ni sede. Sirve de pista, no de dirección exacta</td></tr>
</table>

<h2>&#9997; Paso 1: Activar el rastreo y descargar el agente</h2>
<div class="paso">
  <span class="paso-num">1</span>
  <p>Menú <b>Equipos</b> &rarr; abra la ficha del equipo &rarr; botón <b>Rastreo</b> (o vaya a <b>Equipos &rarr; Seguimiento &rarr; Rastreo en vivo</b> y pulse <i>Detalle</i> en el equipo).</p>
</div>
<div class="paso">
  <span class="paso-num">2</span>
  <p>Pulse <b>Activar rastreo</b>. Luego <b>Descargar agente (.ps1)</b>. El sistema le pide la <b>dirección del servidor</b>
  tal como la verá el equipo (ejemplo <code>http://192.168.1.100/Plataforma-Servicio-TI</code>; no use <i>localhost</i>).</p>
  <p>El archivo descargado <b>ya trae el servidor y el token del equipo</b>: no hay que editar nada.</p>
</div>

<h2>&#128187; Paso 2: Instalar el agente en el equipo (una sola vez)</h2>
<div class="paso">
  <span class="paso-num">3</span>
  <p>Copie el archivo al equipo. Abra <b>PowerShell como administrador</b> en esa carpeta y ejecute:</p>
  <div class="comando">powershell -ExecutionPolicy Bypass -File .\sigti-rastreo-CODIGO.ps1 -Instalar</div>
  <p>Esto crea la tarea programada <code>SIGTI-Rastreo</code> (cada 5 minutos y al iniciar sesión) y hace el primer reporte.
  Para probar sin instalar: el mismo comando sin <code>-Instalar</code>. Para quitarlo: <code>-Desinstalar</code>.</p>
</div>
<div class="paso">
  <span class="paso-num">4</span>
  <p>En Windows active <b>Configuración &rsaquo; Privacidad y seguridad &rsaquo; Ubicación</b> (servicios de ubicación y «permitir que las aplicaciones de escritorio accedan»)
  si quiere posición precisa. Sin eso el equipo igual se ubica por sede o IP.</p>
</div>

<div class="alerta">
  <b>Alternativa mínima (.bat):</b> el archivo <code>storage/agente-rastreo.bat</code> reporta solo IP, MAC y nombre (sin Wi-Fi ni posición).
  Edite <code>SERVIDOR</code> y <code>TOKEN</code> (el token se ve con <i>Ver token</i> en el detalle del equipo) y prográmelo:
  <div class="comando">schtasks /create /tn "SIGTI-Rastreo" /tr "C:\ProgramData\sigti-rastreo.bat" /sc minute /mo 5 /ru SYSTEM</div>
</div>

<h2>&#128269; Paso 3: Verificar</h2>
<div class="paso">
  <span class="paso-num">5</span>
  <p>En <b>Equipos &rarr; Seguimiento &rarr; Rastreo en vivo</b> el equipo debe aparecer <b>En línea</b> con su última ubicación y un punto en el mapa.
  Si la IP o la MAC del inventario no coinciden con las que reporta el equipo, se muestra una advertencia.</p>
</div>
<div class="paso">
  <span class="paso-num">6</span>
  <p>Registre sus oficinas, obras y proyectos en <b>Sedes y redes</b> (IP pública de la sede, inicio de la red local, coordenadas): así los equipos se ven en la sede aunque no tengan GPS.</p>
</div>

<h2>&#128680; Si un equipo se pierde o es robado</h2>
<div class="alerta-rojo"><b>Actúe cuanto antes:</b> mientras el equipo siga encendido y con internet, reportará su posición.</div>
<div class="paso">
  <span class="paso-num">A</span>
  <p>Abra el <b>Detalle</b> del equipo y pulse <b>Marcar perdido / robado</b>, con una nota (dónde se vio por última vez, n.º de denuncia).
  El equipo pasa a reportar <b>cada minuto</b> y, cada vez que se conecte, los usuarios con permiso de edición de equipos reciben una notificación.</p>
</div>
<div class="paso">
  <span class="paso-num">B</span>
  <p>Pulse el botón del mapa (<b>Ubicar</b>) para ver el último punto y el <b>recorrido</b> de los últimos reportes. Con la IP pública, la fecha y hora y la MAC presente la denuncia; el proveedor de internet puede identificar la conexión con orden judicial.</p>
</div>
<div class="paso">
  <span class="paso-num">C</span>
  <p>Cuando aparezca, pulse <b>Equipo encontrado</b>. Si ya no se recupera, dé de baja el equipo con motivo <i>Robo</i> o <i>Pérdida</i>.</p>
</div>

<h2>&#9888; Límites que conviene conocer</h2>
<div class="alerta">
  <ul>
    <li>El equipo debe estar <b>encendido, con internet y con el agente instalado</b>. Si lo formatean o desinstalan el agente, aparecerá «Sin señal». Proteja el BIOS/UEFI con contraseña.</li>
    <li>El equipo debe poder <b>llegar al servidor SIGTI</b>. Si SIGTI solo es accesible dentro de la oficina, solo se recibirán reportes mientras esté en esa red o conectado por VPN. Para ubicar equipos fuera de la oficina, SIGTI debe publicarse con un dominio o IP pública (con HTTPS).</li>
    <li>La ubicación por IP es aproximada; la ubicación por GPS/Wi-Fi depende de la configuración de Windows. Los datos <b>los declara el equipo</b>: confírmelos antes de actuar.</li>
    <li>La geolocalización por IP consulta un servicio externo (ip-api.com) con la IP pública del equipo; el resultado se guarda en caché.</li>
    <li>Celulares y tablets (IMEI) no se rastrean con este agente.</li>
    <li>El token es único por equipo y está dentro del archivo del agente: no lo comparta. Si se filtra, use <b>Regenerar token</b>.</li>
    <li>El historial de reportes se conserva 90 días.</li>
  </ul>
</div>

<p style="margin-top:30px;text-align:center;color:#64748b;font-size:9pt">
  SIGTI — Plataforma de Servicio TI &middot; Documento interno &middot; Área de Sistemas &middot; <?= htmlspecialchars(Empresa::nombre()) ?>
</p>

</div>
</body>
</html>
