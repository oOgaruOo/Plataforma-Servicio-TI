<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    EtiquetasPlantillas::asegurar();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        EtiquetasPlantillas::sembrar();          // modelos base de Personal y Locker (una sola vez)
        $lista = Database::get(
            "SELECT id, nombre, ancho_mm, alto_mm, elementos, activa, objeto FROM etiquetas_plantillas ORDER BY id");
        foreach ($lista as &$p) {
            $p['elementos'] = json_decode($p['elementos'], true) ?: [];
        }
        unset($p);
        Response::ok($lista);
    }

    Csrf::validate();
    Auth::requirePermission('configuracion', 'gestionar');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id        = (int)($_POST['id'] ?? 0);
        $nombre   = trim((string)($_POST['nombre'] ?? ''));
        $objeto   = (string)($_POST['objeto'] ?? 'equipo');
        if (!isset(EtiquetasPlantillas::OBJETOS[$objeto])) Response::validation(['objeto' => 'Tipo de etiqueta no valido.']);
        $ancho    = max(20, min(100, (int)($_POST['ancho_mm'] ?? 40)));
        $alto     = max(15, min(100, (int)($_POST['alto_mm'] ?? 30)));
        $elementos = (string)($_POST['elementos'] ?? '[]');
if ($nombre === '') Response::validation(['nombre' => 'Indique el nombre.']);
 $el = json_decode($elementos, true);
if (!is_array($el)) Response::validation(['elementos' => 'Invalidos.']);
 $datos = ['nombre' => $nombre, 'ancho_mm' => $ancho, 'alto_mm' => $alto,
'elementos' => json_encode($el, JSON_UNESCAPED_UNICODE), 'objeto' => $objeto];
if ($id > 0) {
Database::update('etiquetas_plantillas', $datos, 'id = ?', [$id]);
Response::ok(null, 'Plantilla actualizada.');
}
 $datos['creada_por'] = Auth::userId();
 $nuevo = Database::insert('etiquetas_plantillas', $datos);
Response::ok(['id' => $nuevo], "Plantilla «{$nombre}» creada.");

} elseif ($accion === 'eliminar') {
 $id = (int)($_POST['id'] ?? 0);
Database::delete('etiquetas_plantillas', 'id = ?', [$id]);
Response::ok(null, 'Plantilla eliminada.');

} elseif ($accion === 'duplicar') {
 $id = (int)($_POST['id'] ?? 0);
 $p = Database::getOne('SELECT * FROM etiquetas_plantillas WHERE id = ?', [$id]);
if (!$p) Response::error('No existe.');
Database::insert('etiquetas_plantillas', [
'nombre' => $p['nombre'] . ' (copia)',
'ancho_mm' => $p['ancho_mm'], 'alto_mm' => $p['alto_mm'],
'elementos' => $p['elementos'], 'objeto' => $p['objeto'] ?? 'equipo', 'creada_por' => Auth::userId(),
]);
Response::ok(null, 'Duplicada.');
} else {
Response::error('Accion invalida.');
}
} catch (Throwable $ex) {
Response::error('etiquetas/plantillas: ' . $ex->getMessage());
}