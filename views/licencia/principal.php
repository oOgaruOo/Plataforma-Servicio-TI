<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
if (!Auth::can('configuracion', 'gestionar')):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> Solo el administrador puede ver la licencia y el rubro de la empresa.</div>
<?php else: ?>
<div id="lic-raiz">
  <div id="lic-estado" class="lic-estado mb-3"><span class="text-muted">Cargando…</span></div>

  <div class="row g-3 mb-3">
    <div class="col-lg-5">
      <div class="card h-100"><div class="card-body">
        <h6 class="mb-1"><i class="bi bi-hdd-network"></i> Código de esta instalación</h6>
        <div class="lic-inst mb-1" id="lic-inst">…</div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="lic-copiar"><i class="bi bi-clipboard"></i> Copiar código</button>
        <div class="small text-muted">Envíe este código y el <b>RUC</b> de su empresa a su proveedor de SIGTI para que le emita la licencia. La licencia solo funciona en esta instalación.</div>
      </div></div>
    </div>
    <div class="col-lg-7">
      <div class="card h-100"><div class="card-body">
        <h6 class="mb-2"><i class="bi bi-key"></i> Activar o renovar la licencia</h6>
        <textarea id="lic-texto" class="form-control form-control-sm font-monospace" rows="4" placeholder="Pegue aquí el texto de la licencia (empieza con SIGTI1.)" spellcheck="false"></textarea>
        <div class="d-flex gap-2 mt-2 align-items-center">
          <button type="button" class="btn btn-primary btn-sm" id="lic-activar"><i class="bi bi-check2-circle"></i> Activar licencia</button>
          <span class="small text-muted">Al activar se cargan el nombre y el RUC de la empresa. No se pierde ningún dato.</span>
        </div>
      </div></div>
    </div>
  </div>

  <div class="card mb-3"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
      <h6 class="mb-0"><i class="bi bi-diagram-3"></i> Rubro de la empresa</h6>
      <span class="small text-muted" id="lic-rubro-act"></span>
    </div>
    <div class="small text-muted mb-2">El rubro adapta el sistema a su giro: agrega categorías de tickets, tipos de equipo y áreas propias, y cambia la palabra «obra» por la que usted usa (sede, tienda, planta…). Solo <b>agrega</b>: no borra ni cambia lo que ya tiene, y puede repetirse sin duplicar.</div>
    <div class="row g-2" id="lic-rubros"></div>
    <div class="form-check mt-3">
      <input class="form-check-input" type="checkbox" id="lic-rubro-mod" checked>
      <label class="form-check-label small" for="lic-rubro-mod">Ajustar también los módulos sugeridos para este rubro</label>
    </div>
    <button type="button" class="btn btn-primary btn-sm mt-2" id="lic-rubro-ok" disabled><i class="bi bi-magic"></i> Aplicar rubro</button>
  </div></div>

  <div class="card"><div class="card-body">
    <h6 class="mb-1"><i class="bi bi-toggles"></i> Módulos de la empresa</h6>
    <div class="small text-muted mb-2">Apague lo que su empresa no usa: desaparece del menú y deja de pedirse en el Dashboard. Sus datos se conservan y puede volver a encenderlo cuando quiera.</div>
    <div class="row g-2" id="lic-modulos"></div>
    <button type="button" class="btn btn-primary btn-sm mt-3" id="lic-mod-ok"><i class="bi bi-save"></i> Guardar módulos</button>
  </div></div>
</div>
<?php endif; ?>
