<?php
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeVer   = Auth::can('personal', 'ver');
 $puedeCrear = Auth::can('personal', 'crear');
 $puedeCese  = Auth::can('personal', 'cese');
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

    <table id="tb-personal" class="table table-bordered table-hover align-middle w-100">
      <thead>
        <tr>
          <th>DNI</th><th>Apellidos</th><th>Nombres</th><th>Área</th><th>Cargo</th>
          <th>Ingresa</th><th>Tipo</th><th>Estado</th><th>Onboarding</th><th>&nbsp;</th>
        </tr>
      </thead>
    </table>

    <div class="text-muted small mt-2">
      <i class="bi bi-info-circle"></i>
      <b>📅</b> Registrar cese (programa fecha + checklist de salida) ·
      <b>📥</b> Procesar cese (devolución masiva + bloqueo de cuentas + acta LUMAT-TI-FOR-001) ·
      <b>🚨</b> Ceses/Pendientes (reporte crítico).
    </div>
  </div>
</div>
<?php endif; ?>