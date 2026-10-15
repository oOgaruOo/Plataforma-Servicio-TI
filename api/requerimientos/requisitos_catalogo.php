<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. › Requerimiento de personal: biblioteca de requisitos
//   Frases de requisito (categoría + detalle) reutilizables entre varios puestos: al definir
//   los requisitos de un puesto se elige de aquí, o se escribe una nueva (se suma sola a la
//   biblioteca vía Requerimientos::buscarOCrearRequisito, llamado desde guardarPuesto()).
//
//   GET  -> lista activos (quien puede solicitar o gestionar), o todos con ?todos=1 (rrhh.gestionar)
//   POST accion = guardar | activar | desactivar  (rrhh.gestionar)
//   POST accion = eliminar                         (rrhh.eliminar) borra de verdad si ningun
//                                                   puesto la tiene asignada (si no, desactivar)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Requerimientos::asegurar();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!Requerimientos::alcance()) Auth::requirePermission('rrhh', 'solicitar');
        $todos = !empty($_GET['todos']) && Auth::can('rrhh', 'gestionar');
        Response::ok(Requerimientos::catalogoRequisitos(!$todos));
        return;
    }

    Csrf::validate();
    $accion = (string)($_POST['accion'] ?? '');
    Auth::requirePermission('rrhh', $accion === 'eliminar' ? 'eliminar' : 'gestionar');

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $v = Validator::make($_POST, [
            'categoria' => 'required|minlen:2|maxlen:60',
            'detalle'   => 'required|minlen:2|maxlen:2000',
            'orden'     => 'integer',
        ], ['categoria' => 'Categoría', 'detalle' => 'Detalle', 'orden' => 'Orden']);
        if ($v->fails()) Response::validation($v->errors());
        if ($id) {
            $antes = Database::getOne('SELECT * FROM rrhh_requisito_catalogo WHERE id = ?', [$id]);
            if (!$antes) Response::error('El requisito ya no existe.');
            Requerimientos::guardarRequisitoCatalogo($_POST, $id);
            Auditoria::registrar('editar', 'rrhh_requisito_catalogo', $id, $antes, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
            Response::ok(['id' => $id], 'Requisito actualizado en la biblioteca.');
        } else {
            $nid = Requerimientos::guardarRequisitoCatalogo($_POST);
            Auditoria::registrar('crear', 'rrhh_requisito_catalogo', $nid, null, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
            Response::ok(['id' => $nid], 'Requisito agregado a la biblioteca.');
        }

    } elseif ($accion === 'activar' || $accion === 'desactivar') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT id, activo FROM rrhh_requisito_catalogo WHERE id = ?', [$id]);
        if (!$antes) Response::error('El requisito ya no existe.');
        $nuevo = $accion === 'activar' ? 1 : 0;
        Requerimientos::toggleRequisitoCatalogo($id, $accion === 'activar');
        Auditoria::registrar('actualizar', 'rrhh_requisito_catalogo', $id, ['activo' => (int)$antes['activo']], ['activo' => $nuevo], ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Response::ok(null, 'Requisito ' . ($accion === 'activar' ? 'activado' : 'desactivado') . '.');

    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT * FROM rrhh_requisito_catalogo WHERE id = ?', [$id]);
        if (!$antes) Response::error('El requisito ya no existe.');
        if (!Requerimientos::eliminarRequisitoCatalogo($id)) {
            Response::error('Este requisito ya está asignado a algún puesto: no se puede eliminar, pero puede desactivarlo.');
        }
        Auditoria::registrar('eliminar', 'rrhh_requisito_catalogo', $id, $antes, null, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Response::ok(null, 'Requisito eliminado de la biblioteca.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] requerimientos/requisitos_catalogo: ' . $e->getMessage());
    Response::error('No se pudo completar la operación.', null, 500);
}
