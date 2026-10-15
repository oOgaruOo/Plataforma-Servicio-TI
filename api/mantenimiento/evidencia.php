<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// Evidencias fotograficas: subir (antes/despues/otro) / quitar
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('mantenimiento', 'editar');

 $accion = $_POST['accion'] ?? '';
 $id     = (int)($_POST['mantenimiento_id'] ?? 0);

 $m = Database::getOne('SELECT * FROM mantenimientos WHERE id = ?', [$id]);
if (!$m) Response::error('El mantenimiento no existe.');

if ($accion === 'subir') {
    if (in_array($m['estado'], ['cerrado','cancelado','no_reparable'], true)) {
        Response::error('Registro histórico: no admite evidencias.');
    }
    $tipo = $_POST['tipo'] ?? 'otro';
    if (!in_array($tipo, ['antes','despues','otro'], true)) $tipo = 'otro';

    if (empty($_FILES['archivo']['name'])) {
        Response::validation(['archivo' => 'Seleccione una imagen o PDF.']);
    }
    try {
        $up = Upload::guardar($_FILES['archivo'], 'evidencias-mantenimiento');
    } catch (Throwable $e) {
        Response::error($e->getMessage());
    }

    $eid = Database::insert('mantenimiento_evidencias', [
        'mantenimiento_id' => $id,
        'tipo'             => $tipo,
        'archivo'          => $up['archivo'],
        'descripcion'      => mb_substr(trim((string)($_POST['descripcion'] ?? '')), 0, 200) ?: null,
    ]);
    Auditoria::registrar('crear', 'mantenimiento_evidencias', $eid, null,
        ['mantenimiento_id' => $id, 'tipo' => $tipo]);

    Response::ok(['id' => $eid], 'Evidencia cargada.');

} elseif ($accion === 'quitar') {
    $eid = (int)($_POST['evidencia_id'] ?? 0);
    $e = Database::getOne(
        'SELECT id, archivo FROM mantenimiento_evidencias WHERE id = ? AND mantenimiento_id = ?', [$eid, $id]);
    if (!$e) Response::error('La evidencia no existe.');
    if (in_array($m['estado'], ['cerrado','no_reparable'], true)) {
        Response::error('Registro histórico: no admite quitar evidencias.');
    }
    Database::delete('mantenimiento_evidencias', 'id = ?', [$eid]);
    Response::ok(null, 'Evidencia retirada.');
} else {
    Response::error('Acción inválida.');
}