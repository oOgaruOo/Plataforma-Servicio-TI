<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. › Requerimiento de personal: catálogo de EPP
//   GET  -> lista activos (quien puede solicitar o gestionar), o todos con ?todos=1 (rrhh.gestionar)
//   POST accion = guardar | activar | desactivar  (rrhh.gestionar)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Requerimientos::asegurar();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!Requerimientos::alcance()) Auth::requirePermission('rrhh', 'solicitar');
        $todos = !empty($_GET['todos']) && Auth::can('rrhh', 'gestionar');
        Response::ok(Requerimientos::catalogoEpp(!$todos));
        return;
    }

    Csrf::validate();
    Auth::requirePermission('rrhh', 'gestionar');
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $v = Validator::make($_POST, ['nombre' => 'required|maxlen:100', 'orden' => 'integer'],
            ['nombre' => 'Nombre', 'orden' => 'Orden']);
        if ($v->fails()) Response::validation($v->errors());
        if ($id) {
            $antes = Database::getOne('SELECT * FROM rrhh_epp_catalogo WHERE id = ?', [$id]);
            if (!$antes) Response::error('El EPP ya no existe.');
            Requerimientos::guardarEpp($_POST, $id);
            Auditoria::registrar('editar', 'rrhh_epp_catalogo', $id, $antes, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
            Response::ok(['id' => $id], 'EPP actualizado.');
        } else {
            $nid = Requerimientos::guardarEpp($_POST);
            Auditoria::registrar('crear', 'rrhh_epp_catalogo', $nid, null, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
            Response::ok(['id' => $nid], 'EPP agregado al catálogo.');
        }

    } elseif ($accion === 'activar' || $accion === 'desactivar') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT id, activo FROM rrhh_epp_catalogo WHERE id = ?', [$id]);
        if (!$antes) Response::error('El EPP ya no existe.');
        $nuevo = $accion === 'activar' ? 1 : 0;
        Requerimientos::toggleEpp($id, $accion === 'activar');
        Auditoria::registrar('actualizar', 'rrhh_epp_catalogo', $id, ['activo' => (int)$antes['activo']], ['activo' => $nuevo], ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Response::ok(null, 'EPP ' . ($accion === 'activar' ? 'activado' : 'desactivado') . '.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] requerimientos/epp: ' . $e->getMessage());
    Response::error('No se pudo completar la operación.', null, 500);
}
