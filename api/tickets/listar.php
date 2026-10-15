<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Tickets: DataTables server-side + filtros + permisos
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    TicketFlujo::asegurar();

    $rol = Auth::rol();
    $esUsuarioFinal = ($rol === 'usuario_final' && !Auth::can('tickets', 'ver')
                    && !Auth::can('tickets', 'ver_area'));

    $fEstado    = $_POST['f_estado'] ?? '';
    $fPrioridad = (int)($_POST['f_prioridad'] ?? 0);
    $fCategoria = (int)($_POST['f_categoria'] ?? 0);
    $fTecnico   = (int)($_POST['f_tecnico'] ?? -1);
    $fSoloMios  = (int)($_POST['f_mios'] ?? 0) === 1;

    $where  = [];
    $params = [];

    $estadosValidos = ['abierto','asignado','en_atencion','pendiente_usuario',
                       'pendiente_proveedor','resuelto','cerrado','cancelado'];
    if (in_array($fEstado, $estadosValidos, true)) {
        $where[]  = 't.estado = ?';
        $params[] = $fEstado;
    }
    if ($fPrioridad > 0) { $where[] = 't.prioridad_id = ?'; $params[] = $fPrioridad; }
    if ($fCategoria > 0) { $where[] = 't.categoria_id = ?'; $params[] = $fCategoria; }
    if ($fTecnico >= 0) {
        if ($fTecnico === 0) { $where[] = 't.tecnico_usuario_id IS NULL'; }
        else { $where[] = 't.tecnico_usuario_id = ?'; $params[] = $fTecnico; }
    }

    if ($esUsuarioFinal) {
        $miPersonal = Database::getValue(
            'SELECT personal_id FROM usuarios WHERE id = ?', [Auth::userId()]);
        if ($miPersonal) {
            $where[]  = 't.solicitante_personal_id = ?';
            $params[] = (int)$miPersonal;
        }
    } elseif (!Auth::can('tickets', 'ver')) {
        $miPersonal = Database::getValue(
            'SELECT personal_id FROM usuarios WHERE id = ?', [Auth::userId()]);
        $miArea = $miPersonal
            ? Database::getValue('SELECT area_id FROM personal WHERE id = ?', [(int)$miPersonal])
            : null;
        if ($miArea) {
            $where[]  = 't.area_afectada_id = ?';
            $params[] = (int)$miArea;
        }
    }
    if ($fSoloMios && Auth::userId()) {
        $where[]  = 't.tecnico_usuario_id = ?';
        $params[] = Auth::userId();
    }

    $cond = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $base = "SELECT t.id, t.codigo, t.titulo, t.estado, t.impacto, t.reaperturas,
                    t.fecha_creacion, t.fecha_asignacion, t.fecha_primera_respuesta,
                    t.fecha_resolucion, t.fecha_cierre, t.sla_limite,
                    t.solicitante_personal_id, t.tecnico_usuario_id, t.prioridad_id,
                    (SELECT COUNT(*) FROM ticket_comentarios tc WHERE tc.ticket_id = t.id) AS n_comentarios,
                    (SELECT COUNT(*) FROM ticket_adjuntos ta WHERE ta.ticket_id = t.id) AS n_adjuntos,
                    p.nombre AS prioridad, p.color AS prioridad_color, p.sla_horas,
                    c.nombre AS categoria,
                    CONCAT(pe.nombres, ' ', pe.apellidos) AS solicitante,
                    a.nombre AS area,
                    CONCAT(u.nombre_completo, '') AS tecnico
             FROM tickets t
             INNER JOIN prioridades p ON p.id = t.prioridad_id
             INNER JOIN categorias  c ON c.id = t.categoria_id
             INNER JOIN personal pe ON pe.id = t.solicitante_personal_id
             INNER JOIN areas a ON a.id = t.area_afectada_id
             LEFT  JOIN usuarios u ON u.id = t.tecnico_usuario_id"
           . $cond;

    $alias = ['codigo','titulo','categoria','prioridad','solicitante','area','tecnico','estado','fecha_creacion'];
    $busca = ['t.codigo','t.titulo','c.nombre','p.nombre','pe.nombres','pe.apellidos','a.nombre','u.nombre_completo'];

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

    $ahora = time();
    $puedeEditar   = Auth::can('tickets', 'crear');
    $puedeEliminar = Auth::can('tickets', 'eliminar');
    $puedeAsignar  = Auth::can('tickets', 'asignar');
    $puedeFinalizar = Auth::can('tickets', 'cambiar_estado');
    foreach ($filas as &$f) {
        if (in_array($f['estado'], ['resuelto','cerrado','cancelado'], true)) {
            $f['sla_vencido'] = 0;
        } elseif ($f['sla_limite'] && strtotime($f['sla_limite']) < $ahora) {
            $f['sla_vencido'] = 1;
        } else {
            $f['sla_vencido'] = 0;
        }
        // como termino: dentro o fuera del SLA (para la columna «SLA / Finalizado»)
        $f['fin_atraso_min'] = 0;
        if (in_array($f['estado'], ['resuelto','cerrado'], true) && $f['fecha_resolucion']) {
            $f['fin_atraso_min'] = TicketFlujo::atrasoMin($f, strtotime((string)$f['fecha_resolucion']) ?: null);
        }
        $f['puede_finalizar'] = ($puedeFinalizar && TicketFlujo::finalizable($f['estado'])) ? 1 : 0;
        $f['puede_editar']   = ($puedeEditar && $f['estado'] === 'abierto') ? 1 : 0;
        $f['puede_eliminar'] = $puedeEliminar ? 1 : 0;
        $f['puede_asignar']  = ($puedeAsignar && !in_array($f['estado'], ['cerrado','cancelado'], true)) ? 1 : 0;
    }
    unset($f);

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('tickets/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}