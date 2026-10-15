<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// Logo publico de la empresa (login/sidebar). Cache 1 hora.
require_once __DIR__ . '/../../core/bootstrap.php';

foreach (['png', 'jpg', 'jpeg'] as $ext) {
    $f = STORAGE_PATH . '/branding/logo.' . $ext;
    if (is_file($f)) {
        header('Content-Type: image/' . ($ext === 'png' ? 'png' : 'jpeg'));
        header('Content-Length: ' . filesize($f));
        header('Cache-Control: public, max-age=3600');
        readfile($f);
        exit;
    }
}
// SVG placeholder mientras no suben logo (transparente 1px)
header('Content-Type: image/svg+xml');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
exit;