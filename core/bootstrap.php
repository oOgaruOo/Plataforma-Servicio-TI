<?php
// ============================================================
// SIGTI - Arranque del sistema
// TODOS los endpoints API comienzan con:
//   require_once __DIR__ . '/../../core/bootstrap.php';
// ============================================================
require_once dirname(__DIR__) . '/config/config.php';

// ---- Autoload de Composer (si existe) ----
 $vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

// ---- Autocarga de respaldo: core/, models/ y services/ ----
spl_autoload_register(function (string $clase): void {
    foreach (['core', 'models', 'services'] as $dir) {
        $archivo = ROOT_PATH . '/' . $dir . '/' . $clase . '.php';
        if (is_file($archivo)) {
            require_once $archivo;
            if (!class_exists($clase, false) && !interface_exists($clase, false)) {
                throw new RuntimeException(
                    "SIGTI: el archivo existe ($archivo) pero NO declara 'class $clase'."
                );
            }
            return;
        }
    }
    throw new RuntimeException(
        "SIGTI: no se encontro la clase '$clase'. Se busco: " .
        ROOT_PATH . "/core/$clase.php (tambien en /models y /services). " .
        "Verifique nombre exacto: sin tildes, sin espacios, extension .php."
    );
});

// ---- Excepciones sin capturar -> respuesta clara ----
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
           . ' &middot; linea ' . $e->getLine() . '</small></div>';
    }
    exit;
});

// ---- La sesion SIEMPRE iniciada en cada peticion ----
Auth::startSession();