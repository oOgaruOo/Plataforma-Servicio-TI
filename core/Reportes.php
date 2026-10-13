<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Reportes: todos los calculos en un solo lugar.
// Lo usan api/reportes/datos.php (graficos), api/reportes/exportar.php (CSV / Excel)
// y views/reportes/informe.php (informe imprimible para gerencia).
// SQL portable: las fechas viajan como parametros y los meses se agrupan con SUBSTR.
// ============================================================

class Reportes
{
    public const MODULOS = ['resumen', 'tickets', 'mantenimiento', 'inventario', 'software', 'asignaciones', 'personal'];

    public const TITULOS = [
        'resumen'       => 'Resumen ejecutivo',
        'tickets'       => 'Mesa de ayuda (tickets)',
        'mantenimiento' => 'Mantenimiento',
        'inventario'    => 'Inventario de equipos',
        'software'      => 'Software y licencias',
        'asignaciones'  => 'Asignaciones y actas',
        'personal'      => 'Personal',
    ];

    /**
     * Que puede ver quien consulta: con reportes.ver, todo; con reportes.ver_area, solo tickets y personal de su area.
     * Devuelve ['area' => ?int, 'modulos' => [...]] o null si no tiene ninguno de los dos permisos.
     */
    public static function alcance(): ?array
    {
        if (Auth::can('reportes', 'ver')) return ['area' => null, 'modulos' => self::MODULOS];
        if (!Auth::can('reportes', 'ver_area')) return null;
        $area = Database::getValue(
            'SELECT p.area_id FROM usuarios u INNER JOIN personal p ON p.id = u.personal_id WHERE u.id = ?', [Auth::userId()]);
        if (!$area) return null;
        return ['area' => (int)$area, 'modulos' => ['resumen', 'tickets', 'personal']];
    }

    /** Rango [desde, hasta] validado. 'meses' = mapa Y-m => 0 para armar series sin huecos. */
    public static function rango(?string $desde, ?string $hasta): array
    {
        $ok = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && DateTime::createFromFormat('Y-m-d', $d) !== false;
        $desde = $ok($desde) ? $desde : date('Y-m-01', strtotime('-5 months'));
        $hasta = $ok($hasta) ? $hasta : date('Y-m-d');
        if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }
        $meses = [];
        $cur = new DateTime(substr($desde, 0, 7) . '-01');
        $fin = new DateTime($hasta);
        while ($cur <= $fin && count($meses) < 36) {
            $meses[$cur->format('Y-m')] = 0;
            $cur->modify('+1 month');
        }
        $dias = (int) round((strtotime($hasta) - strtotime($desde)) / 86400) + 1;
        return ['desde' => $desde, 'hasta' => $hasta, 'ini' => $desde . ' 00:00:00', 'fin' => $hasta . ' 23:59:59',
                'meses' => $meses, 'dias' => $dias];
    }

    /** Periodo inmediatamente anterior de la misma duracion (para comparar). */
    public static function rangoAnterior(array $r): array
    {
        $hasta = date('Y-m-d', strtotime($r['desde'] . ' -1 day'));
        $desde = date('Y-m-d', strtotime($hasta . ' -' . ($r['dias'] - 1) . ' days'));
        return self::rango($desde, $hasta);
    }

    private static function serie(array $base, array $filas, string $col = 'n', bool $float = false): array
    {
        foreach ($filas as $f) {
            if (isset($base[$f['mes']])) $base[$f['mes']] = $float ? (float)$f[$col] : (int)$f[$col];
        }
        return array_values($base);
    }

    private static function horas(?int $minutos): ?float
    {
        return $minutos === null ? null : round($minutos / 60, 1);
    }

    private static function variacion(float $actual, float $anterior): ?int
    {
        if ($anterior <= 0) return null;
        return (int) round(($actual - $anterior) * 100 / $anterior);
    }

    // =================== TICKETS ===================
    public static function tickets(array $r, ?int $areaId = null, int $limite = 500): array
    {
        $limite = max(1, min(20000, $limite));
        $a = $areaId ? ' AND t.area_afectada_id = ?' : '';
        $pa = $areaId ? [$areaId] : [];
        $rg = [$r['ini'], $r['fin']];
        $ahora = date('Y-m-d H:i:s');

        $creados = Database::get(
            "SELECT SUBSTR(t.fecha_creacion,1,7) AS mes, COUNT(*) AS n FROM tickets t
             WHERE t.fecha_creacion BETWEEN ? AND ?$a GROUP BY SUBSTR(t.fecha_creacion,1,7)", array_merge($rg, $pa));
        $resueltos = Database::get(
            "SELECT SUBSTR(t.fecha_resolucion,1,7) AS mes, COUNT(*) AS n FROM tickets t
             WHERE t.fecha_resolucion IS NOT NULL AND t.fecha_resolucion BETWEEN ? AND ?$a
             GROUP BY SUBSTR(t.fecha_resolucion,1,7)", array_merge($rg, $pa));

        $estados = Database::get(
            "SELECT t.estado, COUNT(*) AS n FROM tickets t WHERE t.fecha_creacion BETWEEN ? AND ?$a
             GROUP BY t.estado ORDER BY n DESC", array_merge($rg, $pa));
        $prioridades = Database::get(
            "SELECT p.nombre, p.color, COUNT(*) AS n FROM tickets t INNER JOIN prioridades p ON p.id = t.prioridad_id
             WHERE t.fecha_creacion BETWEEN ? AND ?$a GROUP BY p.id, p.nombre, p.color, p.nivel ORDER BY p.nivel", array_merge($rg, $pa));
        $tecnicos = Database::get(
            "SELECT u.nombre_completo AS tecnico, COUNT(*) AS asignados,
                    SUM(CASE WHEN t.estado IN ('resuelto','cerrado') THEN 1 ELSE 0 END) AS resueltos
             FROM tickets t INNER JOIN usuarios u ON u.id = t.tecnico_usuario_id
             WHERE t.fecha_creacion BETWEEN ? AND ?$a
             GROUP BY u.id, u.nombre_completo ORDER BY asignados DESC LIMIT 12", array_merge($rg, $pa));
        $categorias = Database::get(
            "SELECT c.nombre, COUNT(*) AS n FROM tickets t INNER JOIN categorias c ON c.id = t.categoria_id
             WHERE t.fecha_creacion BETWEEN ? AND ?$a GROUP BY c.id, c.nombre ORDER BY n DESC LIMIT 10", array_merge($rg, $pa));
        $areas = Database::get(
            "SELECT ar.nombre, COUNT(*) AS n FROM tickets t INNER JOIN areas ar ON ar.id = t.area_afectada_id
             WHERE t.fecha_creacion BETWEEN ? AND ?$a GROUP BY ar.id, ar.nombre ORDER BY n DESC LIMIT 10", array_merge($rg, $pa));

        // tiempos y cumplimiento del SLA: se calculan aqui con las fechas de cada ticket
        $tiempos = Database::get(
            "SELECT t.fecha_creacion, t.fecha_primera_respuesta, t.fecha_resolucion, t.sla_limite
             FROM tickets t WHERE t.fecha_creacion BETWEEN ? AND ?$a", array_merge($rg, $pa));
        $slaOk = 0; $slaTot = 0; $sumRes = 0; $nRes = 0; $sumResp = 0; $nResp = 0;
        foreach ($tiempos as $x) {
            $c = strtotime((string)$x['fecha_creacion']);
            if (!empty($x['fecha_resolucion'])) {
                $f = strtotime((string)$x['fecha_resolucion']);
                $sumRes += max(0, (int) round(($f - $c) / 60)); $nRes++;
                $slaTot++;
                if (!empty($x['sla_limite']) && $f <= strtotime((string)$x['sla_limite'])) $slaOk++;
            }
            if (!empty($x['fecha_primera_respuesta'])) {
                $sumResp += max(0, (int) round((strtotime((string)$x['fecha_primera_respuesta']) - $c) / 60)); $nResp++;
            }
        }

        $actividad = Database::get(
            "SELECT u.nombre_completo AS usuario,
                    SUM(CASE WHEN th.evento = 'comentario' THEN 1 ELSE 0 END) AS comentarios,
                    SUM(CASE WHEN th.evento <> 'comentario' THEN 1 ELSE 0 END) AS movimientos,
                    COUNT(*) AS total
             FROM ticket_historial th INNER JOIN usuarios u ON u.id = th.usuario_id
             " . ($areaId ? "INNER JOIN tickets t ON t.id = th.ticket_id" : '') . "
             WHERE th.created_at BETWEEN ? AND ?$a
             GROUP BY u.id, u.nombre_completo ORDER BY total DESC LIMIT 12", array_merge($rg, $pa));
        $solicitantes = Database::get(
            "SELECT CONCAT(pe.nombres,' ',pe.apellidos) AS solicitante, ar.nombre AS area, COUNT(*) AS tickets,
                    SUM(CASE WHEN t.estado IN ('resuelto','cerrado') THEN 1 ELSE 0 END) AS resueltos
             FROM tickets t INNER JOIN personal pe ON pe.id = t.solicitante_personal_id
             INNER JOIN areas ar ON ar.id = t.area_afectada_id
             WHERE t.fecha_creacion BETWEEN ? AND ?$a
             GROUP BY pe.id, pe.nombres, pe.apellidos, ar.nombre ORDER BY tickets DESC LIMIT 12", array_merge($rg, $pa));

        $detalle = Database::get(
            "SELECT t.codigo, t.titulo, CONCAT(pe.nombres,' ',pe.apellidos) AS solicitante,
                    ar.nombre AS area, c.nombre AS categoria, p.nombre AS prioridad,
                    COALESCE(u.nombre_completo, '— sin asignar —') AS tecnico,
                    t.estado, t.impacto, t.reaperturas,
                    t.fecha_creacion, COALESCE(t.fecha_primera_respuesta,'—') AS fecha_primera_respuesta,
                    COALESCE(t.fecha_resolucion,'—') AS fecha_resolucion, COALESCE(t.fecha_cierre,'—') AS fecha_cierre,
                    COALESCE(t.sla_limite,'—') AS sla_limite,
                    (SELECT COUNT(*) FROM ticket_historial th WHERE th.ticket_id = t.id AND th.evento = 'comentario') AS comentarios,
                    CASE WHEN t.fecha_resolucion IS NOT NULL AND t.fecha_resolucion <= t.sla_limite THEN 'SLA cumplido'
                         WHEN t.fecha_resolucion IS NOT NULL THEN 'SLA vencido'
                         WHEN t.sla_limite IS NOT NULL AND t.sla_limite < ? THEN 'SLA vencido (sin resolver)'
                         ELSE 'En plazo' END AS sla_estado,
                    COALESCE(SUBSTR(t.solucion,1,300),'') AS solucion
             FROM tickets t
             INNER JOIN prioridades p ON p.id = t.prioridad_id
             INNER JOIN categorias c ON c.id = t.categoria_id
             INNER JOIN personal pe ON pe.id = t.solicitante_personal_id
             INNER JOIN areas ar ON ar.id = t.area_afectada_id
             LEFT  JOIN usuarios u ON u.id = t.tecnico_usuario_id
             WHERE t.fecha_creacion BETWEEN ? AND ?$a
             ORDER BY t.fecha_creacion DESC LIMIT $limite", array_merge([$ahora], $rg, $pa));

        $abiertosAhora = (int) Database::getValue(
            "SELECT COUNT(*) FROM tickets t WHERE t.estado NOT IN ('resuelto','cerrado','cancelado')$a", $pa);
        $sinAsignar = (int) Database::getValue(
            "SELECT COUNT(*) FROM tickets t WHERE t.estado = 'abierto' AND t.tecnico_usuario_id IS NULL$a", $pa);
        $slaVencidosAhora = (int) Database::getValue(
            "SELECT COUNT(*) FROM tickets t WHERE t.estado NOT IN ('resuelto','cerrado','cancelado') AND t.sla_limite < ?$a",
            array_merge([$ahora], $pa));
        $reabiertos = (int) Database::getValue(
            "SELECT COUNT(*) FROM tickets t WHERE t.reaperturas > 0 AND t.fecha_creacion BETWEEN ? AND ?$a", array_merge($rg, $pa));

        $total = (int) array_sum(array_column($creados, 'n'));
        $ant = self::rangoAnterior($r);
        $totalAnt = (int) Database::getValue(
            "SELECT COUNT(*) FROM tickets t WHERE t.fecha_creacion BETWEEN ? AND ?$a", array_merge([$ant['ini'], $ant['fin']], $pa));

        return [
            'rango' => ['desde' => $r['desde'], 'hasta' => $r['hasta']],
            'meses' => array_keys($r['meses']),
            'creados' => self::serie($r['meses'], $creados),
            'resueltos' => self::serie($r['meses'], $resueltos),
            'estados' => $estados, 'prioridades' => $prioridades, 'tecnicos' => $tecnicos,
            'categorias' => $categorias, 'areas' => $areas,
            'actividad_usuarios' => $actividad, 'solicitantes' => $solicitantes,
            'detalle' => $detalle, 'detalle_total' => $total,
            'tarjetas' => [
                'total' => $total,
                'variacion' => self::variacion($total, $totalAnt),
                'resueltos' => $slaTot,
                'sla_pct' => $slaTot > 0 ? (int) round(100 * $slaOk / $slaTot) : null,
                'sla_ok' => $slaOk,
                'prom_resolucion_h' => $nRes ? self::horas((int) round($sumRes / $nRes)) : null,
                'prom_respuesta_h' => $nResp ? self::horas((int) round($sumResp / $nResp)) : null,
                'abiertos_ahora' => $abiertosAhora,
                'sin_asignar' => $sinAsignar,
                'sla_vencidos_ahora' => $slaVencidosAhora,
                'reabiertos' => $reabiertos,
            ],
        ];
    }

    // =================== MANTENIMIENTO ===================
    public static function mantenimiento(array $r, int $limite = 500): array
    {
        $limite = max(1, min(20000, $limite));
        $rg = [$r['ini'], $r['fin']];
        $hoy = date('Y-m-d');

        $costos = Database::get(
            "SELECT SUBSTR(fecha_inicio,1,7) AS mes, SUM(costo_total) AS costo, COUNT(*) AS n
             FROM mantenimientos WHERE fecha_inicio BETWEEN ? AND ? AND estado <> 'cancelado'
             GROUP BY SUBSTR(fecha_inicio,1,7)", $rg);
        $estados = Database::get(
            "SELECT estado, COUNT(*) AS n FROM mantenimientos WHERE fecha_inicio BETWEEN ? AND ?
             GROUP BY estado ORDER BY n DESC", $rg);
        $proveedores = Database::get(
            "SELECT pr.nombre, COUNT(*) AS n, COALESCE(SUM(m.costo_total),0) AS costo
             FROM mantenimientos m INNER JOIN proveedores pr ON pr.id = m.proveedor_id
             WHERE m.fecha_inicio BETWEEN ? AND ? GROUP BY pr.id, pr.nombre ORDER BY costo DESC LIMIT 10", $rg);
        $tipos = Database::get(
            "SELECT tipo, COUNT(*) AS n, COALESCE(SUM(costo_total),0) AS costo FROM mantenimientos
             WHERE fecha_inicio BETWEEN ? AND ? AND estado <> 'cancelado' GROUP BY tipo", $rg);
        $topEquipos = Database::get(
            "SELECT e.codigo, CONCAT(COALESCE(e.marca,''),' ',COALESCE(e.modelo,'')) AS equipo, t.nombre AS tipo,
                    COUNT(*) AS n, COALESCE(SUM(m.costo_total),0) AS costo
             FROM mantenimientos m INNER JOIN equipos e ON e.id = m.equipo_id
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             WHERE m.fecha_inicio BETWEEN ? AND ? AND m.estado <> 'cancelado'
             GROUP BY e.id, e.codigo, e.marca, e.modelo, t.nombre ORDER BY n DESC, costo DESC LIMIT 10", $rg);
        $porTipoEquipo = Database::get(
            "SELECT t.nombre, COUNT(*) AS n, COALESCE(SUM(m.costo_total),0) AS costo
             FROM mantenimientos m INNER JOIN equipos e ON e.id = m.equipo_id
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             WHERE m.fecha_inicio BETWEEN ? AND ? AND m.estado <> 'cancelado'
             GROUP BY t.id, t.nombre ORDER BY costo DESC LIMIT 10", $rg);

        $detalle = Database::get(
            "SELECT m.codigo, e.codigo AS equipo, t.nombre AS equipo_tipo,
                    CONCAT(COALESCE(e.marca,''),' ',COALESCE(e.modelo,'')) AS equipo_desc,
                    m.tipo, m.estado, COALESCE(u.nombre_completo,'—') AS tecnico,
                    COALESCE(pr.nombre,'—') AS proveedor, COALESCE(m.destino_envio,'—') AS destino,
                    m.fecha_inicio, COALESCE(m.fecha_fin,'—') AS fecha_fin,
                    COALESCE(m.fecha_retorno_estimada,'—') AS fecha_retorno_estimada,
                    COALESCE(m.costo_mano_obra,0) AS costo_mano_obra, COALESCE(m.costo_repuestos,0) AS costo_repuestos,
                    COALESCE(m.costo_total,0) AS costo_total,
                    (SELECT COUNT(*) FROM mantenimiento_repuestos x WHERE x.mantenimiento_id = m.id) AS utilidades,
                    COALESCE(SUBSTR(m.solucion,1,300),'') AS solucion
             FROM mantenimientos m
             INNER JOIN equipos e ON e.id = m.equipo_id
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN usuarios u ON u.id = m.tecnico_usuario_id
             LEFT JOIN proveedores pr ON pr.id = m.proveedor_id
             WHERE m.fecha_inicio BETWEEN ? AND ?
             ORDER BY m.fecha_inicio DESC LIMIT $limite", $rg);

        $costoMes = self::serie($r['meses'], $costos, 'costo', true);
        $cantMes = self::serie($r['meses'], $costos, 'n');
        $costoTotal = array_sum($costoMes);
        $total = array_sum($cantMes);
        $ant = self::rangoAnterior($r);
        $costoAnt = (float) (Database::getValue(
            "SELECT COALESCE(SUM(costo_total),0) FROM mantenimientos WHERE fecha_inicio BETWEEN ? AND ? AND estado <> 'cancelado'",
            [$ant['ini'], $ant['fin']]) ?? 0);

        return [
            'rango' => ['desde' => $r['desde'], 'hasta' => $r['hasta']],
            'meses' => array_keys($r['meses']),
            'costos' => $costoMes, 'cantidad' => $cantMes,
            'estados' => $estados, 'proveedores' => $proveedores, 'tipos' => $tipos,
            'top_equipos' => $topEquipos, 'por_tipo_equipo' => $porTipoEquipo,
            'detalle' => $detalle,
            'tarjetas' => [
                'costo_total' => $costoTotal,
                'variacion_costo' => self::variacion($costoTotal, $costoAnt),
                'total' => $total,
                'costo_promedio' => $total ? round($costoTotal / $total, 2) : 0,
                'no_reparables' => (int) Database::getValue(
                    "SELECT COUNT(*) FROM mantenimientos WHERE estado = 'no_reparable' AND fecha_inicio BETWEEN ? AND ?", $rg),
                'atrasados' => (int) Database::getValue(
                    "SELECT COUNT(*) FROM mantenimientos WHERE fecha_retorno_estimada < ?
                     AND estado IN ('enviado_proveedor','cotizado','aprobado','en_reparacion')", [$hoy]),
                'activos' => (int) Database::getValue(
                    "SELECT COUNT(*) FROM mantenimientos
                     WHERE estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')"),
                'preventivos_vencidos' => (int) Database::getValue(
                    "SELECT COUNT(*) FROM mantenimientos_programados WHERE activo = 1
                     AND mantenimiento_actual_id IS NULL AND proxima_fecha < ?", [$hoy]),
            ],
        ];
    }

    // =================== INVENTARIO ===================
    public static function inventario(array $r): array
    {
        $hoy = date('Y-m-d');
        $en90 = date('Y-m-d', strtotime('+90 days'));
        $en180 = date('Y-m-d', strtotime('+180 days'));
        $hace30 = date('Y-m-d H:i:s', strtotime('-30 days'));
        $hace90 = date('Y-m-d H:i:s', strtotime('-90 days'));
        $activo = "e.estado <> 'dado_de_baja'";

        $estados = Database::get(
            "SELECT e.estado, COUNT(*) AS n FROM equipos e WHERE $activo GROUP BY e.estado ORDER BY n DESC");
        $porTipo = Database::get(
            "SELECT t.nombre, COUNT(*) AS n, COALESCE(SUM(e.costo),0) AS valor
             FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id WHERE $activo
             GROUP BY t.id, t.nombre ORDER BY n DESC LIMIT 15");
        $garantias = Database::get(
            "SELECT SUBSTR(garantia_hasta,1,7) AS mes, COUNT(*) AS n FROM equipos e
             WHERE e.garantia_hasta BETWEEN ? AND ? AND $activo GROUP BY SUBSTR(garantia_hasta,1,7) ORDER BY mes", [$hoy, $en180]);
        $condicion = Database::get(
            "SELECT e.condicion, COUNT(*) AS n FROM equipos e WHERE $activo GROUP BY e.condicion ORDER BY n DESC");

        $cmp = "FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id WHERE t.familia = 'computo' AND $activo";
        $verde = (int) Database::getValue("SELECT COUNT(*) $cmp AND e.verificado_ubicacion >= ?", [$hace30]);
        $ambar = (int) Database::getValue("SELECT COUNT(*) $cmp AND e.verificado_ubicacion < ? AND e.verificado_ubicacion >= ?", [$hace30, $hace90]);
        $rojo = (int) Database::getValue("SELECT COUNT(*) $cmp AND (e.verificado_ubicacion IS NULL OR e.verificado_ubicacion < ?)", [$hace90]);

        // antiguedad segun fecha de compra (para planificar renovaciones)
        $edades = ['menos de 1 año' => 0, '1 a 3 años' => 0, '3 a 5 años' => 0, 'más de 5 años' => 0, 'sin fecha de compra' => 0];
        foreach (Database::get("SELECT e.fecha_compra FROM equipos e WHERE $activo") as $x) {
            if (empty($x['fecha_compra'])) { $edades['sin fecha de compra']++; continue; }
            $anios = (strtotime($hoy) - strtotime((string)$x['fecha_compra'])) / (365.25 * 86400);
            if ($anios < 1) $edades['menos de 1 año']++;
            elseif ($anios < 3) $edades['1 a 3 años']++;
            elseif ($anios < 5) $edades['3 a 5 años']++;
            else $edades['más de 5 años']++;
        }

        Destinos::asegurar();
        $porDestino = Database::get(
            "SELECT destino_tipo AS tipo, COUNT(*) AS n FROM asignaciones
             WHERE estado IN ('activa','vencida') GROUP BY destino_tipo ORDER BY n DESC");
        $porProyecto = Database::get(
            "SELECT p.tipo, p.codigo, p.nombre, COUNT(*) AS n, COALESCE(SUM(e.costo),0) AS valor
             FROM asignaciones a INNER JOIN proyectos p ON p.id = a.proyecto_id
             INNER JOIN equipos e ON e.id = a.equipo_id
             WHERE a.estado IN ('activa','vencida')
             GROUP BY p.id, p.tipo, p.codigo, p.nombre ORDER BY n DESC LIMIT 20");

        $valorizado = (float) (Database::getValue("SELECT COALESCE(SUM(e.costo),0) FROM equipos e WHERE $activo") ?? 0);

        return [
            'por_destino' => $porDestino, 'por_proyecto' => $porProyecto,
            'estados' => $estados, 'por_tipo' => $porTipo, 'garantias' => $garantias, 'condicion' => $condicion,
            'edades' => array_map(fn($k, $v) => ['rango' => $k, 'n' => $v], array_keys($edades), array_values($edades)),
            'seguimiento' => ['verde' => $verde, 'ambar' => $ambar, 'rojo' => $rojo],
            'tarjetas' => [
                'total' => (int) Database::getValue("SELECT COUNT(*) FROM equipos e WHERE $activo"),
                'stock' => (int) Database::getValue("SELECT COUNT(*) FROM equipos WHERE estado = 'en_stock'"),
                'asignados' => (int) Database::getValue("SELECT COUNT(*) FROM equipos WHERE estado IN ('asignado','en_prestamo')"),
                'en_mantenimiento' => (int) Database::getValue(
                    "SELECT COUNT(*) FROM equipos WHERE estado IN ('en_mantenimiento','en_reparacion_externa','en_revision')"),
                'obsoletos' => (int) Database::getValue("SELECT COUNT(*) FROM equipos WHERE estado = 'obsoleto'"),
                'dados_baja' => (int) Database::getValue("SELECT COUNT(*) FROM equipos WHERE estado = 'dado_de_baja'"),
                'valorizado' => $valorizado,
                'garantia90' => (int) Database::getValue(
                    "SELECT COUNT(*) FROM equipos e WHERE e.garantia_hasta BETWEEN ? AND ? AND $activo", [$hoy, $en90]),
                'garantia_vencida' => (int) Database::getValue(
                    "SELECT COUNT(*) FROM equipos e WHERE e.garantia_hasta < ? AND $activo", [$hoy]),
            ],
        ];
    }

    // =================== SOFTWARE ===================
    public static function software(array $r = []): array
    {
        $s = Software::resumen();
        $licencias = Database::get(
            "SELECT s.nombre AS software, l.tipo, l.puestos, l.fecha_vencimiento, l.costo, l.proveedor
             FROM software_licencias l INNER JOIN software_catalogo s ON s.id = l.software_id
             WHERE l.activo = 1 ORDER BY (l.fecha_vencimiento IS NULL), l.fecha_vencimiento, s.nombre LIMIT 200");
        $hoy = date('Y-m-d');
        foreach ($licencias as &$l) {
            $l['estado'] = Software::estadoLicencia($l['fecha_vencimiento'], $hoy);
        }
        unset($l);
        $porAut = Database::get(
            "SELECT s.autorizacion, COUNT(*) AS n FROM equipo_software es
             INNER JOIN equipos eq ON eq.id = es.equipo_id INNER JOIN software_catalogo s ON s.id = es.software_id
             WHERE " . Software::INST_VIG . " GROUP BY s.autorizacion");
        return [
            'tarjetas' => array_intersect_key($s, array_flip([
                'programas', 'instalaciones', 'equipos_con', 'equipos_computo', 'equipos_computo_sin', 'cobertura',
                'licencias', 'puestos', 'inversion', 'no_autorizado', 'sin_licencia', 'licencia_vencida',
                'lic_por_vencer', 'lic_vencidas', 'programas_deficit', 'puestos_faltantes', 'sin_verificar', 'alertas'])),
            'por_categoria' => $s['por_categoria'],
            'por_autorizacion' => $porAut,
            'top' => $s['top'],
            'cumplimiento' => $s['cumplimiento'],
            'licencias' => $licencias,
        ];
    }

    // =================== ASIGNACIONES Y ACTAS ===================
    public static function asignaciones(array $r): array
    {
        Destinos::asegurar();
        $rg = [$r['ini'], $r['fin']];
        $hoy = date('Y-m-d');

        $entregas = Database::get(
            "SELECT SUBSTR(fecha_entrega,1,7) AS mes, COUNT(*) AS n FROM asignaciones
             WHERE fecha_entrega BETWEEN ? AND ? AND estado <> 'cancelada' GROUP BY SUBSTR(fecha_entrega,1,7)", $rg);
        $devol = Database::get(
            "SELECT SUBSTR(fecha_devolucion_real,1,7) AS mes, COUNT(*) AS n FROM asignaciones
             WHERE fecha_devolucion_real BETWEEN ? AND ? GROUP BY SUBSTR(fecha_devolucion_real,1,7)", $rg);
        $estados = Database::get("SELECT estado, COUNT(*) AS n FROM asignaciones GROUP BY estado ORDER BY n DESC");
        $porTipo = Database::get(
            "SELECT tipo, COUNT(*) AS n FROM asignaciones WHERE estado IN ('activa','vencida') GROUP BY tipo");
        $porDestino = Database::get(
            "SELECT destino_tipo AS tipo, COUNT(*) AS n FROM asignaciones WHERE estado IN ('activa','vencida')
             GROUP BY destino_tipo ORDER BY n DESC");

        $vencidas = Database::get(
            "SELECT e.codigo AS equipo, CONCAT(COALESCE(e.marca,''),' ',COALESCE(e.modelo,'')) AS equipo_desc,
                    a.destino_tipo, a.fecha_devolucion_esperada, a.tipo,
                    COALESCE(CONCAT(p.nombres,' ',p.apellidos), ar.nombre, py.nombre, '—') AS destino
             FROM asignaciones a INNER JOIN equipos e ON e.id = a.equipo_id
             LEFT JOIN personal p ON p.id = a.personal_id
             LEFT JOIN areas ar ON ar.id = a.area_id
             LEFT JOIN proyectos py ON py.id = a.proyecto_id
             WHERE a.estado IN ('activa','vencida') AND a.fecha_devolucion_esperada IS NOT NULL
               AND a.fecha_devolucion_esperada < ?
             ORDER BY a.fecha_devolucion_esperada ASC LIMIT 20", [$hoy]);
        foreach ($vencidas as &$v) {
            $v['dias_atraso'] = (int) floor((strtotime($hoy) - strtotime((string)$v['fecha_devolucion_esperada'])) / 86400);
        }
        unset($v);

        // actas
        $actas = Database::get(
            "SELECT SUBSTR(fecha,1,7) AS mes, COUNT(*) AS n FROM actas WHERE fecha BETWEEN ? AND ? GROUP BY SUBSTR(fecha,1,7)", $rg);
        $actasTipo = Database::get(
            "SELECT tipo, COUNT(*) AS n FROM actas WHERE fecha BETWEEN ? AND ? GROUP BY tipo ORDER BY n DESC", $rg);
        $actasFam = Database::get(
            "SELECT COALESCE(familia,'sin tipo') AS familia, COUNT(*) AS n FROM actas WHERE fecha BETWEEN ? AND ?
             GROUP BY COALESCE(familia,'sin tipo') ORDER BY n DESC", $rg);
        $pendientes = Database::get(
            "SELECT ac.codigo, ac.tipo, ac.fecha, COALESCE(ac.destino_nombre, CONCAT(p.nombres,' ',p.apellidos)) AS destino,
                    COALESCE(ac.equipos_resumen,'') AS equipos
             FROM actas ac LEFT JOIN personal p ON p.id = ac.personal_id
             WHERE ac.firmado = 0 ORDER BY ac.fecha ASC LIMIT 20");
        foreach ($pendientes as &$p) {
            $p['dias'] = (int) floor((strtotime($hoy) - strtotime(substr((string)$p['fecha'], 0, 10))) / 86400);
        }
        unset($p);

        $entMes = self::serie($r['meses'], $entregas);
        $devMes = self::serie($r['meses'], $devol);
        return [
            'rango' => ['desde' => $r['desde'], 'hasta' => $r['hasta']],
            'meses' => array_keys($r['meses']),
            'entregas' => $entMes, 'devoluciones' => $devMes, 'actas_mes' => self::serie($r['meses'], $actas),
            'estados' => $estados, 'por_tipo' => $porTipo, 'por_destino' => $porDestino,
            'actas_tipo' => $actasTipo, 'actas_familia' => $actasFam,
            'prestamos_vencidos' => $vencidas, 'actas_pendientes' => $pendientes,
            'tarjetas' => [
                'entregas' => array_sum($entMes),
                'devoluciones' => array_sum($devMes),
                'activas' => (int) Database::getValue("SELECT COUNT(*) FROM asignaciones WHERE estado IN ('activa','vencida')"),
                'prestamos' => (int) Database::getValue("SELECT COUNT(*) FROM asignaciones WHERE estado IN ('activa','vencida') AND tipo = 'prestamo'"),
                'vencidas' => count($vencidas) > 0
                    ? (int) Database::getValue(
                        "SELECT COUNT(*) FROM asignaciones WHERE estado IN ('activa','vencida')
                         AND fecha_devolucion_esperada IS NOT NULL AND fecha_devolucion_esperada < ?", [$hoy])
                    : 0,
                'actas' => array_sum(array_column($actas, 'n')),
                'actas_pendientes' => (int) Database::getValue("SELECT COUNT(*) FROM actas WHERE firmado = 0"),
                'solicitudes_pendientes' => (int) Database::getValue(
                    "SELECT COUNT(*) FROM solicitudes_equipo WHERE estado IN ('pendiente','aprobada')"),
            ],
        ];
    }

    // =================== PERSONAL ===================
    public static function personal(array $r, ?int $areaId = null): array
    {
        $a = $areaId ? ' AND p.area_id = ?' : '';
        $pa = $areaId ? [$areaId] : [];
        $rg = [$r['ini'], $r['fin']];

        $porArea = Database::get(
            "SELECT ar.nombre, COUNT(*) AS n FROM personal p INNER JOIN areas ar ON ar.id = p.area_id
             WHERE p.estado <> 'cesado'$a GROUP BY ar.id, ar.nombre ORDER BY n DESC", $pa);
        $altas = Database::get(
            "SELECT SUBSTR(p.fecha_ingreso,1,7) AS mes, COUNT(*) AS n FROM personal p
             WHERE p.fecha_ingreso BETWEEN ? AND ?$a GROUP BY SUBSTR(p.fecha_ingreso,1,7)", array_merge([$r['desde'], $r['hasta']], $pa));
        $ceses = Database::get(
            "SELECT SUBSTR(p.fecha_cese,1,7) AS mes, COUNT(*) AS n FROM personal p
             WHERE p.fecha_cese BETWEEN ? AND ?$a GROUP BY SUBSTR(p.fecha_cese,1,7)", array_merge([$r['desde'], $r['hasta']], $pa));
        $estados = Database::get(
            "SELECT p.estado, COUNT(*) AS n FROM personal p WHERE 1=1$a GROUP BY p.estado ORDER BY n DESC", $pa);

        $q = fn(string $sql, array $par = []) => (int) Database::getValue($sql, array_merge($par, $pa));
        return [
            'rango' => ['desde' => $r['desde'], 'hasta' => $r['hasta']],
            'meses' => array_keys($r['meses']),
            'altas' => self::serie($r['meses'], $altas), 'ceses' => self::serie($r['meses'], $ceses),
            'por_area' => $porArea, 'estados' => $estados,
            'tarjetas' => [
                'activos' => $q("SELECT COUNT(*) FROM personal p WHERE p.estado = 'activo'$a"),
                'pre_ingreso' => $q("SELECT COUNT(*) FROM personal p WHERE p.estado = 'pre_ingreso'$a"),
                'cesados' => $q("SELECT COUNT(*) FROM personal p WHERE p.estado = 'cesado'$a"),
                'con_equipos' => $q("SELECT COUNT(DISTINCT a2.personal_id) FROM asignaciones a2
                    INNER JOIN personal p ON p.id = a2.personal_id
                    WHERE a2.estado IN ('activa','vencida')$a"),
                'sin_equipo' => $q("SELECT COUNT(*) FROM personal p WHERE p.estado = 'activo'$a
                    AND NOT EXISTS (SELECT 1 FROM asignaciones a2 WHERE a2.personal_id = p.id AND a2.estado IN ('activa','vencida'))"),
            ],
        ];
    }

    // =================== RESUMEN EJECUTIVO ===================
    /** Una mirada para gerencia: indicadores de todos los modulos, tendencia y observaciones. */
    public static function resumen(array $r, ?int $areaId = null): array
    {
        $tk = self::tickets($r, $areaId);
        $pe = self::personal($r, $areaId);
        $out = [
            'rango' => ['desde' => $r['desde'], 'hasta' => $r['hasta'], 'dias' => $r['dias']],
            'meses' => array_keys($r['meses']),
            'tickets' => ['tarjetas' => $tk['tarjetas'], 'creados' => $tk['creados'], 'resueltos' => $tk['resueltos'],
                          'categorias' => array_slice($tk['categorias'], 0, 5)],
            'personal' => ['tarjetas' => $pe['tarjetas']],
            'alertas' => [],
        ];
        $al = &$out['alertas'];
        $t = $tk['tarjetas'];
        if ($t['sla_vencidos_ahora'] > 0) $al[] = ['nivel' => 'rojo', 'texto' => $t['sla_vencidos_ahora'] . ' ticket(s) con el SLA vencido sin resolver', 'ruta' => 'tickets/lista'];
        if ($t['sin_asignar'] > 0) $al[] = ['nivel' => 'ambar', 'texto' => $t['sin_asignar'] . ' ticket(s) abiertos sin técnico asignado', 'ruta' => 'tickets/lista'];
        if ($t['sla_pct'] !== null && $t['sla_pct'] < 80) $al[] = ['nivel' => 'ambar', 'texto' => 'Cumplimiento del SLA en ' . $t['sla_pct'] . '% (meta sugerida: 80% o más)', 'ruta' => 'reportes/principal'];

        if ($areaId) { unset($al); return $out; }

        $mt = self::mantenimiento($r);
        $inv = self::inventario($r);
        $sw = self::software($r);
        $as = self::asignaciones($r);
        $out['mantenimiento'] = ['tarjetas' => $mt['tarjetas'], 'costos' => $mt['costos'], 'cantidad' => $mt['cantidad']];
        $out['inventario'] = ['tarjetas' => $inv['tarjetas'], 'estados' => $inv['estados'], 'seguimiento' => $inv['seguimiento']];
        $out['software'] = ['tarjetas' => $sw['tarjetas']];
        $out['asignaciones'] = ['tarjetas' => $as['tarjetas'], 'entregas' => $as['entregas'], 'devoluciones' => $as['devoluciones']];

        $m = $mt['tarjetas'];
        if ($m['atrasados'] > 0) $al[] = ['nivel' => 'rojo', 'texto' => $m['atrasados'] . ' mantenimiento(s) con retorno atrasado', 'ruta' => 'mantenimiento/lista'];
        if ($m['preventivos_vencidos'] > 0) $al[] = ['nivel' => 'ambar', 'texto' => $m['preventivos_vencidos'] . ' preventivo(s) programados ya vencidos', 'ruta' => 'mantenimiento/lista'];
        $i = $inv['tarjetas'];
        if ($i['garantia90'] > 0) $al[] = ['nivel' => 'ambar', 'texto' => $i['garantia90'] . ' equipo(s) con la garantía por vencer en 90 días', 'ruta' => 'equipos/lista'];
        if ($inv['seguimiento']['rojo'] > 0) $al[] = ['nivel' => 'ambar', 'texto' => $inv['seguimiento']['rojo'] . ' equipo(s) de cómputo sin verificar su ubicación en más de 90 días', 'ruta' => 'equipos/seguimiento'];
        $a = $as['tarjetas'];
        if ($a['vencidas'] > 0) $al[] = ['nivel' => 'rojo', 'texto' => $a['vencidas'] . ' préstamo(s) o envío(s) con la devolución vencida', 'ruta' => 'asignaciones/lista'];
        if ($a['actas_pendientes'] > 0) $al[] = ['nivel' => 'ambar', 'texto' => $a['actas_pendientes'] . ' acta(s) pendientes de firma', 'ruta' => 'actas/lista'];
        $s = $sw['tarjetas'];
        if ($s['no_autorizado'] > 0) $al[] = ['nivel' => 'rojo', 'texto' => $s['no_autorizado'] . ' instalación(es) de software prohibido', 'ruta' => 'software/lista'];
        if ($s['sin_licencia'] > 0 || $s['licencia_vencida'] > 0) $al[] = ['nivel' => 'rojo', 'texto' => ($s['sin_licencia'] + $s['licencia_vencida']) . ' instalación(es) sin licencia o con licencia vencida', 'ruta' => 'software/lista'];
        if ($s['lic_por_vencer'] > 0) $al[] = ['nivel' => 'ambar', 'texto' => $s['lic_por_vencer'] . ' licencia(s) por vencer en 60 días', 'ruta' => 'software/lista'];
        if ($s['equipos_computo_sin'] > 0) $al[] = ['nivel' => 'ambar', 'texto' => $s['equipos_computo_sin'] . ' equipo(s) de cómputo sin software registrado', 'ruta' => 'software/lista'];
        unset($al);
        return $out;
    }

    // =================== EXPORTACION (filas planas) ===================
    /** [titulo, cabeceras, filas] de lo que se descarga en CSV / Excel. null si el modulo no se exporta. */
    public static function filas(string $modulo, array $r, ?int $areaId = null): ?array
    {
        $ESTADO_TK = ['abierto' => 'Abierto', 'asignado' => 'Asignado', 'en_atencion' => 'En atención', 'pendiente_usuario' => 'Pendiente usuario',
                      'pendiente_proveedor' => 'Pendiente proveedor', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado', 'cancelado' => 'Cancelado'];
        if ($modulo === 'tickets') {
            $d = self::tickets($r, $areaId, 20000)['detalle'];
            return ['Tickets', ['Código', 'Título', 'Solicitante', 'Área', 'Categoría', 'Prioridad', 'Técnico', 'Estado', 'Impacto',
                                'Creado', '1ra respuesta', 'Resuelto', 'SLA', 'Comentarios', 'Solución'],
                array_map(fn($t) => [$t['codigo'], $t['titulo'], $t['solicitante'], $t['area'], $t['categoria'], $t['prioridad'], $t['tecnico'],
                    $ESTADO_TK[$t['estado']] ?? $t['estado'], $t['impacto'], $t['fecha_creacion'], $t['fecha_primera_respuesta'],
                    $t['fecha_resolucion'], $t['sla_estado'], $t['comentarios'], preg_replace('/\s+/', ' ', (string)$t['solucion'])], $d)];
        }
        if ($modulo === 'mantenimiento') {
            $d = self::mantenimiento($r, 20000)['detalle'];
            return ['Mantenimientos', ['Código', 'Equipo', 'Tipo de equipo', 'Descripción', 'Tipo', 'Estado', 'Técnico', 'Proveedor', 'Destino',
                                       'Inicio', 'Fin', 'Retorno estimado', 'Mano de obra', 'Repuestos', 'Total', 'Solución'],
                array_map(fn($t) => [$t['codigo'], $t['equipo'], $t['equipo_tipo'], trim($t['equipo_desc']), $t['tipo'], str_replace('_', ' ', $t['estado']),
                    $t['tecnico'], $t['proveedor'], $t['destino'], $t['fecha_inicio'], $t['fecha_fin'], $t['fecha_retorno_estimada'],
                    $t['costo_mano_obra'], $t['costo_repuestos'], $t['costo_total'], preg_replace('/\s+/', ' ', (string)$t['solucion'])], $d)];
        }
        if ($modulo === 'inventario') {
            $d = Database::get(
                "SELECT e.codigo, t.nombre AS tipo, e.marca, e.modelo, e.nro_serie, e.activo_fijo, e.estado, e.condicion,
                        e.fecha_compra, e.proveedor_compra, COALESCE(e.costo,0) AS costo, e.garantia_hasta, e.ubicacion,
                        COALESCE(CONCAT(p.nombres,' ',p.apellidos), ar.nombre, py.nombre, '') AS asignado_a
                 FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
                 LEFT JOIN asignaciones a ON a.equipo_id = e.id AND a.estado IN ('activa','vencida')
                 LEFT JOIN personal p ON p.id = a.personal_id
                 LEFT JOIN areas ar ON ar.id = a.area_id
                 LEFT JOIN proyectos py ON py.id = a.proyecto_id
                 WHERE e.estado <> 'dado_de_baja' ORDER BY e.codigo");
            return ['Inventario', ['Código', 'Tipo', 'Marca', 'Modelo', 'Serie', 'Activo fijo', 'Estado', 'Condición', 'Fecha de compra',
                                   'Proveedor', 'Costo', 'Garantía hasta', 'Ubicación', 'Asignado a'],
                array_map(fn($x) => [$x['codigo'], $x['tipo'], $x['marca'], $x['modelo'], $x['nro_serie'], $x['activo_fijo'],
                    str_replace('_', ' ', (string)$x['estado']), $x['condicion'], $x['fecha_compra'], $x['proveedor_compra'], $x['costo'],
                    $x['garantia_hasta'], $x['ubicacion'], $x['asignado_a']], $d)];
        }
        if ($modulo === 'asignaciones') {
            $d = Database::get(
                "SELECT a.id, e.codigo AS equipo, CONCAT(COALESCE(e.marca,''),' ',COALESCE(e.modelo,'')) AS equipo_desc,
                        a.destino_tipo, COALESCE(CONCAT(p.nombres,' ',p.apellidos), ar.nombre, py.nombre, '') AS destino,
                        a.tipo, a.estado, a.fecha_entrega, a.fecha_devolucion_esperada, a.fecha_devolucion_real,
                        a.condicion_entrega, a.condicion_devolucion
                 FROM asignaciones a INNER JOIN equipos e ON e.id = a.equipo_id
                 LEFT JOIN personal p ON p.id = a.personal_id LEFT JOIN areas ar ON ar.id = a.area_id
                 LEFT JOIN proyectos py ON py.id = a.proyecto_id
                 WHERE a.fecha_entrega BETWEEN ? AND ? ORDER BY a.fecha_entrega DESC LIMIT 2000", [$r['ini'], $r['fin']]);
            return ['Asignaciones', ['Equipo', 'Descripción', 'Destino', 'Nombre del destino', 'Tipo', 'Estado', 'Entrega', 'Devolución esperada',
                                     'Devolución real', 'Condición entrega', 'Condición devolución'],
                array_map(fn($x) => [$x['equipo'], trim($x['equipo_desc']), $x['destino_tipo'], $x['destino'], $x['tipo'], $x['estado'],
                    $x['fecha_entrega'], $x['fecha_devolucion_esperada'], $x['fecha_devolucion_real'], $x['condicion_entrega'], $x['condicion_devolucion']], $d)];
        }
        if ($modulo === 'personal') {
            $a = $areaId ? ' AND p.area_id = ?' : '';
            $d = Database::get(
                "SELECT p.dni, CONCAT(p.nombres,' ',p.apellidos) AS nombre, ar.nombre AS area, p.cargo, p.estado, p.fecha_ingreso, p.fecha_cese,
                        (SELECT COUNT(*) FROM asignaciones x WHERE x.personal_id = p.id AND x.estado IN ('activa','vencida')) AS equipos
                 FROM personal p LEFT JOIN areas ar ON ar.id = p.area_id WHERE 1=1$a ORDER BY p.apellidos, p.nombres",
                $areaId ? [$areaId] : []);
            return ['Personal', ['DNI', 'Nombre', 'Área', 'Cargo', 'Estado', 'Ingreso', 'Cese', 'Equipos asignados'],
                array_map(fn($x) => [$x['dni'], $x['nombre'], $x['area'], $x['cargo'], $x['estado'], $x['fecha_ingreso'], $x['fecha_cese'], $x['equipos']], $d)];
        }
        if ($modulo === 'software') {
            $c = self::software($r)['cumplimiento'];
            return ['Cumplimiento de software', ['Programa', 'Categoría', 'Autorización', 'Instaladas', 'Puestos vigentes', 'Déficit', 'Estado'],
                array_map(fn($x) => [$x['nombre'], Software::etiquetaCategoria($x['categoria']), $x['autorizacion'], $x['instaladas'],
                    $x['requiere_licencia'] ? $x['puestos_vigentes'] : 'No requiere', $x['deficit'], $x['estado']], $c)];
        }
        return null;
    }
}
