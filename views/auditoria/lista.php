<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
$puede = Auth::can('auditoria', 'ver');
$puedeArea = $puede || Auth::can('auditoria', 'ver_area');
if (!$puedeArea):
?>
  <div class="alert alert-warning mb-0"><i class="bi bi-shield-lock"></i> Sin permisos de auditoría.</div>
<?php else:
$etq = AuditoriaConsulta::ACCIONES;
?>
<style>
  .au-card{cursor:pointer;transition:box-shadow .15s,transform .15s}
  .au-card:hover{box-shadow:0 .25rem .6rem rgba(0,0,0,.12);transform:translateY(-1px)}
  .au-card.activa{outline:2px solid #2563eb}
  #au-serie{display:flex;align-items:flex-end;gap:3px;height:54px}
  #au-serie .b{flex:1;background:#93b4f5;border-radius:2px 2px 0 0;min-height:2px;cursor:pointer}
  #au-serie .b:hover{background:#2563eb}
  #au-serie .b.hoy{background:#2563eb}
  #tb-aud tbody tr.au-mal td{background:#fef2f2}
  .au-df td{vertical-align:top}
  .au-df .antes{background:#fef2f2;color:#991b1b}
  .au-df .desp{background:#f0fdf4;color:#166534}
  .au-df pre{margin:0;white-space:pre-wrap;word-break:break-word;font-size:.78rem;background:transparent;border:0;padding:0}
</style>
<div id="au-raiz">
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-4 col-xl"><div class="mini-stat au-card" data-card="hoy"><div class="n" data-k="hoy">-</div><div class="t">Eventos hoy</div></div></div>
    <div class="col-6 col-md-4 col-xl"><div class="mini-stat" title="Personas distintas con actividad hoy"><div class="n" data-k="usuarios_hoy">-</div><div class="t">Usuarios activos hoy</div></div></div>
    <div class="col-6 col-md-4 col-xl"><div class="mini-stat au-card" data-card="fallidos"><div class="n" data-k="fallidos_24h" style="color:#dc2626">-</div><div class="t">Inicios fallidos (24 h)</div></div></div>
    <div class="col-6 col-md-4 col-xl"><div class="mini-stat au-card" data-card="denegados"><div class="n" data-k="denegados_24h" style="color:#d97706">-</div><div class="t">Accesos denegados (24 h)</div></div></div>
    <div class="col-6 col-md-4 col-xl"><div class="mini-stat au-card" data-card="seguridad"><div class="n" data-k="seguridad_7d" style="color:#7c3aed">-</div><div class="t">Cambios de seguridad (7 d)</div></div></div>
    <div class="col-12 col-xl-3"><div class="mini-stat py-2"><div class="t mb-1">Actividad · últimos 14 días</div><div id="au-serie"></div></div></div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="bi bi-journal-text me-1"></i>Auditoría del sistema</span>
      <div class="d-flex gap-2 align-items-center">
        <span class="small text-muted d-none d-md-inline" id="au-contador"></span>
        <button id="btn-exportar-auditoria" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Exportar CSV</button>
      </div>
    </div>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <div class="btn-group btn-group-sm" id="au-rangos" role="group" aria-label="Rango">
          <button type="button" class="btn btn-outline-primary" data-r="hoy">Hoy</button>
          <button type="button" class="btn btn-outline-primary" data-r="7">7 días</button>
          <button type="button" class="btn btn-outline-primary" data-r="30">30 días</button>
          <button type="button" class="btn btn-outline-primary active" data-r="todo">Todo</button>
        </div>
        <div class="form-check form-switch mb-0 ms-1">
          <input class="form-check-input" type="checkbox" id="auf-seguridad">
          <label class="form-check-label small" for="auf-seguridad">Solo seguridad <span class="text-muted">(inicios fallidos, denegados, usuarios y permisos)</span></label>
        </div>
        <span class="badge text-bg-info d-none" id="au-objeto"></span>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-2">
          <select id="auf-usuario" class="form-select form-select-sm">
            <option value="">Todos los usuarios</option>
            <?php
            $sqlUsu = "SELECT id, nombre_completo FROM usuarios ORDER BY nombre_completo";
            $pUsu = [];
            if (!$puede) {   // ver_area: solo personas de su propia area en el filtro
                $sqlUsu = "SELECT u.id, u.nombre_completo FROM usuarios u INNER JOIN personal p ON p.id = u.personal_id
                           WHERE p.area_id = ? ORDER BY u.nombre_completo";
                $pUsu = [AuditoriaConsulta::alcance()['area_id'] ?? 0];
            }
            foreach (Database::get($sqlUsu, $pUsu) as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nombre_completo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="auf-modulo" class="form-select form-select-sm">
            <option value="">Todos los módulos</option>
            <?php foreach (Database::get("SELECT DISTINCT modulo FROM auditoria_logs WHERE modulo IS NOT NULL ORDER BY modulo") as $m): ?>
              <option value="<?= htmlspecialchars($m['modulo']) ?>"><?= htmlspecialchars($m['modulo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="auf-accion" class="form-select form-select-sm">
            <option value="">Todas las acciones</option>
            <?php foreach (Database::get("SELECT DISTINCT accion FROM auditoria_logs ORDER BY accion") as $a): ?>
              <option value="<?= htmlspecialchars($a['accion']) ?>"><?= htmlspecialchars($etq[$a['accion']] ?? $a['accion']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="auf-resultado" class="form-select form-select-sm">
            <option value="">Todos los resultados</option>
            <?php foreach (Database::get("SELECT DISTINCT resultado FROM auditoria_logs WHERE resultado IS NOT NULL ORDER BY resultado") as $r): ?>
              <option value="<?= htmlspecialchars($r['resultado']) ?>"><?= htmlspecialchars($r['resultado']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="auf-tabla" class="form-select form-select-sm">
            <option value="">Todos los objetos</option>
            <?php foreach (Database::get("SELECT DISTINCT tabla FROM auditoria_logs WHERE tabla IS NOT NULL ORDER BY tabla") as $t): ?>
              <option value="<?= htmlspecialchars($t['tabla']) ?>"><?= htmlspecialchars($t['tabla']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-1">
          <input type="date" id="auf-desde" class="form-control form-control-sm" title="Desde">
          <input type="date" id="auf-hasta" class="form-control form-control-sm" title="Hasta">
        </div>
        <div class="col-8 col-md-4">
          <input type="text" id="auf-ubicacion" class="form-control form-control-sm" placeholder="Ubicación (ej: Lima, Perú)...">
        </div>
        <div class="col-4 col-md-2 d-flex gap-1">
          <button id="btn-filtrar-aud" class="btn btn-primary btn-sm flex-fill" title="Aplicar"><i class="bi bi-funnel"></i></button>
          <button id="btn-limpiar-aud" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros"><i class="bi bi-x-lg"></i></button>
        </div>
      </div>

      <table id="tb-aud" class="table table-bordered table-hover align-middle w-100">
        <thead>
          <tr>
            <th>Fecha</th><th>Usuario</th><th>Rol (captura)</th><th>Módulo</th>
            <th>Acción</th><th>Objeto</th><th>Resultado</th><th>IP observada</th>
            <th>Ubicación estimada</th><th class="text-center">Detalle</th>
          </tr>
        </thead>
      </table>

      <div class="text-muted small mt-2">
        <i class="bi bi-globe"></i> La geolocalización por IP, cuando está disponible, es aproximada y no representa
        la ubicación física exacta del dispositivo. La IP observada corresponde a la conexión que recibió el servidor;
        los encabezados de proxy no confiables no se presentan como IP real. Las filas en rojo son inicios fallidos o accesos denegados.
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
