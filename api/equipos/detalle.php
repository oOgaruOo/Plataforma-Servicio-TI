<?php
// ============================================================
// SIGTI - Ficha completa del equipo + historial + asignacion activa
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('equipos', 'ver');

 $id = (int)($_GET['id'] ?? 0);
if ($id === 0) Response::error('Equipo no especificado.');

 $e = Database::getOne(
    "SELECT e.*, t.nombre AS tipo
     FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE e.id = ?",
    [$id]
);
if (!$e) Response::error('El equipo no existe.');

// asignacion activa (puede ser a persona o a area)
 $asignacion = Database::getOne(
    "SELECT a.id, a.tipo, a.estado, a.fecha_entrega, a.fecha_devolucion_esperada,
            p.nombres AS p_nombres, p.apellidos AS p_apellidos,
            ar.nombre AS area_nombre
     FROM asignaciones a
     LEFT JOIN personal p ON p.id = a.personal_id
     LEFT JOIN areas   ar ON ar.id = a.area_id
     WHERE a.equipo_id = ? AND a.estado IN ('activa','vencida')
     ORDER BY a.id DESC LIMIT 1",
    [$id]
);

// costos acumulados de mantenimiento (para decision reparar/comprar)
 $costoMant = (float) (Database::getValue(
    "SELECT COALESCE(SUM(costo_total),0) FROM mantenimientos WHERE equipo_id = ? AND estado NOT IN ('cancelado')",
    [$id]) ?? 0);
 $vecesMant = (int) Database::getValue(
    "SELECT COUNT(*) FROM mantenimientos WHERE equipo_id = ?", [$id]);

 $e['accesorios']  = Database::get('SELECT id, nombre, entregado FROM equipo_accesorios WHERE equipo_id = ? ORDER BY id', [$id]);
 $e['licencias']   = Database::get('SELECT id, software, tipo, fecha_inicio, fecha_vencimiento, costo
                                   FROM equipo_licencias WHERE equipo_id = ? ORDER BY fecha_vencimiento IS NULL, fecha_vencimiento', [$id]);
 $e['historial']   = Database::get(
    "SELECT h.evento, h.detalle, h.created_at, CONCAT(u.nombre_completo,'') AS usuario
     FROM equipo_historial h
     LEFT JOIN usuarios u ON u.id = h.usuario_id
     WHERE h.equipo_id = ? ORDER BY h.id DESC LIMIT 50", [$id]);
 $e['asignacion']      = $asignacion;
 $e['costo_mant_total']= $costoMant;
 $e['veces_mant']      = $vecesMant;

Response::ok($e);