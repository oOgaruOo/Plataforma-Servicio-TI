<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Validar y consultar un DNI o RUC
// GET ?tipo=dni|ruc & numero=... [& excluir=ID_PROVEEDOR] [& solo_local=1]
//
// Siempre valida el numero (RUC con digito verificador) y avisa si ya esta
// registrado como proveedor / personal. Si hay un servicio configurado
// (Configuracion > Servicio de consulta DNI/RUC) trae ademas los datos de
// SUNAT / RENIEC. Si el servicio falla, igual responde (valido=true) y deja
// el motivo en 'aviso' para que el usuario pueda llenar los datos a mano.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

// lo usan Configuracion (proveedores), Mantenimiento (proveedor nuevo) y Personal (alta)
$permitido = false;
foreach ([['configuracion', 'gestionar'], ['mantenimiento', 'crear'], ['mantenimiento', 'editar'],
          ['personal', 'crear'], ['personal', 'editar']] as [$m, $a]) {
    if (Auth::can($m, $a)) { $permitido = true; break; }
}
if (!$permitido) Response::forbidden('No tiene permiso para consultar documentos.');

$tipo   = strtolower(trim((string)($_GET['tipo'] ?? '')));
$numero = Documento::limpiar($_GET['numero'] ?? '');
$excluir = (int)($_GET['excluir'] ?? 0);

if ($tipo !== 'dni' && $tipo !== 'ruc') Response::error('Tipo inválido. Use dni o ruc.');
if ($numero === '') Response::validation(['numero' => 'Indique el número.']);

if ($tipo === 'dni') {
    if (!Documento::dniValido($numero)) Response::validation(['numero' => 'El DNI debe tener 8 dígitos.']);
} else {
    $err = Documento::rucError($numero);
    if ($err !== null) Response::validation(['numero' => $err]);
}

// ---- ya registrado en el sistema
Esquema::columna('proveedores', 'tipo_documento', "VARCHAR(3) NOT NULL DEFAULT 'ruc'");
$prov = Database::getOne(
    "SELECT id, nombre, estado FROM proveedores WHERE ruc = ? AND tipo_documento = ? AND id <> ? LIMIT 1",
    [$numero, $tipo, $excluir]);
$pers = null;
if ($tipo === 'dni' && (Auth::can('personal', 'ver') || Auth::can('personal', 'crear'))) {
    $pers = Database::getOne("SELECT id, nombres, apellidos, estado FROM personal WHERE dni = ? LIMIT 1", [$numero]);
}

// ---- datos oficiales (SUNAT / RENIEC)
$remoto = null; $aviso = null;
$configurado = Documento::configurado();
if (empty($_GET['solo_local'])) {
    try {
        $remoto = Documento::consultar($tipo, $numero);
    } catch (DocumentoError $e) {
        $aviso = ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()];
    }
}

Response::ok([
    'valido'      => true,
    'tipo'        => $tipo,
    'numero'      => $numero,
    'configurado' => $configurado,
    'remoto'      => $remoto,
    'aviso'       => $aviso,
    'proveedor'   => $prov ?: null,
    'personal'    => $pers ?: null,
    'puede_configurar' => Auth::can('configuracion', 'gestionar'),
], $remoto ? 'Datos obtenidos.' : 'Documento válido.');
