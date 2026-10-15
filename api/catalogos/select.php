<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Listas simples (id/texto) para los <select>
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $tipo  = trim((string)($_GET['tipo'] ?? ''));
 $lista = null;

switch ($tipo) {

    case 'areas':
        CodigoUsuario::asegurarAreas();      // 'codigo' = prefijo del codigo de usuario del area
        $lista = Database::get(
            "SELECT id, nombre AS texto, codigo FROM areas WHERE estado = 'activo' ORDER BY nombre");
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
        Esquema::columna('roles', 'area_id', 'TINYINT UNSIGNED NULL');   // roles.area_id
        $lista = Database::get(
            "SELECT r.id, r.nombre AS texto, r.area_id, r.descripcion, a.nombre AS area
             FROM roles r LEFT JOIN areas a ON a.id = r.area_id ORDER BY r.id");
        break;

    case 'proyectos':
        // ?clase=proyecto|obra (opcional). Solo activos; incluye el responsable por defecto.
        Destinos::asegurar();
        $clase = (string)($_GET['clase'] ?? '');
        $sql = "SELECT p.id, CONCAT(p.codigo, ' — ', p.nombre) AS texto, p.codigo, p.nombre, p.tipo,
                       p.cliente, p.ubicacion, p.responsable_id, p.fecha_inicio, p.fecha_fin
                FROM proyectos p WHERE p.estado = 'activo'";
        $par = [];
        if ($clase === 'proyecto' || $clase === 'obra') { $sql .= ' AND p.tipo = ?'; $par[] = $clase; }
        $lista = Database::get($sql . ' ORDER BY p.tipo, p.nombre', $par);
        break;

    case 'personal':
        $lista = Database::get(
            "SELECT p.id, CONCAT(p.nombres, ' ', p.apellidos) AS texto, p.dni, p.estado, p.area_id,
                    p.correo_corporativo, p.correo_personal, p.cargo, a.nombre AS area
             FROM personal p LEFT JOIN areas a ON a.id = p.area_id
             WHERE p.estado IN ('pre_ingreso','activo','cese_programado')
             ORDER BY p.apellidos, p.nombres");
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