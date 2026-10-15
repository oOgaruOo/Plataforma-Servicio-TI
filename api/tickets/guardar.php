<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Crear ticket (SLA automatico) / editar (solo abierto)
// v3: REEMPLAZO COMPLETO con trigger Notificador bien integrado
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('tickets', 'crear');

 $id      = (int)($_POST['id'] ?? 0);
 $esNuevo = ($id === 0);

 $v = Validator::make($_POST, [
    'titulo'            => 'required|minlen:5|maxlen:150',
    'descripcion'       => 'required|minlen:10|maxlen:5000',
    'solicitante_id'    => 'required|integer',
    'area_id'           => 'required|integer',
    'categoria_id'      => 'required|integer',
    'subcategoria_id'   => 'integer',
    'prioridad_id'      => 'required|integer',
    'impacto'           => 'required|in:individual,area,empresa',
], [
    'titulo' => 'Título', 'descripcion' => 'Descripción',
    'solicitante_id' => 'Solicitante', 'area_id' => 'Área afectada',
    'categoria_id' => 'Categoría', 'subcategoria_id' => 'Subcategoría',
    'prioridad_id' => 'Prioridad', 'impacto' => 'Impacto',
]);
if ($v->fails()) Response::validation($v->errors());

// ---- Validaciones contra la BD ----
 $sol = Database::getOne(
    "SELECT id, area_id, estado FROM personal WHERE id = ?", [(int)$_POST['solicitante_id']]);
if (!$sol) Response::validation(['solicitante_id' => 'El solicitante no existe.']);
if (in_array($sol['estado'], ['cesado','en_proceso_cese'], true)) {
    Response::validation(['solicitante_id' => 'El solicitante está cesado o en proceso de cese.']);
}

if (!Database::getValue('SELECT id FROM areas WHERE id = ?', [(int)$_POST['area_id']])) {
    Response::validation(['area_id' => 'El área no existe.']);
}
 $cat = Database::getOne('SELECT id, activo FROM categorias WHERE id = ?', [(int)$_POST['categoria_id']]);
if (!$cat || (int)$cat['activo'] !== 1) {
    Response::validation(['categoria_id' => 'Categoría inválida o inactiva.']);
}

 $subId = null;
if (trim((string)($_POST['subcategoria_id'] ?? '')) !== '') {
    $subId = (int)$_POST['subcategoria_id'];
    $sub = Database::getOne(
        'SELECT id, categoria_id, activo FROM subcategorias WHERE id = ?', [$subId]);
    if (!$sub || (int)$sub['activo'] !== 1) {
        Response::validation(['subcategoria_id' => 'Subcategoría inválida o inactiva.']);
    }
    if ((int)$sub['categoria_id'] !== (int)$_POST['categoria_id']) {
        Response::validation(['subcategoria_id' => 'La subcategoría no pertenece a la categoría elegida.']);
    }
}

 $pri = Database::getOne(
    'SELECT id, nombre, sla_horas, activo FROM prioridades WHERE id = ?', [(int)$_POST['prioridad_id']]);
if (!$pri || (int)$pri['activo'] !== 1) {
    Response::validation(['prioridad_id' => 'Prioridad inválida o inactiva.']);
}

// ---- Equipo vinculado (opcional) ----
 $equipoId = null;
if (trim((string)($_POST['equipo_id'] ?? '')) !== '') {
    $equipoId = (int)$_POST['equipo_id'];
    $eq = Database::getOne('SELECT id, estado, codigo FROM equipos WHERE id = ?', [$equipoId]);
    if (!$eq || $eq['estado'] === 'dado_de_baja') {
        Response::validation(['equipo_id' => 'El equipo seleccionado no existe o está dado de baja.']);
    }
}

// ==================== CREAR ====================
if ($esNuevo) {
    Database::begin();
    try {
        $codigo = Correlativo::generar('ticket');
        $sla = (new DateTime('now'))->add(new DateInterval('PT' . (int)$pri['sla_horas'] . 'H'))->format('Y-m-d H:i:s');

        $datos = [
            'codigo'                => $codigo,
            'titulo'                => trim($_POST['titulo']),
            'descripcion'           => trim($_POST['descripcion']),
            'solicitante_personal_id'=> (int)$_POST['solicitante_id'],
            'area_afectada_id'      => (int)$_POST['area_id'],
            'categoria_id'          => (int)$_POST['categoria_id'],
            'subcategoria_id'       => $subId,
            'prioridad_id'          => (int)$_POST['prioridad_id'],
            'impacto'               => $_POST['impacto'],
            'equipo_id'             => $equipoId,
            'estado'                => 'abierto',
            'nivel_atencion'        => 1,
            'fecha_creacion'        => date('Y-m-d H:i:s'),
            'sla_limite'            => $sla,
        ];

        $tecnicoAsig = (int)($_POST['tecnico_id'] ?? 0);
        if ($tecnicoAsig > 0) {
            $t = Database::getOne('SELECT u.id FROM usuarios u WHERE u.id = ? AND u.estado = "activo"', [$tecnicoAsig]);
            if ($t) {
                $datos['tecnico_usuario_id'] = $tecnicoAsig;
                $datos['estado'] = 'asignado';
                $datos['fecha_asignacion'] = date('Y-m-d H:i:s');
            }
        }

        $nuevoId = Database::insert('tickets', $datos);

        Database::insert('ticket_historial', [
            'ticket_id' => $nuevoId, 'usuario_id' => Auth::userId(),
            'evento' => 'creado',
            'detalle' => "$codigo · prioridad {$pri['nombre']} · SLA límite $sla",
        ]);

        // ---- Adjuntos ----
        if (!empty($_FILES['adjuntos']['name'][0])) {
            $n = count($_FILES['adjuntos']['name']);
            for ($i = 0; $i < $n; $i++) {
                $file = [
                    'name'     => $_FILES['adjuntos']['name'][$i],
                    'type'     => $_FILES['adjuntos']['type'][$i],
                    'tmp_name' => $_FILES['adjuntos']['tmp_name'][$i],
                    'error'    => $_FILES['adjuntos']['error'][$i],
                    'size'     => $_FILES['adjuntos']['size'][$i],
                ];
                if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
                try {
                    $up = Upload::guardar($file, 'adjuntos-tickets');
                    Database::insert('ticket_adjuntos', [
                        'ticket_id' => $nuevoId, 'usuario_id' => Auth::userId(),
                        'archivo' => $up['archivo'], 'nombre_original' => $up['nombre_original'],
                        'tamano_kb' => $up['tamano_kb'],
                    ]);
                } catch (Throwable $e) {
                    error_log('[SIGTI] Adjunto fallido: ' . $e->getMessage());
                }
            }
        }

        Database::commit();
        Auditoria::registrar('crear', 'tickets', $nuevoId, null, $datos, [
            'modulo' => 'tickets', 'submodulo' => 'registro',
            'accion_detalle' => 'ticket_creado_abierto', 'resultado' => 'exito',
        ]);

        // ---- NOTIFICACION (DESPUES del commit, ANTES del Response) ----
        try {
            Notificador::avisarPermiso('tickets', 'asignar', 'ticket',
                "Nuevo ticket $codigo",
                mb_substr(trim($_POST['titulo']), 0, 100) . ' — prioridad ' . $pri['nombre'],
                'tickets/lista');
        } catch (Throwable $notifErr) {
            error_log('[SIGTI] Notificacion fallida: ' . $notifErr->getMessage());
        }

        Response::ok(['id' => $nuevoId, 'codigo' => $codigo, 'sla' => $sla],
            "Ticket $codigo creado. SLA: resolver antes de $sla.");

    } catch (Throwable $e) {
        Database::rollback();
        Response::error('Error al crear el ticket: ' . $e->getMessage());
    }
}

// ==================== EDITAR (solo abierto) ====================
 $t = Database::getOne('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$t) Response::error('El ticket no existe.');
if ($t['estado'] !== 'abierto') {
    Response::error('Solo se pueden editar tickets en estado «abierto».');
}

 $nuevos = [
    'titulo'          => trim($_POST['titulo']),
    'descripcion'     => trim($_POST['descripcion']),
    'area_afectada_id'=> (int)$_POST['area_id'],
    'prioridad_id'    => (int)$_POST['prioridad_id'],
    'impacto'         => $_POST['impacto'],
    'subcategoria_id' => $subId,
    'equipo_id'       => $equipoId,
];

if ((int)$_POST['prioridad_id'] !== (int)$t['prioridad_id']) {
    $sla = (new DateTime('now'))->add(new DateInterval('PT' . (int)$pri['sla_horas'] . 'H'))->format('Y-m-d H:i:s');
    $nuevos['sla_limite'] = $sla;
}

Database::update('tickets', $nuevos, 'id = ?', [$id]);
Database::insert('ticket_historial', [
    'ticket_id' => $id, 'usuario_id' => Auth::userId(),
    'evento' => 'actualizado', 'detalle' => 'Edición del ticket',
]);

// ---- Adjuntos en edicion (se suman a los existentes) ----
if (!empty($_FILES['adjuntos']['name'][0])) {
    $nAdj = count($_FILES['adjuntos']['name']);
    for ($i = 0; $i < $nAdj; $i++) {
        $file = [
            'name'     => $_FILES['adjuntos']['name'][$i],
            'type'     => $_FILES['adjuntos']['type'][$i],
            'tmp_name' => $_FILES['adjuntos']['tmp_name'][$i],
            'error'    => $_FILES['adjuntos']['error'][$i],
            'size'     => $_FILES['adjuntos']['size'][$i],
        ];
        if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
        try {
            $up = Upload::guardar($file, 'adjuntos-tickets');
            Database::insert('ticket_adjuntos', [
                'ticket_id' => $id, 'usuario_id' => Auth::userId(),
                'archivo' => $up['archivo'], 'nombre_original' => $up['nombre_original'],
                'tamano_kb' => $up['tamano_kb'],
            ]);
        } catch (Throwable $e) {
            error_log('[SIGTI] Adjunto fallido (edicion): ' . $e->getMessage());
        }
    }
}

Auditoria::registrar('actualizar', 'tickets', $id, $t, $nuevos, [
    'modulo' => 'tickets', 'submodulo' => 'registro',
    'accion_detalle' => 'ticket_abierto_actualizado', 'resultado' => 'exito',
]);

Response::ok(['id' => $id], 'Ticket actualizado.');
