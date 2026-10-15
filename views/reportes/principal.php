<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
$alc = Reportes::alcance();
if (!$alc):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> No tiene permisos para ver reportes.</div>
<?php else:
    $TABS = [
        'resumen'       => ['bi-speedometer2', 'Resumen'],
        'tickets'       => ['bi-life-preserver', 'Tickets'],
        'mantenimiento' => ['bi-tools', 'Mantenimiento'],
        'inventario'    => ['bi-pc-display', 'Inventario'],
        'software'      => ['bi-app-indicator', 'Software'],
        'asignaciones'  => ['bi-box-seam', 'Asignaciones y actas'],
        'personal'      => ['bi-people', 'Personal'],
        'rrhh'          => ['bi-person-plus', 'RR.HH.'],
    ];
?>
<div id="rp-raiz" data-modulos="<?= htmlspecialchars(implode(',', $alc['modulos'])) ?>" data-solo-area="<?= $alc['area'] ? 1 : 0 ?>">

  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group btn-group-sm" role="group" aria-label="Período rápido" id="rp-rapidos">
          <button type="button" class="btn btn-outline-secondary" data-r="mes">Este mes</button>
          <button type="button" class="btn btn-outline-secondary" data-r="30">30 días</button>
          <button type="button" class="btn btn-outline-secondary" data-r="trim">3 meses</button>
          <button type="button" class="btn btn-outline-secondary" data-r="sem">6 meses</button>
          <button type="button" class="btn btn-outline-secondary" data-r="anio">Este año</button>
        </div>
        <div class="d-flex gap-1 align-items-center">
          <input type="date" id="rp-desde" class="form-control form-control-sm" style="width:auto" aria-label="Desde">
          <span class="small text-muted">a</span>
          <input type="date" id="rp-hasta" class="form-control form-control-sm" style="width:auto" aria-label="Hasta">
          <button id="rp-aplicar" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Aplicar</button>
        </div>
        <div class="ms-auto d-flex gap-2 align-items-center">
          <span class="small text-muted d-none d-md-inline" id="rp-generado"></span>
          <button id="rp-imprimir" class="btn btn-outline-primary btn-sm" title="Informe listo para imprimir o guardar como PDF">
            <i class="bi bi-printer"></i> Informe</button>
          <div class="dropdown" id="rp-dd-descarga">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" type="button">
              <i class="bi bi-download"></i> Descargar</button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item rp-desc" href="#" data-f="excel"><i class="bi bi-file-earmark-excel me-1"></i>Excel (con formato)</a></li>
              <li><a class="dropdown-item rp-desc" href="#" data-f="csv"><i class="bi bi-filetype-csv me-1"></i>CSV (para Excel)</a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="small text-muted mt-1 d-none" id="rp-aviso-area"><i class="bi bi-info-circle"></i> Su permiso cubre los reportes de su área: tickets, personal y RR.HH.</div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3" id="rp-tabs">
    <?php $primero = true; foreach ($TABS as $k => [$ic, $tx]): if (!in_array($k, $alc['modulos'], true)) continue; ?>
      <li class="nav-item"><button class="nav-link<?= $primero ? ' active' : '' ?>" type="button" data-bs-toggle="tab"
        data-bs-target="#rp-p-<?= $k ?>" id="rp-t-<?= $k ?>" data-m="<?= $k ?>"><i class="bi <?= $ic ?>"></i> <?= $tx ?></button></li>
    <?php $primero = false; endforeach; ?>
  </ul>

  <div class="tab-content">
    <?php $primero = true; foreach ($TABS as $k => $t): if (!in_array($k, $alc['modulos'], true)) continue; ?>
      <div class="tab-pane fade<?= $primero ? ' show active' : '' ?>" id="rp-p-<?= $k ?>"><div class="text-muted small p-3">Cargando…</div></div>
    <?php $primero = false; endforeach; ?>
  </div>
</div>
<?php endif; ?>
