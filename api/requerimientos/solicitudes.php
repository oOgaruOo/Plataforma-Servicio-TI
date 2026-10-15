<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. › Requerimiento de personal: solicitudes
//   GET  ?vista=mias|todas  ?estado= ?area_id= ?q=   lista (segun lo que puede ver)
//   GET  ?id=               un requerimiento con equipos, EPP e historial
//   POST accion = crear   (rrhh.solicitar)  cualquier área
//   Una vez creado, el área YA NO puede editar ni cancelar: solo pedir un cambio por chat
//   o ticket. Tomar, editar, marcar entregas, cubrir y cancelar: api/requerimientos/gestionar.php
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Requerimientos::asegurar();

    $SQL = "SELECT r.*, u.nombre_completo AS solicitante, a.nombre AS area
            FROM rrhh_requerimientos r
            LEFT JOIN usuarios u ON u.id = r.solicitante_usuario_id
            LEFT JOIN areas a ON a.id = r.area_id";

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $alc = Requerimientos::alcance();
        if (!$alc) Auth::requirePermission('rrhh', 'solicitar');

        // ---- un requerimiento
        if (isset($_GET['id'])) {
            $f = Database::getOne($SQL . ' WHERE r.id = ?', [(int)$_GET['id']]);
            if (!$f || !Requerimientos::puedeVer($f)) Response::error('El requerimiento no existe o no puede verlo.', null, 404);
            $f = Requerimientos::presentar($f);
            $f['puesto_catalogo'] = $f['puesto_id'] ? Requerimientos::puesto($f['puesto_id']) : null;
            $f['equipos'] = array_map(function ($e) {
                $e['id'] = (int)$e['id']; $e['entregado'] = (int)$e['entregado'];
                $e['item_texto'] = !empty($e['nombre']) ? $e['nombre'] : Requerimientos::equipoTexto($e['item']);
                return $e;
            }, Database::get('SELECT * FROM rrhh_requerimiento_equipos WHERE requerimiento_id = ? ORDER BY id', [$f['id']]));
            $f['epp'] = array_map(function ($e) {
                $e['id'] = (int)$e['id']; $e['entregado'] = (int)$e['entregado']; $e['cantidad'] = (int)$e['cantidad'];
                return $e;
            }, Database::get('SELECT * FROM rrhh_requerimiento_epp WHERE requerimiento_id = ? ORDER BY id', [$f['id']]));
            $f['historial'] = array_map(function ($h) {
                $h['estado_texto'] = Requerimientos::etiqueta($h['estado']);
                return $h;
            }, Database::get(
                "SELECT h.estado, h.comentario, h.created_at, u.nombre_completo AS usuario
                 FROM rrhh_requerimiento_historial h LEFT JOIN usuarios u ON u.id = h.usuario_id
                 WHERE h.requerimiento_id = ? ORDER BY h.id", [$f['id']]));
            $f['es_mio'] = (int)$f['solicitante_usuario_id'] === Auth::userId() ? 1 : 0;
            $f['puede_gestionar'] = Auth::can('rrhh', 'gestionar') ? 1 : 0;
            $f['puede_eliminar'] = (Auth::can('rrhh', 'eliminar') && in_array($f['estado'], ['cubierto', 'cancelado'], true)) ? 1 : 0;
            Response::ok($f);
        }

        // ---- lista
        $vista = (string)($_GET['vista'] ?? ($alc['modo'] === 'propias' ? 'mias' : 'todas'));
        $where = ' WHERE 1=1'; $par = [];
        if ($vista === 'mias' || $alc['modo'] === 'propias') {
            $where .= ' AND r.solicitante_usuario_id = ?'; $par[] = Auth::userId();
        } else {
            [$ex, $px] = Requerimientos::condicion($alc, 'r'); $where .= $ex; $par = array_merge($par, $px);
        }
        $estado = (string)($_GET['estado'] ?? '');
        if ($estado !== '' && isset(Requerimientos::ESTADOS[$estado])) { $where .= ' AND r.estado = ?'; $par[] = $estado; }
        $grupo = (string)($_GET['grupo'] ?? '');
        if ($grupo === 'curso') $where .= " AND r.estado IN ('pendiente','en_proceso')";
        elseif ($grupo === 'cerradas') $where .= " AND r.estado IN ('cubierto','cancelado')";
        if ((int)($_GET['area_id'] ?? 0) > 0) { $where .= ' AND r.area_id = ?'; $par[] = (int)$_GET['area_id']; }
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= ' AND (r.codigo LIKE ? OR r.puesto LIKE ? OR u.nombre_completo LIKE ?)';
            array_push($par, $like, $like, $like);
        }
        $filas = Database::get($SQL . $where . " ORDER BY CASE r.estado WHEN 'pendiente' THEN 0 WHEN 'en_proceso' THEN 1 ELSE 2 END,
                                 r.fecha_limite IS NULL, r.fecha_limite, r.id DESC LIMIT 500", $par);
        $out = [];
        $puedeEliminar = Auth::can('rrhh', 'eliminar');
        foreach ($filas as $f) {
            $f = Requerimientos::presentar($f);
            $f['es_mio'] = (int)$f['solicitante_usuario_id'] === Auth::userId() ? 1 : 0;
            $f['puede_eliminar'] = ($puedeEliminar && in_array($f['estado'], ['cubierto', 'cancelado'], true)) ? 1 : 0;
            $out[] = $f;
        }
        Response::ok($out);
    }

    // ------------------------------------------------------------------ escrituras
    Csrf::validate();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        Auth::requirePermission('rrhh', 'solicitar');
        $v = Validator::make($_POST, [
            'puesto'        => 'required|minlen:2|maxlen:120',
            'cantidad'      => 'required|integer|min:1|max:200',
            'tipo_contrato' => 'in:' . implode(',', array_keys(Contratos::TIPOS)),
            'salario_min'   => 'numeric|min:0|max:999999',
            'salario_max'   => 'numeric|min:0|max:999999',
            'justificacion' => 'required|minlen:10|maxlen:500',
            'fecha_limite'  => 'date',
            'observaciones' => 'maxlen:500',
        ], ['puesto' => 'Puesto solicitado', 'cantidad' => 'Cantidad', 'tipo_contrato' => 'Tipo de contrato sugerido',
            'salario_min' => 'Salario mínimo', 'salario_max' => 'Salario máximo',
            'justificacion' => 'Justificación', 'fecha_limite' => 'Plazo para contratar', 'observaciones' => 'Observaciones']);
        if ($v->fails()) Response::validation($v->errors());

        $fl = trim((string)($_POST['fecha_limite'] ?? '')) ?: null;
        if ($fl && $fl < date('Y-m-d')) Response::validation(['fecha_limite' => 'El plazo no puede ser anterior a hoy.']);

        $smin = ($_POST['salario_min'] ?? '') !== '' ? (float)$_POST['salario_min'] : null;
        $smax = ($_POST['salario_max'] ?? '') !== '' ? (float)$_POST['salario_max'] : null;
        if ($smin !== null && $smax !== null && $smin > $smax) Response::validation(['salario_max' => 'El salario máximo debe ser mayor o igual al mínimo.']);

        // ---- equipos solicitados (si marco que requiere) -- vienen del catalogo de equipos de TI
        $requiereEquipo = !empty($_POST['requiere_equipo']);
        $equipos = [];
        if ($requiereEquipo) {
            $crudo = json_decode((string)($_POST['equipos'] ?? '[]'), true);
            if (!is_array($crudo) || count($crudo) === 0) Response::validation(['equipos' => 'Marque al menos un equipo o dispositivo a solicitar.']);
            $catEquipos = [];
            foreach (Requerimientos::catalogoEquipos(true) as $c) $catEquipos[(int)$c['id']] = $c['nombre'];
            foreach ($crudo as $e) {
                $eqid = (int)($e['equipo_id'] ?? 0);
                if (!isset($catEquipos[$eqid])) Response::validation(['equipos' => 'Hay un equipo no reconocido o inactivo en la lista.']);
                $detalle = trim((string)($e['detalle'] ?? ''));
                if (Requerimientos::equipoEsOtro($catEquipos[$eqid]) && $detalle === '') Response::validation(['equipos' => 'Escriba el detalle del equipo «Otro».']);
                $equipos[] = ['item' => 'eq' . $eqid, 'equipo_id' => $eqid, 'nombre' => $catEquipos[$eqid], 'detalle' => $detalle !== '' ? mb_substr($detalle, 0, 200) : null];
            }
        }

        // ---- EPP solicitados (si marco que requiere)
        $requiereEpp = !empty($_POST['requiere_epp']);
        $epp = [];
        if ($requiereEpp) {
            $crudo = json_decode((string)($_POST['epp'] ?? '[]'), true);
            if (!is_array($crudo) || count($crudo) === 0) Response::validation(['epp' => 'Marque al menos un EPP a entregar.']);
            $catalogo = [];
            foreach (Requerimientos::catalogoEpp(true) as $c) $catalogo[(int)$c['id']] = $c['nombre'];
            foreach ($crudo as $e) {
                $eid = (int)($e['epp_id'] ?? 0);
                if (!isset($catalogo[$eid])) Response::validation(['epp' => 'Hay un EPP no reconocido o inactivo en la lista.']);
                $cant = (int)($e['cantidad'] ?? 1); if ($cant < 1) $cant = 1; if ($cant > 50) $cant = 50;
                $epp[] = ['epp_id' => $eid, 'nombre' => $catalogo[$eid], 'cantidad' => $cant];
            }
        }

        $area = Requerimientos::areaDelUsuario(Auth::userId());
        $puestoTexto = trim((string)$_POST['puesto']);
        // El puesto se busca (o se propone como "pendiente" si es nuevo) en el catalogo autonomo
        $puestoId = Requerimientos::buscarOProponerPuesto($puestoTexto, Auth::userId()) ?: null;
        $datos = [
            'solicitante_usuario_id' => Auth::userId(), 'area_id' => $area,
            'puesto' => $puestoTexto,
            'puesto_id' => $puestoId,
            'cantidad' => (int)$_POST['cantidad'],
            'tipo_contrato' => trim((string)($_POST['tipo_contrato'] ?? '')) ?: null,
            'salario_min' => $smin, 'salario_max' => $smax,
            'justificacion' => trim((string)$_POST['justificacion']),
            'fecha_limite' => $fl,
            'requiere_equipo' => $requiereEquipo ? 1 : 0,
            'requiere_epp' => $requiereEpp ? 1 : 0,
            'observaciones' => trim((string)($_POST['observaciones'] ?? '')) ?: null,
            'estado' => 'pendiente',
        ];
        $id = 0;
        for ($i = 0; $i < 3 && !$id; $i++) {            // por si dos areas piden a la vez y chocan en el codigo
            $datos['codigo'] = Requerimientos::codigo();
            try { $id = Database::insert('rrhh_requerimientos', $datos); }
            catch (Throwable $e) { if ($i === 2) throw $e; }
        }
        foreach ($equipos as $e) Database::insert('rrhh_requerimiento_equipos', $e + ['requerimiento_id' => $id]);
        foreach ($epp as $e) Database::insert('rrhh_requerimiento_epp', $e + ['requerimiento_id' => $id]);
        Requerimientos::historial($id, 'pendiente', 'Requerimiento enviado a Sistemas / RR.HH.');
        Auditoria::registrar('crear', 'rrhh_requerimientos', $id, null, $datos, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);

        $areaNom = $area ? (string)Database::getValue('SELECT nombre FROM areas WHERE id = ?', [$area]) : 'sin área';
        Notificador::avisarPermiso('rrhh', 'gestionar', 'sistema',
            'Nuevo requerimiento de personal ' . $datos['codigo'],
            Auth::nombre() . ' (' . $areaNom . ') · ' . $datos['puesto'] . ' · ' . $datos['cantidad'] . ' persona(s)',
            'rrhh/requerimientos');
        Requerimientos::ok(['id' => $id, 'codigo' => $datos['codigo']],
            'Requerimiento ' . $datos['codigo'] . ' enviado. Ya no se puede editar: para un cambio, escriba a Sistemas por el chat o cree un ticket mencionando el código.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] requerimientos/solicitudes: ' . $e->getMessage());
    Response::error('No se pudo completar la operación de requerimientos de personal.', null, 500);
}
