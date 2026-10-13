<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
$puede = Auth::can('configuracion', 'gestionar');
if (!$puede):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> Sin permisos para gestionar permisos.</div>
<?php else: ?>
<style>
  .pm-matriz-wrap{max-height:68vh;overflow:auto;border:1px solid var(--bs-border-color,#dee2e6);border-radius:.4rem}
  .pm-matriz{margin:0;border-collapse:separate;border-spacing:0;font-size:.85rem}
  .pm-matriz thead th{position:sticky;top:0;z-index:3;background:#f8fafc;border-bottom:1px solid #dee2e6;text-align:center;white-space:nowrap;padding:.4rem .6rem}
  .pm-matriz thead th:first-child,.pm-matriz tbody td:first-child{position:sticky;left:0;z-index:2;background:#fff;text-align:left;min-width:260px}
  .pm-matriz thead th:first-child{z-index:4;background:#f8fafc}
  .pm-matriz tbody td{padding:.3rem .6rem;border-bottom:1px solid #eef0f3;text-align:center}
  .pm-matriz tr.pm-mod td{background:#eef2f7;font-weight:600;font-size:.75rem;letter-spacing:.04em;text-transform:uppercase;color:#334155;text-align:left;position:static}
  .pm-matriz tr.pm-sens td:first-child{box-shadow:inset 3px 0 0 #f59e0b}
  .pm-matriz .pm-super{opacity:.55}
  .pm-matriz .form-check-input{cursor:pointer;margin:0}
  .pm-matriz .form-check-input:disabled{cursor:not-allowed}
  .pm-grupo .pm-fila:hover{background:#f8fafc}
  .pm-chip{display:inline-block;font-size:.72rem;padding:.05rem .45rem;border-radius:1rem;background:#eef2ff;color:#3730a3;border:1px solid #e0e7ff;margin:0 .15rem .15rem 0}
  .pm-chip.pm-super{background:#fef3c7;color:#92400e;border-color:#fde68a;opacity:1}
</style>
<div id="pm-raiz" data-puede-roles="<?= Auth::can('usuarios', 'gestionar') ? 1 : 0 ?>">
  <div class="row g-2 mb-3" id="pm-stats">
    <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="total">-</div><div class="t">Permisos</div></div></div>
    <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="modulos">-</div><div class="t">Módulos</div></div></div>
    <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="sensibles" style="color:#d97706">-</div><div class="t">Sensibles</div></div></div>
    <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="sinuso" style="color:#64748b">-</div><div class="t">Sin asignar a ningún rol</div></div></div>
    <div class="col-6 col-md-3 col-xl"><div class="mini-stat"><div class="n" data-k="ajustes" style="color:#2563eb">-</div><div class="t">Ajustes por usuario</div></div></div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header">
      <ul class="nav nav-tabs card-header-tabs" id="pm-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="pm-t-cat" data-bs-toggle="tab" data-bs-target="#pm-p-cat" type="button" role="tab" data-vista="cat"><i class="bi bi-list-check me-1"></i>Catálogo</button></li>
        <li class="nav-item"><button class="nav-link" id="pm-t-mat" data-bs-toggle="tab" data-bs-target="#pm-p-mat" type="button" role="tab" data-vista="mat"><i class="bi bi-grid-3x3-gap me-1"></i>Matriz por rol</button></li>
        <li class="nav-item"><button class="nav-link" id="pm-t-aj" data-bs-toggle="tab" data-bs-target="#pm-p-aj" type="button" role="tab" data-vista="aj"><i class="bi bi-person-gear me-1"></i>Ajustes por usuario</button></li>
      </ul>
    </div>
    <div class="card-body tab-content">

      <!-- ===== CATALOGO ===== -->
      <div class="tab-pane fade show active" id="pm-p-cat" role="tabpanel">
        <div class="row g-2 mb-2 align-items-center">
          <div class="col-12 col-md-4"><input type="search" id="pm-q" class="form-control form-control-sm" placeholder="Buscar permiso o descripción..."></div>
          <div class="col-6 col-md-3"><select id="pm-mod" class="form-select form-select-sm"><option value="">Todos los módulos</option></select></div>
          <div class="col-6 col-md-5 text-md-end">
            <button id="btn-nuevo-permiso" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo permiso</button>
          </div>
        </div>
        <div class="btn-group btn-group-sm mb-3 flex-wrap" id="pm-chips" role="group">
          <button type="button" class="btn btn-outline-secondary active" data-f="todos">Todos</button>
          <button type="button" class="btn btn-outline-secondary" data-f="sensibles">Sensibles</button>
          <button type="button" class="btn btn-outline-secondary" data-f="sinuso">Sin asignar</button>
          <button type="button" class="btn btn-outline-secondary" data-f="ajustes">Con ajustes por usuario</button>
          <button type="button" class="btn btn-outline-secondary" data-f="propios">Creados por mí</button>
        </div>
        <div id="lista-permisos" class="small text-muted">Cargando…</div>
        <div class="text-muted small mt-3">
          <i class="bi bi-info-circle"></i>
          Un permiso define <b>qué puede hacer</b> alguien. Se da a un <b>rol</b> (pestaña «Matriz por rol» o <b>Usuarios → Roles</b>)
          y, solo para casos puntuales, a <b>una persona</b> (<b>Usuarios → permisos del usuario</b>).
          Los permisos marcados <span class="badge text-bg-secondary">Sistema</span> los usa el programa y no se pueden eliminar.
          Un permiso nuevo solo sirve cuando una pantalla del sistema lo exige.
        </div>
      </div>

      <!-- ===== MATRIZ ===== -->
      <div class="tab-pane fade" id="pm-p-mat" role="tabpanel">
        <div class="row g-2 mb-2 align-items-center">
          <div class="col-12 col-md-4"><input type="search" id="mx-q" class="form-control form-control-sm" placeholder="Buscar permiso..."></div>
          <div class="col-6 col-md-3"><select id="mx-mod" class="form-select form-select-sm"><option value="">Todos los módulos</option></select></div>
          <div class="col-6 col-md-5 small text-muted" id="mx-aviso"></div>
        </div>
        <div class="pm-matriz-wrap"><div id="pm-matriz" class="p-3 small text-muted">Cargando…</div></div>
        <div class="text-muted small mt-2">
          <i class="bi bi-info-circle"></i> Cada casilla se guarda al instante y queda en Auditoría. <b>super_admin</b> siempre tiene todo.
          Las filas con barra naranja son permisos sensibles. Los cambios se aplican a los usuarios del rol en menos de un minuto.
        </div>
      </div>

      <!-- ===== AJUSTES ===== -->
      <div class="tab-pane fade" id="pm-p-aj" role="tabpanel">
        <div class="row g-2 mb-2 align-items-center">
          <div class="col-12 col-md-4"><input type="search" id="aj-q" class="form-control form-control-sm" placeholder="Buscar usuario o permiso..."></div>
          <div class="col-6 col-md-3"><select id="aj-tipo" class="form-select form-select-sm">
            <option value="">Concedidos y quitados</option><option value="1">Solo concedidos</option><option value="0">Solo quitados</option></select></div>
        </div>
        <div id="pm-ajustes" class="small text-muted">Cargando…</div>
        <div class="text-muted small mt-3">
          <i class="bi bi-info-circle"></i> Son excepciones: un permiso <b>concedido</b> a una persona además de los de su rol, o un permiso del rol que se le <b>quitó</b>.
          Los ajustes marcados «sin efecto» ya no cambian nada y se pueden quitar.
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
