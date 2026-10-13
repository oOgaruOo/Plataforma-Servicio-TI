<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Guardian de vistas parciales
// Protege las vistas: solo valida SESION, no CSRF (GET views)
// ============================================================
require_once __DIR__ . '/bootstrap.php';

if (!Auth::isLogged()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Sesion no iniciada o expirada.']);
    exit;
}
// NO poner exit aqui - debe continuar para renderizar la vista
