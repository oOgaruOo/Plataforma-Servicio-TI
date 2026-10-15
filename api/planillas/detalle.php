<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Planillas: boleta (detalle) de una planilla  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('rrhh', 'ver');
    Planillas::asegurar();
    $id = (int)($_GET['id'] ?? 0);
    $d = Planillas::detalle($id);
    if (!$d) Response::error('La planilla ya no existe.', null, 404);
    Response::ok($d);
} catch (Throwable $e) {
    error_log('[SIGTI] planillas/detalle: ' . $e->getMessage());
    Response::error('No se pudo cargar el detalle.', null, 500);
}
