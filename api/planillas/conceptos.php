<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Planillas: catalogo de conceptos (ingresos/descuentos)
//   GET  -> lista  (rrhh.ver)
//   POST accion = guardar | activar | desactivar  (rrhh.gestionar)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Planillas::asegurar();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Auth::requirePermission('rrhh', 'ver');
        Response::ok(Planillas::conceptos(false));
        return;
    }

    Csrf::validate();
    Auth::requirePermission('rrhh', 'gestionar');
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $v = Validator::make($_POST, [
            'nombre' => 'required|maxlen:80',
            'tipo'   => 'required|in:ingreso,descuento',
            'modo'   => 'required|in:fijo,porcentaje',
            'valor'  => 'required|numeric|min:0',
            'orden'  => 'integer',
        ], ['nombre' => 'Nombre', 'tipo' => 'Tipo', 'modo' => 'Modo', 'valor' => 'Valor', 'orden' => 'Orden']);
        if ($v->fails()) Response::validation($v->errors());
        if ($id) {
            $antes = Database::getOne('SELECT * FROM rrhh_conceptos WHERE id = ?', [$id]);
            if (!$antes) Response::error('El concepto ya no existe.');
            Planillas::guardarConcepto($_POST, $id);
            Auditoria::registrar('editar', 'rrhh_conceptos', $id, $antes, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'planillas']);
            Response::ok(['id' => $id], 'Concepto actualizado.');
        } else {
            $nid = Planillas::guardarConcepto($_POST);
            Auditoria::registrar('crear', 'rrhh_conceptos', $nid, null, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'planillas']);
            Response::ok(['id' => $nid], 'Concepto creado.');
        }

    } elseif ($accion === 'activar' || $accion === 'desactivar') {
        $id = (int)($_POST['id'] ?? 0);
        if (!Database::getValue('SELECT id FROM rrhh_conceptos WHERE id = ?', [$id])) Response::error('El concepto ya no existe.');
        Planillas::toggleConcepto($id, $accion === 'activar');
        Response::ok(null, 'Concepto ' . ($accion === 'activar' ? 'activado' : 'desactivado') . '.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] planillas/conceptos: ' . $e->getMessage());
    Response::error('No se pudo completar la operación.', null, 500);
}
