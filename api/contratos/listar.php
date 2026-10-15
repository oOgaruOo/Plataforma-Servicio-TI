<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Contratos: lista con DataTables server-side  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('rrhh', 'ver');
    Contratos::asegurar();
    Vacaciones::asegurar();

    $fEstado = (string)($_POST['f_estado'] ?? ''); // vigente|por_vencer|vencido|finalizado
    $fTexto  = trim((string)($_POST['f_texto'] ?? ''));

    $where = []; $params = [];
    // 'activos': TODOS los contratos vigentes (c.vigente=1), sin importar si estan por vencer o ya vencidos.
    // Es lo que usa la pestaña "Personal contratado" (un renglon por persona con contrato activo).
    // 'vigente' es una categoria MAS ANGOSTA, solo para el filtro desplegable del Historial: vigente y que
    // ademas falte mas de 30 dias para su fin (o sea indeterminado); no debe usarse para "todo el personal
    // contratado", porque dejaria afuera a quienes estan "por vencer" o "vencidos" pero siguen activos.
    if ($fEstado === 'activos') { $where[] = 'c.vigente = 1'; }
    elseif ($fEstado === 'vigente') { $where[] = 'c.vigente = 1 AND (c.fecha_fin IS NULL OR c.fecha_fin > ?)'; $params[] = date('Y-m-d', strtotime('+30 days')); }
    elseif ($fEstado === 'por_vencer') { $where[] = 'c.vigente = 1 AND c.fecha_fin IS NOT NULL AND c.fecha_fin BETWEEN ? AND ?'; $params[] = date('Y-m-d'); $params[] = date('Y-m-d', strtotime('+30 days')); }
    elseif ($fEstado === 'vencido') { $where[] = 'c.vigente = 1 AND c.fecha_fin IS NOT NULL AND c.fecha_fin < ?'; $params[] = date('Y-m-d'); }
    elseif ($fEstado === 'finalizado') { $where[] = 'c.vigente = 0'; }

    if ($fTexto !== '') {
        $where[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ? OR c.puesto LIKE ?)';
        for ($i = 0; $i < 4; $i++) $params[] = '%' . $fTexto . '%';
    }

    $select = "SELECT c.id, c.personal_id, c.tipo, c.puesto, c.salario, c.fecha_inicio, c.fecha_fin, c.vigente, c.motivo_fin,
                      p.dni, p.nombres, p.apellidos, a.nombre AS area
               FROM personal_contratos c
               INNER JOIN personal p ON p.id = c.personal_id
               LEFT JOIN areas a ON a.id = p.area_id";

    $alias = ['id', 'apellidos', 'tipo', 'puesto', 'fecha_inicio', 'fecha_fin', 'vigente'];
    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1) $length = 10;
    if ($length > 200) $length = 200;
    $colIdx = (int)($_POST['order'][0]['column'] ?? 4);
    $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc') ? 'ASC' : 'DESC';
    $colOrd = $alias[$colIdx] ?? 'fecha_inicio';

    $baseTotal = $select . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($baseTotal) x", $params);

    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $whereAll = $where; $paramsAll = $params;
    if ($busqueda !== '') {
        $whereAll[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ? OR c.puesto LIKE ?)';
        for ($i = 0; $i < 4; $i++) $paramsAll[] = '%' . $busqueda . '%';
    }
    $baseFull = $select . ($whereAll ? ' WHERE ' . implode(' AND ', $whereAll) : '');
    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($baseFull) x", $paramsAll);
    $filas = Database::get("SELECT * FROM ($baseFull) x ORDER BY $colOrd $dir LIMIT $length OFFSET $start", $paramsAll);

    foreach ($filas as &$f) {
        [$f['estado_txt'], $f['estado_color']] = Contratos::estado($f);
        $f['tipo_txt'] = Contratos::TIPOS[$f['tipo']] ?? $f['tipo'];
        $ant = Vacaciones::antiguedad((int) $f['personal_id']);
        $f['antiguedad_txt'] = $ant['texto'] ?? '—';
        $sal = Vacaciones::saldo((int) $f['personal_id']);
        $f['vac_disponible'] = $sal['disponible'];
        $f['vac_acumula'] = $sal['acumula'];
    }
    unset($f);

    Response::datatable($draw, $total, $filtrados, $filas);
} catch (Throwable $e) {
    Response::error('contratos/listar: ' . $e->getMessage() . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}
