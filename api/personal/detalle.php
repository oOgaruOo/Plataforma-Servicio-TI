<?php
// ============================================================
// SIGTI - Ficha completa de la persona + su checklist
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('personal', 'ver');

 $id = (int)($_GET['id'] ?? 0);
if ($id === 0) Response::error('Persona no especificada.');

 $p = Database::getOne(
    "SELECT p.*, a.nombre AS area,
            CONCAT(j.nombres, ' ', j.apellidos) AS jefe,
            (SELECT COUNT(*) FROM usuarios u WHERE u.personal_id = p.id AND u.estado <> 'inactivo') AS cuentas_sistema
     FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id
     LEFT JOIN personal j ON j.id = p.jefe_id
     WHERE p.id = ?",
    [$id]
);
if (!$p) Response::error('La persona no existe.');

// ---- Checklist de INGRESO (el de cese se mostrara en el Paso 10) ----
 $checklist = Database::get(
    "SELECT pc.id, pc.completado, pc.fecha_completado, pc.observacion,
            pc.responsable_usuario_id, ci.item, ci.responsable AS responsable_definido, ci.obligatorio,
            CONCAT(u.nombre_completo, '') AS responsable_nombre
     FROM personal_checklist pc
     INNER JOIN checklist_items ci ON ci.id = pc.item_id
     INNER JOIN checklist_plantillas cp ON cp.id = ci.plantilla_id AND cp.tipo = 'ingreso'
     LEFT  JOIN usuarios u ON u.id = pc.responsable_usuario_id
     WHERE pc.personal_id = ?
     ORDER BY ci.orden",
    [$id]
);

 $p['checklist'] = $checklist;
 $p['check_ok']    = count(array_filter($checklist, fn($c) => (int)$c['completado'] === 1));
 $p['check_total'] = count($checklist);

Response::ok($p);