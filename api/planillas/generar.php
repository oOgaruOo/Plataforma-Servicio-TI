<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Planillas: generar (o recalcular) el borrador de un periodo  (rrhh.gestionar)
//   POST periodo = YYYY-MM
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', null, 405);
    Csrf::validate();
    Auth::requirePermission('rrhh', 'gestionar');
    Planillas::asegurar();
    Contratos::asegurar();

    $periodo = (string)($_POST['periodo'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) Response::validation(['periodo' => 'Periodo invalido (use AAAA-MM).']);

    $uid = Auth::userId();
    $r = Planillas::generar($periodo, $uid);
    Auditoria::registrar('actualizar', 'rrhh_planillas', null, null, $r, ['modulo' => 'rrhh', 'submodulo' => 'planillas', 'accion_detalle' => 'generar_' . $periodo]);
    $msg = $r['generadas'] . ' planilla(s) generada(s) para ' . $periodo . '.';
    if ($r['omitidas_sin_contrato']) $msg .= ' ' . $r['omitidas_sin_contrato'] . ' persona(s) omitida(s) por no tener contrato vigente.';
    Response::ok($r, $msg);
} catch (Throwable $e) {
    error_log('[SIGTI] planillas/generar: ' . $e->getMessage());
    Response::error('No se pudo generar la planilla.', null, 500);
}
