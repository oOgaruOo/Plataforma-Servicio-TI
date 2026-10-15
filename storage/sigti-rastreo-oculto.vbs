' @autor Ing. Alejandro Aldo Meneses Aliaga . ORCID 0000-0003-1788-1978 . Codigo de autor: SIGTI-DEV-AMA-2026 . (c) 2026
' ============================================================
' SIGTI - Lanzador oculto del agente de rastreo minimo (agente-rastreo.bat)
' Copie este archivo junto al .bat (por ejemplo, ambos en C:\ProgramData\) y programe
' la tarea apuntando a ESTE .vbs, no al .bat directo (vea las instrucciones dentro del .bat).
' Si copio el .bat a otra ruta, edite la ruta de abajo para que coincida.
' ============================================================
Set objShell = CreateObject("WScript.Shell")
objShell.Run "cmd.exe /c ""C:\ProgramData\sigti-rastreo.bat""", 0, False
