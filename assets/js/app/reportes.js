/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Reportes
   Pestañas: Resumen, Tickets, Mantenimiento, Inventario, Software, Asignaciones y actas, Personal.
   Los datos vienen de api/reportes/datos.php (calculados en core/Reportes.php).
   ============================================================ */
var RP_PAL = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#0891b2', '#7c3aed', '#db2777', '#64748b', '#84cc16', '#ea580c'];
var RP_EST_TK = { abierto: 'Abierto', asignado: 'Asignado', en_atencion: 'En atención', pendiente_usuario: 'Pend. usuario', pendiente_proveedor: 'Pend. proveedor', resuelto: 'Resuelto', cerrado: 'Cerrado', cancelado: 'Cancelado' };
var RP_EST_MT = { solicitado: 'Solicitado', en_evaluacion: 'En evaluación', en_proceso: 'En proceso', enviado_proveedor: 'En proveedor', cotizado: 'Cotizado', aprobado: 'Aprobado', rechazado: 'Rechazado', en_reparacion: 'En reparación', recibido_reparado: 'Recibido', listo: 'Listo', entregado: 'Entregado', devuelto_stock: 'A stock', cerrado: 'Cerrado', cancelado: 'Cancelado', no_reparable: 'No reparable' };
var RP_EST_EQ = { en_stock: 'En stock', asignado: 'Asignado', en_prestamo: 'En préstamo', en_revision: 'En revisión', en_mantenimiento: 'En mantenimiento', en_reparacion_externa: 'Rep. externa', obsoleto: 'Obsoleto', dado_de_baja: 'De baja' };
var RP_COLOR_EQ = { en_stock: '#16a34a', asignado: '#2563eb', en_prestamo: '#0891b2', en_revision: '#f59e0b', en_mantenimiento: '#ea580c', en_reparacion_externa: '#dc2626', obsoleto: '#64748b' };
var RP_TDEST = { persona: 'Personas', area: 'Áreas', proyecto: 'Proyectos', obra: 'Obras' };
var RP_COND = { nuevo: 'Nuevo', bueno: 'Bueno', regular: 'Regular', danado: 'Dañado', irreparable: 'Irreparable' };
var RP_EST_PE = { activo: 'Activo', pre_ingreso: 'Por ingresar', cese_programado: 'Cese programado', en_proceso_cese: 'En proceso de cese', cesado: 'Cesado' };
var RP_TIPO_MT = { preventivo: 'Preventivo', correctivo: 'Correctivo', predictivo: 'Predictivo' };
var RP_MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

var Reportes = {
  charts: {}, _m: 'resumen', _datos: {}, _ultimo: {},

  /* ---------------------------------------------------------- utilidades */
  _f: function (d) { var m = d.getMonth() + 1, dd = d.getDate(); return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (dd < 10 ? '0' : '') + dd; },
  _mes: function (ym) { var p = String(ym).split('-'); return (RP_MESES[(+p[1]) - 1] || p[1]) + ' ' + String(p[0]).slice(2); },
  _n: function (v) { return (Number(v) || 0).toLocaleString('es-PE'); },
  _s: function (v) {
    var x = (Number(v) || 0).toFixed(2).split('.');
    return 'S/ ' + x[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + x[1];
  },
  _fecha: function (f) { var s = String(f || '').slice(0, 10); return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s.slice(8, 10) + '/' + s.slice(5, 7) + '/' + s.slice(0, 4) : (f || '—'); },
  _tx: function (map, k) { return map[k] || String(k || '').replace(/_/g, ' '); },
  _var: function (v, maloSiSube) {
    if (v === null || v === undefined) return '';
    var cls = v === 0 ? 'text-muted' : ((v > 0) === !!maloSiSube ? 'text-danger' : 'text-success');
    return '<span class="small ' + cls + '" title="Comparado con el período anterior de la misma duración">' + (v > 0 ? '▲ +' : (v < 0 ? '▼ ' : '= ')) + v + '%</span>';
  },

  /* ---------------------------------------------------------- init */
  init: function () {
    var s = this, $r = $('#rp-raiz');
    if (!$r.length) return;
    s.charts = {}; s._datos = {};
    var hoy = new Date();
    $('#rp-desde').val(s._f(new Date(hoy.getFullYear(), hoy.getMonth() - 5, 1)));
    $('#rp-hasta').val(s._f(hoy));
    if (+$r.attr('data-solo-area') === 1) $('#rp-aviso-area').removeClass('d-none');

    $('#rp-aplicar').off('click.rp').on('click.rp', function () { s._cargar(s._m, true); });
    $('#rp-desde,#rp-hasta').off('keydown.rp').on('keydown.rp', function (e) { if (e.which === 13) s._cargar(s._m, true); });
    $('#rp-rapidos').off('click.rp').on('click.rp', 'button', function () {
      var h = new Date(), d = new Date(), k = $(this).attr('data-r');
      if (k === 'mes') d = new Date(h.getFullYear(), h.getMonth(), 1);
      else if (k === '30') d.setDate(h.getDate() - 29);
      else if (k === 'trim') d = new Date(h.getFullYear(), h.getMonth() - 2, 1);
      else if (k === 'sem') d = new Date(h.getFullYear(), h.getMonth() - 5, 1);
      else if (k === 'anio') d = new Date(h.getFullYear(), 0, 1);
      $('#rp-desde').val(s._f(d)); $('#rp-hasta').val(s._f(h));
      s._cargar(s._m, true);
    });
    $('#rp-tabs').off('shown.bs.tab.rp').on('shown.bs.tab.rp', 'button', function () {
      var m = $(this).attr('data-m'); if (m) s._cargar(m, false);
    });
    $('#rp-imprimir').off('click.rp').on('click.rp', function () {
      window.open(BASE_URL + 'views/reportes/informe.php?' + $.param({ modulo: s._m, desde: $('#rp-desde').val(), hasta: $('#rp-hasta').val() }), '_blank');
    });
    $('#rp-dd-descarga').off('click.rp').on('click.rp', '.rp-desc', function (e) { e.preventDefault(); s._descargar($(this).attr('data-f')); });
    // buscador dentro de las tablas de detalle
    $('#rp-raiz').off('input.rp').on('input.rp', '.rp-filtro', function () {
      var q = $.trim($(this).val()).toLowerCase(), $t = $('#' + $(this).attr('data-t')), n = 0, tot = 0;
      $t.find('tbody tr').each(function () {
        var ok = !q || $(this).text().toLowerCase().indexOf(q) >= 0;
        $(this).toggle(ok); tot++; if (ok) n++;
      });
      $('#' + $(this).attr('data-t') + '-n').text(q ? (n + ' de ' + tot) : (tot + ''));
    });
    $('#rp-raiz').off('click.rp').on('click.rp', '[data-ir]', function (e) { e.preventDefault(); Router.ir($(this).attr('data-ir')); });

    var primero = $('#rp-tabs button').first().attr('data-m') || 'resumen';
    s._m = primero;
    s._cargar(primero, true);
  },

  /* ---------------------------------------------------------- carga */
  _cargar: function (m, forzar) {
    var s = this;
    s._m = m;
    $('#rp-dd-descarga').toggle(m !== 'resumen');
    if (!forzar && s._datos[m] && s._ultimo[m] === $('#rp-desde').val() + '|' + $('#rp-hasta').val()) { s._pie(s._datos[m]); return; }
    var clave = $('#rp-desde').val() + '|' + $('#rp-hasta').val();
    Api.get('api/reportes/datos.php', { modulo: m, desde: $('#rp-desde').val() || '', hasta: $('#rp-hasta').val() || '' }).then(function (d) {
      s._datos[m] = d; s._ultimo[m] = clave;
      s._limpiar(m);
      $('#rp-p-' + m).html(s['_p_' + m](d));
      if (s['_g_' + m]) s['_g_' + m](d);
      s._pie(d);
    }).catch(function () { $('#rp-p-' + m).html('<div class="alert alert-warning">No se pudo cargar este reporte.</div>'); });
  },
  _pie: function (d) { $('#rp-generado').text(d && d.generado ? 'Datos al ' + d.generado : ''); },
  _limpiar: function (m) {
    var s = this;
    Object.keys(s.charts).forEach(function (id) {
      if (id.indexOf('rp-' + m + '-') === 0) { try { s.charts[id].destroy(); } catch (_) { } delete s.charts[id]; }
    });
  },

  /* ---------------------------------------------------------- piezas de pantalla */
  _tarjetas: function (items) {
    var h = '<div class="row g-2 mb-3">';
    items.forEach(function (i) {
      if (!i) return;
      var col = i.c === 'danger' ? '#dc2626' : (i.c === 'warning' ? '#d97706' : (i.c === 'success' ? '#16a34a' : '#1e293b'));
      var ir = i.ir ? ' data-ir="' + esc(i.ir) + '" style="cursor:pointer"' : '';
      h += '<div class="col-6 col-md-3 col-xl"><div class="border rounded p-2 h-100 bg-white rp-kpi"' + ir + '>' +
        '<div class="small text-muted"><i class="bi ' + (i.ic || 'bi-circle') + ' me-1"></i>' + esc(i.t) + '</div>' +
        '<div class="fs-4 fw-bold" style="color:' + col + ';line-height:1.2">' + (i.raw ? i.v : esc(i.v)) + '</div>' +
        (i.sub ? '<div class="small">' + i.sub + '</div>' : '') + '</div></div>';
    });
    return h + '</div>';
  },
  _caja: function (titulo, cuerpo, ancho) {
    return '<div class="col-12 col-lg-' + (ancho || 6) + '"><div class="border rounded p-2 h-100 bg-white">' +
      (titulo ? '<div class="small fw-semibold mb-1">' + titulo + '</div>' : '') + cuerpo + '</div></div>';
  },
  _lienzo: function (m, nombre, alto) { return '<div style="position:relative;height:' + (alto || 230) + 'px"><canvas id="rp-' + m + '-' + nombre + '"></canvas></div>'; },
  _tabla: function (cab, filas, vacio, id) {
    var h = '<div class="table-responsive" style="max-height:340px;overflow:auto"><table class="table table-sm table-bordered mb-0" style="font-size:12px"' + (id ? ' id="' + id + '"' : '') + '><thead class="table-light"><tr>';
    cab.forEach(function (c) { h += '<th class="' + (c[1] || '') + '">' + esc(c[0]) + '</th>'; });
    h += '</tr></thead><tbody>';
    filas.forEach(function (f) {
      h += '<tr>';
      f.forEach(function (v, i) {
        var cls = (cab[i] && cab[i][1]) || '', html = false;
        if (v && typeof v === 'object') { cls += ' ' + (v.c || ''); html = !!v.h; v = v.v; }
        h += '<td class="' + cls + '">' + (html ? v : esc(v === null || v === undefined || v === '' ? '—' : v)) + '</td>';
      });
      h += '</tr>';
    });
    if (!filas.length) h += '<tr><td colspan="' + cab.length + '" class="text-muted text-center">' + esc(vacio || 'Sin datos en el período.') + '</td></tr>';
    return h + '</tbody></table></div>';
  },
  /** Tabla grande con buscador y contador (detalle del período). */
  _detalle: function (id, titulo, cab, filas, total) {
    var h = '<div class="border rounded p-2 bg-white mt-3"><div class="d-flex flex-wrap gap-2 align-items-center mb-1">' +
      '<b class="small"><i class="bi bi-table"></i> ' + esc(titulo) + '</b> <span class="badge text-bg-light border" id="' + id + '-n">' + filas.length + '</span>' +
      '<input type="search" class="form-control form-control-sm rp-filtro ms-auto" style="max-width:240px" data-t="' + id + '" placeholder="Buscar en la tabla…" aria-label="Buscar en la tabla"></div>';
    h += this._tabla(cab, filas, 'Sin registros en el rango seleccionado.', id).replace('max-height:340px', 'max-height:420px');
    if (total && total > filas.length) h += '<div class="small text-muted mt-1">Se muestran los ' + filas.length + ' más recientes de ' + total + '. Con «Descargar» obtiene todos.</div>';
    return h + '</div>';
  },
  _filasObs: function (alertas) {
    if (!alertas || !alertas.length) return '<div class="text-success small"><i class="bi bi-check-circle"></i> Sin observaciones pendientes. Todo en regla.</div>';
    return alertas.map(function (a) {
      return '<a href="#" data-ir="' + esc(a.ruta) + '" class="d-block text-decoration-none small py-1 px-2 mb-1 rounded" style="border-left:4px solid ' +
        (a.nivel === 'rojo' ? '#dc2626;background:#fef2f2;color:#7f1d1d' : '#d97706;background:#fffbeb;color:#78350f') + '">' + esc(a.texto) + ' <i class="bi bi-arrow-right-short"></i></a>';
    }).join('');
  },

  /* ---------------------------------------------------------- gráficos */
  _chart: function (m, nombre, cfg) {
    var id = 'rp-' + m + '-' + nombre, el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;
    if (this.charts[id]) { try { this.charts[id].destroy(); } catch (_) { } }
    cfg.options = $.extend(true, { responsive: true, maintainAspectRatio: false }, cfg.options || {});
    try { this.charts[id] = new Chart(el, cfg); } catch (_) { }
  },
  _linea: function (m, nombre, etiquetas, series) {
    var s = this;
    s._chart(m, nombre, { type: 'line', data: { labels: etiquetas.map(s._mes), datasets: series.map(function (x, i) {
      var c = x.color || RP_PAL[i];
      return { label: x.label, data: x.data, borderColor: c, backgroundColor: c + '22', fill: i === 0, tension: .3, pointRadius: 3 };
    }) }, options: { plugins: { legend: { display: series.length > 1, position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
  },
  _barras: function (m, nombre, etiquetas, valores, o) {
    o = o || {};
    var colores = o.colores || (o.colorUno ? o.colorUno : RP_PAL[0]);
    this._chart(m, nombre, { type: 'bar', data: { labels: etiquetas, datasets: [{ label: o.label || '', data: valores, backgroundColor: colores, borderRadius: 3 }] },
      options: { indexAxis: o.horizontal ? 'y' : 'x', plugins: { legend: { display: false },
        tooltip: o.dinero ? { callbacks: { label: function (c) { return Reportes._s(c.parsed[o.horizontal ? 'x' : 'y']); } } } : {} },
        scales: o.horizontal ? { x: { beginAtZero: true, ticks: { precision: 0 } } } : { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
  },
  _barrasVarias: function (m, nombre, etiquetas, series) {
    this._chart(m, nombre, { type: 'bar', data: { labels: etiquetas, datasets: series.map(function (x, i) {
      return { label: x.label, data: x.data, backgroundColor: x.color || RP_PAL[i], borderRadius: 3 };
    }) }, options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
  },
  _dona: function (m, nombre, etiquetas, valores, colores) {
    this._chart(m, nombre, { type: 'doughnut', data: { labels: etiquetas, datasets: [{ data: valores, backgroundColor: colores || RP_PAL, borderWidth: 1 }] },
      options: { plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } } } });
  },
  _col: function (arr, k) { return (arr || []).map(function (x) { return x[k]; }); },
  _num: function (arr, k) { return (arr || []).map(function (x) { return +x[k]; }); },

  /* ---------------------------------------------------------- RESUMEN */
  _p_resumen: function (d) {
    var s = this, t = d.tickets.tarjetas, m = d.mantenimiento && d.mantenimiento.tarjetas, i = d.inventario && d.inventario.tarjetas,
      w = d.software && d.software.tarjetas, a = d.asignaciones && d.asignaciones.tarjetas, p = d.personal.tarjetas;
    var h = '<div class="small text-muted mb-2">Del ' + s._fecha(d.rango.desde) + ' al ' + s._fecha(d.rango.hasta) + ' (' + d.rango.dias + ' días). Las cifras «ahora» son de este momento.</div>';
    h += s._tarjetas([
      { t: 'Tickets creados', v: s._n(t.total), ic: 'bi-life-preserver', sub: s._var(t.variacion, true), ir: 'tickets/lista' },
      { t: 'Cumplimiento del SLA', v: t.sla_pct === null ? '—' : t.sla_pct + '%', ic: 'bi-stopwatch', c: t.sla_pct !== null && t.sla_pct < 80 ? 'warning' : 'success', sub: t.prom_resolucion_h !== null ? 'Resolución promedio: ' + t.prom_resolucion_h + ' h' : '' },
      { t: 'Abiertos ahora', v: t.abiertos_ahora, ic: 'bi-inbox', c: t.sla_vencidos_ahora ? 'danger' : '', sub: t.sin_asignar + ' sin asignar · ' + t.sla_vencidos_ahora + ' con SLA vencido', ir: 'tickets/lista' },
      m ? { t: 'Costo de mantenimiento', v: s._s(m.costo_total), ic: 'bi-tools', sub: s._var(m.variacion_costo, true) + ' ' + m.total + ' servicios', ir: 'mantenimiento/lista' } : null,
      p ? { t: 'Personal activo', v: p.activos, ic: 'bi-people', sub: p.sin_equipo + ' sin equipo asignado' } : null
    ]);
    if (i) {
      h += s._tarjetas([
        { t: 'Equipos en inventario', v: s._n(i.total), ic: 'bi-pc-display', sub: i.stock + ' en stock · ' + i.asignados + ' asignados', ir: 'equipos/lista' },
        { t: 'Valor del inventario', v: s._s(i.valorizado), ic: 'bi-cash-coin' },
        { t: 'Software: cobertura', v: w.cobertura + '%', ic: 'bi-app-indicator', c: w.cobertura < 80 ? 'warning' : 'success', sub: w.instalaciones + ' instalaciones registradas', ir: 'software/lista' },
        { t: 'Entregas vigentes', v: a.activas, ic: 'bi-box-seam', c: a.vencidas ? 'danger' : '', sub: a.vencidas + ' devolución(es) vencida(s)', ir: 'asignaciones/lista' },
        { t: 'Actas por firmar', v: a.actas_pendientes, ic: 'bi-file-earmark-text', c: a.actas_pendientes ? 'warning' : 'success', ir: 'actas/lista' }
      ]);
    }
    h += '<div class="row g-3">' +
      s._caja('<i class="bi bi-exclamation-diamond text-warning"></i> Observaciones para atender', s._filasObs(d.alertas), i ? 5 : 12) +
      s._caja('Tickets creados y resueltos', s._lienzo('resumen', 'tk', 220), i ? 7 : 12);
    if (m) {
      h += s._caja('Costo de mantenimiento por mes', s._lienzo('resumen', 'mt', 220), 4) +
        s._caja('Equipos por estado', s._lienzo('resumen', 'eq', 220), 4) +
        s._caja('Entregas y devoluciones por mes', s._lienzo('resumen', 'as', 220), 4);
    }
    return h + '</div>' + '<div class="text-end mt-2"><button class="btn btn-sm btn-outline-primary" onclick="$(\'#rp-imprimir\').click()"><i class="bi bi-printer"></i> Informe para gerencia (imprimir / PDF)</button></div>';
  },
  _g_resumen: function (d) {
    var s = this;
    s._linea('resumen', 'tk', d.meses, [{ label: 'Creados', data: d.tickets.creados }, { label: 'Resueltos', data: d.tickets.resueltos, color: '#16a34a' }]);
    if (d.mantenimiento) {
      s._barras('resumen', 'mt', d.meses.map(s._mes), d.mantenimiento.costos, { dinero: true, colorUno: '#ea580c' });
      s._dona('resumen', 'eq', d.inventario.estados.map(function (e) { return s._tx(RP_EST_EQ, e.estado); }), s._num(d.inventario.estados, 'n'),
        d.inventario.estados.map(function (e) { return RP_COLOR_EQ[e.estado] || '#64748b'; }));
      s._barrasVarias('resumen', 'as', d.meses.map(s._mes), [{ label: 'Entregas', data: d.asignaciones.entregas }, { label: 'Devoluciones', data: d.asignaciones.devoluciones, color: '#f59e0b' }]);
    }
  },

  /* ---------------------------------------------------------- TICKETS */
  _p_tickets: function (d) {
    var s = this, t = d.tarjetas;
    var h = s._tarjetas([
      { t: 'Tickets creados', v: s._n(t.total), ic: 'bi-life-preserver', sub: s._var(t.variacion, true) },
      { t: 'Resueltos', v: s._n(t.resueltos), ic: 'bi-check2-circle', c: 'success' },
      { t: 'Cumplimiento del SLA', v: t.sla_pct === null ? '—' : t.sla_pct + '%', ic: 'bi-stopwatch', c: t.sla_pct !== null && t.sla_pct < 80 ? 'warning' : 'success', sub: t.sla_ok + ' dentro del plazo' },
      { t: 'Resolución promedio', v: t.prom_resolucion_h === null ? '—' : t.prom_resolucion_h + ' h', ic: 'bi-hourglass-split' },
      { t: '1ra respuesta promedio', v: t.prom_respuesta_h === null ? '—' : t.prom_respuesta_h + ' h', ic: 'bi-reply' }
    ]) + s._tarjetas([
      { t: 'Abiertos ahora', v: t.abiertos_ahora, ic: 'bi-inbox', ir: 'tickets/lista' },
      { t: 'Sin asignar ahora', v: t.sin_asignar, ic: 'bi-person-dash', c: t.sin_asignar ? 'warning' : '' },
      { t: 'SLA vencidos ahora', v: t.sla_vencidos_ahora, ic: 'bi-alarm', c: t.sla_vencidos_ahora ? 'danger' : 'success' },
      { t: 'Reabiertos', v: t.reabiertos, ic: 'bi-arrow-counterclockwise', c: t.reabiertos ? 'warning' : '' }
    ]);
    h += '<div class="row g-3">' +
      s._caja('Tickets creados y resueltos por mes', s._lienzo('tickets', 'mes', 240), 7) +
      s._caja('Por estado', s._lienzo('tickets', 'estado', 240), 5) +
      s._caja('Por prioridad', s._lienzo('tickets', 'prio', 240), 5) +
      s._caja('Atención por técnico', s._lienzo('tickets', 'tec', 240), 7) +
      s._caja('Categorías más frecuentes', s._lienzo('tickets', 'cat', 240), 6) +
      s._caja('Áreas con más tickets', s._lienzo('tickets', 'area', 240), 6) +
      s._caja('<i class="bi bi-activity"></i> Actividad por usuario', s._tabla([['Usuario'], ['Comentarios', 'text-center'], ['Movimientos', 'text-center'], ['Total', 'text-center']],
        (d.actividad_usuarios || []).map(function (u) { return [u.usuario, u.comentarios, u.movimientos, { v: u.total, c: 'fw-semibold' }]; })), 6) +
      s._caja('<i class="bi bi-person-raised-hand"></i> Tickets por solicitante', s._tabla([['Solicitante'], ['Área'], ['Tickets', 'text-center'], ['Resueltos', 'text-center']],
        (d.solicitantes || []).map(function (u) { return [u.solicitante, u.area, { v: u.tickets, c: 'fw-semibold' }, u.resueltos]; })), 6) +
      '</div>';
    h += s._detalle('rp-tk-det', 'Detalle de tickets del período', [['Código'], ['Título'], ['Solicitante'], ['Área'], ['Categoría'], ['Prioridad'], ['Técnico'], ['Estado'], ['Creado'], ['Resuelto'], ['SLA'], ['💬', 'text-center']],
      (d.detalle || []).map(function (x) {
        return [{ v: x.codigo, c: 'fw-semibold' }, x.titulo, x.solicitante, x.area, x.categoria, x.prioridad, x.tecnico, s._tx(RP_EST_TK, x.estado),
          String(x.fecha_creacion).slice(5, 16), String(x.fecha_resolucion).slice(5, 16), { v: x.sla_estado, c: String(x.sla_estado).indexOf('vencido') >= 0 ? 'text-danger fw-semibold' : '' }, { v: x.comentarios, c: 'text-center' }];
      }), d.detalle_total);
    return h;
  },
  _g_tickets: function (d) {
    var s = this;
    s._linea('tickets', 'mes', d.meses, [{ label: 'Creados', data: d.creados }, { label: 'Resueltos', data: d.resueltos, color: '#16a34a' }]);
    s._dona('tickets', 'estado', d.estados.map(function (e) { return s._tx(RP_EST_TK, e.estado); }), s._num(d.estados, 'n'));
    s._barras('tickets', 'prio', s._col(d.prioridades, 'nombre'), s._num(d.prioridades, 'n'), { colores: d.prioridades.map(function (p, i) { return p.color || RP_PAL[i]; }) });
    s._barrasVarias('tickets', 'tec', s._col(d.tecnicos, 'tecnico'), [{ label: 'Asignados', data: s._num(d.tecnicos, 'asignados') }, { label: 'Resueltos', data: s._num(d.tecnicos, 'resueltos'), color: '#16a34a' }]);
    s._barras('tickets', 'cat', s._col(d.categorias, 'nombre'), s._num(d.categorias, 'n'), { horizontal: true });
    s._barras('tickets', 'area', s._col(d.areas, 'nombre'), s._num(d.areas, 'n'), { horizontal: true, colorUno: '#0891b2' });
  },

  /* ---------------------------------------------------------- MANTENIMIENTO */
  _p_mantenimiento: function (d) {
    var s = this, t = d.tarjetas;
    var h = s._tarjetas([
      { t: 'Costo total', v: s._s(t.costo_total), ic: 'bi-cash-coin', sub: s._var(t.variacion_costo, true) },
      { t: 'Mantenimientos', v: t.total, ic: 'bi-tools' },
      { t: 'Costo promedio', v: s._s(t.costo_promedio), ic: 'bi-calculator' },
      { t: 'No reparables', v: t.no_reparables, ic: 'bi-x-octagon', c: t.no_reparables ? 'warning' : '' }
    ]) + s._tarjetas([
      { t: 'En curso ahora', v: t.activos, ic: 'bi-gear-wide-connected', ir: 'mantenimiento/lista' },
      { t: 'Retorno atrasado', v: t.atrasados, ic: 'bi-alarm', c: t.atrasados ? 'danger' : 'success' },
      { t: 'Preventivos vencidos', v: t.preventivos_vencidos, ic: 'bi-calendar-x', c: t.preventivos_vencidos ? 'warning' : 'success' }
    ]);
    h += '<div class="row g-3">' +
      s._caja('Costo por mes', s._lienzo('mantenimiento', 'costo', 240), 7) +
      s._caja('Por estado', s._lienzo('mantenimiento', 'estado', 240), 5) +
      s._caja('Costo por tipo de equipo', s._lienzo('mantenimiento', 'tipoeq', 240), 6) +
      s._caja('Proveedores (costo)', s._lienzo('mantenimiento', 'prov', 240), 6) +
      s._caja('Equipos con más mantenimientos', s._tabla([['Equipo'], ['Descripción'], ['Tipo'], ['Veces', 'text-center'], ['Costo', 'text-end']],
        (d.top_equipos || []).map(function (x) { return [{ v: x.codigo, c: 'fw-semibold' }, x.equipo, x.tipo, x.n, { v: s._s(x.costo), c: 'text-end' }]; })), 7) +
      s._caja('Preventivo vs. correctivo', s._tabla([['Tipo'], ['Cantidad', 'text-center'], ['Costo', 'text-end']],
        (d.tipos || []).map(function (x) { return [s._tx(RP_TIPO_MT, x.tipo), x.n, { v: s._s(x.costo), c: 'text-end' }]; })), 5) +
      '</div>';
    h += s._detalle('rp-mt-det', 'Detalle de mantenimientos del período', [['Código'], ['Equipo'], ['Descripción'], ['Tipo'], ['Estado'], ['Técnico'], ['Proveedor'], ['Inicio'], ['Fin'], ['Total', 'text-end']],
      (d.detalle || []).map(function (x) {
        return [{ v: x.codigo, c: 'fw-semibold' }, x.equipo, (x.equipo_tipo || '') + ' ' + (x.equipo_desc || ''), x.tipo, s._tx(RP_EST_MT, x.estado), x.tecnico, x.proveedor,
          String(x.fecha_inicio).slice(0, 10), String(x.fecha_fin).slice(0, 10), { v: s._s(x.costo_total), c: 'text-end fw-semibold' }];
      }), d.detalle.length);
    return h;
  },
  _g_mantenimiento: function (d) {
    var s = this;
    s._chart('mantenimiento', 'costo', { type: 'bar', data: { labels: d.meses.map(s._mes), datasets: [
      { type: 'bar', label: 'Costo (S/)', data: d.costos, backgroundColor: '#ea580c', borderRadius: 3, yAxisID: 'y' },
      { type: 'line', label: 'Servicios', data: d.cantidad, borderColor: '#2563eb', backgroundColor: '#2563eb', tension: .3, yAxisID: 'y1' }] },
      options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true }, y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } } } } });
    s._dona('mantenimiento', 'estado', d.estados.map(function (e) { return s._tx(RP_EST_MT, e.estado); }), s._num(d.estados, 'n'));
    s._barras('mantenimiento', 'tipoeq', s._col(d.por_tipo_equipo, 'nombre'), s._num(d.por_tipo_equipo, 'costo'), { horizontal: true, dinero: true, colorUno: '#ea580c' });
    s._barras('mantenimiento', 'prov', s._col(d.proveedores, 'nombre'), s._num(d.proveedores, 'costo'), { horizontal: true, dinero: true, colorUno: '#7c3aed' });
  },

  /* ---------------------------------------------------------- INVENTARIO */
  _p_inventario: function (d) {
    var s = this, t = d.tarjetas, sg = d.seguimiento;
    var h = s._tarjetas([
      { t: 'Equipos en inventario', v: s._n(t.total), ic: 'bi-pc-display', ir: 'equipos/lista' },
      { t: 'Valor del inventario', v: s._s(t.valorizado), ic: 'bi-cash-coin' },
      { t: 'En stock', v: t.stock, ic: 'bi-box', c: 'success' },
      { t: 'Asignados / en préstamo', v: t.asignados, ic: 'bi-person-check' },
      { t: 'En mantenimiento o revisión', v: t.en_mantenimiento, ic: 'bi-tools' }
    ]) + s._tarjetas([
      { t: 'Obsoletos', v: t.obsoletos, ic: 'bi-hourglass-bottom', c: t.obsoletos ? 'warning' : '' },
      { t: 'Garantía por vencer (90 días)', v: t.garantia90, ic: 'bi-shield-exclamation', c: t.garantia90 ? 'warning' : 'success' },
      { t: 'Garantía ya vencida', v: t.garantia_vencida, ic: 'bi-shield-x' },
      { t: 'Ubicación al día (30 días)', v: sg.verde, ic: 'bi-geo-alt', c: 'success', ir: 'equipos/seguimiento' },
      { t: 'Sin verificar (+90 días)', v: sg.rojo, ic: 'bi-geo-alt', c: sg.rojo ? 'danger' : 'success', sub: sg.ambar + ' entre 30 y 90 días', ir: 'equipos/seguimiento' }
    ]);
    h += '<div class="row g-3">' +
      s._caja('Por estado', s._lienzo('inventario', 'estado', 240), 4) +
      s._caja('Por tipo de equipo (cantidad)', s._lienzo('inventario', 'tipo', 240), 8) +
      s._caja('Antigüedad (según fecha de compra)', s._lienzo('inventario', 'edad', 230), 4) +
      s._caja('Condición', s._lienzo('inventario', 'cond', 230), 4) +
      s._caja('Garantías que vencen (próximos 6 meses)', s._lienzo('inventario', 'gar', 230), 4) +
      s._caja('Equipos entregados por destino', s._lienzo('inventario', 'dest', 230), 4) +
      s._caja('Valor por tipo de equipo', s._tabla([['Tipo'], ['Cantidad', 'text-center'], ['Valor', 'text-end']],
        (d.por_tipo || []).map(function (x) { return [x.nombre, x.n, { v: s._s(x.valor), c: 'text-end' }]; })), 8) +
      s._caja('Equipos por proyecto / obra', s._tabla([['Código'], ['Proyecto / obra'], ['Tipo'], ['Equipos', 'text-center'], ['Valor', 'text-end']],
        (d.por_proyecto || []).map(function (x) { return [x.codigo, x.nombre, x.tipo === 'obra' ? 'Obra' : 'Proyecto', { v: x.n, c: 'fw-semibold' }, { v: s._s(x.valor), c: 'text-end' }]; }),
        'Sin equipos asignados a proyectos u obras.'), 12) +
      '</div>';
    return h;
  },
  _g_inventario: function (d) {
    var s = this;
    s._dona('inventario', 'estado', d.estados.map(function (e) { return s._tx(RP_EST_EQ, e.estado); }), s._num(d.estados, 'n'), d.estados.map(function (e) { return RP_COLOR_EQ[e.estado] || '#64748b'; }));
    s._barras('inventario', 'tipo', s._col(d.por_tipo, 'nombre'), s._num(d.por_tipo, 'n'));
    s._barras('inventario', 'edad', s._col(d.edades, 'rango'), s._num(d.edades, 'n'), { colores: ['#16a34a', '#84cc16', '#f59e0b', '#dc2626', '#94a3b8'] });
    s._dona('inventario', 'cond', d.condicion.map(function (e) { return s._tx(RP_COND, e.condicion); }), s._num(d.condicion, 'n'), ['#16a34a', '#84cc16', '#f59e0b', '#ea580c', '#dc2626']);
    s._barras('inventario', 'gar', d.garantias.map(function (g) { return s._mes(g.mes); }), s._num(d.garantias, 'n'), { colorUno: '#f59e0b' });
    s._dona('inventario', 'dest', d.por_destino.map(function (e) { return s._tx(RP_TDEST, e.tipo); }), s._num(d.por_destino, 'n'));
  },

  /* ---------------------------------------------------------- SOFTWARE */
  _p_software: function (d) {
    var s = this, t = d.tarjetas;
    var h = '<div class="small text-muted mb-2"><i class="bi bi-info-circle"></i> Este reporte muestra la situación actual del software; el rango de fechas no lo afecta. ' +
      '<a href="#" data-ir="software/lista">Abrir el módulo Software</a></div>';
    h += s._tarjetas([
      { t: 'Cobertura de equipos de cómputo', v: t.cobertura + '%', ic: 'bi-pc-display', c: t.cobertura < 80 ? 'warning' : 'success', sub: (t.equipos_computo - t.equipos_computo_sin) + ' de ' + t.equipos_computo },
      { t: 'Instalaciones registradas', v: s._n(t.instalaciones), ic: 'bi-app-indicator', sub: t.programas + ' programas en el catálogo' },
      { t: 'Puestos de licencia vigentes', v: s._n(t.puestos), ic: 'bi-key' },
      { t: 'Inversión en licencias', v: s._s(t.inversion), ic: 'bi-cash-coin' }
    ]) + s._tarjetas([
      { t: 'Software prohibido instalado', v: t.no_autorizado, ic: 'bi-slash-circle', c: t.no_autorizado ? 'danger' : 'success', ir: 'software/lista' },
      { t: 'Instalado sin licencia', v: t.sin_licencia, ic: 'bi-exclamation-triangle', c: t.sin_licencia ? 'danger' : 'success' },
      { t: 'Con licencia vencida', v: t.licencia_vencida, ic: 'bi-calendar-x', c: t.licencia_vencida ? 'danger' : 'success' },
      { t: 'Licencias por vencer (60 días)', v: t.lic_por_vencer, ic: 'bi-hourglass-split', c: t.lic_por_vencer ? 'warning' : 'success' },
      { t: 'Programas con licencias de menos', v: t.programas_deficit, ic: 'bi-graph-down', c: t.programas_deficit ? 'danger' : 'success', sub: t.puestos_faltantes + ' puesto(s) faltante(s)' }
    ]);
    var SIT = { ok: ['En regla', 'text-success'], deficit: ['Faltan licencias', 'text-danger fw-semibold'], sin_uso: ['Sin uso', 'text-muted'], libre: ['No requiere licencia', 'text-muted'] };
    var cumpl = (d.cumplimiento || []).filter(function (c) { return c.instaladas > 0 || c.licencias > 0; });
    h += '<div class="row g-3">' +
      s._caja('Instalaciones por categoría', s._lienzo('software', 'cat', 240), 6) +
      s._caja('Instalaciones por autorización', s._lienzo('software', 'aut', 240), 6) +
      s._caja('Cumplimiento por programa', s._tabla([['Programa'], ['Instalados', 'text-center'], ['Puestos', 'text-center'], ['Sin licencia', 'text-center'], ['Situación']],
        cumpl.map(function (c) { var x = SIT[c.estado] || ['', '']; return [{ v: c.nombre, c: 'fw-semibold' }, c.instaladas, c.requiere_licencia ? c.puestos_vigentes : '—', c.requiere_licencia ? c.sin_licencia : '—', { v: x[0] + (c.estado === 'deficit' ? ' (' + c.deficit + ')' : ''), c: x[1] }]; }),
        'Todavía no hay software registrado.'), 7) +
      s._caja('Licencias', s._tabla([['Programa'], ['Tipo'], ['Puestos', 'text-center'], ['Vence']],
        (d.licencias || []).map(function (l) {
          var cls = l.estado === 'vencida' ? 'text-danger fw-semibold' : (l.estado === 'por_vencer' ? 'text-warning fw-semibold' : '');
          return [l.software, l.tipo, l.puestos, { v: l.fecha_vencimiento ? s._fecha(l.fecha_vencimiento) : 'No vence', c: cls }];
        }), 'No hay licencias registradas.'), 5) +
      '</div>';
    return h;
  },
  _g_software: function (d) {
    var s = this, AUT = { autorizado: 'Autorizado', evaluacion: 'En evaluación', prohibido: 'Prohibido' };
    s._barras('software', 'cat', s._col(d.por_categoria, 'nombre'), s._num(d.por_categoria, 'n'), { horizontal: true });
    s._dona('software', 'aut', d.por_autorizacion.map(function (e) { return AUT[e.autorizacion] || e.autorizacion; }), s._num(d.por_autorizacion, 'n'),
      d.por_autorizacion.map(function (e) { return e.autorizacion === 'prohibido' ? '#dc2626' : (e.autorizacion === 'evaluacion' ? '#f59e0b' : '#16a34a'); }));
  },

  /* ---------------------------------------------------------- ASIGNACIONES Y ACTAS */
  _p_asignaciones: function (d) {
    var s = this, t = d.tarjetas;
    var h = s._tarjetas([
      { t: 'Equipos entregados vigentes', v: s._n(t.activas), ic: 'bi-box-seam', ir: 'asignaciones/lista' },
      { t: 'En préstamo', v: t.prestamos, ic: 'bi-arrow-left-right' },
      { t: 'Entregas del período', v: t.entregas, ic: 'bi-box-arrow-right' },
      { t: 'Devoluciones del período', v: t.devoluciones, ic: 'bi-box-arrow-in-left' },
      { t: 'Devoluciones vencidas', v: t.vencidas, ic: 'bi-alarm', c: t.vencidas ? 'danger' : 'success' }
    ]) + s._tarjetas([
      { t: 'Actas del período', v: t.actas, ic: 'bi-file-earmark-text', ir: 'actas/lista' },
      { t: 'Actas pendientes de firma', v: t.actas_pendientes, ic: 'bi-pen', c: t.actas_pendientes ? 'warning' : 'success' },
      { t: 'Solicitudes de equipo abiertas', v: t.solicitudes_pendientes, ic: 'bi-inbox', c: t.solicitudes_pendientes ? 'warning' : '' }
    ]);
    h += '<div class="row g-3">' +
      s._caja('Entregas, devoluciones y actas por mes', s._lienzo('asignaciones', 'mes', 240), 8) +
      s._caja('Destino de los equipos', s._lienzo('asignaciones', 'dest', 240), 4) +
      s._caja('Actas por tipo', s._lienzo('asignaciones', 'actas', 220), 4) +
      s._caja('Actas por tipo de equipo', s._lienzo('asignaciones', 'fam', 220), 4) +
      s._caja('Estado de las asignaciones', s._lienzo('asignaciones', 'est', 220), 4) +
      s._caja('<i class="bi bi-alarm text-danger"></i> Devoluciones vencidas', s._tabla([['Equipo'], ['Destino'], ['Debía devolverse'], ['Atraso', 'text-center']],
        (d.prestamos_vencidos || []).map(function (x) { return [{ v: x.equipo + ' ' + (x.equipo_desc || ''), c: 'fw-semibold' }, x.destino, s._fecha(x.fecha_devolucion_esperada), { v: x.dias_atraso + ' días', c: 'text-danger text-center' }]; }),
        'No hay devoluciones vencidas.'), 6) +
      s._caja('<i class="bi bi-pen text-warning"></i> Actas pendientes de firma', s._tabla([['Acta'], ['Tipo'], ['Destino'], ['Fecha'], ['Días', 'text-center']],
        (d.actas_pendientes || []).map(function (x) { return [{ v: x.codigo, c: 'fw-semibold' }, x.tipo, x.destino, s._fecha(x.fecha), { v: x.dias, c: x.dias > 7 ? 'text-danger text-center' : 'text-center' }]; }),
        'No hay actas pendientes.'), 6) +
      '</div>';
    return h;
  },
  _g_asignaciones: function (d) {
    var s = this, EST = { activa: 'Activa', vencida: 'Vencida', devuelta: 'Devuelta', cancelada: 'Cancelada' };
    s._barrasVarias('asignaciones', 'mes', d.meses.map(s._mes), [{ label: 'Entregas', data: d.entregas }, { label: 'Devoluciones', data: d.devoluciones, color: '#f59e0b' }, { label: 'Actas', data: d.actas_mes, color: '#7c3aed' }]);
    s._dona('asignaciones', 'dest', d.por_destino.map(function (e) { return s._tx(RP_TDEST, e.tipo); }), s._num(d.por_destino, 'n'));
    s._dona('asignaciones', 'actas', d.actas_tipo.map(function (e) { return s._tx({ entrega: 'Entrega', devolucion: 'Devolución', cambio: 'Cambio' }, e.tipo); }), s._num(d.actas_tipo, 'n'));
    s._barras('asignaciones', 'fam', d.actas_familia.map(function (e) { return e.familia; }), s._num(d.actas_familia, 'n'), { horizontal: true, colorUno: '#7c3aed' });
    s._dona('asignaciones', 'est', d.estados.map(function (e) { return EST[e.estado] || e.estado; }), s._num(d.estados, 'n'), ['#16a34a', '#dc2626', '#64748b', '#94a3b8']);
  },

  /* ---------------------------------------------------------- PERSONAL */
  _p_personal: function (d) {
    var s = this, t = d.tarjetas;
    var h = s._tarjetas([
      { t: 'Personal activo', v: s._n(t.activos), ic: 'bi-people', ir: 'personal/lista' },
      { t: 'Por ingresar', v: t.pre_ingreso, ic: 'bi-person-plus' },
      { t: 'Cesados', v: t.cesados, ic: 'bi-person-dash' },
      { t: 'Con equipos asignados', v: t.con_equipos, ic: 'bi-pc-display', c: 'success' },
      { t: 'Activos sin equipo', v: t.sin_equipo, ic: 'bi-person-x' }
    ]);
    h += '<div class="row g-3">' +
      s._caja('Ingresos y ceses por mes', s._lienzo('personal', 'mes', 240), 7) +
      s._caja('Por estado', s._lienzo('personal', 'est', 240), 5) +
      s._caja('Personal por área', s._lienzo('personal', 'area', 260), 12) + '</div>';
    return h;
  },
  _g_personal: function (d) {
    var s = this;
    s._barrasVarias('personal', 'mes', d.meses.map(s._mes), [{ label: 'Ingresos', data: d.altas, color: '#16a34a' }, { label: 'Ceses', data: d.ceses, color: '#dc2626' }]);
    s._dona('personal', 'est', d.estados.map(function (e) { return s._tx(RP_EST_PE, e.estado); }), s._num(d.estados, 'n'));
    s._barras('personal', 'area', s._col(d.por_area, 'nombre'), s._num(d.por_area, 'n'));
  },

  /* ---------------------------------------------------------- descarga */
  _descargar: function (formato) {
    var s = this, m = s._m;
    if (m === 'resumen') { Toast.warning('El resumen se imprime con «Informe». Elija otra pestaña para descargar.'); return; }
    var q = $.param({ modulo: m, desde: $('#rp-desde').val(), hasta: $('#rp-hasta').val(), formato: formato, csrf_token: CSRF.token });
    fetch(BASE_URL + 'api/reportes/exportar.php?' + q, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) return r.json().then(function (j) { throw j; }, function () { throw { message: 'Error (' + r.status + ')' }; });
      var mm = /filename="?([^";]+)"?/.exec(r.headers.get('Content-Disposition') || '');
      var nombre = mm ? mm[1] : ('reporte_' + m + (formato === 'csv' ? '.csv' : '.xls'));
      return r.blob().then(function (b) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(b); a.download = nombre;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
        Toast.success('Reporte descargado');
      });
    }).catch(function (e) { Toast.error((e && e.message) || 'No se pudo descargar'); });
  }
};
App.registrar('reportes', Reportes);
