<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeVer     = Auth::can('asignaciones', 'ver');
 $puedeCrear   = Auth::can('asignaciones', 'crear');
 $puedeDevolver= Auth::can('asignaciones', 'devolver');
 $puedeSolicitar=Auth::can('asignaciones', 'solicitar');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para ver las asignaciones.
  </div>
<?php else: ?>

<div id="asig-permisos" class="d-none"
     data-crear="<?= $puedeCrear ? 1 : 0 ?>" data-devolver="<?= $puedeDevolver ? 1 : 0 ?>"
     data-solicitar="<?= $puedeSolicitar ? 1 : 0 ?>"></div>

<div class="row g-2 mb-3" id="asig-stats">
  <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="activos">-</div><div class="t">Asignados</div></div></div>
  <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="prestamos">-</div><div class="t">Préstamos</div></div></div>
  <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="prestamos_vencidos" style="color:#dc2626">-</div><div class="t">Prést. vencidos</div></div></div>
  <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="en_proyectos">-</div><div class="t">En proyectos</div></div></div>
  <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="en_obras">-</div><div class="t">En obras</div></div></div>
  <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="solicitudes_pendientes" style="color:#d97706">-</div><div class="t">Solicitudes pend.</div></div></div>
  <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="devueltos_mes">-</div><div class="t">Devueltos mes</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header p-0">
    <ul class="nav nav-tabs card-header-tabs px-2 pt-2">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-asig" type="button">
          <i class="bi bi-box-seam"></i> Asignaciones
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sol" type="button">
          <i class="bi bi-inbox"></i> Solicitudes <span class="badge text-bg-warning text-dark" id="badge-sol-pend">0</span>
        </button>
      </li>
      <li class="nav-item ms-auto pb-1 d-flex gap-2">
        <?php if ($puedeSolicitar): ?>
          <button id="btn-nueva-solicitud" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-inbox"></i> Solicitar equipo
          </button>
        <?php endif; ?>
        <?php if ($puedeCrear): ?>
          <button id="btn-nueva-asignacion" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Registrar entrega
          </button>
        <?php endif; ?>
      </li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">

      <!-- ===== TAB asignaciones ===== -->
      <div class="tab-pane fade show active" id="tab-asig">
        <div class="row g-2 mb-3">
          <div class="col-6 col-md-3">
            <select id="asf-estado" class="form-select form-select-sm">
              <option value="">Todos los estados</option>
              <option value="activa">Activas</option>
              <option value="vencida">Vencidas</option>
              <option value="devuelta">Devueltas</option>
              <option value="cancelada">Canceladas</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="asf-tipo" class="form-select form-select-sm">
              <option value="">Permanentes y préstamos</option>
              <option value="permanente">Solo permanentes</option>
              <option value="prestamo">Solo préstamos</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="asf-destino" class="form-select form-select-sm">
              <option value="">Todos los destinos</option>
              <option value="persona">A personas</option>
              <option value="area">A áreas</option>
              <option value="proyecto">A proyectos</option>
              <option value="obra">A obras</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="asf-proyecto" class="form-select form-select-sm" title="Equipos de un proyecto u obra concretos">
              <option value="">Cualquier proyecto / obra</option>
            </select>
          </div>
          <div class="col-6 col-md-3 d-flex gap-1">
            <button id="btn-filtrar-asig" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
            <button id="btn-limpiar-asig" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>

        <table id="tb-asig" class="table table-bordered table-hover align-middle w-100">
          <thead>
            <tr>
              <th>Equipo</th><th>Tipo</th><th>Asignado a</th><th>Responsable / Área</th>
              <th>Modalidad</th><th>Entregado</th><th>Retorno esperado</th>
              <th>Estado</th><th class="text-center">&nbsp;</th>
            </tr>
          </thead>
        </table>
      </div>

      <!-- ===== TAB solicitudes (render servidor) ===== -->
      <div class="tab-pane fade" id="tab-sol">
        <table class="table table-bordered table-hover align-middle tabla-mini">
          <thead>
            <tr>
              <th>Código</th><th>Solicitante</th><th>Área</th><th>Equipo pedido</th>
              <th>Para</th><th>Cant.</th><th>Justificación</th><th>Estado</th><th class="text-center">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
          <?php
          Destinos::asegurar();
          $sols = Database::get(
            "SELECT s.*, CONCAT(p.nombres,' ',p.apellidos) AS solicitante,
                    t.nombre AS tipo_equipo, ar.nombre AS area,
                    py.nombre AS proyecto, py.tipo AS proyecto_tipo, py.codigo AS proyecto_codigo
             FROM solicitudes_equipo s
             INNER JOIN personal p ON p.id = s.solicitante_personal_id
             INNER JOIN areas ar ON ar.id = s.area_id
             INNER JOIN tipo_equipos t ON t.id = s.tipo_equipo_id
             LEFT JOIN proyectos py ON py.id = s.proyecto_id
             ORDER BY FIELD(s.estado,'pendiente','aprobada','atendida','rechazada','cancelada'),
                      s.fecha_solicitud DESC");
          if (!$sols): ?>
            <tr><td colspan="9" class="text-muted text-center">Sin solicitudes registradas</td></tr>
          <?php else: foreach ($sols as $s):
            $cls = ['pendiente'=>'text-bg-warning text-dark','aprobada'=>'text-bg-success',
                    'atendida'=>'text-bg-primary','rechazada'=>'text-bg-danger','cancelada'=>'text-bg-secondary'][$s['estado']] ?? 'text-bg-light';
            $puedeAprobar = Auth::can('asignaciones','aprobar');
          ?>
            <tr>
              <td><b><?= htmlspecialchars($s['codigo']) ?></b></td>
              <td><?= htmlspecialchars($s['solicitante']) ?></td>
              <td><?= htmlspecialchars($s['area']) ?></td>
              <td><?= htmlspecialchars($s['tipo_equipo']) ?></td>
              <td class="small">
                <?php if ($s['proyecto']): ?>
                  <span class="badge <?= $s['proyecto_tipo'] === 'obra' ? 'text-bg-warning text-dark' : 'text-bg-info' ?>"><?= $s['proyecto_tipo'] === 'obra' ? 'Obra' : 'Proyecto' ?></span>
                  <?= htmlspecialchars($s['proyecto']) ?>
                <?php else: ?><span class="text-muted">Uso propio</span><?php endif; ?>
              </td>
              <td class="text-center"><?= (int)$s['entregados'] ?>/<?= (int)$s['cantidad'] ?></td>
              <td class="small"><?= htmlspecialchars($s['justificacion']) ?></td>
              <td><span class="badge <?= $cls ?> ticket-estado"><?= htmlspecialchars($s['estado']) ?></span></td>
              <td class="text-center text-nowrap">
                <?php if ($s['estado'] === 'pendiente' && $puedeAprobar): ?>
                  <button class="btn btn-sm btn-outline-success btn-aprobar-sol" data-id="<?= (int)$s['id'] ?>"
                          data-codigo="<?= htmlspecialchars($s['codigo']) ?>" title="Aprobar"><i class="bi bi-check-lg"></i></button>
                  <button class="btn btn-sm btn-outline-danger btn-rechazar-sol" data-id="<?= (int)$s['id'] ?>"
                          data-codigo="<?= htmlspecialchars($s['codigo']) ?>" title="Rechazar"><i class="bi bi-x-lg"></i></button>
                <?php endif; ?>
                <?php if ($s['estado'] === 'aprobada' && $puedeCrear): ?>
                  <button class="btn btn-sm btn-primary btn-entregar-sol" data-id="<?= (int)$s['id'] ?>"
                          data-codigo="<?= htmlspecialchars($s['codigo']) ?>"
                          data-tipo="<?= (int)$s['tipo_equipo_id'] ?>"
                          data-proyecto="<?= (int)$s['proyecto_id'] ?>"
                          data-proyecto-tipo="<?= htmlspecialchars((string)$s['proyecto_tipo']) ?>"
                          data-solicitante="<?= (int)$s['solicitante_personal_id'] ?>"
                          title="Entregar un equipo desde stock"><i class="bi bi-box-arrow-right"></i> Entregar</button>
                <?php endif; ?>
                <?php if (in_array($s['estado'], ['pendiente','aprobada'], true) && ($puedeAprobar || $puedeSolicitar)): ?>
                  <button class="btn btn-sm btn-outline-secondary btn-cancelar-sol" data-id="<?= (int)$s['id'] ?>"
                          data-codigo="<?= htmlspecialchars($s['codigo']) ?>" title="Cancelar solicitud"><i class="bi bi-slash-circle"></i></button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        <div class="text-muted small">
          <i class="bi bi-info-circle"></i> Flujo: <b>Solicitud</b> (cualquier usuario) →
          <b>Aprobación</b> (jefe de área / TI) → <b>Entrega</b> (tab Asignaciones, botón "Registrar entrega").
        </div>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>