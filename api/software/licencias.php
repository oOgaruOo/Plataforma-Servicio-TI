<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: licencias (puestos, vencimiento, costo)
//   GET  (software.ver)   ?software_id= ?estado=vigente|por_vencer|vencida|baja
//        ?modo=select&software_id=   licencias con puestos libres para asignar a una instalacion
//        La CLAVE solo se ve con software.gestionar (los demas ven los 4 ultimos caracteres).
//   precio_unitario x puestos = costo total (se calcula aqui).
//   La fecha de activacion + la duracion calculan solas el vencimiento.
//   POST accion = guardar | baja | eliminar     (software.gestionar)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

function sw_ult4(?string $clave): string
{
    $clave = (string)$clave;
    if ($clave === '') return '';
    return '…' . mb_substr($clave, -4);
}

try {
    Auth::check();
    Software::asegurar();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!Auth::can('software', 'ver') && !Auth::can('software', 'registrar')) {
            Auth::requirePermission('software', 'ver');
        }
        $verClave = Auth::can('software', 'gestionar');
        $hoy = date('Y-m-d');
        $uso = Software::usoPorLicencia();
        $swId = (int)($_GET['software_id'] ?? 0);

        $where = []; $par = [];
        if ($swId > 0) { $where[] = 'l.software_id = ?'; $par[] = $swId; }
        $modoSelect = ($_GET['modo'] ?? '') === 'select';
        if ($modoSelect) {
            $where[] = 'l.activo = 1';
            $where[] = '(l.fecha_vencimiento IS NULL OR l.fecha_vencimiento >= ?)';
            $par[] = $hoy;
        }
        $cond = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $filas = Database::get(
            "SELECT l.*, s.nombre AS software, s.categoria
             FROM software_licencias l INNER JOIN software_catalogo s ON s.id = l.software_id$cond
             ORDER BY s.nombre, CASE WHEN l.fecha_vencimiento IS NULL THEN 1 ELSE 0 END, l.fecha_vencimiento, l.id", $par);

        $fEstado = (string)($_GET['estado'] ?? '');
        $out = [];
        foreach ($filas as $l) {
            $usados = $uso[(int)$l['id']] ?? 0;
            $estado = (int)$l['activo'] === 1 ? Software::estadoLicencia($l['fecha_vencimiento'], $hoy) : 'baja';
            if ($modoSelect && $usados >= (int)$l['puestos']) continue;
            if ($fEstado !== '' && !$modoSelect && $estado !== $fEstado) continue;
            $dias = $l['fecha_vencimiento'] ? (int) floor((strtotime($l['fecha_vencimiento']) - strtotime($hoy)) / 86400) : null;
            $item = [
                'id' => (int)$l['id'], 'software_id' => (int)$l['software_id'], 'software' => $l['software'],
                'tipo' => $l['tipo'], 'tipo_nombre' => Software::TIPOS_LIC[$l['tipo']] ?? $l['tipo'],
                'puestos' => (int)$l['puestos'], 'usados' => $usados, 'libres' => max(0, (int)$l['puestos'] - $usados),
                'fecha_compra' => $l['fecha_compra'], 'fecha_activacion' => $l['fecha_activacion'] ?? null,
                'duracion_valor' => ($l['duracion_valor'] ?? null) !== null ? (int)$l['duracion_valor'] : null,
                'duracion_unidad' => $l['duracion_unidad'] ?? null,
                'duracion_txt' => Software::textoDuracion($l['duracion_valor'] ?? null, $l['duracion_unidad'] ?? null),
                'fecha_vencimiento' => $l['fecha_vencimiento'], 'dias' => $dias,
                'precio_unitario' => ($l['precio_unitario'] ?? null) !== null ? (float)$l['precio_unitario'] : null,
                'costo' => $l['costo'] !== null ? (float)$l['costo'] : null, 'proveedor' => $l['proveedor'],
                'documento' => $l['documento'], 'observaciones' => $l['observaciones'],
                'activo' => (int)$l['activo'], 'estado' => $estado,
                'tiene_clave' => ($l['clave'] ?? '') !== '' ? 1 : 0, 'clave_ult4' => sw_ult4($l['clave']),
                'tiene_factura' => ($l['factura_archivo'] ?? '') !== '' ? 1 : 0,
                'factura_nombre' => $l['factura_nombre'] ?? null,
                'factura_kb' => ($l['factura_kb'] ?? null) !== null ? (int)$l['factura_kb'] : null,
                'factura_pdf' => strtolower(pathinfo((string)($l['factura_archivo'] ?? ''), PATHINFO_EXTENSION)) === 'pdf' ? 1 : 0,
            ];
            if ($verClave) $item['clave'] = $l['clave'];
            $out[] = $item;
        }
        Response::ok($out);
    }

    // ------------------------------------------------------------------ escrituras
    Csrf::validate();
    Auth::requirePermission('software', 'gestionar');
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $v = Validator::make($_POST, [
            'software_id'       => 'required|integer',
            'tipo'              => 'required|in:' . implode(',', array_keys(Software::TIPOS_LIC)),
            'clave'             => 'maxlen:190',
            'puestos'           => 'required|integer|min:1|max:100000',
            'fecha_compra'      => 'date',
            'fecha_activacion'  => 'date',
            'duracion_valor'    => 'integer|min:1|max:3650',
            'duracion_unidad'   => 'in:' . implode(',', array_keys(Software::UNIDADES_DURACION)),
            'fecha_vencimiento' => 'date',
            'precio_unitario'   => 'numeric|min:0|max:9999999',
            'costo'             => 'numeric|min:0|max:99999999',
            'proveedor'         => 'maxlen:100',
            'documento'         => 'maxlen:60',
            'observaciones'     => 'maxlen:255',
        ], ['software_id' => 'Programa', 'tipo' => 'Tipo de licencia', 'clave' => 'Clave', 'puestos' => 'Puestos',
            'fecha_compra' => 'Fecha de compra', 'fecha_activacion' => 'Fecha de activación',
            'duracion_valor' => 'Duración', 'duracion_unidad' => 'Unidad de duración', 'fecha_vencimiento' => 'Vencimiento', 'precio_unitario' => 'Precio unitario', 'costo' => 'Costo',
            'proveedor' => 'Proveedor', 'documento' => 'Documento', 'observaciones' => 'Observaciones']);
        if ($v->fails()) Response::validation($v->errors());

        $sw = Database::getOne('SELECT id, nombre FROM software_catalogo WHERE id = ?', [(int)$_POST['software_id']]);
        if (!$sw) Response::validation(['software_id' => 'El programa no existe.']);

        $nulo = fn(string $k): ?string => (trim((string)($_POST[$k] ?? '')) === '') ? null : trim((string)$_POST[$k]);
        $fc = $nulo('fecha_compra'); $fv = $nulo('fecha_vencimiento');
        $fa = $nulo('fecha_activacion'); $dv = $nulo('duracion_valor'); $du = $nulo('duracion_unidad');

        // Activacion + duracion = vencimiento (se calcula aqui; manda sobre la fecha escrita a mano)
        if ($dv !== null && $du === null) $du = 'meses';
        if ($dv !== null && !$fa) Response::validation(['fecha_activacion' => 'Indique la fecha de activación para calcular el vencimiento.']);
        if ($dv === null) { $du = null; }
        if ($fa && $dv !== null) {
            $fv = Software::calcularVencimiento($fa, $dv, $du);
            if (!$fv) Response::validation(['duracion_valor' => 'No se pudo calcular el vencimiento con esa duración.']);
        }
        if ($fa && $fc && $fa < $fc) Response::validation(['fecha_activacion' => 'La activación no puede ser anterior a la compra.']);
        if ($fa && $fv && $fv < $fa) Response::validation(['fecha_vencimiento' => 'El vencimiento no puede ser anterior a la activación.']);
        if ($fc && $fv && $fv < $fc) Response::validation(['fecha_vencimiento' => 'El vencimiento no puede ser anterior a la compra.']);
        if ($_POST['tipo'] === 'suscripcion' && !$fv) Response::validation(['fecha_vencimiento' => 'Una suscripción necesita fecha de vencimiento.']);

        // Precio por puesto: el costo total se calcula solo (precio unitario x puestos)
        $pu = $nulo('precio_unitario') !== null ? round((float)$_POST['precio_unitario'], 2) : null;
        $costo = $nulo('costo') !== null ? (float)$_POST['costo'] : null;
        if ($pu !== null) {
            $costo = round($pu * (int)$_POST['puestos'], 2);
            if ($costo > 99999999) Response::validation(['precio_unitario' => 'El costo total resultante es demasiado alto.']);
        }

        $datos = [
            'software_id' => (int)$sw['id'], 'tipo' => $_POST['tipo'], 'clave' => $nulo('clave'),
            'puestos' => (int)$_POST['puestos'], 'fecha_compra' => $fc,
            'fecha_activacion' => $fa, 'duracion_valor' => $dv !== null ? (int)$dv : null, 'duracion_unidad' => $du,
            'fecha_vencimiento' => $fv,
            'precio_unitario' => $pu,
            'costo' => $costo,
            'proveedor' => $nulo('proveedor'), 'documento' => $nulo('documento'), 'observaciones' => $nulo('observaciones'),
        ];

        if ($id === 0) {
            $datos['creado_por'] = Auth::userId();
            $nuevo = Database::insert('software_licencias', $datos);
            Auditoria::registrar('crear', 'software_licencias', $nuevo, null,
                $datos + ['software' => $sw['nombre'], 'tiene_clave' => $datos['clave'] ? 1 : 0],
                ['accion_detalle' => 'licencia_creada', 'submodulo' => 'licencias']);
            Response::ok(['id' => $nuevo, 'mensaje' => 'Licencia registrada: ' . $datos['puestos'] . ' puesto(s) de «' . $sw['nombre'] . '».']);
        }

        $antes = Database::getOne('SELECT * FROM software_licencias WHERE id = ?', [$id]);
        if (!$antes) Response::error('La licencia ya no existe.');
        if ((int)$antes['software_id'] !== $datos['software_id']) {
            $enUso = (int) Database::getValue('SELECT COUNT(*) FROM equipo_software WHERE licencia_id = ?', [$id]);
            if ($enUso > 0) Response::validation(['software_id' => 'No se puede cambiar el programa: la licencia ya se usó en ' . $enUso . ' instalación(es).']);
        }
        $usados = Software::usoPorLicencia()[$id] ?? 0;
        if ($datos['puestos'] < $usados) {
            Response::validation(['puestos' => "No puede tener menos puestos que los que se usan hoy ($usados). Retire primero el software de algún equipo."]);
        }
        $datos['updated_at'] = date('Y-m-d H:i:s');
        Database::update('software_licencias', $datos, 'id = ?', [$id]);
        $ant = $antes; unset($ant['factura_archivo']); $ant['tiene_clave'] = $antes['clave'] ? 1 : 0; $ant['clave_ult4'] = sw_ult4($antes['clave']); unset($ant['clave']);
        $nue = $datos; $nue['tiene_clave'] = $datos['clave'] ? 1 : 0; $nue['clave_ult4'] = sw_ult4($datos['clave']); unset($nue['clave']);
        if ((string)$antes['clave'] !== (string)$datos['clave']) $nue['clave_cambiada'] = 1;
        Auditoria::registrar('actualizar', 'software_licencias', $id, $ant, $nue, ['accion_detalle' => 'licencia_editada', 'submodulo' => 'licencias']);
        Response::ok(['id' => $id, 'mensaje' => 'Licencia actualizada.']);

    } elseif ($accion === 'baja') {
        $id = (int)($_POST['id'] ?? 0);
        $l = Database::getOne('SELECT * FROM software_licencias WHERE id = ?', [$id]);
        if (!$l) Response::error('La licencia ya no existe.');
        $nuevo = (int)$l['activo'] === 1 ? 0 : 1;
        if ($nuevo === 0) {
            $usados = Software::usoPorLicencia()[$id] ?? 0;
            if ($usados > 0) Response::error("No se puede dar de baja: $usados equipo(s) la están usando. Cámbieles la licencia o retire el software.");
        }
        Database::update('software_licencias', ['activo' => $nuevo, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        Auditoria::registrar('actualizar', 'software_licencias', $id, ['activo' => (int)$l['activo']], ['activo' => $nuevo],
            ['accion_detalle' => $nuevo ? 'licencia_reactivada' : 'licencia_baja', 'submodulo' => 'licencias']);
        Response::ok(['activo' => $nuevo, 'mensaje' => $nuevo ? 'Licencia reactivada.' : 'Licencia dada de baja (queda en el historial).']);

    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $l = Database::getOne('SELECT * FROM software_licencias WHERE id = ?', [$id]);
        if (!$l) Response::error('La licencia ya no existe.');
        $n = (int) Database::getValue('SELECT COUNT(*) FROM equipo_software WHERE licencia_id = ?', [$id]);
        if ($n > 0) Response::error('No se puede eliminar: se usó en ' . $n . ' instalación(es) y queda en el historial. Use «Dar de baja».');
        Database::delete('software_licencias', 'id = ?', [$id]);
        Documentos::borrar('facturas-licencias', $l['factura_archivo'] ?? null);
        $ant = $l; unset($ant['factura_archivo']); $ant['tiene_clave'] = $l['clave'] ? 1 : 0; $ant['clave_ult4'] = sw_ult4($l['clave']); unset($ant['clave']);
        Auditoria::registrar('eliminar', 'software_licencias', $id, $ant, null, ['accion_detalle' => 'licencia_eliminada', 'submodulo' => 'licencias']);
        Response::ok(['mensaje' => 'Licencia eliminada.']);

    } else {
        Response::error('Acción inválida.');
    }

} catch (Throwable $e) {
    error_log('[SIGTI] software/licencias: ' . $e->getMessage());
    Response::error('No se pudo completar la operación de licencias.', null, 500);
}
