<?php
// ============================================================
// SIGTI - Guardian de vistas parciales
// ============================================================
require_once __DIR__ . '/bootstrap.php';

if (!Auth::isLogged()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Sesion no iniciada o expirada.']);
    exit;
}