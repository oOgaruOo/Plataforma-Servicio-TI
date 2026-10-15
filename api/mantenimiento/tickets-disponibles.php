<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Tickets abiertos para vincular a mantenimiento
// GET -> [{id, codigo, titulo, equipo_id, equipo_codigo}]
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('mantenimiento', 'ver');

// solo tickets NO vinculados ya a un mantenimiento activo
 $tickets = Database::get(
    "SELECT t.id, t.codigo, t.titulo, t.equipo_id,
            e.codigo AS equipo_codigo, e.marca, e.modelo, te.nombre AS equipo_tipo
     FROM tickets t
     LEFT JOIN equipos e ON e.id = t.equipo_id
     LEFT JOIN tipo_equipos te ON te.id = e.tipo_equipo_id
     WHERE t.estado IN ('abierto','asignado','en_atencion','pendiente_usuario','pendiente_proveedor')
       AND (t.equipo_id IS NOT NULL)
       AND NOT EXISTS (
           SELECT 1 FROM mantenimientos m
           WHERE m.ticket_id = t.id
             AND m.estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')
       )
     ORDER BY t.fecha_creacion DESC LIMIT 50");

Response::ok($tickets);