<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Vacaciones: personal que ya puede salir de vacaciones (saldo >= umbral)  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('rrhh', 'ver');
    Vacaciones::asegurar();
    Response::ok(['umbral' => Vacaciones::UMBRAL_SUGERENCIA, 'items' => Vacaciones::sugeridos()]);
} catch (Throwable $e) {
    error_log('[SIGTI] vacaciones/sugeridos: ' . $e->getMessage());
    Response::error('No se pudo cargar la lista de sugeridos.', null, 500);
}
