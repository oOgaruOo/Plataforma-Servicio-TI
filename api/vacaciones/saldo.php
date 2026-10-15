<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Vacaciones: saldo estimado de una persona  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('rrhh', 'ver');
    Vacaciones::asegurar();
    $personalId = (int)($_GET['personal_id'] ?? 0);
    if ($personalId <= 0) Response::validation(['personal_id' => 'Falta la persona.']);
    if (!Database::getValue('SELECT id FROM personal WHERE id = ?', [$personalId])) Response::error('La persona no existe.');
    Response::ok(Vacaciones::saldo($personalId));
} catch (Throwable $e) {
    error_log('[SIGTI] vacaciones/saldo: ' . $e->getMessage());
    Response::error('No se pudo calcular el saldo.', null, 500);
}
