<?php
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeVer    = Auth::can('equipos', 'ver');
 $puedeEditar = Auth::can('equipos', 'editar');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para ver el seguimiento.
  </div>
<?php else: ?>

<div class="row g-2 mb-3" id="seg-stats">
  <div class="col-6 col-md-3"><div class="mini-stat"><div class="n" data-k="total">-</div><div class="t">Laptops + PCs</div></div></div>
  <div class="col-6 col-md-3"><div class="mini-stat"><div class="n" data-k="verde" style="color:#16a34a">-</div><div class="t">Verificado &lt;30d</div></div></div>
  <div class="col-6 col-md-3"><div class="mini-stat"><div class="n" data-k="ambar" style="color:#d97706">-</div><div class="t">30-90 dias</div></div></div>
  <div class="col-6 col-md-3"><div class="mini-stat"><div class="n" data-k="rojo" style="color:#dc2626">-</div><div class="t">Sin verificar +90d</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-geo-alt me-1"></i>Seguimiento de ubicacion — equipos de computo</span>
    <div class="d-flex gap-2">
      <select id="sg-filtro" class="form-select form-select-sm" style="width:auto">
        <option value="todos">Ver todos</option>
        <option value="vencidos30">Sin verificar +30 dias</option>
        <option value="vencidos90">CRITICO: +90 dias</option>
      </select>
      <button id="btn-seg-gestion-ubica" class="btn btn-outline-secondary btn-sm" title="Catalogo de ubicaciones">
        <i class="bi bi-geo"></i> Ubicaciones
      </button>
    </div>
  </div>
  <div class="card-body">

    <table id="tb-seg" class="table table-bordered table-hover align-middle w-100">
      <thead>
        <tr>
          <th>Equipo</th><th>Ubicacion actual</th><th>Con quien</th>
          <th>Estado equipo</th><th>Ult. verificacion</th><th class="text-center">Acciones</th>
        </tr>
      </thead>
    </table>

    <div class="text-muted small mt-2">
      <i class="bi bi-info-circle"></i>
      <b>Verificar</b>: confirma donde esta el equipo (1 clic). <b>Traslado</b>: cambia de ubicacion dejando bitacora.
      <b>Historial</b>: todos los movimientos. Use el filtro para el barrido periodico.
    </div>
  </div>
</div>
<?php endif; ?>