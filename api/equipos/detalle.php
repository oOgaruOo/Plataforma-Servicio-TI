<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Ficha completa del equipo (incluye familia)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('equipos', 'ver');
Destinos::asegurar();

 $id = (int)($_GET['id'] ?? 0);
if ($id === 0) Response::error('Equipo no especificado.');

 $e = Database::getOne(
    "SELECT e.*, t.nombre AS tipo, t.familia
     FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE e.id = ?",
    [$id]
);
if (!$e) Response::error('El equipo no existe.');

 // asignacion vigente: a una persona, un area, un proyecto o una obra
 $asignacion = Database::getOne(
    "SELECT a.id, a.tipo, a.estado, a.fecha_entrega, a.fecha_devolucion_esperada,
            a.destino_tipo, a.destino_detalle, a.proyecto_id,
            " . Destinos::SQL_NOMBRE . " AS destino_nombre,
            dp.nombres AS p_nombres, dp.apellidos AS p_apellidos, dp.dni AS p_dni,
            da.nombre AS area_nombre,
            dy.codigo AS destino_codigo, dy.ubicacion AS destino_ubicacion, dy.cliente AS destino_cliente,
            CONCAT(dr.nombres, ' ', dr.apellidos) AS responsable
     FROM asignaciones a" . Destinos::SQL_JOIN . "
     WHERE a.equipo_id = ? AND a.estado IN ('activa','vencida')
     ORDER BY a.id DESC LIMIT 1",
    [$id]
);

 // quienes lo han tenido (las ultimas 15 asignaciones, ya cerradas o no)
 $e_asignaciones = Database::get(
    "SELECT a.id, a.tipo, a.estado, a.fecha_entrega, a.fecha_devolucion_real, a.condicion_devolucion,
            a.destino_tipo, " . Destinos::SQL_NOMBRE . " AS destino_nombre,
            CONCAT(dr.nombres, ' ', dr.apellidos) AS responsable
     FROM asignaciones a" . Destinos::SQL_JOIN . "
     WHERE a.equipo_id = ? ORDER BY a.id DESC LIMIT 15", [$id]);

 $costoMant = (float) (Database::getValue(
    "SELECT COALESCE(SUM(costo_total),0) FROM mantenimientos WHERE equipo_id = ? AND estado NOT IN ('cancelado')",
    [$id]) ?? 0);
 $vecesMant = (int) Database::getValue(
    "SELECT COUNT(*) FROM mantenimientos WHERE equipo_id = ?", [$id]);

 $e['accesorios']  = Database::get('SELECT id, nombre, entregado FROM equipo_accesorios WHERE equipo_id = ? ORDER BY id', [$id]);
 $e['licencias']   = Database::get('SELECT id, software, tipo, fecha_inicio, fecha_vencimiento, costo
                                   FROM equipo_licencias WHERE equipo_id = ? ORDER BY fecha_vencimiento IS NULL, fecha_vencimiento', [$id]);
 // software instalado (modulo Software): solo si la persona puede verlo
 $e['software'] = [];
 $e['software_ver'] = 0;
try {
    if (Auth::can('software', 'ver')) {
        Software::asegurar();
        $e['software_ver'] = 1;
        $hoySw = date('Y-m-d');
        $e['software'] = Database::get(
            "SELECT es.id, s.nombre, s.autorizacion, s.requiere_licencia, es.version, es.licencia_id,
                    l.fecha_vencimiento AS lic_vence
             FROM equipo_software es
             INNER JOIN software_catalogo s ON s.id = es.software_id
             LEFT JOIN software_licencias l ON l.id = es.licencia_id
             WHERE es.equipo_id = ? AND es.estado = 'instalado' ORDER BY s.nombre", [$id]);
        foreach ($e['software'] as &$sw) {
            $sw['alertas'] = array_values(array_filter([
                $sw['autorizacion'] === 'prohibido' ? 'No autorizado' : null,
                ((int)$sw['requiere_licencia'] === 1 && !$sw['licencia_id']) ? 'Sin licencia' : null,
                ($sw['lic_vence'] && $sw['lic_vence'] < $hoySw) ? 'Licencia vencida' : null,
            ]));
        }
        unset($sw);
    }
} catch (Throwable $exSw) { /* informativo */ }
 $e['historial']   = Database::get(
    "SELECT h.evento, h.detalle, h.created_at, CONCAT(u.nombre_completo,'') AS usuario
     FROM equipo_historial h
     LEFT JOIN usuarios u ON u.id = h.usuario_id
     WHERE h.equipo_id = ? ORDER BY h.id DESC LIMIT 50", [$id]);
 $e['asignacion']       = $asignacion;
 $e['asignaciones']     = $e_asignaciones;
 $e['costo_mant_total'] = $costoMant;
 $e['vecesMant']        = $vecesMant;
 // mantenimiento que retiene al equipo (en taller / con el proveedor) y estado de su ticket
 $e['mantenimiento_abierto'] = null;
try {
    $e['mantenimiento_abierto'] = Database::getOne(
        "SELECT m.id, m.codigo, m.estado, tk.codigo AS ticket_codigo, tk.estado AS ticket_estado
         FROM mantenimientos m LEFT JOIN tickets tk ON tk.id = m.ticket_id
         WHERE m.equipo_id = ? AND m.estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')
         ORDER BY m.id DESC LIMIT 1", [$id]) ?: null;
} catch (Throwable $ex) { /* informativo */ }

Response::ok($e);