<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Detalle del ticket v2
// FIX: columnas ambiguas prefijadas (created_at 1052)
// MEJORA: nivel de atencion + historial de escalamientos visible
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
TicketFlujo::asegurar();

 $id = (int)($_GET['id'] ?? 0);
if ($id === 0) Response::error('Ticket no especificado.');

 $t = Database::getOne(
    "SELECT t.*, p.nombre AS prioridad, p.color AS prioridad_color, p.sla_horas,
            c.nombre AS categoria, sc.nombre AS subcategoria,
            CONCAT(pe.nombres, ' ', pe.apellidos) AS solicitante, pe.correo_corporativo AS sol_correo,
            a.nombre AS area, CONCAT(u.nombre_completo, '') AS tecnico
     FROM tickets t
     INNER JOIN prioridades p ON p.id = t.prioridad_id
     INNER JOIN categorias  c ON c.id = t.categoria_id
     LEFT  JOIN subcategorias sc ON sc.id = t.subcategoria_id
     INNER JOIN personal pe ON pe.id = t.solicitante_personal_id
     INNER JOIN areas a ON a.id = t.area_afectada_id
     LEFT  JOIN usuarios u ON u.id = t.tecnico_usuario_id
     WHERE t.id = ?",
    [$id]
);
if (!$t) Response::error('El ticket no existe.');

// permiso de ver: usuario_final solo sus tickets
 $rol = Auth::rol();
if ($rol === 'usuario_final' && !Auth::can('tickets', 'ver') && !Auth::can('tickets', 'ver_area')) {
    $miPersonal = Database::getValue('SELECT personal_id FROM usuarios WHERE id = ?', [Auth::userId()]);
    if ((int)$miPersonal !== (int)$t['solicitante_personal_id']) {
        Response::forbidden('Solo puede ver sus propios tickets.');
    }
}

// ---- comentarios (PREFIJOS EXPLICITOS: fix del 1052) ----
 $t['comentarios'] = Database::get(
    "SELECT tc.id, tc.usuario_id, tc.mensaje, tc.interno,
            tc.created_at AS comentario_fecha,
            CONCAT(u.nombre_completo, '') AS autor
     FROM ticket_comentarios tc
     LEFT JOIN usuarios u ON u.id = tc.usuario_id
     WHERE tc.ticket_id = ? AND (tc.interno = 0 OR ? = 1)
     ORDER BY tc.id ASC",
    [$id, Auth::can('tickets', 'ver') ? 1 : 0]
);

// ---- adjuntos (prefijado) ----
 $t['adjuntos'] = Database::get(
    "SELECT ta.id, ta.archivo, ta.nombre_original, ta.tamano_kb,
            ta.created_at AS adjunto_fecha
     FROM ticket_adjuntos ta
     WHERE ta.ticket_id = ? ORDER BY ta.id", [$id]);

// ---- historial completo (incluye ASIGNACIONES = escalamientos) ----
 $t['historial'] = Database::get(
    "SELECT th.evento, th.detalle, th.created_at AS evento_fecha,
            CONCAT(u.nombre_completo, '') AS usuario
     FROM ticket_historial th
     LEFT JOIN usuarios u ON u.id = th.usuario_id
     WHERE th.ticket_id = ? ORDER BY th.id ASC", [$id]);

// ---- nivel de atencion legible ----
 $t['nivel_txt'] = ['N1 — Mesa de ayuda', 'N2 — Soporte técnico', 'N3 — Especialista'][(int)$t['nivel_atencion'] - 1] ?? 'N1';

// ---- transiciones permitidas (misma maquina de estados que estado.php) ----
$t['transiciones'] = TicketFlujo::transiciones($t['estado']);
$t['tipos_solucion'] = TicketFlujo::TIPOS_SOLUCION;

// ---- plazo (SLA) vigente y, si ya termino, COMO y CUANDO se finalizo ----
 $atrasoAhora = in_array($t['estado'], ['resuelto','cerrado','cancelado'], true) ? 0 : TicketFlujo::atrasoMin($t);
$t['sla_vencido_ahora'] = $atrasoAhora > 0;
$t['sla_atraso_txt'] = $atrasoAhora > 0 ? TicketFlujo::texto($atrasoAhora) : null;
$t['finalizacion'] = null;
if (in_array($t['estado'], ['resuelto','cerrado'], true) && !empty($t['fecha_resolucion'])) {
    $nombre = function ($uid) {
        return $uid ? (Database::getValue('SELECT nombre_completo FROM usuarios WHERE id = ?', [(int)$uid]) ?: null) : null;
    };
    $am = TicketFlujo::atrasoMin($t, strtotime((string)$t['fecha_resolucion']) ?: null);
    $total = max(0, (int)round(((strtotime((string)$t['fecha_resolucion']) ?: 0) - (strtotime((string)$t['fecha_creacion']) ?: 0)) / 60));
    $t['finalizacion'] = [
        'estado'         => $t['estado'],
        'fecha'          => $t['fecha_resolucion'],
        'por'            => $nombre($t['resuelto_por'] ?? null),
        'tipo_solucion'  => $t['tipo_solucion'] ?? null,
        'tipo_txt'       => !empty($t['tipo_solucion']) ? (TicketFlujo::TIPOS_SOLUCION[$t['tipo_solucion']] ?? $t['tipo_solucion']) : null,
        'tiempo_min'     => isset($t['tiempo_invertido_min']) ? (int)$t['tiempo_invertido_min'] : null,
        'tiempo_txt'     => isset($t['tiempo_invertido_min']) && $t['tiempo_invertido_min'] !== null ? TicketFlujo::texto((int)$t['tiempo_invertido_min']) : null,
        'total_min'      => $total,
        'total_txt'      => TicketFlujo::texto($total),
        'sla_limite'     => $t['sla_limite'],
        'a_tiempo'       => $am === 0,
        'atraso_min'     => $am,
        'atraso_txt'     => $am > 0 ? TicketFlujo::texto($am) : null,
        'motivo_retraso' => $t['motivo_retraso'] ?? null,
        'fecha_cierre'   => $t['estado'] === 'cerrado' ? $t['fecha_cierre'] : null,
        'cerrado_por'    => $t['estado'] === 'cerrado' ? $nombre($t['cerrado_por'] ?? null) : null,
    ];
}

// ---- mantenimientos de este ticket que siguen abiertos (el equipo sigue en taller / con proveedor) ----
try {
    $t['mantenimientos_abiertos'] = MantenimientoFlujo::abiertosDeTicket((int)$t['id']);
} catch (Throwable $e) {
    $t['mantenimientos_abiertos'] = [];
}
$t['puede_finalizar_mt'] = Auth::can('mantenimiento', 'cambiar_estado');

// ---- permisos efectivos para las acciones del detalle ----
$t['puede'] = [
    'cambiar_estado' => Auth::can('tickets', 'cambiar_estado'),
    'asignar'        => Auth::can('tickets', 'asignar'),
    'comentario_interno' => Auth::can('tickets', 'ver'),
    'finalizar'      => Auth::can('tickets', 'cambiar_estado') && TicketFlujo::finalizable($t['estado']),
    'cerrar'         => Auth::can('tickets', 'cambiar_estado') && $t['estado'] === 'resuelto',
    'reabrir'        => Auth::can('tickets', 'cambiar_estado') && in_array($t['estado'], ['resuelto','cerrado'], true),
];

Response::ok($t);