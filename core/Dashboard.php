<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Dashboard: indicadores, alertas y graficos segun lo que cada persona puede ver.
// Lo usan api/dashboard/indicadores.php y api/dashboard/graficos.php.
// SQL portable: las fechas viajan como parametros.
// ============================================================

class Dashboard
{
    /**
     * Que tickets puede ver quien consulta: 'todos' (tickets.ver), 'area' (tickets.ver_area),
     * 'propios' (tickets.ver_propios) o null si ninguno. Devuelve [modo, SQL extra con alias t, parametros].
     */
    public static function alcanceTickets(): ?array
    {
        if (Auth::can('tickets', 'ver')) return ['todos', '', []];
        $personal = Database::getValue('SELECT personal_id FROM usuarios WHERE id = ?', [Auth::userId()]);
        if (Auth::can('tickets', 'ver_area')) {
            $area = $personal ? Database::getValue('SELECT area_id FROM personal WHERE id = ?', [(int)$personal]) : null;
            if ($area) return ['area', ' AND t.area_afectada_id = ?', [(int)$area]];
        }
        if (Auth::can('tickets', 'ver_propios') && $personal) {
            return ['propios', ' AND (t.solicitante_personal_id = ? OR t.tecnico_usuario_id = ?)', [(int)$personal, Auth::userId()]];
        }
        return null;
    }

    private static function n(string $sql, array $p = []): int
    {
        return (int) Database::getValue($sql, $p);
    }

    /** Indicadores, observaciones y accesos rapidos del Dashboard. */
    public static function indicadores(): array
    {
        $hoy = date('Y-m-d');
        $ahora = date('Y-m-d H:i:s');
        $hace30 = date('Y-m-d H:i:s', strtotime('-30 days'));
        $en30 = date('Y-m-d', strtotime('+30 days'));
        $en90 = date('Y-m-d', strtotime('+90 days'));
        $uid = Auth::userId();
        $puede = fn(string $m, string $a = 'ver') => Licencia::moduloActivo($m) && Auth::can($m, $a);
        $out = ['generado' => date('d/m/Y H:i:s'), 'mios' => 0, 'alertas' => [], 'rapidos' => []];
        $al = [];

        // ---------------- tickets ----------------
        $alc = self::alcanceTickets();
        if ($alc) {
            [$modo, $ex, $px] = $alc;
            $abiertos = "t.estado NOT IN ('resuelto','cerrado','cancelado')";
            $tk = [
                'alcance' => $modo,
                'abiertos' => self::n("SELECT COUNT(*) FROM tickets t WHERE $abiertos$ex", $px),
                'sin_asignar' => $modo === 'todos'
                    ? self::n("SELECT COUNT(*) FROM tickets t WHERE t.estado = 'abierto' AND t.tecnico_usuario_id IS NULL") : null,
                'sla_vencidos' => self::n("SELECT COUNT(*) FROM tickets t WHERE $abiertos AND t.sla_limite < ?$ex", array_merge([$ahora], $px)),
                'creados_30' => self::n("SELECT COUNT(*) FROM tickets t WHERE t.fecha_creacion >= ?$ex", array_merge([$hace30], $px)),
                'resueltos_30' => self::n("SELECT COUNT(*) FROM tickets t WHERE t.fecha_resolucion >= ?$ex", array_merge([$hace30], $px)),
                'mios' => self::n("SELECT COUNT(*) FROM tickets t WHERE t.tecnico_usuario_id = ? AND $abiertos", [$uid]),
            ];
            $out['tickets'] = $tk;
            $out['actividad'] = Database::get(
                "SELECT th.evento, th.detalle, th.created_at, t.codigo, t.titulo, u.nombre_completo AS usuario
                 FROM ticket_historial th INNER JOIN tickets t ON t.id = th.ticket_id
                 LEFT JOIN usuarios u ON u.id = th.usuario_id
                 WHERE 1=1$ex ORDER BY th.id DESC LIMIT 8", $px);
            $out['mios'] = $tk['mios'];
            if ($tk['sla_vencidos'] > 0) $al[] = ['nivel' => 'rojo', 'n' => $tk['sla_vencidos'], 'texto' => $tk['sla_vencidos'] . ' ticket(s) con el SLA vencido', 'ruta' => 'tickets/lista', 'icono' => 'bi-alarm'];
            if (($tk['sin_asignar'] ?? 0) > 0) $al[] = ['nivel' => 'ambar', 'n' => $tk['sin_asignar'], 'texto' => $tk['sin_asignar'] . ' ticket(s) abierto(s) sin técnico', 'ruta' => 'tickets/lista', 'icono' => 'bi-person-dash'];
        }

        // ---------------- equipos ----------------
        if ($puede('equipos')) {
            $out['equipos'] = [
                'total' => self::n("SELECT COUNT(*) FROM equipos WHERE estado <> 'dado_de_baja'"),
                'stock' => self::n("SELECT COUNT(*) FROM equipos WHERE estado = 'en_stock'"),
                'asignados' => self::n("SELECT COUNT(*) FROM equipos WHERE estado IN ('asignado','en_prestamo')"),
                'mantenimiento' => self::n("SELECT COUNT(*) FROM equipos WHERE estado IN ('en_mantenimiento','en_reparacion_externa','en_revision')"),
                'obsoletos' => self::n("SELECT COUNT(*) FROM equipos WHERE estado = 'obsoleto'"),
                'garantia30' => self::n("SELECT COUNT(*) FROM equipos WHERE garantia_hasta BETWEEN ? AND ? AND estado <> 'dado_de_baja'", [$hoy, $en30]),
                'valorizado' => $puede('reportes') ? (float) (Database::getValue("SELECT COALESCE(SUM(costo),0) FROM equipos WHERE estado <> 'dado_de_baja'") ?? 0) : null,
            ];
            $g = $out['equipos']['garantia30'];
            if ($g > 0) $al[] = ['nivel' => 'ambar', 'n' => $g, 'texto' => $g . ' equipo(s) con la garantía por vencer en 30 días', 'ruta' => 'equipos/lista', 'icono' => 'bi-shield-exclamation'];
            $sin = self::n("SELECT COUNT(*) FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
                            WHERE t.familia = 'computo' AND e.estado <> 'dado_de_baja'
                              AND (e.verificado_ubicacion IS NULL OR e.verificado_ubicacion < ?)", [date('Y-m-d H:i:s', strtotime('-90 days'))]);
            $out['equipos']['sin_verificar'] = $sin;
            if ($sin > 0) $al[] = ['nivel' => 'ambar', 'n' => $sin, 'texto' => $sin . ' equipo(s) de cómputo sin verificar su ubicación en 90 días', 'ruta' => 'equipos/seguimiento', 'icono' => 'bi-geo-alt'];
        }

        // ---------------- mantenimiento ----------------
        if ($puede('mantenimiento')) {
            $mt = [
                'activos' => self::n("SELECT COUNT(*) FROM mantenimientos WHERE estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')"),
                'atrasados' => self::n("SELECT COUNT(*) FROM mantenimientos WHERE fecha_retorno_estimada < ? AND estado IN ('enviado_proveedor','cotizado','aprobado','en_reparacion')", [$hoy]),
                'preventivos_vencidos' => self::n("SELECT COUNT(*) FROM mantenimientos_programados WHERE activo = 1 AND mantenimiento_actual_id IS NULL AND proxima_fecha < ?", [$hoy]),
                'costo_mes' => $puede('reportes') ? (float) (Database::getValue(
                    "SELECT COALESCE(SUM(costo_total),0) FROM mantenimientos WHERE fecha_inicio >= ? AND estado <> 'cancelado'", [date('Y-m-01')]) ?? 0) : null,
            ];
            $out['mantenimiento'] = $mt;
            if ($mt['atrasados'] > 0) $al[] = ['nivel' => 'rojo', 'n' => $mt['atrasados'], 'texto' => $mt['atrasados'] . ' mantenimiento(s) con el retorno atrasado', 'ruta' => 'mantenimiento/lista', 'icono' => 'bi-tools'];
            if ($mt['preventivos_vencidos'] > 0) $al[] = ['nivel' => 'ambar', 'n' => $mt['preventivos_vencidos'], 'texto' => $mt['preventivos_vencidos'] . ' preventivo(s) programados ya vencidos', 'ruta' => 'mantenimiento/lista', 'icono' => 'bi-calendar-x'];
        }

        // ---------------- asignaciones / actas ----------------
        if ($puede('asignaciones')) {
            $as = [
                'activas' => self::n("SELECT COUNT(*) FROM asignaciones WHERE estado IN ('activa','vencida')"),
                'vencidas' => self::n("SELECT COUNT(*) FROM asignaciones WHERE estado IN ('activa','vencida') AND fecha_devolucion_esperada IS NOT NULL AND fecha_devolucion_esperada < ?", [$hoy]),
                'solicitudes' => self::n("SELECT COUNT(*) FROM solicitudes_equipo WHERE estado IN ('pendiente','aprobada')"),
            ];
            $out['asignaciones'] = $as;
            if ($as['vencidas'] > 0) $al[] = ['nivel' => 'rojo', 'n' => $as['vencidas'], 'texto' => $as['vencidas'] . ' devolución(es) de equipos vencida(s)', 'ruta' => 'asignaciones/lista', 'icono' => 'bi-box-arrow-in-left'];
            if ($as['solicitudes'] > 0) $al[] = ['nivel' => 'ambar', 'n' => $as['solicitudes'], 'texto' => $as['solicitudes'] . ' solicitud(es) de equipo por atender', 'ruta' => 'asignaciones/lista', 'icono' => 'bi-inbox'];
        }
        if ($puede('actas', 'generar')) {
            $out['actas'] = ['pendientes' => self::n("SELECT COUNT(*) FROM actas WHERE firmado = 0")];
            if ($out['actas']['pendientes'] > 0) $al[] = ['nivel' => 'ambar', 'n' => $out['actas']['pendientes'], 'texto' => $out['actas']['pendientes'] . ' acta(s) pendientes de firma', 'ruta' => 'actas/lista', 'icono' => 'bi-pen'];
        }

        // ---------------- software ----------------
        if ($puede('software')) {
            $s = Software::resumen();
            $out['software'] = ['cobertura' => $s['cobertura'], 'instalaciones' => $s['instalaciones'], 'alertas' => $s['alertas'],
                                'lic_por_vencer' => $s['lic_por_vencer'], 'no_autorizado' => $s['no_autorizado'],
                                'sin_licencia' => $s['sin_licencia'] + $s['licencia_vencida'], 'equipos_sin' => $s['equipos_computo_sin']];
            if ($s['no_autorizado'] > 0) $al[] = ['nivel' => 'rojo', 'n' => $s['no_autorizado'], 'texto' => $s['no_autorizado'] . ' instalación(es) de software prohibido', 'ruta' => 'software/lista', 'icono' => 'bi-slash-circle'];
            $sl = $s['sin_licencia'] + $s['licencia_vencida'];
            if ($sl > 0) $al[] = ['nivel' => 'rojo', 'n' => $sl, 'texto' => $sl . ' instalación(es) sin licencia o con licencia vencida', 'ruta' => 'software/lista', 'icono' => 'bi-key'];
            if ($s['lic_por_vencer'] > 0) $al[] = ['nivel' => 'ambar', 'n' => $s['lic_por_vencer'], 'texto' => $s['lic_por_vencer'] . ' licencia(s) por vencer en 60 días', 'ruta' => 'software/lista', 'icono' => 'bi-hourglass-split'];
            if ($s['equipos_computo_sin'] > 0) $al[] = ['nivel' => 'ambar', 'n' => $s['equipos_computo_sin'], 'texto' => $s['equipos_computo_sin'] . ' equipo(s) de cómputo sin software registrado', 'ruta' => 'software/lista', 'icono' => 'bi-app-indicator'];
        }

        // ---------------- capacitaciones ----------------
        try {
            Capacitaciones::asegurar();
            $cp = Licencia::moduloActivo('capacitaciones') ? Capacitaciones::alcance() : null;
            if ($cp) {
                [$cex, $cpx] = Capacitaciones::condicion($cp, 'c');
                $cap = [
                    'en_curso' => self::n("SELECT COUNT(*) FROM capacitaciones WHERE solicitante_usuario_id = ? AND estado IN ('solicitada','en_revision','programada')", [$uid]),
                    'por_calificar' => self::n("SELECT COUNT(*) FROM capacitaciones WHERE solicitante_usuario_id = ? AND estado = 'realizada' AND calificacion IS NULL", [$uid]),
                ];
                if ($cp['modo'] !== 'propias') {
                    $cap['nuevas'] = self::n("SELECT COUNT(*) FROM capacitaciones c WHERE c.estado = 'solicitada'$cex", $cpx);
                    $cap['por_cerrar'] = self::n("SELECT COUNT(*) FROM capacitaciones c WHERE c.estado = 'programada' AND c.fecha_programada < ?$cex", array_merge([$ahora], $cpx));
                }
                $out['capacitaciones'] = $cap;
                if (Auth::can('capacitaciones', 'gestionar')) {
                    if (($cap['nuevas'] ?? 0) > 0) $al[] = ['nivel' => 'ambar', 'n' => $cap['nuevas'], 'texto' => $cap['nuevas'] . ' solicitud(es) de capacitación sin revisar', 'ruta' => 'capacitaciones/lista', 'icono' => 'bi-mortarboard'];
                    if (($cap['por_cerrar'] ?? 0) > 0) $al[] = ['nivel' => 'ambar', 'n' => $cap['por_cerrar'], 'texto' => $cap['por_cerrar'] . ' capacitación(es) ya dictada(s) por cerrar', 'ruta' => 'capacitaciones/lista', 'icono' => 'bi-calendar-check'];
                }
                if ($cap['por_calificar'] > 0) $al[] = ['nivel' => 'ambar', 'n' => $cap['por_calificar'], 'texto' => $cap['por_calificar'] . ' capacitación(es) esperan su calificación', 'ruta' => 'capacitaciones/lista', 'icono' => 'bi-star'];
            }
        } catch (Throwable $e) { error_log('[SIGTI] Dashboard capacitaciones: ' . $e->getMessage()); }

        // ---------------- personal ----------------
        if ($puede('personal')) {
            $out['personal'] = [
                'activos' => self::n("SELECT COUNT(*) FROM personal WHERE estado = 'activo'"),
                'por_ingresar' => self::n("SELECT COUNT(*) FROM personal WHERE estado = 'pre_ingreso'"),
                'ceses_proximos' => self::n("SELECT COUNT(*) FROM personal WHERE fecha_cese IS NOT NULL AND fecha_cese >= ? AND fecha_cese <= ?
                                             AND estado IN ('activo','cese_programado','en_proceso_cese')", [$hoy, $en30]),
                'onboarding' => self::n("SELECT COUNT(DISTINCT p.id) FROM personal p INNER JOIN personal_checklist pc ON pc.personal_id = p.id
                                         WHERE pc.completado = 0 AND p.estado IN ('pre_ingreso','activo')"),
            ];
            $c = $out['personal']['ceses_proximos'];
            if ($c > 0) $al[] = ['nivel' => 'ambar', 'n' => $c, 'texto' => $c . ' cese(s) en los próximos 30 días (recoger equipos)', 'ruta' => 'personal/lista', 'icono' => 'bi-person-dash'];
        }

        // ---------------- RR.HH. ampliado (contratos, vacaciones y requerimiento de personal) ----------------
        try {
            if (Licencia::moduloActivo('rrhh')) {
                if (Auth::can('rrhh', 'ver')) {
                    Contratos::asegurar();
                    Vacaciones::asegurar();
                    $rrhh = [
                        'contratos_por_vencer' => count(Contratos::porVencer(30)),
                        'contratos_vencidos'   => self::n("SELECT COUNT(*) FROM personal_contratos WHERE vigente = 1 AND fecha_fin IS NOT NULL AND fecha_fin < ?", [$hoy]),
                        'vacaciones_pendientes'=> self::n("SELECT COUNT(*) FROM rrhh_vacaciones WHERE estado = 'pendiente'"),
                    ];
                    $out['rrhh'] = $rrhh;
                    if (Auth::can('rrhh', 'gestionar')) {
                        if ($rrhh['contratos_vencidos'] > 0) $al[] = ['nivel' => 'rojo', 'n' => $rrhh['contratos_vencidos'], 'texto' => $rrhh['contratos_vencidos'] . ' contrato(s) vencido(s) sin renovar ni finalizar', 'ruta' => 'rrhh/contratos', 'icono' => 'bi-file-earmark-x'];
                        if ($rrhh['contratos_por_vencer'] > 0) $al[] = ['nivel' => 'ambar', 'n' => $rrhh['contratos_por_vencer'], 'texto' => $rrhh['contratos_por_vencer'] . ' contrato(s) por vencer en 30 días', 'ruta' => 'rrhh/contratos', 'icono' => 'bi-file-earmark-person'];
                        if ($rrhh['vacaciones_pendientes'] > 0) $al[] = ['nivel' => 'ambar', 'n' => $rrhh['vacaciones_pendientes'], 'texto' => $rrhh['vacaciones_pendientes'] . ' solicitud(es) de vacaciones por revisar', 'ruta' => 'rrhh/vacaciones', 'icono' => 'bi-airplane'];
                    }
                }

                // Requerimiento de personal: cualquier area que pide (solicitar/ver_area) o Sistemas/RR.HH. (ver/gestionar)
                if (Auth::can('rrhh', 'solicitar') || Auth::can('rrhh', 'ver_area') || Auth::can('rrhh', 'ver') || Auth::can('rrhh', 'gestionar')) {
                    Requerimientos::asegurar();
                    if (Auth::can('rrhh', 'ver') || Auth::can('rrhh', 'gestionar')) {
                        $reqPend = self::n("SELECT COUNT(*) FROM rrhh_requerimientos WHERE estado IN ('pendiente','en_proceso')");
                    } elseif (Auth::can('rrhh', 'ver_area') && ($areaReq = Requerimientos::areaDelUsuario($uid))) {
                        $reqPend = self::n("SELECT COUNT(*) FROM rrhh_requerimientos WHERE estado IN ('pendiente','en_proceso') AND (area_id = ? OR solicitante_usuario_id = ?)", [$areaReq, $uid]);
                    } else {
                        $reqPend = self::n("SELECT COUNT(*) FROM rrhh_requerimientos WHERE estado IN ('pendiente','en_proceso') AND solicitante_usuario_id = ?", [$uid]);
                    }
                    $out['rrhh_requerimientos'] = ['pendientes' => $reqPend];
                    if (Auth::can('rrhh', 'gestionar')) {
                        $reqAtrasados = self::n("SELECT COUNT(*) FROM rrhh_requerimientos WHERE estado IN ('pendiente','en_proceso') AND fecha_limite IS NOT NULL AND fecha_limite < ?", [$hoy]);
                        $reqPorTomar = self::n("SELECT COUNT(*) FROM rrhh_requerimientos WHERE estado = 'pendiente'");
                        if ($reqAtrasados > 0) $al[] = ['nivel' => 'rojo', 'n' => $reqAtrasados, 'texto' => $reqAtrasados . ' requerimiento(s) de personal atrasado(s) (venció el plazo)', 'ruta' => 'rrhh/requerimientos', 'icono' => 'bi-person-fill-exclamation'];
                        if ($reqPorTomar > 0) $al[] = ['nivel' => 'ambar', 'n' => $reqPorTomar, 'texto' => $reqPorTomar . ' requerimiento(s) de personal por atender', 'ruta' => 'rrhh/requerimientos', 'icono' => 'bi-person-plus'];
                        $puestosPend = self::n("SELECT COUNT(*) FROM rrhh_puesto_catalogo WHERE estado = 'pendiente'");
                        if ($puestosPend > 0) $al[] = ['nivel' => 'ambar', 'n' => $puestosPend, 'texto' => $puestosPend . ' puesto(s) nuevo(s) propuesto(s) por validar en el catálogo', 'ruta' => 'rrhh/requerimientos', 'icono' => 'bi-person-vcard'];
                    }
                }
            }
        } catch (Throwable $e) { error_log('[SIGTI] Dashboard rrhh: ' . $e->getMessage()); }

        // rojas primero
        usort($al, fn($a, $b) => ($a['nivel'] === 'rojo' ? 0 : 1) <=> ($b['nivel'] === 'rojo' ? 0 : 1));
        $out['alertas'] = $al;

        // accesos rapidos segun permisos: [ruta, icono, texto]
        $rap = [];
        if (Auth::can('tickets', 'crear')) $rap[] = ['tickets/lista', 'bi-plus-circle', 'Nuevo ticket'];
        if ($puede('equipos')) $rap[] = ['equipos/lista', 'bi-pc-display', 'Equipos'];
        if ($puede('asignaciones')) $rap[] = ['asignaciones/lista', 'bi-box-seam', 'Asignaciones'];
        if ($puede('mantenimiento')) $rap[] = ['mantenimiento/lista', 'bi-tools', 'Mantenimiento'];
        if ($puede('software')) $rap[] = ['software/lista', 'bi-app-indicator', 'Software'];
        if (Auth::can('reportes', 'ver') || Auth::can('reportes', 'ver_area')) $rap[] = ['reportes/principal', 'bi-graph-up-arrow', 'Reportes'];
        if (isset($out['capacitaciones'])) $rap[] = ['capacitaciones/lista', 'bi-mortarboard', Auth::can('capacitaciones', 'solicitar') ? 'Pedir capacitación' : 'Capacitaciones'];
        if (isset($out['rrhh'])) $rap[] = ['rrhh/contratos', 'bi-person-badge', 'RR.HH.'];
        elseif (isset($out['rrhh_requerimientos'])) $rap[] = ['rrhh/requerimientos', 'bi-person-plus', 'Pedir personal'];
        if (Licencia::moduloActivo('chat')) $rap[] = ['chat/principal', 'bi-chat-dots', 'Chat'];
        $rap[] = ['guias/principal', 'bi-journal-text', 'Guías'];
        $out['rapidos'] = $rap;
        return $out;
    }

    /** Series para los graficos del Dashboard (solo lo que puede ver). */
    public static function graficos(): array
    {
        $out = [];
        // ultimos 6 meses (de enero a diciembre si cambia de anio)
        $meses = []; $etq = [];
        $cur = new DateTime('first day of this month -5 months');
        for ($i = 0; $i < 6; $i++) { $meses[$cur->format('Y-m')] = 0; $etq[] = $cur->format('Y-m'); $cur->modify('+1 month'); }
        $desde = array_key_first($meses) . '-01 00:00:00';
        $serie = function (array $filas) use ($meses) {
            foreach ($filas as $f) if (isset($meses[$f['m']])) $meses[$f['m']] = (float)$f['n'];
            return array_values($meses);
        };
        $out['meses'] = $etq;

        $alc = self::alcanceTickets();
        if ($alc) {
            [, $ex, $px] = $alc;
            $out['tickets'] = [
                'creados' => array_map('intval', $serie(Database::get(
                    "SELECT SUBSTR(t.fecha_creacion,1,7) AS m, COUNT(*) AS n FROM tickets t WHERE t.fecha_creacion >= ?$ex
                     GROUP BY SUBSTR(t.fecha_creacion,1,7)", array_merge([$desde], $px)))),
                'resueltos' => array_map('intval', $serie(Database::get(
                    "SELECT SUBSTR(t.fecha_resolucion,1,7) AS m, COUNT(*) AS n FROM tickets t WHERE t.fecha_resolucion >= ?$ex
                     GROUP BY SUBSTR(t.fecha_resolucion,1,7)", array_merge([$desde], $px)))),
                'prioridad' => Database::get(
                    "SELECT p.nombre, p.color, COUNT(*) AS n FROM tickets t INNER JOIN prioridades p ON p.id = t.prioridad_id
                     WHERE t.estado NOT IN ('resuelto','cerrado','cancelado')$ex
                     GROUP BY p.id, p.nombre, p.color, p.nivel ORDER BY p.nivel", $px),
            ];
        }
        if (Auth::can('equipos', 'ver')) {
            $out['equipos_estado'] = Database::get(
                "SELECT estado, COUNT(*) AS n FROM equipos WHERE estado <> 'dado_de_baja' GROUP BY estado ORDER BY n DESC");
        }
        if (Licencia::moduloActivo('mantenimiento') && Auth::can('mantenimiento', 'ver')) {
            $out['mantenimiento'] = [
                'cantidad' => array_map('intval', $serie(Database::get(
                    "SELECT SUBSTR(fecha_inicio,1,7) AS m, COUNT(*) AS n FROM mantenimientos
                     WHERE fecha_inicio >= ? AND estado <> 'cancelado' GROUP BY SUBSTR(fecha_inicio,1,7)", [$desde]))),
            ];
            if (Auth::can('reportes', 'ver')) {
                $out['mantenimiento']['costo'] = $serie(Database::get(
                    "SELECT SUBSTR(fecha_inicio,1,7) AS m, COALESCE(SUM(costo_total),0) AS n FROM mantenimientos
                     WHERE fecha_inicio >= ? AND estado <> 'cancelado' GROUP BY SUBSTR(fecha_inicio,1,7)", [$desde]));
            }
        }
        return $out;
    }
}
