<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
 $u = Auth::info();
 $puedeAnuncios = Auth::can('configuracion', 'gestionar');
?>
<style>
.dash-card{background:#fff;border:1px solid var(--borde,#e5e7eb);border-left:4px solid var(--c,#2563eb);border-radius:10px;padding:12px 14px;height:100%;transition:box-shadow .15s}
.dash-card.clic{cursor:pointer}.dash-card.clic:hover{box-shadow:0 2px 10px rgba(0,0,0,.1)}
.dash-card .et{font-size:12.5px;color:#6b7280}.dash-card .num{font-size:26px;font-weight:700;line-height:1.15;color:#1e293b}
.dash-card .sub{font-size:12px;color:#6b7280}.dash-card i.ic{font-size:20px;color:var(--c,#2563eb);float:right;opacity:.85}
.dash-card.rojo{--c:#dc2626}.dash-card.rojo .num{color:#b91c1c}.dash-card.ambar{--c:#d97706}.dash-card.verde{--c:#16a34a}.dash-card.gris{--c:#64748b}
.dash-sec{font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin:4px 0 6px}
.dash-al{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;margin-bottom:6px;text-decoration:none;font-size:13.5px;border-left:4px solid #d97706;background:#fffbeb;color:#78350f}
.dash-al.rojo{border-color:#dc2626;background:#fef2f2;color:#7f1d1d}.dash-al:hover{filter:brightness(.97)}
.dash-rap a{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--borde,#e5e7eb);border-radius:20px;background:#fff;color:#1e293b;text-decoration:none;font-size:13px;margin:0 6px 6px 0}
.dash-rap a:hover{background:#eff6ff;border-color:#93c5fd}
</style>
<div class="row g-3">

  <div class="col-12">
    <div class="card-bienvenida">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h4 class="mb-1">Bienvenido, <?= htmlspecialchars($u['nombre']) ?></h4>
          <div class="mb-1">Rol: <span class="badge bg-light text-primary"><?= htmlspecialchars($u['rol']) ?></span></div>
          <div style="font-size:13.5px;opacity:.85" id="dash-fecha">Panel en vivo (se actualiza cada 60 segundos)</div>
        </div>
        <div class="text-end" style="font-size:12px;opacity:.75">
          <div>Actualizado: <span id="dash-actualizado">…</span></div>
          <button id="dash-refrescar" class="btn btn-light btn-sm mt-1" style="opacity:.9">
            <i class="bi bi-arrow-clockwise"></i> Refrescar</button>
          <?php if ($puedeAnuncios): ?>
          <button id="dash-btn-anuncios" class="btn btn-light btn-sm mt-1 d-block ms-auto" style="opacity:.9">
            <i class="bi bi-megaphone"></i> Gestionar anuncios</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== ANUNCIOS ===== -->
  <div class="col-12" id="dash-anuncios-zona"></div>

  <!-- ===== ACCESOS RAPIDOS ===== -->
  <div class="col-12 dash-rap" id="dash-rapidos"></div>

  <!-- ===== INDICADORES ===== -->
  <div class="col-12" id="dash-kpis"><div class="text-muted small">Cargando indicadores…</div></div>

  <!-- ===== OBSERVACIONES + PARA TI ===== -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2"><i class="bi bi-exclamation-diamond text-warning me-1"></i>
        <strong>Requiere atención</strong> <span class="badge text-bg-secondary ms-1" id="dash-al-n">0</span></div>
      <div class="card-body p-2" id="dash-alertas"><div class="text-muted small p-2">Cargando…</div></div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2"><i class="bi bi-person-check text-primary me-1"></i>
        <strong>Para ti</strong>
        <span class="badge text-bg-danger ms-1 d-none" id="pa-sla">0</span></div>
      <div class="card-body p-2" id="pa-cuerpo">Cargando…</div>
    </div>
  </div>

  <!-- ===== GRAFICOS ===== -->
  <div class="col-12 col-lg-7" id="dash-g-tk">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2"><i class="bi bi-graph-up me-1"></i><strong>Tickets — últimos 6 meses</strong></div>
      <div class="card-body p-2"><div style="position:relative;height:230px"><canvas id="dash-tk-mes"></canvas></div></div>
    </div>
  </div>
  <div class="col-12 col-lg-5" id="dash-g-prio">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2"><i class="bi bi-flag me-1"></i><strong>Tickets abiertos por prioridad</strong></div>
      <div class="card-body p-2"><div style="position:relative;height:230px"><canvas id="dash-tk-prio"></canvas></div></div>
    </div>
  </div>
  <div class="col-12 col-lg-5" id="dash-g-eq">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2"><i class="bi bi-pc-display me-1"></i><strong>Equipos por estado</strong></div>
      <div class="card-body p-2"><div style="position:relative;height:230px"><canvas id="dash-eq-estado"></canvas></div></div>
    </div>
  </div>
  <div class="col-12 col-lg-7" id="dash-g-mt">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2"><i class="bi bi-tools me-1"></i><strong>Mantenimientos — últimos 6 meses</strong></div>
      <div class="card-body p-2"><div style="position:relative;height:230px"><canvas id="dash-mt-mes"></canvas></div></div>
    </div>
  </div>

  <!-- ===== Actividad reciente de tickets ===== -->
  <div class="col-12" id="dash-g-act">
    <div class="card shadow-sm">
      <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-activity me-1"></i><strong>Actividad reciente — Tickets</strong></span>
        <button class="btn btn-link btn-sm text-decoration-none py-0" id="dash-ver-tickets">Ver mesa de ayuda <i class="bi bi-arrow-right"></i></button>
      </div>
      <div class="card-body p-2" id="dash-actividad"><div class="text-muted small p-2">Sin actividad aún.</div></div>
    </div>
  </div>

</div>

<script>
(function () {
  var PAL = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#0891b2', '#7c3aed', '#64748b'];
  var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  function mes(ym) { var p = String(ym).split('-'); return (MESES[(+p[1]) - 1] || p[1]) + ' ' + String(p[0]).slice(2); }
  function n(v) { return (Number(v) || 0).toLocaleString('es-PE'); }
  function soles(v) { var x = (Number(v) || 0).toFixed(2).split('.'); return 'S/ ' + x[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + x[1]; }
  function chart(id, cfg) {
    var el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;
    try {
      var viejo = Chart.getChart(el); if (viejo) viejo.destroy();
      cfg.options = $.extend(true, { responsive: true, maintainAspectRatio: false }, cfg.options || {});
      new Chart(el, cfg);
    } catch (_) {}
  }
  function tarjeta(o) {
    var cls = 'dash-card' + (o.ir ? ' clic' : '') + (o.c ? ' ' + o.c : '');
    return '<div class="col-6 col-md-4 col-xl-3"><div class="' + cls + '"' + (o.ir ? ' data-ir="' + esc(o.ir) + '"' : '') + '>' +
      '<i class="bi ' + o.ic + ' ic"></i><div class="et">' + esc(o.t) + '</div><div class="num">' + esc(o.v) + '</div>' +
      (o.sub ? '<div class="sub">' + esc(o.sub) + '</div>' : '') + '</div></div>';
  }
  function seccion(titulo, tarjetas) {
    if (!tarjetas.length) return '';
    return '<div class="dash-sec">' + esc(titulo) + '</div><div class="row g-2 mb-3">' + tarjetas.join('') + '</div>';
  }

  // ---------------------------------------------------------- indicadores
  function cargarIndicadores() {
    Api.get('api/dashboard/indicadores.php').then(function (d) {
      $('#dash-actualizado').text(d.generado);
      var h = '', tk = d.tickets, eq = d.equipos, mt = d.mantenimiento, as = d.asignaciones, ac = d.actas, sw = d.software, pe = d.personal;

      if (tk) {
        var t = [];
        t.push(tarjeta({ t: tk.alcance === 'propios' ? 'Mis tickets abiertos' : 'Tickets abiertos', v: n(tk.abiertos), ic: 'bi-inbox', ir: 'tickets/lista', c: tk.sla_vencidos ? 'rojo' : '' }));
        t.push(tarjeta({ t: 'Con el SLA vencido', v: n(tk.sla_vencidos), ic: 'bi-alarm', c: tk.sla_vencidos ? 'rojo' : 'verde', ir: 'tickets/lista' }));
        if (tk.sin_asignar !== null) t.push(tarjeta({ t: 'Sin técnico asignado', v: n(tk.sin_asignar), ic: 'bi-person-dash', c: tk.sin_asignar ? 'ambar' : 'verde', ir: 'tickets/lista' }));
        t.push(tarjeta({ t: 'Resueltos (30 días)', v: n(tk.resueltos_30), ic: 'bi-check2-circle', c: 'verde', sub: n(tk.creados_30) + ' creados en 30 días' }));
        h += seccion('Mesa de ayuda', t);
      }
      if (eq) {
        var e = [];
        e.push(tarjeta({ t: 'Equipos en inventario', v: n(eq.total), ic: 'bi-pc-display', ir: 'equipos/lista', sub: eq.valorizado !== null ? soles(eq.valorizado) : '' }));
        e.push(tarjeta({ t: 'En stock', v: n(eq.stock), ic: 'bi-box', c: 'verde', ir: 'equipos/lista' }));
        e.push(tarjeta({ t: 'Asignados / en préstamo', v: n(eq.asignados), ic: 'bi-person-check', ir: 'asignaciones/lista' }));
        e.push(tarjeta({ t: 'En mantenimiento o revisión', v: n(eq.mantenimiento), ic: 'bi-tools', c: 'ambar', sub: eq.obsoletos ? eq.obsoletos + ' obsoleto(s)' : '', ir: 'mantenimiento/lista' }));
        h += seccion('Inventario', e);
      }
      var o = [];
      if (mt) {
        o.push(tarjeta({ t: 'Mantenimientos en curso', v: n(mt.activos), ic: 'bi-gear-wide-connected', ir: 'mantenimiento/lista', c: mt.atrasados ? 'rojo' : '', sub: mt.atrasados ? mt.atrasados + ' con retorno atrasado' : (mt.costo_mes !== null ? 'Costo del mes: ' + soles(mt.costo_mes) : '') }));
      }
      if (as) {
        o.push(tarjeta({ t: 'Devoluciones vencidas', v: n(as.vencidas), ic: 'bi-box-arrow-in-left', c: as.vencidas ? 'rojo' : 'verde', ir: 'asignaciones/lista', sub: n(as.activas) + ' equipos entregados' }));
        if (as.solicitudes) o.push(tarjeta({ t: 'Solicitudes de equipo', v: n(as.solicitudes), ic: 'bi-inbox', c: 'ambar', ir: 'asignaciones/lista' }));
      }
      if (ac) o.push(tarjeta({ t: 'Actas por firmar', v: n(ac.pendientes), ic: 'bi-pen', c: ac.pendientes ? 'ambar' : 'verde', ir: 'actas/lista' }));
      h += seccion('Operación', o);
      var otros = [];
      if (sw) otros.push(tarjeta({ t: 'Software: cobertura', v: sw.cobertura + '%', ic: 'bi-app-indicator', c: sw.cobertura < 80 ? 'ambar' : 'verde', ir: 'software/lista', sub: n(sw.instalaciones) + ' instalaciones · ' + sw.alertas + ' observación(es)' }));
      if (pe) otros.push(tarjeta({ t: 'Personal activo', v: n(pe.activos), ic: 'bi-people', ir: 'personal/lista', sub: (pe.por_ingresar ? pe.por_ingresar + ' por ingresar · ' : '') + (pe.ceses_proximos ? pe.ceses_proximos + ' cese(s) próximo(s)' : '') }));
      h += seccion('Control y personas', otros);
      $('#dash-kpis').html(h || '<div class="text-muted small">No hay indicadores para mostrar con sus permisos.</div>');

      // observaciones
      var al = d.alertas || [];
      $('#dash-al-n').text(al.length).toggleClass('text-bg-danger', al.length > 0).toggleClass('text-bg-secondary', !al.length);
      $('#dash-alertas').html(al.length ? al.map(function (a) {
        return '<a href="#" class="dash-al ' + esc(a.nivel) + '" data-ir="' + esc(a.ruta) + '"><i class="bi ' + esc(a.icono || 'bi-exclamation-circle') + '"></i><span class="flex-grow-1">' + esc(a.texto) + '</span><i class="bi bi-chevron-right small"></i></a>';
      }).join('') : '<div class="text-success small p-2"><i class="bi bi-check-circle"></i> Todo en orden: no hay observaciones pendientes.</div>');

      // accesos rapidos
      $('#dash-rapidos').html((d.rapidos || []).map(function (r) { return '<a href="#" data-ir="' + esc(r[0]) + '"><i class="bi ' + esc(r[1]) + '"></i>' + esc(r[2]) + '</a>'; }).join(''));

      // actividad reciente
      $('#dash-g-act').toggle(!!tk);
      var EV = { creado: ['bi-plus-circle', 'Creado'], comentario: ['bi-chat-left-text', 'Comentario'], asignado: ['bi-person-check', 'Asignación'], cambio_estado: ['bi-arrow-repeat', 'Cambio de estado'], actualizado: ['bi-pencil', 'Editado'] };
      var acts = d.actividad || [];
      $('#dash-actividad').html(acts.length ? '<div class="list-group list-group-flush">' + acts.map(function (a) {
        var ic = EV[a.evento] || ['bi-dot', a.evento];
        return '<div class="list-group-item px-2 py-1 border-0 d-flex align-items-start gap-2"><i class="bi ' + ic[0] + ' text-primary mt-1"></i><div class="flex-grow-1" style="min-width:0">' +
          '<span class="small fw-semibold">' + esc(a.codigo) + '</span> <span class="badge text-bg-light border" style="font-size:10px">' + esc(ic[1]) + '</span> ' +
          '<span class="small text-muted text-truncate d-inline-block align-middle" style="max-width:46%" title="' + esc(a.titulo) + '">' + esc(a.titulo) + '</span>' +
          '<div class="small text-muted text-truncate">' + esc(a.detalle || '') + '</div>' +
          '<div class="small" style="font-size:11px;color:#94a3b8">' + esc(a.created_at) + (a.usuario ? ' · ' + esc(a.usuario) : '') + '</div></div></div>';
      }).join('') + '</div>' : '<div class="text-muted small p-2">Sin actividad aún.</div>');
    }).catch(function () { $('#dash-kpis').html('<div class="alert alert-warning mb-0">No se pudieron cargar los indicadores.</div>'); });
  }

  // ---------------------------------------------------------- graficos
  function cargarGraficos() {
    Api.get('api/dashboard/graficos.php').then(function (g) {
      var meses = (g.meses || []).map(mes);
      $('#dash-g-tk,#dash-g-prio').toggle(!!g.tickets);
      $('#dash-g-eq').toggle(!!g.equipos_estado);
      $('#dash-g-mt').toggle(!!g.mantenimiento);
      if (g.tickets) {
        chart('dash-tk-mes', { type: 'line',
          data: { labels: meses, datasets: [
            { label: 'Creados', data: g.tickets.creados, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .3, pointRadius: 3 },
            { label: 'Resueltos', data: g.tickets.resueltos, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.1)', fill: true, tension: .3, pointRadius: 3 }] },
          options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
        var pr = g.tickets.prioridad || [];
        chart('dash-tk-prio', { type: 'bar',
          data: { labels: pr.map(function (p) { return p.nombre; }), datasets: [{ data: pr.map(function (p) { return +p.n; }), backgroundColor: pr.map(function (p, i) { return p.color || PAL[i]; }), borderRadius: 4 }] },
          options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
      }
      if (g.equipos_estado) {
        var EST = { en_stock: 'En stock', asignado: 'Asignado', en_prestamo: 'En préstamo', en_revision: 'En revisión', en_mantenimiento: 'Mantenimiento', en_reparacion_externa: 'Rep. externa', obsoleto: 'Obsoleto' };
        var COL = { en_stock: '#16a34a', asignado: '#2563eb', en_prestamo: '#0891b2', en_revision: '#f59e0b', en_mantenimiento: '#ea580c', en_reparacion_externa: '#dc2626', obsoleto: '#64748b' };
        chart('dash-eq-estado', { type: 'doughnut',
          data: { labels: g.equipos_estado.map(function (e) { return EST[e.estado] || e.estado; }),
                  datasets: [{ data: g.equipos_estado.map(function (e) { return +e.n; }), backgroundColor: g.equipos_estado.map(function (e) { return COL[e.estado] || '#64748b'; }), borderWidth: 1 }] },
          options: { plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } } } });
      }
      if (g.mantenimiento) {
        var ds = [{ type: 'bar', label: 'Servicios', data: g.mantenimiento.cantidad, backgroundColor: '#ea580c', borderRadius: 4, yAxisID: 'y' }];
        var sc = { y: { beginAtZero: true, ticks: { precision: 0 } } };
        if (g.mantenimiento.costo) {
          ds.push({ type: 'line', label: 'Costo (S/)', data: g.mantenimiento.costo, borderColor: '#2563eb', backgroundColor: '#2563eb', tension: .3, yAxisID: 'y1' });
          sc.y1 = { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } };
        }
        chart('dash-mt-mes', { type: 'bar', data: { labels: meses, datasets: ds }, options: { plugins: { legend: { position: 'bottom' } }, scales: sc } });
      }
    }).catch(function () {});
  }

  // ---------------------------------------------------------- PARA TI
  function cargarAtencion() {
    Api.get('api/dashboard/para-atencion.php').then(function (d) {
      var h = '', total = (d.mios || []).length + (d.sin_asignar || []).length + (d.mantenimientos || []).length;
      $('#pa-sla').text(d.sla_vencidos + ' SLA vencido(s)').toggleClass('d-none', !d.sla_vencidos);
      if (d.mios.length) {
        h += '<div class="form-label mb-1 small"><b>Tus tickets</b></div>';
        d.mios.forEach(function (t) {
          h += '<div class="d-flex justify-content-between align-items-center border-bottom py-1">' +
               '<span class="small"><b>' + esc(t.codigo) + '</b> · ' + esc(t.titulo) +
               ' <span class="badge" style="background:' + esc(t.color) + ';color:#fff">' + esc(t.prioridad) + '</span></span>' +
               '<span class="small text-muted">' + esc((t.sla_limite || '').slice(0, 16)) + '</span></div>';
        });
      }
      if (d.sin_asignar.length) {
        h += '<div class="form-label mb-1 small mt-2"><b>Sin asignar</b></div>';
        d.sin_asignar.forEach(function (t) { h += '<div class="small border-bottom py-1"><b>' + esc(t.codigo) + '</b> · ' + esc(t.titulo) + '</div>'; });
      }
      if (d.mantenimientos.length) {
        h += '<div class="form-label mb-1 small mt-2"><b>Mantenimientos activos</b></div>';
        d.mantenimientos.forEach(function (m) {
          h += '<div class="small border-bottom py-1"><b>' + esc(m.codigo) + '</b> · ' + esc(m.equipo + ' ' + m.marca) +
               ' <span class="badge text-bg-warning text-dark">' + esc(m.estado.replace(/_/g, ' ')) + '</span></div>';
        });
      }
      if (!total) h = '<div class="text-success small p-2"><i class="bi bi-check-circle"></i> Nada pendiente para ti.</div>';
      $('#pa-cuerpo').html(h);
    }).catch(function () {});
  }

  // ---------------------------------------------------------- ANUNCIOS (los pinta assets/js/app/anuncios.js)
  function cargarAnuncios() { Anuncios.cargar('#dash-anuncios-zona'); }

  function todo() { cargarIndicadores(); cargarGraficos(); cargarAtencion(); cargarAnuncios(); }
  $('#dash-fecha').text(new Date().toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) + ' · panel en vivo');
  $('#dash-refrescar').on('click', todo);
  $('#dash-ver-tickets').on('click', function () { Router.ir('tickets/lista'); });
  $('#dash-kpis,#dash-alertas,#dash-rapidos').on('click', '[data-ir]', function (e) { e.preventDefault(); Router.ir($(this).attr('data-ir')); });

  todo();
  var timer = setInterval(function () {
    if (!document.getElementById('dash-actualizado')) { clearInterval(timer); return; }
    if (!document.querySelector('.modal.show')) { cargarIndicadores(); cargarAtencion(); cargarAnuncios(); }
  }, 60000);
})();
</script>

<?php if ($puedeAnuncios): ?>
<script>
// ---- GESTIÓN DE ANUNCIOS: generador y lista (assets/js/app/anuncios.js) ----
$('#dash-btn-anuncios').off('click.anun').on('click.anun', function () {
  Anuncios.gestionar(function () {
    Anuncios.cargar('#dash-anuncios-zona');
    if (typeof Notificaciones !== 'undefined' && Notificaciones.refrescar) Notificaciones.refrescar();
  });
});
</script>
<?php endif; ?>
