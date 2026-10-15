<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

Requerimientos::asegurar();
$alc = Requerimientos::alcance();
if (!$alc):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> No tiene permisos para usar Requerimiento de personal.</div>
<?php else:
    $puedeSolicitar = Auth::can('rrhh', 'solicitar');
    $puedeGestionar = Auth::can('rrhh', 'gestionar');
    $puedeEliminar = Auth::can('rrhh', 'eliminar');
    $veTodas = $alc['modo'] !== 'propias';
    $pestanaInicial = $puedeSolicitar ? 'mias' : ($veTodas ? 'todas' : 'epp');
?>
<style>
  .req-kpi{border:1px solid #e5e7eb;border-radius:.6rem;padding:.6rem .8rem;background:#fff;height:100%}
  .req-kpi .n{font-size:1.5rem;font-weight:700;line-height:1.1}
  .req-kpi.clic{cursor:pointer}.req-kpi.clic:hover{border-color:#93c5fd;background:#f8fafc}
  .req-card{border:1px solid #e5e7eb;border-radius:.7rem;background:#fff;padding:.9rem 1rem;margin-bottom:.7rem}
  .req-card.atrasado{border-color:#fca5a5;background:#fef2f2}
  .req-ayuda{font-size:.82rem;color:#4b5563}
  .req-linea{border-left:2px solid #e5e7eb;margin-left:.45rem;padding-left:.9rem}
  .req-linea .it{position:relative;padding-bottom:.7rem}
  .req-linea .it::before{content:"";position:absolute;left:-1.18rem;top:.3rem;width:.55rem;height:.55rem;border-radius:50%;background:#2563eb}
  .req-lock{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:.6rem;padding:.7rem .9rem;font-size:.85rem;color:#475569}
  #req-vista .nav-link{white-space:nowrap}
</style>
<div id="req-raiz" data-solicitar="<?= $puedeSolicitar ? 1 : 0 ?>" data-gestionar="<?= $puedeGestionar ? 1 : 0 ?>" data-eliminar="<?= $puedeEliminar ? 1 : 0 ?>" data-todas="<?= $veTodas ? 1 : 0 ?>" data-inicial="<?= $pestanaInicial ?>">
  <div class="card shadow-sm" id="req-vista">
    <div class="card-header">
      <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto" id="req-tabs" role="tablist">
        <?php if ($puedeSolicitar): ?>
        <li class="nav-item"><button class="nav-link" id="req-t-mias" data-bs-toggle="tab" data-bs-target="#req-p-mias" type="button" role="tab" data-vista="mias"><i class="bi bi-person-check me-1"></i>Mis requerimientos <span class="badge text-bg-primary d-none" id="req-b-mias"></span></button></li>
        <?php endif; ?>
        <?php if ($veTodas): ?>
        <li class="nav-item"><button class="nav-link" id="req-t-todas" data-bs-toggle="tab" data-bs-target="#req-p-todas" type="button" role="tab" data-vista="todas"><i class="bi bi-inboxes me-1"></i><?= $puedeGestionar ? 'Requerimientos' : ($alc['modo'] === 'area' ? 'De mi área' : 'Requerimientos') ?> <span class="badge text-bg-danger d-none" id="req-b-todas"></span></button></li>
        <?php endif; ?>
        <li class="nav-item"><button class="nav-link" id="req-t-puestos" data-bs-toggle="tab" data-bs-target="#req-p-puestos" type="button" role="tab" data-vista="puestos"><i class="bi bi-person-vcard me-1"></i>Puestos y requisitos</button></li>
        <?php if ($puedeGestionar): ?>
        <li class="nav-item"><button class="nav-link" id="req-t-equipos" data-bs-toggle="tab" data-bs-target="#req-p-equipos" type="button" role="tab" data-vista="equipos"><i class="bi bi-laptop me-1"></i>Equipos de TI</button></li>
        <li class="nav-item"><button class="nav-link" id="req-t-epp" data-bs-toggle="tab" data-bs-target="#req-p-epp" type="button" role="tab" data-vista="epp"><i class="bi bi-shield-check me-1"></i>Catálogo EPP</button></li>
        <li class="nav-item"><button class="nav-link" id="req-t-requisitos" data-bs-toggle="tab" data-bs-target="#req-p-requisitos" type="button" role="tab" data-vista="requisitos"><i class="bi bi-card-checklist me-1"></i>Biblioteca de requisitos</button></li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="card-body tab-content">

      <?php if ($puedeSolicitar): ?>
      <!-- ================= MIS REQUERIMIENTOS ================= -->
      <div class="tab-pane fade" id="req-p-mias" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h5 class="mb-0">¿Necesita personal para su área?</h5>
            <div class="text-muted small">Pida residente, analista, practicante u otro puesto a Sistemas / RR.HH. y siga aquí en qué va.</div>
          </div>
          <button class="btn btn-primary" id="req-btn-nuevo"><i class="bi bi-plus-lg me-1"></i>Solicitar personal</button>
        </div>
        <div class="row g-2 mb-3" id="req-kpi-mias"></div>
        <div class="btn-group btn-group-sm mb-3" id="req-filtro-mias" role="group">
          <button type="button" class="btn btn-outline-primary active" data-g="curso">En curso</button>
          <button type="button" class="btn btn-outline-primary" data-g="cubierto">Cubiertos</button>
          <button type="button" class="btn btn-outline-primary" data-g="">Todos</button>
        </div>
        <div id="req-lista-mias"></div>
      </div>
      <?php endif; ?>

      <?php if ($veTodas): ?>
      <!-- ================= REQUERIMIENTOS (todos / de mi area) ================= -->
      <div class="tab-pane fade" id="req-p-todas" role="tabpanel">
        <div class="row g-2 mb-3" id="req-kpi-todas"></div>
        <div class="row g-2 mb-3">
          <div class="col-md-3"><select class="form-select form-select-sm" id="reqf-estado">
            <option value="">Todos los estados</option>
            <?php foreach (Requerimientos::ESTADOS as $k => $e): ?><option value="<?= $k ?>"><?= htmlspecialchars($e[0]) ?></option><?php endforeach; ?>
          </select></div>
          <div class="col-md-3"><select class="form-select form-select-sm" id="reqf-area"><option value="">Todas las áreas</option></select></div>
          <div class="col-md-4"><input class="form-control form-control-sm" id="reqf-q" placeholder="Buscar por código, puesto o persona…"></div>
          <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-secondary" id="reqf-limpiar"><i class="bi bi-x-circle me-1"></i>Limpiar</button></div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="req-tb">
            <thead><tr><th>Código</th><th>Puesto</th><th>Solicitante</th><th>Área</th><th class="text-center">Cant.</th><th>Plazo</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- ================= CATALOGO DE PUESTOS Y REQUISITOS ================= -->
      <div class="tab-pane fade" id="req-p-puestos" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div class="text-muted small">
            <?= $puedeGestionar
                ? 'Los puestos y sus requisitos (educación, formación, experiencia, etc.) que se muestran al solicitar personal. Se agregan, editan y eliminan libremente.'
                : 'Puestos ya definidos en el catálogo (con sus requisitos) y los que usted mismo propuso. Si el puesto que necesita no está, escríbalo tal cual al solicitar personal: quedará propuesto automáticamente.' ?>
          </div>
          <?php if ($puedeSolicitar): ?><button class="btn btn-sm btn-outline-primary" id="req-btn-proponer"><i class="bi bi-plus-lg me-1"></i>Proponer puesto</button><?php endif; ?>
          <?php if ($puedeGestionar): ?><button class="btn btn-sm btn-primary" id="req-btn-puesto"><i class="bi bi-plus-lg me-1"></i>Nuevo puesto</button><?php endif; ?>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="req-tb-puestos">
            <thead><tr><th>Puesto</th><th>Contrato sugerido</th><th>Salario referencial</th><th class="text-center">Requisitos</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <?php if ($puedeGestionar): ?>
      <!-- ================= CATALOGO DE EQUIPOS DE TI ================= -->
      <div class="tab-pane fade" id="req-p-equipos" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-muted small">Los equipos / dispositivos de TI que se ofrecen al solicitar personal.</div>
          <button class="btn btn-sm btn-primary" id="req-btn-equipo"><i class="bi bi-plus-lg me-1"></i>Nuevo equipo</button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="req-tb-equipos">
            <thead><tr><th>Nombre</th><th class="text-center">Orden</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <!-- ================= CATALOGO EPP ================= -->
      <div class="tab-pane fade" id="req-p-epp" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-muted small">Los EPP que se ofrecen al solicitar personal. Se usan tal como estén al momento de pedir.</div>
          <button class="btn btn-sm btn-primary" id="req-btn-epp"><i class="bi bi-plus-lg me-1"></i>Nuevo EPP</button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="req-tb-epp">
            <thead><tr><th>Nombre</th><th class="text-center">Orden</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <!-- ================= BIBLIOTECA DE REQUISITOS ================= -->
      <div class="tab-pane fade" id="req-p-requisitos" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-muted small">Frases de requisito (educación, formación, experiencia, etc.) reutilizables entre varios puestos: se eligen o se escriben nuevas al definir los requisitos de un puesto, y la nueva se suma aquí sola.</div>
          <button class="btn btn-sm btn-primary" id="req-btn-requisito"><i class="bi bi-plus-lg me-1"></i>Nuevo requisito</button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="req-tb-requisitos">
            <thead><tr><th>Categoría</th><th>Detalle</th><th class="text-center">Orden</th><th>Estado</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php endif; ?>
