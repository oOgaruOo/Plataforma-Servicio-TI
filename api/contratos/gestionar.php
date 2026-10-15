<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH. / Contratos: crear, editar, renovar y finalizar  (rrhh.gestionar)
//   POST accion = crear | editar | renovar | finalizar
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', null, 405);
    Csrf::validate();
    Auth::requirePermission('rrhh', 'gestionar');
    Contratos::asegurar();

    $accion = (string)($_POST['accion'] ?? '');
    $uid = Auth::userId();

    if ($accion === 'crear' || $accion === 'editar' || $accion === 'renovar') {
        $personalId = (int)($_POST['personal_id'] ?? 0);
        if ($accion !== 'editar' && $personalId <= 0) Response::validation(['personal_id' => 'Elija la persona.']);
        // 'tipo' se valida contra Contratos::TIPOS (no una lista escrita a mano aqui), para que al agregar un
        // tipo nuevo en esa constante no haga falta acordarse de venir a actualizar tambien esta linea.
        $v = Validator::make($_POST, [
            'tipo'          => 'required|in:' . implode(',', array_keys(Contratos::TIPOS)),
            'puesto'        => 'maxlen:80',
            'salario'       => 'numeric|min:0',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'date',
            'observaciones' => 'maxlen:250',
        ], ['tipo' => 'Tipo de contrato', 'puesto' => 'Puesto', 'salario' => 'Salario', 'fecha_inicio' => 'Fecha de inicio',
            'fecha_fin' => 'Fecha de fin', 'observaciones' => 'Observaciones']);
        if ($v->fails()) Response::validation($v->errors());
        if (!empty($_POST['fecha_fin']) && $_POST['fecha_fin'] < $_POST['fecha_inicio']) {
            Response::validation(['fecha_fin' => 'La fecha de fin no puede ser anterior a la de inicio.']);
        }

        if ($accion === 'crear') {
            if (!Database::getValue('SELECT id FROM personal WHERE id = ?', [$personalId])) Response::validation(['personal_id' => 'La persona no existe.']);
            $r = Contratos::crear($personalId, $_POST, $uid);
            if (!$r['ok']) Response::error($r['error']);
            Auditoria::registrar('crear', 'personal_contratos', $r['id'], null, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'contratos']);
            Response::ok(['id' => $r['id']], 'Contrato registrado.');

        } elseif ($accion === 'renovar') {
            $r = Contratos::renovar($personalId, $_POST, $uid);
            if (!$r['ok']) Response::error($r['error']);
            Auditoria::registrar('crear', 'personal_contratos', $r['id'], null, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'contratos', 'accion_detalle' => 'renovacion']);
            Response::ok(['id' => $r['id']], 'Contrato renovado.');

        } else { // editar
            $id = (int)($_POST['id'] ?? 0);
            $antes = Database::getOne('SELECT * FROM personal_contratos WHERE id = ?', [$id]);
            if (!$antes) Response::error('El contrato ya no existe.');
            Contratos::editar($id, $_POST);
            Auditoria::registrar('editar', 'personal_contratos', $id, $antes, $_POST, ['modulo' => 'rrhh', 'submodulo' => 'contratos']);
            Response::ok(null, 'Contrato actualizado.');
        }

    } elseif ($accion === 'finalizar') {
        $id = (int)($_POST['id'] ?? 0);
        $antes = Database::getOne('SELECT * FROM personal_contratos WHERE id = ?', [$id]);
        if (!$antes) Response::error('El contrato ya no existe.');
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        if (!Contratos::finalizar($id, $motivo)) Response::error('El contrato ya estaba finalizado.');
        Auditoria::registrar('cambiar_estado', 'personal_contratos', $id, $antes, ['vigente' => 0, 'motivo_fin' => $motivo], ['modulo' => 'rrhh', 'submodulo' => 'contratos', 'accion_detalle' => 'finalizar']);
        Response::ok(null, 'Contrato finalizado.');

    } else {
        Response::error('Acción inválida.');
    }
} catch (Throwable $e) {
    error_log('[SIGTI] contratos/gestionar: ' . $e->getMessage());
    Response::error('No se pudo completar la operación.', null, 500);
}
