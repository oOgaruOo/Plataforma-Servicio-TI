<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeVer   = Auth::can('asignaciones', 'ver');
 $puedeCrear = Auth::can('asignaciones', 'crear');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para ver los proyectos y obras.
  </div>
<?php else: ?>

<div id="py-permisos" class="d-none" data-crear="<?= $puedeCrear ? 1 : 0 ?>"></div>

<div class="row g-2 mb-3" id="py-stats">
  <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="activos">-</div><div class="t">Abiertos</div></div></div>
  <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="en_curso" style="color:#2563eb">-</div><div class="t">En curso</div></div></div>
  <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="vencidos" style="color:#dc2626">-</div><div class="t">Plazo vencido</div></div></div>
  <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="equipos">-</div><div class="t">Equipos enviados</div></div></div>
  <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="devoluciones_vencidas" style="color:#dc2626">-</div><div class="t">Devoluciones vencidas</div></div></div>
  <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="finalizados">-</div><div class="t">Finalizados</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <select id="pyf-tipo" class="form-select form-select-sm" style="width:auto">
        <option value="">Proyectos y obras</option>
        <option value="proyecto">Solo proyectos</option>
        <option value="obra">Solo obras</option>
      </select>
      <select id="pyf-est" class="form-select form-select-sm" style="width:auto">
        <option value="abiertos">Abiertos</option>
        <option value="finalizados">Finalizados</option>
        <option value="">Todos</option>
      </select>
      <div class="form-check ms-1">
        <input class="form-check-input" type="checkbox" id="pyf-problemas">
        <label class="form-check-label small" for="pyf-problemas">Solo con devoluciones vencidas / plazo vencido</label>
      </div>
    </div>
    <?php if ($puedeCrear): ?>
      <button id="btn-nuevo-proy" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo proyecto / obra</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="alert alert-light border py-2 small">
      <i class="bi bi-info-circle text-primary"></i>
      Registre aquí cada <strong>proyecto</strong> u <strong>obra</strong> con su <strong>plazo</strong>. Los equipos que se envíen
      (desde <em>Asignaciones</em>) quedan amarrados a él con su <strong>fecha de envío</strong> y una
      <strong>devolución estipulada</strong> dentro de ese plazo; el sistema avisa cuando se acerca o vence.
    </div>
    <div class="table-responsive">
      <table id="tb-proy" class="table table-hover table-sm w-100">
        <thead>
          <tr>
            <th>Código</th><th>Proyecto / Obra</th><th>Responsable</th><th>Plazo</th>
            <th>Situación</th><th>Equipos</th><th>Próx. devolución</th><th class="text-center">&nbsp;</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<?php endif; ?>
