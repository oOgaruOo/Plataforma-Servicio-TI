<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

Response::ok([
    'total' => (int) Database::getValue(
        "SELECT COUNT(*) FROM notificaciones WHERE usuario_destino_id = ? AND leido = 0",
        [Auth::userId()]),
]);