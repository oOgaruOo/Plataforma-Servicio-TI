<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Chat: resumen liviano para el icono flotante (se consulta cada ~15 s)
//   no_leidos -> mensajes sin leer en todas mis conversaciones
//   reuniones -> reuniones mias que estan en curso o empiezan en menos de 15 min
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Chat::asegurar();
$yo = (int)Auth::userId();
Chat::sincronizar($yo);

$ahora = date('Y-m-d H:i:s');
$pronto = date('Y-m-d H:i:s', time() + 900);
$reu = Database::get(
    "SELECT r.id, r.sala_id, r.titulo, r.inicio, r.duracion_min, r.enlace, r.estado
     FROM chat_reuniones r
     INNER JOIN chat_sala_usuarios csu ON csu.sala_id = r.sala_id AND csu.usuario_id = ?
     WHERE r.estado <> 'cancelada' AND r.inicio <= ? AND r.inicio >= ?
     ORDER BY r.inicio", [$yo, $pronto, date('Y-m-d H:i:s', time() - 8 * 3600)]);
$activas = [];
foreach ($reu as $r) {
    $e = Chat::estadoReunion($r);
    if ($e === 'programada' || $e === 'en_curso') {
        $activas[] = ['id' => (int)$r['id'], 'sala_id' => (int)$r['sala_id'], 'titulo' => $r['titulo'], 'inicio' => $r['inicio'], 'estado' => $e];
    }
}
Response::ok(['no_leidos' => Chat::noLeidos($yo), 'reuniones' => $activas, 'ahora' => $ahora]);
