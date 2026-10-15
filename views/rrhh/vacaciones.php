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
<style>.vac-kpi{border:1px solid #e5e7eb;border-radius:.6rem;padding:.6rem .8rem;background:#fff;height:100%}.vac-kpi .n{font-size:1.5rem;font-weight:700;line-height:1.1}</style>
<div id="vac-raiz" data-gestionar="<?= $puedeGestionar ? 1 : 0 ?>">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="text-muted small"><i class="bi bi-info-circle"></i> Vacaciones del personal. Saldo estimado: 2.5 días ganados por mes trabajado desde la fecha de ingreso (no acumulan los contratos de Practicante ni Locación de servicios).</div>
        <?php if ($puedeGestionar): ?><button class="btn btn-primary btn-sm" id="vac-btn-nuevo"><i class="bi bi-plus-lg me-1"></i>Nueva solicitud</button><?php endif; ?>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="vac-kpi"><div class="text-muted small">Pendientes</div><div class="n text-warning" id="vac-k-pend">—</div></div></div>
        <div class="col-6 col-md-3"><div class="vac-kpi"><div class="text-muted small">Próximas (7 días)</div><div class="n text-primary" id="vac-k-prox">—</div></div></div>
        <div class="col-6 col-md-3"><div class="vac-kpi"><div class="text-muted small">Ya pueden salir</div><div class="n text-info" id="vac-k-sugeridos">—</div></div></div>
      </div>
      <div id="vac-sugeridos-wrap" class="mb-3 d-none">
        <div class="alert alert-info py-2 mb-0">
          <div class="small mb-2"><i class="bi bi-stars me-1"></i><b>El sistema recalculó y sugiere</b> que este personal ya puede salir de vacaciones (tiene <?= Vacaciones::UMBRAL_SUGERENCIA ?> días o más disponibles):</div>
          <div id="vac-sugeridos-lista" class="d-flex flex-wrap gap-2"></div>
        </div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-md-3"><select class="form-select form-select-sm" id="vacf-estado">
          <option value="">Todos los estados</option>
          <option value="pendiente">Pendiente</option><option value="aprobada">Aprobada</option>
          <option value="rechazada">Rechazada</option><option value="cancelada">Cancelada</option>
        </select></div>
        <div class="col-md-5"><input class="form-control form-control-sm" id="vacf-q" placeholder="Buscar por DNI o nombre…"></div>
        <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-secondary" id="vacf-limpiar"><i class="bi bi-x-circle me-1"></i>Limpiar</button></div>
      </div>
      <!-- Sin "table-responsive": esa clase de Bootstrap deja que la tabla crezca y aparezca scroll horizontal,
           lo que le gana la jugada al propio ajuste de DataTables (responsive:true, en vacaciones.js) que
           esconde columnas detras de un boton "+" cuando la pantalla es angosta. -->
      <table class="table table-hover align-middle mb-0 w-100" id="vac-tb">
        <thead><tr><th>Persona</th><th>Del</th><th>Al</th><th class="text-center">Días</th><th>Estado</th><th></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
