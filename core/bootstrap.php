<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - bootstrap.php v5 (MINIMAL y ESTABLE)
// Sin error handler custom (causaba interferencias)
// ============================================================
require_once dirname(__DIR__) . '/config/config.php';

ob_start();

// Autoload de Composer
 $vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Autocarga simple: core/, models/, services/
spl_autoload_register(function (string $clase): void {
    foreach (['core', 'models', 'services'] as $dir) {
        $archivo = ROOT_PATH . '/' . $dir . '/' . $clase . '.php';
        if (is_file($archivo)) {
            require_once $archivo;
            return;
        }
    }
});

// Exception handler: SOLO para excepciones no capturadas
set_exception_handler(function (Throwable $e): void {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    error_log('[SIGTI] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $ajax = (strpos($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') !== false)
         || (strpos($uri, '/api/')   !== false)
         || (strpos($uri, '/views/') !== false);

    http_response_code(500);

    // En produccion defina APP_DEBUG=false en config/config.php para no exponer
    // mensajes internos ni rutas (el detalle completo queda en el log de PHP).
    $oculto = defined('APP_DEBUG') && APP_DEBUG === false;
    if ($oculto) {
        $ref = substr(md5(uniqid('', true)), 0, 8);
        error_log('[SIGTI] ref ' . $ref);
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor (ref ' . $ref . ').',
                              'data' => null, 'errors' => null], JSON_UNESCAPED_UNICODE);
        } else {
            echo '<div style="font-family:sans-serif;padding:24px">Error interno del servidor (ref ' . $ref . ').</div>';
        }
        exit;
    }

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
           . '<br><small>En: ' . htmlspecialchars($e->getFile())
           . ' linea ' . $e->getLine() . '</small></div>';
    }
    exit;
});

// Session siempre iniciada
Auth::startSession();

// Licencia: modulos apagados para la empresa y modo solo lectura (vencida / sin licencia)
if (class_exists('Licencia')) {
    Licencia::guardia();
}

// Motor diario autonomo de preventivos (corre 1 vez al dia, silencioso,
// al terminar la respuesta para no penalizar la carga de la pagina)
if (class_exists('MotorDiario')) {
    register_shutdown_function(function () {
        MotorDiario::ejecutarSiToca();
    });
}