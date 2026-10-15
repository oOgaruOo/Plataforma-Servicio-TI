<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Configuracion del servicio de consulta DNI / RUC
// GET                      -> estado actual (el token nunca se devuelve completo)
// POST accion=guardar      -> servicio, token (vacio = conservar), cacert
// POST accion=probar       -> consulta real de prueba (tipo, numero) sin usar cache
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('configuracion', 'gestionar');

function cdoc_estado(): array
{
    $c = Documento::config();
    $t = $c['token'];
    return [
        'servicio'   => $c['servicio'],
        'servicios'  => Documento::SERVICIOS,
        'tiene_token'=> $t !== '',
        'token_mask' => $t === '' ? '' : (str_repeat('•', 8) . substr($t, -4)),
        'cacert'     => $c['cacert'],
        'configurado'=> Documento::configurado(),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::ok(cdoc_estado());
}

Csrf::validate();
$accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    $servicio = trim((string)($_POST['servicio'] ?? ''));
    $token    = trim((string)($_POST['token'] ?? ''));
    $cacert   = trim((string)($_POST['cacert'] ?? ''));
    $quitar   = ($_POST['quitar_token'] ?? '') === '1';

    $err = [];
    if ($servicio !== '' && !isset(Documento::SERVICIOS[$servicio])) $err['servicio'] = 'Servicio no válido.';
    if (strlen($token) > 250)  $err['token'] = 'El token es demasiado largo.';
    if (preg_match('/\s/', $token)) $err['token'] = 'El token no debe contener espacios.';
    if (strlen($cacert) > 250) $err['cacert'] = 'La ruta es demasiado larga.';
    if ($cacert !== '' && !is_file($cacert)) $err['cacert'] = 'No existe ese archivo en el servidor.';
    if ($err) Response::validation($err);

    // token vacio = conservar el actual (salvo que pidan quitarlo)
    Documento::guardarConfig($servicio, $quitar ? '' : ($token !== '' ? $token : null), $cacert);
    // el token nunca se escribe en auditoria
    Auditoria::registrar('actualizar', 'configuracion', 0, null,
        ['consulta_documentos' => $servicio === '' ? 'desactivado' : $servicio, 'token' => $token !== '' ? 'actualizado' : 'sin cambio']);
    Response::ok(cdoc_estado(), 'Configuración guardada.');
}

if ($accion === 'probar') {
    $tipo   = ($_POST['tipo'] ?? 'ruc') === 'dni' ? 'dni' : 'ruc';
    $numero = Documento::limpiar($_POST['numero'] ?? '');
    if ($tipo === 'dni' ? !Documento::dniValido($numero) : !Documento::rucValido($numero)) {
        Response::validation(['numero' => $tipo === 'dni' ? 'DNI de 8 dígitos.' : (Documento::rucError($numero) ?? 'RUC inválido.')]);
    }
    try {
        $d = Documento::consultar($tipo, $numero, ['sin_cache' => true]);
        Response::ok(['remoto' => $d], 'La conexión funciona.');
    } catch (DocumentoError $e) {
        Response::error($e->getMessage(), ['codigo' => $e->codigo], 422);
    }
}

Response::error('Acción inválida.');
