<?php
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('asignaciones', 'ver');

    $fEstado = $_POST['f_estado'] ?? '';
    $fTipo   = $_POST['f_tipo'] ?? '';

    $where  = [];
    $params = [];
    if (in_array($fEstado, ['activa','vencida','devuelta','cancelada'], true)) {
        $where[]  = 'a.estado = ?';
        $params[] = $fEstado;
    }
    if (in_array($fTipo, ['permanente','prestamo'], true)) {
        $where[]  = 'a.tipo = ?';
        $params[] = $fTipo;
    }
    $cond = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $base = "SELECT a.id, a.equipo_id, a.personal_id, a.tipo, a.estado,
                    a.fecha_entrega, a.fecha_devolucion_esperada, a.fecha_devolucion_real,
                    a.condicion_entrega, a.condicion_devolucion,
                    e.codigo AS equipo_codigo, e.marca, e.modelo, e.nro_serie,
                    t.nombre AS equipo_tipo,
                    CONCAT(p.nombres, ' ', p.apellidos) AS colaborador, p.dni,
                    ar.nombre AS area
             FROM asignaciones a
             INNER JOIN equipos e ON e.id = a.equipo_id
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN personal p ON p.id = a.personal_id
             LEFT JOIN areas ar ON ar.id = a.area_id" . $cond;

    $alias = ['equipo_codigo','equipo_tipo','colaborador','area','tipo','estado','fecha_entrega','fecha_devolucion_esperada'];
    $busca = ['a.id','e.codigo','e.marca','e.modelo','t.nombre','p.nombres','p.apellidos','ar.nombre'];

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

    $hoy = date('Y-m-d');
    foreach ($filas as &$f) {
        $f['vencido'] = ($f['estado'] === 'activa' && $f['tipo'] === 'prestamo'
            && $f['fecha_devolucion_esperada'] && $f['fecha_devolucion_esperada'] < $hoy) ? 1 : 0;
    }
    unset($f);

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('asignaciones/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}