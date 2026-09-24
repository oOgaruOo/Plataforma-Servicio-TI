<?php
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeCrear  = Auth::can('mantenimiento', 'crear');
 $puedeVer    = Auth::can('mantenimiento', 'ver');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para ver los mantenimientos.
  </div>
<?php else: ?>

<div class="row g-2 mb-3" id="mt-stats">
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="activos">-</div><div class="t">Activos</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="con_proveedor">-</div><div class="t">Con proveedor</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="atrasados" style="color:#dc2626">-</div><div class="t">Atrasados</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="listos" style="color:#16a34a">-</div><div class="t">Listos</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="costo_mes">-</div><div class="t">Costo del mes</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="programados_prox" style="color:#d97706">-</div><div class="t">Preventivos <30d</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header p-0">
    <ul class="nav nav-tabs card-header-tabs px-2 pt-2">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-mt" type="button">
          <i class="bi bi-tools"></i> Mantenimientos
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-prev" type="button" id="btn-tab-prev">
          <i class="bi bi-calendar-check"></i> Preventivos programados
        </button>
      </li>
      <?php if ($puedeCrear): ?>
      <li class="nav-item ms-auto pb-1">
        <button id="btn-nuevo-mt" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg"></i> Registrar mantenimiento
        </button>
      </li>
      <?php endif; ?>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">

      <!-- ===== TAB: mantenimientos ===== -->
      <div class="tab-pane fade show active" id="tab-mt">
        <div class="row g-2 mb-3">
          <div class="col-6 col-md-3">
            <select id="mtf-estado" class="form-select form-select-sm">
              <option value="">Todos los estados</option>
              <option value="solicitado">Solicitado</option>
              <option value="en_evaluacion">En evaluación</option>
              <option value="en_proceso">En proceso (taller)</option>
              <option value="enviado_proveedor">Enviado a proveedor</option>
              <option value="cotizado">Cotizado</option>
              <option value="aprobado">Aprobado</option>
              <option value="rechazado">Rechazado</option>
              <option value="en_reparacion">En reparación</option>
              <option value="recibido_reparado">Recibido</option>
              <option value="listo">Listo</option>
              <option value="entregado">Entregado</option>
              <option value="devuelto_stock">Devuelto a stock</option>
              <option value="cerrado">Cerrado</option>
              <option value="cancelado">Cancelado</option>
              <option value="no_reparable">No reparable</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <select id="mtf-tipo" class="form-select form-select-sm">
              <option value="">Todo tipo</option>
              <option value="correctivo">Correctivo</option>
              <option value="preventivo">Preventivo</option>
              <option value="limpieza">Limpieza</option>
              <option value="actualizacion">Actualización</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="mtf-proveedor" class="form-select form-select-sm">
              <option value="">Todos los proveedores</option>
              <?php foreach (Database::get("SELECT id, nombre FROM proveedores WHERE estado='activo' ORDER BY nombre") as $pr): ?>
                <option value="<?= (int)$pr['id'] ?>"><?= htmlspecialchars($pr['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-2 d-flex align-items-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="mtf-atrasados">
              <label class="form-check-label small" for="mtf-atrasados">Solo atrasados</label>
            </div>
          </div>
          <div class="col-6 col-md-2 d-flex gap-1">
            <button id="btn-filtrar-mt" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
            <button id="btn-limpiar-mt" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>

        <table id="tb-mt" class="table table-bordered table-hover align-middle w-100">
          <thead>
            <tr>
              <th>Código</th><th>Equipo</th><th>Tipo</th><th>Estado</th>
              <th>Proveedor</th><th>Técnico</th><th>Retorno est.</th>
              <th>Costo</th><th>Iniciado</th><th>&nbsp;</th>
            </tr>
          </thead>
        </table>
      </div>

      <!-- ===== TAB: preventivos programados (render servidor) ===== -->
      <div class="tab-pane fade" id="tab-prev">
        <div class="d-flex justify-content-end mb-2">
          <?php if ($puedeCrear): ?>
            <button id="btn-nuevo-prog" class="btn btn-primary btn-sm">
              <i class="bi bi-plus-lg"></i> Programar preventivo
            </button>
          <?php endif; ?>
        </div>
        <table class="table table-bordered table-hover align-middle tabla-mini">
          <thead>
            <tr>
              <th>Equipo</th><th>Tipo</th><th>Frecuencia</th><th>Última</th>
              <th>Próxima</th><th>Responsable</th><th>Estado</th><th class="text-center">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $progs = Database::get(
            "SELECT mp.*, e.codigo AS eq, e.marca, e.modelo, t.nombre AS tipo_eq,
                    CONCAT(u.nombre_completo, '') AS resp
             FROM mantenimientos_programados mp
             INNER JOIN equipos e ON e.id = mp.equipo_id
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN usuarios u ON u.id = mp.responsable_id
             ORDER BY mp.activo DESC, mp.proxima_fecha ASC");
          if (!$progs): ?>
            <tr><td colspan="8" class="text-muted text-center">Sin mantenimientos programados aún</td></tr>
          <?php else: foreach ($progs as $p):
            $vencido = $p['activo'] && $p['proxima_fecha'] <= date('Y-m-d');
          ?>
            <tr>
              <td><b><?= htmlspecialchars($p['eq']) ?></b><br>
                  <span class="text-muted small"><?= htmlspecialchars($p['tipo_eq'].' '.$p['marca']) ?></span></td>
              <td><?= htmlspecialchars(ucfirst($p['tipo'])) ?></td>
              <td><?= htmlspecialchars(ucfirst($p['frecuencia'])) ?></td>
              <td><?= htmlspecialchars($p['ultima_ejecucion'] ?: '—') ?></td>
              <td><span class="badge <?= $vencido ? 'text-bg-danger' : ($p['activo'] ? 'text-bg-primary' : 'text-bg-secondary') ?>">
                  <?= htmlspecialchars($p['proxima_fecha']) ?></span></td>
              <td><?= htmlspecialchars($p['resp'] ?: '—') ?></td>
              <td><?= $p['activo'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Pausado</span>' ?></td>
              <td class="text-center text-nowrap">
                <?php if ($puedeCrear && $p['activo']): ?>
                  <button class="btn btn-sm btn-outline-primary btn-ejecutar-prog" data-id="<?= (int)$p['id'] ?>"
                          data-equipo="<?= htmlspecialchars($p['eq']) ?>" title="Ejecutar ahora">
                    <i class="bi bi-play-fill"></i></button>
                <?php endif; ?>
                <?php if ($puedeCrear): ?>
                  <button class="btn btn-sm btn-outline-secondary btn-editar-prog" data-id="<?= (int)$p['id'] ?>" title="Editar">
                    <i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-warning btn-toggle-prog" data-id="<?= (int)$p['id'] ?>"
                          data-activo="<?= (int)$p['activo'] ?>" title="Pausar/activar">
                    <i class="bi bi-pause-fill"></i></button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        <div class="text-muted small">
          <i class="bi bi-info-circle"></i> «Ejecutar» genera el mantenimiento del ciclo y reprograma
          automáticamente la próxima fecha según la frecuencia. Los vencidos aparecen en rojo.
        </div>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>