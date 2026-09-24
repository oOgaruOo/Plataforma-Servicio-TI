<?php
// ============================================================
// SIGTI - Datos para etiquetas con codigo de barras
// GET ?ids=1,2,3
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('equipos', 'ver');

 $idsRaw = trim((string)($_GET['ids'] ?? ''));
if ($idsRaw === '') Response::ok([]);

 $ids = array_values(array_filter(
    array_map('intval', explode(',', $idsRaw)),
    fn($i) => $i > 0
));
if (!$ids) Response::ok([]);
if (count($ids) > 200) Response::error('Maximo 200 etiquetas por lote.');

 $marcadores = implode(',', array_fill(0, count($ids), '?'));

 $equipos = Database::get(
    "SELECT e.id, e.codigo, e.marca, e.modelo, e.nro_serie, e.imei,
            e.activo_fijo, t.nombre AS tipo
     FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE e.id IN ($marcadores)
     ORDER BY e.codigo",
    $ids
);

Response::ok($equipos);