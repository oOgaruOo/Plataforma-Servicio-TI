<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: lo que tiene instalado UN equipo (ficha / gestion)
// GET ?equipo_id=   (software.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('software', 'ver');
    Destinos::asegurar();
    Software::asegurar();

    $id = (int)($_GET['equipo_id'] ?? 0);
    $eq = Database::getOne(
        "SELECT e.id, e.codigo, e.marca, e.modelo, e.estado, e.software_verificado_en, e.software_verificado_por,
                t.nombre AS tipo, t.familia,
                " . Destinos::SQL_NOMBRE . " AS asignado_a,
                vu.nombre_completo AS verificado_por
         FROM equipos e
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         LEFT JOIN usuarios vu ON vu.id = e.software_verificado_por
         LEFT JOIN asignaciones a ON a.id = (SELECT MAX(av.id) FROM asignaciones av WHERE av.equipo_id = e.id AND av.estado IN ('activa','vencida'))"
         . Destinos::SQL_JOIN . " WHERE e.id = ?", [$id]);
    if (!$eq) Response::error('El equipo no existe.');

    $hoy = date('Y-m-d');
    $filas = Database::get(
        "SELECT es.id, es.software_id, s.nombre AS software, s.categoria, s.autorizacion, s.requiere_licencia, s.fabricante,
                es.version, es.licencia_id, l.tipo AS lic_tipo, l.clave AS lic_clave, l.fecha_vencimiento AS lic_vence,
                es.estado, es.fecha_instalacion, es.fecha_desinstalacion, es.observaciones,
                u.nombre_completo AS instalado_por, ud.nombre_completo AS desinstalado_por
         FROM equipo_software es
         INNER JOIN software_catalogo s ON s.id = es.software_id
         LEFT JOIN software_licencias l ON l.id = es.licencia_id
         LEFT JOIN usuarios u ON u.id = es.instalado_por
         LEFT JOIN usuarios ud ON ud.id = es.desinstalado_por
         WHERE es.equipo_id = ?
         ORDER BY CASE WHEN es.estado = 'instalado' THEN 0 ELSE 1 END, s.nombre, es.id DESC", [$id]);

    $instalados = []; $historial = []; $idsInst = [];
    foreach ($filas as $f) {
        $f['lic_ult4'] = ($f['lic_clave'] ?? '') !== '' ? '…' . mb_substr((string)$f['lic_clave'], -4) : '';
        unset($f['lic_clave']);
        $f['lic_estado'] = $f['licencia_id'] ? Software::estadoLicencia($f['lic_vence'], $hoy) : null;
        $f['lic_tipo_nombre'] = $f['lic_tipo'] ? (Software::TIPOS_LIC[$f['lic_tipo']] ?? $f['lic_tipo']) : null;
        $f['categoria_nombre'] = Software::etiquetaCategoria($f['categoria']);
        $f['alertas'] = array_values(array_filter([
            $f['autorizacion'] === 'prohibido' ? 'no_autorizado' : null,
            ((int)$f['requiere_licencia'] === 1 && !$f['licencia_id']) ? 'sin_licencia' : null,
            $f['lic_estado'] === 'vencida' ? 'licencia_vencida' : null,
        ]));
        if ($f['estado'] === 'instalado') { $instalados[] = $f; $idsInst[(int)$f['software_id']] = true; }
        elseif (count($historial) < 40) $historial[] = $f;
    }

    $sugeridos = [];
    foreach (Database::get("SELECT id, nombre FROM software_catalogo WHERE activo = 1 AND estandar = 1 ORDER BY nombre") as $s) {
        if (!isset($idsInst[(int)$s['id']])) $sugeridos[] = $s;
    }

    $dias = null;
    if ($eq['software_verificado_en']) $dias = (int) floor((strtotime($hoy) - strtotime(substr($eq['software_verificado_en'], 0, 10))) / 86400);
    Response::ok([
        'equipo' => $eq, 'instalados' => $instalados, 'historial' => $historial, 'sugeridos' => $sugeridos,
        'dias_verificado' => $dias, 'dias_verificacion' => Software::DIAS_VERIFICACION,
        'editable' => $eq['estado'] !== 'dado_de_baja' ? 1 : 0,
        'puede_registrar' => Auth::can('software', 'registrar') ? 1 : 0,
        'puede_gestionar' => Auth::can('software', 'gestionar') ? 1 : 0,
    ]);

} catch (Throwable $e) {
    error_log('[SIGTI] software/equipo: ' . $e->getMessage());
    Response::error('No se pudo consultar el software del equipo.', null, 500);
}
