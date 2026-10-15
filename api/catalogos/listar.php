<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - DataTables server-side para todos los catalogos
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('configuracion', 'gestionar');

    $tipo = $_POST['tipo'] ?? '';
    if ($tipo === 'areas') CodigoUsuario::asegurarAreas();
    if ($tipo === 'proyectos') Destinos::asegurar();
    if ($tipo === 'proveedores') {
        Esquema::columna('proveedores', 'tipo_documento', "VARCHAR(3) NOT NULL DEFAULT 'ruc'");
        Esquema::columna('proveedores', 'direccion', 'VARCHAR(200) NULL');
    }

    $CONFIG = [
        'areas' => [
            'sql'   => 'SELECT id, nombre, codigo, descripcion, estado FROM areas',
            'alias' => ['nombre', 'descripcion', 'estado'],
            'busca' => ['nombre', 'codigo', 'descripcion'],
        ],
        'tipos' => [
            'sql'   => 'SELECT id, nombre, activo FROM tipo_equipos',
            'alias' => ['nombre', 'activo'],
            'busca' => ['nombre'],
        ],
        'categorias' => [
            'sql'   => 'SELECT id, nombre, descripcion, activo FROM categorias',
            'alias' => ['nombre', 'descripcion', 'activo'],
            'busca' => ['nombre', 'descripcion'],
        ],
        'subcategorias' => [
            'sql'   => 'SELECT s.id, s.categoria_id, s.nombre, c.nombre AS categoria, s.activo
                        FROM subcategorias s
                        INNER JOIN categorias c ON c.id = s.categoria_id',
            'alias' => ['nombre', 'categoria', 'activo'],
            'busca' => ['nombre', 'categoria'],
        ],
        'prioridades' => [
            'sql'   => 'SELECT id, nombre, nivel, color, sla_horas, activo FROM prioridades',
            'alias' => ['nombre', 'nivel', 'color', 'sla_horas', 'activo'],
            'busca' => ['nombre'],
        ],
        'proyectos' => [
            // equipos = cuantos equipos TI tiene hoy asignados (activa / vencida)
            'sql'   => "SELECT p.id, p.codigo, p.nombre, p.tipo, p.tipo AS clase, p.cliente, p.ubicacion, p.responsable_id,
                               p.fecha_inicio, p.fecha_fin, p.estado,
                               (SELECT CONCAT(r.nombres, ' ', r.apellidos) FROM personal r WHERE r.id = p.responsable_id) AS responsable,
                               (SELECT COUNT(*) FROM asignaciones a WHERE a.proyecto_id = p.id
                                   AND a.estado IN ('activa','vencida')) AS equipos
                        FROM proyectos p",
            'alias' => ['codigo', 'nombre', 'tipo', 'ubicacion', 'responsable', 'equipos', 'estado'],
            'busca' => ['codigo', 'nombre', 'cliente', 'ubicacion', 'responsable'],
        ],
        'proveedores' => [
            'sql'   => 'SELECT id, tipo_documento, ruc, nombre, direccion, contacto, telefono, correo, especialidad, estado
                        FROM proveedores',
            'alias' => ['ruc', 'nombre', 'contacto', 'telefono', 'correo', 'especialidad', 'estado'],
            'busca' => ['ruc', 'nombre', 'direccion', 'contacto', 'telefono', 'correo', 'especialidad'],
        ],
    ];

    if (!isset($CONFIG[$tipo])) Response::error('Catalogo desconocido.');
    $c = $CONFIG[$tipo];

    $draw    = (int)($_POST['draw'] ?? 0);
    $start   = max(0, (int)($_POST['start'] ?? 0));
    $length  = (int)($_POST['length'] ?? 10);
    if ($length < 1)   $length = 10;
    if ($length > 200) $length = 200;

    $busqueda = trim((string)($_POST['search']['value'] ?? ''));

    $alias  = $c['alias'];
    $colIdx = (int)($_POST['order'][0]['column'] ?? 0);
    $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC';
    $colOrd = $alias[$colIdx] ?? $alias[0];

    $base  = $c['sql'];
    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($base) x");

    $cond   = '';
    $params = [];
    if ($busqueda !== '') {
        $partes = [];
        foreach ($c['busca'] as $col) {
            $partes[] = "$col LIKE ?";
            $params[] = '%' . $busqueda . '%';
        }
        $cond = ' WHERE (' . implode(' OR ', $partes) . ')';
    }

    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($base) x$cond", $params);

    $filas = Database::get(
        "SELECT * FROM ($base) x$cond ORDER BY $colOrd $dir LIMIT $length OFFSET $start",
        $params
    );

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error(
        'listar.php: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine()
    );
}