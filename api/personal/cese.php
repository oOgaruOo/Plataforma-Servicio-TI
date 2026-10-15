<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Registrar cese: programa la fecha, genera checklist
// de SALIDA y muestra todo lo que hay que recuperar/bloquear
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('personal', 'cese');

 $id = (int)($_POST['id'] ?? 0);

 $v = Validator::make($_POST, [
    'fecha_cese'    => 'required|date',
    'motivo'        => 'maxlen:250',
    'tipo_cese'     => 'in:renuncia,despido,fin_contrato,jubilacion,otros',
], ['fecha_cese' => 'Fecha de cese', 'motivo' => 'Motivo', 'tipo_cese' => 'Tipo de cese']);
if ($v->fails()) Response::validation($v->errors());

 $p = Database::getOne('SELECT * FROM personal WHERE id = ?', [$id]);
if (!$p) Response::error('La persona no existe.');
if (in_array($p['estado'], ['cesado'], true)) {
    Response::error('La persona ya está cesada (histórico).');
}
if (in_array($p['estado'], ['en_proceso_cese'], true)) {
    Response::error('El cese ya está en proceso. Úselo desde el panel de ceses.');
}

 $fechaCese = $_POST['fecha_cese'];
if ($fechaCese < date('Y-m-d')) {
    Response::validation(['fecha_cese' => 'La fecha de cese no puede ser anterior a hoy (para ceses pasados use el proceso directo).']);
}

Database::begin();
try {
    // ---- estado + datos del cese ----
    Database::update('personal', [
        'estado'      => 'cese_programado',
        'fecha_cese'  => $fechaCese,
        'observaciones' => trim(($p['observaciones'] ?: '') . ' | CESE: ' .
            ($_POST['tipo_cese'] ?? '') . ' ' . trim((string)($_POST['motivo'] ?? ''))),
    ], 'id = ?', [$id]);

    // ---- checklist de SALIDA (solo si no lo tiene ya) ----
    $existe = Database::getValue(
        "SELECT COUNT(*) FROM personal_checklist pc
         INNER JOIN checklist_items ci ON ci.id = pc.item_id
         INNER JOIN checklist_plantillas cp ON cp.id = ci.plantilla_id AND cp.tipo = 'cese'
         WHERE pc.personal_id = ?", [$id]);

    if ((int)$existe === 0) {
        $items = Database::get(
            "SELECT ci.id FROM checklist_items ci
             INNER JOIN checklist_plantillas cp ON cp.id = ci.plantilla_id
             WHERE cp.tipo = 'cese' AND cp.activo = 1 AND ci.activo = 1
             ORDER BY ci.orden");
        foreach ($items as $item) {
            Database::insert('personal_checklist', [
                'personal_id' => $id,
                'item_id'     => $item['id'],
            ]);
        }
    }

    // ---- inventario de lo que hay que recuperar ----
    $equipos = Database::get(
        "SELECT a.id AS asignacion_id, e.codigo, e.marca, e.modelo, t.nombre AS tipo
         FROM asignaciones a
         INNER JOIN equipos e ON e.id = a.equipo_id
         INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         WHERE a.personal_id = ? AND a.estado IN ('activa','vencida')", [$id]);

    $cuentas = Database::get(
        "SELECT usuario, estado FROM usuarios WHERE personal_id = ? AND estado = 'activo'", [$id]);

    Database::commit();
    Auditoria::registrar('actualizar', 'personal', $id,
        ['estado' => $p['estado']], ['estado' => 'cese_programado', 'fecha_cese' => $fechaCese]);

    // ---- NOTIFICACION: cese programado a TI ----
    Notificador::avisarPermiso('personal', 'cese', 'cese',
        'Cese programado: ' . $p['nombres'] . ' ' . $p['apellidos'],
        'Fecha: ' . $fechaCese . ' — ' . count($equipos) . ' equipo(s) por recuperar',
        'personal/lista');
    Response::ok([
        'equipos'   => count($equipos),
        'cuentas'   => count($cuentas),
        'checklist' => (int)$existe > 0 ? 'ya_existia' : 'generado',
        'fecha_cese'=> $fechaCese,
    ], "Cese programado para $fechaCese. " . count($equipos) . " equipo(s) por recuperar, " .
        count($cuentas) . " cuenta(s) por bloquear. Checklist de salida generado.");

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al registrar el cese: ' . $e->getMessage());
}