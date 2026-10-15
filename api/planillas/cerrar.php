<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Planillas: cerrar (o reabrir) un periodo  (rrhh.gestionar)
//   POST accion = cerrar | reabrir
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', null, 405);
    Csrf::validate();
    Auth::requirePermission('rrhh', 'gestionar');
    Planillas::asegurar();

    $accion = (string)($_POST['accion'] ?? 'cerrar');
    $uid = Auth::userId();

    if ($accion === 'cerrar') {
        $periodo = (string)($_POST['periodo'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) Response::validation(['periodo' => 'Periodo invalido.']);
        $n = Planillas::cerrar($periodo, $uid);
        if ($n === 0) Response::error('No hay planillas en borrador para ese periodo.');
        Auditoria::registrar('cambiar_estado', 'rrhh_planillas', null, null, ['periodo' => $periodo, 'cerradas' => $n],
            ['modulo' => 'rrhh', 'submodulo' => 'planillas', 'accion_detalle' => 'cerrar_periodo']);
        Response::ok(['cerradas' => $n], $n . ' planilla(s) cerrada(s) para ' . $periodo . '.');

    } elseif ($accion === 'reabrir') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT * FROM rrhh_planillas WHERE id = ?', [$id]);
        if (!$antes) Response::error('La planilla ya no existe.');
        if (!Planillas::reabrir($id)) Response::error('Solo se reabre una planilla Cerrada.');
        Auditoria::registrar('cambiar_estado', 'rrhh_planillas', $id, $antes, ['estado' => 'borrador'], ['modulo' => 'rrhh', 'submodulo' => 'planillas', 'accion_detalle' => 'reabrir']);
        Response::ok(null, 'Planilla reabierta para editar.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] planillas/cerrar: ' . $e->getMessage());
    Response::error('No se pudo completar la operación.', null, 500);
}
