<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: factura / comprobante de una licencia (PDF o imagen)
//   GET  ?id=<licencia>[&descargar=1]     ver el archivo (software.ver o software.registrar)
//   POST accion=subir  licencia_id, archivo (multipart)      (software.gestionar) · reemplaza la anterior
//   POST accion=quitar licencia_id                           (software.gestionar)
// El archivo vive en storage/facturas-licencias (cerrado al navegador) y solo sale por aqui.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

const SW_FACT_DIR = 'facturas-licencias';

try {
    Auth::check();
    Software::asegurar();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        if (!Auth::can('software', 'ver') && !Auth::can('software', 'registrar')) {
            Auth::requirePermission('software', 'ver');
        }
        $l = Database::getOne('SELECT id, factura_archivo, factura_nombre FROM software_licencias WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
        if (!$l || ($l['factura_archivo'] ?? '') === '') Response::error('Esta licencia no tiene factura adjunta.', null, 404);
        Documentos::servir(SW_FACT_DIR, $l['factura_archivo'], $l['factura_nombre'], !empty($_GET['descargar']));
    }

    Csrf::validate();
    if (Documentos::excedeLimitePost()) Response::validation(['archivo' => Documentos::MSG_GRANDE]);
    Auth::requirePermission('software', 'gestionar');
    $accion = (string)($_POST['accion'] ?? '');
    $id = (int)($_POST['licencia_id'] ?? 0);
    $l = Database::getOne('SELECT l.id, l.factura_archivo, l.factura_nombre, s.nombre AS software
                           FROM software_licencias l INNER JOIN software_catalogo s ON s.id = l.software_id WHERE l.id = ?', [$id]);
    if (!$l) Response::error('La licencia ya no existe.');
    $tenia = ($l['factura_archivo'] ?? '') !== '';

    if ($accion === 'subir') {
        if (empty($_FILES['archivo']['name'])) Response::validation(['archivo' => 'Seleccione la factura (PDF o imagen).']);
        try {
            $up = Documentos::guardar($_FILES['archivo'], SW_FACT_DIR);
        } catch (RuntimeException $e) {
            Response::validation(['archivo' => $e->getMessage()]);
        }
        Database::update('software_licencias', [
            'factura_archivo' => $up['archivo'], 'factura_nombre' => $up['nombre_original'],
            'factura_kb' => $up['tamano_kb'], 'factura_subida_en' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        if ($tenia) Documentos::borrar(SW_FACT_DIR, $l['factura_archivo']);
        Auditoria::registrar('actualizar', 'software_licencias', $id,
            ['factura' => $tenia ? $l['factura_nombre'] : null], ['factura' => $up['nombre_original'], 'tamano_kb' => $up['tamano_kb']],
            ['accion_detalle' => $tenia ? 'licencia_factura_reemplazada' : 'licencia_factura_cargada', 'submodulo' => 'licencias']);
        Response::ok(['mensaje' => 'Factura adjunta: ' . $up['nombre_original'], 'nombre' => $up['nombre_original']]);

    } elseif ($accion === 'quitar') {
        if (!$tenia) Response::error('Esta licencia no tiene factura adjunta.');
        Database::update('software_licencias', ['factura_archivo' => null, 'factura_nombre' => null, 'factura_kb' => null, 'factura_subida_en' => null], 'id = ?', [$id]);
        Documentos::borrar(SW_FACT_DIR, $l['factura_archivo']);
        Auditoria::registrar('actualizar', 'software_licencias', $id, ['factura' => $l['factura_nombre']], ['factura' => null],
            ['accion_detalle' => 'licencia_factura_quitada', 'submodulo' => 'licencias']);
        Response::ok(['mensaje' => 'Factura retirada.']);

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] software/factura: ' . $e->getMessage());
    Response::error('No se pudo completar la operación con la factura.', null, 500);
}
