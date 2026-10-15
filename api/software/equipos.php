<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: control por equipo (DataTables, servidor)
// Filtros (POST): f_tipo f_familia f_cobertura(con|sin) f_alerta(1) f_verif(pendiente)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('software', 'ver');
    Destinos::asegurar();
    Software::asegurar();

    $hoy = date('Y-m-d');
    $corte = date('Y-m-d H:i:s', strtotime($hoy . ' -' . Software::DIAS_VERIFICACION . ' days'));
    $where = ["e.estado <> 'dado_de_baja'"]; $par = [];

    if ((int)($_POST['f_tipo'] ?? 0) > 0) { $where[] = 'e.tipo_equipo_id = ?'; $par[] = (int)$_POST['f_tipo']; }
    $fam = (string)($_POST['f_familia'] ?? '');
    if (preg_match('/^[a-z_]{1,20}$/', $fam)) { $where[] = 't.familia = ?'; $par[] = $fam; }

    $nSw = "(SELECT COUNT(*) FROM equipo_software es WHERE es.equipo_id = e.id AND es.estado = 'instalado')";
    $nAl = "(SELECT COUNT(*) FROM equipo_software es
             INNER JOIN software_catalogo s ON s.id = es.software_id
             LEFT JOIN software_licencias l ON l.id = es.licencia_id
             WHERE es.equipo_id = e.id AND es.estado = 'instalado'
               AND (s.autorizacion = 'prohibido'
                    OR (s.requiere_licencia = 1 AND es.licencia_id IS NULL)
                    OR (l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento < '$hoy')))";

    $cob = (string)($_POST['f_cobertura'] ?? '');
    if ($cob === 'con') $where[] = "$nSw > 0";
    elseif ($cob === 'sin') $where[] = "$nSw = 0";
    if (!empty($_POST['f_alerta']) && $_POST['f_alerta'] !== '0') $where[] = "$nAl > 0";
    if (($_POST['f_verif'] ?? '') === 'pendiente') {
        $where[] = "$nSw > 0 AND (e.software_verificado_en IS NULL OR e.software_verificado_en < ?)";
        $par[] = $corte;
    }

    $base = "SELECT e.id, e.codigo, e.marca, e.modelo, e.estado, e.software_verificado_en,
                    t.nombre AS tipo, t.familia,
                    $nSw AS n_software, $nAl AS n_alertas,
                    " . Destinos::SQL_NOMBRE . " AS asignado_a
             FROM equipos e
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN asignaciones a ON a.id = (
                 SELECT MAX(av.id) FROM asignaciones av WHERE av.equipo_id = e.id AND av.estado IN ('activa','vencida')
             )" . Destinos::SQL_JOIN;
    $alias = ['codigo', 'tipo', 'marca', 'asignado_a', 'n_software', 'n_alertas', 'software_verificado_en'];
    $busca = ['e.codigo', 't.nombre', 'e.marca', 'e.modelo', 'e.nro_serie', 'dp.nombres', 'dp.apellidos', 'da.nombre', 'dy.nombre'];

    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1) $length = 10;
    if ($length > 200) $length = 200;
    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $colIdx = (int)($_POST['order'][0]['column'] ?? 0);
    $dir = strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $colOrd = $alias[$colIdx] ?? $alias[0];

    $cond = ' WHERE ' . implode(' AND ', $where);
    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($base$cond) x", $par);
    $parC = $par; $condFull = $cond;
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $c) { $partes[] = "$c LIKE ?"; $parC[] = '%' . $busqueda . '%'; }
        $condFull .= ' AND (' . implode(' OR ', $partes) . ')';
    }
    $filtrados = $busqueda === '' ? $total : (int) Database::getValue("SELECT COUNT(*) FROM ($base$condFull) x", $parC);
    $filas = Database::get("SELECT * FROM ($base$condFull) x ORDER BY $colOrd $dir, id DESC LIMIT $length OFFSET $start", $parC);
    foreach ($filas as &$f) {
        $f['n_software'] = (int)$f['n_software']; $f['n_alertas'] = (int)$f['n_alertas'];
        $f['verif_vencida'] = $f['n_software'] > 0 && (!$f['software_verificado_en'] || $f['software_verificado_en'] < $corte) ? 1 : 0;
    }
    unset($f);

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    error_log('[SIGTI] software/equipos: ' . $e->getMessage());
    Response::error('No se pudo consultar el control por equipo.', null, 500);
}
