<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// Widget "Para atención": lo que TIENE que atender este usuario
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

// tickets a mi cargo (abiertos)
 $mios = Database::get(
    "SELECT t.id, t.codigo, t.titulo, t.estado, t.sla_limite, t.prioridad_id,
            p.nombre AS prioridad, p.color
     FROM tickets t INNER JOIN prioridades p ON p.id = t.prioridad_id
     WHERE t.tecnico_usuario_id = ? AND t.estado NOT IN ('cerrado','cancelado')
     ORDER BY t.sla_limite ASC LIMIT 10", [Auth::userId()]);

// sin asignar (solo si puede asignar)
 $sinAsignar = [];
if (Auth::can('tickets', 'ver')) {
    $sinAsignar = Database::get(
        "SELECT t.id, t.codigo, t.titulo, t.sla_limite, p.nombre AS prioridad, p.color
         FROM tickets t INNER JOIN prioridades p ON p.id = t.prioridad_id
         WHERE t.estado = 'abierto' AND t.tecnico_usuario_id IS NULL
         ORDER BY t.sla_limite ASC LIMIT 5");
}

// SLA vencidos (solo de los tickets que esta persona puede ver)
 $vencidos = 0;
 $alc = Dashboard::alcanceTickets();
if ($alc) {
    $vencidos = (int) Database::getValue(
        "SELECT COUNT(*) FROM tickets t
         WHERE t.estado NOT IN ('resuelto','cerrado','cancelado') AND t.sla_limite < ?" . $alc[1],
        array_merge([date('Y-m-d H:i:s')], $alc[2]));
}

// mantenimientos activos mios
 $mmts = [];
if (Auth::can('mantenimiento', 'ver')) {
    $cond = Auth::can('mantenimiento', 'editar')
        ? " AND (m.tecnico_usuario_id = ? OR m.tecnico_usuario_id IS NULL) "
        : " AND m.tecnico_usuario_id = ? ";
    $mmts = Database::get(
        "SELECT m.codigo, m.estado, m.tipo, e.codigo AS equipo, e.marca
         FROM mantenimientos m
         INNER JOIN equipos e ON e.id = m.equipo_id
         WHERE m.estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')" .
         $cond . " ORDER BY m.id ASC LIMIT 8", [Auth::userId()]);
}

Response::ok([
    'mios' => $mios,
    'sin_asignar' => $sinAsignar,
    'sla_vencidos' => $vencidos,
    'mantenimientos' => $mmts,
]);