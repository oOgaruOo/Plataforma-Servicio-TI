<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

// ultimas 30 del usuario
 $lista = Database::get(
    "SELECT id, tipo, titulo, mensaje, url, leido, created_at
     FROM notificaciones WHERE usuario_destino_id = ?
     ORDER BY id DESC LIMIT 30", [Auth::userId()]);

 $total = (int) Database::getValue(
    "SELECT COUNT(*) FROM notificaciones WHERE usuario_destino_id = ? AND leido = 0",
    [Auth::userId()]);

Response::ok(['notificaciones' => $lista, 'no_leidas' => $total]);