<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Contratos: tarjetas de resumen + lista de por vencer  (rrhh.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('rrhh', 'ver');
    Contratos::asegurar();
    $out = Contratos::resumen();
    $out['por_vencer_lista'] = array_map(function ($c) {
        [$et, $co] = Contratos::estado($c);
        return ['id' => $c['id'], 'personal_id' => $c['personal_id'], 'nombre' => trim($c['nombres'] . ' ' . $c['apellidos']),
                'dni' => $c['dni'], 'fecha_fin' => $c['fecha_fin'], 'estado_txt' => $et, 'estado_color' => $co];
    }, Contratos::porVencer(30));
    Response::ok($out);
} catch (Throwable $e) {
    error_log('[SIGTI] contratos/resumen: ' . $e->getMessage());
    Response::error('No se pudo cargar el resumen.', null, 500);
}
