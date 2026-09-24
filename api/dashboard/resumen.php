<?php
// ============================================================
// SIGTI - Resumen del dashboard (datos reales en vivo)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $personal = [
    'total'           => (int) Database::getValue("SELECT COUNT(*) FROM personal"),
    'activo'          => (int) Database::getValue("SELECT COUNT(*) FROM personal WHERE estado = 'activo'"),
    'pre_ingreso'     => (int) Database::getValue("SELECT COUNT(*) FROM personal WHERE estado = 'pre_ingreso'"),
    'cese_programado' => (int) Database::getValue("SELECT COUNT(*) FROM personal WHERE estado = 'cese_programado'"),
    'cesado'          => (int) Database::getValue("SELECT COUNT(*) FROM personal WHERE estado = 'cesado'"),
];

 $equipos = [
    'total'         => (int) Database::getValue("SELECT COUNT(*) FROM equipos"),
    'stock'         => (int) Database::getValue("SELECT COUNT(*) FROM equipos WHERE estado = 'en_stock'"),
    'asignados'     => (int) Database::getValue("SELECT COUNT(*) FROM equipos WHERE estado IN ('asignado','en_prestamo')"),
    'mantenimiento' => (int) Database::getValue("SELECT COUNT(*) FROM equipos WHERE estado IN ('en_mantenimiento','en_reparacion_externa','en_revision')"),
    'baja'          => (int) Database::getValue("SELECT COUNT(*) FROM equipos WHERE estado = 'dado_de_baja'"),
    'valorizado'    => (float) (Database::getValue("SELECT COALESCE(SUM(costo),0) FROM equipos WHERE estado <> 'dado_de_baja'") ?? 0),
];

// Items de onboarding pendientes (personal no cesado)
 $checkPend = (int) Database::getValue(
    "SELECT COUNT(*) FROM personal_checklist pc
     INNER JOIN personal p ON p.id = pc.personal_id
     WHERE pc.completado = 0 AND p.estado <> 'cesado'");

// Personal con onboarding incompleto
 $onboarding = (int) Database::getValue(
    "SELECT COUNT(DISTINCT p.id) FROM personal p
     INNER JOIN personal_checklist pc ON pc.personal_id = p.id
     WHERE pc.completado = 0 AND p.estado IN ('pre_ingreso','activo')");

// Ceses proximos (30 dias)
 $ceses = Database::get(
    "SELECT CONCAT(nombres, ' ', apellidos) AS nombre, fecha_cese, estado
     FROM personal
     WHERE fecha_cese IS NOT NULL
       AND fecha_cese >= CURDATE()
       AND fecha_cese <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       AND estado IN ('activo','cese_programado','en_proceso_cese')
     ORDER BY fecha_cese ASC
     LIMIT 6");

Response::ok([
    'personal'      => $personal,
    'equipos'       => $equipos,
    'check_pend'    => $checkPend,
    'onboarding'    => $onboarding,
    'ceses_prox'    => count($ceses),
    'ceses'         => $ceses,
    'generado'      => date('d/m/Y H:i:s'),
]);