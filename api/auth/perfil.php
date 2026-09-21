<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

Response::ok([
    'usuario'  => Auth::info(),
    'permisos' => $_SESSION['usuario']['permisos'] ?? [],
]);