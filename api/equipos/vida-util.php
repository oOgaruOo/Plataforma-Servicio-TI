<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Vida util de equipos: antiguedad, % consumido, semaforo
// GET accion=panel [&familia=computo] | accion=equipo&id=X
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('equipos', 'ver');

function calcularVida(array $e, int $alerta = 75): array
{
    $anos = (int)($e['vida_util_anos'] ?: 4);
    $compra = $e['fecha_compra'];

    $base = [
        'id' => (int)$e['id'], 'codigo' => $e['codigo'], 'tipo' => $e['tipo'],
        'marca' => $e['marca'], 'modelo' => $e['modelo'], 'estado' => $e['estado'],
        'vida_util' => $anos, 'costo' => $e['costo'],
    ];

    if (!$compra) {
        return array_merge($base, [
            'fecha_compra' => null, 'antiguedad_anos' => null, 'pct' => null,
            'fin_vida' => null, 'sem' => 'sin_fecha', 'sem_txt' => 'Sin fecha de compra',
        ]);
    }

    $inicio = new DateTime($compra);
    $hoy = new DateTime();
    $antiguedad = $hoy->diff($inicio)->days / 365.25;
    $pct = round(100 * $antiguedad / $anos);
    $fin = (clone $inicio)->modify("+{$anos} years")->format('Y-m-d');

    $sem = 'ok'; $semTxt = $pct . '% de vida';
    if ($pct >= 100)          { $sem = 'vencido'; $semTxt = 'VIDA UTIL VENCIDA'; }
    elseif ($pct >= $alerta)  { $sem = 'critico'; $semTxt = $pct . '% - renovar pronto'; }

    return array_merge($base, [
        'fecha_compra' => $compra,
        'antiguedad_anos' => round($antiguedad, 1),
        'pct' => $pct, 'fin_vida' => $fin,
        'sem' => $sem, 'sem_txt' => $semTxt,
    ]);
}

 $accion = $_GET['accion'] ?? 'panel';

if ($accion === 'equipo') {
    $id = (int)($_GET['id'] ?? 0);
    $e = Database::getOne(
        "SELECT e.id, e.codigo, e.marca, e.modelo, e.fecha_compra, e.costo, e.estado,
                t.vida_util_anos, t.nombre AS tipo
         FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         WHERE e.id = ?", [$id]);
    if (!$e) Response::error('Equipo no existe.');
    $alerta = (int) (Database::getValue(
        "SELECT valor FROM configuracion WHERE clave = 'vida_util_alerta_pct'") ?: 75);
    Response::ok(calcularVida($e, $alerta));
}

 $familia = $_GET['familia'] ?? '';

 $equipos = Database::get(
    "SELECT e.id, e.codigo, e.marca, e.modelo, e.fecha_compra, e.costo, e.estado,
            t.vida_util_anos, t.nombre AS tipo
     FROM equipos e
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE e.estado <> 'dado_de_baja'" .
     ($familia !== '' ? " AND t.familia = ?" : "") .
     " ORDER BY e.fecha_compra ASC LIMIT 1000",
    $familia !== '' ? [$familia] : []);

 $alerta = (int) (Database::getValue(
    "SELECT valor FROM configuracion WHERE clave = 'vida_util_alerta_pct'") ?: 75);

 $resultado = array_map(fn($e) => calcularVida($e, $alerta), $equipos);

Response::ok([
    'equipos' => $resultado,
    'stats' => [
        'total'     => count($resultado),
        'vencidos'  => count(array_filter($resultado, fn($r) => $r['sem'] === 'vencido')),
        'criticos'  => count(array_filter($resultado, fn($r) => $r['sem'] === 'critico')),
        'normales'  => count(array_filter($resultado, fn($r) => $r['sem'] === 'ok')),
    ],
    'alerta_pct' => $alerta,
]);