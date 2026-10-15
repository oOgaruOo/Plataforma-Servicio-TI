<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Dashboard: indicadores y observaciones segun los permisos de quien consulta.
// Los calculos estan en core/Dashboard.php
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
try {
    Response::ok(Dashboard::indicadores());
} catch (Throwable $e) {
    error_log('[SIGTI] dashboard/indicadores: ' . $e->getMessage() . ' ' . basename($e->getFile()) . ':' . $e->getLine());
    Response::error('No se pudo cargar el panel.', null, 500);
}
