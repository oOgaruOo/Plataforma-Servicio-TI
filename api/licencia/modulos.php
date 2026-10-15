<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Licencia: encender o apagar modulos de esta empresa (POST off[] = claves apagadas)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('configuracion', 'gestionar');
Csrf::validate();

$off = $_POST['off'] ?? [];
if (!is_array($off)) $off = $off === '' ? [] : explode(',', (string)$off);
$antes = Licencia::modulosOff();
Licencia::guardarModulosOff($off);
Auditoria::registrar('actualizar', 'configuracion', 0, ['modulos_off' => $antes], ['modulos_off' => Licencia::modulosOff()], ['modulo' => 'licencia']);
Response::ok(['sistema' => Licencia::resumenCliente(), 'mensaje' => 'Módulos actualizados.'], 'Módulos actualizados.');
