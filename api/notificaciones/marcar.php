<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();

 $accion = $_POST['accion'] ?? '';

if ($accion === 'leida') {
    $id = (int)($_POST['id'] ?? 0);
    Database::run(
        "UPDATE notificaciones SET leido = 1 WHERE id = ? AND usuario_destino_id = ?",
        [$id, Auth::userId()]);
    Response::ok(null, 'Marcada como leída.');
}

if ($accion === 'todas') {
    Database::run(
        "UPDATE notificaciones SET leido = 1 WHERE usuario_destino_id = ?",
        [Auth::userId()]);
    Response::ok(null, 'Todas marcadas como leídas.');
}

Response::error('Acción inválida.');