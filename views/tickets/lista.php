<?php
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeCrear   = Auth::can('tickets', 'crear');
 $puedeAsignar = Auth::can('tickets', 'asignar');
?>

<div class="row g-2 mb-3" id="tickets-stats">
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="activos">–</div><div class="t">Activos</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="abiertos_hoy">–</div><div class="t">Creados hoy</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="vencidos" style="color:#dc2626">–</div><div class="t">SLA vencidos</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="sin_asignar" style="color:#d97706">–</div><div class="t">Sin asignar</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="resueltos_mes">–</div><div class="t">Resueltos mes</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="mis_tickets">–</div><div class="t">A mi cargo</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-life-preserver me-1"></i>Mesa de Ayuda — Tickets de incidencia</span>
    <?php if ($puedeCrear): ?>
      <button id="btn-nuevo-ticket" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo ticket
      </button>
    <?php endif; ?>
  </div>
  <div class="card-body">

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-2">
        <select id="tf-estado" class="form-select form-select-sm">
          <option value="">Todos los estados</option>
          <option value="abierto">Abierto</option>
          <option value="asignado">Asignado</option>
          <option value="en_atencion">En atención</option>
          <option value="pendiente_usuario">Pendiente usuario</option>
          <option value="pendiente_proveedor">Pendiente proveedor</option>
          <option value="resuelto">Resuelto</option>
          <option value="cerrado">Cerrado</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select id="tf-prioridad" class="form-select form-select-sm">
          <option value="">Toda prioridad</option>
          <?php foreach (Database::get("SELECT id, nombre FROM prioridades WHERE activo=1 ORDER BY nivel") as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select id="tf-categoria" class="form-select form-select-sm">
          <option value="">Toda categoría</option>
          <?php foreach (Database::get("SELECT id, nombre FROM categorias WHERE activo=1 ORDER BY nombre") as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select id="tf-tecnico" class="form-select form-select-sm">
          <option value="-1">Todos los técnicos</option>
          <option value="0">— Sin asignar —</option>
          <?php foreach (Database::get("SELECT u.id, u.nombre_completo FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id WHERE r.nombre IN ('tecnico','supervisor','admin_ti') AND u.estado='activo' ORDER BY u.nombre_completo") as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nombre_completo']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2 d-flex align-items-center">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="tf-mios">
          <label class="form-check-label small" for="tf-mios">Solo a mi cargo</label>
        </div>
      </div>
      <div class="col-6 col-md-2 d-flex gap-1">
        <button id="btn-filtrar-tickets" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
        <button id="btn-limpiar-tickets" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>

    <table id="tb-tickets" class="table table-bordered table-hover align-middle w-100">
      <thead>
        <tr>
          <th>Código</th><th>Título</th><th>Categoría</th><th>Prioridad</th>
          <th>Solicitante</th><th>Área</th><th>Técnico</th><th>Estado</th>
          <th>Creado</th><th>SLA restante</th><th>&nbsp;</th>
        </tr>
      </thead>
    </table>
  </div>
</div>