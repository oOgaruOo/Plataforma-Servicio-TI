<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('mantenimiento', 'ver');
    MantenimientoFlujo::asegurar();

    $fEstado    = $_POST['f_estado'] ?? '';
    $fTipo      = $_POST['f_tipo'] ?? '';
    $fProveedor = (int)($_POST['f_proveedor'] ?? 0);
    $fAtrasados = (int)($_POST['f_atrasados'] ?? 0) === 1;

    $where  = [];
    $params = [];

    $estadosValidos = ['solicitado','en_evaluacion','en_proceso','enviado_proveedor','cotizado',
                       'aprobado','rechazado','en_reparacion','recibido_reparado','listo',
                       'entregado','devuelto_stock','cerrado','cancelado','no_reparable'];
    if (in_array($fEstado, $estadosValidos, true)) { $where[] = 'm.estado = ?'; $params[] = $fEstado; }
    // grupos para trabajar el dia a dia
    elseif ($fEstado === 'en_curso')   { $where[] = "m.estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')"; }
    elseif ($fEstado === 'por_cerrar') { $where[] = "m.estado IN ('listo','entregado','devuelto_stock')"; }
    elseif ($fEstado === 'finalizados'){ $where[] = "m.estado IN ('cerrado','cancelado','no_reparable')"; }

    $tiposValidos = ['correctivo','preventivo','limpieza','actualizacion'];
    if (in_array($fTipo, $tiposValidos, true)) { $where[] = 'm.tipo = ?'; $params[] = $fTipo; }

    if ($fProveedor > 0) { $where[] = 'm.proveedor_id = ?'; $params[] = $fProveedor; }

    if ($fAtrasados) {
        $where[] = "m.fecha_retorno_estimada IS NOT NULL AND m.fecha_retorno_estimada < CURDATE()
                    AND m.estado IN ('enviado_proveedor','cotizado','aprobado','en_reparacion')";
    }

    $cond = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $base = "SELECT m.id, m.codigo, m.tipo, m.estado, m.diagnostico, m.fecha_inicio,
                    m.fecha_envio_proveedor, m.fecha_retorno_estimada, m.fecha_fin,
                    m.sla_limite, m.destino_envio,
                    m.costo_mano_obra, m.costo_repuestos, m.costo_total,
                    m.cotizacion_monto, m.ticket_id, m.equipo_id, m.proveedor_id,
                    m.tecnico_usuario_id,
                    m.resultado_final, m.atraso_min, m.motivo_atraso,
                    (SELECT COUNT(*) FROM mantenimiento_percances pc WHERE pc.mantenimiento_id = m.id) AS n_percances,
                    e.codigo AS equipo_codigo, e.marca AS equipo_marca, e.modelo AS equipo_modelo,
                    t.nombre AS equipo_tipo,
                    pr.nombre AS proveedor,
                    CONCAT(u.nombre_completo, '') AS tecnico,
                    tk.codigo AS ticket_codigo, tk.estado AS ticket_estado
             FROM mantenimientos m
             INNER JOIN equipos e ON e.id = m.equipo_id
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN proveedores pr ON pr.id = m.proveedor_id
             LEFT JOIN usuarios u ON u.id = m.tecnico_usuario_id
             LEFT JOIN tickets tk ON tk.id = m.ticket_id" . $cond;

    $alias = ['codigo','equipo_codigo','tipo','estado','proveedor','tecnico','fecha_retorno_estimada','costo_total','fecha_inicio'];
    $busca = ['m.codigo','e.codigo','e.marca','e.modelo','t.nombre','pr.nombre','u.nombre_completo','tk.codigo','m.diagnostico'];

    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1)   $length = 10;
    if ($length > 200) $length = 200;

    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $colIdx = (int)($_POST['order'][0]['column'] ?? 0);
    $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC';
    $colOrd = $alias[$colIdx] ?? $alias[0];

    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($base) x", $params);

    $paramsCon  = $params;
    $whereExtra = '';
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $col) {
            $partes[] = "$col LIKE ?";
            $paramsCon[] = '%' . $busqueda . '%';
        }
        $whereExtra = ($cond ? ' AND (' : ' WHERE (') . implode(' OR ', $partes) . ')';
    }

    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($base) x$whereExtra", $paramsCon);

    $filas = Database::get(
        "SELECT * FROM ($base) x$whereExtra ORDER BY $colOrd $dir LIMIT $length OFFSET $start",
        $paramsCon
    );

    // flag de atraso con proveedor + permisos por fila
    $hoy = date('Y-m-d');
    $puedeEditar   = Auth::can('mantenimiento', 'editar');
    $puedeEliminar = Auth::can('mantenimiento', 'eliminar');
    $puedeEstado   = Auth::can('mantenimiento', 'cambiar_estado');
    foreach ($filas as &$f) {
        $f['atrasado'] = ($f['fecha_retorno_estimada']
            && $f['fecha_retorno_estimada'] < $hoy
            && in_array($f['estado'], ['enviado_proveedor','cotizado','aprobado','en_reparacion'], true)) ? 1 : 0;
        $f['puede_editar']   = ($puedeEditar && !in_array($f['estado'], ['cerrado','cancelado','no_reparable'], true)) ? 1 : 0;
        $f['puede_eliminar'] = $puedeEliminar ? 1 : 0;
        $f['puede_estado']   = ($puedeEstado && !in_array($f['estado'], ['cerrado','cancelado','no_reparable'], true)) ? 1 : 0;
        $f['puede_finalizar'] = ($puedeEstado && MantenimientoFlujo::finalizableConTicket($f['estado'], $f['ticket_estado'] ?? null)) ? 1 : 0;
    }
    unset($f);

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('mantenimiento/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}