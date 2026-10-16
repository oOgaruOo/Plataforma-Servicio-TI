<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
$puedeVer = Auth::can('rrhh', 'ver');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> No tiene permisos para ver RR.HH.</div>
<?php else:
    $puedeGestionar = Auth::can('rrhh', 'gestionar');
?>
<style>
  .pla-kpi{border:1px solid #e5e7eb;border-radius:.6rem;padding:.6rem .8rem;background:#fff;height:100%}
  .pla-kpi .n{font-size:1.5rem;font-weight:700;line-height:1.1}
  #pla-vista .nav-link{white-space:nowrap}
</style>
<div id="pla-raiz" data-gestionar="<?= $puedeGestionar ? 1 : 0 ?>">
  <div class="card shadow-sm" id="pla-vista">
    <div class="card-header">
      <ul class="nav nav-tabs card-header-tabs" id="pla-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="pla-t-pl" data-bs-toggle="tab" data-bs-target="#pla-p-pl" type="button" role="tab" data-vista="pl"><i class="bi bi-cash-coin me-1"></i>Planillas</button></li>
        <li class="nav-item"><button class="nav-link" id="pla-t-cc" data-bs-toggle="tab" data-bs-target="#pla-p-cc" type="button" role="tab" data-vista="cc"><i class="bi bi-list-check me-1"></i>Conceptos</button></li>
      </ul>
    </div>
    <div class="card-body tab-content">

      <div class="tab-pane fade show active" id="pla-p-pl" role="tabpanel">
        <div class="text-muted small mb-3"><i class="bi bi-info-circle"></i> Planilla referencial de uso interno: NO reemplaza PLAME, boletas electrónicas ni un sistema contable oficial.</div>
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-md-3"><label class="form-label small mb-1">Periodo</label><input type="month" class="form-control form-control-sm" id="pla-periodo"></div>
          <?php if ($puedeGestionar): ?>
          <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary w-100" id="pla-btn-generar"><i class="bi bi-gear me-1"></i>Generar / recalcular</button>
            <button class="btn btn-sm btn-outline-primary text-nowrap" id="pla-btn-agregar" title="Agregar a una persona que quedo afuera, o que recien se incorporo en este periodo"><i class="bi bi-person-plus"></i></button>
          </div>
          <?php endif; ?>
          <div class="col-md-6 d-flex gap-2 flex-wrap">
            <div class="pla-kpi flex-fill"><div class="text-muted small">Generadas</div><div class="n" id="pla-k-gen">—</div></div>
            <div class="pla-kpi flex-fill"><div class="text-muted small">Cerradas</div><div class="n text-success" id="pla-k-cerr">—</div></div>
            <div class="pla-kpi flex-fill"><div class="text-muted small">Total neto</div><div class="n text-primary" id="pla-k-neto">—</div></div>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <input class="form-control form-control-sm w-auto" id="plaf-q" placeholder="Buscar por DNI o nombre…" style="min-width:220px">
          <?php if ($puedeGestionar): ?><button class="btn btn-sm btn-outline-danger" id="pla-btn-cerrar"><i class="bi bi-lock me-1"></i>Cerrar periodo</button><?php endif; ?>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="pla-tb">
            <thead><tr><th>Persona</th><th class="text-end">Base</th><th class="text-end">Ingresos</th><th class="text-end">Descuentos</th><th class="text-end">Neto</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <div class="tab-pane fade" id="pla-p-cc" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-muted small">Catálogo de ingresos y descuentos que se aplican al generar la planilla (fijo en soles, o porcentaje del sueldo base).</div>
          <?php if ($puedeGestionar): ?><button class="btn btn-sm btn-primary" id="pla-btn-nuevo-cc"><i class="bi bi-plus-lg me-1"></i>Nuevo concepto</button><?php endif; ?>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="pla-tb-cc">
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Modo</th><th class="text-end">Valor</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>
