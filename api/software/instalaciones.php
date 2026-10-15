<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: instalaciones (DataTables, servidor)
// Filtros (POST): f_software f_categoria f_tipo f_estado(instalado|desinstalado|todos)
//                 f_alerta(no_autorizado|sin_licencia|licencia_vencida|cualquiera) f_equipo f_autorizacion
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('software', 'ver');
    Destinos::asegurar();
    Software::asegurar();

    $hoy = date('Y-m-d');
    $where = []; $par = [];

    $fEstado = (string)($_POST['f_estado'] ?? 'instalado');
    if ($fEstado === 'desinstalado') $where[] = "es.estado = 'desinstalado'";
    elseif ($fEstado !== 'todos')    { $where[] = "es.estado = 'instalado'"; $where[] = "eq.estado <> 'dado_de_baja'"; }

    if ((int)($_POST['f_software'] ?? 0) > 0)  { $where[] = 'es.software_id = ?';    $par[] = (int)$_POST['f_software']; }
    if ((int)($_POST['f_tipo'] ?? 0) > 0)      { $where[] = 'eq.tipo_equipo_id = ?'; $par[] = (int)$_POST['f_tipo']; }
    if ((int)($_POST['f_equipo'] ?? 0) > 0)    { $where[] = 'es.equipo_id = ?';      $par[] = (int)$_POST['f_equipo']; }
    $cat = (string)($_POST['f_categoria'] ?? '');
    if (isset(Software::CATEGORIAS[$cat]))     { $where[] = 's.categoria = ?';       $par[] = $cat; }
    $aut = (string)($_POST['f_autorizacion'] ?? '');
    if (isset(Software::AUTORIZACION[$aut]))   { $where[] = 's.autorizacion = ?';    $par[] = $aut; }

    $alerta = (string)($_POST['f_alerta'] ?? '');
    $cNoAut = "s.autorizacion = 'prohibido'";
    $cSinLic = "(s.requiere_licencia = 1 AND es.licencia_id IS NULL)";
    $cVenc = "(l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento < '$hoy')";
    if ($alerta === 'no_autorizado')          $where[] = $cNoAut;
    elseif ($alerta === 'sin_licencia')       $where[] = $cSinLic;
    elseif ($alerta === 'licencia_vencida')   $where[] = $cVenc;
    elseif ($alerta === 'cualquiera')         $where[] = "($cNoAut OR $cSinLic OR $cVenc)";

    $base = "SELECT es.id, es.equipo_id, eq.codigo, eq.estado AS eq_estado, t.nombre AS tipo,
                    TRIM(CONCAT(COALESCE(eq.marca, ''), ' ', COALESCE(eq.modelo, ''))) AS equipo_desc,
                    es.software_id, s.nombre AS software, s.categoria, s.autorizacion, s.requiere_licencia,
                    es.version, es.licencia_id, l.tipo AS lic_tipo, l.clave AS lic_clave, l.fecha_vencimiento AS lic_vence,
                    es.estado, es.fecha_instalacion, es.fecha_desinstalacion, es.observaciones,
                    u.nombre_completo AS instalado_por,
                    CASE WHEN $cNoAut THEN 1 ELSE 0 END AS f_noaut,
                    CASE WHEN $cSinLic THEN 1 ELSE 0 END AS f_sinlic,
                    CASE WHEN $cVenc THEN 1 ELSE 0 END AS f_venc,
                    " . Destinos::SQL_NOMBRE . " AS asignado_a
             FROM equipo_software es
             INNER JOIN equipos eq ON eq.id = es.equipo_id
             INNER JOIN tipo_equipos t ON t.id = eq.tipo_equipo_id
             INNER JOIN software_catalogo s ON s.id = es.software_id
             LEFT JOIN software_licencias l ON l.id = es.licencia_id
             LEFT JOIN usuarios u ON u.id = es.instalado_por
             LEFT JOIN asignaciones a ON a.id = (
                 SELECT MAX(av.id) FROM asignaciones av WHERE av.equipo_id = eq.id AND av.estado IN ('activa','vencida')
             )" . Destinos::SQL_JOIN;

    $alias = ['codigo', 'software', 'categoria', 'version', 'lic_vence', 'fecha_instalacion', 'asignado_a', 'estado'];
    $busca = ['eq.codigo', 'eq.marca', 'eq.modelo', 'eq.nro_serie', 's.nombre', 's.fabricante', 'es.version', 'l.documento',
              'dp.nombres', 'dp.apellidos', 'da.nombre', 'dy.nombre'];

    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1) $length = 10;
    if ($length > 200) $length = 200;
    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $colIdx = (int)($_POST['order'][0]['column'] ?? 0);
    $dir = strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $colOrd = $alias[$colIdx] ?? $alias[0];

    $cond = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($base$cond) x", $par);

    $parC = $par; $condFull = $cond;
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $c) { $partes[] = "$c LIKE ?"; $parC[] = '%' . $busqueda . '%'; }
        $condFull .= ($cond ? ' AND (' : ' WHERE (') . implode(' OR ', $partes) . ')';
    }
    $filtrados = $busqueda === '' ? $total : (int) Database::getValue("SELECT COUNT(*) FROM ($base$condFull) x", $parC);

    $filas = Database::get("SELECT * FROM ($base$condFull) x ORDER BY $colOrd $dir, id DESC LIMIT $length OFFSET $start", $parC);
    foreach ($filas as &$f) {
        $f['lic_ult4'] = ($f['lic_clave'] ?? '') !== '' ? '…' . mb_substr((string)$f['lic_clave'], -4) : '';
        unset($f['lic_clave']);   // la clave completa nunca sale en la lista
        $f['categoria_nombre'] = Software::etiquetaCategoria($f['categoria']);
        $f['lic_tipo_nombre'] = $f['lic_tipo'] ? (Software::TIPOS_LIC[$f['lic_tipo']] ?? $f['lic_tipo']) : null;
    }
    unset($f);

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    error_log('[SIGTI] software/instalaciones: ' . $e->getMessage());
    Response::error('No se pudo consultar el software instalado.', null, 500);
}
