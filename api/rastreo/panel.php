<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Panel de RASTREO EN VIVO
// Equipos que tienen IP o MAC registrada (o rastreo activado), con su ultimo reporte:
// en linea / visto hoy / sin senal / sin agente, donde se vio por ultima vez y si estan
// marcados como perdidos o robados.
// GET (equipos.ver)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('equipos', 'ver');
    Destinos::asegurar();
    Rastreo::asegurar();

    $filas = Database::get(
        "SELECT e.id, e.codigo, e.marca, e.modelo, e.nro_serie, e.mac, e.imei, e.estado,
                e.ubicacion, e.verificado_ubicacion, e.especificaciones,
                t.nombre AS tipo, t.familia,
                rc.equipo_id AS tiene_config, rc.activo AS rastreo_activo, rc.ultimo_checkin,
                rc.modo_perdido, rc.perdido_desde, rc.perdido_motivo, rc.perdido_nota,
                er.ip_publica, er.ip_local, er.mac_address, er.hostname,
                er.ubicacion AS ubic_rastreo, er.latitud, er.longitud, er.fuente, er.precision_m,
                er.wifi, er.mac_coincide, er.created_at AS ultimo_reporte,
                s.nombre AS sede,
                a.destino_tipo, a.destino_detalle,
                " . Destinos::SQL_NOMBRE . " AS destino_nombre,
                CONCAT(dr.nombres, ' ', dr.apellidos) AS destino_responsable
         FROM equipos e
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         LEFT JOIN equipos_rastreo_config rc ON rc.equipo_id = e.id
         LEFT JOIN equipos_rastreo er ON er.id = (SELECT MAX(x.id) FROM equipos_rastreo x WHERE x.equipo_id = e.id)
         LEFT JOIN rastreo_sedes s ON s.id = er.sede_id
         LEFT JOIN asignaciones a ON a.id = (
             SELECT MAX(av.id) FROM asignaciones av
             WHERE av.equipo_id = e.id AND av.estado IN ('activa','vencida')
         )" . Destinos::SQL_JOIN . "
         WHERE (e.estado <> 'dado_de_baja' OR rc.activo = 1)
           AND ((e.mac IS NOT NULL AND e.mac <> '')
                OR e.especificaciones LIKE '%\"ip_asignada\"%'
                OR rc.equipo_id IS NOT NULL)
         ORDER BY e.codigo
         LIMIT 500");

    $stats = ['total' => 0, 'en_linea' => 0, 'reciente' => 0, 'sin_senal' => 0, 'sin_agente' => 0, 'perdidos' => 0];
    $ahora = time();
    foreach ($filas as &$f) {
        $tiene = $f['tiene_config'] !== null && (int)$f['rastreo_activo'] === 1;
        $con = Rastreo::conexion($tiene, $f['ultimo_checkin'], $ahora);
        $f['conexion']      = $con['clave'];
        $f['minutos']       = $con['minutos'];
        $f['hace']          = $con['hace'];
        $f['tiene_agente']  = $tiene ? 1 : 0;
        $f['perdido']       = (int)$f['modo_perdido'] === 1 ? 1 : 0;
        $f['ip_registrada'] = Rastreo::ipRegistrada($f['especificaciones']);
        $f['mac']           = Rastreo::normalizarMac($f['mac']) ?: ($f['mac'] ?: null);
        $f['lat']           = $f['latitud'] !== null ? (float)$f['latitud'] : null;
        $f['lon']           = $f['longitud'] !== null ? (float)$f['longitud'] : null;
        unset($f['especificaciones'], $f['latitud'], $f['longitud'], $f['tiene_config']);

        $stats['total']++;
        $stats[$con['clave']]++;
        if ($f['perdido']) $stats['perdidos']++;
    }
    unset($f);

    // perdidos primero, luego los que estan en linea, luego el resto por antiguedad
    $peso = ['en_linea' => 1, 'reciente' => 2, 'sin_senal' => 3, 'sin_agente' => 4];
    usort($filas, function ($a, $b) use ($peso) {
        if ($a['perdido'] !== $b['perdido']) return $b['perdido'] <=> $a['perdido'];
        $pa = $peso[$a['conexion']]; $pb = $peso[$b['conexion']];
        if ($pa !== $pb) return $pa <=> $pb;
        return strcmp((string)$a['codigo'], (string)$b['codigo']);
    });

    $sedes = Database::get(
        'SELECT id, nombre, ip_publica, prefijo_local, direccion, latitud, longitud, activo FROM rastreo_sedes WHERE activo = 1 ORDER BY nombre');

    Response::ok([
        'equipos' => $filas,
        'stats' => $stats,
        'sedes' => $sedes,
        'puede_editar' => Auth::can('equipos', 'editar'),
    ]);

} catch (Throwable $e) {
    error_log('[SIGTI][RASTREO] panel: ' . $e->getMessage());
    Response::error('No se pudo cargar el panel de rastreo.', null, 500);
}
