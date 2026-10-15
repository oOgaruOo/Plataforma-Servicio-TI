<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. › Requerimiento de personal: catálogo de puestos y sus requisitos
//   GET  ?id=            un puesto con sus requisitos (activo, o propio/pendiente, o cualquiera si gestiona)
//   GET  ?estado=        lista (rrhh.gestionar puede filtrar por cualquier estado; los demas solo
//                        ven los activos + los que ellos mismos propusieron)
//   POST accion = proponer   (rrhh.solicitar) agrega un puesto nuevo con solo el nombre -> pendiente
//   POST accion = guardar    (rrhh.gestionar) crea o edita (nombre, contrato sugerido, salario,
//                             estado, requisitos[]); asi se valida/activa, se corrige o se completa
//   POST accion = eliminar   (rrhh.eliminar) borra el puesto si ningun requerimiento lo uso todavia
//                             (si ya se uso, se debe inactivar en vez de eliminar)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Requerimientos::asegurar();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!Requerimientos::alcance()) Auth::requirePermission('rrhh', 'solicitar');
        $gestiona = Auth::can('rrhh', 'gestionar');

        if (isset($_GET['id'])) {
            $p = Requerimientos::puesto((int)$_GET['id']);
            if (!$p) Response::error('El puesto no existe.', null, 404);
            if ($p['estado'] !== 'activo' && !$gestiona && (int)($p['creado_por'] ?? 0) !== Auth::userId()) {
                Response::error('El puesto no existe o no puede verlo.', null, 404);
            }
            Response::ok(Requerimientos::presentarPuesto($p) + ['requisitos' => $p['requisitos']]);
        }

        if ($gestiona) {
            $estado = trim((string)($_GET['estado'] ?? ''));
            $filas = Requerimientos::catalogoPuestos($estado !== '' ? $estado : null);
        } else {
            $filas = Database::get(
                "SELECT p.*, (SELECT COUNT(*) FROM rrhh_puesto_requisitos WHERE puesto_id = p.id) AS n_requisitos
                 FROM rrhh_puesto_catalogo p WHERE p.estado = 'activo' OR p.creado_por = ? ORDER BY p.nombre",
                [Auth::userId()]
            );
        }
        Response::ok(array_map(['Requerimientos', 'presentarPuesto'], $filas));
    }

    Csrf::validate();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'proponer') {
        Auth::requirePermission('rrhh', 'solicitar');
        $v = Validator::make($_POST, ['nombre' => 'required|minlen:2|maxlen:120'], ['nombre' => 'Nombre del puesto']);
        if ($v->fails()) Response::validation($v->errors());
        $existe = Database::getOne('SELECT id, estado FROM rrhh_puesto_catalogo WHERE LOWER(nombre) = LOWER(?)', [trim((string)$_POST['nombre'])]);
        if ($existe) {
            Requerimientos::ok(['id' => (int)$existe['id'], 'estado' => $existe['estado']],
                'Ese puesto ya está en el catálogo (' . (Requerimientos::ESTADOS_PUESTO[$existe['estado']] ?? $existe['estado']) . ').');
        }
        $id = Requerimientos::buscarOProponerPuesto((string)$_POST['nombre'], Auth::userId());
        Auditoria::registrar('crear', 'rrhh_puesto_catalogo', $id, null, ['nombre' => trim((string)$_POST['nombre'])], ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Requerimientos::ok(['id' => $id, 'estado' => 'pendiente'], 'Puesto propuesto. Quedará «pendiente» hasta que Sistemas / RR.HH. lo valide y le agregue los requisitos.');

    } elseif ($accion === 'guardar') {
        Auth::requirePermission('rrhh', 'gestionar');
        $id = (int)($_POST['id'] ?? 0);
        $v = Validator::make($_POST, [
            'nombre'                 => 'required|minlen:2|maxlen:120',
            'tipo_contrato_sugerido' => 'in:' . implode(',', array_keys(Contratos::TIPOS)),
            'salario_min'            => 'numeric|min:0|max:999999',
            'salario_max'            => 'numeric|min:0|max:999999',
            'estado'                 => 'in:' . implode(',', array_keys(Requerimientos::ESTADOS_PUESTO)),
        ], ['nombre' => 'Nombre del puesto', 'tipo_contrato_sugerido' => 'Tipo de contrato sugerido',
            'salario_min' => 'Salario mínimo', 'salario_max' => 'Salario máximo', 'estado' => 'Estado']);
        if ($v->fails()) Response::validation($v->errors());
        $smin = ($_POST['salario_min'] ?? '') !== '' ? (float)$_POST['salario_min'] : null;
        $smax = ($_POST['salario_max'] ?? '') !== '' ? (float)$_POST['salario_max'] : null;
        if ($smin !== null && $smax !== null && $smin > $smax) Response::validation(['salario_max' => 'El salario máximo debe ser mayor o igual al mínimo.']);

        $requisitos = json_decode((string)($_POST['requisitos'] ?? '[]'), true);
        if (!is_array($requisitos)) $requisitos = [];
        $datos = $_POST; $datos['requisitos'] = $requisitos;

        if ($id) {
            $antes = Database::getOne('SELECT * FROM rrhh_puesto_catalogo WHERE id = ?', [$id]);
            if (!$antes) Response::error('El puesto ya no existe.');
            $otro = (int) Database::getValue('SELECT id FROM rrhh_puesto_catalogo WHERE LOWER(nombre) = LOWER(?) AND id <> ?', [trim((string)$_POST['nombre']), $id]);
            if ($otro) Response::validation(['nombre' => 'Ya existe otro puesto con ese nombre en el catálogo.']);
            Requerimientos::guardarPuesto($datos, $id);
            Auditoria::registrar('editar', 'rrhh_puesto_catalogo', $id, $antes, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
            Requerimientos::ok(['id' => $id], 'Puesto actualizado en el catálogo.');
        } else {
            $otro = (int) Database::getValue('SELECT id FROM rrhh_puesto_catalogo WHERE LOWER(nombre) = LOWER(?)', [trim((string)$_POST['nombre'])]);
            if ($otro) Response::validation(['nombre' => 'Ya existe un puesto con ese nombre en el catálogo.']);
            $nid = Requerimientos::guardarPuesto($datos);
            Auditoria::registrar('crear', 'rrhh_puesto_catalogo', $nid, null, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
            Requerimientos::ok(['id' => $nid], 'Puesto agregado al catálogo.');
        }

    } elseif ($accion === 'eliminar') {
        Auth::requirePermission('rrhh', 'eliminar');
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT * FROM rrhh_puesto_catalogo WHERE id = ?', [$id]);
        if (!$antes) Response::error('El puesto ya no existe.');
        if (!Requerimientos::eliminarPuesto($id)) {
            Response::error('Este puesto ya se usó en algún requerimiento: no se puede eliminar, pero puede inactivarlo (Guardar → Estado: Inactivo).');
        }
        Auditoria::registrar('eliminar', 'rrhh_puesto_catalogo', $id, $antes, null, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Requerimientos::ok(null, 'Puesto eliminado del catálogo.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] requerimientos/puestos: ' . $e->getMessage());
    Response::error('No se pudo completar la operación sobre el catálogo de puestos.', null, 500);
}
