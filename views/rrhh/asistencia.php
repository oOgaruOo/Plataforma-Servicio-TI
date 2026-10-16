<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
$puedeVer = Auth::can('rrhh', 'ver');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> No tiene permisos para ver RR.HH.</div>
<?php else:
    $puedeGestionar = Auth::can('rrhh', 'gestionar');
    $areas = Database::get("SELECT id, nombre FROM areas WHERE estado = 'activo' ORDER BY nombre");
?>
<style>
  .asi-kpi{border:1px solid #e5e7eb;border-radius:.6rem;padding:.5rem .7rem;background:#fff;text-align:center}
  .asi-kpi .n{font-size:1.3rem;font-weight:700;line-height:1.1}
  #asi-tb td select{min-width:150px}
</style>
<div id="asi-raiz" data-gestionar="<?= $puedeGestionar ? 1 : 0 ?>">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div class="text-muted small"><i class="bi bi-info-circle"></i> Marcado diario de asistencia (entrada, salida a almorzar, regreso de almuerzo y salida). Se puede marcar a mano aquí, o importar las marcas de un reloj/marcador de huella desde un Excel o CSV. Si alguien tiene vacaciones (o un permiso/día libre/compensación) aprobado ese día, aparece solo.
          Horario: lunes a viernes de 8:00 a.m. a 5:00 p.m. (almuerzo 1:00-2:00 p.m.); sábados de 8:00 a.m. a 1:00 p.m. Tolerancia de ingreso: 15 minutos (hasta las 8:15 cuenta como Normal); el sistema marca Normal/Tardanza solo según la hora de entrada.</div>
        <?php if ($puedeGestionar): ?><button class="btn btn-outline-primary btn-sm text-nowrap" id="asi-btn-importar"><i class="bi bi-upload me-1"></i>Importar Excel/CSV</button><?php endif; ?>
      </div>

      <ul class="nav nav-tabs mb-3" id="asi-tabs">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#asi-pane-dia" type="button">Marcado diario</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#asi-pane-historial" type="button">Historial</button></li>
      </ul>
      <div class="tab-content">

        <div class="tab-pane fade show active" id="asi-pane-dia">
          <div class="row g-2 mb-3 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Fecha</label><input type="date" class="form-control form-control-sm" id="asi-fecha"></div>
            <div class="col-md-3"><label class="form-label small mb-1">Área</label><select class="form-select form-select-sm" id="asi-area">
              <option value="">Todas las áreas</option>
              <?php foreach ($areas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="col-md-6 d-flex gap-2 flex-wrap">
              <div class="asi-kpi flex-fill"><div class="text-muted small">Normal</div><div class="n text-success" id="asi-k-normal">—</div></div>
              <div class="asi-kpi flex-fill"><div class="text-muted small">Tardanzas</div><div class="n text-warning" id="asi-k-tardanza">—</div></div>
              <div class="asi-kpi flex-fill"><div class="text-muted small">Faltas</div><div class="n text-danger" id="asi-k-falta">—</div></div>
              <div class="asi-kpi flex-fill"><div class="text-muted small">Vacaciones</div><div class="n text-primary" id="asi-k-vacaciones">—</div></div>
              <div class="asi-kpi flex-fill"><div class="text-muted small">Sin marcar</div><div class="n text-muted" id="asi-k-sin">—</div></div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="asi-tb">
              <thead><tr><th>Persona</th><th>Área</th><th>Marca</th><th>Entrada</th><th>Salida almuerzo</th><th>Regreso almuerzo</th><th>Salida</th><th>Observación</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
          <?php if ($puedeGestionar): ?>
          <div class="text-end mt-3"><button class="btn btn-primary" id="asi-btn-guardar"><i class="bi bi-save me-1"></i>Guardar marcado del día</button></div>
          <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="asi-pane-historial">
          <div class="text-muted small mb-2"><i class="bi bi-clock-history"></i> Entrada, salida y tipo de marca de una persona (o de todas) a lo largo de un rango de fechas.</div>
          <div class="row g-2 mb-3 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Persona</label><select class="form-select form-select-sm" id="ahf-persona"><option value="">Todas</option></select></div>
            <div class="col-md-2"><label class="form-label small mb-1">Área</label><select class="form-select form-select-sm" id="ahf-area">
              <option value="">Todas</option>
              <?php foreach ($areas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="col-md-2"><label class="form-label small mb-1">Desde</label><input type="date" class="form-control form-control-sm" id="ahf-desde"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Hasta</label><input type="date" class="form-control form-control-sm" id="ahf-hasta"></div>
            <div class="col-md-3 d-grid"><button class="btn btn-sm btn-primary" id="ahf-buscar"><i class="bi bi-search me-1"></i>Buscar</button></div>
          </div>
          <!-- Sin "table-responsive": ver nota igual en Contratos/Vacaciones (esta tabla usa DataTables Responsive). -->
          <table class="table table-hover table-sm align-middle mb-0 w-100" id="asi-hist-tb">
            <thead><tr><th>Persona</th><th>Área</th><th>Fecha</th><th>Tipo</th><th>Entrada</th><th>Salida almuerzo</th><th>Regreso almuerzo</th><th>Salida</th><th>Observación</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
<?php endif; ?>
