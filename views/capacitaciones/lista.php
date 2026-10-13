<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

Capacitaciones::asegurar();
$alc = Capacitaciones::alcance();
if (!$alc):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> No tiene permisos para usar Capacitaciones.</div>
<?php else:
    $puedeSolicitar = Auth::can('capacitaciones', 'solicitar');
    $puedeGestionar = Auth::can('capacitaciones', 'gestionar');
    $veTodas = $alc['modo'] !== 'propias';
    $pestanaInicial = $veTodas ? 'todas' : 'mias';
    if (!$puedeSolicitar && !$veTodas) $pestanaInicial = 'agenda';
?>
<style>
  .cap-kpi{border:1px solid #e5e7eb;border-radius:.6rem;padding:.6rem .8rem;background:#fff;height:100%}
  .cap-kpi .n{font-size:1.5rem;font-weight:700;line-height:1.1}
  .cap-kpi.clic{cursor:pointer}.cap-kpi.clic:hover{border-color:#93c5fd;background:#f8fafc}
  .cap-card{border:1px solid #e5e7eb;border-radius:.7rem;background:#fff;padding:.9rem 1rem;margin-bottom:.7rem}
  .cap-card.por-cerrar{border-color:#fbbf24;background:#fffbeb}
  .cap-paso{display:flex;align-items:center;gap:.25rem;flex-wrap:wrap;margin:.55rem 0 .2rem}
  .cap-paso .p{display:flex;align-items:center;gap:.3rem;font-size:.78rem;color:#9ca3af}
  .cap-paso .p .c{width:1.15rem;height:1.15rem;border-radius:50%;background:#e5e7eb;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem}
  .cap-paso .p.hecho{color:#16a34a}.cap-paso .p.hecho .c{background:#16a34a}
  .cap-paso .p.actual{color:#1d4ed8;font-weight:600}.cap-paso .p.actual .c{background:#2563eb;box-shadow:0 0 0 3px #dbeafe}
  .cap-paso .p.fin-mal{color:#b91c1c;font-weight:600}.cap-paso .p.fin-mal .c{background:#dc2626}
  .cap-paso .sep{width:1.2rem;height:2px;background:#e5e7eb}.cap-paso .sep.hecho{background:#16a34a}
  .cap-ayuda{font-size:.82rem;color:#4b5563}
  .cap-linea{border-left:2px solid #e5e7eb;margin-left:.45rem;padding-left:.9rem}
  .cap-linea .it{position:relative;padding-bottom:.7rem}
  .cap-linea .it::before{content:"";position:absolute;left:-1.18rem;top:.3rem;width:.55rem;height:.55rem;border-radius:50%;background:#2563eb}
  .cap-estrellas i{font-size:1.5rem;color:#d1d5db;cursor:pointer}.cap-estrellas i.on{color:#f59e0b}
  .cap-dia{font-weight:600;margin:.8rem 0 .3rem;color:#374151}
  .cap-ses{display:flex;gap:.8rem;align-items:flex-start;border:1px solid #e5e7eb;border-radius:.6rem;padding:.55rem .75rem;margin-bottom:.4rem;background:#fff}
  .cap-ses .h{min-width:3.4rem;font-weight:700;color:#1d4ed8}
  #cap-vista .nav-link{white-space:nowrap}
</style>
<div id="cap-raiz" data-solicitar="<?= $puedeSolicitar ? 1 : 0 ?>" data-gestionar="<?= $puedeGestionar ? 1 : 0 ?>" data-todas="<?= $veTodas ? 1 : 0 ?>" data-inicial="<?= $pestanaInicial ?>">
  <div class="card shadow-sm" id="cap-vista">
    <div class="card-header">
      <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto" id="cap-tabs" role="tablist">
        <?php if ($puedeSolicitar): ?>
        <li class="nav-item"><button class="nav-link" id="cap-t-mias" data-bs-toggle="tab" data-bs-target="#cap-p-mias" type="button" role="tab" data-vista="mias"><i class="bi bi-person-check me-1"></i>Mis solicitudes <span class="badge text-bg-primary d-none" id="cap-b-mias"></span></button></li>
        <?php endif; ?>
        <?php if ($veTodas): ?>
        <li class="nav-item"><button class="nav-link" id="cap-t-todas" data-bs-toggle="tab" data-bs-target="#cap-p-todas" type="button" role="tab" data-vista="todas"><i class="bi bi-inboxes me-1"></i><?= $puedeGestionar ? 'Solicitudes' : ($alc['modo'] === 'area' ? 'De mi área' : 'Solicitudes') ?> <span class="badge text-bg-danger d-none" id="cap-b-todas"></span></button></li>
        <?php endif; ?>
        <li class="nav-item"><button class="nav-link" id="cap-t-agenda" data-bs-toggle="tab" data-bs-target="#cap-p-agenda" type="button" role="tab" data-vista="agenda"><i class="bi bi-calendar-event me-1"></i>Agenda</button></li>
        <?php if ($puedeGestionar): ?>
        <li class="nav-item"><button class="nav-link" id="cap-t-temas" data-bs-toggle="tab" data-bs-target="#cap-p-temas" type="button" role="tab" data-vista="temas"><i class="bi bi-collection me-1"></i>Temas</button></li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="card-body tab-content">

      <?php if ($puedeSolicitar): ?>
      <!-- ================= MIS SOLICITUDES ================= -->
      <div class="tab-pane fade" id="cap-p-mias" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h5 class="mb-0">¿Necesita una capacitación?</h5>
            <div class="text-muted small">Pídala a Sistemas desde cualquier área y siga aquí en qué va.</div>
          </div>
          <button class="btn btn-primary" id="cap-btn-nueva"><i class="bi bi-plus-lg me-1"></i>Solicitar capacitación</button>
        </div>
        <div class="row g-2 mb-3" id="cap-kpi-mias"></div>
        <div class="btn-group btn-group-sm mb-3" id="cap-filtro-mias" role="group">
          <button type="button" class="btn btn-outline-primary active" data-g="curso">En curso</button>
          <button type="button" class="btn btn-outline-primary" data-g="cerradas">Finalizadas</button>
          <button type="button" class="btn btn-outline-primary" data-g="">Todas</button>
        </div>
        <div id="cap-lista-mias"></div>
      </div>
      <?php endif; ?>

      <?php if ($veTodas): ?>
      <!-- ================= SOLICITUDES (todas / de mi area) ================= -->
      <div class="tab-pane fade" id="cap-p-todas" role="tabpanel">
        <div class="row g-2 mb-3" id="cap-kpi-todas"></div>
        <div class="row g-2 mb-3">
          <div class="col-md-3"><select class="form-select form-select-sm" id="capf-estado">
            <option value="">Todos los estados</option>
            <?php foreach (Capacitaciones::ESTADOS as $k => $e): ?><option value="<?= $k ?>"><?= htmlspecialchars($e[0]) ?></option><?php endforeach; ?>
          </select></div>
          <div class="col-md-3"><select class="form-select form-select-sm" id="capf-area"><option value="">Todas las áreas</option></select></div>
          <div class="col-md-4"><input class="form-control form-control-sm" id="capf-q" placeholder="Buscar por código, tema o persona…"></div>
          <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-secondary" id="capf-limpiar"><i class="bi bi-x-circle me-1"></i>Limpiar</button></div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="cap-tb">
            <thead><tr><th>Código</th><th>Tema</th><th>Solicitante</th><th>Área</th><th class="text-center">Pers.</th><th>Deseada / programada</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- ================= AGENDA ================= -->
      <div class="tab-pane fade" id="cap-p-agenda" role="tabpanel">
        <div class="text-muted small mb-2">Capacitaciones programadas<?= $veTodas ? '' : ' para usted' ?>, de la más próxima a la más lejana.</div>
        <div id="cap-agenda"></div>
      </div>

      <?php if ($puedeGestionar): ?>
      <!-- ================= TEMAS ================= -->
      <div class="tab-pane fade" id="cap-p-temas" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-muted small">Los temas que se ofrecen al solicitar. Quien pida puede además escribir otro tema.</div>
          <button class="btn btn-sm btn-primary" id="cap-btn-tema"><i class="bi bi-plus-lg me-1"></i>Nuevo tema</button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="cap-tb-temas">
            <thead><tr><th>Tema</th><th>Descripción</th><th class="text-center">Horas</th><th class="text-center">Solicitudes</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php endif; ?>
