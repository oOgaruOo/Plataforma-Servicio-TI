<?php
// ============================================================
// SIGTI - Catalogo de accesorios seleccionables por familia
// ?familia=computo  ->  accesorios de computo + generales
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $familia = trim((string)($_GET['familia'] ?? ''));
if ($familia === '') Response::error('Familia no especificada.');

 $lista = Database::get(
    "SELECT id, nombre AS texto, familia
     FROM accesorios_tipos
     WHERE activo = 1 AND (familia = ? OR familia = 'general')
     ORDER BY CASE WHEN familia = 'general' THEN 1 ELSE 0 END, nombre",
    [$familia]
);

Response::ok($lista);