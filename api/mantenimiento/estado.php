<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// Cambio de estado del mantenimiento (flujo validado + efectos en el equipo)
// El paso a "cotizado" sube el archivo de cotizacion (multipart).
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('mantenimiento', 'cambiar_estado');

 $id      = (int)($_POST['id'] ?? 0);
 $nuevo   = $_POST['estado'] ?? '';
 $motivo  = trim((string)($_POST['motivo'] ?? ''));
 $solucion= trim((string)($_POST['solucion'] ?? ''));
 $monto   = trim((string)($_POST['cotizacion_monto'] ?? ''));
 $provId  = (int)($_POST['proveedor_id'] ?? 0);
 $retorno = trim((string)($_POST['fecha_retorno'] ?? ''));

 $m = Database::getOne('SELECT * FROM mantenimientos WHERE id = ?', [$id]);
if (!$m) Response::error('El mantenimiento no existe.');

 $FLUJO = MantenimientoFlujo::FLUJO;
MantenimientoFlujo::asegurar();   // DDL antes de abrir la transaccion
Programados::asegurar();

if (!isset($FLUJO[$m['estado']])) Response::error('Estado actual inválido.');
if (!in_array($nuevo, $FLUJO[$m['estado']], true)) {
    Response::error("Transición no permitida: «{$m['estado']}» → «{$nuevo}».");
}

 $datos = ['estado' => $nuevo];
 $equipoUpdate = null;   // estado a aplicar al equipo
 $revierte = false;      // se anulo el envio al proveedor (atendido por Sistemas)

switch ($nuevo) {

    case 'cancelado':
        if ($motivo === '') Response::validation(['motivo' => 'El motivo de cancelación es obligatorio.']);
        $datos['motivo_cancelacion'] = mb_substr($motivo, 0, 250);
        $datos['fecha_fin'] = date('Y-m-d H:i:s');
        // liberar el equipo: vuelve a su destino si estaba asignado (antes quedaba "en stock"
        // aunque tuviera una asignacion vigente) o a stock si no tenia
        $equipoUpdate = MantenimientoFlujo::estadoAlVolver((int)$m['equipo_id'])[0];
        break;

    case 'en_evaluacion':
    case 'en_proceso':
        // REVERTIR: estaba con el proveedor y se anula el envio (el area de Sistemas lo atiende)
        if (in_array($m['estado'], MantenimientoFlujo::CON_PROVEEDOR, true)) {
            if ($motivo === '') {
                Response::validation(['motivo' => 'Indique por qué se anula el envío al proveedor.']);
            }
            $provNombre = $m['proveedor_id']
                ? (string)Database::getValue('SELECT nombre FROM proveedores WHERE id = ?', [$m['proveedor_id']]) : '';
            $nota = '[' . date('Y-m-d H:i') . '] Envío al proveedor' . ($provNombre !== '' ? " «$provNombre»" : '')
                . ' ANULADO · atendido por Sistemas: ' . mb_substr($motivo, 0, 200);
            $datos['proveedor_id'] = null;
            $datos['fecha_envio_proveedor'] = null;
            $datos['fecha_retorno_estimada'] = null;
            $datos['destino_envio'] = null;
            $datos['observaciones'] = mb_substr(trim((string)$m['observaciones'] . "\n" . $nota), 0, 1000);
            if ($nuevo === 'en_proceso' && !$m['tecnico_usuario_id']) $datos['tecnico_usuario_id'] = Auth::userId();
            $equipoUpdate = 'en_mantenimiento';      // ya no esta con el proveedor
            $revierte = true;
        }
        break;

    case 'enviado_proveedor':
        if ($provId === 0) Response::validation(['proveedor_id' => 'Seleccione el proveedor.']);
        $prov = Database::getOne("SELECT id FROM proveedores WHERE id = ? AND estado = 'activo'", [$provId]);
        if (!$prov) Response::validation(['proveedor_id' => 'El proveedor no existe o está inactivo.']);
        if ($retorno === '') Response::validation(['fecha_retorno' => 'Indique la fecha estimada de retorno.']);
        $d = DateTime::createFromFormat('Y-m-d', $retorno);
        if (!$d || $d->format('Y-m-d') !== $retorno) {
            Response::validation(['fecha_retorno' => 'Fecha inválida.']);
        }
        $datos['proveedor_id'] = $provId;
        $datos['fecha_envio_proveedor'] = date('Y-m-d H:i:s');
        $datos['fecha_retorno_estimada'] = $retorno;
        $equipoUpdate = 'en_reparacion_externa';
        break;

    case 'cotizado':
        if ($monto === '' || !is_numeric($monto) || (float)$monto <= 0) {
            Response::validation(['cotizacion_monto' => 'Indique el monto de la cotización.']);
        }
        if (empty($_FILES['cotizacion_archivo']['name'])) {
            Response::validation(['cotizacion_archivo' => 'Adjunte el archivo de la cotización.']);
        }
        try {
            $up = Upload::guardar($_FILES['cotizacion_archivo'], 'cotizaciones');
            $datos['cotizacion_archivo'] = $up['archivo'];
        } catch (Throwable $e) {
            Response::validation(['cotizacion_archivo' => $e->getMessage()]);
        }
        $datos['cotizacion_monto'] = (float)$monto;
        break;

    case 'aprobado':
        if (!Auth::can('mantenimiento', 'aprobar_costo')) {
            Response::forbidden('Aprobar costos requiere el permiso [mantenimiento.aprobar_costo].');
        }
        $datos['autorizado_por'] = Auth::userId();
        break;

    case 'rechazado':
        if ($motivo === '') Response::validation(['motivo' => 'Indique el motivo del rechazo.']);
        break;

    case 'en_reparacion':
        break;

    case 'recibido_reparado':
        $equipoUpdate = 'en_revision';
        break;

    case 'listo':
        if ($solucion === '') Response::validation(['solucion' => 'Describa el trabajo realizado.']);
        $datos['solucion'] = $solucion;
        break;

    case 'entregado':
        // restaurar segun asignacion activa del equipo
        // vuelve a su destino (persona, area, proyecto u obra); si era prestamo, sigue en prestamo
        $equipoUpdate = MantenimientoFlujo::estadoAlVolver((int)$m['equipo_id'])[0];
        break;

    case 'devuelto_stock':
        // si el equipo tiene una asignacion vigente no puede quedar "en stock" a la vez
        $equipoUpdate = MantenimientoFlujo::estadoAlVolver((int)$m['equipo_id'])[0];
        break;

    case 'cerrado':
        $datos['fecha_fin'] = date('Y-m-d H:i:s');
        // el cierre deja constancia: resultado, quien lo cerro y si hubo atraso (con su motivo)
        $datos += MantenimientoFlujo::datosCierre($m, trim((string)($_POST['resultado'] ?? 'completado')),
            trim((string)($_POST['motivo_atraso'] ?? '')));
        break;

    case 'no_reparable':
        if ($motivo === '') Response::validation(['motivo' => 'Describa por qué no es reparable.']);
        $datos['motivo_cancelacion'] = 'NO REPARABLE: ' . mb_substr($motivo, 0, 230);
        $datos['fecha_fin'] = date('Y-m-d H:i:s');
        // el equipo vuelve a revision (ya no esta en taller ni con el proveedor) -> decidir su BAJA
        $equipoUpdate = 'en_revision';
        break;
}

Database::begin();
try {
    Database::update('mantenimientos', $datos, 'id = ?', [$id]);

    if ($equipoUpdate !== null) {
        Database::update('equipos', ['estado' => $equipoUpdate], 'id = ?', [$m['equipo_id']]);
    }
    // cada cambio de estado queda en el seguimiento del mantenimiento
    Database::insert('equipo_historial', [
        'equipo_id' => $m['equipo_id'], 'usuario_id' => Auth::userId(),
        'evento' => 'mantenimiento',
        'detalle' => "MT {$m['codigo']}: {$m['estado']} → {$nuevo}"
            . ($equipoUpdate !== null ? " · equipo → {$equipoUpdate}" : '')
            . ($revierte ? ' · envío al proveedor ANULADO: atendido por Sistemas' : '')
            . ((in_array($nuevo, ['cancelado', 'rechazado', 'no_reparable'], true) || $revierte) && $motivo !== ''
                ? ' · ' . mb_substr($motivo, 0, 120) : ''),
    ]);

    // preventivo programado: al cerrar se calcula la proxima fecha; si se cancela o no es reparable se libera
    $prog = null;
    if ($nuevo === 'cerrado')          $prog = Programados::alCerrar($m, $datos['fecha_fin']);
    elseif ($nuevo === 'cancelado')    Programados::alCancelar($m);
    elseif ($nuevo === 'no_reparable') Programados::alCancelar($m, true);

    Database::commit();
    Auditoria::registrar('cambiar_estado', 'mantenimientos', $id,
        ['estado' => $m['estado']], $datos);

    $msg = [
        'en_evaluacion' => 'Mantenimiento en evaluación.',
        'en_proceso'    => 'En proceso en taller interno.',
        'enviado_proveedor' => 'Enviado a proveedor (equipo en reparación externa).',
        'cotizado'      => 'Cotización registrada. Pendiente de aprobación.',
        'aprobado'      => 'Costo APROBADO. Puede iniciar la reparación.',
        'rechazado'     => 'Cotización rechazada.',
        'en_reparacion' => 'En reparación.',
        'recibido_reparado' => 'Equipo recibido del proveedor (en revisión).',
        'listo'         => 'Mantenimiento LISTO.',
        'entregado'     => 'Equipo entregado.',
        'devuelto_stock'=> 'Equipo devuelto al stock.',
        'cerrado'       => 'Mantenimiento CERRADO.',
        'cancelado'     => 'Mantenimiento cancelado (equipo liberado).',
        'no_reparable'  => 'Marcado NO REPARABLE. Evalúe darlo de baja en Inventario.',
    ][$nuevo] ?? 'Estado actualizado.';

    // ---- NOTIFICACION: cotizado -> aprobadores ----
if ($nuevo === 'cotizado') {
    Notificador::avisarPermiso('mantenimiento', 'aprobar_costo', 'mantenimiento',
        'Cotizacion pendiente MT ' . $m['codigo'],
        'Monto: S/ ' . number_format((float)$datos['cotizacion_monto'], 2) . ' - requiere aprobacion',
        'mantenimiento/lista');
}
if ($revierte) $msg = 'Envío al proveedor ANULADO: el equipo vuelve a Mantenimiento interno (' . ($nuevo === 'en_proceso' ? 'en proceso, atendido por Sistemas' : 'en evaluación') . ').';
if ($prog) $msg .= ' Próximo mantenimiento programado (' . $prog['frecuencia'] . '): ' . $prog['proxima'] . '.';
Response::ok(['estado' => $nuevo, 'programado' => $prog, 'revertido' => $revierte, 'mensaje' => $msg], $msg);

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al cambiar estado: ' . $e->getMessage());
}