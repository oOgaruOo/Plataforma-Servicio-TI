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
        // incluye la FAMILIA para que el formulario de equipos
        // se adapte al tipo seleccionado
        $lista = Database::get(
            "SELECT id, nombre AS texto, familia
             FROM tipo_equipos WHERE activo = 1
             ORDER BY familia, nombre");
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

    case 'proveedores':
        $lista = Database::get(
            "SELECT id, nombre AS texto FROM proveedores WHERE estado = 'activo' ORDER BY nombre");
        break;
    default:
        Response::error('Tipo de lista desconocido.');
        exit;

        case 'prioridades':
        $lista = Database::get(
            "SELECT id, nombre AS texto, color, sla_horas FROM prioridades WHERE activo = 1 ORDER BY nivel");
        break;

    case 'tecnicos':
        $lista = Database::get(
            "SELECT u.id, u.nombre_completo AS texto
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE r.nombre IN ('tecnico','supervisor','admin_ti','super_admin')
               AND u.estado = 'activo'
             ORDER BY u.nombre_completo");
        break;
}

Response::ok($lista);