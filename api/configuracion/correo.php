<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Configuracion del envio de correo (SMTP), para avisos de Anuncios
// GET                   -> estado actual (la clave nunca se devuelve completa)
// POST accion=guardar   -> host, puerto, seguridad, usuario, clave (vacio = conservar),
//                          remitente, remitente_nombre, cacert, activo (0|1)
// POST accion=probar    -> para (correo de prueba), con la configuracion ya guardada
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('configuracion', 'gestionar');

function correo_estado(): array
{
    $c = Correo::config();
    return [
        'host'             => $c['host'],
        'puerto'           => $c['puerto'],
        'seguridad'        => $c['seguridad'],
        'seguridades'      => Correo::SEGURIDADES,
        'usuario'          => $c['usuario'],
        'tiene_clave'      => $c['clave'] !== '',
        'remitente'        => $c['remitente'],
        'remitente_nombre' => $c['remitente_nombre'],
        'cacert'           => $c['cacert'],
        'activo'           => $c['activo'],
        'configurado'      => Correo::activo(),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::ok(correo_estado());
}

Csrf::validate();
$accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    $host    = trim((string) ($_POST['host'] ?? ''));
    $puerto  = (int) ($_POST['puerto'] ?? 587);
    $seg     = (string) ($_POST['seguridad'] ?? 'tls');
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave   = (string) ($_POST['clave'] ?? '');
    $remitente = trim((string) ($_POST['remitente'] ?? ''));
    $remNombre = trim((string) ($_POST['remitente_nombre'] ?? ''));
    $cacert  = trim((string) ($_POST['cacert'] ?? ''));
    $activo  = ($_POST['activo'] ?? '0') === '1';

    $err = [];
    if ($activo || $host !== '') {
        if ($host === '') $err['host'] = 'Indique el servidor SMTP.';
        if (strlen($host) > 150) $err['host'] = 'El servidor es demasiado largo.';
        if ($puerto < 1 || $puerto > 65535) $err['puerto'] = 'Puerto inválido.';
        if (!isset(Correo::SEGURIDADES[$seg])) $err['seguridad'] = 'Seguridad no válida.';
        if ($remitente === '' || !filter_var($remitente, FILTER_VALIDATE_EMAIL)) $err['remitente'] = 'Indique un correo remitente válido.';
        if (strlen($remNombre) > 100) $err['remitente_nombre'] = 'El nombre es demasiado largo.';
    }
    if ($cacert !== '') {
        if (strlen($cacert) > 250) $err['cacert'] = 'La ruta es demasiado larga.';
        elseif (!is_file($cacert)) $err['cacert'] = 'No existe ese archivo en el servidor.';
    }
    if ($err) Response::validation($err);

    Correo::guardarConfig([
        'host' => $host, 'puerto' => $puerto, 'seguridad' => $seg, 'usuario' => $usuario,
        'remitente' => $remitente, 'remitente_nombre' => $remNombre, 'cacert' => $cacert, 'activo' => $activo,
    ], $clave !== '' ? $clave : null);

    Auditoria::registrar('actualizar', 'configuracion', null, null,
        ['correo' => $activo ? 'activado' : 'desactivado', 'host' => $host, 'clave' => $clave !== '' ? 'actualizada' : 'sin cambio']);
    Response::ok(correo_estado(), 'Configuración de correo guardada.');
}

if ($accion === 'probar') {
    $para = trim((string) ($_POST['para'] ?? ''));
    if ($para === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        Response::validation(['para' => 'Ingrese un correo válido para la prueba.']);
    }
    try {
        Correo::enviarPrueba($para);
        Response::ok(null, "Correo de prueba enviado a $para.");
    } catch (CorreoError $e) {
        Response::error($e->getMessage(), null, 422);
    }
}

Response::error('Acción inválida.');
