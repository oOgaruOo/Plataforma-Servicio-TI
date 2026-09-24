<?php
// ============================================================
// SIGTI - Logo de empresa para actas
// GET  ?accion=ver    -> muestra la imagen (vista previa)
// POST accion=subir   -> guarda logo.png|jpg en storage/branding
// POST accion=quitar  -> elimina el logo
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $accion = $_GET['accion'] ?? ($_POST['accion'] ?? '');

if ($accion === 'ver') {
    foreach (['png', 'jpg', 'jpeg'] as $ext) {
        $f = STORAGE_PATH . '/branding/logo.' . $ext;
        if (is_file($f)) {
            header('Content-Type: image/' . ($ext === 'png' ? 'png' : 'jpeg'));
            header('Content-Length: ' . filesize($f));
            header('Cache-Control: no-store');
            readfile($f);
            exit;
        }
    }
    http_response_code(404);
    exit;
}

Csrf::validate();
Auth::requirePermission('configuracion', 'gestionar');

if ($accion === 'subir') {
    if (empty($_FILES['logo']['name'])) Response::validation(['logo' => 'Seleccione la imagen del logo.']);

    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
        Response::validation(['logo' => 'Solo se aceptan imagenes PNG o JPG.']);
    }
    if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        Response::error('Error al subir el archivo (codigo ' . $_FILES['logo']['error'] . ').');
    }
    if ($_FILES['logo']['size'] > 3 * 1024 * 1024) {
        Response::validation(['logo' => 'El logo no debe superar 3 MB (recomendado: PNG horizontal).']);
    }

    $dir = STORAGE_PATH . '/branding';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    foreach (['png', 'jpg', 'jpeg'] as $e) {
        @unlink($dir . '/logo.' . $e);
    }

    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/logo.' . $ext)) {
        Response::error('No se pudo guardar el logo en el servidor.');
    }

    Auditoria::registrar('actualizar', 'configuracion', null, null,
        ['clave' => 'empresa_logo', 'archivo' => 'logo.' . $ext]);

    Response::ok(null, 'Logo actualizado. Las actas lo incluiran automaticamente.');

} elseif ($accion === 'quitar') {
    $borrados = 0;
    foreach (['png', 'jpg', 'jpeg'] as $e) {
        if (@unlink(STORAGE_PATH . '/branding/logo.' . $e)) $borrados++;
    }
    Auditoria::registrar('eliminar', 'configuracion', null, null, ['clave' => 'empresa_logo']);
    Response::ok(null, $borrados ? 'Logo eliminado.' : 'No habia logo configurado.');

} else {
    Response::error('Accion invalida.');
}