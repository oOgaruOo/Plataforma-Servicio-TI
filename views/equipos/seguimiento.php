<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeVer    = Auth::can('equipos', 'ver');
 $puedeEditar = Auth::can('equipos', 'editar');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para ver el seguimiento.
  </div>
<?php else: ?>

<ul class="nav nav-tabs mb-3" id="seg-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="sg-tab-rastreo" data-bs-toggle="tab" data-bs-target="#sg-pane-rastreo" type="button" role="tab">
      <i class="bi bi-crosshair me-1"></i>Rastreo en vivo <span class="badge text-bg-danger d-none" id="rt-badge-perdidos"></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="sg-tab-verif" data-bs-toggle="tab" data-bs-target="#sg-pane-verif" type="button" role="tab">
      <i class="bi bi-geo-alt me-1"></i>Verificación de ubicación
    </button>
  </li>
</ul>

<div class="tab-content">

<!-- ================= RASTREO EN VIVO ================= -->
<div class="tab-pane fade show active" id="sg-pane-rastreo" role="tabpanel">

  <div id="rt-alerta-perdidos"></div>

  <div class="row g-2 mb-3" id="rt-stats">
    <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="total">-</div><div class="t">Con IP / MAC</div></div></div>
    <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="en_linea" style="color:#16a34a">-</div><div class="t">En línea ahora</div></div></div>
    <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="reciente" style="color:#2563eb">-</div><div class="t">Visto en 24 h</div></div></div>
    <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="sin_senal" style="color:#d97706">-</div><div class="t">Sin señal</div></div></div>
    <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="sin_agente" style="color:#64748b">-</div><div class="t">Sin agente</div></div></div>
    <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="perdidos" style="color:#dc2626">-</div><div class="t">Perdidos / robados</div></div></div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="bi bi-broadcast me-1"></i>Equipos con IP o MAC — última ubicación conocida
        <span class="text-muted small ms-2" id="rt-ultima"></span></span>
      <div class="d-flex gap-2 flex-wrap">
        <input type="search" id="rt-buscar" class="form-control form-control-sm" style="width:190px" placeholder="Buscar código, IP, MAC...">
        <select id="rt-filtro" class="form-select form-select-sm" style="width:auto">
          <option value="todos">Todos</option>
          <option value="perdidos">Perdidos / robados</option>
          <option value="en_linea">En línea ahora</option>
          <option value="reciente">Vistos en 24 h</option>
          <option value="sin_senal">Sin señal</option>
          <option value="sin_agente">Sin agente</option>
          <option value="con_mapa">Con ubicación en el mapa</option>
        </select>
        <button id="btn-rt-refrescar" class="btn btn-outline-secondary btn-sm" title="Actualizar ahora"><i class="bi bi-arrow-clockwise"></i></button>
        <?php if ($puedeEditar): ?>
        <button id="btn-rt-sedes" class="btn btn-outline-secondary btn-sm" title="Redes y oficinas conocidas"><i class="bi bi-building"></i> Sedes y redes</button>
        <?php endif; ?>
        <button id="btn-rt-guia" class="btn btn-outline-primary btn-sm" title="Cómo instalar el agente"><i class="bi bi-question-circle"></i> Cómo funciona</button>
      </div>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-xl-7">
          <table id="tb-rt" class="table table-bordered table-hover align-middle w-100">
            <thead>
              <tr>
                <th>Equipo</th><th>Red (IP / MAC)</th><th>Estado</th><th>Última ubicación</th>
                <th>Con quien</th><th class="text-center">Acciones</th>
              </tr>
            </thead>
          </table>
        </div>
        <div class="col-xl-5">
          <div id="rt-mapa" style="height:460px;border:1px solid #cbd5e1;border-radius:8px;background:#f1f5f9"></div>
          <div class="small mt-2 d-flex flex-wrap gap-3" id="rt-leyenda">
            <span><i class="bi bi-circle-fill" style="color:#16a34a"></i> En línea</span>
            <span><i class="bi bi-circle-fill" style="color:#2563eb"></i> Visto en 24 h</span>
            <span><i class="bi bi-circle-fill" style="color:#d97706"></i> Sin señal</span>
            <span><i class="bi bi-circle-fill" style="color:#dc2626"></i> Perdido / robado</span>
            <a href="#" id="rt-ver-todos" class="ms-auto">Ver todos en el mapa</a>
          </div>
        </div>
      </div>

      <div class="text-muted small mt-3">
        <i class="bi bi-info-circle"></i>
        La ubicación la <b>informa el propio equipo</b> (agente instalado): con <b>GPS/Wi-Fi de Windows</b> es precisa (decenas de metros);
        por <b>sede conocida</b> es la oficina; por <b>IP pública</b> es solo aproximada (ciudad). Son datos declarados por el equipo:
        confírmelos físicamente antes de actuar. No modifican el inventario.
      </div>
    </div>
  </div>
</div>

<!-- ================= VERIFICACION ================= -->
<div class="tab-pane fade" id="sg-pane-verif" role="tabpanel">

<div class="row g-2 mb-3" id="seg-stats">
  <div class="col-6 col-md-3"><div class="mini-stat"><div class="n" data-k="total">-</div><div class="t">Laptops + PCs</div></div></div>
  <div class="col-6 col-md-3"><div class="mini-stat"><div class="n" data-k="verde" style="color:#16a34a">-</div><div class="t">Verificado &lt;30d</div></div></div>
  <div class="col-6 col-md-3"><div class="mini-stat"><div class="n" data-k="ambar" style="color:#d97706">-</div><div class="t">30-90 dias</div></div></div>
  <div class="col-6 col-md-3"><div class="mini-stat"><div class="n" data-k="rojo" style="color:#dc2626">-</div><div class="t">Sin verificar +90d</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-geo-alt me-1"></i>Seguimiento de ubicacion — equipos de computo</span>
    <div class="d-flex gap-2">
      <select id="sg-filtro" class="form-select form-select-sm" style="width:auto">
        <option value="todos">Ver todos</option>
        <option value="vencidos30">Sin verificar +30 dias</option>
        <option value="vencidos90">CRITICO: +90 dias</option>
      </select>
      <button id="btn-seg-gestion-ubica" class="btn btn-outline-secondary btn-sm" title="Catalogo de ubicaciones">
        <i class="bi bi-geo"></i> Ubicaciones
      </button>
    </div>
  </div>
  <div class="card-body">

    <table id="tb-seg" class="table table-bordered table-hover align-middle w-100">
      <thead>
        <tr>
          <th>Equipo</th><th>Ubicacion actual</th><th>Con quien</th>
          <th>Estado equipo</th><th>Ult. verificacion</th><th class="text-center">Acciones</th>
        </tr>
      </thead>
    </table>

    <div class="text-muted small mt-2">
      <i class="bi bi-info-circle"></i>
      <b>Verificar</b>: confirma donde esta el equipo (1 clic). <b>Traslado</b>: cambia de ubicacion dejando bitacora.
      <b>Historial</b>: todos los movimientos. Use el filtro para el barrido periodico.
    </div>
  </div>
</div>
</div>

</div>
<?php endif; ?>
