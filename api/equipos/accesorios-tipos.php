<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Catalogo de accesorios seleccionables por familia
// ?familia=computo  ->  accesorios de computo + generales
// Si la tabla no existe todavia se crea con accesorios comunes.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $familia = trim((string)($_GET['familia'] ?? ''));
if ($familia === '') Response::error('Familia no especificada.');

try {
    Database::run('SELECT id FROM accesorios_tipos LIMIT 0');
} catch (Throwable $e) {
    Database::run("CREATE TABLE IF NOT EXISTS accesorios_tipos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(80) NOT NULL,
        familia VARCHAR(20) NOT NULL DEFAULT 'general',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_acc_tipo (familia, nombre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $semilla = [
        'general'    => ['Cargador', 'Cable de poder', 'Manual', 'Caja original', 'Funda / estuche'],
        'computo'    => ['Mouse', 'Teclado', 'Maletín', 'Cable HDMI', 'Adaptador de red', 'Candado de seguridad'],
        'movil'      => ['Cable USB', 'Protector de pantalla', 'Audífonos', 'Chip SIM'],
        'impresion'  => ['Cable USB', 'Tóner / cartucho', 'Bandeja de papel'],
        'red'        => ['Fuente de poder', 'Kit de montaje en rack', 'Antenas'],
        'visual'     => ['Cable HDMI', 'Cable VGA', 'Control remoto'],
        'periferico' => ['Cable USB', 'Receptor USB'],
    ];
    foreach ($semilla as $fam => $nombres) {
        foreach ($nombres as $n) {
            Database::run('INSERT IGNORE INTO accesorios_tipos (nombre, familia) VALUES (?, ?)', [$n, $fam]);
        }
    }
}

 $lista = Database::get(
    "SELECT id, nombre AS texto, familia
     FROM accesorios_tipos
     WHERE activo = 1 AND (familia = ? OR familia = 'general')
     ORDER BY CASE WHEN familia = 'general' THEN 1 ELSE 0 END, nombre",
    [$familia]
);

Response::ok($lista);
