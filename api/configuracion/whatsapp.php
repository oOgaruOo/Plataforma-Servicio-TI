<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Configuracion del envio por WhatsApp (puente Node.js), para avisos de Anuncios
// GET                     -> estado guardado + estado en vivo del puente (vinculado, numero, QR)
// POST accion=guardar     -> url, token (vacio = conservar), activo (0|1)
// POST accion=desvincular -> cierra la sesion de WhatsApp en el puente (hay que volver a escanear el QR)
// POST accion=probar      -> numero, mensaje (opcional) -> manda un mensaje real de prueba
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('configuracion', 'gestionar');

function wa_estado(): array
{
    $c = WhatsApp::config();
    $e = WhatsApp::estado();
    return [
        'url'         => $c['url'],
        'tiene_token' => $c['token'] !== '',
        'activo'      => $c['activo'],
        'configurado' => WhatsApp::configurado(),
        'vinculado'   => $e['vinculado'],
        'numero'      => $e['numero'],
        'qr'          => $e['qr'],
        'error'       => $e['error'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::ok(wa_estado());
}

Csrf::validate();
$accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    $url    = trim((string) ($_POST['url'] ?? ''));
    $token  = (string) ($_POST['token'] ?? '');
    $activo = ($_POST['activo'] ?? '0') === '1';

    $err = [];
    if ($activo || $url !== '') {
        if ($url === '') $err['url'] = 'Indique la dirección del puente (ej: http://127.0.0.1:3300).';
        elseif (!preg_match('#^https?://#i', $url)) $err['url'] = 'Debe empezar con http:// o https://';
        elseif (strlen($url) > 200) $err['url'] = 'La dirección es demasiado larga.';
    }
    if ($err) Response::validation($err);

    WhatsApp::guardarConfig($url, $token !== '' ? $token : null, $activo);

    Auditoria::registrar('actualizar', 'configuracion', null, null,
        ['whatsapp' => $activo ? 'activado' : 'desactivado', 'url' => $url, 'token' => $token !== '' ? 'actualizado' : 'sin cambio']);
    Response::ok(wa_estado(), 'Configuración de WhatsApp guardada.');
}

if ($accion === 'desvincular') {
    try {
        WhatsApp::desvincular();
        Auditoria::registrar('actualizar', 'configuracion', null, null, ['whatsapp' => 'desvinculado']);
        Response::ok(wa_estado(), 'WhatsApp desvinculado. Escanee el código QR para volver a usarlo.');
    } catch (WhatsAppError $e) {
        Response::error($e->getMessage(), null, 422);
    }
}

if ($accion === 'probar') {
    $numero = trim((string) ($_POST['numero'] ?? ''));
    $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
    if ($mensaje === '') $mensaje = 'Este es un mensaje de prueba enviado desde SIGTI. Si lo recibió, la configuración de WhatsApp está funcionando.';
    if (WhatsApp::normalizarNumero($numero) === null) {
        Response::validation(['numero' => 'Número de WhatsApp inválido. Use el celular con código de país (ej: 51987654321).']);
    }
    try {
        WhatsApp::enviar($numero, $mensaje);
        Response::ok(null, "Mensaje de prueba enviado a $numero.");
    } catch (WhatsAppError $e) {
        Response::error($e->getMessage(), null, 422);
    }
}

Response::error('Acción inválida.');
