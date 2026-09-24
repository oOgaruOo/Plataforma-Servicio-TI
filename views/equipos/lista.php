<?php
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeVer   = Auth::can('equipos', 'ver');
 $puedeCrear = Auth::can('equipos', 'crear');
if (!$puedeVer):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para ver el inventario.
  </div>
<?php else: ?>

<div class="row g-2 mb-3" id="equipos-stats">
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="stock">-</div><div class="t">En stock</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="asignados">-</div><div class="t">Asignados</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="mantenimiento">-</div><div class="t">Mantenim.</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="garantia">-</div><div class="t">Garantia <90d</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="baja">-</div><div class="t">De baja</div></div></div>
  <div class="col-6 col-md-2"><div class="mini-stat"><div class="n" data-k="valorizado">-</div><div class="t">Valorizado</div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-pc-display me-1"></i>Inventario de equipos TI</span>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($puedeCrear): ?>
        <button id="btn-importar-equipos" class="btn btn-outline-success btn-sm" title="Carga masiva desde Excel">
          <i class="bi bi-upload"></i> Importar Excel
        </button>
      <?php endif; ?>
      <div class="btn-group">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                title="Descargar el inventario con los filtros aplicados">
          <i class="bi bi-download"></i> Exportar
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#" id="btn-exportar-csv"><i class="bi bi-filetype-csv"></i> CSV (para Excel)</a></li>
          <li><a class="dropdown-item" href="#" id="btn-exportar-xls"><i class="bi bi-file-earmark-excel"></i> Excel con formato</a></li>
        </ul>
      </div>
      <button id="btn-etiquetas-lote" class="btn btn-outline-dark btn-sm" disabled
              title="Imprimir etiquetas con codigo de barras de los seleccionados">
        <i class="bi bi-upc-scan"></i> Etiquetas (0)
      </button>
      <?php if ($puedeCrear): ?>
        <button id="btn-nuevo-equipo" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg"></i> Registrar equipo
        </button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <select id="ef-estado" class="form-select form-select-sm">
          <option value="">Todos los estados</option>
          <option value="en_stock">En stock</option>
          <option value="asignado">Asignado</option>
          <option value="en_prestamo">En prestamo</option>
          <option value="en_revision">En revision</option>
          <option value="en_mantenimiento">En mantenimiento</option>
          <option value="en_reparacion_externa">Reparacion externa</option>
          <option value="obsoleto">Obsoleto</option>
          <option value="dado_de_baja">Dado de baja</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <select id="ef-tipo" class="form-select form-select-sm">
          <option value="">Todos los tipos</option>
          <?php foreach (Database::get("SELECT id, nombre FROM tipo_equipos WHERE activo=1 ORDER BY nombre") as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <select id="ef-garantia" class="form-select form-select-sm">
          <option value="">Garantia: cualquiera</option>
          <option value="1">Por vencer (90 dias o menos)</option>
        </select>
      </div>
      <div class="col-6 col-md-3 d-flex gap-1">
        <button id="btn-filtrar-equipos" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
        <button id="btn-limpiar-equipos" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>

    <table id="tb-equipos" class="table table-bordered table-hover align-middle w-100">
      <thead>
        <tr>
          <th style="width:30px"><input type="checkbox" class="form-check-input chk-todos" title="Seleccionar todos"></th>
          <th>Codigo</th><th>Tipo</th><th>Marca / Modelo</th><th>Serie</th>
          <th>Estado</th><th>Condicion</th><th>Asignado a</th><th>Garantia</th><th>&nbsp;</th>
        </tr>
      </thead>
    </table>

    <div class="text-muted small mt-2">
      <i class="bi bi-info-circle"></i>
      El codigo <b>EQ-AAAAA-#####</b> se genera automaticamente al registrar o importar.
      Marque equipos con los checkboxes para imprimir sus <b>etiquetas con codigo de barras</b>.
      <b>Importar Excel</b> valida cada fila antes de insertar. <b>Exportar</b> descarga lo filtrado.
    </div>
  </div>
</div>
<?php endif; ?>