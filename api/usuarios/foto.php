<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Foto de usuario
// GET  ?id=X          -> sirve la imagen (protegida por sesion)
// POST accion=subir   -> guarda la foto (valida, recorta cuadrada, reduce)
// POST accion=quitar  -> elimina la foto
// La foto queda amarrada al usuario (usuarios.foto). El personal
// vinculado (usuarios.personal_id) la hereda en api/personal/foto.php
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

const FOTO_DIR_USR = '/fotos-usuarios/';
const FOTO_LADO_PX = 320;               // lado final de la imagen guardada
const FOTO_MAX_BYTES = 5 * 1024 * 1024; // limite de lo que se sube (se reduce al guardar)

// ---------- GET: servir imagen ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    $u = Database::getOne('SELECT foto FROM usuarios WHERE id = ?', [$id]);
    $f = $u['foto'] ?? '';
    // basename: el nombre guardado nunca puede apuntar fuera de la carpeta
    if (!$f || $f !== basename($f) || !is_file(STORAGE_PATH . FOTO_DIR_USR . $f)) {
        // silueta SVG generica (para <img> sin foto)
        header('Content-Type: image/svg+xml');
        header('X-Content-Type-Options: nosniff');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
           . '<rect width="100" height="100" fill="#2563eb"/>'
           . '<circle cx="50" cy="36" r="18" fill="#fff"/>'
           . '<path d="M15 92 Q15 60 50 60 Q85 60 85 92 Z" fill="#fff"/></svg>';
        exit;
    }
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    header('Content-Type: image/' . ($ext === 'png' ? 'png' : 'jpeg'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize(STORAGE_PATH . FOTO_DIR_USR . $f));
    header('Cache-Control: private, max-age=86400'); // la URL lleva ?v=<archivo>, cambia al subir otra
    readfile(STORAGE_PATH . FOTO_DIR_USR . $f);
    exit;
}

// ---------- POST: subir / quitar ----------
Csrf::validate();
$id = (int)($_POST['id'] ?? 0);

// permiso: gestionar usuarios O ser uno mismo (cambiarse su propia foto)
if ($id !== Auth::userId()) {
    Auth::requirePermission('usuarios', 'gestionar');
}

$u = Database::getOne('SELECT id, usuario, foto FROM usuarios WHERE id = ?', [$id]);
if (!$u) Response::error('El usuario no existe.');

$accion = $_POST['accion'] ?? '';

/** Borra un archivo de foto de la carpeta de fotos (solo por nombre, sin rutas). */
function foto_borrar(?string $nombre): void
{
    if ($nombre && $nombre === basename($nombre)) {
        $ruta = STORAGE_PATH . FOTO_DIR_USR . $nombre;
        if (is_file($ruta)) @unlink($ruta);
    }
}

/**
 * Recorta al centro en cuadrado, reduce a FOTO_LADO_PX y guarda como JPG.
 * Devuelve true si pudo; false si no hay GD o la imagen no se pudo procesar.
 */
function foto_procesar(string $origen, int $tipo, string $destino): bool
{
    if (!function_exists('imagecreatetruecolor')) return false;
    $img = $tipo === IMAGETYPE_PNG ? @imagecreatefrompng($origen) : @imagecreatefromjpeg($origen);
    if (!$img) return false;

    // respetar la orientacion de las fotos de celular (EXIF)
    if ($tipo === IMAGETYPE_JPEG && function_exists('exif_read_data') && function_exists('imagerotate')) {
        $exif = @exif_read_data($origen);
        $ori = (int)($exif['Orientation'] ?? 1);
        $giro = [3 => 180, 6 => -90, 8 => 90][$ori] ?? 0;
        if ($giro !== 0) {
            $rot = imagerotate($img, $giro, 0);
            if ($rot) { imagedestroy($img); $img = $rot; }
        }
    }

    $w = imagesx($img); $h = imagesy($img);
    $lado = min($w, $h);
    $sx = (int)(($w - $lado) / 2);
    $sy = (int)(($h - $lado) / 2);

    $out = imagecreatetruecolor(FOTO_LADO_PX, FOTO_LADO_PX);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // fondo blanco para PNG con transparencia
    imagecopyresampled($out, $img, 0, 0, $sx, $sy, FOTO_LADO_PX, FOTO_LADO_PX, $lado, $lado);
    $ok = imagejpeg($out, $destino, 85);
    imagedestroy($img);
    imagedestroy($out);
    return (bool)$ok;
}

if ($accion === 'subir') {
    if (empty($_FILES['foto']['name'])) Response::validation(['foto' => 'Seleccione una imagen.']);

    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        if (in_array($_FILES['foto']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            Response::validation(['foto' => 'La imagen es demasiado grande (maximo 5 MB).']);
        }
        Response::error('Error al subir (codigo ' . $_FILES['foto']['error'] . ').');
    }
    if ($_FILES['foto']['size'] > FOTO_MAX_BYTES) {
        Response::validation(['foto' => 'Maximo 5 MB.']);
    }

    // el contenido manda, no la extension: debe ser realmente PNG o JPG
    $info = @getimagesize($_FILES['foto']['tmp_name']);
    $tipo = $info[2] ?? 0;
    if (!in_array($tipo, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        Response::validation(['foto' => 'Solo imagenes PNG o JPG validas.']);
    }

    $base = 'usr_' . $id . '_' . bin2hex(random_bytes(8));
    $nombre = $base . '.jpg';
    $destino = STORAGE_PATH . FOTO_DIR_USR . $nombre;

    if (!foto_procesar($_FILES['foto']['tmp_name'], $tipo, $destino)) {
        // sin GD (o imagen no procesable): se guarda tal cual, con la extension real
        $nombre = $base . ($tipo === IMAGETYPE_PNG ? '.png' : '.jpg');
        $destino = STORAGE_PATH . FOTO_DIR_USR . $nombre;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
            Response::error('No se pudo guardar la foto.');
        }
    }

    // recien ahora se borra la anterior (si algo fallo arriba, no se pierde)
    foto_borrar($u['foto']);

    Database::update('usuarios', ['foto' => $nombre], 'id = ?', [$id]);
    Auditoria::registrar('actualizar', 'usuarios', $id, null, ['foto' => 'actualizada']);

    Response::ok(['foto' => $nombre], 'Foto actualizada.');

} elseif ($accion === 'quitar') {
    foto_borrar($u['foto']);
    Database::update('usuarios', ['foto' => null], 'id = ?', [$id]);
    Auditoria::registrar('eliminar', 'usuarios', $id, null, ['foto' => 'eliminada']);
    Response::ok(['foto' => null], 'Foto eliminada.');

} else {
    Response::error('Accion invalida.');
}
