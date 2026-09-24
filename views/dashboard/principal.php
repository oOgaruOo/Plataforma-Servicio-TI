<?php
require_once __DIR__ . '/../../core/guard_vista.php';
 $u = Auth::info();
?>
<div class="row g-3">

  <div class="col-12">
    <div class="card-bienvenida">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h4 class="mb-1">Bienvenido, <?= htmlspecialchars($u['nombre']) ?></h4>
          <div class="mb-1">Rol actual:
            <span class="badge bg-light text-primary"><?= htmlspecialchars($u['rol']) ?></span>
          </div>
          <div style="font-size:13.5px;opacity:.85">
            Panel con datos en vivo. El dashboard final con graficos y auto-refresco llega en el Paso 11.
          </div>
        </div>
        <div class="text-end" style="font-size:12px;opacity:.75">
          <div>Actualizado: <span id="dash-actualizado">cargando...</span></div>
          <button id="dash-refrescar" class="btn btn-light btn-sm mt-1" style="opacity:.9">
            <i class="bi bi-arrow-clockwise"></i> Refrescar
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== Contadores reales ===== -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="tarjeta-pendiente" style="color:#1e293b">
      <i class="bi bi-people" style="color:#2563eb"></i>
      <div class="mt-1" style="font-size:26px;font-weight:700" id="d-personal-activo">-</div>
      <h6>Personal activo</h6>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="tarjeta-pendiente" style="color:#1e293b">
      <i class="bi bi-person-plus" style="color:#f59e0b"></i>
      <div class="mt-1" style="font-size:26px;font-weight:700" id="d-personal-pre">-</div>
      <h6>Pre-ingreso</h6>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="tarjeta-pendiente" style="color:#1e293b">
      <i class="bi bi-list-check" style="color:#dc2626"></i>
      <div class="mt-1" style="font-size:26px;font-weight:700" id="d-check-pend">-</div>
      <h6>Checklist pendiente</h6>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="tarjeta-pendiente" style="color:#1e293b">
      <i class="bi bi-pc-display" style="color:#16a34a"></i>
      <div class="mt-1" style="font-size:26px;font-weight:700" id="d-equipos-stock">-</div>
      <h6>Equipos en stock</h6>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="tarjeta-pendiente" style="color:#1e293b">
      <i class="bi bi-box-seam" style="color:#2563eb"></i>
      <div class="mt-1" style="font-size:26px;font-weight:700" id="d-equipos-asig">-</div>
      <h6>Equipos asignados</h6>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="tarjeta-pendiente" style="color:#1e293b">
      <i class="bi bi-tools" style="color:#f59e0b"></i>
      <div class="mt-1" style="font-size:26px;font-weight:700" id="d-equipos-mant">-</div>
      <h6>En mantenimiento</h6>
    </div>
  </div>

  <!-- ===== Ceses proximos ===== -->
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2">
        <i class="bi bi-calendar-x me-1 text-danger"></i>
        <strong>Ceses proximos (30 dias)</strong>
        <span class="badge text-bg-danger ms-1" id="d-ceses-badge">0</span>
      </div>
      <div class="card-body p-2">
        <table class="table table-sm tabla-mini mb-0">
          <thead><tr><th>Persona</th><th>Fecha cese</th><th>Estado</th></tr></thead>
          <tbody id="d-ceses-tabla">
            <tr><td colspan="3" class="text-muted text-center">Sin ceses programados</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ===== Avance del proyecto ===== -->
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2">
        <i class="bi bi-signpost-split me-1 text-primary"></i>
        <strong>Avance del desarrollo</strong>
        <span class="badge text-bg-success ms-1">6 de 12 pasos</span>
      </div>
      <div class="card-body p-2">
        <div class="row g-1" style="font-size:13px">
          <div class="col-md-6"><span class="text-success">1. Base de datos (27 tablas)</span></div>
          <div class="col-md-6"><span class="text-success">2. Nucleo PHP (seguridad)</span></div>
          <div class="col-md-6"><span class="text-success">3. Shell + Login AJAX</span></div>
          <div class="col-md-6"><span class="text-success">4. Catalogos + Usuarios</span></div>
          <div class="col-md-6"><span class="text-success">5. Personal + Checklist</span></div>
          <div class="col-md-6"><span class="text-success">6. Equipos / Inventario</span></div>
          <div class="col-md-6"><span class="text-muted">7. Tickets + SLA (siguiente)</span></div>
          <div class="col-md-6"><span class="text-muted">8. Mantenimiento</span></div>
          <div class="col-md-6"><span class="text-muted">9. Asignaciones + Actas PDF</span></div>
          <div class="col-md-6"><span class="text-muted">10. Ceses + Devoluciones</span></div>
          <div class="col-md-6"><span class="text-muted">11. Dashboard final + Reportes</span></div>
          <div class="col-md-6"><span class="text-muted">12. Notificaciones + Auditoria</span></div>
        </div>
        <div class="progress mt-2" style="height:8px">
          <div class="progress-bar" style="width:50%"></div>
        </div>
        <div class="text-muted mt-1" style="font-size:12px">50% del plan lineal completado</div>
      </div>
    </div>
  </div>

</div>

<script>
(function () {
  function cargarResumen() {
    Api.get('api/dashboard/resumen.php').then(function (d) {
      $('#d-personal-activo').text(d.personal.activo);
      $('#d-personal-pre').text(d.personal.pre_ingreso);
      $('#d-check-pend').text(d.check_pend);
      $('#d-equipos-stock').text(d.equipos.stock);
      $('#d-equipos-asig').text(d.equipos.asignados);
      $('#d-equipos-mant').text(d.equipos.mantenimiento);
      $('#d-ceses-badge').text(d.ceses_prox);

      if (d.ceses && d.ceses.length) {
        var filas = '';
        d.ceses.forEach(function (c) {
          filas += '<tr><td>' + esc(c.nombre) + '</td>'
                 + '<td class="nowrap"><b>' + esc(c.fecha_cese) + '</b></td>'
                 + '<td>' + esc(c.estado) + '</td></tr>';
        });
        $('#d-ceses-tabla').html(filas);
      }

      $('#dash-actualizado').text(d.generado);
    });
  }

  $('#dash-refrescar').on('click', cargarResumen);
  cargarResumen();
  setInterval(cargarResumen, 60000);   // auto-refresco cada 60 seg
})();
</script>