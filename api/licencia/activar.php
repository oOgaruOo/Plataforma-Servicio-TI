<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Licencia: activar o renovar (POST licencia = texto SIGTI1.xxxx.yyyy)
// Se puede usar aun en solo lectura (es la forma de salir de ella).
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('configuracion', 'gestionar');
Csrf::validate();

$txt = trim((string)($_POST['licencia'] ?? ''));
if ($txt === '') Response::validation(['licencia' => 'Pegue el texto de la licencia.']);
if (strlen($txt) > 4000) Response::validation(['licencia' => 'El texto es demasiado largo para ser una licencia.']);

$r = Licencia::activar($txt, (int)Auth::userId());
if (!$r['ok']) Response::error($r['error'], ['licencia' => $r['error']], 422);

$msg = 'Licencia activada. ' . $r['estado']['mensaje'];
Response::ok(['sistema' => Licencia::resumenCliente(), 'mensaje' => $msg], $msg);
