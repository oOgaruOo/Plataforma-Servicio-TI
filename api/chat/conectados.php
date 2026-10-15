<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

// usuarios "en linea" = ultimo_acceso en los ultimos 5 minutos
 $usuarios = Database::get(
    "SELECT u.id, u.nombre_completo, r.nombre AS rol,
            CASE WHEN u.ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END AS en_linea,
            u.ultimo_acceso
     FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE u.estado = 'activo'
     ORDER BY en_linea DESC, u.nombre_completo");

Response::ok($usuarios);