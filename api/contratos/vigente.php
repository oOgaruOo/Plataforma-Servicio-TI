<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Contratos: contrato vigente de una persona (para abrir "Renovar" con los datos actuales)  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('rrhh', 'ver');
    Contratos::asegurar();
    $personalId = (int)($_GET['personal_id'] ?? 0);
    if ($personalId <= 0) Response::validation(['personal_id' => 'Falta la persona.']);
    Response::ok(['vigente' => Contratos::vigenteDe($personalId)]);
} catch (Throwable $e) {
    error_log('[SIGTI] contratos/vigente: ' . $e->getMessage());
    Response::error('No se pudo consultar el contrato vigente.', null, 500);
}
