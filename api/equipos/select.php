<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Equipos para <select> de otros modulos
//   modo=disponibles  equipos en stock y en buen estado (opcional ?tipo_equipo_id=)
//   modo=asignados    equipos con asignacion vigente; filtros opcionales:
//                     ?destino=persona|area|proyecto|obra&ref=<id>  (los de ese destino)
//                     ?tipo_equipo_id=
//   modo=activos      todo lo que no esta dado de baja
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $modo = $_GET['modo'] ?? 'disponibles';
 $tipoEq = (int)($_GET['tipo_equipo_id'] ?? 0);

 $desc = "CONCAT(e.codigo, ' — ', t.nombre, ' ', COALESCE(e.marca, ''), ' ', COALESCE(e.modelo, ''))";

if ($modo === 'disponibles') {
    $par = [];
    $filtro = '';
    if ($tipoEq > 0) { $filtro = ' AND e.tipo_equipo_id = ?'; $par[] = $tipoEq; }
    $lista = Database::get(
        "SELECT e.id, $desc AS texto, e.nro_serie, t.familia
         FROM equipos e
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         WHERE e.estado = 'en_stock' AND e.condicion IN ('nuevo','bueno','regular')$filtro
         ORDER BY t.nombre, e.marca, e.codigo", $par);
    foreach ($lista as &$f) {
        if (!empty($f['nro_serie'])) $f['texto'] .= ' (S/N ' . $f['nro_serie'] . ')';
        unset($f['nro_serie']);
    }
    unset($f);

} elseif ($modo === 'asignados') {
    Destinos::asegurar();
    $where = "a.estado IN ('activa','vencida') AND e.estado IN ('asignado','en_prestamo')";
    $par = [];
    $destino = (string)($_GET['destino'] ?? '');
    $ref = (int)($_GET['ref'] ?? 0);
    if ($destino !== '') {
        if (!isset(Destinos::TIPOS[$destino]) || $ref <= 0) {
            Response::ok([]);
        }
        $where .= ' AND a.destino_tipo = ? AND ' . ($destino === 'persona' ? 'a.personal_id' : ($destino === 'area' ? 'a.area_id' : 'a.proyecto_id')) . ' = ?';
        $par[] = $destino;
        $par[] = $ref;
    }
    if ($tipoEq > 0) { $where .= ' AND e.tipo_equipo_id = ?'; $par[] = $tipoEq; }
    $lista = Database::get(
        "SELECT e.id, $desc AS texto, t.familia, a.id AS asignacion_id, a.destino_tipo,
                " . Destinos::SQL_NOMBRE . " AS destino_nombre
         FROM asignaciones a
         INNER JOIN equipos e ON e.id = a.equipo_id
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id" . Destinos::SQL_JOIN . "
         WHERE $where
         ORDER BY t.nombre, e.codigo", $par);
    foreach ($lista as &$f) {
        $f['texto'] .= ' → ' . Destinos::nombreTipo($f['destino_tipo']) . ': ' . $f['destino_nombre'];
    }
    unset($f);

} elseif ($modo === 'activos') {
    $lista = Database::get(
        "SELECT e.id, $desc AS texto, e.estado, t.familia
         FROM equipos e
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         WHERE e.estado <> 'dado_de_baja'
         ORDER BY e.codigo DESC");
} else {
    Response::error('Modo desconocido.');
    exit;
}

Response::ok($lista);
