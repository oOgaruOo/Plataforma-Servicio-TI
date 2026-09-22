<?php
// ============================================================
// SIGTI - Metricas del inventario (mini-tarjetas)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $fTipo = (int)($_GET['tipo'] ?? 0);
 $and   = $fTipo > 0 ? " AND e.tipo_equipo_id = $fTipo" : '';

Response::ok([
    'total'         => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE 1=1 $and"),
    'stock'         => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.estado='en_stock' $and"),
    'asignados'     => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.estado IN ('asignado','en_prestamo') $and"),
    'mantenimiento' => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.estado IN ('en_mantenimiento','en_reparacion_externa','en_revision') $and"),
    'baja'          => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.estado='dado_de_baja' $and"),
    'garantia'      => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.garantia_hasta IS NOT NULL AND e.garantia_hasta >= CURDATE() AND e.garantia_hasta <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND e.estado <> 'dado_de_baja' $and"),
    'valorizado'    => (float) (Database::getValue("SELECT COALESCE(SUM(e.costo),0) FROM equipos e WHERE e.estado <> 'dado_de_baja' $and") ?? 0),
]);