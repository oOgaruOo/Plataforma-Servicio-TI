<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Menú lateral: insignias de pendientes por módulo (solo lo que la persona puede ver)
// GET -> { insignias: { "<ruta>": { n, nivel: rojo|ambar|azul, titulo } } }
// Reutiliza las observaciones del Dashboard (core/Dashboard.php), así el menú y el panel siempre coinciden.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
header('Cache-Control: no-store');

try {
    $d = Dashboard::indicadores();
} catch (Throwable $e) {
    error_log('[SIGTI] menu/insignias: ' . $e->getMessage() . ' ' . basename($e->getFile()) . ':' . $e->getLine());
    Response::ok(['insignias' => (object)[]]);
}

$m = [];
foreach (($d['alertas'] ?? []) as $a) {
    $ruta = (string)($a['ruta'] ?? '');
    $n = (int)($a['n'] ?? 0);
    if ($ruta === '' || $n <= 0) continue;
    if (!isset($m[$ruta])) $m[$ruta] = ['n' => 0, 'nivel' => 'ambar', 'titulo' => []];
    $m[$ruta]['n'] += $n;
    if (($a['nivel'] ?? '') === 'rojo') $m[$ruta]['nivel'] = 'rojo';
    $m[$ruta]['titulo'][] = (string)($a['texto'] ?? '');
}
// tickets asignados a la persona (informativo, en azul) si no hay una observación mayor
$mios = (int)($d['tickets']['mios'] ?? 0);
if ($mios > 0 && !isset($m['tickets/lista'])) {
    $m['tickets/lista'] = ['n' => $mios, 'nivel' => 'azul', 'titulo' => [$mios . ' ticket(s) asignado(s) a usted']];
}
foreach ($m as &$x) $x['titulo'] = implode(' · ', array_slice($x['titulo'], 0, 3));
unset($x);

Response::ok(['insignias' => $m ?: (object)[]]);
