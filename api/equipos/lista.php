<?php
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeVer   = Auth::can('equipos', 'ver');
 $puedeCrear = Auth::can('equipos', 'crear');
 $puedeBaja  = Auth::can('equipos', 'baja');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para ver el inventario.
  </div>
<?php else: ?>

<div class="row g-2 mb-3" id="equipos-stats">
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="stock">–</div><div class="t">En stock</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="asignados">–</div><div class="t">Asignados</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="mantenimiento">–</div><div class="t">Mantenim.</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="garantia">–</div><div class="t">Garantía <90d</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="baja">–</div><div class="t">De baja</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="valorizado">–</div><div class="t">Valorizado</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-pc-display me-1"></i>Inventario de equipos TI</span>
    <?php if ($puedeCrear): ?>
      <button id="btn-nuevo-equipo" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Registrar equipo
      </button>
    <?php endif; ?>
  </div>
  <div class="card-body">

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <select id="ef-estado" class="form-select form-select-sm">
          <option value="">Todos los estados</option>
          <option value="en_stock">En stock</option>
          <option value="asignado">Asignado</option>
          <option value="en_prestamo">En préstamo</option>
          <option value="en_revision">En revisión</option>
          <option value="en_mantenimiento">En mantenimiento</option>
          <option value="en_reparacion_externa">Reparación externa</option>
          <option value="obsoleto">Obsoleto</option>
          <option value="dado_de_baja">Dado de baja</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <select id="ef-tipo" class="form-select form-select-sm">
          <option value="">Todos los tipos</option>
          <?php foreach (Database::get("SELECT id, nombre FROM tipo_equipos WHERE activo=1 ORDER BY nombre") as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <select id="ef-garantia" class="form-select form-select-sm">
          <option value="">Garantía: cualquiera</option>
          <option value="1">Por vencer (≤ 90 días)</option>
        </select>
      </div>
      <div class="col-6 col-md-3 d-flex gap-1">
        <button id="btn-filtrar-equipos" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
        <button id="btn-limpiar-equipos" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>

    <table id="tb-equipos" class="table table-bordered table-hover align-middle w-100">
      <thead>
        <tr>
          <th>Código</th><th>Tipo</th><th>Marca / Modelo</th><th>Serie</th>
          <th>Estado</th><th>Condición</th><th>Asignado a</th><th>Garantía</th><th>&nbsp;</th>
        </tr>
      </thead>
    </table>

    <div class="text-muted small mt-2">
      <i class="bi bi-info-circle"></i>
      El código <b>EQ-AAAAA-#####</b> se genera automáticamente al registrar.
      Los equipos <b>dados de baja</b> nunca se eliminan: quedan como histórico con su trazabilidad.
    </div>
  </div>
</div>
<?php endif; ?>