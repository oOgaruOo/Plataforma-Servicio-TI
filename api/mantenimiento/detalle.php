<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('mantenimiento', 'ver');
MantenimientoFlujo::asegurar();
Programados::asegurar();

 $id = (int)($_GET['id'] ?? 0);
if ($id === 0) Response::error('Mantenimiento no especificado.');

 $m = Database::getOne(
    "SELECT m.*, e.codigo AS equipo_codigo, e.marca AS equipo_marca, e.modelo AS equipo_modelo,
            e.estado AS equipo_estado, t.nombre AS equipo_tipo,
            pr.nombre AS proveedor, pr.telefono AS proveedor_tel,
            CONCAT(u.nombre_completo, '') AS tecnico,
            tk.codigo AS ticket_codigo, tk.titulo AS ticket_titulo,
            CONCAT(au.nombre_completo, '') AS autorizado_por_nombre,
            CONCAT(fu.nombre_completo, '') AS finalizado_por_nombre
     FROM mantenimientos m
     INNER JOIN equipos e ON e.id = m.equipo_id
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     LEFT JOIN proveedores pr ON pr.id = m.proveedor_id
     LEFT JOIN usuarios u ON u.id = m.tecnico_usuario_id
     LEFT JOIN tickets tk ON tk.id = m.ticket_id
     LEFT JOIN usuarios au ON au.id = m.autorizado_por
     LEFT JOIN usuarios fu ON fu.id = m.finalizado_por
     WHERE m.id = ?", [$id]);
if (!$m) Response::error('El mantenimiento no existe.');

// origen del trabajo: interno o proveedor
 $m['origen_trabajo'] = $m['proveedor_id']
    ? ['tipo' => 'proveedor', 'quien' => $m['proveedor'], 'telefono' => $m['proveedor_tel']]
    : ($m['tecnico_usuario_id']
        ? ['tipo' => 'interno', 'quien' => $m['tecnico'], 'telefono' => null]
        : ['tipo' => 'sin_asignar', 'quien' => null, 'telefono' => null]);

 $m['repuestos'] = Database::get(
    "SELECT r.id, r.descripcion, r.cantidad, r.costo_unitario,
            (r.cantidad * r.costo_unitario) AS subtotal, pr.nombre AS proveedor
     FROM mantenimiento_repuestos r
     LEFT JOIN proveedores pr ON pr.id = r.proveedor_id
     WHERE r.mantenimiento_id = ? ORDER BY r.id", [$id]);

 $m['evidencias'] = Database::get(
    "SELECT id, tipo, archivo, descripcion, created_at
     FROM mantenimiento_evidencias WHERE mantenimiento_id = ? ORDER BY id", [$id]);

// percances registrados durante el trabajo
 $m['percances'] = Database::get(
    "SELECT p.id, p.tipo, p.detalle, p.created_at, CONCAT(u.nombre_completo, '') AS usuario
     FROM mantenimiento_percances p
     LEFT JOIN usuarios u ON u.id = p.usuario_id
     WHERE p.mantenimiento_id = ? ORDER BY p.id", [$id]);
foreach ($m['percances'] as &$pc) { $pc['tipo_txt'] = MantenimientoFlujo::PERCANCES[$pc['tipo']] ?? $pc['tipo']; }
unset($pc);
 $m['percance_tipos'] = MantenimientoFlujo::PERCANCES;
 $m['resultado_tipos'] = MantenimientoFlujo::RESULTADOS;

// El historial derivado de auditoria_logs contiene datos internos del actor;
// no se expone con mantenimiento.ver si el usuario carece de auditoria.ver.
 $m['historial'] = Auth::can('auditoria', 'ver') ? Database::get(
    "SELECT accion, datos_nuevos, created_at
     FROM auditoria_logs
     WHERE tabla = 'mantenimientos' AND registro_id = ?
     ORDER BY id ASC", [$id]) : [];

// ---- transiciones permitidas (misma maquina que estado.php) + permisos ----
$m['transiciones'] = MantenimientoFlujo::transiciones($m['estado']);
$m['terminal']     = in_array($m['estado'], MantenimientoFlujo::TERMINALES, true);
$m['ticket_estado'] = null;
if ($m['ticket_id']) {
    $m['ticket_estado'] = Database::getValue('SELECT estado FROM tickets WHERE id = ?', [$m['ticket_id']]);
}
$m['finalizable']  = MantenimientoFlujo::finalizableConTicket($m['estado'], $m['ticket_estado'] ?: null);
// el ticket ya se atendio pero el mantenimiento (y el equipo en taller / con proveedor) sigue abierto
$m['ticket_atendido_pendiente'] = !$m['terminal'] && !in_array($m['estado'], MantenimientoFlujo::SIN_TRABAJO, true)
    && in_array((string)$m['ticket_estado'], MantenimientoFlujo::TICKET_ATENDIDO, true);
$m['sin_cotizar'] = $m['finalizable'] && !MantenimientoFlujo::finalizable($m['estado']);
// a donde vuelve el equipo al terminar (persona / area / proyecto / obra / stock)
$m['equipo_vuelve_a'] = $m['terminal'] ? null : MantenimientoFlujo::destinoTexto((int)$m['equipo_id']);
// seguimiento legible para todos (no depende de auditoria.ver)
$m['seguimiento'] = Database::get(
    "SELECT detalle, created_at FROM equipo_historial
     WHERE equipo_id = ? AND evento = 'mantenimiento' AND detalle LIKE ?
     ORDER BY id ASC", [$m['equipo_id'], '%' . $m['codigo'] . '%']);
// plazo comprometido (fecha de retorno del proveedor, o el SLA) y como se cerro
 $plazo = MantenimientoFlujo::plazo($m);
 $m['plazo'] = $plazo ? [
    'limite' => $plazo['limite'], 'origen' => $plazo['origen'],
    'vencido_ahora' => !$m['terminal'] && MantenimientoFlujo::atrasoMin($m) > 0,
    'atraso_min' => $m['terminal'] ? 0 : MantenimientoFlujo::atrasoMin($m),
    'atraso_txt' => (!$m['terminal'] && MantenimientoFlujo::atrasoMin($m) > 0)
        ? MantenimientoFlujo::textoAtraso(MantenimientoFlujo::atrasoMin($m)) : null,
] : null;
 // preventivo programado al que pertenece (cuando toca el siguiente)
 $m['programado'] = Programados::infoParaMt($m);
 $m['finalizacion'] = null;
if ($m['estado'] === 'cerrado') {
    $am = (int)($m['atraso_min'] ?? 0);
    $m['finalizacion'] = [
        'fecha'         => $m['fecha_fin'],
        'por'           => $m['finalizado_por_nombre'] ?: null,
        'resultado'     => $m['resultado_final'] ?: null,
        'resultado_txt' => $m['resultado_final'] ? (MantenimientoFlujo::RESULTADOS[$m['resultado_final']] ?? $m['resultado_final']) : null,
        'solucion'      => $m['solucion'] ?? null,
        'a_tiempo'      => $am === 0,
        'atraso_min'    => $am,
        'atraso_txt'    => $am > 0 ? MantenimientoFlujo::textoAtraso($am) : null,
        'motivo_atraso' => $m['motivo_atraso'] ?: null,
        'plazo_limite'  => $plazo['limite'] ?? null,
        'plazo_origen'  => $plazo['origen'] ?? null,
        'n_percances'   => count($m['percances']),
    ];
}
$m['puede'] = [
    'cambiar_estado' => Auth::can('mantenimiento', 'cambiar_estado'),
    'resolver_ticket' => Auth::can('tickets', 'cambiar_estado'),
    'editar'         => Auth::can('mantenimiento', 'editar'),
    'eliminar'       => Auth::can('mantenimiento', 'eliminar'),
    'repuestos'      => Auth::can('mantenimiento', 'editar') || Auth::can('mantenimiento', 'crear'),
    'percance'       => Auth::can('mantenimiento', 'cambiar_estado') && !$m['terminal'],
];

Response::ok($m);
