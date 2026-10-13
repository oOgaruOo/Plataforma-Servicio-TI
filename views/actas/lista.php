<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeGenerar  = Auth::can('actas', 'generar');
 $puedeBranding = Auth::can('configuracion', 'gestionar');
?>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-file-earmark-text me-1"></i>Actas de entrega, devolución y cambio de equipos</span>
    <div class="d-flex gap-2 flex-wrap">
      <button id="btn-formatos-acta" class="btn btn-outline-secondary btn-sm" title="Código de formato de cada acta según el tipo de equipo">
        <i class="bi bi-upc-scan"></i> Códigos de formato
      </button>
      <?php if ($puedeBranding): ?>
        <button id="btn-branding-acta" class="btn btn-outline-secondary btn-sm" title="Logo y datos de la empresa en el encabezado y pie">
          <i class="bi bi-image"></i> Logo / Encabezado
        </button>
      <?php endif; ?>
      <?php if ($puedeGenerar): ?>
        <button id="btn-nueva-acta" class="btn btn-primary btn-sm" title="Acta de un movimiento ya registrado que todavía no tiene acta">
          <i class="bi bi-plus-lg"></i> Generar acta
        </button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">

    <div class="alert alert-light border small py-2 mb-3">
      <i class="bi bi-info-circle"></i>
      Las actas se generan <b>solas</b> al entregar, cambiar o recibir un equipo en <b>Asignaciones</b>, y su contenido cambia según el
      <b>tipo de equipo</b> (cómputo, celular, impresión, red, UPS, monitor, periférico, telefonía).
      Use <b>Generar acta</b> solo para movimientos que quedaron sin acta.
    </div>

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <select id="af-tipo" class="form-select form-select-sm">
          <option value="">Todos los movimientos</option>
          <option value="entrega">Entrega de equipo</option>
          <option value="devolucion">Devolución</option>
          <option value="cambio">Cambio de equipo</option>
          <option value="otros">Otros formatos</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <select id="af-familia" class="form-select form-select-sm">
          <option value="">Todos los tipos de equipo</option>
          <?php foreach (ActaPerfiles::perfiles() as $k => $p): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($p['nombre']) ?></option>
          <?php endforeach; ?>
          <option value="mixto">Varios tipos en una acta</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <select id="af-firmado" class="form-select form-select-sm">
          <option value="">Firmadas y pendientes</option>
          <option value="0">Pendientes de firma</option>
          <option value="1">Firmadas</option>
        </select>
      </div>
      <div class="col-6 col-md-3 d-flex gap-1">
        <button id="btn-filtrar-actas" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filtrar</button>
        <button id="btn-limpiar-actas" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>

    <table id="tb-actas" class="table table-bordered table-hover align-middle w-100">
      <thead>
        <tr>
          <th>Código</th><th>Acta</th><th>Equipos</th><th>Destino / responsable</th>
          <th>Generada por</th><th>Fecha</th><th>Firma</th><th class="text-center">Documento</th>
        </tr>
      </thead>
    </table>

    <div class="text-muted small mt-2">
      <i class="bi bi-printer"></i>
      <b>Ver / imprimir</b> abre el acta A4 con logo (imprimir o «Guardar como PDF»); allí también puede enviarla por WhatsApp o correo.
      Cuando tenga el acta firmada, súbala con <i class="bi bi-upload"></i> (PDF o foto): queda marcada como <b>Firmada</b> y se puede ver con <i class="bi bi-file-earmark-check"></i> desde aquí.
    </div>
  </div>
</div>
