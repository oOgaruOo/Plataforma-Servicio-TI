<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

 $puede = Auth::can('configuracion', 'gestionar');
if (!$puede):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para gestionar la configuracion.
  </div>
<?php
else:

 $tabs = [
    'areas' => [
        'titulo' => 'Areas', 'nuevo' => 'Nueva Area',
        'columnas' => ['Nombre', 'Descripcion', 'Estado', ''],
    ],
    'tipos' => [
        'titulo' => 'Tipos de Equipo', 'nuevo' => 'Nuevo Tipo',
        'columnas' => ['Nombre', 'Activo', ''],
    ],
    'categorias' => [
        'titulo' => 'Categorias', 'nuevo' => 'Nueva Categoria',
        'columnas' => ['Nombre', 'Descripcion', 'Activo', ''],
    ],
    'subcategorias' => [
        'titulo' => 'Subcategorias', 'nuevo' => 'Nueva Subcategoria',
        'columnas' => ['Nombre', 'Categoria', 'Activo', ''],
    ],
    'prioridades' => [
        'titulo' => 'Prioridades / SLA', 'nuevo' => 'Nueva Prioridad',
        'columnas' => ['Nombre', 'Nivel', 'Color', 'SLA (horas)', 'Activo', ''],
    ],
    'proyectos' => [
        'titulo' => 'Proyectos y Obras', 'nuevo' => 'Nuevo Proyecto / Obra',
        'columnas' => ['Codigo', 'Nombre', 'Tipo', 'Ubicacion', 'Responsable', 'Equipos', 'Estado', ''],
    ],
    'proveedores' => [
        'titulo' => 'Proveedores', 'nuevo' => 'Nuevo Proveedor',
        'columnas' => ['Documento', 'Nombre', 'Contacto', 'Telefono', 'Correo', 'Especialidad', 'Estado', ''],
    ],
];
 $primero = true;
?>
<div class="card shadow-sm">
  <div class="card-header p-0">
    <ul class="nav nav-tabs card-header-tabs px-2 pt-2" role="tablist">
      <?php foreach ($tabs as $tipo => $t): ?>
        <li class="nav-item">
          <button class="nav-link <?= $primero ? 'active' : '' ?>" type="button"
                  data-bs-toggle="tab" data-bs-target="#tab-<?= $tipo ?>" data-tipo="<?= $tipo ?>">
            <?= htmlspecialchars($t['titulo']) ?>
          </button>
        </li>
      <?php $primero = false; endforeach; ?>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <?php $primero = true; foreach ($tabs as $tipo => $t): ?>
        <div class="tab-pane fade <?= $primero ? 'show active' : '' ?>" id="tab-<?= $tipo ?>">
          <?php if ($tipo === 'proyectos'): ?>
            <div class="alert alert-light border py-2 small">
              <i class="bi bi-info-circle text-primary"></i>
              Los equipos de TI se pueden asignar a una <strong>persona</strong>, un <strong>area</strong>,
              un <strong>proyecto</strong> o una <strong>obra</strong>. <strong>Ahora se gestionan en Inventario &rsaquo; Proyectos y Obras</strong>
              (con su plazo y los equipos enviados). Los registros son los mismos; aqui puede revisarlos y elegirlos en
              <em>Asignaciones &rsaquo; Registrar entrega</em>. La columna <strong>Equipos</strong>
              muestra cuantos tiene asignados hoy.
            </div>
          <?php endif; ?>
          <?php if ($tipo === 'prioridades'): ?>
            <div class="alert alert-light border py-2 small">
              <i class="bi bi-info-circle text-primary"></i>
              El <strong>SLA</strong> define las horas maximas para resolver un ticket de esa prioridad.
              El <strong>nivel 1</strong> es el mas urgente. Estos valores se usan en el modulo de Tickets (Paso 7).
            </div>
          <?php endif; ?>
          <div class="d-flex justify-content-end gap-2 mb-2">
            <?php if ($tipo === 'proveedores'): ?>
              <button class="btn btn-outline-secondary btn-sm btn-config-doc" type="button"
                      title="Token para traer datos de SUNAT / RENIEC al escribir un RUC o DNI">
                <i class="bi bi-gear"></i> Servicio de consulta DNI/RUC
              </button>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm btn-nuevo" data-tipo="<?= $tipo ?>">
              <i class="bi bi-plus-lg"></i> <?= htmlspecialchars($t['nuevo']) ?>
            </button>
          </div>
          <table id="tb-<?= $tipo ?>" class="table table-bordered table-hover align-middle w-100">
            <thead>
              <tr>
                <?php foreach ($t['columnas'] as $col): ?>
                  <th><?= $col === '' ? '&nbsp;' : htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
          </table>
        </div>
      <?php $primero = false; endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>