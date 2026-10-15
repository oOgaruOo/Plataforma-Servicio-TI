<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Finalizar / reabrir un proyecto u obra
//   Finalizar exige que ya no tenga equipos enviados (devueltos o trasladados).
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('asignaciones', 'crear');
Destinos::asegurar();

 $id = (int)($_POST['id'] ?? 0);
 $accion = (string)($_POST['accion'] ?? '');
 $p = Database::getOne('SELECT * FROM proyectos WHERE id = ?', [$id]);
if (!$p) Response::error('El proyecto u obra no existe.');
 $que = $p['tipo'] === 'obra' ? 'La obra' : 'El proyecto';

if ($accion === 'finalizar') {
    if ($p['estado'] !== 'activo') Response::error("$que ya está finalizado.");
    $n = (int)Database::getValue(
        "SELECT COUNT(*) FROM asignaciones WHERE proyecto_id = ? AND estado IN ('activa','vencida')", [$id]);
    if ($n > 0) {
        Response::error(($p['tipo'] === 'obra' ? 'La obra' : 'El proyecto') . " tiene $n equipo(s) sin devolver: "
            . 'recíbalos (o trasládelos) en Asignaciones antes de finalizar.');
    }
    $datos = ['estado' => 'inactivo', 'fecha_cierre' => date('Y-m-d')];
    Database::update('proyectos', $datos, 'id = ?', [$id]);
    Auditoria::registrar('cambiar_estado', 'proyectos', $id, ['estado' => $p['estado']], $datos);
    $msg = "$que {$p['codigo']} quedó FINALIZADO.";
    Response::ok(['estado' => 'inactivo', 'mensaje' => $msg], $msg);
}

if ($accion === 'reabrir') {
    if ($p['estado'] === 'activo') Response::error("$que ya está abierto.");
    $datos = ['estado' => 'activo', 'fecha_cierre' => null];
    $fin = trim((string)($_POST['fecha_fin'] ?? ''));
    $hoy = date('Y-m-d');
    if ($fin !== '') {
        $f = DateTime::createFromFormat('Y-m-d', $fin);
        if (!$f || $f->format('Y-m-d') !== $fin) Response::validation(['fecha_fin' => 'Fecha inválida.']);
        if ($p['fecha_inicio'] && $fin < substr((string)$p['fecha_inicio'], 0, 10)) {
            Response::validation(['fecha_fin' => 'El fin no puede ser anterior al inicio.']);
        }
        $datos['fecha_fin'] = $fin;
    } elseif (!$p['fecha_fin'] || substr((string)$p['fecha_fin'], 0, 10) < $hoy) {
        Response::validation(['fecha_fin' => 'Indique la nueva fecha de fin para reabrirlo (la anterior ya pasó).']);
    }
    Database::update('proyectos', $datos, 'id = ?', [$id]);
    Auditoria::registrar('cambiar_estado', 'proyectos', $id, ['estado' => $p['estado']], $datos);
    $msg = "$que {$p['codigo']} fue REABIERTO.";
    Response::ok(['estado' => 'activo', 'mensaje' => $msg], $msg);
}

Response::error('Acción inválida.');
