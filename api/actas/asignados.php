<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $pid = (int)($_GET['personal'] ?? 0);
if ($pid === 0) Response::ok([]);

 $lista = Database::get(
    "SELECT e.id, e.codigo, e.marca, e.modelo, e.nro_serie, e.imei,
            t.nombre AS tipo
     FROM asignaciones a
     INNER JOIN equipos e ON e.id = a.equipo_id
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE a.personal_id = ? AND a.estado IN ('activa','vencida')
     ORDER BY t.nombre", [$pid]);

Response::ok($lista);