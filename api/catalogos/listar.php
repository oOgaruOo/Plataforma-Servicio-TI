<?php
// ============================================================
// SIGTI - DataTables server-side para todos los catalogos
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('configuracion', 'gestionar');

    $tipo = $_POST['tipo'] ?? '';

    $CONFIG = [
        'areas' => [
            'sql'   => 'SELECT id, nombre, descripcion, estado FROM areas',
            'alias' => ['nombre', 'descripcion', 'estado'],
            'busca' => ['nombre', 'descripcion'],
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
        'proveedores' => [
            'sql'   => 'SELECT id, ruc, nombre, contacto, telefono, correo, especialidad, estado
                        FROM proveedores',
            'alias' => ['ruc', 'nombre', 'contacto', 'telefono', 'correo', 'especialidad', 'estado'],
            'busca' => ['ruc', 'nombre', 'contacto', 'telefono', 'correo', 'especialidad'],
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