<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
if (!Auth::can('tickets', 'ver') && !Auth::can('tickets', 'ver_area') && !Auth::can('tickets', 'ver_propios')) {
    Response::forbidden('No tiene permiso para ver las estadísticas de tickets.');
}

 $hoy = date('Y-m-d');

Response::ok([
    'abiertos_hoy' => (int) Database::getValue(
        "SELECT COUNT(*) FROM tickets WHERE DATE(fecha_creacion) = ? AND estado NOT IN ('cerrado','cancelado')", [$hoy]),
    'activos' => (int) Database::getValue(
        "SELECT COUNT(*) FROM tickets WHERE estado IN ('abierto','asignado','en_atencion','pendiente_usuario','pendiente_proveedor')"),
    'vencidos' => (int) Database::getValue(
        "SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('resuelto','cerrado','cancelado') AND sla_limite < NOW()"),
    'resueltos_mes' => (int) Database::getValue(
        "SELECT COUNT(*) FROM tickets WHERE estado IN ('resuelto','cerrado') AND MONTH(fecha_resolucion) = MONTH(CURDATE()) AND YEAR(fecha_resolucion) = YEAR(CURDATE())"),
    'sin_asignar' => (int) Database::getValue(
        "SELECT COUNT(*) FROM tickets WHERE estado = 'abierto' AND tecnico_usuario_id IS NULL"),
    'mis_tickets' => (int) Database::getValue(
        "SELECT COUNT(*) FROM tickets WHERE tecnico_usuario_id = ? AND estado NOT IN ('cerrado','cancelado')",
        [Auth::userId()]),
]);