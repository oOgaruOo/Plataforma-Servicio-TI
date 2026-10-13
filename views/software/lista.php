<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
$puedeVer = Auth::can('software', 'ver');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> No tiene permisos para ver el software instalado.</div>
<?php else:
    $puedeRegistrar = Auth::can('software', 'registrar');
    $puedeGestionar = Auth::can('software', 'gestionar');
    $tipos = Database::get("SELECT id, nombre FROM tipo_equipos WHERE activo=1 ORDER BY nombre");
?>
<style>
  .sw-card{cursor:default}
  .sw-alerta{display:flex;align-items:center;gap:.75rem;padding:.55rem .75rem;border:1px solid #e5e7eb;border-radius:.5rem;margin-bottom:.4rem;background:#fff}
  .sw-alerta.clic{cursor:pointer}
  .sw-alerta.clic:hover{background:#f8fafc;border-color:#cbd5e1}
  .sw-alerta .n{min-width:2.4rem;text-align:center;font-weight:700;font-size:1.1rem;border-radius:.4rem;padding:.1rem .4rem}
  .sw-alerta.rojo .n{background:#fee2e2;color:#b91c1c}
  .sw-alerta.ambar .n{background:#fef3c7;color:#92400e}
  .sw-alerta.azul .n{background:#dbeafe;color:#1e40af}
  .sw-barra{height:.5rem;background:#e5e7eb;border-radius:1rem;overflow:hidden}
  .sw-barra>span{display:block;height:100%;background:#2563eb}
  .sw-barra.rojo>span{background:#dc2626}.sw-barra.verde>span{background:#16a34a}.sw-barra.ambar>span{background:#f59e0b}
  .sw-fila-sw{display:flex;justify-content:space-between;gap:.5rem;font-size:.85rem;padding:.2rem 0;border-bottom:1px dashed #e5e7eb}
  #sw-vista .nav-link{white-space:nowrap}
  .sw-clave{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.8rem}
  .sw-pick-fila{display:grid;grid-template-columns:minmax(180px,2fr) minmax(110px,1fr) minmax(150px,1.2fr) auto;gap:.4rem;align-items:center;margin-bottom:.4rem}
  @media (max-width:768px){.sw-pick-fila{grid-template-columns:1fr 1fr}}
</style>
<div id="sw-raiz" data-registrar="<?= $puedeRegistrar ? 1 : 0 ?>" data-gestionar="<?= $puedeGestionar ? 1 : 0 ?>">

  <div class="card shadow-sm" id="sw-vista">
    <div class="card-header">
      <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto" id="sw-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="sw-t-res" data-bs-toggle="tab" data-bs-target="#sw-p-res" type="button" role="tab" data-vista="res"><i class="bi bi-clipboard-data me-1"></i>Resumen</button></li>
        <li class="nav-item"><button class="nav-link" id="sw-t-inst" data-bs-toggle="tab" data-bs-target="#sw-p-inst" type="button" role="tab" data-vista="inst"><i class="bi bi-app-indicator me-1"></i>Instalaciones</button></li>
        <li class="nav-item"><button class="nav-link" id="sw-t-eq" data-bs-toggle="tab" data-bs-target="#sw-p-eq" type="button" role="tab" data-vista="eq"><i class="bi bi-pc-display me-1"></i>Por equipo</button></li>
        <li class="nav-item"><button class="nav-link" id="sw-t-cat" data-bs-toggle="tab" data-bs-target="#sw-p-cat" type="button" role="tab" data-vista="cat"><i class="bi bi-collection me-1"></i>Catálogo</button></li>
        <li class="nav-item"><button class="nav-link" id="sw-t-lic" data-bs-toggle="tab" data-bs-target="#sw-p-lic" type="button" role="tab" data-vista="lic"><i class="bi bi-key me-1"></i>Licencias</button></li>
      </ul>
    </div>
    <div class="card-body tab-content">

      <!-- ====================== RESUMEN ====================== -->
      <div class="tab-pane fade show active" id="sw-p-res" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div class="text-muted small"><i class="bi bi-info-circle"></i> Control interno del software de los equipos: qué hay instalado, con qué licencia y qué está fuera de regla.</div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="#" class="btn btn-outline-primary btn-sm" id="sw-informe"><i class="bi bi-printer"></i> Informe para gerencia</a>
            <div class="btn-group">
              <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> Descargar</button>
              <ul class="dropdown-menu dropdown-menu-end" id="sw-menu-exp">
                <li><h6 class="dropdown-header">Cumplimiento por programa</h6></li>
                <li><a class="dropdown-item sw-exp" href="#" data-r="cumplimiento" data-f="excel"><i class="bi bi-file-earmark-excel"></i> Excel</a></li>
                <li><a class="dropdown-item sw-exp" href="#" data-r="cumplimiento" data-f="csv"><i class="bi bi-filetype-csv"></i> CSV</a></li>
                <li><h6 class="dropdown-header">Software instalado por equipo</h6></li>
                <li><a class="dropdown-item sw-exp" href="#" data-r="instalaciones" data-f="excel"><i class="bi bi-file-earmark-excel"></i> Excel</a></li>
                <li><a class="dropdown-item sw-exp" href="#" data-r="instalaciones" data-f="csv"><i class="bi bi-filetype-csv"></i> CSV</a></li>
                <li><h6 class="dropdown-header">Licencias</h6></li>
                <li><a class="dropdown-item sw-exp" href="#" data-r="licencias" data-f="excel"><i class="bi bi-file-earmark-excel"></i> Excel</a></li>
                <li><a class="dropdown-item sw-exp" href="#" data-r="licencias" data-f="csv"><i class="bi bi-filetype-csv"></i> CSV</a></li>
                <li><h6 class="dropdown-header">Control por equipo</h6></li>
                <li><a class="dropdown-item sw-exp" href="#" data-r="equipos" data-f="excel"><i class="bi bi-file-earmark-excel"></i> Excel</a></li>
                <li><a class="dropdown-item sw-exp" href="#" data-r="equipos" data-f="csv"><i class="bi bi-filetype-csv"></i> CSV</a></li>
              </ul>
            </div>
          </div>
        </div>

        <div class="row g-2 mb-3" id="sw-stats">
          <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="programas">-</div><div class="t">Programas en catálogo</div></div></div>
          <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="instalaciones">-</div><div class="t">Instalaciones</div></div></div>
          <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="cobertura">-</div><div class="t">Equipos de cómputo con software registrado</div></div></div>
          <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="puestos">-</div><div class="t">Puestos de licencia vigentes</div></div></div>
          <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="inversion">-</div><div class="t">Inversión en licencias</div></div></div>
          <div class="col-6 col-md-4 col-xl"><div class="mini-stat"><div class="n" data-k="alertas" style="color:#b91c1c">-</div><div class="t">Observaciones por revisar</div></div></div>
        </div>

        <div class="row g-3">
          <div class="col-lg-5">
            <h6 class="mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Observaciones de cumplimiento</h6>
            <div id="sw-alertas"><div class="text-muted small">Cargando…</div></div>
            <h6 class="mt-3 mb-2"><i class="bi bi-calendar-event me-1"></i>Licencias por vencer o vencidas</h6>
            <div id="sw-lic-alerta"></div>
          </div>
          <div class="col-lg-7">
            <h6 class="mb-2"><i class="bi bi-shield-check me-1"></i>Cumplimiento por programa</h6>
            <div class="table-responsive" style="max-height:340px;overflow:auto">
              <table class="table table-sm table-bordered align-middle mb-0" id="sw-tb-cumpl">
                <thead><tr><th>Programa</th><th class="text-center">Instalados</th><th class="text-center">Puestos vigentes</th><th>Situación</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-6"><h6 class="mb-2"><i class="bi bi-bar-chart me-1"></i>Más instalados</h6><div id="sw-top"></div></div>
              <div class="col-md-6"><h6 class="mb-2"><i class="bi bi-diagram-3 me-1"></i>Por categoría</h6><div id="sw-categ"></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ====================== INSTALACIONES ====================== -->
      <div class="tab-pane fade" id="sw-p-inst" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
          <div class="text-muted small">Cada fila es un programa instalado en un equipo. Al retirarlo queda en el historial.</div>
          <?php if ($puedeRegistrar): ?>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm" id="sw-btn-importar"><i class="bi bi-upload"></i> Carga masiva</button>
            <button class="btn btn-outline-primary btn-sm" id="sw-btn-masivo"><i class="bi bi-pc-display-horizontal"></i> Instalar en varios equipos</button>
            <button class="btn btn-primary btn-sm" id="sw-btn-instalar"><i class="bi bi-plus-lg"></i> Registrar instalación</button>
          </div>
          <?php endif; ?>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6 col-md-3"><select id="swf-software" class="form-select form-select-sm"><option value="">Todos los programas</option></select></div>
          <div class="col-6 col-md-3"><select id="swf-categoria" class="form-select form-select-sm"><option value="">Todas las categorías</option></select></div>
          <div class="col-6 col-md-3">
            <select id="swf-tipo" class="form-select form-select-sm">
              <option value="">Todos los tipos de equipo</option>
              <?php foreach ($tipos as $t): ?><option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="swf-alerta" class="form-select form-select-sm">
              <option value="">Sin filtro de observaciones</option>
              <option value="cualquiera">Con cualquier observación</option>
              <option value="no_autorizado">Programa no autorizado</option>
              <option value="sin_licencia">Sin licencia asignada</option>
              <option value="licencia_vencida">Licencia vencida</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="swf-estado" class="form-select form-select-sm">
              <option value="instalado">Instalados</option>
              <option value="desinstalado">Retirados (historial)</option>
              <option value="todos">Todos</option>
            </select>
          </div>
          <div class="col-6 col-md-3 d-flex gap-1">
            <button id="swf-filtrar" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
            <button id="swf-limpiar" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
        <div id="swf-equipo-badge" class="mb-2 d-none"></div>
        <table id="tb-sw-inst" class="table table-bordered table-hover align-middle w-100">
          <thead><tr><th>Equipo</th><th>Programa</th><th>Categoría</th><th>Versión</th><th>Licencia</th><th>Instalado</th><th>Asignado a</th><th>Estado</th><th>&nbsp;</th></tr></thead>
        </table>
      </div>

      <!-- ====================== POR EQUIPO ====================== -->
      <div class="tab-pane fade" id="sw-p-eq" role="tabpanel">
        <div class="text-muted small mb-2">Todos los equipos activos y lo que tienen registrado. «Confirmar» deja constancia de que alguien revisó el equipo y el listado es correcto (vale por <?= (int)Software::DIAS_VERIFICACION ?> días).</div>
        <div class="row g-2 mb-3">
          <div class="col-6 col-md-3">
            <select id="swe-tipo" class="form-select form-select-sm">
              <option value="">Todos los tipos de equipo</option>
              <?php foreach ($tipos as $t): ?><option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="swe-familia" class="form-select form-select-sm">
              <option value="">Todas las familias</option>
              <option value="computo" selected>Equipos de cómputo (laptop, PC, servidor)</option>
              <option value="movil">Móviles</option>
              <option value="impresion">Impresión</option>
              <option value="red">Red</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="swe-cobertura" class="form-select form-select-sm">
              <option value="">Con y sin software</option>
              <option value="con">Con software registrado</option>
              <option value="sin">Sin software registrado</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select id="swe-estado" class="form-select form-select-sm">
              <option value="">Sin filtro de observaciones</option>
              <option value="alerta">Con observaciones</option>
              <option value="verif">Inventario sin confirmar</option>
            </select>
          </div>
          <div class="col-12 col-md-3 d-flex gap-1">
            <button id="swe-filtrar" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
            <button id="swe-limpiar" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
        <table id="tb-sw-eq" class="table table-bordered table-hover align-middle w-100">
          <thead><tr><th>Equipo</th><th>Tipo</th><th>Marca / Modelo</th><th>Asignado a</th><th class="text-center">Programas</th><th class="text-center">Observaciones</th><th>Inventario confirmado</th><th>&nbsp;</th></tr></thead>
        </table>
      </div>

      <!-- ====================== CATALOGO ====================== -->
      <div class="tab-pane fade" id="sw-p-cat" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
          <div class="text-muted small">Programas conocidos por la empresa. La <b>autorización</b> y si <b>requiere licencia</b> definen qué se marca como observación.</div>
          <?php if ($puedeGestionar): ?><button class="btn btn-primary btn-sm" id="sw-btn-nuevo-prog"><i class="bi bi-plus-lg"></i> Nuevo programa</button><?php endif; ?>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-4"><input id="swc-q" class="form-control form-control-sm" placeholder="Buscar programa o fabricante…"></div>
          <div class="col-6 col-md-3"><select id="swc-categoria" class="form-select form-select-sm"><option value="">Todas las categorías</option></select></div>
          <div class="col-6 col-md-3">
            <select id="swc-aut" class="form-select form-select-sm">
              <option value="">Toda autorización</option><option value="autorizado">Autorizado</option><option value="evaluacion">En evaluación</option><option value="prohibido">No autorizado</option>
            </select>
          </div>
          <div class="col-12 col-md-2">
            <select id="swc-activo" class="form-select form-select-sm"><option value="1">Activos</option><option value="0">Desactivados</option><option value="">Todos</option></select>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle" id="sw-tb-cat">
            <thead><tr><th>Programa</th><th>Categoría</th><th>Autorización</th><th class="text-center">Licencia</th><th class="text-center">Instalados</th><th>Licencias / puestos</th><th>&nbsp;</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <!-- ====================== LICENCIAS ====================== -->
      <div class="tab-pane fade" id="sw-p-lic" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
          <div class="text-muted small">Cada licencia tiene <b>puestos</b> (equipos que puede cubrir). No se puede instalar más allá de los puestos ni con una licencia vencida.
            <?php if (!$puedeGestionar): ?>Las claves completas solo las ven quienes gestionan licencias.<?php endif; ?></div>
          <?php if ($puedeGestionar): ?><button class="btn btn-primary btn-sm" id="sw-btn-nueva-lic"><i class="bi bi-plus-lg"></i> Nueva licencia</button><?php endif; ?>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-4"><select id="swl-software" class="form-select form-select-sm"><option value="">Todos los programas</option></select></div>
          <div class="col-6 col-md-3">
            <select id="swl-estado" class="form-select form-select-sm">
              <option value="">Todos los estados</option><option value="vigente">Vigentes</option><option value="por_vencer">Por vencer</option>
              <option value="vencida">Vencidas</option><option value="sin_vencimiento">Sin vencimiento</option><option value="baja">Dadas de baja</option>
            </select>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle" id="sw-tb-lic">
            <thead><tr><th>Programa</th><th>Tipo</th><th>Clave</th><th style="min-width:130px">Puestos</th><th>Vencimiento</th><th class="text-end">Costo</th><th>Proveedor / Documento</th><th>&nbsp;</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>
