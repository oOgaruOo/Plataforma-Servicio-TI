<?php
// ============================================================
// SIGTI - Arranque del sistema (VERSIÓN FINAL COMPLETA)
// TODOS los endpoints API comienzan con:
//   require_once __DIR__ . '/../../core/bootstrap.php';
// ============================================================

// ---- 1. Configuración global ----
require_once dirname(__DIR__) . '/config/config.php';

// ---- 2. Autoload de Composer (si existe vendor/autoload.php) ----
// El classmap de Composer resuelve las clases por mapa (rápido) y
// además los editores (VS Code / Intelephense, PhpStorm) lo leen,
// lo que elimina los marcados en rojo tipo "Undefined type Auditoria".
 $vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

// ---- 3. Autocarga de respaldo: core/, models/ y services/ ----
// (models/ y services/ se usarán a partir del Paso 5)
spl_autoload_register(function (string $clase): void {

    foreach (['core', 'models', 'services'] as $dir) {
        $archivo = ROOT_PATH . '/' . $dir . '/' . $clase . '.php';

        if (is_file($archivo)) {
            require_once $archivo;

            // El archivo existe pero no declara la clase esperada
            if (!class_exists($clase, false) && !interface_exists($clase, false)) {
                throw new RuntimeException(
                    "SIGTI: el archivo existe ($archivo) pero dentro NO declara 'class $clase'. " .
                    "Abra el archivo y corrija el nombre de la clase interna."
                );
            }
            return;
        }
    }

    // La clase no está en ninguna carpeta → error con ubicación exacta
    throw new RuntimeException(
        "SIGTI: no se encontró la clase '$clase'. Se buscó el archivo: " .
        ROOT_PATH . "/core/$clase.php (también en /models y /services). " .
        "Verifique que el archivo exista con ese nombre EXACTO: sin tildes, " .
        "sin espacios, con extensión .php (no .php.txt)."
    );
});

// ---- 4. Excepciones sin capturar → respuesta clara ----
// JSON amigable si es petición AJAX (Api.js lo muestra en toast),
// tarjeta roja legible si es una página normal.
set_exception_handler(function (Throwable $e): void {

    error_log('[SIGTI] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $ajax = (strpos($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') !== false)
         || (strpos($uri, '/api/')   !== false)
         || (strpos($uri, '/views/') !== false);

    http_response_code(500);

    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Error interno: ' . $e->getMessage(),
            'data'    => null,
            'errors'  => null,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<div style="font-family:monospace;padding:24px;color:#b91c1c;background:#fef2f2;'
           . 'border:1px solid #fecaca;border-radius:8px;max-width:860px;margin:24px">'
           . '<b>Error SIGTI:</b> ' . htmlspecialchars($e->getMessage())
           . '<br><br><small>En: ' . htmlspecialchars($e->getFile())
           . ' &middot; l&iacute;nea ' . $e->getLine() . '</small></div>';
    }
    exit;
});

// ---- 5. La sesión SIEMPRE iniciada en cada petición ----
Auth::startSession();