<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: catalogo de programas
//   GET  (software.ver)        lista con instalaciones y puestos
//   GET  ?modo=select          programas activos para elegir al instalar (con licencias libres)
//   POST accion = guardar | toggle | eliminar   (software.gestionar)
//     guardar: id (0 = nuevo), nombre, fabricante, categoria, requiere_licencia, autorizacion,
//              estandar, descripcion
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Software::asegurar();

    // ------------------------------------------------------------------ lecturas
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!Auth::can('software', 'ver') && !Auth::can('software', 'registrar')) {
            Auth::requirePermission('software', 'ver');
        }
        $cumpl = [];
        foreach (Software::cumplimiento() as $c) $cumpl[(int)$c['id']] = $c;

        if (($_GET['modo'] ?? '') === 'select') {
            $uso = Software::usoPorLicencia();
            $hoy = date('Y-m-d');
            $libres = [];
            foreach (Database::get(
                "SELECT id, software_id, puestos FROM software_licencias
                 WHERE activo = 1 AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= ?)", [$hoy]) as $l) {
                $libres[(int)$l['software_id']] = ($libres[(int)$l['software_id']] ?? 0)
                    + max(0, (int)$l['puestos'] - ($uso[(int)$l['id']] ?? 0));
            }
            $lista = [];
            foreach (Database::get('SELECT id, nombre, fabricante, categoria, requiere_licencia, autorizacion, estandar
                                    FROM software_catalogo WHERE activo = 1 ORDER BY nombre') as $s) {
                $lista[] = [
                    'id' => (int)$s['id'], 'nombre' => $s['nombre'], 'fabricante' => $s['fabricante'],
                    'categoria' => $s['categoria'], 'requiere_licencia' => (int)$s['requiere_licencia'],
                    'autorizacion' => $s['autorizacion'], 'estandar' => (int)$s['estandar'],
                    'libres' => $libres[(int)$s['id']] ?? 0,
                ];
            }
            Response::ok($lista);
        }

        $lista = [];
        foreach (Database::get('SELECT * FROM software_catalogo ORDER BY nombre') as $s) {
            $id = (int)$s['id'];
            $c = $cumpl[$id] ?? null;
            $enUso = (int) Database::getValue('SELECT COUNT(*) FROM equipo_software WHERE software_id = ?', [$id]);
            $lista[] = [
                'id' => $id, 'nombre' => $s['nombre'], 'fabricante' => $s['fabricante'], 'categoria' => $s['categoria'],
                'categoria_nombre' => Software::etiquetaCategoria($s['categoria']),
                'requiere_licencia' => (int)$s['requiere_licencia'], 'autorizacion' => $s['autorizacion'],
                'estandar' => (int)$s['estandar'], 'descripcion' => $s['descripcion'], 'activo' => (int)$s['activo'],
                'instaladas' => $c['instaladas'] ?? 0, 'licencias' => $c['licencias'] ?? 0,
                'puestos_total' => $c['puestos_total'] ?? 0, 'puestos_vigentes' => $c['puestos_vigentes'] ?? 0,
                'deficit' => $c['deficit'] ?? 0, 'estado' => $c['estado'] ?? ((int)$s['activo'] === 1 ? 'sin_uso' : 'inactivo'),
                'historial' => $enUso,
            ];
        }
        Response::ok([
            'items' => $lista,
            'categorias' => Software::CATEGORIAS,
            'autorizacion' => Software::AUTORIZACION,
        ]);
    }

    // ------------------------------------------------------------------ escrituras
    Csrf::validate();
    Auth::requirePermission('software', 'gestionar');
    $accion = (string)($_POST['accion'] ?? '');
    $uid = Auth::userId();

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $v = Validator::make($_POST, [
            'nombre'       => 'required|maxlen:100',
            'fabricante'   => 'maxlen:80',
            'categoria'    => 'required|in:' . implode(',', array_keys(Software::CATEGORIAS)),
            'autorizacion' => 'required|in:' . implode(',', array_keys(Software::AUTORIZACION)),
            'descripcion'  => 'maxlen:255',
        ], ['nombre' => 'Nombre', 'fabricante' => 'Fabricante', 'categoria' => 'Categoría', 'autorizacion' => 'Autorización', 'descripcion' => 'Descripción']);
        if ($v->fails()) Response::validation($v->errors());

        $nombre = trim((string)$_POST['nombre']);
        $dup = Database::getValue('SELECT id FROM software_catalogo WHERE LOWER(nombre) = ? AND id <> ?', [mb_strtolower($nombre), $id]);
        if ($dup) Response::validation(['nombre' => "«$nombre» ya está en el catálogo."]);

        $fab = trim((string)($_POST['fabricante'] ?? ''));
        $desc = trim((string)($_POST['descripcion'] ?? ''));
        $datos = [
            'nombre' => $nombre, 'fabricante' => $fab !== '' ? $fab : null,
            'categoria' => $_POST['categoria'],
            'requiere_licencia' => !empty($_POST['requiere_licencia']) && $_POST['requiere_licencia'] !== '0' ? 1 : 0,
            'autorizacion' => $_POST['autorizacion'],
            'estandar' => !empty($_POST['estandar']) && $_POST['estandar'] !== '0' ? 1 : 0,
            'descripcion' => $desc !== '' ? $desc : null,
        ];
        if ($datos['autorizacion'] === 'prohibido') $datos['estandar'] = 0;

        if ($id === 0) {
            $datos['creado_por'] = $uid;
            $nuevo = Database::insert('software_catalogo', $datos);
            Auditoria::registrar('crear', 'software_catalogo', $nuevo, null, $datos, ['accion_detalle' => 'programa_creado', 'submodulo' => 'catalogo']);
            Response::ok(['id' => $nuevo, 'mensaje' => "«$nombre» agregado al catálogo."]);
        }
        $antes = Database::getOne('SELECT * FROM software_catalogo WHERE id = ?', [$id]);
        if (!$antes) Response::error('El programa ya no existe.');
        $datos['updated_at'] = date('Y-m-d H:i:s');
        Database::update('software_catalogo', $datos, 'id = ?', [$id]);
        Auditoria::registrar('actualizar', 'software_catalogo', $id, $antes, $datos, ['accion_detalle' => 'programa_editado', 'submodulo' => 'catalogo']);
        $aviso = '';
        if ($datos['autorizacion'] === 'prohibido' && $antes['autorizacion'] !== 'prohibido') {
            $n = (int) Database::getValue(
                "SELECT COUNT(*) FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id WHERE " . Software::INST_VIG . " AND es.software_id = ?", [$id]);
            if ($n > 0) $aviso = " Hay $n equipo(s) con este programa: aparecen como «no autorizado» en el resumen.";
        }
        Response::ok(['id' => $id, 'mensaje' => 'Programa actualizado.' . $aviso]);

    } elseif ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $s = Database::getOne('SELECT * FROM software_catalogo WHERE id = ?', [$id]);
        if (!$s) Response::error('El programa ya no existe.');
        $nuevo = (int)$s['activo'] === 1 ? 0 : 1;
        if ($nuevo === 0) {
            $n = (int) Database::getValue(
                "SELECT COUNT(*) FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id WHERE " . Software::INST_VIG . " AND es.software_id = ?", [$id]);
            if ($n > 0) Response::error("No se puede desactivar: está instalado en $n equipo(s). Retírelo primero.");
        }
        Database::update('software_catalogo', ['activo' => $nuevo, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        Auditoria::registrar('actualizar', 'software_catalogo', $id, ['activo' => (int)$s['activo']], ['activo' => $nuevo],
            ['accion_detalle' => $nuevo ? 'programa_activado' : 'programa_desactivado', 'submodulo' => 'catalogo']);
        Response::ok(['activo' => $nuevo, 'mensaje' => $nuevo ? 'Programa activado.' : 'Programa desactivado: ya no se ofrece al registrar equipos.']);

    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $s = Database::getOne('SELECT * FROM software_catalogo WHERE id = ?', [$id]);
        if (!$s) Response::error('El programa ya no existe.');
        $usos = (int) Database::getValue('SELECT COUNT(*) FROM equipo_software WHERE software_id = ?', [$id]);
        $lics = (int) Database::getValue('SELECT COUNT(*) FROM software_licencias WHERE software_id = ?', [$id]);
        if ($usos > 0 || $lics > 0) {
            Response::error('No se puede eliminar: tiene ' . ($usos ? "$usos instalación(es) en el historial" : "$lics licencia(s)")
                . '. Para auditoría se conserva; use «Desactivar».');
        }
        Database::delete('software_catalogo', 'id = ?', [$id]);
        Auditoria::registrar('eliminar', 'software_catalogo', $id, $s, null, ['accion_detalle' => 'programa_eliminado', 'submodulo' => 'catalogo']);
        Response::ok(['mensaje' => 'Programa eliminado del catálogo.']);

    } else {
        Response::error('Acción inválida.');
    }

} catch (Throwable $e) {
    error_log('[SIGTI] software/catalogo: ' . $e->getMessage());
    Response::error('No se pudo completar la operación del catálogo.', null, 500);
}
