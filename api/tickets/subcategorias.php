<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $categoria = (int)($_GET['categoria'] ?? 0);
if ($categoria === 0) Response::error('Categoria no especificada.');

 $lista = Database::get(
    "SELECT id, nombre AS texto FROM subcategorias
     WHERE categoria_id = ? AND activo = 1 ORDER BY nombre",
    [$categoria]
);

Response::ok($lista);