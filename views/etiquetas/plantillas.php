<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
 $puede = Auth::can('configuracion', 'gestionar');
if (!$puede):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> Sin permisos para diseñar etiquetas.</div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-tags me-1"></i>Diseñador de etiquetas — plantillas personalizadas</span>
    <button id="btn-nueva-plantilla" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nueva plantilla</button>
  </div>
  <div class="card-body">
    <div id="etq-filtros" class="d-flex gap-2 flex-wrap mb-3"></div>
    <div id="lista-plantillas" class="row g-3">Cargando...</div>
    <div class="text-muted small mt-3">
      <i class="bi bi-info-circle"></i>
      Diseñe etiquetas para <b>equipos</b>, para el <b>personal</b> (con su código de usuario) y para sus <b>lockers</b>.
      Elija «Para» al crear la plantilla: cada tipo tiene sus variables (se muestran en el editor), por ejemplo
      <code>{codigo_usuario}</code> <code>{nombre_completo}</code> <code>{area}</code> <code>{locker}</code> para personal y lockers, o
      <code>{codigo}</code> <code>{serie}</code> <code>{tipo}</code> para equipos.
      Se imprimen desde <b>Personal › Etiquetas</b> o con el botón de etiqueta de cada persona.
      La impresión usa el <b>diálogo del sistema</b> que detecta todas sus impresoras (XP-237B incluida).
    </div>
  </div>
</div>
<?php endif; ?>