<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeVer   = Auth::can('personal', 'ver');
 $puedeCrear = Auth::can('personal', 'crear');
 $puedeCese  = Auth::can('personal', 'cese');
 $puedeEditar   = Auth::can('personal', 'editar');
 $puedeEliminar = Auth::can('personal', 'eliminar');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para ver el módulo de personal.
  </div>
<?php else: ?>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-people me-1"></i>Personal — registro de ingresos y ceses</span>
    <div class="d-flex gap-2">
      <button id="btn-cumples" class="btn btn-outline-warning btn-sm" title="Próximos cumpleaños del personal">
        <i class="bi bi-cake2"></i> Cumpleaños
      </button>
      <div class="btn-group">
        <button id="btn-sel-acciones" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                aria-expanded="false" disabled title="Acciones sobre las personas marcadas en la tabla">
          <i class="bi bi-check2-square"></i> Seleccionados (0)
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#" data-sel="etq-personal"><i class="bi bi-upc-scan"></i> Etiquetas del personal</a></li>
          <li><a class="dropdown-item" href="#" data-sel="etq-locker"><i class="bi bi-lock"></i> Etiquetas de lockers</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="#" data-sel="correos"><i class="bi bi-envelope"></i> Copiar correos</a></li>
          <li><a class="dropdown-item" href="#" data-sel="csv"><i class="bi bi-filetype-csv"></i> Descargar lista (CSV)</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="#" data-sel="limpiar"><i class="bi bi-x-circle"></i> Quitar la selección</a></li>
        </ul>
      </div>
      <button id="btn-etiquetas-personal" class="btn btn-outline-dark btn-sm" title="Imprimir etiquetas de todo el personal de un área (sin marcar casillas)">
        <i class="bi bi-upc-scan"></i> Etiquetas por área
      </button>
      <button id="btn-ver-pendientes" class="btn btn-outline-danger btn-sm"
              title="Ceses próximos y cesados con equipos pendientes">
        <i class="bi bi-exclamation-octagon"></i> Ceses / Pendientes
      </button>
      <?php if ($puedeCrear): ?>
        <button id="btn-nuevo-personal" class="btn btn-primary btn-sm">
          <i class="bi bi-person-plus"></i> Registrar ingreso (alta)
        </button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-2">
        <select id="pf-estado" class="form-select form-select-sm">
          <option value="">Todos los estados</option>
          <option value="pre_ingreso">Pre-ingreso</option>
          <option value="activo">Activo</option>
          <option value="cese_programado">Cese programado</option>
          <option value="en_proceso_cese">En proceso de cese</option>
          <option value="cesado">Cesado</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select id="pf-area" class="form-select form-select-sm">
          <option value="">Todas las áreas</option>
          <?php foreach (Database::get("SELECT id, nombre FROM areas WHERE estado='activo' ORDER BY nombre") as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select id="pf-campo" class="form-select form-select-sm">
          <option value="nombres">Buscar por nombre/apellido</option>
          <option value="codigo_usuario">Código de usuario</option>
          <option value="dni">DNI</option>
          <option value="cargo">Cargo</option>
          <option value="correo_corporativo">Correo corporativo</option>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="pf-texto" class="form-control" placeholder="Escriba y presione Enter">
        </div>
      </div>
      <div class="col-12 col-md-2 d-flex gap-1">
        <button id="btn-filtrar-personal" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
        <button id="btn-limpiar-personal" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>

    <table id="tb-personal" class="table table-bordered table-hover align-middle w-100"
           data-puede-editar="<?= $puedeEditar ? 1 : 0 ?>" data-puede-eliminar="<?= $puedeEliminar ? 1 : 0 ?>">
      <thead>
        <tr>
          <th style="width:30px"><input type="checkbox" class="form-check-input chk-todos" title="Marcar / desmarcar los de esta página"></th>
          <th>Código</th><th>DNI</th><th>Apellidos</th><th>Nombres</th><th>Área</th><th>Cargo</th>
          <th>Ingresa</th><th>Tipo</th><th>Estado</th><th>Onboarding</th><th>&nbsp;</th>
        </tr>
      </thead>
    </table>

    <div class="text-muted small mt-2">
      <i class="bi bi-info-circle"></i>
      <b>Ver</b> la ficha completa · <b>Editar</b> los datos ·
      <b>Eliminar</b> solo personal en <i>pre-ingreso</i> sin actividad (el resto pasa a «cesado» y queda como histórico) ·
      <b>🎂</b> persona que cumple años hoy · Marque las casillas (se conservan al cambiar de página) y use
      <b>Seleccionados</b> para imprimir etiquetas u otras acciones.
    </div>
  </div>
</div>
<?php endif; ?>