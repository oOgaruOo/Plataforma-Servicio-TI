<?php
// ============================================================
// SIGTI - Personal: DataTables server-side con filtros
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('personal', 'ver');

    // ---- Filtros externos (barra de filtros de la vista) ----
    $fEstado  = $_POST['f_estado']  ?? '';
    $fArea    = (int)($_POST['f_area'] ?? 0);
    $fCampo   = $_POST['f_campo']   ?? '';    // dni|nombres|apellidos|cargo|correo
    $fTexto   = trim((string)($_POST['f_texto'] ?? ''));

    $where  = [];
    $params = [];

    if (in_array($fEstado, ['pre_ingreso','activo','cese_programado','en_proceso_cese','cesado'], true)) {
        $where[]  = 'p.estado = ?';
        $params[] = $fEstado;
    }
    if ($fArea > 0) {
        $where[]  = 'p.area_id = ?';
        $params[] = $fArea;
    }
    if ($fTexto !== '') {
        $permitidos = ['dni','nombres','apellidos','cargo','correo_corporativo','correo_personal'];
        $col        = in_array($fCampo, $permitidos, true) ? $fCampo : 'nombres';
        // busqueda flexible: el texto puede estar en nombres O apellidos
        if ($col === 'nombres') {
            $where[]  = '(p.nombres LIKE ? OR p.apellidos LIKE ?)';
            $params[] = '%' . $fTexto . '%';
            $params[] = '%' . $fTexto . '%';
        } else {
            $where[]  = "p.$col LIKE ?";
            $params[] = '%' . $fTexto . '%';
        }
    }
    $cond = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $base = "SELECT p.id, p.dni, p.nombres, p.apellidos, p.correo_corporativo, p.telefono,
                    p.cargo, p.fecha_ingreso, p.fecha_cese, p.tipo_personal, p.estado,
                    a.nombre AS area,
                    j.nombres AS jefe_nombres, j.apellidos AS jefe_apellidos,
                    (SELECT COUNT(*) FROM personal_checklist pc
                        WHERE pc.personal_id = p.id AND pc.completado = 1) AS check_ok,
                    (SELECT COUNT(*) FROM personal_checklist pc
                        WHERE pc.personal_id = p.id) AS check_total
             FROM personal p
             LEFT JOIN areas a ON a.id = p.area_id
             LEFT JOIN personal j ON j.id = p.jefe_id"
           . $cond;

    $alias = ['dni','apellidos','nombres','area','cargo','fecha_ingreso','tipo_personal','estado'];
    $busca = ['p.dni','p.nombres','p.apellidos','a.nombre','p.cargo'];

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

    // el buscador propio de DataTables se suma a los filtros externos
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $col) {
            $partes[] = "$col LIKE ?";
            $params[] = '%' . $busqueda . '%';
        }
        $extra = ' AND (' . implode(' OR ', $partes) . ')';
        // recalcular params de conteo: los filtros externos van primero
        $paramsFiltrado = array_merge($params);
    } else {
        $extra = '';
        $paramsFiltrado = $params;
    }

    // contar filtrados: filtros externos + buscador DT
    $sqlFiltrado = "SELECT COUNT(*) FROM ($base) x";
    if ($busqueda !== '') {
        $sqlFiltrado .= ' WHERE (' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $busca)) . ')';
        $paramsFiltro = [];
        foreach ($busca as $c) $paramsFiltro[] = '%' . $busqueda . '%';
        // WHERE externo ya esta en $base; el AND interno requiere rearmar:
    }

    // --- Version simple y correcta del conteo filtrado ---
    $whereExtra = '';
    $paramsCon  = $params;               // params de filtros externos
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $col) {
            $partes[] = "$col LIKE ?";
            $paramsCon[] = '%' . $busqueda . '%';
        }
        $whereExtra = ' AND (' . implode(' OR ', $partes) . ')';  // $base ya trae WHERE si hay filtros
        if (!$where) $whereExtra = str_replace(' AND ', ' WHERE ', $whereExtra);
    }

    $filtrados = (int) Database::getValue(
        "SELECT COUNT(*) FROM ($base) x$whereExtra", $paramsCon
    );

    $filas = Database::get(
        "SELECT * FROM ($base) x ORDER BY $colOrd $dir LIMIT $length OFFSET $start",
        $paramsCon
    );

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('personal/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}