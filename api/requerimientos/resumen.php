<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. › Requerimiento de personal: tarjetas de resumen (segun lo que la persona puede ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Requerimientos::asegurar();
    $alc = Requerimientos::alcance();
    if (!$alc) Auth::requirePermission('rrhh', 'solicitar');

    $uid = Auth::userId();
    $hoy = date('Y-m-d');
    $n = fn(string $sql, array $p = []) => (int) Database::getValue($sql, $p);

    // lo mio (siempre)
    $mias = [
        'en_curso'  => $n("SELECT COUNT(*) FROM rrhh_requerimientos WHERE solicitante_usuario_id = ? AND estado IN ('pendiente','en_proceso')", [$uid]),
        'cubiertos' => $n("SELECT COUNT(*) FROM rrhh_requerimientos WHERE solicitante_usuario_id = ? AND estado = 'cubierto'", [$uid]),
    ];
    $out = ['mias' => $mias, 'modo' => $alc['modo']];

    // lo que ve (todas / su area)
    if ($alc['modo'] !== 'propias') {
        [$ex, $px] = Requerimientos::condicion($alc, 'r');
        $q = fn(string $extra, array $p = []) => $n("SELECT COUNT(*) FROM rrhh_requerimientos r WHERE 1=1$ex $extra", array_merge($px, $p));
        $out['todas'] = [
            'pendientes'  => $q("AND r.estado = 'pendiente'"),
            'en_proceso'  => $q("AND r.estado = 'en_proceso'"),
            'atrasados'   => $q("AND r.estado IN ('pendiente','en_proceso') AND r.fecha_limite IS NOT NULL AND r.fecha_limite < ?", [$hoy]),
            'cubiertos_mes' => $q("AND r.estado = 'cubierto' AND r.updated_at >= ?", [date('Y-m-01 00:00:00')]),
        ];
    }
    Response::ok($out);
} catch (Throwable $e) {
    error_log('[SIGTI] requerimientos/resumen: ' . $e->getMessage());
    Response::error('No se pudo cargar el resumen.', null, 500);
}
