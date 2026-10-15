<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - ENDPOINT DEL MOTOR DE PREVENTIVOS
// La logica vive en core/MotorDiario.php (tambien corre solo,
// una vez al dia, desde bootstrap).
// GET  accion=estado   -> dry-run (que generaria) + ultima ejecucion
// POST accion=ejecutar -> fuerza la ejecucion ahora
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('mantenimiento', 'crear');

 $accion = $_GET['accion'] ?? ($_POST['accion'] ?? '');
if ($accion === '') Response::error('Accion requerida.');

if ($accion === 'estado') {
    Response::ok(MotorDiario::estado());
}

if ($accion !== 'ejecutar') Response::error('Accion invalida.');
Csrf::validate();   // ejecutar modifica datos: exige token CSRF

try {
    $generados = MotorDiario::ejecutar();
    @file_put_contents(STORAGE_PATH . '/.motor_dia', date('Y-m-d'));
} catch (Throwable $e) {
    Response::error('Error del motor: ' . $e->getMessage());
}

 $msg = count($generados)
    ? 'Motor ejecutado: ' . count($generados) . ' mantenimiento(s) automatico(s) generados.'
    : 'Motor ejecutado: nada pendiente por generar.';

Response::ok(['generados' => $generados], $msg);
