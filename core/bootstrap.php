<?php
// ============================================================
// SIGTI - Arranque del sistema
// ============================================================
require_once dirname(__DIR__) . '/config/config.php';

ob_start();

 $vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

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

set_exception_handler(function (Throwable $e): void {
    while (ob_get_level() > 0) { @ob_end_clean(); }
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

Auth::startSession();