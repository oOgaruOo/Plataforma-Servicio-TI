<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Equipos: DataTables server-side + filtros
// v3: "Asignado a" muestra persona, area, proyecto u obra.
//     La busqueda ahora funciona (antes fallaba con "Unknown column").
//     Filtros: f_estado, f_tipo, f_garantia, f_destino, f_proyecto
//     (f_destino = persona|area|proyecto|obra|libre)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('equipos', 'ver');
    Destinos::asegurar();

    $fEstado   = $_POST['f_estado'] ?? '';
    $fTipo     = (int)($_POST['f_tipo'] ?? 0);
    $fGarantia = (int)($_POST['f_garantia'] ?? 0);
    $fDestino  = (string)($_POST['f_destino'] ?? '');
    $fProyecto = (int)($_POST['f_proyecto'] ?? 0);

    $where  = [];
    $params = [];

    $estadosValidos = ['en_stock','asignado','en_prestamo','en_revision','en_mantenimiento',
                       'en_reparacion_externa','obsoleto','dado_de_baja'];
    if (in_array($fEstado, $estadosValidos, true)) {
        $where[]  = 'e.estado = ?';
        $params[] = $fEstado;
    }
    if ($fTipo > 0) {
        $where[]  = 'e.tipo_equipo_id = ?';
        $params[] = $fTipo;
    }
    if ($fGarantia === 1) {
        $where[] = 'e.garantia_hasta IS NOT NULL AND e.garantia_hasta >= CURDATE() '
                 . 'AND e.garantia_hasta <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) '
                 . "AND e.estado <> 'dado_de_baja'";
    }
    if (isset(Destinos::TIPOS[$fDestino])) {
        $where[]  = 'a.destino_tipo = ?';
        $params[] = $fDestino;
    } elseif ($fDestino === 'libre') {
        $where[] = 'a.id IS NULL';
    }
    if ($fProyecto > 0) {
        $where[]  = 'a.proyecto_id = ?';
        $params[] = $fProyecto;
    }

    // la asignacion VIGENTE del equipo (la mas reciente activa / vencida)
    $base = "SELECT e.id, e.codigo, e.tipo_equipo_id, e.marca, e.modelo, e.nro_serie, e.activo_fijo,
                    e.imei, e.mac, e.estado, e.condicion, e.fecha_compra, e.costo, e.garantia_hasta,
                    e.proveedor_compra, e.ubicacion, e.observaciones, e.especificaciones,
                    t.nombre AS tipo, t.familia,
                    a.id AS asignacion_id, a.destino_tipo, a.destino_detalle, a.proyecto_id,
                    " . Destinos::SQL_NOMBRE . " AS asignado_a,
                    dy.codigo AS destino_codigo,
                    CONCAT(dr.nombres, ' ', dr.apellidos) AS responsable
             FROM equipos e
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN asignaciones a ON a.id = (
                 SELECT MAX(av.id) FROM asignaciones av
                 WHERE av.equipo_id = e.id AND av.estado IN ('activa','vencida')
             )" . Destinos::SQL_JOIN;

    $alias = ['codigo','codigo','tipo','marca','nro_serie','estado','condicion','asignado_a','garantia_hasta'];
    $busca = ['e.codigo','t.nombre','e.marca','e.modelo','e.nro_serie','e.activo_fijo','e.imei','e.ubicacion',
              'dp.nombres','dp.apellidos','da.nombre','dy.nombre','dy.codigo'];

    $draw   = (int)($_POST['draw'] ?? 0);
    $start  = max(0, (int)($_POST['start'] ?? 0));
    $length = (int)($_POST['length'] ?? 10);
    if ($length < 1)   $length = 10;
    if ($length > 200) $length = 200;

    $busqueda = trim((string)($_POST['search']['value'] ?? ''));
    $colIdx = (int)($_POST['order'][0]['column'] ?? 1);
    $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC';
    $colOrd = $alias[$colIdx] ?? $alias[0];

    $condBase = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $total = (int) Database::getValue("SELECT COUNT(*) FROM ($base$condBase) x", $params);

    $paramsCon = $params;
    $condFull  = $condBase;
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $col) {
            $partes[] = "$col LIKE ?";
            $paramsCon[] = '%' . $busqueda . '%';
        }
        $condFull .= ($condBase ? ' AND (' : ' WHERE (') . implode(' OR ', $partes) . ')';
    }

    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($base$condFull) x", $paramsCon);

    $filas = Database::get(
        "SELECT * FROM ($base$condFull) x ORDER BY $colOrd $dir, id DESC LIMIT $length OFFSET $start",
        $paramsCon
    );

    // equipos en taller / con el proveedor: que mantenimiento los retiene y si su ticket ya se atendio
    // (para que se vea por que siguen en «Reparacion externa» aunque el ticket ya este cerrado)
    try {
        $ids = [];
        foreach ($filas as $f) {
            if (in_array($f['estado'], ['en_mantenimiento', 'en_reparacion_externa', 'en_revision'], true)) $ids[] = (int)$f['id'];
        }
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $mts = Database::get(
                "SELECT m.id, m.equipo_id, m.codigo, m.estado, tk.codigo AS ticket_codigo, tk.estado AS ticket_estado
                 FROM mantenimientos m
                 LEFT JOIN tickets tk ON tk.id = m.ticket_id
                 WHERE m.equipo_id IN ($in)
                   AND m.estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')
                 ORDER BY m.id ASC", $ids);
            $porEq = [];
            foreach ($mts as $m) $porEq[(int)$m['equipo_id']] = $m;   // el mas reciente gana
            foreach ($filas as &$f) {
                $m = $porEq[(int)$f['id']] ?? null;
                $f['mt_id'] = $m ? (int)$m['id'] : null;
                $f['mt_codigo'] = $m['codigo'] ?? null;
                $f['mt_estado'] = $m['estado'] ?? null;
                $f['mt_ticket_codigo'] = $m['ticket_codigo'] ?? null;
                $f['mt_ticket_atendido'] = ($m && in_array((string)$m['ticket_estado'], ['resuelto', 'cerrado'], true)) ? 1 : 0;
            }
            unset($f);
        }
    } catch (Throwable $e) {
        // dato informativo: si falla, la lista sale igual
    }

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('equipos/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}
