<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Vacaciones: tarjetas de resumen  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('rrhh', 'ver');
    Vacaciones::asegurar();
    Response::ok(Vacaciones::resumen());
} catch (Throwable $e) {
    error_log('[SIGTI] vacaciones/resumen: ' . $e->getMessage());
    Response::error('No se pudo cargar el resumen.', null, 500);
}
