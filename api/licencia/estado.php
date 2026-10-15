<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Licencia: estado del sistema (GET)
// Cualquier persona con sesion recibe el resumen. Quien tiene configuracion.gestionar recibe ademas el detalle.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

$data = Licencia::resumenCliente();
if (Auth::can('configuracion', 'gestionar')) {
    $e = Licencia::estado();
    $mods = [];
    foreach (Licencia::MODULOS as $k => $d) $mods[] = ['clave' => $k, 'nombre' => $d[0], 'activo' => Licencia::moduloActivo($k)];
    $data['detalle'] = [
        'estado'      => $e['estado'], 'solo_lectura' => $e['solo_lectura'], 'mensaje' => $e['mensaje'], 'dias' => $e['dias'], 'nivel' => $e['nivel'],
        'instalacion' => Licencia::instalacionId(),
        'licencia_id' => $e['licencia_id'], 'empresa' => $e['empresa'], 'ruc' => $e['ruc'], 'vence' => $e['vence'],
        'permanente'  => $e['permanente'], 'emitida' => $e['emitida'], 'prueba_hasta' => $e['prueba_hasta'],
        'aviso_dias'  => Licencia::DIAS_AVISO, 'gracia_dias' => Licencia::DIAS_GRACIA, 'prueba_dias' => Licencia::DIAS_PRUEBA,
        'modulos'     => $mods,
        'rubros'      => Rubros::lista(),
        'empresa_ruc_config' => Empresa::ruc(),
    ];
}
Response::ok($data);
