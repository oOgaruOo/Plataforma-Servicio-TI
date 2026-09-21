<?php
// ============================================================
// SIGTI - Configuracion global
// ============================================================

// ---- Base de datos (ajustar si tu MySQL tiene contrasena) ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'sigti');
define('DB_USER', 'root');
define('DB_PASS', '');              // XAMPP/WAMP por defecto: vacia
define('DB_CHARSET', 'utf8mb4');

// ---- Aplicacion ----
define('APP_NAME', 'SIGTI');
define('ROOT_PATH', dirname(__DIR__));       // raiz del proyecto (dinamico)

// BASE_URL autodetectada: usa el nombre REAL de la carpeta del disco.
// Funciona con cualquier nombre de carpeta, sin editar nunca mas este archivo.
 $nombreCarpeta = basename(str_replace('\\', '/', ROOT_PATH));
define('BASE_URL', '/' . $nombreCarpeta . '/');

define('STORAGE_PATH', ROOT_PATH . '/storage');

// ---- Seguridad ----
define('MAX_INTENTOS_LOGIN', 5);
define('SESSION_TIMEOUT_MIN', 120);
define('HASH_ALGO', PASSWORD_BCRYPT);

// ---- Entorno ----
error_reporting(E_ALL);
ini_set('display_errors', '1');              // en produccion cambiar a '0'
date_default_timezone_set('America/Lima');