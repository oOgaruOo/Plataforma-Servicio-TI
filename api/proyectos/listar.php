<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Proyectos y Obras: lista con su plazo y los equipos que tienen enviados
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('asignaciones', 'ver');
Destinos::asegurar();

 $hoy = date('Y-m-d');
 $filas = Database::get(
    "SELECT p.id, p.codigo, p.nombre, p.tipo, p.cliente, p.ubicacion, p.descripcion, p.responsable_id,
            p.fecha_inicio, p.fecha_fin, p.fecha_cierre, p.estado,
            CONCAT(r.nombres, ' ', r.apellidos) AS responsable,
            (SELECT COUNT(*) FROM asignaciones a WHERE a.proyecto_id = p.id
                AND a.estado IN ('activa','vencida')) AS n_equipos,
            (SELECT COUNT(*) FROM asignaciones a WHERE a.proyecto_id = p.id
                AND a.estado IN ('activa','vencida') AND a.fecha_devolucion_esperada < ?) AS n_vencidos,
            (SELECT MIN(a.fecha_devolucion_esperada) FROM asignaciones a WHERE a.proyecto_id = p.id
                AND a.estado IN ('activa','vencida')) AS prox_devolucion,
            (SELECT COUNT(*) FROM asignaciones a WHERE a.proyecto_id = p.id) AS n_historico
     FROM proyectos p
     LEFT JOIN personal r ON r.id = p.responsable_id
     ORDER BY (p.estado = 'activo') DESC, p.fecha_fin IS NULL, p.fecha_fin ASC, p.nombre ASC", [$hoy]);

 $st = ['activos' => 0, 'en_curso' => 0, 'vencidos' => 0, 'equipos' => 0, 'devoluciones_vencidas' => 0, 'finalizados' => 0];
foreach ($filas as &$f) {
    $f['situacion'] = Destinos::situacion($f, $hoy);
    $f['dias_fin'] = $f['fecha_fin'] ? Destinos::dias((string)$f['fecha_fin'], $hoy) : null;
    $f['n_equipos'] = (int)$f['n_equipos'];
    $f['n_vencidos'] = (int)$f['n_vencidos'];
    $f['n_historico'] = (int)$f['n_historico'];
    if ($f['estado'] === 'activo') {
        $st['activos']++;
        if ($f['situacion'] === 'en_curso' || $f['situacion'] === 'por_vencer') $st['en_curso']++;
        if ($f['situacion'] === 'vencido') $st['vencidos']++;
        $st['equipos'] += $f['n_equipos'];
        $st['devoluciones_vencidas'] += $f['n_vencidos'];
    } else {
        $st['finalizados']++;
    }
}
unset($f);

Response::ok(['lista' => $filas, 'stats' => $st, 'hoy' => $hoy]);
