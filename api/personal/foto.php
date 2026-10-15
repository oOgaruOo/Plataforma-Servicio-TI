<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Foto del PERSONAL (para el carnet y la ficha)
// GET  ?id=PERSONAL_ID        -> la foto propia de la persona; si no tiene, la de su
//                                cuenta de usuario; si tampoco, un avatar SVG por genero
// POST accion=subir|quitar    -> guarda / elimina la foto propia (permiso personal.editar)
// La foto propia queda en storage/fotos-personal y en personal.foto
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Esquema::columna('personal', 'foto', 'VARCHAR(80) NULL');

const FOTO_DIR_PER = '/fotos-personal/';

function foto_per_borrar(?string $nombre): void
{
    if ($nombre && $nombre === basename($nombre)) {
        $ruta = STORAGE_PATH . FOTO_DIR_PER . $nombre;
        if (is_file($ruta)) @unlink($ruta);
    }
}

function foto_per_servir(string $ruta): void
{
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    header('Content-Type: image/' . ($ext === 'png' ? 'png' : 'jpeg'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: private, max-age=3600');
    readfile($ruta);
    exit;
}

// ---------- GET ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    $p = Database::getOne(
        "SELECT p.genero, p.foto AS foto_propia, u.foto AS foto_usuario
         FROM personal p
         LEFT JOIN usuarios u ON u.personal_id = p.id AND u.estado <> 'inactivo'
         WHERE p.id = ?", [$id]);

    $propia = $p['foto_propia'] ?? '';
    if ($propia && $propia === basename($propia) && is_file(STORAGE_PATH . FOTO_DIR_PER . $propia)) {
        foto_per_servir(STORAGE_PATH . FOTO_DIR_PER . $propia);
    }
    $usr = $p['foto_usuario'] ?? '';
    if ($usr && $usr === basename($usr) && is_file(STORAGE_PATH . '/fotos-usuarios/' . $usr)) {
        foto_per_servir(STORAGE_PATH . '/fotos-usuarios/' . $usr);
    }

    // sin foto: avatar SVG por genero
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=3600');
    if (($p['genero'] ?? null) === 'femenino') {
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#e8b4d8"/>'
           . '<circle cx="50" cy="36" r="17" fill="#fff"/><path d="M25 92 Q25 58 50 58 Q75 58 75 92 Z" fill="#fff"/>'
           . '<path d="M38 30 Q40 18 50 18 Q60 18 62 30 Q58 24 50 24 Q42 24 38 30 Z" fill="#c69c6d"/></svg>';
    } else {
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#93b8e8"/>'
           . '<circle cx="50" cy="36" r="17" fill="#fff"/><path d="M22 92 Q22 58 50 58 Q78 58 78 92 Z" fill="#fff"/></svg>';
    }
    exit;
}

// ---------- POST ----------
Csrf::validate();
Auth::requirePermission('personal', 'editar');

$id = (int)($_POST['id'] ?? 0);
$p = Database::getOne('SELECT id, foto FROM personal WHERE id = ?', [$id]);
if (!$p) Response::error('La persona no existe.');

$accion = $_POST['accion'] ?? '';
if ($accion === 'subir') {
    [$tipo, $err] = FotoImagen::validar($_FILES['foto'] ?? []);
    if ($err) Response::validation(['foto' => $err]);

    $dir = STORAGE_PATH . FOTO_DIR_PER;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) Response::error('No se pudo crear la carpeta de fotos.');

    $base = 'per_' . $id . '_' . bin2hex(random_bytes(8));
    $nombre = $base . '.jpg';
    if (!FotoImagen::procesar($_FILES['foto']['tmp_name'], $tipo, $dir . $nombre)) {
        // sin GD: se guarda tal cual con su extension real
        $nombre = $base . ($tipo === IMAGETYPE_PNG ? '.png' : '.jpg');
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $nombre)) Response::error('No se pudo guardar la foto.');
    }
    foto_per_borrar($p['foto']);                         // la anterior, solo cuando la nueva ya quedo guardada
    Database::update('personal', ['foto' => $nombre], 'id = ?', [$id]);
    Auditoria::registrar('actualizar', 'personal', $id, null, ['foto' => 'actualizada']);
    $reimp = Carnets::datosCambiaron($id, 'foto');
    Response::ok(['foto' => $nombre, 'carnet_reimprimir' => $reimp], 'Foto actualizada.' . ($reimp ? ' Su carnet ya impreso quedo marcado para reimprimir.' : ''));

} elseif ($accion === 'quitar') {
    foto_per_borrar($p['foto']);
    Database::update('personal', ['foto' => null], 'id = ?', [$id]);
    Auditoria::registrar('eliminar', 'personal', $id, null, ['foto' => 'eliminada']);
    $reimp = Carnets::datosCambiaron($id, 'foto');
    Response::ok(['foto' => null, 'carnet_reimprimir' => $reimp], 'Foto eliminada.');
}
Response::error('Accion invalida.');
