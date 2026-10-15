<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Render de plantilla con datos de equipo(s)
// GET ?plantilla=X&ids=1,2,3  -> elementos con variables resueltas
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('equipos', 'ver');

 $plantillaId = (int)($_GET['plantilla'] ?? 0);
 $idsRaw = trim((string)($_GET['ids'] ?? ''));
 $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw)), fn($i) => $i > 0));

if ($plantillaId === 0 || !$ids) Response::error('Parámetros insuficientes.');

 $p = Database::getOne('SELECT * FROM etiquetas_plantillas WHERE id = ? AND activa = 1', [$plantillaId]);
if (!$p) Response::error('La plantilla no existe.');

 $equipos = Database::get(
    "SELECT e.*, t.nombre AS tipo, a.ubicacion
     FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     LEFT JOIN (
        SELECT asig.equipo_id, eq.ubicacion
        FROM asignaciones asig
        INNER JOIN equipos eq ON eq.id = asig.equipo_id
        WHERE asig.estado IN ('activa','vencida')
        GROUP BY asig.equipo_id
     ) a ON a.equipo_id = e.id
     WHERE e.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
    $ids
);

 $elementos = json_decode($p['elementos'], true) ?: [];

// resolver variables: {codigo}, {serie}, {tipo}, {marca}, {modelo}, {activo_fijo}, {ubicacion}, {url_consulta}
 $resultado = [];
foreach ($equipos as $eq) {
    $vars = [
        '{codigo}'      => $eq['codigo'],
        '{codigo_compacto}' => str_replace(' ', '', $eq['codigo']),
        '{serie}'       => $eq['nro_serie'] ?: '—',
        '{tipo}'        => $eq['tipo'],
        '{marca}'       => $eq['marca'] ?: '—',
        '{modelo}'      => $eq['modelo'] ?: '—',
        '{activo_fijo}' => $eq['activo_fijo'] ?: '—',
        '{ubicacion}'   => $eq['ubicacion'] ?: '—',
        '{area}'        => '—',
        '{piso}'        => '—',
        '{url_consulta}' => BASE_URL . 'acta.php?eq=' . $eq['codigo'],
    ];
    $els = [];
    foreach ($elementos as $el) {
        $el['contenido'] = str_replace(array_keys($vars), array_values($vars), $el['contenido']);
        $els[] = $el;
    }
    $resultado[] = ['equipo' => $eq['codigo'], 'elementos' => $els];
}

Response::ok([
    'plantilla' => [
        'nombre' => $p['nombre'],
        'ancho_mm' => (int)$p['ancho_mm'],
        'alto_mm' => (int)$p['alto_mm'],
    ],
    'etiquetas' => $resultado,
]);