/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Software instalado en los equipos (control interno / auditoría)
   Vista: views/software/lista.php
   API:   api/software/{resumen,catalogo,licencias,instalaciones,instalar,equipo,equipos,exportar}.php
   También lo usan: la ficha del equipo (Software.abrirEquipo) y el formulario de
   registro de equipos (SoftwarePicker).
   ============================================================ */
var SW_AUT = {
  autorizado: ['Autorizado', 'text-bg-success'],
  evaluacion: ['En evaluación', 'text-bg-warning text-dark'],
  prohibido: ['No autorizado', 'text-bg-danger']
};
var SW_LIC_EST = {
  vigente: ['Vigente', 'text-bg-success'], por_vencer: ['Por vencer', 'text-bg-warning text-dark'],
  vencida: ['Vencida', 'text-bg-danger'], sin_vencimiento: ['Sin vencimiento', 'text-bg-secondary'], baja: ['De baja', 'text-bg-dark']
};
var SW_SIT = {
  ok: ['En regla', 'text-bg-success'], deficit: ['Faltan licencias', 'text-bg-danger'],
  sin_uso: ['Sin uso', 'text-bg-secondary'], libre: ['No requiere licencia', 'text-bg-light text-dark border']
};
var SW_DURACIONES = { dias: 'día(s)', meses: 'mes(es)', anios: 'año(s)' };
var SW_TIPOS_LIC = { perpetua: 'Perpetua', suscripcion: 'Suscripción', oem: 'OEM (viene con el equipo)', volumen: 'Por volumen' };

var Software = {
  perm: { registrar: false, gestionar: false },
  tInst: null, tEq: null,
  _cat: [], _catMeta: { categorias: {}, autorizacion: {} },
  _equipos: null,
  _lic: [],
  _sucio: false,        // algo cambió: el resumen se vuelve a pedir al volver a él
  _verClave: {},

  /* ---------------------------------------------------------- utilidades */
  _fecha: function (s) {
    s = String(s || '').slice(0, 10);
    return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s.slice(8, 10) + '/' + s.slice(5, 7) + '/' + s.slice(0, 4) : '';
  },
  _hoy: function () {
    var d = new Date(), m = d.getMonth() + 1, dd = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (dd < 10 ? '0' : '') + dd;
  },
  _money: function (n) {
    return 'S/ ' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  },
  _badge: function (par, extra) {
    return '<span class="badge ' + par[1] + (extra ? ' ' + extra : '') + '">' + esc(par[0]) + '</span>';
  },
  _opt: function (v, t, sel) {
    return '<option value="' + esc(v) + '"' + (String(sel) === String(v) ? ' selected' : '') + '>' + esc(t) + '</option>';
  },
  _llenar: function (sel, mapa, primera) {
    var $s = $(sel), v = $s.val() || '';
    var h = primera ? '<option value="">' + esc(primera) + '</option>' : '';
    Object.keys(mapa || {}).forEach(function (k) { h += Software._opt(k, mapa[k], v); });
    $s.html(h);
  },
  _errores: function (pref, e) {
    $('[id^="' + pref + '"]').removeClass('is-invalid');
    $('.sw-fb').remove();
    var er = e && e.cuerpo && e.cuerpo.errors;
    if (!er) return false;
    var alguno = false;
    Object.keys(er).forEach(function (k) {
      var $i = $('#' + pref + k);
      if ($i.length) {
        $i.addClass('is-invalid').after('<div class="invalid-feedback d-block sw-fb">' + esc(er[k]) + '</div>');
        alguno = true;
      }
    });
    return alguno;
  },
  _post: function (url, datos) { return Api.post(url, datos); },
  _refrescar: function () {
    this._sucio = true;
    if (this.tInst) this.tInst.draw(false);
    if (this.tEq) this.tEq.draw(false);
    if ($('#sw-raiz').length) {
      this._cargarCatalogo();
      if ($('#sw-p-res').hasClass('active') || $('#sw-p-res').hasClass('show')) this._resumen();
      if ($('#sw-p-lic').hasClass('active') || $('#sw-p-lic').hasClass('show') || this._licVista) this._cargarLic();
    }
  },

  /* ---------------------------------------------------------- init */
  init: function (p) {
    var s = this, $r = $('#sw-raiz');
    if (!$r.length) return;
    s.perm = { registrar: +$r.attr('data-registrar') === 1, gestionar: +$r.attr('data-gestionar') === 1 };
    s.tInst = null; s.tEq = null; s._equipos = null; s._sucio = false; s._verClave = {};

    $('#sw-tabs button[data-bs-toggle=tab]').off('shown.bs.tab.sw').on('shown.bs.tab.sw', function () {
      s._activar($(this).attr('data-vista'));
    });

    // Resumen
    $('#sw-alertas').off('click.sw').on('click.sw', '.sw-alerta.clic', function () {
      var a = s._alertasDef[+$(this).attr('data-i')];
      if (a) s._ir(a.tab, a.f || {});
    });
    $('#sw-informe').off('click.sw').on('click.sw', function (e) { e.preventDefault(); window.open(BASE_URL + 'views/software/informe.php', '_blank'); });
    $('#sw-menu-exp').off('click.sw').on('click.sw', '.sw-exp', function (e) {
      e.preventDefault(); s._exportar($(this).attr('data-r'), $(this).attr('data-f'), {});
    });
    $('#sw-tb-cumpl').off('click.sw').on('click.sw', 'tr[data-sw]', function () { s._ir('inst', { software_id: $(this).attr('data-sw') }); });
    $('#sw-lic-alerta').off('click.sw').on('click.sw', '.sw-lic-ir', function () { s._ir('lic', { estado: '' }); });

    // Instalaciones
    $('#swf-filtrar').off('click.sw').on('click.sw', function () { if (s.tInst) s.tInst.draw(); });
    $('#swf-limpiar').off('click.sw').on('click.sw', function () {
      $('#swf-software,#swf-categoria,#swf-tipo,#swf-alerta').val(''); $('#swf-estado').val('instalado');
      s._equipoFiltro = null; s._badgeEquipo();
      if (s.tInst) { s.tInst.search(''); s.tInst.draw(); }
    });
    $('#swf-software,#swf-categoria,#swf-tipo,#swf-alerta,#swf-estado').off('change.sw').on('change.sw', function () { if (s.tInst) s.tInst.draw(); });
    $('#swf-equipo-badge').off('click.sw').on('click.sw', '#swf-equipo-x', function (e) { e.preventDefault(); s._equipoFiltro = null; s._badgeEquipo(); if (s.tInst) s.tInst.draw(); });
    $('#sw-btn-instalar').off('click.sw').on('click.sw', function () { s._formInstalar(); });
    $('#sw-btn-masivo').off('click.sw').on('click.sw', function () { s._formMasivo(); });
    $('#sw-btn-importar').off('click.sw').on('click.sw', function () { s._formImportar(); });

    // Por equipo
    $('#swe-filtrar').off('click.sw').on('click.sw', function () { if (s.tEq) s.tEq.draw(); });
    $('#swe-tipo,#swe-familia,#swe-cobertura,#swe-estado').off('change.sw').on('change.sw', function () { if (s.tEq) s.tEq.draw(); });
    $('#swe-limpiar').off('click.sw').on('click.sw', function () {
      $('#swe-tipo,#swe-cobertura,#swe-estado').val(''); $('#swe-familia').val('computo');
      if (s.tEq) { s.tEq.search(''); s.tEq.draw(); }
    });

    // Catálogo
    $('#swc-q').off('input.sw').on('input.sw', function () { s._renderCat(); });
    $('#swc-categoria,#swc-aut,#swc-activo').off('change.sw').on('change.sw', function () { s._renderCat(); });
    $('#sw-btn-nuevo-prog').off('click.sw').on('click.sw', function () { s._formPrograma(null); });
    $('#sw-tb-cat').off('click.sw')
      .on('click.sw', '.sw-c-edit', function () { s._formPrograma(s._buscarCat($(this).attr('data-id'))); })
      .on('click.sw', '.sw-c-toggle', function () { s._togglePrograma($(this).attr('data-id')); })
      .on('click.sw', '.sw-c-del', function () { s._eliminarPrograma($(this).attr('data-id')); })
      .on('click.sw', '.sw-c-ver', function () { s._ir('inst', { software_id: $(this).attr('data-id') }); });

    // Licencias
    $('#swl-software,#swl-estado').off('change.sw').on('change.sw', function () { s._cargarLic(); });
    $('#sw-btn-nueva-lic').off('click.sw').on('click.sw', function () { s._formLicencia(null); });
    $('#sw-tb-lic').off('click.sw')
      .on('click.sw', '.sw-l-fact', function () { s._verFactura(s._buscarLic($(this).attr('data-id'))); })
      .on('click.sw', '.sw-l-edit', function () { s._formLicencia(s._buscarLic($(this).attr('data-id'))); })
      .on('click.sw', '.sw-l-baja', function () { s._bajaLicencia($(this).attr('data-id')); })
      .on('click.sw', '.sw-l-del', function () { s._eliminarLicencia($(this).attr('data-id')); })
      .on('click.sw', '.sw-l-ver', function () { s._ir('inst', { software_id: $(this).attr('data-sw') }); })
      .on('click.sw', '.sw-l-clave', function () {
        var id = $(this).attr('data-id'); s._verClave[id] = !s._verClave[id]; s._renderLic();
      });

    s._cargarCatalogo().then(function () {
      s._resumen();
      if (p && p.tab) s._ir(p.tab, p);
    });
  },

  _activar: function (v) {
    var s = this;
    if (v === 'res') { if (s._sucio) { s._sucio = false; s._resumen(); } return false; }
    if (v === 'inst') { if (!s.tInst) { s._tablaInst(); return true; } s.tInst.columns.adjust(); return false; }
    if (v === 'eq') { if (!s.tEq) { s._tablaEq(); return true; } s.tEq.columns.adjust(); return false; }
    if (v === 'cat') { s._renderCat(); return false; }
    if (v === 'lic') { s._cargarLic(); return true; }
    return false;
  },

  /* abre una pestaña con filtros ya puestos (desde las observaciones del resumen, el catálogo, etc.) */
  _ir: function (tab, f) {
    var s = this;
    f = f || {};
    var ids = { res: 'sw-t-res', inst: 'sw-t-inst', eq: 'sw-t-eq', cat: 'sw-t-cat', lic: 'sw-t-lic' };
    if (tab === 'inst') {
      $('#swf-software').val(f.software_id ? String(f.software_id) : '');
      $('#swf-alerta').val(f.f_alerta || ''); $('#swf-estado').val(f.estado || 'instalado');
      $('#swf-categoria,#swf-tipo').val('');
      s._equipoFiltro = f.equipo_id ? { id: f.equipo_id, codigo: f.codigo || '' } : null; s._badgeEquipo();
    } else if (tab === 'eq') {
      $('#swe-cobertura').val(f.cobertura || ''); $('#swe-familia').val(f.familia || '');
      $('#swe-estado').val(f.estado || ''); $('#swe-tipo').val('');
    } else if (tab === 'lic') {
      $('#swl-estado').val(f.estado || ''); $('#swl-software').val(f.software_id ? String(f.software_id) : '');
    }
    var el = document.getElementById(ids[tab]);
    try { if (window.bootstrap && bootstrap.Tab) bootstrap.Tab.getOrCreateInstance(el).show(); } catch (x) { /* sin Bootstrap */ }
    var creada = s._activar(tab);
    if (tab === 'inst' && !creada && s.tInst) s.tInst.draw();
    if (tab === 'eq' && !creada && s.tEq) s.tEq.draw();
    if (tab === 'lic' && !creada) s._cargarLic();
  },
  _badgeEquipo: function () {
    var $b = $('#swf-equipo-badge');
    if (!this._equipoFiltro) { $b.addClass('d-none').empty(); return; }
    $b.removeClass('d-none').html('<span class="badge text-bg-primary">Equipo ' + esc(this._equipoFiltro.codigo || ('#' + this._equipoFiltro.id)) +
      ' <a href="#" id="swf-equipo-x" class="text-white ms-1" title="Quitar">&times;</a></span>');
  },

  /* ---------------------------------------------------------- catálogo (datos) */
  _cargarCatalogo: function () {
    var s = this;
    return Api.get('api/software/catalogo.php').then(function (r) {
      s._cat = (r && r.items) || [];
      s._catMeta = { categorias: (r && r.categorias) || {}, autorizacion: (r && r.autorizacion) || {} };
      s._llenarFiltros();
      s._renderCat();
    }).catch(function () { });
  },
  _llenarFiltros: function () {
    var s = this;
    var act = s._cat.filter(function (c) { return +c.activo === 1; });
    ['#swf-software', '#swl-software'].forEach(function (sel) {
      var v = $(sel).val() || '';
      var h = '<option value="">Todos los programas</option>';
      s._cat.forEach(function (c) { h += s._opt(c.id, c.nombre + (+c.activo === 1 ? '' : ' (desactivado)'), v); });
      $(sel).html(h);
    });
    s._llenar('#swf-categoria', s._catMeta.categorias, 'Todas las categorías');
    s._llenar('#swc-categoria', s._catMeta.categorias, 'Todas las categorías');
    s._actCount = act.length;
  },
  _buscarCat: function (id) {
    return this._cat.filter(function (c) { return String(c.id) === String(id); })[0] || null;
  },

  /* ---------------------------------------------------------- RESUMEN */
  _resumen: function () {
    var s = this;
    Api.get('api/software/resumen.php').then(function (r) {
      if (!r) return;
      var set = function (k, v) { $('#sw-stats [data-k="' + k + '"]').html(v); };
      set('programas', r.programas); set('instalaciones', r.instalaciones);
      set('cobertura', r.cobertura + '%<div class="small fw-normal text-muted" style="font-size:.7rem">' +
        (r.equipos_computo - r.equipos_computo_sin) + ' de ' + r.equipos_computo + ' equipos</div>');
      set('puestos', r.puestos); set('inversion', s._money(r.inversion)); set('alertas', r.alertas || 0);
      $('#sw-stats [data-k="alertas"]').css('color', r.alertas ? '#b91c1c' : '#15803d');

      // observaciones
      s._alertasDef = [
        { k: 'no_autorizado', c: 'rojo', t: 'instalación(es) de programas NO autorizados', tab: 'inst', f: { f_alerta: 'no_autorizado' } },
        { k: 'sin_licencia', c: 'rojo', t: 'instalación(es) que necesitan licencia y no tienen una asignada', tab: 'inst', f: { f_alerta: 'sin_licencia' } },
        { k: 'licencia_vencida', c: 'rojo', t: 'instalación(es) con la licencia vencida', tab: 'inst', f: { f_alerta: 'licencia_vencida' } },
        { k: 'programas_deficit', c: 'rojo', t: 'programa(s) con menos puestos vigentes que instalaciones (faltan ' + (r.puestos_faltantes || 0) + ' licencia(s))', tab: 'res' },
        { k: 'lic_vencidas', c: 'ambar', t: 'licencia(s) ya vencida(s)', tab: 'lic', f: { estado: 'vencida' } },
        { k: 'lic_por_vencer', c: 'ambar', t: 'licencia(s) vencen en los próximos ' + r.dias_alerta + ' días', tab: 'lic', f: { estado: 'por_vencer' } },
        { k: 'equipos_computo_sin', c: 'ambar', t: 'equipo(s) de cómputo sin software registrado', tab: 'eq', f: { cobertura: 'sin', familia: 'computo' } },
        { k: 'sin_verificar', c: 'azul', t: 'equipo(s) con el software sin confirmar en ' + r.dias_verificacion + ' días', tab: 'eq', f: { estado: 'verif', familia: '' } }
      ];
      var h = '';
      s._alertasDef.forEach(function (a, i) {
        var n = +r[a.k] || 0;
        if (!n) return;
        h += '<div class="sw-alerta ' + a.c + ' clic" data-i="' + i + '"><span class="n">' + n + '</span><span class="small">' + esc(a.t) + '</span></div>';
      });
      $('#sw-alertas').html(h || '<div class="sw-alerta"><i class="bi bi-check-circle-fill text-success fs-5"></i><span>Sin observaciones: todo el software registrado está en regla.</span></div>');

      // licencias por vencer
      var lh = '';
      (r.licencias_alerta || []).forEach(function (l) {
        var est = SW_LIC_EST[l.estado] || SW_LIC_EST.vigente;
        var txt = l.estado === 'vencida' ? ('venció hace ' + Math.abs(l.dias) + ' día(s)') : (l.dias === 0 ? 'vence hoy' : ('vence en ' + l.dias + ' día(s)'));
        lh += '<div class="sw-fila-sw"><span>' + esc(l.software) + ' <span class="text-muted small">· ' + esc(SW_TIPOS_LIC[l.tipo] || l.tipo) + ' · ' + l.usados + '/' + l.puestos + ' puestos</span></span>' +
          '<span>' + s._badge(est) + ' <span class="small text-muted">' + esc(s._fecha(l.fecha_vencimiento)) + ' (' + txt + ')</span></span></div>';
      });
      $('#sw-lic-alerta').html(lh ? lh + '<a href="#" class="small sw-lic-ir">Ver todas las licencias</a>' : '<div class="text-muted small">Ninguna licencia vence en los próximos ' + r.dias_alerta + ' días.</div>');

      // cumplimiento
      var orden = { deficit: 0, ok: 1, sin_uso: 2, libre: 3 };
      var filas = (r.cumplimiento || []).filter(function (c) { return c.instaladas > 0 || c.licencias > 0; })
        .sort(function (a, b) { return (orden[a.estado] - orden[b.estado]) || (b.instaladas - a.instaladas) || a.nombre.localeCompare(b.nombre); });
      var ch = '';
      filas.forEach(function (c) {
        var sit = c.estado === 'deficit' ? ['Faltan ' + c.deficit, 'text-bg-danger'] : (SW_SIT[c.estado] || SW_SIT.ok);
        ch += '<tr data-sw="' + c.id + '" style="cursor:pointer"><td>' + esc(c.nombre) +
          (c.autorizacion === 'prohibido' ? ' <span class="badge text-bg-danger">No autorizado</span>' : '') +
          '<div class="small text-muted">' + esc(r.categorias[c.categoria] || c.categoria) + '</div></td>' +
          '<td class="text-center">' + c.instaladas + '</td><td class="text-center">' + (c.requiere_licencia ? c.puestos_vigentes : '—') + '</td><td>' + s._badge(sit) + '</td></tr>';
      });
      $('#sw-tb-cumpl tbody').html(ch || '<tr><td colspan="4" class="text-center text-muted">Todavía no hay software registrado en los equipos.</td></tr>');

      // top y categorías
      var mx = 1;
      (r.top || []).forEach(function (t) { if (t.instaladas > mx) mx = t.instaladas; });
      $('#sw-top').html((r.top || []).map(function (t) {
        return '<div class="mb-2"><div class="d-flex justify-content-between small"><span>' + esc(t.nombre) + '</span><b>' + t.instaladas + '</b></div><div class="sw-barra"><span style="width:' + Math.round(t.instaladas / mx * 100) + '%"></span></div></div>';
      }).join('') || '<div class="text-muted small">Sin datos todavía.</div>');
      var mc = 1;
      (r.por_categoria || []).forEach(function (t) { if (t.n > mc) mc = t.n; });
      $('#sw-categ').html((r.por_categoria || []).map(function (t) {
        return '<div class="mb-2"><div class="d-flex justify-content-between small"><span>' + esc(t.nombre) + '</span><b>' + t.n + '</b></div><div class="sw-barra verde"><span style="width:' + Math.round(t.n / mc * 100) + '%"></span></div></div>';
      }).join('') || '<div class="text-muted small">Sin datos todavía.</div>');
    }).catch(function () { });
  },

  /* ---------------------------------------------------------- INSTALACIONES (tabla) */
  _tablaInst: function () {
    var s = this;
    s.tInst = DT.server('#tb-sw-inst', 'api/software/instalaciones.php', function () {
      return {
        f_software: $('#swf-software').val() || 0, f_categoria: $('#swf-categoria').val() || '', f_tipo: $('#swf-tipo').val() || 0,
        f_alerta: $('#swf-alerta').val() || '', f_estado: $('#swf-estado').val() || 'instalado',
        f_equipo: s._equipoFiltro ? s._equipoFiltro.id : 0
      };
    }, [
      { data: 'codigo', render: function (v, t, f) {
        return '<a href="#" class="sw-ver-eq fw-semibold" data-id="' + f.equipo_id + '">' + esc(v) + '</a>' +
          (f.eq_estado === 'dado_de_baja' ? ' <span class="badge text-bg-dark">De baja</span>' : '') +
          '<div class="small text-muted">' + esc(f.tipo) + (f.equipo_desc ? ' · ' + esc(f.equipo_desc) : '') + '</div>';
      } },
      { data: 'software', render: function (v, t, f) {
        var a = f.autorizacion === 'prohibido' ? ' <span class="badge text-bg-danger">No autorizado</span>' : (f.autorizacion === 'evaluacion' ? ' <span class="badge text-bg-warning text-dark">En evaluación</span>' : '');
        return '<b>' + esc(v) + '</b>' + a;
      } },
      { data: 'categoria_nombre', render: function (v) { return '<span class="small">' + esc(v) + '</span>'; } },
      { data: 'version', render: function (v) { return v ? esc(v) : '<span class="text-muted">—</span>'; } },
      { data: 'lic_vence', render: function (v, t, f) { return s._celdaLicencia(f); } },
      { data: 'fecha_instalacion', render: function (v, t, f) {
        var h = '<span class="small">' + esc(s._fecha(v) || '—') + '</span>';
        if (f.instalado_por) h += '<div class="small text-muted">' + esc(f.instalado_por) + '</div>';
        if (f.estado === 'desinstalado' && f.fecha_desinstalacion) h += '<div class="small text-danger">Retirado ' + esc(s._fecha(f.fecha_desinstalacion)) + '</div>';
        return h;
      } },
      { data: 'asignado_a', render: function (v) { return v ? '<span class="small">' + esc(v) + '</span>' : '<span class="text-muted small">Sin asignar</span>'; } },
      { data: 'estado', render: function (v) { return v === 'instalado' ? '<span class="badge text-bg-success">Instalado</span>' : '<span class="badge text-bg-secondary">Retirado</span>'; } },
      { data: null, orderable: false, searchable: false, className: 'text-nowrap', render: function (v, t, f) {
        if (!s.perm.registrar || f.estado !== 'instalado' || f.eq_estado === 'dado_de_baja') return '';
        return '<button class="btn btn-sm btn-outline-secondary sw-editar me-1" title="Editar versión o licencia"><i class="bi bi-pencil"></i></button>' +
          '<button class="btn btn-sm btn-outline-danger sw-retirar" title="Retirar (queda en el historial)"><i class="bi bi-box-arrow-left"></i></button>';
      } }
    ], { order: [[0, 'asc']] });

    $('#tb-sw-inst').off('click.sw')
      .on('click.sw', '.sw-ver-eq', function (e) { e.preventDefault(); s.abrirEquipo($(this).attr('data-id')); })
      .on('click.sw', '.sw-editar', function () { s._formEditar(s.tInst.row($(this).closest('tr')).data()); })
      .on('click.sw', '.sw-retirar', function () { s._retirar(s.tInst.row($(this).closest('tr')).data()); });
  },

  _celdaLicencia: function (f) {
    var s = this;
    if (+f.requiere_licencia !== 1) return '<span class="text-muted small">No requiere</span>';
    if (!f.licencia_id) return '<span class="badge text-bg-danger">Sin licencia</span>';
    var venc = f.lic_vence ? (f.lic_vence < s._hoy() ? ' <span class="badge text-bg-danger">Vencida</span>' : '') : '';
    return '<span class="small">' + esc(f.lic_tipo_nombre || f.lic_tipo || '') + '</span> <span class="sw-clave text-muted">' + esc(f.lic_ult4 || '') + '</span>' + venc +
      (f.lic_vence ? '<div class="small text-muted">vence ' + esc(s._fecha(f.lic_vence)) + '</div>' : '');
  },

  /* ---------------------------------------------------------- POR EQUIPO (tabla) */
  _tablaEq: function () {
    var s = this;
    s.tEq = DT.server('#tb-sw-eq', 'api/software/equipos.php', function () {
      var est = $('#swe-estado').val() || '';
      return {
        f_tipo: $('#swe-tipo').val() || 0, f_familia: $('#swe-familia').val() || '', f_cobertura: $('#swe-cobertura').val() || '',
        f_alerta: est === 'alerta' ? 1 : 0, f_verif: est === 'verif' ? 'pendiente' : ''
      };
    }, [
      { data: 'codigo', render: function (v, t, f) {
        return '<a href="#" class="sw-eq-ver fw-semibold" data-id="' + f.id + '">' + esc(v) + '</a>';
      } },
      { data: 'tipo' },
      { data: 'marca', render: function (v, t, f) { return esc(((v || '') + ' ' + (f.modelo || '')).trim()) || '—'; } },
      { data: 'asignado_a', render: function (v) { return v ? esc(v) : '<span class="text-muted">Sin asignar</span>'; } },
      { data: 'n_software', className: 'text-center', render: function (v) {
        return v > 0 ? '<span class="badge text-bg-primary">' + v + '</span>' : '<span class="badge text-bg-secondary">0</span>';
      } },
      { data: 'n_alertas', className: 'text-center', render: function (v) {
        return v > 0 ? '<span class="badge text-bg-danger">' + v + '</span>' : '<i class="bi bi-check-circle text-success"></i>';
      } },
      { data: 'software_verificado_en', render: function (v, t, f) {
        if (+f.n_software === 0) return '<span class="text-muted small">—</span>';
        if (!v) return '<span class="badge text-bg-warning text-dark">Nunca</span>';
        return '<span class="small">' + esc(s._fecha(v)) + '</span>' + (+f.verif_vencida ? ' <span class="badge text-bg-warning text-dark">Vencido</span>' : '');
      } },
      { data: null, orderable: false, searchable: false, className: 'text-nowrap', render: function (v, t, f) {
        var h = '<button class="btn btn-sm btn-outline-primary sw-eq-ver" data-id="' + f.id + '" title="Ver y gestionar su software"><i class="bi bi-app-indicator"></i></button>';
        if (s.perm.registrar && +f.n_software > 0) h += ' <button class="btn btn-sm btn-outline-success sw-eq-conf" data-id="' + f.id + '" title="Confirmar que el listado es correcto"><i class="bi bi-patch-check"></i></button>';
        return h;
      } }
    ], { order: [[0, 'asc']] });

    $('#tb-sw-eq').off('click.sw')
      .on('click.sw', '.sw-eq-ver', function (e) { e.preventDefault(); s.abrirEquipo($(this).attr('data-id')); })
      .on('click.sw', '.sw-eq-conf', function () {
        s._post('api/software/instalar.php', { accion: 'verificar', equipo_ids: $(this).attr('data-id') })
          .then(function (r) { Toast.success((r && r.mensaje) || 'Confirmado'); s._refrescar(); }).catch(function () { });
      });
  },

  /* ---------------------------------------------------------- CATÁLOGO (pestaña) */
  _renderCat: function () {
    var s = this;
    if (!$('#sw-tb-cat').length) return;
    var q = ($('#swc-q').val() || '').toLowerCase().trim(), cat = $('#swc-categoria').val() || '', aut = $('#swc-aut').val() || '', act = $('#swc-activo').val();
    var h = '', n = 0;
    s._cat.forEach(function (c) {
      if (q && (c.nombre + ' ' + (c.fabricante || '')).toLowerCase().indexOf(q) < 0) return;
      if (cat && c.categoria !== cat) return;
      if (aut && c.autorizacion !== aut) return;
      if (act !== '' && act != null && String(c.activo) !== act) return;
      n++;
      var lic = '<span class="text-muted small">No requiere</span>';
      if (+c.requiere_licencia === 1) {
        var pv = +c.puestos_vigentes, ins = +c.instaladas;
        var pct = pv > 0 ? Math.min(100, Math.round(ins / pv * 100)) : (ins > 0 ? 100 : 0);
        lic = '<div class="small">' + c.licencias + ' licencia(s) · <b>' + ins + '</b> de ' + pv + ' puesto(s)</div><div class="sw-barra ' + (c.deficit > 0 ? 'rojo' : (pct >= 90 ? 'ambar' : 'verde')) + '"><span style="width:' + pct + '%"></span></div>' +
          (c.deficit > 0 ? '<div class="small text-danger">Faltan ' + c.deficit + '</div>' : '');
      }
      h += '<tr' + (+c.activo === 1 ? '' : ' class="table-secondary"') + '><td><b>' + esc(c.nombre) + '</b>' + (+c.estandar === 1 ? ' <span class="badge text-bg-info" title="Se sugiere al registrar equipos de cómputo">Estándar</span>' : '') +
        (+c.activo === 1 ? '' : ' <span class="badge text-bg-secondary">Desactivado</span>') +
        (c.fabricante ? '<div class="small text-muted">' + esc(c.fabricante) + '</div>' : '') + '</td>' +
        '<td class="small">' + esc(c.categoria_nombre) + '</td><td>' + s._badge(SW_AUT[c.autorizacion] || SW_AUT.autorizado) + '</td>' +
        '<td class="text-center">' + (+c.requiere_licencia === 1 ? '<i class="bi bi-key-fill text-warning" title="Requiere licencia"></i>' : '<span class="text-muted">—</span>') + '</td>' +
        '<td class="text-center">' + (c.instaladas > 0 ? '<a href="#" class="sw-c-ver" data-id="' + c.id + '">' + c.instaladas + '</a>' : '0') + '</td><td style="min-width:170px">' + lic + '</td><td class="text-nowrap">' +
        (s.perm.gestionar ? '<button class="btn btn-sm btn-outline-secondary sw-c-edit me-1" data-id="' + c.id + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
          '<button class="btn btn-sm btn-outline-' + (+c.activo === 1 ? 'warning' : 'success') + ' sw-c-toggle me-1" data-id="' + c.id + '" title="' + (+c.activo === 1 ? 'Desactivar' : 'Activar') + '"><i class="bi bi-' + (+c.activo === 1 ? 'eye-slash' : 'eye') + '"></i></button>' +
          '<button class="btn btn-sm btn-outline-danger sw-c-del" data-id="' + c.id + '" title="Eliminar (solo si nunca se usó)"><i class="bi bi-trash3"></i></button>' : '') + '</td></tr>';
    });
    $('#sw-tb-cat tbody').html(h || '<tr><td colspan="7" class="text-center text-muted">No hay programas con ese filtro.</td></tr>');
  },

  _formPrograma: function (c) {
    var s = this, nuevo = !c;
    c = c || { nombre: '', fabricante: '', categoria: 'otro', requiere_licencia: 1, autorizacion: 'autorizado', estandar: 0, descripcion: '' };
    var h = '<form id="form-sw-prog" autocomplete="off"><div class="row g-3">' +
      '<div class="col-md-7"><label class="form-label">Nombre del programa <span class="text-danger">*</span></label><input class="form-control" id="swp-nombre" maxlength="100" value="' + esc(c.nombre) + '" placeholder="Ej: Microsoft Office"></div>' +
      '<div class="col-md-5"><label class="form-label">Fabricante</label><input class="form-control" id="swp-fabricante" maxlength="80" value="' + esc(c.fabricante || '') + '"></div>' +
      '<div class="col-md-6"><label class="form-label">Categoría</label><select class="form-select" id="swp-categoria">';
    Object.keys(s._catMeta.categorias).forEach(function (k) { h += s._opt(k, s._catMeta.categorias[k], c.categoria); });
    h += '</select></div><div class="col-md-6"><label class="form-label">Autorización</label><select class="form-select" id="swp-autorizacion">';
    Object.keys(s._catMeta.autorizacion).forEach(function (k) { h += s._opt(k, s._catMeta.autorizacion[k], c.autorizacion); });
    h += '</select><div class="form-text">«No autorizado» marca como observación cada equipo que lo tenga.</div></div>' +
      '<div class="col-md-6"><div class="form-check"><input type="checkbox" class="form-check-input" id="swp-requiere"' + (+c.requiere_licencia === 1 ? ' checked' : '') + '><label class="form-check-label" for="swp-requiere">Requiere licencia (de pago)</label></div>' +
      '<div class="form-text">Si lo marca, cada instalación debe tener un puesto de licencia.</div></div>' +
      '<div class="col-md-6"><div class="form-check"><input type="checkbox" class="form-check-input" id="swp-estandar"' + (+c.estandar === 1 ? ' checked' : '') + '><label class="form-check-label" for="swp-estandar">Software estándar</label></div>' +
      '<div class="form-text">Se sugiere automáticamente al registrar laptops y PC.</div></div>' +
      '<div class="col-12"><label class="form-label">Descripción</label><input class="form-control" id="swp-descripcion" maxlength="255" value="' + esc(c.descripcion || '') + '"></div></div>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary" id="swp-ok"><i class="bi bi-check-lg"></i> ' + (nuevo ? 'Agregar al catálogo' : 'Guardar') + '</button></div></form>';
    Modal.abrir(nuevo ? 'Nuevo programa' : 'Editar — ' + c.nombre, h, 'modal-lg');
    $('#form-sw-prog').off('submit').on('submit', function (e) {
      e.preventDefault();
      var d = {
        accion: 'guardar', id: nuevo ? 0 : c.id, nombre: $.trim($('#swp-nombre').val()), fabricante: $.trim($('#swp-fabricante').val()),
        categoria: $('#swp-categoria').val(), autorizacion: $('#swp-autorizacion').val(), requiere_licencia: $('#swp-requiere').prop('checked') ? 1 : 0,
        estandar: $('#swp-estandar').prop('checked') ? 1 : 0, descripcion: $.trim($('#swp-descripcion').val())
      };
      $('#swp-ok').prop('disabled', true);
      s._post('api/software/catalogo.php', d).then(function (r) {
        Modal.cerrar(); Toast.success((r && r.mensaje) || 'Guardado'); s._refrescar();
      }).catch(function (er) { $('#swp-ok').prop('disabled', false); s._errores('swp-', er); });
    });
  },
  _togglePrograma: function (id) {
    var s = this;
    s._post('api/software/catalogo.php', { accion: 'toggle', id: id }).then(function (r) { Toast.success((r && r.mensaje) || 'Listo'); s._refrescar(); }).catch(function () { });
  },
  _eliminarPrograma: function (id) {
    var s = this, c = s._buscarCat(id);
    if (!c) return;
    Swal.fire({
      title: '¿Eliminar «' + c.nombre + '»?', text: 'Solo se puede si nunca tuvo instalaciones ni licencias; si no, use «Desactivar».',
      icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      s._post('api/software/catalogo.php', { accion: 'eliminar', id: id }).then(function (x) { Toast.success((x && x.mensaje) || 'Eliminado'); s._refrescar(); }).catch(function () { });
    });
  },

  /* ---------------------------------------------------------- LICENCIAS */
  _cargarLic: function () {
    var s = this;
    s._licVista = true;
    return Api.get('api/software/licencias.php', { software_id: $('#swl-software').val() || 0, estado: $('#swl-estado').val() || '' }).then(function (r) {
      s._lic = r || [];
      s._renderLic();
    }).catch(function () { });
  },
  // Misma regla que Software::calcularVencimiento (PHP): mismo día del mes/año siguiente; si no existe, el último día
  _calcVence: function (act, val, uni) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(act || '');
    if (!m || !(val > 0)) return '';
    var a = +m[1], mes = +m[2], dia = +m[3], p = function (n) { return (n < 10 ? '0' : '') + n; };
    if (uni === 'dias') { var dt = new Date(Date.UTC(a, mes - 1, dia + val)); return dt.getUTCFullYear() + '-' + p(dt.getUTCMonth() + 1) + '-' + p(dt.getUTCDate()); }
    var tot = a * 12 + (mes - 1) + (uni === 'anios' ? val * 12 : val), na = Math.floor(tot / 12), nm = tot % 12 + 1;
    var ult = new Date(Date.UTC(na, nm, 0)).getUTCDate();
    return na + '-' + p(nm) + '-' + p(Math.min(dia, ult));
  },
  _buscarLic: function (id) {
    return this._lic.filter(function (l) { return String(l.id) === String(id); })[0] || null;
  },
  _renderLic: function () {
    var s = this, h = '';
    s._lic.forEach(function (l) {
      var est = SW_LIC_EST[l.estado] || SW_LIC_EST.vigente;
      var pct = l.puestos > 0 ? Math.min(100, Math.round(l.usados / l.puestos * 100)) : 0;
      var clave = '<span class="text-muted small">—</span>';
      if (+l.tiene_clave === 1) {
        if (l.clave != null) clave = '<span class="sw-clave">' + (s._verClave[l.id] ? esc(l.clave) : '••••••' + esc(l.clave_ult4 || '')) + '</span> <a href="#" class="sw-l-clave small ms-1" data-id="' + l.id + '" onclick="return false" title="Mostrar / ocultar"><i class="bi bi-eye"></i></a>';
        else clave = '<span class="sw-clave text-muted">' + esc(l.clave_ult4) + '</span>';
      }
      var venc = l.fecha_vencimiento ? esc(s._fecha(l.fecha_vencimiento)) + '<div>' + s._badge(est) + '</div>' + (l.fecha_activacion ? '<div class="small text-muted">Activada ' + esc(s._fecha(l.fecha_activacion)) + (l.duracion_txt ? ' · ' + esc(l.duracion_txt) : '') + '</div>' : '') : '<span class="text-muted small">Sin vencimiento</span>' + (l.fecha_activacion ? '<div class="small text-muted">Activada ' + esc(s._fecha(l.fecha_activacion)) + '</div>' : '') + (l.estado === 'baja' ? '<div>' + s._badge(est) + '</div>' : '');
      h += '<tr' + (+l.activo === 1 ? '' : ' class="table-secondary"') + '><td><b>' + esc(l.software) + '</b></td><td class="small">' + esc(l.tipo_nombre) + '</td><td>' + clave + '</td>' +
        '<td><div class="small"><b>' + l.usados + '</b> de ' + l.puestos + ' <span class="text-muted">(' + l.libres + ' libres)</span></div><div class="sw-barra ' + (l.usados >= l.puestos ? 'ambar' : 'verde') + '"><span style="width:' + pct + '%"></span></div></td>' +
        '<td>' + venc + '</td><td class="text-end">' + (l.costo != null ? esc(s._money(l.costo)) : '—') + (l.precio_unitario != null ? '<div class="small text-muted">' + esc(s._money(l.precio_unitario)) + ' c/u</div>' : '') + '</td>' +
        '<td class="small">' + esc(l.proveedor || '') + (l.documento ? '<div class="text-muted">' + esc(l.documento) + '</div>' : '') +
          (+l.tiene_factura === 1 ? '<div><a href="#" class="sw-l-fact" data-id="' + l.id + '" title="Ver la factura adjunta" onclick="return false"><i class="bi bi-paperclip"></i> Ver factura</a></div>' : '') + '</td><td class="text-nowrap">' +
        (l.usados > 0 ? '<button class="btn btn-sm btn-outline-primary sw-l-ver me-1" data-sw="' + l.software_id + '" title="Ver dónde está instalado"><i class="bi bi-pc-display"></i></button>' : '') +
        (s.perm.gestionar ? '<button class="btn btn-sm btn-outline-secondary sw-l-edit me-1" data-id="' + l.id + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
          '<button class="btn btn-sm btn-outline-' + (+l.activo === 1 ? 'warning' : 'success') + ' sw-l-baja me-1" data-id="' + l.id + '" title="' + (+l.activo === 1 ? 'Dar de baja' : 'Reactivar') + '"><i class="bi bi-' + (+l.activo === 1 ? 'slash-circle' : 'arrow-counterclockwise') + '"></i></button>' +
          '<button class="btn btn-sm btn-outline-danger sw-l-del" data-id="' + l.id + '" title="Eliminar (solo si nunca se usó)"><i class="bi bi-trash3"></i></button>' : '') + '</td></tr>';
    });
    $('#sw-tb-lic tbody').html(h || '<tr><td colspan="8" class="text-center text-muted">No hay licencias con ese filtro.</td></tr>');
  },

  _formLicencia: function (l) {
    var s = this, nuevo = !l;
    var act = s._cat.filter(function (c) { return +c.activo === 1; });
    if (!act.length) { Toast.warning('Primero agregue un programa al catálogo'); return; }
    l = l || { software_id: '', tipo: 'perpetua', clave: '', puestos: 1, fecha_compra: '', fecha_activacion: '', duracion_valor: '', duracion_unidad: 'meses', fecha_vencimiento: '', precio_unitario: '', costo: '', proveedor: '', documento: '', observaciones: '' };
    var optU = Object.keys(SW_DURACIONES).map(function (k) { return s._opt(k, SW_DURACIONES[k], l.duracion_unidad || 'meses'); }).join('');
    var h = '<form id="form-sw-lic" autocomplete="off"><div class="row g-3">' +
      '<div class="col-md-7"><label class="form-label">Programa <span class="text-danger">*</span></label><select class="form-select" id="swl-f-software_id"><option value="">— Elija —</option>';
    act.forEach(function (c) { h += s._opt(c.id, c.nombre, l.software_id); });
    if (!nuevo && !act.some(function (c) { return +c.id === +l.software_id; })) h += s._opt(l.software_id, l.software, l.software_id);
    h += '</select></div><div class="col-md-5"><label class="form-label">Tipo <span class="text-danger">*</span></label><select class="form-select" id="swl-f-tipo">';
    Object.keys(SW_TIPOS_LIC).forEach(function (k) { h += s._opt(k, SW_TIPOS_LIC[k], l.tipo); });
    h += '</select></div>' +
      '<div class="col-md-8"><label class="form-label">Clave / número de licencia</label><input class="form-control sw-clave" id="swl-f-clave" maxlength="190" value="' + esc(l.clave || '') + '" placeholder="Solo la ven quienes gestionan licencias"></div>' +
      '<div class="col-md-4"><label class="form-label">Puestos <span class="text-danger">*</span></label><input type="number" class="form-control" id="swl-f-puestos" min="1" max="100000" value="' + esc(l.puestos) + '"><div class="form-text">Equipos que cubre.</div></div>' +
      '<div class="col-md-4"><label class="form-label">Fecha de compra</label><input type="date" class="form-control" id="swl-f-fecha_compra" value="' + esc(l.fecha_compra || '') + '"></div>' +
      '<div class="col-md-4"><label class="form-label">Fecha de activación</label><input type="date" class="form-control" id="swl-f-fecha_activacion" value="' + esc(l.fecha_activacion || '') + '"><div class="form-text">Cuándo se activó la clave.</div></div>' +
      '<div class="col-md-4"><label class="form-label">Duración</label><div class="input-group"><input type="number" class="form-control" id="swl-f-duracion_valor" min="1" max="3650" placeholder="Ej. 12" value="' + esc(l.duracion_valor == null ? '' : l.duracion_valor) + '">' +
        '<select class="form-select" id="swl-f-duracion_unidad" style="max-width:112px">' + optU + '</select></div>' +
        '<div class="swl-rapido mt-1">' + [['1 mes', 1, 'meses'], ['3 meses', 3, 'meses'], ['6 meses', 6, 'meses'], ['1 año', 1, 'anios'], ['2 años', 2, 'anios'], ['3 años', 3, 'anios']].map(function (p) {
          return '<a href="#" class="badge text-bg-light border me-1 swl-dur" data-v="' + p[1] + '" data-u="' + p[2] + '" onclick="return false">' + p[0] + '</a>'; }).join('') + '</div></div>' +
      '<div class="col-md-6"><label class="form-label">Vencimiento</label><input type="date" class="form-control" id="swl-f-fecha_vencimiento" value="' + esc(l.fecha_vencimiento || '') + '"><div class="form-text" id="swl-venc-txt">Vacío = no vence.</div></div>' +
      '<div class="col-md-3"><label class="form-label">Precio unitario (S/)</label><input type="number" class="form-control" id="swl-f-precio_unitario" min="0" step="0.01" placeholder="Por puesto" value="' + esc(l.precio_unitario == null ? '' : l.precio_unitario) + '"><div class="form-text">Lo que cuesta cada puesto.</div></div>' +
      '<div class="col-md-3"><label class="form-label">Costo total (S/)</label><input type="number" class="form-control" id="swl-f-costo" min="0" step="0.01" value="' + esc(l.costo == null ? '' : l.costo) + '"><div class="form-text" id="swl-costo-txt">Precio × puestos.</div></div>' +
      '<div class="col-md-6"><label class="form-label">Proveedor</label><input class="form-control" id="swl-f-proveedor" maxlength="100" value="' + esc(l.proveedor || '') + '"></div>' +
      '<div class="col-md-6"><label class="form-label">Factura / orden de compra</label><input class="form-control" id="swl-f-documento" maxlength="60" value="' + esc(l.documento || '') + '"></div>' +
      '<div class="col-12"><label class="form-label"><i class="bi bi-paperclip"></i> Factura o comprobante (PDF o imagen)</label>' +
        (l.id && +l.tiene_factura === 1 ? '<div class="small mb-1" id="swl-fact-actual"><i class="bi bi-file-earmark-check text-success"></i> Actual: <b>' + esc(l.factura_nombre || 'factura') + '</b>' + (l.factura_kb ? ' (' + esc(l.factura_kb) + ' KB)' : '') +
          ' · <a href="' + esc(BASE_URL + 'api/software/factura.php?id=' + l.id) + '" id="swl-fact-ver" target="_blank" rel="noopener">Ver</a> · <label class="text-danger" style="cursor:pointer"><input type="checkbox" id="swl-f-quitar_factura"> Quitar</label></div>' : '') +
        '<input type="file" class="form-control" id="swl-f-factura" accept=".pdf,image/png,image/jpeg,image/webp"><div class="form-text">' + (l.id && +l.tiene_factura === 1 ? 'Si elige otro archivo, reemplaza al actual. ' : '') + 'Máximo 10 MB.</div></div>' +
      '<div class="col-12"><label class="form-label">Observaciones</label><input class="form-control" id="swl-f-observaciones" maxlength="255" value="' + esc(l.observaciones || '') + '"></div></div>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary" id="swl-ok"><i class="bi bi-check-lg"></i> ' + (nuevo ? 'Registrar licencia' : 'Guardar') + '</button></div></form>';
    Modal.abrir(nuevo ? 'Nueva licencia' : 'Editar licencia — ' + l.software, h, 'modal-lg');
    // Activación + duración = vencimiento (se calcula al instante; con duración el campo queda bloqueado)
    var recalcular = function () {
      var act = $('#swl-f-fecha_activacion').val(), val = parseInt($('#swl-f-duracion_valor').val(), 10), uni = $('#swl-f-duracion_unidad').val();
      var $v = $('#swl-f-fecha_vencimiento'), $t = $('#swl-venc-txt');
      if (val > 0) {
        if (!act) { $v.prop('readonly', true).val(''); $t.removeClass('text-success').addClass('text-danger').text('Indique la fecha de activación para calcular el vencimiento.'); return; }
        var venc = s._calcVence(act, val, uni);
        $v.prop('readonly', true).val(venc || '');
        $t.removeClass('text-danger').addClass('text-success').html('<i class="bi bi-calculator"></i> Calculado: activación + ' + val + ' ' + esc(SW_DURACIONES[uni] || '') + (venc ? ' → vence el <b>' + esc(s._fecha(venc)) + '</b>' : ''));
      } else {
        $v.prop('readonly', false);
        $t.removeClass('text-success text-danger').text('Vacío = no vence. O indique activación y duración para calcularlo.');
      }
    };
    $('#form-sw-lic').on('input change', '#swl-f-fecha_activacion, #swl-f-duracion_valor, #swl-f-duracion_unidad', recalcular);
    $('#form-sw-lic').on('click', '.swl-dur', function () {
      $('#swl-f-duracion_valor').val($(this).data('v')); $('#swl-f-duracion_unidad').val($(this).data('u'));
      if (!$('#swl-f-fecha_activacion').val()) $('#swl-f-fecha_activacion').val(s._hoy());
      recalcular();
    });
    if (l.duracion_valor) recalcular();
    // Precio unitario x puestos = costo total (automático; sin precio unitario el total se escribe a mano)
    var recostear = function () {
      var pu = parseFloat($('#swl-f-precio_unitario').val()), n = parseInt($('#swl-f-puestos').val(), 10), $c = $('#swl-f-costo'), $t = $('#swl-costo-txt');
      if (!isNaN(pu) && pu >= 0) {
        if (!(n > 0)) { $c.prop('readonly', true).val(''); $t.removeClass('text-success').text('Indique los puestos para calcular el total.'); return; }
        $c.prop('readonly', true).val((Math.round(pu * n * 100) / 100).toFixed(2));
        $t.addClass('text-success').html('<i class="bi bi-calculator"></i> ' + esc(s._money(pu)) + ' × ' + n + ' puesto(s)');
      } else {
        $c.prop('readonly', false); $t.removeClass('text-success').text('Escriba el total, o el precio unitario para calcularlo.');
      }
    };
    $('#form-sw-lic').on('input change', '#swl-f-precio_unitario, #swl-f-puestos', recostear);
    if (l.precio_unitario != null && l.precio_unitario !== '') recostear();
    $('#form-sw-lic').off('submit').on('submit', function (e) {
      e.preventDefault();
      var d = { accion: 'guardar', id: nuevo ? 0 : l.id };
      ['software_id', 'tipo', 'clave', 'puestos', 'fecha_compra', 'fecha_activacion', 'duracion_valor', 'duracion_unidad', 'fecha_vencimiento', 'precio_unitario', 'costo', 'proveedor', 'documento', 'observaciones'].forEach(function (k) { d[k] = $.trim($('#swl-f-' + k).val() || ''); });
      if (!d.software_id) { Toast.warning('Elija el programa'); return; }
      var fArch = $('#swl-f-factura')[0] && $('#swl-f-factura')[0].files && $('#swl-f-factura')[0].files[0];
      var quitar = $('#swl-f-quitar_factura').is(':checked');
      if (fArch && fArch.size > 10 * 1024 * 1024) { Toast.warning('La factura supera los 10 MB'); return; }
      $('#swl-ok').prop('disabled', true);
      s._post('api/software/licencias.php', d).then(function (r) {
        var lid = (r && r.id) || (nuevo ? 0 : l.id), msg = (r && r.mensaje) || 'Guardado';
        var fin = function (extra) { Modal.cerrar(); Toast.success(msg + (extra || '')); s._refrescar(); };
        if (fArch && lid) {
          var fd = new FormData(); fd.append('accion', 'subir'); fd.append('licencia_id', lid); fd.append('archivo', fArch);
          Api.postFiles('api/software/factura.php', fd).then(function () { fin(' Factura adjunta.'); })
            .catch(function () { Modal.cerrar(); Toast.warning('La licencia se guardó, pero la factura no se pudo subir. Edite la licencia e inténtelo de nuevo.'); s._refrescar(); });
        } else if (quitar && lid) {
          s._post('api/software/factura.php', { accion: 'quitar', licencia_id: lid }).then(function () { fin(' Factura retirada.'); }).catch(function () { fin(); });
        } else fin();
      }).catch(function (er) { $('#swl-ok').prop('disabled', false); s._errores('swl-f-', er); });
    });
  },
  _verFactura: function (l) {
    if (!l || !l.id) return;
    Visor.abrir('api/software/factura.php?id=' + encodeURIComponent(l.id), 'Factura · ' + (l.software || 'licencia'), { pdf: +l.factura_pdf === 1, nombre: l.factura_nombre });
  },
  _bajaLicencia: function (id) {
    var s = this, l = s._buscarLic(id);
    if (!l) return;
    var baja = +l.activo === 1;
    Swal.fire({
      title: baja ? '¿Dar de baja esta licencia?' : '¿Reactivar esta licencia?',
      text: baja ? 'Deja de contar como puesto vigente. Queda en el historial.' : 'Vuelve a contar como puesto vigente.',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar', confirmButtonColor: '#2563eb'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      s._post('api/software/licencias.php', { accion: 'baja', id: id }).then(function (x) { Toast.success((x && x.mensaje) || 'Listo'); s._refrescar(); }).catch(function () { });
    });
  },
  _eliminarLicencia: function (id) {
    var s = this;
    Swal.fire({
      title: '¿Eliminar esta licencia?', text: 'Solo se puede si nunca se usó en un equipo; si no, use «Dar de baja».',
      icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      s._post('api/software/licencias.php', { accion: 'eliminar', id: id }).then(function (x) { Toast.success((x && x.mensaje) || 'Eliminada'); s._refrescar(); }).catch(function () { });
    });
  },

  /* ---------------------------------------------------------- campos de instalación (se reutilizan) */
  _camposInstalar: function (pref, o) {
    var s = this;
    o = o || {};
    var h = '';
    if (!o.equipoFijo) {
      h += '<div class="col-12"><label class="form-label">Equipo <span class="text-danger">*</span></label>' +
        '<input class="form-control" id="' + pref + 'equipo" list="' + pref + 'eq-dl" placeholder="Escriba el código o el modelo…" autocomplete="off"><datalist id="' + pref + 'eq-dl"></datalist></div>';
    }
    h += '<div class="col-md-6"><label class="form-label">Programa <span class="text-danger">*</span></label><select class="form-select" id="' + pref + 'software"><option value="">— Elija —</option>';
    s._cat.filter(function (c) { return +c.activo === 1; }).forEach(function (c) { h += s._opt(c.id, c.nombre + (c.autorizacion === 'prohibido' ? ' (no autorizado)' : ''), o.software_id); });
    h += '</select></div><div class="col-md-3"><label class="form-label">Versión</label><input class="form-control" id="' + pref + 'version" maxlength="40" placeholder="Ej: 23H2, 2021" value="' + esc(o.version || '') + '"></div>' +
      '<div class="col-md-3"><label class="form-label">Fecha de instalación</label><input type="date" class="form-control" id="' + pref + 'fecha" max="' + s._hoy() + '" value="' + esc(o.fecha || s._hoy()) + '"></div>' +
      '<div class="col-md-8" id="' + pref + 'zona-lic"><label class="form-label">Licencia</label><select class="form-select" id="' + pref + 'licencia"></select><div class="form-text" id="' + pref + 'lic-ayuda"></div></div>' +
      '<div class="col-md-4"><label class="form-label">Observaciones</label><input class="form-control" id="' + pref + 'obs" maxlength="255"></div>';
    return h;
  },
  _bindInstalar: function (pref, o) {
    var s = this;
    o = o || {};
    var cargar = function () {
      var id = +$('#' + pref + 'software').val() || 0;
      var c = s._buscarCat(id);
      var $z = $('#' + pref + 'zona-lic'), $l = $('#' + pref + 'licencia');
      if (!id || !c) { $z.addClass('d-none'); return; }
      if (+c.requiere_licencia !== 1) { $z.removeClass('d-none'); $l.html('<option value="">No requiere licencia</option>').prop('disabled', true); $('#' + pref + 'lic-ayuda').text('Este programa es libre o gratuito: no consume puestos.'); return; }
      $z.removeClass('d-none'); $l.prop('disabled', false);
      Api.get('api/software/licencias.php', { modo: 'select', software_id: id }).then(function (ls) {
        ls = ls || [];
        var hh = ls.length ? s._opt('auto', 'Automática (la que venza primero y tenga puesto libre)', o.licencia || 'auto') : '';
        hh += s._opt('', 'Sin licencia (queda como observación)', o.licencia === '' ? '' : '__');
        ls.forEach(function (l) {
          hh += s._opt(l.id, l.tipo_nombre + (l.clave_ult4 ? ' ' + l.clave_ult4 : '') + ' · ' + l.libres + ' libre(s) de ' + l.puestos + (l.fecha_vencimiento ? ' · vence ' + s._fecha(l.fecha_vencimiento) : ''), o.licencia);
        });
        $l.html(hh);
        if (!ls.length) $l.val('');
        $('#' + pref + 'lic-ayuda').html(ls.length ? '' : '<span class="text-danger">No hay licencias con puestos libres para este programa.</span> Se registrará sin licencia.');
      }).catch(function () { });
    };
    $('#' + pref + 'software').off('change.swi').on('change.swi', cargar);
    cargar();
  },
  _leerInstalar: function (pref) {
    return {
      software_id: $('#' + pref + 'software').val() || '', version: $.trim($('#' + pref + 'version').val() || ''),
      fecha: $('#' + pref + 'fecha').val() || '', observaciones: $.trim($('#' + pref + 'obs').val() || ''),
      licencia: $('#' + pref + 'licencia').prop('disabled') ? '' : ($('#' + pref + 'licencia').val() || '')
    };
  },
  /* lista de equipos activos para elegir (datalist) */
  _cargarEquipos: function (cb) {
    var s = this;
    if (s._equipos) { cb(s._equipos); return; }
    Api.get('api/equipos/select.php', { modo: 'activos' }).then(function (l) { s._equipos = l || []; cb(s._equipos); }).catch(function () { cb([]); });
  },
  _resolverEquipo: function (txt) {
    txt = $.trim(txt || '').toLowerCase();
    if (!txt) return null;
    var ex = this._equipos || [];
    var m = ex.filter(function (e) { return e.texto.toLowerCase() === txt; })[0] ||
      ex.filter(function (e) { return e.texto.toLowerCase().indexOf(txt + ' ') === 0 || e.texto.toLowerCase().indexOf(txt + ' —') === 0; })[0];
    return m || null;
  },

  _formInstalar: function (pre) {
    var s = this;
    pre = pre || {};
    s._cargarEquipos(function (eqs) {
      var h = '<form id="form-sw-inst" autocomplete="off"><div class="row g-3">' + s._camposInstalar('swi-', pre) + '</div>' +
        '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary" id="swi-ok"><i class="bi bi-check-lg"></i> Registrar</button></div></form>';
      Modal.abrir('Registrar software instalado', h, 'modal-lg');
      $('#swi-eq-dl').html(eqs.map(function (e) { return '<option value="' + esc(e.texto) + '">'; }).join(''));
      s._bindInstalar('swi-', pre);
      $('#form-sw-inst').off('submit').on('submit', function (ev) {
        ev.preventDefault();
        var eq = s._resolverEquipo($('#swi-equipo').val());
        if (!eq) { Toast.warning('Elija un equipo de la lista'); return; }
        var d = s._leerInstalar('swi-');
        if (!d.software_id) { Toast.warning('Elija el programa'); return; }
        d.accion = 'instalar'; d.equipo_id = eq.id;
        $('#swi-ok').prop('disabled', true);
        s._post('api/software/instalar.php', d).then(function (r) {
          Modal.cerrar(); (r && r.aviso ? Toast.warning : Toast.success)((r && r.mensaje) || 'Registrado'); s._refrescar();
        }).catch(function () { $('#swi-ok').prop('disabled', false); });
      });
    });
  },

  /* ---------------------------------------------------------- instalar en varios equipos */
  _formMasivo: function () {
    var s = this;
    s._cargarEquipos(function (eqs) {
      var h = '<form id="form-sw-masivo" autocomplete="off"><div class="row g-3">' +
        '<div class="col-md-6"><label class="form-label">Programa <span class="text-danger">*</span></label><select class="form-select" id="swm-software"><option value="">— Elija —</option>';
      s._cat.filter(function (c) { return +c.activo === 1; }).forEach(function (c) { h += s._opt(c.id, c.nombre, ''); });
      h += '</select></div><div class="col-md-3"><label class="form-label">Versión</label><input class="form-control" id="swm-version" maxlength="40"></div>' +
        '<div class="col-md-3"><label class="form-label">Fecha</label><input type="date" class="form-control" id="swm-fecha" max="' + s._hoy() + '" value="' + s._hoy() + '"></div>' +
        '<div class="col-md-8" id="swm-zona-lic"><label class="form-label">Licencia</label><select class="form-select" id="swm-licencia"><option value="auto">Automática (usa los puestos libres que haya)</option><option value="">Sin licencia (queda como observación)</option></select>' +
        '<div class="form-text">Si faltan puestos, los equipos que no alcancen quedan sin licencia y se marcan como observación.</div></div>' +
        '<div class="col-12"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1"><label class="form-label mb-0">Equipos (<span id="swm-n">0</span> elegidos)</label>' +
        '<div class="d-flex gap-2 align-items-center"><label class="small mb-0"><input type="checkbox" class="form-check-input" id="swm-computo" checked> Solo cómputo</label>' +
        '<input class="form-control form-control-sm" id="swm-q" placeholder="Filtrar…" style="width:180px"><button type="button" class="btn btn-sm btn-outline-secondary" id="swm-todos">Marcar visibles</button><button type="button" class="btn btn-sm btn-outline-secondary" id="swm-ninguno">Ninguno</button></div></div>' +
        '<div class="border rounded p-2" id="swm-lista" style="max-height:260px;overflow:auto"></div></div></div>' +
        '<div id="swm-res" class="mt-2"></div>' +
        '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> <button type="submit" class="btn btn-primary" id="swm-ok"><i class="bi bi-check-lg"></i> Instalar en los elegidos</button></div></form>';
      Modal.abrir('Instalar un programa en varios equipos', h, 'modal-xl');
      var sel = {};
      var pintar = function () {
        var q = ($('#swm-q').val() || '').toLowerCase(), soloC = $('#swm-computo').prop('checked'), hh = '', n = 0;
        eqs.forEach(function (e) {
          if (soloC && e.familia && e.familia !== 'computo') return;
          if (q && e.texto.toLowerCase().indexOf(q) < 0) return;
          n++;
          hh += '<div class="form-check"><input type="checkbox" class="form-check-input swm-chk" id="swm-e' + e.id + '" data-id="' + e.id + '"' + (sel[e.id] ? ' checked' : '') + '><label class="form-check-label small" for="swm-e' + e.id + '">' + esc(e.texto) + '</label></div>';
        });
        $('#swm-lista').html(hh || '<div class="text-muted small">Sin equipos con ese filtro.</div>');
        $('#swm-n').text(Object.keys(sel).filter(function (k) { return sel[k]; }).length);
      };
      pintar();
      $('#swm-q').on('input', pintar); $('#swm-computo').on('change', pintar);
      $('#swm-lista').on('change', '.swm-chk', function () { sel[$(this).attr('data-id')] = $(this).prop('checked'); $('#swm-n').text(Object.keys(sel).filter(function (k) { return sel[k]; }).length); });
      $('#swm-todos').on('click', function () { $('#swm-lista .swm-chk').each(function () { sel[$(this).attr('data-id')] = true; }); pintar(); });
      $('#swm-ninguno').on('click', function () { sel = {}; pintar(); });
      $('#swm-software').on('change', function () {
        var c = s._buscarCat($(this).val());
        $('#swm-zona-lic').toggleClass('d-none', !!c && +c.requiere_licencia !== 1);
      });
      $('#form-sw-masivo').off('submit').on('submit', function (ev) {
        ev.preventDefault();
        var ids = Object.keys(sel).filter(function (k) { return sel[k]; });
        if (!$('#swm-software').val()) { Toast.warning('Elija el programa'); return; }
        if (!ids.length) { Toast.warning('Marque al menos un equipo'); return; }
        $('#swm-ok').prop('disabled', true);
        s._post('api/software/instalar.php', {
          accion: 'masivo', software_id: $('#swm-software').val(), equipo_ids: ids.join(','), version: $.trim($('#swm-version').val()),
          fecha: $('#swm-fecha').val(), licencia: $('#swm-licencia').val()
        }).then(function (r) {
          $('#swm-ok').prop('disabled', false);
          var om = (r && r.omitidos) || [];
          $('#swm-res').html('<div class="alert ' + (om.length ? 'alert-warning' : 'alert-success') + ' py-2 small mb-0">' + esc(r.mensaje) +
            (om.length ? '<ul class="mb-0 mt-1">' + om.slice(0, 12).map(function (o) { return '<li><b>' + esc(o.codigo) + '</b>: ' + esc(o.motivo) + '</li>'; }).join('') + (om.length > 12 ? '<li>…</li>' : '') + '</ul>' : '') + '</div>');
          sel = {}; pintar(); s._refrescar();
        }).catch(function () { $('#swm-ok').prop('disabled', false); });
      });
    });
  },

  /* ---------------------------------------------------------- carga masiva (texto / CSV) */
  _formImportar: function () {
    var s = this;
    var h = '<div class="alert alert-light border small py-2">Una línea por programa instalado: <code>CODIGO;Programa;Versión;Licencia</code><br>' +
      'Ejemplo: <code>EQ-0012;Microsoft Office;2021</code> · <code>EQ-0012;Google Chrome</code> · agregue <code>;sin</code> al final para no asignar licencia. ' +
      'Puede pegar el texto o cargar un archivo CSV/TXT (también sirve «Guardar como CSV» desde Excel).</div>' +
      '<div class="mb-2"><input type="file" class="form-control form-control-sm" id="swx-archivo" accept=".csv,.txt,text/csv,text/plain"></div>' +
      '<textarea class="form-control sw-clave" id="swx-texto" rows="8" placeholder="EQ-0012;Microsoft Office;2021&#10;EQ-0012;Windows 11 Pro;23H2"></textarea>' +
      (s.perm.gestionar ? '<div class="form-check mt-2"><input type="checkbox" class="form-check-input" id="swx-crear"><label class="form-check-label small" for="swx-crear">Agregar al catálogo los programas que no existan (quedan «En evaluación» para revisarlos)</label></div>' : '') +
      '<div id="swx-res" class="mt-2" style="max-height:240px;overflow:auto"></div>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> ' +
      '<button type="button" class="btn btn-outline-primary" id="swx-validar"><i class="bi bi-search"></i> Revisar sin guardar</button> ' +
      '<button type="button" class="btn btn-primary" id="swx-ok"><i class="bi bi-upload"></i> Importar</button></div>';
    Modal.abrir('Carga masiva de software', h, 'modal-lg');
    $('#swx-archivo').on('change', function () {
      var f = this.files && this.files[0];
      if (!f) return;
      var rd = new FileReader();
      rd.onload = function () { $('#swx-texto').val(String(rd.result || '')); };
      rd.readAsText(f, 'UTF-8');
    });
    var enviar = function (solo) {
      var txt = $('#swx-texto').val() || '';
      if (!$.trim(txt)) { Toast.warning('Pegue las líneas o cargue un archivo'); return; }
      $('#swx-ok,#swx-validar').prop('disabled', true);
      s._post('api/software/instalar.php', { accion: 'importar', lineas: txt, solo_validar: solo ? 1 : 0, crear_faltantes: $('#swx-crear').prop('checked') ? 1 : 0 }).then(function (r) {
        $('#swx-ok,#swx-validar').prop('disabled', false);
        var th = '<div class="alert ' + (r.errores ? 'alert-warning' : 'alert-success') + ' py-2 small">' + esc(r.mensaje) + '</div>';
        var malas = (r.filas || []).filter(function (f) { return f.estado !== 'ok'; });
        if (malas.length) {
          th += '<table class="table table-sm table-bordered small mb-0"><thead><tr><th>Línea</th><th>Equipo</th><th>Programa</th><th>Resultado</th></tr></thead><tbody>' +
            malas.slice(0, 100).map(function (f) {
              return '<tr><td>' + f.linea + '</td><td>' + esc(f.codigo) + '</td><td>' + esc(f.programa) + '</td><td><span class="badge ' + (f.estado === 'error' ? 'text-bg-danger' : 'text-bg-secondary') + '">' + (f.estado === 'error' ? 'Error' : 'Omitida') + '</span> ' + esc(f.detalle) + '</td></tr>';
            }).join('') + '</tbody></table>';
        }
        $('#swx-res').html(th);
        if (!solo) { Toast.success(r.mensaje); s._refrescar(); }
      }).catch(function () { $('#swx-ok,#swx-validar').prop('disabled', false); });
    };
    $('#swx-validar').on('click', function () { enviar(true); });
    $('#swx-ok').on('click', function () { enviar(false); });
  },

  /* ---------------------------------------------------------- editar / retirar una instalación */
  _formEditar: function (f) {
    var s = this;
    if (!f) return;
    var h = '<form id="form-sw-edit" autocomplete="off"><div class="alert alert-light border py-2 small mb-3"><b>' + esc(f.software) + '</b> en <b>' + esc(f.codigo) + '</b></div><div class="row g-3">' +
      '<div class="col-md-4"><label class="form-label">Versión</label><input class="form-control" id="swe2-version" maxlength="40" value="' + esc(f.version || '') + '"></div>' +
      '<div class="col-md-8" id="swe2-zona"><label class="form-label">Licencia</label><select class="form-select" id="swe2-licencia"></select><div class="form-text" id="swe2-ayuda"></div></div>' +
      '<div class="col-12"><label class="form-label">Observaciones</label><input class="form-control" id="swe2-obs" maxlength="255" value="' + esc(f.observaciones || '') + '"></div></div>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary" id="swe2-ok"><i class="bi bi-check-lg"></i> Guardar</button></div></form>';
    Modal.abrir('Editar instalación', h, 'modal-md');
    if (+f.requiere_licencia !== 1) {
      $('#swe2-licencia').html('<option value="">No requiere licencia</option>').prop('disabled', true);
    } else {
      Api.get('api/software/licencias.php', { modo: 'select', software_id: f.software_id }).then(function (ls) {
        ls = ls || [];
        var hh = s._opt('', 'Sin licencia (queda como observación)', f.licencia_id ? '__' : '');
        var actual = f.licencia_id ? String(f.licencia_id) : '';
        var hay = false;
        ls.forEach(function (l) {
          if (String(l.id) === actual) hay = true;
          hh += s._opt(l.id, l.tipo_nombre + (l.clave_ult4 ? ' ' + l.clave_ult4 : '') + ' · ' + l.libres + ' libre(s) de ' + l.puestos + (l.fecha_vencimiento ? ' · vence ' + s._fecha(l.fecha_vencimiento) : ''), actual);
        });
        if (actual && !hay) hh += s._opt(actual, 'Licencia actual ' + (f.lic_ult4 || ('#' + actual)) + (f.lic_vence && f.lic_vence < s._hoy() ? ' (vencida)' : ' (sin puestos libres)'), actual);
        if (!actual && ls.length) hh = s._opt('auto', 'Automática (la primera con puesto libre)', '__') + hh;
        $('#swe2-licencia').html(hh).val(actual);
      }).catch(function () { });
    }
    $('#form-sw-edit').off('submit').on('submit', function (ev) {
      ev.preventDefault();
      var d = { accion: 'editar', id: f.id, version: $.trim($('#swe2-version').val()), observaciones: $.trim($('#swe2-obs').val()) };
      if (!$('#swe2-licencia').prop('disabled')) d.licencia = $('#swe2-licencia').val();
      $('#swe2-ok').prop('disabled', true);
      s._post('api/software/instalar.php', d).then(function (r) {
        Modal.cerrar(); Toast.success((r && r.mensaje) || 'Guardado'); s._refrescar();
      }).catch(function () { $('#swe2-ok').prop('disabled', false); });
    });
  },
  _retirar: function (f, despues) {
    var s = this;
    if (!f) return;
    var MOT = ['Ya no se usa', 'Licencia vencida o sin puestos', 'Reemplazado por otro programa', 'Programa no autorizado', 'Equipo reasignado o reformateado', 'Instalación por error', 'Otro'];
    Swal.fire({
      title: '¿Retirar «' + esc(f.software) + '» de ' + esc(f.codigo) + '?',
      html: '<div class="text-start"><p class="small text-muted mb-3">Queda en el historial como desinstalado y el puesto de licencia se libera.</p>' +
        '<label class="form-label small fw-semibold mb-1" for="swr-mot">Motivo *</label>' +
        '<select id="swr-mot" class="form-select mb-3">' + MOT.map(function (m) { return '<option>' + m + '</option>'; }).join('') + '</select>' +
        '<label class="form-label small fw-semibold mb-1" for="swr-det">Detalle <span class="text-muted fw-normal" id="swr-req">(opcional)</span></label>' +
        '<textarea id="swr-det" class="form-control" rows="2" maxlength="150" placeholder="Ej.: se cambió a otra versión, pedido de la jefatura…"></textarea></div>',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, retirar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626', focusConfirm: false,
      didOpen: function () {
        var $m = $('#swr-mot'), $d = $('#swr-det');
        $m.on('change', function () { $('#swr-req').text($m.val() === 'Otro' ? '(obligatorio)' : '(opcional)'); if ($m.val() === 'Otro') $d.trigger('focus'); });
        setTimeout(function () { $m.trigger('focus'); }, 60);
      },
      preConfirm: function () {
        var m = $('#swr-mot').val() || '', d = $.trim($('#swr-det').val() || '');
        if (m === 'Otro' && !d) { Swal.showValidationMessage('Escriba el motivo en el detalle.'); return false; }
        return d ? (m === 'Otro' ? d : m + ' — ' + d) : m;
      }
    }).then(function (r) {
      if (!r.isConfirmed) return;
      s._post('api/software/instalar.php', { accion: 'desinstalar', id: f.id, motivo: r.value || '' }).then(function (x) {
        Toast.success((x && x.mensaje) || 'Retirado'); s._refrescar(); if (despues) despues();
      }).catch(function () { });
    });
  },

  /* ---------------------------------------------------------- FICHA DE SOFTWARE DE UN EQUIPO (modal) */
  abrirEquipo: function (id) {
    var s = Software;
    Api.get('api/software/equipo.php', { equipo_id: id }).then(function (d) {
      if (!d) return;
      s._pintarEquipo(d);
    }).catch(function () { });
  },
  _pintarEquipo: function (d) {
    var s = this, e = d.equipo, ed = +d.editable === 1 && +d.puede_registrar === 1;
    s._eqActual = d;
    var estados = { en_stock: 'En stock', asignado: 'Asignado', en_prestamo: 'En préstamo', en_revision: 'En revisión', en_mantenimiento: 'En mantenimiento', en_reparacion_externa: 'Reparación externa', obsoleto: 'Obsoleto', dado_de_baja: 'De baja' };
    var verif;
    if (!e.software_verificado_en) verif = +d.instalados.length ? '<span class="badge text-bg-warning text-dark">Inventario sin confirmar</span>' : '';
    else verif = '<span class="badge ' + (d.dias_verificado > d.dias_verificacion ? 'text-bg-warning text-dark' : 'text-bg-success') + '"><i class="bi bi-patch-check"></i> Confirmado el ' + esc(s._fecha(e.software_verificado_en)) + (e.verificado_por ? ' por ' + esc(e.verificado_por) : '') + '</span>';
    var h = '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3"><div><h5 class="mb-0">' + esc(e.codigo) + ' · ' + esc(e.tipo) + '</h5>' +
      '<div class="text-muted small">' + esc(((e.marca || '') + ' ' + (e.modelo || '')).trim()) + ' · ' + esc(estados[e.estado] || e.estado) + (e.asignado_a ? ' · ' + esc(e.asignado_a) : '') + '</div></div>' +
      '<div class="text-end">' + verif + (ed && d.instalados.length ? '<div class="mt-1"><button class="btn btn-sm btn-outline-success" id="swq-conf"><i class="bi bi-patch-check"></i> Confirmar que está correcto</button></div>' : '') + '</div></div>';

    h += '<h6>Software instalado <span class="badge text-bg-primary">' + d.instalados.length + '</span></h6>';
    if (d.instalados.length) {
      h += '<div class="table-responsive"><table class="table table-sm table-bordered align-middle" id="swq-tb"><thead><tr><th>Programa</th><th>Versión</th><th>Licencia</th><th>Instalado</th><th></th></tr></thead><tbody>';
      d.instalados.forEach(function (i) {
        var al = '';
        if (i.autorizacion === 'prohibido') al += ' <span class="badge text-bg-danger">No autorizado</span>';
        else if (i.autorizacion === 'evaluacion') al += ' <span class="badge text-bg-warning text-dark">En evaluación</span>';
        var lic;
        if (+i.requiere_licencia !== 1) lic = '<span class="text-muted small">No requiere</span>';
        else if (!i.licencia_id) lic = '<span class="badge text-bg-danger">Sin licencia</span>';
        else lic = '<span class="small">' + esc(i.lic_tipo_nombre || '') + '</span> <span class="sw-clave text-muted">' + esc(i.lic_ult4 || '') + '</span>' + (i.lic_estado === 'vencida' ? ' <span class="badge text-bg-danger">Vencida</span>' : (i.lic_estado === 'por_vencer' ? ' <span class="badge text-bg-warning text-dark">Por vencer</span>' : ''));
        h += '<tr><td><b>' + esc(i.software) + '</b>' + al + '<div class="small text-muted">' + esc(i.categoria_nombre) + '</div></td><td>' + (i.version ? esc(i.version) : '<span class="text-muted">—</span>') + '</td><td>' + lic + '</td>' +
          '<td class="small">' + esc(s._fecha(i.fecha_instalacion)) + (i.instalado_por ? '<div class="text-muted">' + esc(i.instalado_por) + '</div>' : '') + '</td><td class="text-nowrap">' +
          (ed ? '<button class="btn btn-sm btn-outline-secondary swq-edit me-1" data-id="' + i.id + '" title="Editar"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-outline-danger swq-del" data-id="' + i.id + '" title="Retirar"><i class="bi bi-box-arrow-left"></i></button>' : '') + '</td></tr>';
      });
      h += '</tbody></table></div>';
    } else {
      h += '<div class="alert alert-warning py-2 small">Este equipo todavía no tiene software registrado.</div>';
    }

    if (ed) {
      h += '<div class="border rounded p-3 mb-3 bg-light" id="swq-panel"><div class="fw-semibold mb-2" id="swq-panel-t"><i class="bi bi-plus-circle me-1"></i>Agregar un programa</div>';
      if (d.sugeridos.length) h += '<div class="mb-2 small">Estándar que falta: ' + d.sugeridos.map(function (x) { return '<a href="#" class="badge text-bg-light border text-dark swq-sug me-1" data-id="' + x.id + '" onclick="return false">+ ' + esc(x.nombre) + '</a>'; }).join('') + '</div>';
      h += '<form id="swq-form" autocomplete="off"><div class="row g-2">' + s._camposInstalar('swq-', { equipoFijo: true }) + '</div>' +
        '<div class="text-end mt-2"><button type="submit" class="btn btn-primary btn-sm" id="swq-ok"><i class="bi bi-check-lg"></i> Agregar</button></div></form></div>';
    }
    if (d.historial.length) {
      h += '<details class="mb-2"><summary class="small text-muted" style="cursor:pointer">Historial de programas retirados (' + d.historial.length + ')</summary><table class="table table-sm table-bordered small mt-1 mb-0"><thead><tr><th>Programa</th><th>Versión</th><th>Instalado</th><th>Retirado</th></tr></thead><tbody>' +
        d.historial.map(function (i) { return '<tr><td>' + esc(i.software) + '</td><td>' + esc(i.version || '') + '</td><td>' + esc(s._fecha(i.fecha_instalacion)) + '</td><td>' + esc(s._fecha(i.fecha_desinstalacion)) + (i.desinstalado_por ? ' · ' + esc(i.desinstalado_por) : '') + '</td></tr>'; }).join('') + '</tbody></table></details>';
    }
    h += '<div class="text-end"><a href="#" class="btn btn-outline-secondary btn-sm" id="swq-todo"><i class="bi bi-list-ul"></i> Ver en el listado</a></div>';
    Modal.abrir('Software — ' + e.codigo, h, 'modal-xl');

    if (ed) {
      s._bindInstalar('swq-', {});
      $('#swq-form').off('submit').on('submit', function (ev) {
        ev.preventDefault();
        var x = s._leerInstalar('swq-');
        if (!x.software_id) { Toast.warning('Elija el programa'); return; }
        x.accion = 'instalar'; x.equipo_id = e.id;
        $('#swq-ok').prop('disabled', true);
        s._post('api/software/instalar.php', x).then(function (r) {
          (r && r.aviso ? Toast.warning : Toast.success)((r && r.mensaje) || 'Registrado'); s._refrescar(); s.abrirEquipo(e.id);
        }).catch(function () { $('#swq-ok').prop('disabled', false); });
      });
      $('#swq-panel').off('click.swq').on('click.swq', '.swq-sug', function () {
        $('#swq-software').val(String($(this).attr('data-id'))).trigger('change'); $('#swq-version').focus();
      });
      $('#swq-conf').off('click').on('click', function () {
        s._post('api/software/instalar.php', { accion: 'verificar', equipo_ids: e.id }).then(function (r) { Toast.success((r && r.mensaje) || 'Confirmado'); s._refrescar(); s.abrirEquipo(e.id); }).catch(function () { });
      });
      $('#swq-tb').off('click.swq')
        .on('click.swq', '.swq-del', function () {
          var i = s._instDe($(this).attr('data-id'));
          s._retirar({ id: i.id, software: i.software, codigo: e.codigo }, function () { s.abrirEquipo(e.id); });
        })
        .on('click.swq', '.swq-edit', function () { s._editarEnPanel(s._instDe($(this).attr('data-id')), e); });
    }
    $('#swq-todo').off('click').on('click', function (ev) {
      ev.preventDefault();
      Modal.cerrar();
      var abrir = function () { s._ir('inst', { equipo_id: e.id, codigo: e.codigo, estado: 'todos' }); };
      if ($('#sw-raiz').length) setTimeout(abrir, 350);
      else { Router.ir('software/lista', {}); setTimeout(function () { if (App.modulos.software) App.modulos.software._ir('inst', { equipo_id: e.id, codigo: e.codigo, estado: 'todos' }); }, 1200); }
    });
  },
  _instDe: function (id) {
    return (this._eqActual.instalados || []).filter(function (i) { return String(i.id) === String(id); })[0];
  },
  /* edita una instalacion dentro de la misma ventana del equipo */
  _editarEnPanel: function (i, e) {
    var s = this;
    if (!i) return;
    $('#swq-panel-t').html('<i class="bi bi-pencil me-1"></i>Editar «' + esc(i.software) + '»');
    var h = '<div class="row g-2"><div class="col-md-4"><label class="form-label">Versión</label><input class="form-control" id="swq2-version" maxlength="40" value="' + esc(i.version || '') + '"></div>' +
      '<div class="col-md-5"><label class="form-label">Licencia</label><select class="form-select" id="swq2-licencia"></select></div>' +
      '<div class="col-md-3"><label class="form-label">Observaciones</label><input class="form-control" id="swq2-obs" maxlength="255" value="' + esc(i.observaciones || '') + '"></div></div>' +
      '<div class="text-end mt-2"><button type="button" class="btn btn-secondary btn-sm" id="swq2-cancel">Cancelar</button> <button type="button" class="btn btn-primary btn-sm" id="swq2-ok"><i class="bi bi-check-lg"></i> Guardar cambios</button></div>';
    $('#swq-panel form, #swq-panel .mb-2').hide();
    $('#swq-panel').append('<div id="swq2">' + h + '</div>');
    if (+i.requiere_licencia !== 1) $('#swq2-licencia').html('<option value="">No requiere licencia</option>').prop('disabled', true);
    else {
      Api.get('api/software/licencias.php', { modo: 'select', software_id: i.software_id }).then(function (ls) {
        var actual = i.licencia_id ? String(i.licencia_id) : '', hay = false;
        var hh = s._opt('', 'Sin licencia (queda como observación)', actual ? '__' : '');
        (ls || []).forEach(function (l) {
          if (String(l.id) === actual) hay = true;
          hh += s._opt(l.id, l.tipo_nombre + (l.clave_ult4 ? ' ' + l.clave_ult4 : '') + ' · ' + l.libres + ' libre(s)' + (l.fecha_vencimiento ? ' · vence ' + s._fecha(l.fecha_vencimiento) : ''), actual);
        });
        if (actual && !hay) hh += s._opt(actual, 'Licencia actual ' + (i.lic_ult4 || '#' + actual), actual);
        $('#swq2-licencia').html(hh).val(actual);
      }).catch(function () { });
    }
    $('#swq2-cancel').on('click', function () { s.abrirEquipo(e.id); });
    $('#swq2-ok').on('click', function () {
      var d = { accion: 'editar', id: i.id, version: $.trim($('#swq2-version').val()), observaciones: $.trim($('#swq2-obs').val()) };
      if (!$('#swq2-licencia').prop('disabled')) d.licencia = $('#swq2-licencia').val();
      s._post('api/software/instalar.php', d).then(function (r) { Toast.success((r && r.mensaje) || 'Guardado'); s._refrescar(); s.abrirEquipo(e.id); }).catch(function () { });
    });
  },

  /* ---------------------------------------------------------- descargas */
  _exportar: function (reporte, formato, extra) {
    var q = $.param($.extend({ reporte: reporte, formato: formato, csrf_token: CSRF.token }, extra || {}));
    fetch(BASE_URL + 'api/software/exportar.php?' + q, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) return r.json().then(function (j) { throw j; }, function () { throw { message: 'Error (' + r.status + ')' }; });
      var m = /filename="?([^";]+)"?/.exec(r.headers.get('Content-Disposition') || '');
      var nombre = m ? m[1] : ('software_' + reporte + (formato === 'csv' ? '.csv' : '.xls'));
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
App.registrar('software', Software);

/* ============================================================
   SoftwarePicker: lista de programas dentro del formulario de
   «Registrar equipo» (laptop, PC...). Cada fila = programa + versión + licencia.
   ============================================================ */
var SoftwarePicker = {
  sel: null, cat: [], filas: [], familia: 'otro',

  montar: function (sel, opts) {
    var p = this;
    opts = opts || {};
    p.sel = sel; p.filas = []; p.familia = opts.familia || 'otro';
    return Api.get('api/software/catalogo.php', { modo: 'select' }).then(function (l) {
      p.cat = l || [];
      if (p.familia === 'computo') p.estandar(true);
      if (!p.filas.length) p.filas.push({ software_id: '', version: '', licencia: 'auto' });
      p._pintar();
    }).catch(function () { $(sel).html('<div class="text-muted small">No se pudo cargar el catálogo de software.</div>'); });
  },
  cambiarFamilia: function (fam) {
    this.familia = fam;
    if (fam === 'computo' && this.cantidad() === 0) { this.filas = []; this.estandar(true); if (!this.filas.length) this.filas.push({ software_id: '', version: '', licencia: 'auto' }); this._pintar(); }
  },
  /* agrega el software marcado como estándar que no esté ya en la lista */
  estandar: function (silencioso) {
    var p = this, n = 0;
    p.cat.forEach(function (c) {
      if (+c.estandar !== 1) return;
      if (p.filas.some(function (f) { return +f.software_id === +c.id; })) return;
      p.filas.push({ software_id: c.id, version: '', licencia: 'auto' }); n++;
    });
    if (!silencioso) { p.filas = p.filas.filter(function (f, i) { return f.software_id || i === p.filas.length - 1 || true; }); p._pintar(); if (!n) Toast.info('No hay más software estándar por agregar'); }
  },
  cantidad: function () {
    var ids = {};
    this.filas.forEach(function (f) { if (+f.software_id > 0) ids[f.software_id] = 1; });
    return Object.keys(ids).length;
  },
  valor: function () {
    var vistos = {}, out = [];
    this.filas.forEach(function (f) {
      if (!(+f.software_id > 0) || vistos[f.software_id]) return;
      vistos[f.software_id] = 1;
      out.push({ software_id: +f.software_id, version: $.trim(f.version || ''), licencia: f.licencia === 'auto' ? 'auto' : '' });
    });
    return JSON.stringify(out);
  },
  _pintar: function () {
    var p = this, h = '';
    p.filas.forEach(function (f, i) {
      var c = p.cat.filter(function (x) { return +x.id === +f.software_id; })[0];
      h += '<div class="sw-pick-fila" data-i="' + i + '"><select class="form-select form-select-sm swp-sw"><option value="">— Programa —</option>';
      p.cat.forEach(function (x) { h += '<option value="' + x.id + '"' + (+x.id === +f.software_id ? ' selected' : '') + '>' + esc(x.nombre) + (x.autorizacion === 'prohibido' ? ' (no autorizado)' : '') + '</option>'; });
      h += '</select><input class="form-control form-control-sm swp-ver" maxlength="40" placeholder="Versión" value="' + esc(f.version || '') + '">';
      if (c && +c.requiere_licencia === 1) {
        h += '<select class="form-select form-select-sm swp-lic"><option value="auto"' + (f.licencia === 'auto' ? ' selected' : '') + '>Licencia automática' + (c.libres > 0 ? ' (' + c.libres + ' libres)' : ' (sin puestos libres)') + '</option><option value=""' + (f.licencia === '' ? ' selected' : '') + '>Sin licencia</option></select>';
      } else {
        h += '<span class="small text-muted">' + (c ? 'No requiere licencia' : '') + '</span>';
      }
      h += '<button type="button" class="btn btn-sm btn-outline-danger swp-del" title="Quitar"><i class="bi bi-x-lg"></i></button></div>';
    });
    h += '<div class="d-flex gap-2 flex-wrap mt-1"><button type="button" class="btn btn-sm btn-outline-primary" id="swp-add"><i class="bi bi-plus-lg"></i> Agregar programa</button>' +
      '<button type="button" class="btn btn-sm btn-outline-secondary" id="swp-std"><i class="bi bi-stars"></i> Agregar el software estándar</button></div>';
    $(p.sel).html(h);
    var $z = $(p.sel);
    $z.off('.swp');
    $z.on('change.swp', '.swp-sw', function () {
      var i = +$(this).closest('.sw-pick-fila').attr('data-i'); p.filas[i].software_id = $(this).val(); p._pintar();
    });
    $z.on('input.swp', '.swp-ver', function () { p.filas[+$(this).closest('.sw-pick-fila').attr('data-i')].version = $(this).val(); });
    $z.on('change.swp', '.swp-lic', function () { p.filas[+$(this).closest('.sw-pick-fila').attr('data-i')].licencia = $(this).val(); });
    $z.on('click.swp', '.swp-del', function () {
      p.filas.splice(+$(this).closest('.sw-pick-fila').attr('data-i'), 1);
      if (!p.filas.length) p.filas.push({ software_id: '', version: '', licencia: 'auto' });
      p._pintar();
    });
    $z.on('click.swp', '#swp-add', function () { p.filas.push({ software_id: '', version: '', licencia: 'auto' }); p._pintar(); });
    $z.on('click.swp', '#swp-std', function () {
      p.filas = p.filas.filter(function (f) { return +f.software_id > 0; });
      p.estandar(false);
      if (!p.filas.length) { p.filas.push({ software_id: '', version: '', licencia: 'auto' }); p._pintar(); }
    });
  }
};
