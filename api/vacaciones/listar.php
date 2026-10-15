<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Vacaciones: lista de solicitudes con DataTables server-side  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('rrhh', 'ver');
    Vacaciones::asegurar();

    $fEstado = (string)($_POST['f_estado'] ?? '');
    $fTexto  = trim((string)($_POST['f_texto'] ?? ''));

    $where = []; $params = [];
    if (in_array($fEstado, ['pendiente', 'aprobada', 'rechazada', 'cancelada'], true)) { $where[] = 'v.estado = ?'; $params[] = $fEstado; }
    if ($fTexto !== '') {
        $where[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)';
        for ($i = 0; $i < 3; $i++) $params[] = '%' . $fTexto . '%';
    }

    $select = "SELECT v.id, v.personal_id, v.fecha_inicio, v.fecha_fin, v.dias, v.estado, v.motivo_rechazo, v.observaciones, v.creado_en,
                      p.dni, p.nombres, p.apellidos, a.nombre AS area
               FROM rrhh_vacaciones v
               INNER JOIN personal p ON p.id = v.personal_id
               LEFT JOIN areas a ON a.id = p.area_id";

    $alias = ['id', 'apellidos', 'fecha_inicio', 'fecha_fin', 'dias', 'estado'];
    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1) $length = 10;
    if ($length > 200) $length = 200;
    $colIdx = (int)($_POST['order'][0]['column'] ?? 2);
    $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc') ? 'ASC' : 'DESC';
    $colOrd = $alias[$colIdx] ?? 'fecha_inicio';

    $baseTotal = $select . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($baseTotal) x", $params);

    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $whereAll = $where; $paramsAll = $params;
    if ($busqueda !== '') {
        $whereAll[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)';
        for ($i = 0; $i < 3; $i++) $paramsAll[] = '%' . $busqueda . '%';
    }
    $baseFull = $select . ($whereAll ? ' WHERE ' . implode(' AND ', $whereAll) : '');
    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($baseFull) x", $paramsAll);
    $filas = Database::get("SELECT * FROM ($baseFull) x ORDER BY $colOrd $dir LIMIT $length OFFSET $start", $paramsAll);

    foreach ($filas as &$f) { [$f['estado_txt'], $f['estado_color']] = Vacaciones::ESTADOS[$f['estado']] ?? [$f['estado'], 'gris']; }
    unset($f);

    Response::datatable($draw, $total, $filtrados, $filas);
} catch (Throwable $e) {
    Response::error('vacaciones/listar: ' . $e->getMessage() . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}
