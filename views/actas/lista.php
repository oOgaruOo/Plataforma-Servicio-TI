<?php
require_once __DIR__ . '/../../core/guard_vista.php';

 $puedeGenerar  = Auth::can('actas', 'generar');
 $puedeBranding = Auth::can('configuracion', 'gestionar');
?>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-file-earmark-text me-1"></i>Actas de entrega, cambio y devolución</span>
    <div class="d-flex gap-2">
      <?php if ($puedeBranding): ?>
        <button id="btn-branding-acta" class="btn btn-outline-secondary btn-sm"
                title="Logo y datos de empresa en las actas">
          <i class="bi bi-image"></i> Logo / Encabezado
        </button>
      <?php endif; ?>
      <?php if ($puedeGenerar): ?>
        <button id="btn-nueva-acta" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg"></i> Generar acta
        </button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <select id="af-tipo" class="form-select form-select-sm">
          <option value="">Todos los tipos</option>
          <option value="entrega">Entrega de equipo</option>
          <option value="cambio">Reemplazo de equipo</option>
          <option value="devolucion">Devolución (cese)</option>
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
          <th>Código</th><th>Tipo</th><th>Colaborador</th><th>Área</th>
          <th>Generada por</th><th>Fecha</th><th class="text-center">Documento</th>
        </tr>
      </thead>
    </table>

    <div class="text-muted small mt-2">
      <i class="bi bi-info-circle"></i>
      <b>Ver / Imprimir</b> abre el acta A4 con logo corporativo (imprimir o "Guardar como PDF").
      <b>WhatsApp</b> y <b>Correo</b> comparten el enlace público del acta.
    </div>
  </div>
</div>