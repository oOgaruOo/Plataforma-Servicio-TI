<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();

Auth::logout();
Response::ok(null, 'Sesion cerrada correctamente.');