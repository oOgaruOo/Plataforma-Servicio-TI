<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Planillas: lista de un periodo con DataTables server-side  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('rrhh', 'ver');
    Planillas::asegurar();

    $periodo = (string)($_POST['periodo'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) Response::validation(['periodo' => 'Periodo invalido.']);
    $fTexto = trim((string)($_POST['f_texto'] ?? ''));

    $where = ['pl.periodo = ?']; $params = [$periodo];
    if ($fTexto !== '') {
        $where[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)';
        for ($i = 0; $i < 3; $i++) $params[] = '%' . $fTexto . '%';
    }

    $select = "SELECT pl.id, pl.personal_id, pl.sueldo_base, pl.total_ingresos, pl.total_descuentos, pl.neto, pl.estado,
                      p.dni, p.nombres, p.apellidos, a.nombre AS area
               FROM rrhh_planillas pl
               INNER JOIN personal p ON p.id = pl.personal_id
               LEFT JOIN areas a ON a.id = p.area_id";

    $alias = ['id', 'apellidos', 'sueldo_base', 'total_ingresos', 'total_descuentos', 'neto', 'estado'];
    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1) $length = 10;
    if ($length > 200) $length = 200;
    $colIdx = (int)($_POST['order'][0]['column'] ?? 1);
    $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC';
    $colOrd = $alias[$colIdx] ?? 'apellidos';

    $baseTotal = $select . ' WHERE ' . implode(' AND ', $where);
    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($baseTotal) x", $params);

    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $whereAll = $where; $paramsAll = $params;
    if ($busqueda !== '') {
        $whereAll[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)';
        for ($i = 0; $i < 3; $i++) $paramsAll[] = '%' . $busqueda . '%';
    }
    $baseFull = $select . ' WHERE ' . implode(' AND ', $whereAll);
    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($baseFull) x", $paramsAll);
    $filas = Database::get("SELECT * FROM ($baseFull) x ORDER BY $colOrd $dir LIMIT $length OFFSET $start", $paramsAll);

    Response::datatable($draw, $total, $filtrados, $filas);
} catch (Throwable $e) {
    Response::error('planillas/listar: ' . $e->getMessage() . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}
