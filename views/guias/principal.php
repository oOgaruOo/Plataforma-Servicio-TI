<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
?>
<style>
.gd-hero{background:linear-gradient(135deg,#0D2E5C,#1B4F9C);color:#fff;border-radius:14px;padding:22px 24px}
.gd-hero h4{margin:0 0 4px;font-weight:700}
.gd-busca{position:relative;max-width:560px}
.gd-busca i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#64748b}
.gd-busca input{padding-left:40px;border-radius:30px;border:0;height:44px}
.gd-chip{border:1px solid #cbd5e1;background:#fff;border-radius:20px;padding:4px 14px;font-size:.85rem;cursor:pointer;color:#334155}
.gd-chip.activo{background:#0D2E5C;color:#fff;border-color:#0D2E5C}
.gd-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;height:100%;cursor:pointer;transition:.15s;border-top:4px solid var(--gc,#2563eb)}
.gd-card:hover{box-shadow:0 6px 18px rgba(15,23,42,.12);transform:translateY(-2px)}
.gd-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff;background:var(--gc,#2563eb)}
.gd-paso{display:flex;gap:10px;margin:8px 0}
.gd-paso .n{flex:0 0 26px;height:26px;border-radius:50%;background:#0D2E5C;color:#F5C518;font-weight:700;font-size:.8rem;display:flex;align-items:center;justify-content:center;margin-top:1px}
.gd-nota{background:#eff6ff;border-left:4px solid #3b82f6;border-radius:6px;padding:8px 12px;font-size:.9rem;margin-top:8px}
.gd-alerta{background:#fffbeb;border-left:4px solid #f59e0b;border-radius:6px;padding:8px 12px;font-size:.9rem;margin-top:8px}
.gd-indice a{display:block;padding:5px 10px;border-left:3px solid #e2e8f0;color:#334155;text-decoration:none;font-size:.9rem}
.gd-indice a:hover{border-color:#F5C518;background:#f8fafc}
.gd-sec h5{color:#0D2E5C;border-bottom:2px solid #F5C518;padding-bottom:4px;margin-top:22px}
mark.gd-m{background:#fde68a;padding:0 1px;border-radius:2px}
</style>
<div id="gd-raiz">
  <!-- Portada / buscador -->
  <div id="gd-lista">
    <div class="gd-hero mb-3">
      <h4><i class="bi bi-journal-text me-2"></i>Centro de ayuda</h4>
      <div class="mb-3" style="opacity:.85">Aprende a usar cada módulo de SIGTI. Escribe lo que necesitas hacer, por ejemplo: «dar de baja», «licencia» o «devolver equipo».</div>
      <div class="gd-busca"><i class="bi bi-search"></i><input type="search" id="gd-q" class="form-control" placeholder="Buscar en las guías…" autocomplete="off"></div>
    </div>
    <div id="gd-chips" class="d-flex flex-wrap gap-2 mb-3"></div>
    <div id="gd-grid" class="row g-3"></div>
    <div id="gd-vacio" class="text-center text-muted py-5 d-none"><i class="bi bi-search" style="font-size:2rem"></i><div class="mt-2">No encontramos nada con esa búsqueda. Prueba con otra palabra.</div></div>

    <div id="gd-extra" class="row g-3 mt-2">
      <div class="col-lg-7">
        <div class="card shadow-sm h-100"><div class="card-header"><i class="bi bi-patch-question me-1"></i>Preguntas frecuentes</div>
          <div class="card-body p-2"><div class="accordion accordion-flush" id="gd-faq"></div></div></div>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm h-100"><div class="card-header"><i class="bi bi-book me-1"></i>Glosario</div>
          <div class="card-body"><dl class="mb-0" id="gd-glosario"></dl></div></div>
      </div>
    </div>
  </div>

  <!-- Detalle de una guía -->
  <div id="gd-detalle" class="d-none"></div>
</div>
