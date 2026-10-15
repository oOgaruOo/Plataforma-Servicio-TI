<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Datos de los reportes (graficos y tarjetas). Los calculos estan en core/Reportes.php
// GET: modulo = resumen|tickets|mantenimiento|inventario|software|asignaciones|personal|rrhh, desde, hasta (Y-m-d)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    $alc = Reportes::alcance();
    if (!$alc) Response::forbidden('No tiene permiso para ver reportes.');

    $modulo = (string)($_GET['modulo'] ?? 'resumen');
    if (!in_array($modulo, Reportes::MODULOS, true)) Response::error('Módulo desconocido.');
    if (!in_array($modulo, $alc['modulos'], true)) Response::forbidden('Su permiso solo cubre los reportes de su área.');

    $r = Reportes::rango($_GET['desde'] ?? '', $_GET['hasta'] ?? '');
    switch ($modulo) {
        case 'resumen':       $d = Reportes::resumen($r, $alc['area']); break;
        case 'tickets':       $d = Reportes::tickets($r, $alc['area']); break;
        case 'mantenimiento': $d = Reportes::mantenimiento($r); break;
        case 'inventario':    $d = Reportes::inventario($r); break;
        case 'software':      $d = Reportes::software($r); break;
        case 'asignaciones':  $d = Reportes::asignaciones($r); break;
        case 'rrhh':          $d = Reportes::rrhh($r, $alc['area']); break;
        default:              $d = Reportes::personal($r, $alc['area']);
    }
    $d['alcance'] = ['area' => $alc['area'], 'solo_area' => $alc['area'] !== null, 'modulos' => $alc['modulos']];
    $d['generado'] = date('d/m/Y H:i');
    Response::ok($d);
} catch (Throwable $e) {
    Response::error('reportes/datos: ' . $e->getMessage() . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}
