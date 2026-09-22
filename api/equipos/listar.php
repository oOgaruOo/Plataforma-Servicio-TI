<?php
// ============================================================
// SIGTI - Equipos: DataTables server-side + filtros + metricas
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('equipos', 'ver');

    $fEstado = $_POST['f_estado'] ?? '';
    $fTipo   = (int)($_POST['f_tipo'] ?? 0);
    $fGarantia = (int)($_POST['f_garantia'] ?? 0);   // 1 = garantía por vencer (90 días)

    $where  = [];
    $params = [];

    $estadosValidos = ['en_stock','asignado','en_prestamo','en_revision','en_mantenimiento',
                       'en_reparacion_externa','obsoleto','dado_de_baja'];
    if (in_array($fEstado, $estadosValidos, true)) {
        $where[]  = 'e.estado = ?';
        $params[] = $fEstado;
    }
    if ($fTipo > 0) {
        $where[]  = 'e.tipo_equipo_id = ?';
        $params[] = $fTipo;
    }
    if ($fGarantia === 1) {
        $where[] = 'e.garantia_hasta IS NOT NULL AND e.garantia_hasta >= CURDATE() '
                 . 'AND e.garantia_hasta <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) '
                 . "AND e.estado <> 'dado_de_baja'";
    }
    $cond = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $base = "SELECT e.id, e.codigo, e.marca, e.modelo, e.nro_serie, e.activo_fijo,
                    e.estado, e.condicion, e.fecha_compra, e.costo, e.garantia_hasta,
                    e.ubicacion, e.especificaciones,
                    t.nombre AS tipo,
                    CONCAT(a.personal_nombres, ' ', a.personal_apellidos) AS asignado_a,
                    a.fecha_entrega AS asignado_desde
             FROM equipos e
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN (
                 SELECT asig.equipo_id, asig.fecha_entrega, asig.estado AS asig_estado,
                        p.nombres AS personal_nombres, p.apellidos AS personal_apellidos
                 FROM asignaciones asig
                 LEFT JOIN personal p ON p.id = asig.personal_id
                 WHERE asig.estado IN ('activa','vencida')
             ) a ON a.equipo_id = e.id" . $cond;

    $alias = ['codigo','tipo','marca','modelo','nro_serie','estado','condicion','asignado_a','garantia_hasta'];
    $busca = ['e.codigo','t.nombre','e.marca','e.modelo','e.nro_serie','e.activo_fijo','e.ubicacion'];

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

    $paramsCon = $params;
    $whereExtra = '';
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $col) {
            $partes[] = "$col LIKE ?";
            $paramsCon[] = '%' . $busqueda . '%';
        }
        $whereExtra = ($cond ? ' AND (' : ' WHERE (') . implode(' OR ', $partes) . ')';
    }

    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($base) x$whereExtra", $paramsCon);

    $filas = Database::get(
        "SELECT * FROM ($base) x$whereExtra ORDER BY $colOrd $dir LIMIT $length OFFSET $start",
        $paramsCon
    );

    // metricas para las mini-tarjetas (mismo filtro de tipo, sin estado)
    $condStats = $fTipo > 0 ? ' WHERE e.tipo_equipo_id = ' . $fTipo : '';
    $stats = [
        'stock'   => (int) Database::getValue("SELECT COUNT(*) FROM equipos e $condStats" . ($fTipo ? " AND e.estado='en_stock'" : " WHERE e.estado='en_stock'")),
        'asignados' => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.estado IN ('asignado','en_prestamo')" . ($fTipo ? " AND e.tipo_equipo_id=$fTipo" : '')),
        'mantenimiento' => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.estado IN ('en_mantenimiento','en_reparacion_externa','en_revision')" . ($fTipo ? " AND e.tipo_equipo_id=$fTipo" : '')),
        'baja'    => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.estado='dado_de_baja'" . ($fTipo ? " AND e.tipo_equipo_id=$fTipo" : '')),
        'garantia' => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE e.garantia_hasta IS NOT NULL AND e.garantia_hasta >= CURDATE() AND e.garantia_hasta <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND e.estado <> 'dado_de_baja'" . ($fTipo ? " AND e.tipo_equipo_id=$fTipo" : '')),
    ];

    // metricas por endpoint aparte (ver api/equipos/stats.php)
    
    $respuesta = Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('equipos/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}
