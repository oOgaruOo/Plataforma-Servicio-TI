<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Proximos cumpleanos del personal
// GET ?dias=30 (1 a 366)  -> lista ordenada por cercania
// Solo personal vigente (activo / en cese). Hoy cuenta como dia 0.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('personal', 'ver');
Esquema::columna('personal', 'fecha_nacimiento', 'DATE NULL');

$dias = (int)($_GET['dias'] ?? 30);
if ($dias < 1)   $dias = 30;
if ($dias > 366) $dias = 366;

$filas = Database::get(
    "SELECT p.id, p.nombres, p.apellidos, p.cargo, p.fecha_nacimiento, a.nombre AS area
     FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id
     WHERE p.fecha_nacimiento IS NOT NULL
       AND p.estado IN ('activo','cese_programado','en_proceso_cese')");

$hoy = new DateTimeImmutable('today');
$lista = [];
foreach ($filas as $f) {
    $faltan = Cumpleanos::dias((string)$f['fecha_nacimiento'], $hoy);
    if ($faltan === null || $faltan > $dias) continue;
    $prox = Cumpleanos::proximo((string)$f['fecha_nacimiento'], $hoy);
    $lista[] = [
        'id'       => (int)$f['id'],
        'nombre'   => trim($f['nombres'] . ' ' . $f['apellidos']),
        'area'     => $f['area'],
        'cargo'    => $f['cargo'],
        'proximo'  => $prox->format('Y-m-d'),
        'dias'     => $faltan,
        'cumple'   => Cumpleanos::edadEn((string)$f['fecha_nacimiento'], $prox),
    ];
}
usort($lista, fn($a, $b) => [$a['dias'], $a['nombre']] <=> [$b['dias'], $b['nombre']]);

Response::ok(['dias' => $dias, 'hoy' => $hoy->format('Y-m-d'), 'lista' => $lista]);
