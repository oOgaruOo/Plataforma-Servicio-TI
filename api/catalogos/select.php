<?php
// ============================================================
// SIGTI - Listas simples (id/texto) para los <select>
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $tipo  = trim((string)($_GET['tipo'] ?? ''));
 $lista = null;

switch ($tipo) {

    case 'areas':
        $lista = Database::get(
            "SELECT id, nombre AS texto FROM areas WHERE estado = 'activo' ORDER BY nombre");
        break;

    case 'categorias':
        $lista = Database::get(
            "SELECT id, nombre AS texto FROM categorias WHERE activo = 1 ORDER BY nombre");
        break;

    case 'tipos':
        $lista = Database::get(
            "SELECT id, nombre AS texto FROM tipo_equipos WHERE activo = 1 ORDER BY nombre");
        break;

    case 'roles':
        $lista = Database::get(
            "SELECT id, nombre AS texto FROM roles ORDER BY id");
        break;

    case 'personal':
        $lista = Database::get(
            "SELECT id, CONCAT(nombres, ' ', apellidos) AS texto, dni, estado
             FROM personal
             WHERE estado IN ('pre_ingreso','activo','cese_programado')
             ORDER BY apellidos, nombres");
        break;

    default:
        Response::error('Tipo de lista desconocido.');
        exit;
}

Response::ok($lista);