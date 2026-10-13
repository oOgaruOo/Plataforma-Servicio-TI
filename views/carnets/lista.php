<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
 $puedeVer    = Auth::can('personal', 'ver');
 $puedeEditar = Auth::can('personal', 'editar');
if (!$puedeVer):
?>
<div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> No tiene permisos para ver los carnets.</div>
<?php return; endif; ?>
<div class="row g-2 mb-3" id="car-stats">
  <div class="col-6 col-md"><div class="mini-stat"><div class="n" data-k="sin_carnet" style="color:#dc2626">-</div><div class="t">Sin carnet</div></div></div>
  <div class="col-6 col-md"><div class="mini-stat"><div class="n" data-k="pendientes">-</div><div class="t">Pendientes de imprimir</div></div></div>
  <div class="col-6 col-md"><div class="mini-stat"><div class="n" data-k="impresos" style="color:#2563eb">-</div><div class="t">Impresos</div></div></div>
  <div class="col-6 col-md"><div class="mini-stat"><div class="n" data-k="entregados" style="color:#16a34a">-</div><div class="t">Entregados</div></div></div>
  <div class="col-12 col-md"><div class="mini-stat"><div class="n" data-k="renovar" style="color:#d97706">-</div><div class="t">Vencidos / por reimprimir</div></div></div>
</div>
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-person-vcard me-1"></i>Carnets de trabajadores</span>
    <?php if ($puedeEditar): ?>
    <div class="d-flex gap-2 flex-wrap">
      <button id="btn-generar-carnets" class="btn btn-primary btn-sm" disabled>
        <i class="bi bi-plus-lg"></i> Generar (<span id="car-n-gen">0</span>)
      </button>
      <button id="btn-imprimir-carnets" class="btn btn-outline-dark btn-sm" disabled>
        <i class="bi bi-printer"></i> <span id="car-lbl-imp">Imprimir</span> (<span id="car-n-imp">0</span>)
      </button>
      <button id="btn-marcar-impreso" class="btn btn-outline-primary btn-sm" disabled>
        <i class="bi bi-check2"></i> Marcar impresos (<span id="car-n-mi">0</span>)
      </button>
      <button id="btn-marcar-entregado" class="btn btn-outline-success btn-sm" disabled>
        <i class="bi bi-hand-thumbs-up"></i> Marcar entregados (<span id="car-n-me">0</span>)
      </button>
    </div>
    <?php else: ?>
    <button id="btn-imprimir-carnets" class="btn btn-outline-dark btn-sm" disabled>
      <i class="bi bi-printer"></i> <span id="car-lbl-imp">Imprimir</span> (<span id="car-n-imp">0</span>)
    </button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <select id="caf-estado" class="form-select form-select-sm">
          <option value="">Todos los estados</option>
          <option value="sin">Sin carnet</option>
          <option value="pendiente">Pendiente</option>
          <option value="impreso">Impreso</option>
          <option value="entregado">Entregado</option>
          <option value="renovar">Vencido / por reimprimir</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <select id="caf-area" class="form-select form-select-sm"><option value="">Todas las áreas</option></select>
      </div>
      <div class="col-12 col-md-4">
        <input type="text" id="caf-buscar" class="form-control form-control-sm" placeholder="Buscar por código, DNI, nombre o cargo...">
      </div>
      <div class="col-12 col-md-2 text-md-end">
        <button id="caf-limpiar" class="btn btn-outline-secondary btn-sm w-100">Limpiar</button>
      </div>
    </div>
    <div class="table-responsive">
    <table id="tb-carnets" class="table table-bordered table-hover align-middle w-100"
           data-puede-editar="<?= $puedeEditar ? 1 : 0 ?>">
      <thead><tr><th><input type="checkbox" id="car-todos" title="Seleccionar los de esta página"></th><th>Código</th><th>DNI</th><th>Trabajador</th><th>Área</th><th>Cargo</th><th>Carnet</th><th>Vigencia</th><th>Estado</th><th></th></tr></thead>
    </table>
    </div>
  </div>
</div>
