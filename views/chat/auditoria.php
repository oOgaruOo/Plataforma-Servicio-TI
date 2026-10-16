<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

if (!Chat::puedeAuditar()):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permiso para auditar el chat.
  </div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="card-header">
    <i class="bi bi-search me-1"></i>Auditoría de chat
    <div class="text-muted small mt-1">
      Supervisión de solo lectura de <b>cualquier</b> conversación (general, personas, grupos, áreas y reuniones).
      No se recupera el texto de mensajes ya borrados.
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="d-flex gap-2 mb-2">
          <input type="search" class="form-control form-control-sm" id="caud-texto" placeholder="Buscar conversación…">
          <select class="form-select form-select-sm" id="caud-tipo" style="max-width:150px">
            <option value="">Todos los tipos</option>
          </select>
        </div>
        <div class="table-responsive" style="max-height:60vh;overflow-y:auto">
          <table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>Conversación</th><th>Tipo</th><th class="text-end">Mensajes</th><th>Última actividad</th></tr></thead>
            <tbody id="caud-tb"><tr><td colspan="4" class="text-muted text-center p-3">Cargando…</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="col-lg-7">
        <div id="caud-vacio" class="text-muted text-center p-5">
          <i class="bi bi-chat-square-text" style="font-size:2rem"></i>
          <p class="mb-0 mt-2">Elija una conversación de la lista para ver su historial.</p>
        </div>
        <div id="caud-conv" class="d-none">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <div class="fw-semibold" id="caud-titulo"></div>
              <div class="text-muted small" id="caud-sub"></div>
            </div>
            <div class="d-flex gap-1">
              <input type="date" class="form-control form-control-sm" id="caud-desde" title="Desde">
              <input type="date" class="form-control form-control-sm" id="caud-hasta" title="Hasta">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="caud-filtrar" title="Filtrar por fecha"><i class="bi bi-funnel"></i></button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="caud-limpiar" title="Quitar filtro de fecha"><i class="bi bi-x-lg"></i></button>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-light w-100 mb-2 d-none" id="caud-mas">Cargar mensajes más antiguos…</button>
          <div id="caud-msgs" style="max-height:55vh;overflow-y:auto;border:1px solid #e5e7eb;border-radius:.5rem;padding:10px;background:#f8fafc"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
