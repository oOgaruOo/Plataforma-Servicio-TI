<?php
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('actas', 'generar');

    $fTipo = $_POST['f_tipo'] ?? '';

    $where  = [];
    $params = [];
    if (in_array($fTipo, ['entrega','cambio','devolucion'], true)) {
        $where[]  = 'a.tipo = ?';
        $params[] = $fTipo;
    }
    $cond = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $base = "SELECT a.id, a.codigo, a.tipo, a.fecha, a.firmado, a.token,
                    CONCAT(p.nombres, ' ', p.apellidos) AS colaborador, p.dni,
                    ar.nombre AS area,
                    CONCAT(u.nombre_completo, '') AS responsable
             FROM actas a
             INNER JOIN personal p ON p.id = a.personal_id
             LEFT JOIN areas ar ON ar.id = p.area_id
             LEFT JOIN usuarios u ON u.id = a.responsable_usuario_id" . $cond;

    $alias = ['codigo','tipo','colaborador','area','responsable','fecha'];
    $busca = ['a.codigo','p.nombres','p.apellidos','ar.nombre','u.nombre_completo'];

    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1)   $length = 10;
    if ($length > 200) $length = 200;

    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $colIdx = (int)($_POST['order'][0]['column'] ?? 0);
    $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC';
    $colOrd = $alias[$colIdx] ?? $alias[0];

    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($base) x", $params);

    $searchExtra = '';
    $paramsBusq  = [];
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $col) {
            $partes[] = "$col LIKE ?";
            $paramsBusq[] = '%' . $busqueda . '%';
        }
        $searchExtra = ($cond ? ' AND (' : ' WHERE (') . implode(' OR ', $partes) . ')';
    }
    $baseFull = $base . $searchExtra;

    $paramsCon = array_merge($params, $paramsBusq);
    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($baseFull) x", $paramsCon);

    $filas = Database::get(
        "SELECT * FROM ($baseFull) x ORDER BY $colOrd $dir LIMIT $length OFFSET $start",
        $paramsCon
    );

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('actas/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}