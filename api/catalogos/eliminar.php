<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Eliminar registro de catalogo (borrado FISICO protegido)
// Solo si ningun otro registro lo utiliza.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('configuracion', 'gestionar');

 $tipo = $_POST['tipo'] ?? '';
 $id   = (int)($_POST['id'] ?? 0);

 $TABLAS = [
  'areas' => 'areas', 'tipos' => 'tipo_equipos', 'categorias' => 'categorias',
  'subcategorias' => 'subcategorias', 'prioridades' => 'prioridades',
  'proveedores' => 'proveedores', 'proyectos' => 'proyectos',
];
if (!isset($TABLAS[$tipo])) Response::error('Catalogo desconocido.');
 $tabla = $TABLAS[$tipo];

 $fila = Database::getOne("SELECT * FROM $tabla WHERE id = ?", [$id]);
if (!$fila) Response::error('El registro no existe.');

// ---- relaciones que BLOQUEAN el borrado fisico ----
 $USO = [
  'areas' => [
     ['personal','area_id','personal'],
     ['tickets','area_afectada_id','tickets'],
     ['solicitudes_equipo','area_id','solicitudes de equipo'],
  ],
  'tipos' => [
     ['equipos','tipo_equipo_id','equipos del inventario'],
     ['solicitudes_equipo','tipo_equipo_id','solicitudes de equipo'],
  ],
  'categorias' => [
     ['subcategorias','categoria_id','subcategorias'],
     ['tickets','categoria_id','tickets'],
  ],
  'subcategorias' => [
     ['tickets','subcategoria_id','tickets'],
  ],
  'prioridades' => [
     ['tickets','prioridad_id','tickets'],
  ],
  'proveedores' => [
     ['mantenimientos','proveedor_id','mantenimientos'],
     ['mantenimiento_repuestos','proveedor_id','repuestos'],
  ],
  'proyectos' => [
     ['asignaciones','proyecto_id','asignaciones de equipos (activas o historicas)'],
     ['solicitudes_equipo','proyecto_id','solicitudes de equipo'],
  ],
];

foreach ($USO[$tipo] as [$tablaRef, $col, $etiqueta]) {
    $n = (int) Database::getValue("SELECT COUNT(*) FROM $tablaRef WHERE $col = ?", [$id]);
    if ($n > 0) {
        Response::error("No se puede eliminar: hay $n registro(s) en $etiqueta que lo usan. " .
            'Desactive el registro (boton de encendido) para retirarlo de los formularios conservando el historial.');
    }
}

Database::delete($tabla, 'id = ?', [$id]);
if ($tipo === 'areas') {
    // sus roles automaticos no se pierden (pueden tener permisos configurados): quedan sueltos y,
    // si se vuelve a crear un area con el mismo nombre, se enlazan a la nueva
    try { Database::update('roles', ['area_id' => null], 'area_id = ?', [$id]); } catch (Throwable $e) { /* sin columna */ }
}
Auditoria::registrar('eliminar', $tabla, $id, $fila, null);
Response::ok(null, 'Registro eliminado definitivamente.');