<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Descarga del AGENTE de rastreo ya configurado para un equipo
// (lleva el servidor y el token del equipo; no hay que editar nada).
// GET equipo_id=X [servidor=http://192.168.1.100/Plataforma-Servicio-TI]   (equipos.editar)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('equipos', 'editar');
Rastreo::asegurar();

 $equipoId = (int)($_GET['equipo_id'] ?? 0);
 $eq = Database::getOne('SELECT id, codigo FROM equipos WHERE id = ?', [$equipoId]);
if (!$eq) Response::error('El equipo no existe.');
 $config = Database::getOne('SELECT token_rastreo, activo FROM equipos_rastreo_config WHERE equipo_id = ?', [$equipoId]);
if (!$config || (int)$config['activo'] !== 1) {
    Response::error('Active primero el rastreo de este equipo.');
}

// direccion con la que el equipo llegara al servidor
 $servidor = trim((string)($_GET['servidor'] ?? ''));
if ($servidor === '') {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $servidor = ($https ? 'https' : 'http') . '://' . $host . rtrim((string)BASE_URL, '/');
}
if (!preg_match('~^https?://[A-Za-z0-9.\-:\[\]]+(/[A-Za-z0-9._\-/]*)?$~', $servidor)) {
    Response::validation(['servidor' => 'Dirección del servidor inválida. Ejemplo: http://192.168.1.100/Plataforma-Servicio-TI']);
}
 $servidor = rtrim($servidor, '/');

 $plantilla = <<<'PS1'
<#
  SIGTI - Agente de rastreo  -  Equipo {{CODIGO}}

  INSTALAR (una sola vez, abrir PowerShell como ADMINISTRADOR en la carpeta donde esta este archivo):
      powershell -ExecutionPolicy Bypass -File .\sigti-rastreo-{{CODIGO}}.ps1 -Instalar

  PROBAR AHORA (sin instalar):   powershell -ExecutionPolicy Bypass -File .\sigti-rastreo-{{CODIGO}}.ps1
  DESINSTALAR:                    ... -Desinstalar

  Cada 5 minutos informa al servidor: IP, MAC, nombre del equipo, Wi-Fi y, si Windows tiene la
  Ubicacion activada (Configuracion > Privacidad > Ubicacion), la posicion del equipo.
  Si el equipo se marca como PERDIDO o ROBADO en SIGTI, reporta cada minuto.
  Se ejecuta totalmente oculto: no se ve ninguna ventana de PowerShell ni de CMD en ningun momento.
  Este archivo contiene el token del equipo: no lo comparta.
#>
param([switch]$Instalar, [switch]$Desinstalar)

$Servidor = '{{SERVIDOR}}'
$Token    = '{{TOKEN}}'
$Tarea    = 'SIGTI-Rastreo'
$Carpeta  = Join-Path $env:ProgramData 'SIGTI'
$Destino  = Join-Path $Carpeta 'sigti-rastreo.ps1'
$Lanzador = Join-Path $Carpeta 'sigti-rastreo-oculto.vbs'
try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch {}

if ($Desinstalar) {
    Unregister-ScheduledTask -TaskName $Tarea -Confirm:$false -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $Carpeta -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host 'Agente SIGTI desinstalado.'
    return
}

if ($Instalar) {
    $ErrorActionPreference = 'Stop'
    New-Item -ItemType Directory -Force -Path $Carpeta | Out-Null
    Copy-Item -LiteralPath $PSCommandPath -Destination $Destino -Force

    # Lanzador oculto (VBScript): programar powershell.exe directo con -WindowStyle Hidden a veces igual
    # deja ver un parpadeo de la consola (es un problema conocido de Windows con el Programador de tareas).
    # Lanzandolo a traves de wscript.exe con Run(...,0,...) la ventana nunca llega a aparecer.
    $vbsContenido = @"
Set objShell = CreateObject("WScript.Shell")
objShell.Run "powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File ""$Destino""", 0, False
"@
    Set-Content -LiteralPath $Lanzador -Value $vbsContenido -Encoding ASCII

    $accion  = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument ('//B "{0}"' -f $Lanzador)
    $cada5   = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 3650)
    $alIniciarSesion = New-ScheduledTaskTrigger -AtLogOn
    # 6 minutos de margen: ahora se espera un poco mas (hasta 20s) para conseguir una posicion mas precisa.
    $ajustes = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes 6) -MultipleInstances IgnoreNew -Hidden
    # se ejecuta con el usuario que tenga la sesion abierta (asi Windows puede dar la ubicacion)
    $quien   = New-ScheduledTaskPrincipal -GroupId 'S-1-5-32-545' -RunLevel Limited
    Register-ScheduledTask -TaskName $Tarea -Action $accion -Trigger @($cada5, $alIniciarSesion) -Settings $ajustes -Principal $quien -Force | Out-Null
    Write-Host 'Agente SIGTI instalado: reporta cada 5 minutos, sin mostrar ninguna ventana.'
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Destino
    return
}

$ErrorActionPreference = 'SilentlyContinue'

function Obtener-Datos {
    $d = @{ token = $Token; hostname = $env:COMPUTERNAME }

    $cfg = Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1
    if ($cfg) {
        $d.ip_local = ($cfg.IPv4Address | Select-Object -First 1).IPAddress
        $d.mac      = $cfg.NetAdapter.MacAddress
    }

    # Wi-Fi a la que esta conectado
    $wl = (netsh wlan show interfaces 2>$null) | Out-String
    $ssid = $null; $bssid = $null
    if ($wl -match '(?m)^\s*SSID\s*:\s*(.+?)\s*$')  { $ssid  = $Matches[1] }
    if ($wl -match '(?m)^\s*BSSID\s*:\s*(.+?)\s*$') { $bssid = $Matches[1] }
    if ($ssid) { $d.wifi = $(if ($bssid) { "$ssid ($bssid)" } else { $ssid }) }

    # IP publica (sirve cuando el servidor SIGTI esta dentro de la misma red que el equipo)
    try {
        $ip = ([string](Invoke-RestMethod -Uri 'https://api.ipify.org' -TimeoutSec 6)).Trim()
        if ($ip -match '^\d{1,3}(\.\d{1,3}){3}$') { $d.ip_publica = $ip }
    } catch {}

    # Posicion del equipo (GPS / Wi-Fi de Windows). Apenas queda "Ready" casi siempre da una primera
    # lectura gruesa (de varios cientos de metros a kilometros); si se le da un poco mas de tiempo,
    # Windows la va afinando. Por eso no se toma la primera: se escuchan varias lecturas durante unos
    # segundos mas y se guarda la MAS PRECISA (la de menor precision_m), para que el mapa muestre un
    # radio de error mas chico. Se corta antes si ya se consiguio una lectura buena (<=100 m).
    try {
        Add-Type -AssemblyName System.Device
        $w = New-Object System.Device.Location.GeoCoordinateWatcher([System.Device.Location.GeoPositionAccuracy]::High)
        [void]$w.TryStart($false, [TimeSpan]::FromSeconds(8))
        $n = 0
        while ($w.Status -ne 'Ready' -and $w.Permission -ne 'Denied' -and $n -lt 16) { Start-Sleep -Milliseconds 500; $n++ }

        $mejor = $null
        if ($w.Status -eq 'Ready') {
            $limite = (Get-Date).AddSeconds(20)
            while ((Get-Date) -lt $limite) {
                $p = $w.Position.Location
                if ($p -and -not $p.IsUnknown) {
                    if (-not $mejor -or $p.HorizontalAccuracy -lt $mejor.HorizontalAccuracy) { $mejor = $p }
                    if ($mejor.HorizontalAccuracy -le 100) { break }
                }
                Start-Sleep -Milliseconds 1000
            }
        }
        if ($mejor) {
            $inv = [Globalization.CultureInfo]::InvariantCulture
            $d.lat = $mejor.Latitude.ToString($inv)
            $d.lon = $mejor.Longitude.ToString($inv)
            $d.precision = [int]$mejor.HorizontalAccuracy
        }
        $w.Stop()
    } catch {}
    return $d
}

function Reportar {
    return Invoke-RestMethod -Uri ($Servidor.TrimEnd('/') + '/api/rastreo/checkin.php') -Method Post -Body (Obtener-Datos) -TimeoutSec 25 -UserAgent 'SIGTI-Agente/2'
}

$r = $null
try { $r = Reportar } catch {}

# marcado como perdido/robado: seguir reportando cada minuto durante esta ejecucion
$vueltas = 0
while ($r -and $r.data -and $r.data.perdido -eq 1 -and $vueltas -lt 2) {
    Start-Sleep -Seconds 60
    try { $r = Reportar } catch { break }
    $vueltas++
}
PS1;

 $script = str_replace(['{{SERVIDOR}}', '{{TOKEN}}', '{{CODIGO}}'],
    [$servidor, $config['token_rastreo'], $eq['codigo']], $plantilla);
 $script = "\xEF\xBB\xBF" . str_replace("\n", "\r\n", str_replace("\r\n", "\n", $script));

 $registrado = Auditoria::registrar('actualizar', 'equipos_rastreo_config', $equipoId, null,
    ['agente_descargado' => true], [
        'modulo' => 'equipos', 'submodulo' => 'rastreo',
        'accion_detalle' => 'agente_rastreo_descargado', 'resultado' => 'exito', 'equipo_id' => $equipoId,
    ]);
if (!$registrado) Response::error('No se pudo registrar la descarga en auditoría.', null, 500);

while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="sigti-rastreo-' . preg_replace('/[^A-Za-z0-9_-]/', '', $eq['codigo']) . '.ps1"');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($script));
echo $script;
