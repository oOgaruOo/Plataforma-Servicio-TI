<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Ficha de un proyecto / obra: plazo, equipos enviados (con su devolucion estipulada)
//         e historial de equipos ya devueltos
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('asignaciones', 'ver');
Destinos::asegurar();

 $id = (int)($_GET['id'] ?? 0);
 $p = Database::getOne(
    "SELECT p.*, CONCAT(r.nombres, ' ', r.apellidos) AS responsable
     FROM proyectos p LEFT JOIN personal r ON r.id = p.responsable_id WHERE p.id = ?", [$id]);
if (!$p) Response::error('El proyecto u obra no existe.');

 $hoy = date('Y-m-d');
 $p['situacion'] = Destinos::situacion($p, $hoy);
 $p['dias_fin'] = $p['fecha_fin'] ? Destinos::dias((string)$p['fecha_fin'], $hoy) : null;
 $p['dias_total'] = ($p['fecha_inicio'] && $p['fecha_fin'])
    ? max(1, Destinos::dias((string)$p['fecha_fin'], (string)$p['fecha_inicio'])) : null;
 $p['dias_usados'] = $p['fecha_inicio'] ? max(0, -Destinos::dias((string)$p['fecha_inicio'], $hoy)) : null;

 $sel = "SELECT a.id, a.equipo_id, a.estado, a.fecha_entrega, a.fecha_devolucion_esperada, a.fecha_devolucion_real,
                a.condicion_devolucion, a.destino_detalle,
                (SELECT ac.token FROM actas ac WHERE ac.asignacion_id = a.id ORDER BY ac.id DESC LIMIT 1) AS acta_token,
                e.codigo AS equipo_codigo, e.marca, e.modelo, t.nombre AS equipo_tipo,
                CONCAT(dr.nombres, ' ', dr.apellidos) AS responsable
         FROM asignaciones a
         INNER JOIN equipos e ON e.id = a.equipo_id
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         LEFT JOIN personal dr ON dr.id = a.responsable_id
         WHERE a.proyecto_id = ? ";

 $enviados = Database::get($sel . "AND a.estado IN ('activa','vencida') ORDER BY a.fecha_devolucion_esperada ASC, a.id ASC", [$id]);
foreach ($enviados as &$e) {
    $dev = $e['fecha_devolucion_esperada'] ? Destinos::dias((string)$e['fecha_devolucion_esperada'], $hoy) : null;
    $e['dias_devolucion'] = $dev;
    $e['vencida'] = ($dev !== null && $dev < 0) ? 1 : 0;
    // devolucion despues del fin del proyecto (el plazo se acorto)
    $e['fuera_de_plazo'] = ($dev !== null && $p['fecha_fin'] && substr((string)$e['fecha_devolucion_esperada'], 0, 10) > substr((string)$p['fecha_fin'], 0, 10)) ? 1 : 0;
}
unset($e);

 $devueltos = Database::get($sel . "AND a.estado = 'devuelta' ORDER BY a.fecha_devolucion_real DESC, a.id DESC LIMIT 100", [$id]);
foreach ($devueltos as &$e) {
    $e['atraso_dias'] = null;
    if ($e['fecha_devolucion_esperada'] && $e['fecha_devolucion_real']) {
        $e['atraso_dias'] = max(0, Destinos::dias((string)$e['fecha_devolucion_real'], (string)$e['fecha_devolucion_esperada']));
    }
}
unset($e);

 $p['enviados'] = $enviados;
 $p['devueltos'] = $devueltos;
 $p['n_vencidos'] = count(array_filter($enviados, function ($x) { return $x['vencida']; }));
 $p['puede'] = [
    'editar'  => Auth::can('asignaciones', 'crear'),
    'asignar' => Auth::can('asignaciones', 'crear') && $p['estado'] === 'activo',
    'devolver'=> Auth::can('asignaciones', 'devolver'),
];

Response::ok($p);
