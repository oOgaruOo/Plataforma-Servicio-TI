<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// SIGTI - Dashboard: series de los graficos (solo lo que la persona puede ver). Ver core/Dashboard.php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
try {
    Response::ok(Dashboard::graficos());
} catch (Throwable $e) {
    error_log('[SIGTI] dashboard/graficos: ' . $e->getMessage() . ' ' . basename($e->getFile()) . ':' . $e->getLine());
    Response::error('No se pudieron cargar los gráficos.', null, 500);
}
