<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// Preventivos programados: guardar / toggle / ejecutar (genera el mantenimiento)
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Programados::asegurar();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Auth::requirePermission('mantenimiento', 'ver');
    $lista = Database::get(
        "SELECT mp.*, e.codigo AS equipo_codigo, e.marca, e.modelo, t.nombre AS equipo_tipo,
                CONCAT(u.nombre_completo, '') AS responsable
         FROM mantenimientos_programados mp
         INNER JOIN equipos e ON e.id = mp.equipo_id
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         LEFT JOIN usuarios u ON u.id = mp.responsable_id
         ORDER BY mp.activo DESC, mp.proxima_fecha ASC");
    $hoy = date('Y-m-d');
    foreach ($lista as &$p) {
        $p['ciclo'] = Programados::estadoCiclo($p, $hoy);
        $p['dias_para_proxima'] = Programados::dias((string)$p['proxima_fecha'], $hoy);
        $p['mantenimiento_actual'] = $p['mantenimiento_actual_id']
            ? Database::getOne('SELECT id, codigo, estado FROM mantenimientos WHERE id = ?', [$p['mantenimiento_actual_id']]) : null;
        $p['ultimo_mantenimiento'] = $p['ultimo_mantenimiento_id']
            ? Database::getOne('SELECT id, codigo, fecha_fin, resultado_final, atraso_min FROM mantenimientos WHERE id = ?', [$p['ultimo_mantenimiento_id']]) : null;
    }
    unset($p);
    Response::ok($lista);
}

Csrf::validate();
Auth::requirePermission('mantenimiento', 'crear');

 $accion = $_POST['accion'] ?? '';
 $MESES = ['mensual' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12];

if ($accion === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);

    $v = Validator::make($_POST, [
        'equipo_id'     => 'required|integer',
        'tipo'          => 'required|in:preventivo,limpieza,actualizacion',
        'frecuencia'    => 'required|in:mensual,trimestral,semestral,anual',
        'proxima_fecha' => 'required|date',
    ], ['equipo_id' => 'Equipo', 'tipo' => 'Tipo', 'frecuencia' => 'Frecuencia',
        'proxima_fecha' => 'Próxima fecha']);
    if ($v->fails()) Response::validation($v->errors());

    $eq = Database::getOne('SELECT id, codigo, estado FROM equipos WHERE id = ?', [(int)$_POST['equipo_id']]);
    if (!$eq || $eq['estado'] === 'dado_de_baja') {
        Response::validation(['equipo_id' => 'El equipo no existe o está dado de baja.']);
    }

    $respId = null;
    if (trim((string)($_POST['responsable_id'] ?? '')) !== '') {
        $respId = (int)$_POST['responsable_id'];
        if (!Database::getValue('SELECT id FROM usuarios WHERE id = ?', [$respId])) {
            Response::validation(['responsable_id' => 'El responsable no existe.']);
        }
    }

    $datos = [
        'equipo_id'      => (int)$_POST['equipo_id'],
        'tipo'           => $_POST['tipo'],
        'frecuencia'     => $_POST['frecuencia'],
        'proxima_fecha'  => $_POST['proxima_fecha'],
        'responsable_id' => $respId,
        'activo'         => 1,
    ];

    if ($id > 0) {
        $antes = Database::getOne('SELECT * FROM mantenimientos_programados WHERE id = ?', [$id]);
        if (!$antes) Response::error('El programado no existe.');
        unset($datos['activo']);
        // si cambia la fecha, el aviso previo debe poder enviarse de nuevo para la fecha nueva
        if (substr((string)$antes['proxima_fecha'], 0, 10) !== $datos['proxima_fecha']) $datos['aviso_para'] = null;
        Database::update('mantenimientos_programados', $datos, 'id = ?', [$id]);
        Auditoria::registrar('actualizar', 'mantenimientos_programados', $id, $antes, $datos);
        Response::ok(null, 'Programación actualizada.');
    }

    $nuevo = Database::insert('mantenimientos_programados', $datos);
    Auditoria::registrar('crear', 'mantenimientos_programados', $nuevo, null, $datos);
    Response::ok(null, 'Mantenimiento programado registrado.');

} elseif ($accion === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $p = Database::getOne('SELECT * FROM mantenimientos_programados WHERE id = ?', [$id]);
    if (!$p) Response::error('El programado no existe.');
    $nuevo = ((int)$p['activo'] === 1) ? 0 : 1;
    Database::update('mantenimientos_programados', ['activo' => $nuevo], 'id = ?', [$id]);
    Response::ok(null, $nuevo ? 'Programación activada.' : 'Programación pausada.');

} elseif ($accion === 'ejecutar') {
    // Genera YA el mantenimiento de este ciclo (sin esperar la fecha). La proxima fecha NO se mueve
    // ahora: se calcula sola cuando ese mantenimiento se FINALICE.
    $id = (int)($_POST['id'] ?? 0);
    $p = Database::getOne('SELECT * FROM mantenimientos_programados WHERE id = ?', [$id]);
    if (!$p || (int)$p['activo'] !== 1) Response::error('El programado no existe o está pausado.');

    if (!empty($p['mantenimiento_actual_id'])) {
        $cod = Database::getValue('SELECT codigo FROM mantenimientos WHERE id = ?', [$p['mantenimiento_actual_id']]);
        Response::error('Esta programación ya tiene el mantenimiento ' . ($cod ?: '') . ' en curso: finalícelo para que se calcule la próxima fecha.');
    }
    $eq = Database::getOne('SELECT id, estado, codigo FROM equipos WHERE id = ?', [$p['equipo_id']]);
    if (!$eq || $eq['estado'] === 'dado_de_baja') {
        Response::error('El equipo fue dado de baja: pause o elimine esta programación.');
    }
    $enCurso = Database::getValue(
        "SELECT id FROM mantenimientos WHERE equipo_id = ?
         AND estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')",
        [$p['equipo_id']]);
    if ($enCurso) Response::error('El equipo ya tiene un mantenimiento en curso.');

    Database::begin();
    try {
        $g = Programados::generar($p, Auth::userId(), 'Mantenimiento programado');
        if (!Auditoria::registrar('crear', 'mantenimientos', $g['id'], null,
            ['origen' => 'programado', 'programado_id' => (int)$p['id']])) {
            Database::rollback();
            Response::error('No se pudo registrar la auditoría; no se generó el mantenimiento.', null, 500);
        }
        Database::commit();
    } catch (Throwable $e) {
        Database::rollback();
        Response::error('Error al ejecutar: ' . $e->getMessage());
    }

    $msg = "Mantenimiento {$g['codigo']} generado. Al finalizarlo, la próxima vez se programará "
         . 'automáticamente (' . $p['frecuencia'] . ').';
    Response::ok(['id' => $g['id'], 'codigo' => $g['codigo'], 'mensaje' => $msg], $msg);

} else {
    Response::error('Acción inválida.');
}