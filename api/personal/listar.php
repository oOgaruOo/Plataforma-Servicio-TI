<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Personal: DataTables server-side con filtros
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('personal', 'ver');
    Esquema::columna('personal', 'fecha_nacimiento', 'DATE NULL');
    Apellidos::asegurar();
    CodigoUsuario::asegurar();

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
        $permitidos = ['codigo_usuario','dni','nombres','apellidos','cargo','correo_corporativo','correo_personal'];
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

    $select = "SELECT p.id, p.codigo_usuario, p.locker, p.dni, p.nombres, p.apellidos, p.correo_corporativo, p.telefono,
                      p.cargo, p.fecha_ingreso, p.fecha_cese, p.fecha_nacimiento, p.tipo_personal, p.estado,
                      a.nombre AS area,
                      j.nombres AS jefe_nombres, j.apellidos AS jefe_apellidos,
                      (SELECT COUNT(*) FROM personal_checklist pc
                          WHERE pc.personal_id = p.id AND pc.completado = 1) AS check_ok,
                      (SELECT COUNT(*) FROM personal_checklist pc
                          WHERE pc.personal_id = p.id) AS check_total
               FROM personal p
               LEFT JOIN areas a ON a.id = p.area_id
               LEFT JOIN personal j ON j.id = p.jefe_id";

    $alias = ['codigo_usuario','codigo_usuario','dni','apellidos','nombres','area','cargo','fecha_ingreso','tipo_personal','estado','check_ok'];
    $busca = ['p.codigo_usuario','p.dni','p.nombres','p.apellidos','a.nombre','p.cargo'];

    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1)   $length = 10;
    if ($length > 200) $length = 200;

    $colIdx = (int)($_POST['order'][0]['column'] ?? 0);
    $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC';
    $colOrd = $alias[$colIdx] ?? $alias[0];

    // total = con los filtros externos (sin el buscador propio de DataTables)
    $baseTotal = $select . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($baseTotal) x", $params);

    // el buscador de DataTables se suma DENTRO de la consulta, junto a los filtros externos
    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $whereAll  = $where;
    $paramsAll = $params;
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $c) {
            $partes[]    = "$c LIKE ?";
            $paramsAll[] = '%' . $busqueda . '%';
        }
        $whereAll[] = '(' . implode(' OR ', $partes) . ')';
    }
    $baseFull = $select . ($whereAll ? ' WHERE ' . implode(' AND ', $whereAll) : '');

    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($baseFull) x", $paramsAll);

    $filas = Database::get(
        "SELECT * FROM ($baseFull) x ORDER BY $colOrd $dir LIMIT $length OFFSET $start",
        $paramsAll
    );

    // marca de cumpleanos de HOY (se calcula en PHP: respeta 29-feb y no depende del motor de BD)
    $hoy = new DateTimeImmutable('today');
    foreach ($filas as &$f) {
        $f['cumple_hoy'] = (Cumpleanos::esHoy($f['fecha_nacimiento'] ?? null, $hoy)
                            && in_array($f['estado'], ['activo','cese_programado','en_proceso_cese'], true)) ? 1 : 0;
    }
    unset($f);

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('personal/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}
