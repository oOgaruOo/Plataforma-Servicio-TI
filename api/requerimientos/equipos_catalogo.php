<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. › Requerimiento de personal: catálogo de equipos de TI
//   GET  -> lista activos (quien puede solicitar o gestionar), o todos con ?todos=1 (rrhh.gestionar)
//   POST accion = guardar | activar | desactivar  (rrhh.gestionar)
//   POST accion = eliminar                         (rrhh.eliminar) borra de verdad si ningun
//                                                   requerimiento lo pidio todavia (si no, desactivar)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Requerimientos::asegurar();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!Requerimientos::alcance()) Auth::requirePermission('rrhh', 'solicitar');
        $todos = !empty($_GET['todos']) && Auth::can('rrhh', 'gestionar');
        Response::ok(Requerimientos::catalogoEquipos(!$todos));
        return;
    }

    Csrf::validate();
    $accion = (string)($_POST['accion'] ?? '');
    Auth::requirePermission('rrhh', $accion === 'eliminar' ? 'eliminar' : 'gestionar');

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $v = Validator::make($_POST, ['nombre' => 'required|maxlen:100', 'orden' => 'integer'],
            ['nombre' => 'Nombre', 'orden' => 'Orden']);
        if ($v->fails()) Response::validation($v->errors());
        if ($id) {
            $antes = Database::getOne('SELECT * FROM rrhh_equipo_catalogo WHERE id = ?', [$id]);
            if (!$antes) Response::error('El equipo ya no existe.');
            Requerimientos::guardarEquipo($_POST, $id);
            Auditoria::registrar('editar', 'rrhh_equipo_catalogo', $id, $antes, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
            Response::ok(['id' => $id], 'Equipo actualizado.');
        } else {
            $nid = Requerimientos::guardarEquipo($_POST);
            Auditoria::registrar('crear', 'rrhh_equipo_catalogo', $nid, null, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
            Response::ok(['id' => $nid], 'Equipo agregado al catálogo.');
        }

    } elseif ($accion === 'activar' || $accion === 'desactivar') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT id, activo FROM rrhh_equipo_catalogo WHERE id = ?', [$id]);
        if (!$antes) Response::error('El equipo ya no existe.');
        $nuevo = $accion === 'activar' ? 1 : 0;
        Requerimientos::toggleEquipo($id, $accion === 'activar');
        Auditoria::registrar('actualizar', 'rrhh_equipo_catalogo', $id, ['activo' => (int)$antes['activo']], ['activo' => $nuevo], ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Response::ok(null, 'Equipo ' . ($accion === 'activar' ? 'activado' : 'desactivado') . '.');

    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT * FROM rrhh_equipo_catalogo WHERE id = ?', [$id]);
        if (!$antes) Response::error('El equipo ya no existe.');
        if (!Requerimientos::eliminarEquipo($id)) {
            Response::error('Este equipo ya se pidió en algún requerimiento: no se puede eliminar, pero puede desactivarlo.');
        }
        Auditoria::registrar('eliminar', 'rrhh_equipo_catalogo', $id, $antes, null, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Response::ok(null, 'Equipo eliminado del catálogo.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] requerimientos/equipos_catalogo: ' . $e->getMessage());
    Response::error('No se pudo completar la operación.', null, 500);
}
