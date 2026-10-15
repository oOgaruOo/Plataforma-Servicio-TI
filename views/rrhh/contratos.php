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
  .ctr-kpi{border:1px solid #e5e7eb;border-radius:.6rem;padding:.6rem .8rem;background:#fff;height:100%}
  .ctr-kpi .n{font-size:1.5rem;font-weight:700;line-height:1.1}
</style>
<div id="ctr-raiz" data-gestionar="<?= $puedeGestionar ? 1 : 0 ?>">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="text-muted small"><i class="bi bi-info-circle"></i> Contratos del personal (gestión interna). No reemplaza un sistema contable ni tiene validez tributaria/legal.</div>
        <?php if ($puedeGestionar): ?><button class="btn btn-primary btn-sm" id="ctr-btn-nuevo"><i class="bi bi-plus-lg me-1"></i>Nuevo contrato</button><?php endif; ?>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="ctr-kpi"><div class="text-muted small">Vigentes</div><div class="n text-success" id="ctr-k-vigentes">—</div></div></div>
        <div class="col-6 col-md-3"><div class="ctr-kpi"><div class="text-muted small">Por vencer (30 días)</div><div class="n text-warning" id="ctr-k-porvencer">—</div></div></div>
        <div class="col-6 col-md-3"><div class="ctr-kpi"><div class="text-muted small">Vencidos</div><div class="n text-danger" id="ctr-k-vencidos">—</div></div></div>
        <div class="col-6 col-md-3"><div class="ctr-kpi"><div class="text-muted small">Plazo indeterminado</div><div class="n" id="ctr-k-indet">—</div></div></div>
      </div>

      <ul class="nav nav-tabs mb-3" id="ctr-tabs">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ctr-pane-vigentes" type="button">Personal contratado</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ctr-pane-historial" type="button">Historial completo</button></li>
      </ul>
      <div class="tab-content">

        <div class="tab-pane fade show active" id="ctr-pane-vigentes">
          <div class="text-muted small mb-2"><i class="bi bi-people"></i> Solo el personal con contrato vigente en este momento (un renglón por persona).</div>
          <div class="row g-2 mb-3">
            <div class="col-md-8"><input class="form-control form-control-sm" id="ctrv-q" placeholder="Buscar por DNI, nombre o puesto…"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-secondary" id="ctrv-limpiar"><i class="bi bi-x-circle me-1"></i>Limpiar</button></div>
          </div>
          <!-- Sin "table-responsive": esa clase de Bootstrap deja que la tabla crezca y aparezca scroll horizontal,
               lo que le gana la jugada al propio ajuste de DataTables (responsive:true, en contratos.js) que
               esconde columnas detras de un boton "+" cuando la pantalla es angosta. -->
          <table class="table table-hover align-middle mb-0 w-100" id="ctr-tb-vigentes">
            <thead><tr><th>Persona</th><th>Tipo</th><th>Puesto</th><th>Inicio</th><th>Fin</th><th>Antigüedad</th><th>Vacaciones</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="tab-pane fade" id="ctr-pane-historial">
          <div class="text-muted small mb-2"><i class="bi bi-clock-history"></i> Todos los contratos de siempre, incluidos los ya renovados o finalizados.</div>
          <div class="row g-2 mb-3">
            <div class="col-md-3"><select class="form-select form-select-sm" id="ctrf-estado">
              <option value="">Todos los estados</option>
              <option value="vigente">Vigente</option>
              <option value="por_vencer">Por vencer (30 días)</option>
              <option value="vencido">Vencido</option>
              <option value="finalizado">Finalizado / renovado</option>
            </select></div>
            <div class="col-md-5"><input class="form-control form-control-sm" id="ctrf-q" placeholder="Buscar por DNI, nombre o puesto…"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-secondary" id="ctrf-limpiar"><i class="bi bi-x-circle me-1"></i>Limpiar</button></div>
          </div>
          <table class="table table-hover align-middle mb-0 w-100" id="ctr-tb">
            <thead><tr><th>Persona</th><th>Tipo</th><th>Puesto</th><th>Inicio</th><th>Fin</th><th>Antigüedad</th><th>Vacaciones</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
<?php endif; ?>
