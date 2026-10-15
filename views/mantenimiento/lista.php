<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeCrear  = Auth::can('mantenimiento', 'crear');
 $puedeVer    = Auth::can('mantenimiento', 'ver');
if ($puedeVer) Programados::asegurar();   // columnas del ciclo de preventivos (se crean solas)
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
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="listos" style="color:#16a34a">-</div><div class="t" title="Trabajo terminado que falta cerrar (Listo / Entregado / Devuelto a stock)">Por cerrar</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="costo_mes">-</div><div class="t">Costo del mes</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="programados_prox" style="color:#d97706">-</div><div class="t">Preventivos <30d</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header p-0">
    <ul class="nav nav-tabs card-header-tabs px-2 pt-2">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-panel" type="button" id="btn-tab-panel">
          <i class="bi bi-speedometer2"></i> Panel
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mt" type="button">
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
      <!-- ===== TAB: panel (dashboard del modulo) ===== -->
      <div class="tab-pane fade show active" id="tab-panel">
        <div class="row g-3">
          <div class="col-12 col-lg-4"><div class="border rounded p-2"><canvas id="mt-p-estado" height="180"></canvas></div></div>
          <div class="col-12 col-lg-4"><div class="border rounded p-2"><canvas id="mt-p-tipo" height="180"></canvas></div></div>
          <div class="col-12 col-lg-4"><div class="border rounded p-2">
            <b class="small"><i class="bi bi-cash-stack"></i> Costos</b>
            <div class="small mt-2">Del mes: <b id="mt-p-costomes">—</b></div>
            <div class="small">Del año: <b id="mt-p-costoyear">—</b></div>
            <div class="small text-muted mt-1">Detalle en Reportes → Mantenimiento</div>
          </div></div>
          <div class="col-12 col-lg-7"><div class="border rounded p-2"><canvas id="mt-p-costos" height="130"></canvas></div></div>
          <div class="col-12 col-lg-5"><div class="border rounded p-2">
            <b class="small"><i class="bi bi-calendar-check"></i> Preventivos próximos (30 días)</b>
            <div id="mt-p-proximos" class="small mt-1 text-muted">Sin programaciones próximas.</div>
          </div></div>
          <div class="col-12"><div class="border rounded p-2">
            <b class="small text-danger"><i class="bi bi-exclamation-triangle"></i> SLA interna vencida (sin cerrar)</b>
            <div id="mt-p-sla" class="small mt-1 text-muted">Nada vencido. ✔</div>
          </div></div>
          <div class="col-12"><hr class="my-1"></div>
          <div class="col-12 col-lg-4"><div class="border rounded p-2">
            <b class="small"><i class="bi bi-stopwatch"></i> Tiempo promedio de atención</b>
            <div class="small mt-2">General: <b id="mt-p-tprom">—</b></div>
            <div id="mt-p-tprom-tipo" class="small mt-1 text-muted">Sin datos aún.</div>
          </div></div>
          <div class="col-12 col-lg-4"><div class="border rounded p-2">
            <b class="small"><i class="bi bi-truck"></i> Top proveedores</b>
            <div id="mt-p-proveedores" class="small mt-1 text-muted">Sin mantenimientos con proveedor aún.</div>
          </div></div>
          <div class="col-12 col-lg-4"><div class="border rounded p-2">
            <b class="small"><i class="bi bi-person-gear"></i> Top técnicos internos</b>
            <div id="mt-p-tecnicos" class="small mt-1 text-muted">Sin mantenimientos internos aún.</div>
          </div></div>
          <div class="col-12"><div class="border rounded p-2">
            <b class="small text-warning-emphasis"><i class="bi bi-arrow-repeat"></i> Equipos con fallas recurrentes (últimos 12 meses)</b>
            <div id="mt-p-topequipos" class="small mt-1 text-muted">Sin equipos con 2 o más mantenimientos aún.</div>
          </div></div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-mt">
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-3">
            <input type="text" id="mtf-buscar" class="form-control form-control-sm"
                   placeholder="Buscar por código, equipo, proveedor, técnico o ticket…">
          </div>
          <div class="col-6 col-md-3">
            <select id="mtf-estado" class="form-select form-select-sm">
              <option value="">Todos los estados</option>
              <optgroup label="Grupos">
                <option value="en_curso">En curso (trabajo pendiente)</option>
                <option value="por_cerrar">Por cerrar (listo / entregado)</option>
                <option value="finalizados">Finalizados (cerrado / cancelado)</option>
              </optgroup>
              <optgroup label="Estado exacto">
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
              </optgroup>
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

        <div class="d-flex justify-content-end mb-2">
          <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                    title="Descargar la lista con los filtros aplicados">
              <i class="bi bi-download"></i> Exportar
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="#" id="btn-exportar-mt-csv"><i class="bi bi-filetype-csv"></i> CSV (para Excel)</a></li>
              <li><a class="dropdown-item" href="#" id="btn-exportar-mt-xls"><i class="bi bi-file-earmark-excel"></i> Excel con formato</a></li>
            </ul>
          </div>
        </div>

        <table id="tb-mt" class="table table-bordered table-hover align-middle w-100">
          <thead>
            <tr>
              <th>Código</th><th>Equipo</th><th>Tipo</th><th>Estado</th>
              <th>Proveedor</th><th>Técnico</th><th>Retorno est.</th>
              <th>Costo</th><th>Iniciado</th><th>SLA interna</th><th>&nbsp;</th>
            </tr>
          </thead>
        </table>
      </div>

      <!-- ===== TAB: preventivos programados (render servidor) ===== -->
      <div class="tab-pane fade" id="tab-prev">
        <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 mb-3" id="motor-banner">
          <span>
            <i class="bi bi-robot me-1"></i><b>Motor autónomo activo</b> — revisa cada día:
            preventivos por calendario, equipos que cumplieron su
            <b>tiempo de uso (<?= (int)(Database::getValue("SELECT MAX(vida_util_meses) FROM tipo_equipos") ?: 65) ?> meses)</b>
            y equipos con <b>fallas técnicas recurrentes</b> (2+ correctivos en 6 meses).
            <span class="d-block small text-muted" id="motor-ultima">Consultando última ejecución…</span>
          </span>
          <?php if ($puedeCrear): ?>
            <button id="btn-motor-ejecutar" class="btn btn-sm btn-outline-success">
              <i class="bi bi-play-fill"></i> Forzar ahora
            </button>
          <?php endif; ?>
        </div>
        <div class="row g-2 mb-2 align-items-center">
          <div class="col-12 col-md-5">
            <input type="text" id="prevf-buscar" class="form-control form-control-sm"
                   placeholder="Buscar por equipo, marca, tipo o responsable…">
          </div>
          <div class="col-6 col-md-3">
            <select id="prevf-situacion" class="form-select form-select-sm">
              <option value="">Toda situación</option>
              <option value="vencida">Vencida</option>
              <option value="por_vencer">Por vencer</option>
              <option value="en_curso">En curso</option>
              <option value="al_dia">Al día</option>
              <option value="pausada">Pausada</option>
            </select>
          </div>
          <div class="col-6 col-md-4 text-end">
            <?php if ($puedeCrear): ?>
              <button id="btn-nuevo-prog" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Programar preventivo
              </button>
            <?php endif; ?>
          </div>
        </div>
        <table id="tb-prev" class="table table-bordered table-hover align-middle tabla-mini">
          <thead>
            <tr>
              <th>Equipo</th><th>Tipo</th><th>Frecuencia</th><th>Último realizado</th>
              <th>Próximo</th><th>Situación</th><th>Responsable</th><th class="text-center">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $progs = Database::get(
            "SELECT mp.*, e.codigo AS eq, e.marca, e.modelo, t.nombre AS tipo_eq,
                    CONCAT(u.nombre_completo, '') AS resp,
                    ma.codigo AS mt_actual, ma.estado AS mt_actual_estado,
                    ml.codigo AS mt_ultimo, ml.atraso_min AS mt_ultimo_atraso
             FROM mantenimientos_programados mp
             INNER JOIN equipos e ON e.id = mp.equipo_id
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN usuarios u ON u.id = mp.responsable_id
             LEFT JOIN mantenimientos ma ON ma.id = mp.mantenimiento_actual_id
             LEFT JOIN mantenimientos ml ON ml.id = mp.ultimo_mantenimiento_id
             ORDER BY mp.activo DESC, mp.proxima_fecha ASC");
          $hoyP = date('Y-m-d');
          $ESTMT = ['solicitado'=>'Solicitado','en_evaluacion'=>'En evaluación','en_proceso'=>'En proceso','enviado_proveedor'=>'Con proveedor',
                    'cotizado'=>'Cotizado','aprobado'=>'Aprobado','rechazado'=>'Rechazado','en_reparacion'=>'En reparación',
                    'recibido_reparado'=>'Recibido','listo'=>'Listo','entregado'=>'Entregado','devuelto_stock'=>'Devuelto a stock'];
          if (!$progs): ?>
            <tr><td colspan="8" class="text-muted text-center">Sin mantenimientos programados aún</td></tr>
          <?php else: foreach ($progs as $p):
            $ciclo = Programados::estadoCiclo($p, $hoyP);
            $dias  = Programados::dias((string)$p['proxima_fecha'], $hoyP);
          ?>
            <tr data-ciclo="<?= $ciclo ?>"
                data-busca="<?= htmlspecialchars(mb_strtolower($p['eq'].' '.$p['marca'].' '.$p['modelo'].' '.$p['tipo_eq'].' '.$p['tipo'].' '.($p['resp'] ?: ''))) ?>">
              <td><b><?= htmlspecialchars($p['eq']) ?></b><br>
                  <span class="text-muted small"><?= htmlspecialchars($p['tipo_eq'].' '.$p['marca']) ?></span></td>
              <td><?= htmlspecialchars(ucfirst($p['tipo'])) ?></td>
              <td><?= htmlspecialchars(ucfirst($p['frecuencia'])) ?></td>
              <td>
                <?php if ($p['ultima_ejecucion']): ?>
                  <b><?= htmlspecialchars($p['ultima_ejecucion']) ?></b>
                  <?php if ($p['mt_ultimo']): ?><br><span class="text-muted small"><?= htmlspecialchars($p['mt_ultimo']) ?>
                    <?= (int)$p['mt_ultimo_atraso'] > 0 ? '· <span class="text-danger">con atraso</span>' : '· a tiempo' ?></span><?php endif; ?>
                <?php else: ?><span class="text-muted">Nunca</span><?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $ciclo === 'vencida' ? 'text-bg-danger' : ($ciclo === 'pausada' ? 'text-bg-secondary' : ($ciclo === 'en_curso' ? 'text-bg-info' : ($ciclo === 'por_vencer' ? 'text-bg-warning text-dark' : 'text-bg-primary'))) ?>">
                  <?= htmlspecialchars($p['proxima_fecha']) ?></span>
                <?php if ($ciclo !== 'pausada' && $ciclo !== 'en_curso'): ?>
                  <br><span class="small <?= $dias < 0 ? 'text-danger' : 'text-muted' ?>"><?=
                    $dias < 0 ? 'vencido hace ' . abs($dias) . ' d' : ($dias === 0 ? 'vence hoy' : 'en ' . $dias . ' d') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($ciclo === 'en_curso'): ?>
                  <span class="badge text-bg-info">En curso</span><br>
                  <a href="#" class="small btn-ver-prog-mt" data-id="<?= (int)$p['mantenimiento_actual_id'] ?>"><?= htmlspecialchars($p['mt_actual']) ?></a>
                  <span class="small text-muted">· <?= htmlspecialchars($ESTMT[$p['mt_actual_estado']] ?? $p['mt_actual_estado']) ?></span>
                <?php elseif ($ciclo === 'vencida'): ?><span class="badge text-bg-danger">Vencida</span>
                <?php elseif ($ciclo === 'por_vencer'): ?><span class="badge text-bg-warning text-dark">Por vencer</span>
                <?php elseif ($ciclo === 'pausada'): ?><span class="badge text-bg-secondary">Pausada</span>
                <?php else: ?><span class="badge text-bg-success">Al día</span><?php endif; ?>
              </td>
              <td><?= htmlspecialchars($p['resp'] ?: '—') ?></td>
              <td class="text-center text-nowrap">
                <?php if ($puedeCrear && $p['activo'] && $ciclo !== 'en_curso'): ?>
                  <button class="btn btn-sm btn-outline-primary btn-ejecutar-prog" data-id="<?= (int)$p['id'] ?>"
                          data-equipo="<?= htmlspecialchars($p['eq']) ?>" title="Generar el mantenimiento ahora">
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
          <tr id="prev-sin-resultados" class="d-none"><td colspan="8" class="text-muted text-center">Sin resultados para el filtro aplicado</td></tr>
          </tbody>
        </table>
        <div class="text-muted small">
          <i class="bi bi-info-circle"></i> Al llegar la fecha el sistema genera el mantenimiento solo (y avisa al responsable
          7 días antes). Cuando usted lo <b>finaliza</b>, se anota la fecha real y la <b>próxima fecha se calcula sola</b>
          según la frecuencia, contada desde el día en que se terminó. «▶» lo genera antes de tiempo.
        </div>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>