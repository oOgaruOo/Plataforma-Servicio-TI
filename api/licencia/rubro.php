<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Licencia: elegir el rubro de la empresa (POST rubro, modulos=1|0)
// Agrega los catalogos del giro (sin borrar nada) y propone los modulos. Se puede repetir sin duplicar.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('configuracion', 'gestionar');
Csrf::validate();

$r = (string)($_POST['rubro'] ?? '');
if (!Rubros::existe($r)) Response::validation(['rubro' => 'Elija un rubro de la lista.']);
$antes = Rubros::actual();
$n = Rubros::aplicar($r, ($_POST['modulos'] ?? '1') !== '0');
Auditoria::registrar('actualizar', 'configuracion', 0, ['rubro' => $antes], ['rubro' => $r, 'agregado' => $n], ['modulo' => 'licencia']);
$msg = 'Rubro aplicado. Se agregaron ' . $n['categorias'] . ' categoría(s), ' . $n['subcategorias'] . ' subcategoría(s), ' . $n['tipos'] . ' tipo(s) de equipo y ' . $n['areas'] . ' área(s).';
Response::ok(['sistema' => Licencia::resumenCliente(), 'agregado' => $n, 'mensaje' => $msg], $msg);
