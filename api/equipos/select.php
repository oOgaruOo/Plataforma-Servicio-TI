<?php
// ============================================================
// SIGTI - Equipos para <select> de otros modulos
// ?modo=disponibles  -> en_stock (para asignar)
// ?modo=activos      -> no dados de baja (para tickets/mantenimiento)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $modo = $_GET['modo'] ?? 'disponibles';

if ($modo === 'disponibles') {
    $lista = Database::get(
        "SELECT e.id, CONCAT(e.codigo, ' — ', t.nombre, ' ', e.marca, ' ', e.modelo,
             COALESCE(CONCAT(' (S/N ', e.nro_serie, ')'), '')) AS texto
         FROM equipos e
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         WHERE e.estado = 'en_stock' AND e.condicion IN ('nuevo','bueno','regular')
         ORDER BY t.nombre, e.marca");
} elseif ($modo === 'activos') {
    $lista = Database::get(
        "SELECT e.id, CONCAT(e.codigo, ' — ', t.nombre, ' ', e.marca, ' ', e.modelo) AS texto, e.estado
         FROM equipos e
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         WHERE e.estado <> 'dado_de_baja'
         ORDER BY e.codigo DESC");
} else {
    Response::error('Modo desconocido.');
    exit;
}

Response::ok($lista);