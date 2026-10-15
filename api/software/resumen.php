<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: resumen para auditoria y gerencia
// GET  (permiso software.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('software', 'ver');
    Software::asegurar();

    $r = Software::resumen();

    // licencias que vencen pronto o ya vencieron (las 8 mas urgentes)
    $uso = Software::usoPorLicencia();
    $hoy = date('Y-m-d');
    $limite = date('Y-m-d', strtotime($hoy . ' +' . Software::DIAS_ALERTA . ' days'));
    $filas = Database::get(
        "SELECT l.id, l.tipo, l.puestos, l.fecha_vencimiento, s.nombre AS software
         FROM software_licencias l INNER JOIN software_catalogo s ON s.id = l.software_id
         WHERE l.activo = 1 AND l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento <= ?
         ORDER BY l.fecha_vencimiento, l.id LIMIT 8", [$limite]);
    foreach ($filas as &$f) {
        $f['usados'] = $uso[(int)$f['id']] ?? 0;
        $f['estado'] = Software::estadoLicencia($f['fecha_vencimiento'], $hoy);
        $f['dias'] = (int) floor((strtotime($f['fecha_vencimiento']) - strtotime($hoy)) / 86400);
    }
    unset($f);
    $r['licencias_alerta'] = $filas;
    $r['categorias'] = Software::CATEGORIAS;
    $r['dias_alerta'] = Software::DIAS_ALERTA;
    $r['dias_verificacion'] = Software::DIAS_VERIFICACION;
    $r['puede_registrar'] = Auth::can('software', 'registrar') ? 1 : 0;
    $r['puede_gestionar'] = Auth::can('software', 'gestionar') ? 1 : 0;

    Response::ok($r);

} catch (Throwable $e) {
    error_log('[SIGTI] software/resumen: ' . $e->getMessage());
    Response::error('No se pudo cargar el resumen de software.', null, 500);
}
