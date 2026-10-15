<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Activar / desactivar catalogo (borrado logico)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('configuracion', 'gestionar');

 $tipo = $_POST['tipo'] ?? '';
 $id   = (int)($_POST['id'] ?? 0);

 $CONFIG = [
    'areas'         => ['tabla' => 'areas',         'campo' => 'estado', 'modo' => 'enum'],
    'tipos'         => ['tabla' => 'tipo_equipos',  'campo' => 'activo', 'modo' => 'bool'],
    'categorias'    => ['tabla' => 'categorias',    'campo' => 'activo', 'modo' => 'bool'],
    'subcategorias' => ['tabla' => 'subcategorias', 'campo' => 'activo', 'modo' => 'bool'],
    'prioridades'   => ['tabla' => 'prioridades',   'campo' => 'activo', 'modo' => 'bool'],
    'proveedores'   => ['tabla' => 'proveedores',   'campo' => 'estado', 'modo' => 'enum'],
    'proyectos'     => ['tabla' => 'proyectos',     'campo' => 'estado', 'modo' => 'enum'],
];

if (!isset($CONFIG[$tipo])) Response::error('Catalogo desconocido.');
if ($id === 0)              Response::error('Registro no especificado.');

 $c    = $CONFIG[$tipo];
 $fila = Database::getOne("SELECT id, {$c['campo']} AS valor FROM {$c['tabla']} WHERE id = ?", [$id]);
if (!$fila) Response::error('El registro no existe.');

 $nuevo = ($c['modo'] === 'enum')
    ? ($fila['valor'] === 'activo' ? 'inactivo' : 'activo')
    : (((int)$fila['valor'] === 1) ? 0 : 1);

if ($tipo === 'proyectos' && $nuevo === 'inactivo') {
    $n = (int) Database::getValue(
        "SELECT COUNT(*) FROM asignaciones WHERE proyecto_id = ? AND estado IN ('activa','vencida')", [$id]);
    if ($n > 0) {
        Response::error("No se puede desactivar: tiene $n equipo(s) asignados. Devuelvalos o trasladelos primero.");
    }
}

Database::update($c['tabla'], [$c['campo'] => $nuevo], 'id = ?', [$id]);
Auditoria::registrar('cambiar_estado', $c['tabla'], $id, $fila, [$c['campo'] => $nuevo]);

Response::ok(null, 'Estado actualizado correctamente.');