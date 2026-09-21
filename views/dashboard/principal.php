<?php
require_once __DIR__ . '/../../core/guard_vista.php';
 $u = Auth::info();
 $modulos = [
    ['icono'=>'life-preserver', 'nombre'=>'Tickets / Mesa de Ayuda',        'paso'=>7],
    ['icono'=>'tools',          'nombre'=>'Mantenimiento de Equipos',       'paso'=>8],
    ['icono'=>'pc-display',     'nombre'=>'Inventario de Equipos',          'paso'=>6],
    ['icono'=>'people',         'nombre'=>'Personal (Altas / Ceses)',       'paso'=>5],
    ['icono'=>'box-seam',       'nombre'=>'Asignaciones y Actas',           'paso'=>9],
    ['icono'=>'graph-up-arrow', 'nombre'=>'Reportes en vivo',               'paso'=>11],
];
?>
<div class="row g-3">

  <div class="col-12">
    <div class="card-bienvenida">
      <h4 class="mb-1">👋 Bienvenido, <?= htmlspecialchars($u['nombre']) ?></h4>
      <div class="mb-2">Rol actual:
        <span class="badge bg-light text-primary"><?= htmlspecialchars($u['rol']) ?></span>
      </div>
      <div style="font-size:14px;opacity:.85">
        El shell del sistema funciona correctamente. Los contadores y gráficos
        en tiempo real de este panel se activan en el <strong>Paso 11</strong>.
      </div>
    </div>
  </div>

  <?php foreach ($modulos as $m): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="tarjeta-pendiente">
      <i class="bi bi-<?= $m['icono'] ?>"></i>
      <h6><?= $m['nombre'] ?></h6>
      <span class="badge bg-secondary-subtle text-secondary">Paso <?= $m['paso'] ?></span>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title"><i class="bi bi-signpost-split me-1"></i>Ruta de desarrollo pendiente</h6>
        <div class="row g-2 mt-1" style="font-size:13px">
          <div class="col-md-4">4️⃣ Catálogos + Usuarios del sistema</div>
          <div class="col-md-4">5️⃣ Personal (altas / checklist)</div>
          <div class="col-md-4">6️⃣ Equipos / Inventario</div>
          <div class="col-md-4">7️⃣ Tickets + SLA</div>
          <div class="col-md-4">8️⃣ Mantenimiento</div>
          <div class="col-md-4">9️⃣ Asignaciones + Actas PDF</div>
          <div class="col-md-6">🔟 Ceses + devoluciones</div>
          <div class="col-md-3">1️⃣1️⃣ Dashboard + Reportes</div>
          <div class="col-md-3">1️⃣2️⃣ Notificaciones + Auditoría</div>
        </div>
      </div>
    </div>
  </div>

</div>