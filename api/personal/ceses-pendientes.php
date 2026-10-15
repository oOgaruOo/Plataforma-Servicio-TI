<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Ceses proximos + cesados con equipos PENDIENTES
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('personal', 'ver');

// ---- ceses proximos (30 dias) ----
 $proximos = Database::get(
    "SELECT p.id, CONCAT(p.nombres, ' ', p.apellidos) AS nombre, p.dni,
            p.cargo, a.nombre AS area, p.fecha_cese,
            DATEDIFF(p.fecha_cese, CURDATE()) AS dias_restantes,
            (SELECT COUNT(*) FROM asignaciones asig
                WHERE asig.personal_id = p.id AND asig.estado IN ('activa','vencida')) AS equipos,
            (SELECT COUNT(*) FROM usuarios u
                WHERE u.personal_id = p.id AND u.estado = 'activo') AS cuentas
     FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id
     WHERE p.estado IN ('cese_programado','en_proceso_cese')
       AND p.fecha_cese >= CURDATE()
       AND p.fecha_cese <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY p.fecha_cese ASC");

// ---- cesados con equipos PENDIENTES (critico) ----
 $pendientes = Database::get(
    "SELECT p.id, CONCAT(p.nombres, ' ', p.apellidos) AS nombre, p.dni,
            p.fecha_cese, a.nombre AS area,
            COUNT(asig.id) AS equipos_pendientes,
            GROUP_CONCAT(CONCAT(eq.codigo, ' (', t.nombre, ')') SEPARATOR ', ') AS equipos_lista
     FROM personal p
     INNER JOIN asignaciones asig ON asig.personal_id = p.id AND asig.estado IN ('activa','vencida')
     INNER JOIN equipos eq ON eq.id = asig.equipo_id
     INNER JOIN tipo_equipos t ON t.id = eq.tipo_equipo_id
     LEFT JOIN areas a ON a.id = p.area_id
     WHERE p.estado IN ('cesado','en_proceso_cese')
     GROUP BY p.id, nombre, dni, fecha_cese, area
     ORDER BY p.fecha_cese DESC");

Response::ok([
    'proximos'   => $proximos,
    'pendientes' => $pendientes,
    'total_pendientes' => count($pendientes),
]);