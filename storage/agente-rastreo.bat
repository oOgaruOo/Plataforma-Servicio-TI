@echo off
REM ============================================================
REM SIGTI - Agente de Rastreo de Equipo (version minima)
REM Reporta IP local, MAC y nombre del equipo. Para recibir tambien Wi-Fi y
REM posicion use el agente .ps1 que se descarga desde
REM Equipos > Seguimiento > Rastreo en vivo > Detalle > Descargar agente.
REM
REM INSTALACION:
REM   1. Copiar este archivo a C:\ProgramData\sigti-rastreo.bat
REM   2. Editar SERVIDOR y TOKEN (el token se ve en el Detalle del equipo > Ver token)
REM   3. Abrir CMD como administrador y ejecutar:
REM      schtasks /create /tn "SIGTI-Rastreo" /tr "C:\ProgramData\sigti-rastreo.bat" /sc minute /mo 5 /ru SYSTEM
REM ============================================================

REM ---- CONFIGURAR ----
set SERVIDOR=http://TU-SERVIDOR/Plataforma-Servicio-TI
set TOKEN=PEGAR_TOKEN_DEL_EQUIPO_AQUI

REM ---- NO EDITAR DE AQUI PARA ABAJO ----
set IP_LOCAL=
set MAC=

REM IP local (funciona en Windows en espanol e ingles)
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4"') do (
    for /f "tokens=* delims= " %%b in ("%%a") do if not defined IP_LOCAL set IP_LOCAL=%%b
)

REM MAC (el servidor la normaliza, con guiones o dos puntos)
for /f "tokens=1 delims=," %%m in ('getmac /fo csv /nh') do if not defined MAC set MAC=%%~m

set HOSTNAME=%COMPUTERNAME%

REM check-in por POST (el token no queda en la URL)
curl -s -m 20 -X POST -d "token=%TOKEN%" -d "ip_local=%IP_LOCAL%" -d "mac=%MAC%" -d "hostname=%HOSTNAME%" "%SERVIDOR%/api/rastreo/checkin.php" >nul 2>&1
