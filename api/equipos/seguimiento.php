<?php
// ============================================================
// SIGTI - Seguimiento de laptops/PCs (COMPLETO v3)
// Incluye: area_directa (asignaciones a areas) + semaforo
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('equipos', 'ver');

 $accion = $_GET['accion'] ?? ($_POST['accion'] ?? '');

if ($accion === 'panel' && $_SERVER['REQUEST_METHOD'] === 'GET') {

    $fVerif = $_GET['f_verif'] ?? 'todos';
    $condVerif = '';
    if ($fVerif === 'vencidos30') $condVerif = ' AND (e.verificado_ubicacion IS NULL OR e.verificado_ubicacion < DATE_SUB(NOW(), INTERVAL 30 DAY))';
    if ($fVerif === 'vencidos90') $condVerif = ' AND (e.verificado_ubicacion IS NULL OR e.verificado_ubicacion < DATE_SUB(NOW(), INTERVAL 90 DAY))';

    $equipos = Database::get(
        "SELECT e.id, e.codigo, e.marca, e.modelo, e.nro_serie, e.estado,
                e.ubicacion, e.verificado_ubicacion,
                CONCAT(p.nombres, ' ', p.apellidos) AS asignado,
                p.cargo AS asignado_cargo, ar.nombre AS area_asignado,
                ar2.nombre AS area_directa,
                DATEDIFF(NOW(), e.verificado_ubicacion) AS dias_sin_verificar
         FROM equipos e
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         LEFT JOIN (
             SELECT asig.equipo_id, asig.personal_id, asig.area_id
             FROM asignaciones asig WHERE asig.estado IN ('activa','vencida')
         ) act ON act.equipo_id = e.id
         LEFT JOIN personal p ON p.id = act.personal_id
         LEFT JOIN areas ar ON ar.id = p.area_id
         LEFT JOIN areas ar2 ON ar2.id = act.area_id
         WHERE t.familia = 'computo' AND e.estado <> 'dado_de_baja'" .
         $condVerif . "
         ORDER BY e.verificado_ubicacion IS NULL DESC, e.verificado_ubicacion ASC
         LIMIT 500");

    foreach ($equipos as &$e) {
        $dias = $e['dias_sin_verificar'] === null ? null : (int)$e['dias_sin_verificar'];
        if ($dias === null) { $e['sem'] = 'rojo';  $e['sem_txt'] = 'Nunca'; }
        elseif ($dias > 90) { $e['sem'] = 'rojo';  $e['sem_txt'] = $dias . 'd'; }
        elseif ($dias > 30) { $e['sem'] = 'ambar'; $e['sem_txt'] = $dias . 'd'; }
        else                { $e['sem'] = 'verde'; $e['sem_txt'] = $dias . 'd'; }
        $e['dias_sin_verificar'] = $dias;
    }
    unset($e);

    $whereC = "t.familia = 'computo' AND e.estado <> 'dado_de_baja'";
    $stats = [
        'total' => (int) Database::getValue(
            "SELECT COUNT(*) FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id WHERE $whereC"),
        'verde' => (int) Database::getValue(
            "SELECT COUNT(*) FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             WHERE $whereC AND e.verificado_ubicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        'ambar' => (int) Database::getValue(
            "SELECT COUNT(*) FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             WHERE $whereC AND e.verificado_ubicacion < DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND e.verificado_ubicacion >= DATE_SUB(NOW(), INTERVAL 90 DAY)"),
        'rojo'  => (int) Database::getValue(
            "SELECT COUNT(*) FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             WHERE $whereC AND (e.verificado_ubicacion IS NULL OR e.verificado_ubicacion < DATE_SUB(NOW(), INTERVAL 90 DAY))"),
    ];

    Response::ok(['equipos' => $equipos, 'stats' => $stats]);
}

if ($accion === 'historial' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $equipoId = (int)($_GET['equipo_id'] ?? 0);
    if ($equipoId === 0) Response::error('Equipo no especificado.');

    $historial = Database::get(
        "SELECT eu.ubicacion, eu.tipo, eu.observaciones, eu.created_at,
                CONCAT(p.nombres, ' ', p.apellidos) AS con_quien,
                CONCAT(u.nombre_completo, '') AS registrado_por
         FROM equipo_ubicaciones eu
         LEFT JOIN personal p ON p.id = eu.personal_id
         LEFT JOIN usuarios u ON u.id = eu.usuario_id
         WHERE eu.equipo_id = ?
         ORDER BY eu.id DESC LIMIT 100", [$equipoId]);

    Response::ok($historial);
}

Csrf::validate();
Auth::requirePermission('equipos', 'editar');

if ($accion === 'verificar') {
    $equipoId = (int)($_POST['equipo_id'] ?? 0);
    $e = Database::getOne('SELECT id, codigo, ubicacion FROM equipos WHERE id = ?', [$equipoId]);
    if (!$e) Response::error('El equipo no existe.');

    $ubicacion = trim((string)($_POST['ubicacion'] ?? '')) ?: ($e['ubicacion'] ?: 'Sin ubicacion');

    Database::update('equipos', [
        'verificado_ubicacion' => date('Y-m-d H:i:s'),
        'ubicacion'            => $ubicacion,
    ], 'id = ?', [$equipoId]);

    Database::insert('equipo_ubicaciones', [
        'equipo_id'     => $equipoId,
        'ubicacion'     => mb_substr($ubicacion, 0, 150),
        'tipo'          => 'verificacion',
        'observaciones' => 'Verificacion de ubicacion',
        'usuario_id'    => Auth::userId(),
    ]);

    Response::ok(null, 'Ubicacion verificada: ' . $e['codigo'] . ' esta en «' . $ubicacion . '».');

} elseif ($accion === 'trasladar') {
    $equipoId  = (int)($_POST['equipo_id'] ?? 0);
    $ubicacion = trim((string)($_POST['ubicacion'] ?? ''));
    $obs       = trim((string)($_POST['observaciones'] ?? ''));

    if ($ubicacion === '') Response::validation(['ubicacion' => 'Indique la nueva ubicacion.']);

    $e = Database::getOne('SELECT id, codigo, ubicacion, estado FROM equipos WHERE id = ?', [$equipoId]);
    if (!$e) Response::error('El equipo no existe.');
    if ($e['estado'] === 'dado_de_baja') Response::error('Equipo dado de baja.');

    $anterior = $e['ubicacion'] ?: '—';

    Database::update('equipos', [
        'ubicacion'            => mb_substr($ubicacion, 0, 100),
        'verificado_ubicacion' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$equipoId]);

    Database::insert('equipo_ubicaciones', [
        'equipo_id'     => $equipoId,
        'ubicacion'     => mb_substr($ubicacion, 0, 150),
        'tipo'          => 'traslado',
        'observaciones' => mb_substr('De «' . $anterior . '»' . ($obs ? ' · ' . $obs : ''), 0, 250),
        'usuario_id'    => Auth::userId(),
    ]);

    Response::ok(null, 'Equipo ' . $e['codigo'] . ' trasladado de «' . $anterior . '» a «' . $ubicacion . '».');

} else {
    Response::error('Accion invalida.');
}