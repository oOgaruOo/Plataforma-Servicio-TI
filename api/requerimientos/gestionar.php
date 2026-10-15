<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. › Requerimiento de personal: lo que hace Sistemas / RR.HH.   (rrhh.gestionar)
//   POST accion = tomar | editar | marcar_equipo | marcar_epp | cubrir | cancelar | eliminar
//     tomar          Pendiente -> En proceso
//     editar         corrige los datos (asi se atiende el cambio que el área pidió por chat/ticket);
//                    si vienen equipos[]/epp[] tambien reemplaza esas listas (agregar/quitar items),
//                    conservando "entregado" en los que siguen igual (se identifican por equipo_id/epp_id)
//     marcar_equipo  marca/desmarca un equipo de la lista como entregado
//     marcar_epp     marca/desmarca un EPP de la lista como entregado
//     cubrir         Pendiente/En proceso -> Cubierto (opcionalmente enlaza el contrato generado)
//     cancelar       Pendiente/En proceso -> Cancelado (motivo obligatorio)
//     eliminar       borra de verdad un requerimiento ya CERRADO (Cubierto o Cancelado);
//                    exige rrhh.eliminar (no solo rrhh.gestionar) — uno en curso no se puede borrar
//   Crear y ver: api/requerimientos/solicitudes.php. Cada cambio queda en el historial,
//   en Auditoria y avisa (campana) a quien lo solicitó.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Requerimientos::asegurar();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', null, 405);
    Csrf::validate();

    $accion = (string)($_POST['accion'] ?? '');
    Auth::requirePermission('rrhh', $accion === 'eliminar' ? 'eliminar' : 'gestionar');

    $id = (int)($_POST['id'] ?? 0);
    $f = Database::getOne('SELECT * FROM rrhh_requerimientos WHERE id = ?', [$id]);
    if (!$f) Response::error('El requerimiento ya no existe.');
    $dest = (int)$f['solicitante_usuario_id'];
    $ahora = date('Y-m-d H:i:s');
    $cambio = function (string $nuevo, array $extra, ?string $coment) use ($f, $id, $ahora) {
        Database::update('rrhh_requerimientos', array_merge(['estado' => $nuevo, 'updated_at' => $ahora], $extra), 'id = ?', [$id]);
        Requerimientos::historial($id, $nuevo, $coment);
        Auditoria::registrar('cambiar_estado', 'rrhh_requerimientos', $id, ['estado' => $f['estado']], array_merge(['estado' => $nuevo], $extra),
            ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
    };

    if ($accion === 'tomar') {
        if ($f['estado'] !== 'pendiente') Response::error('Solo se puede tomar un requerimiento «Pendiente» (estado actual: ' . Requerimientos::etiqueta($f['estado']) . ').');
        $cambio('en_proceso', [], 'Sistemas / RR.HH. empezó a gestionar el requerimiento.');
        Notificador::avisar($dest, 'sistema', 'Su requerimiento de personal está en proceso: ' . $f['codigo'],
            '«' . $f['puesto'] . '» · Sistemas / RR.HH. lo está gestionando.', 'rrhh/requerimientos');
        Requerimientos::ok(null, 'Requerimiento ' . $f['codigo'] . ' en proceso.');

    } elseif ($accion === 'editar') {
        if (!in_array($f['estado'], Requerimientos::EN_CURSO, true)) {
            Response::error('Solo se puede editar un requerimiento en curso (estado actual: ' . Requerimientos::etiqueta($f['estado']) . ').');
        }
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
        $smin = ($_POST['salario_min'] ?? '') !== '' ? (float)$_POST['salario_min'] : null;
        $smax = ($_POST['salario_max'] ?? '') !== '' ? (float)$_POST['salario_max'] : null;
        if ($smin !== null && $smax !== null && $smin > $smax) Response::validation(['salario_max' => 'El salario máximo debe ser mayor o igual al mínimo.']);

        // ---- equipos de TI: si el formulario los manda, se reemplaza la lista completa
        //      (agregar/quitar items); si no los manda (llamada antigua), no se tocan.
        $equiposProvistos = isset($_POST['equipos']);
        $requiereEquipo = $equiposProvistos ? !empty($_POST['requiere_equipo']) : (bool)$f['requiere_equipo'];
        $equipos = [];
        if ($equiposProvistos && $requiereEquipo) {
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

        // ---- EPP: idem
        $eppProvistos = isset($_POST['epp']);
        $requiereEpp = $eppProvistos ? !empty($_POST['requiere_epp']) : (bool)$f['requiere_epp'];
        $epp = [];
        if ($eppProvistos && $requiereEpp) {
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

        $antes = ['puesto' => $f['puesto'], 'cantidad' => $f['cantidad'], 'tipo_contrato' => $f['tipo_contrato'],
                  'salario_min' => $f['salario_min'] ?? null, 'salario_max' => $f['salario_max'] ?? null,
                  'justificacion' => $f['justificacion'], 'fecha_limite' => $f['fecha_limite'], 'observaciones' => $f['observaciones'],
                  'requiere_equipo' => (int)$f['requiere_equipo'], 'requiere_epp' => (int)$f['requiere_epp']];
        $puestoTexto = trim((string)$_POST['puesto']);
        // Si Sistemas corrige el puesto, se mantiene sincronizado con el catalogo (se busca o se propone)
        $puestoId = Requerimientos::buscarOProponerPuesto($puestoTexto, Auth::userId()) ?: null;
        $datos = [
            'puesto' => $puestoTexto, 'puesto_id' => $puestoId, 'cantidad' => (int)$_POST['cantidad'],
            'tipo_contrato' => trim((string)($_POST['tipo_contrato'] ?? '')) ?: null,
            'salario_min' => $smin, 'salario_max' => $smax,
            'justificacion' => trim((string)$_POST['justificacion']), 'fecha_limite' => $fl,
            'observaciones' => trim((string)($_POST['observaciones'] ?? '')) ?: null,
            'updated_at' => $ahora,
        ];
        if ($equiposProvistos) $datos['requiere_equipo'] = $requiereEquipo ? 1 : 0;
        if ($eppProvistos) $datos['requiere_epp'] = $requiereEpp ? 1 : 0;
        Database::update('rrhh_requerimientos', $datos, 'id = ?', [$id]);

        if ($equiposProvistos) {
            // Se reemplaza la lista pero se conserva "entregado" en los equipos que siguen (match por equipo_id)
            $previos = Database::get('SELECT equipo_id, entregado, entregado_en FROM rrhh_requerimiento_equipos WHERE requerimiento_id = ?', [$id]);
            $prevMap = [];
            foreach ($previos as $p) if ($p['equipo_id']) $prevMap[(int)$p['equipo_id']] = $p;
            Database::run('DELETE FROM rrhh_requerimiento_equipos WHERE requerimiento_id = ?', [$id]);
            foreach ($equipos as $e) {
                $old = $prevMap[$e['equipo_id']] ?? null;
                Database::insert('rrhh_requerimiento_equipos', $e + [
                    'requerimiento_id' => $id,
                    'entregado' => $old['entregado'] ?? 0,
                    'entregado_en' => $old['entregado_en'] ?? null,
                ]);
            }
        }
        if ($eppProvistos) {
            // Idem, conservando "entregado" en los EPP que siguen (match por epp_id)
            $previosEpp = Database::get('SELECT epp_id, entregado, entregado_en FROM rrhh_requerimiento_epp WHERE requerimiento_id = ?', [$id]);
            $prevMapEpp = [];
            foreach ($previosEpp as $p) if ($p['epp_id']) $prevMapEpp[(int)$p['epp_id']] = $p;
            Database::run('DELETE FROM rrhh_requerimiento_epp WHERE requerimiento_id = ?', [$id]);
            foreach ($epp as $e) {
                $old = $prevMapEpp[$e['epp_id']] ?? null;
                Database::insert('rrhh_requerimiento_epp', $e + [
                    'requerimiento_id' => $id,
                    'entregado' => $old['entregado'] ?? 0,
                    'entregado_en' => $old['entregado_en'] ?? null,
                ]);
            }
        }

        Requerimientos::historial($id, $f['estado'], 'Sistemas / RR.HH. corrigió los datos del requerimiento.');
        Auditoria::registrar('actualizar', 'rrhh_requerimientos', $id, $antes, $datos, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Notificador::avisar($dest, 'sistema', 'Su requerimiento de personal fue actualizado: ' . $f['codigo'],
            'Sistemas / RR.HH. corrigió los datos que usted pidió cambiar.', 'rrhh/requerimientos');
        Requerimientos::ok(null, 'Requerimiento ' . $f['codigo'] . ' actualizado.');

    } elseif ($accion === 'marcar_equipo' || $accion === 'marcar_epp') {
        $tabla = $accion === 'marcar_equipo' ? 'rrhh_requerimiento_equipos' : 'rrhh_requerimiento_epp';
        $itemId = (int)($_POST['item_id'] ?? 0);
        $entregado = !empty($_POST['entregado']);
        $fila = Database::getOne("SELECT * FROM $tabla WHERE id = ? AND requerimiento_id = ?", [$itemId, $id]);
        if (!$fila) Response::error('El ítem no existe en este requerimiento.');
        Database::update($tabla, ['entregado' => $entregado ? 1 : 0, 'entregado_en' => $entregado ? $ahora : null], 'id = ?', [$itemId]);
        Auditoria::registrar('actualizar', 'rrhh_requerimientos', $id, ['entregado' => (int)$fila['entregado']], ['entregado' => $entregado ? 1 : 0],
            ['modulo' => 'rrhh', 'submodulo' => 'requerimientos', 'accion_detalle' => $accion]);
        Requerimientos::ok(null, $entregado ? 'Marcado como entregado.' : 'Desmarcado.');

    } elseif ($accion === 'cubrir') {
        if (!in_array($f['estado'], Requerimientos::EN_CURSO, true)) {
            Response::error('Solo se puede cubrir un requerimiento en curso (estado actual: ' . Requerimientos::etiqueta($f['estado']) . ').');
        }
        $contratoId = (int)($_POST['contrato_id'] ?? 0) ?: null;
        if ($contratoId && !Database::getValue('SELECT id FROM personal_contratos WHERE id = ?', [$contratoId])) {
            Response::validation(['contrato_id' => 'El contrato indicado no existe.']);
        }
        $comentario = trim((string)($_POST['comentario'] ?? ''));
        $cambio('cubierto', ['contrato_id' => $contratoId], $comentario !== '' ? $comentario : 'Vacante cubierta.' . ($contratoId ? ' Enlazada al contrato #' . $contratoId . '.' : ''));
        Notificador::avisar($dest, 'sistema', 'Su requerimiento de personal fue cubierto: ' . $f['codigo'],
            '«' . $f['puesto'] . '» ya quedó contratado.', 'rrhh/requerimientos');
        Requerimientos::ok(null, 'Requerimiento ' . $f['codigo'] . ' marcado como cubierto.');

    } elseif ($accion === 'cancelar') {
        if (!in_array($f['estado'], Requerimientos::EN_CURSO, true)) {
            Response::error('Solo se puede cancelar un requerimiento en curso (estado actual: ' . Requerimientos::etiqueta($f['estado']) . ').');
        }
        $v = Validator::make($_POST, ['motivo' => 'required|minlen:5|maxlen:255'], ['motivo' => 'Motivo']);
        if ($v->fails()) Response::validation($v->errors());
        $motivo = trim((string)$_POST['motivo']);
        $cambio('cancelado', ['motivo_cierre' => $motivo], 'Motivo: ' . $motivo);
        Notificador::avisar($dest, 'sistema', 'Su requerimiento de personal fue cancelado: ' . $f['codigo'],
            '«' . $f['puesto'] . '» · ' . $motivo, 'rrhh/requerimientos');
        Requerimientos::ok(null, 'Requerimiento ' . $f['codigo'] . ' cancelado.');

    } elseif ($accion === 'eliminar') {
        if (!in_array($f['estado'], ['cubierto', 'cancelado'], true)) {
            Response::error('Solo se puede eliminar un requerimiento «Cubierto» o «Cancelado» (estado actual: ' . Requerimientos::etiqueta($f['estado']) . '). Para quitarlo de la lista, primero cúbralo o cancélelo.');
        }
        $codigo = $f['codigo'];
        if (!Requerimientos::eliminar($id)) Response::error('No se pudo eliminar el requerimiento.');
        Auditoria::registrar('eliminar', 'rrhh_requerimientos', $id, $f, null, ['modulo' => 'rrhh', 'submodulo' => 'requerimientos']);
        Requerimientos::ok(null, 'Requerimiento ' . $codigo . ' eliminado.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] requerimientos/gestionar: ' . $e->getMessage());
    Response::error('No se pudo completar la operación de requerimientos de personal.', null, 500);
}
