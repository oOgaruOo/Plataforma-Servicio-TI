<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $foto = null;
 $u = Database::getOne('SELECT foto FROM usuarios WHERE id = ?', [Auth::userId()]);
if ($u && $u['foto'] && is_file(STORAGE_PATH . '/fotos-usuarios/' . $u['foto'])) {
    $ext = strtolower(pathinfo($u['foto'], PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
    $foto = 'data:' . $mime . ';base64,' . base64_encode(
        (string) file_get_contents(STORAGE_PATH . '/fotos-usuarios/' . $u['foto']));
}

 $u2 = $_SESSION['usuario'] ?? [];
unset($u2['permisos']);
 $u2['foto'] = $foto;

Response::ok(['usuario' => $u2]);