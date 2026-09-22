<?php
// ============================================================
// SIGTI - Marcar / desmarcar item de checklist (guardado inmediato)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('personal', 'editar');

 $id     = (int)($_POST['id'] ?? 0);              // id de personal_checklist
 $estado = (int)($_POST['completado'] ?? 0);      // 1 o 0
 $obs    = trim((string)($_POST['observacion'] ?? ''));

if ($id === 0) Response::error('Ítem no especificado.');
if (!in_array($estado, [0, 1], true)) Response::error('Estado inválido.');

 $item = Database::getOne(
    "SELECT pc.id, pc.completado, pc.personal_id, p.estado AS estado_persona, ci.item
     FROM personal_checklist pc
     INNER JOIN personal p ON p.id = pc.personal_id
     INNER JOIN checklist_items ci ON ci.id = pc.item_id
     WHERE pc.id = ?",
    [$id]
);
if (!$item) Response::error('El ítem no existe.');

if ($item['estado_persona'] === 'cesado') {
    Response::error('No se puede modificar el checklist de una persona cesada.');
}

 $nuevos = [
    'completado'            => $estado,
    'responsable_usuario_id'=> $estado ? Auth::userId() : null,
    'fecha_completado'      => $estado ? date('Y-m-d H:i:s') : null,
    'observacion'           => $obs !== '' ? mb_substr($obs, 0, 250) : null,
];

Database::update('personal_checklist', $nuevos, 'id = ?', [$id]);
Auditoria::registrar('actualizar', 'personal_checklist', $id,
    ['completado' => $item['completado']], $nuevos);

// progreso total actualizado para refrescar la barra sin recargar
 $stats = Database::getOne(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN completado = 1 THEN 1 ELSE 0 END) AS ok
     FROM personal_checklist WHERE personal_id = ?",
    [$item['personal_id']]
);

Response::ok([
    'ok'    => (int)($stats['ok'] ?? 0),
    'total' => (int)($stats['total'] ?? 0),
], $estado ? 'Ítem marcado como completado.' : 'Ítem desmarcado.');