/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Auditoría
   Vista: views/auditoria/lista.php
   API:   api/auditoria/{listar,resumen,exportar}.php
   ============================================================ */
var AUD_ACCION = {
  crear: ['Creó', 'text-bg-success'], actualizar: ['Modificó', 'text-bg-primary'], eliminar: ['Eliminó', 'text-bg-danger'],
  login: ['Inició sesión', 'text-bg-secondary'], logout: ['Cerró sesión', 'text-bg-secondary'],
  login_fallido: ['Inicio fallido', 'text-bg-danger'], exportar: ['Exportó', 'text-bg-info'],
  aprobar: ['Aprobó', 'text-bg-success'], cambiar_estado: ['Cambió estado', 'text-bg-warning text-dark']
};
var AUD_RES = { exito: 'text-bg-success', denegado: 'text-bg-danger', rechazado: 'text-bg-danger', error: 'text-bg-danger' };

var Auditoria = {
  tabla: null,
  _detalles: {},
  obj: null,   // {tabla, id}: historial de un registro

  /* ---------------------------------------------------------- fechas */
  _fecha: function (dias) {
    var d = new Date();
    d.setDate(d.getDate() - dias);
    var m = d.getMonth() + 1, dd = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (dd < 10 ? '0' : '') + dd;
  },
  _humana: function (s) { return String(s || '').replace(/_/g, ' '); },
  _setSel: function (sel, val) {
    var $s = $(sel);
    if (val && !$s.find('option').filter(function () { return this.value === val; }).length) {
      $s.append('<option value="' + esc(val) + '">' + esc(val) + '</option>');
    }
    $s.val(val);
  },

  _filtros: function () {
    var o = {
      f_usuario: $('#auf-usuario').val() || 0,
      f_tabla: $('#auf-tabla').val() || '',
      f_accion: $('#auf-accion').val() || '',
      f_desde: $('#auf-desde').val() || '',
      f_hasta: $('#auf-hasta').val() || '',
      f_ubicacion: $('#auf-ubicacion').val() || '',
      f_modulo: $('#auf-modulo').val() || '',
      f_resultado: $('#auf-resultado').val() || '',
      f_seguridad: $('#auf-seguridad').prop('checked') ? 1 : 0
    };
    if (Auditoria.obj) { o.f_tabla = Auditoria.obj.tabla; o.f_registro = Auditoria.obj.id; }
    return o;
  },

  _redibujar: function () { if (this.tabla) this.tabla.draw(); },

  _rango: function (r) {
    r = String(r);   // jQuery .data() convierte "7" en número
    $('#au-rangos button').removeClass('active').filter('[data-r="' + r + '"]').addClass('active');
    var d = '', h = '';
    if (r === 'hoy') { d = this._fecha(0); h = this._fecha(0); }
    else if (r === '7') { d = this._fecha(6); h = this._fecha(0); }
    else if (r === '30') { d = this._fecha(29); h = this._fecha(0); }
    $('#auf-desde').val(d); $('#auf-hasta').val(h);
  },

  _limpiar: function (sinDibujar) {
    $('#auf-usuario,#auf-tabla,#auf-accion,#auf-desde,#auf-hasta,#auf-ubicacion,#auf-modulo,#auf-resultado').val('');
    $('#auf-seguridad').prop('checked', false);
    this.obj = null;
    this._badgeObjeto();
    this._rango('todo');
    $('#tb-aud_filter input').val('');
    if (this.tabla) { this.tabla.search(''); if (!sinDibujar) this.tabla.draw(); }
    $('.au-card').removeClass('activa');
  },

  _badgeObjeto: function () {
    var $b = $('#au-objeto');
    if (!this.obj) { $b.addClass('d-none').empty(); return; }
    $b.removeClass('d-none').html('Historial de <b>' + esc(this.obj.tabla) + ' #' + esc(this.obj.id) + '</b> <a href="#" class="text-white ms-1" id="au-obj-x" title="Quitar">&times;</a>');
  },

  /* ---------------------------------------------------------- init */
  init: function () {
    var s = this;
    s.obj = null;
    s._detalles = {};

    s.tabla = DT.server('#tb-aud', 'api/auditoria/listar.php', function () { return s._filtros(); }, [
      { data: 'created_at', render: function (v, t, f) {
        return '<span class="small text-nowrap" title="' + esc(v || '') + '">' + esc(v || '—') + '</span>';
      } },
      { data: 'usuario_mostrar', render: function (v, t, f) {
        return esc(v || f.usuario || 'sistema');
      } },
      { data: 'rol_snapshot', render: function (v) { return v ? '<span class="small">' + esc(v) + '</span>' : '—'; } },
      { data: 'modulo', render: function (v, t, f) {
        return esc((v || '—') + (f.submodulo && f.accion_detalle !== 'permiso_denegado' ? ' / ' + f.submodulo : ''));
      } },
      { data: 'accion_detalle', render: function (v, t, f) {
        var a = AUD_ACCION[f.accion] || [f.accion || '—', 'text-bg-light border'];
        var det = (f.accion_detalle && f.accion_detalle !== f.accion) ? '<div class="text-muted" style="font-size:.72rem">' + esc(s._humana(f.accion_detalle)) + '</div>' : '';
        return '<span class="badge ' + a[1] + '">' + esc(a[0]) + '</span>' + det;
      } },
      { data: 'tabla', render: function (v, t, f) {
        return '<code class="small">' + esc((v || '—') + (f.registro_id !== null && f.registro_id !== undefined ? ' #' + f.registro_id : '')) + '</code>';
      } },
      { data: 'resultado', render: function (v) {
        return v ? '<span class="badge ' + (AUD_RES[v] || 'text-bg-light border') + '">' + esc(v) + '</span>' : '—';
      } },
      { data: 'ip', render: function (v, t, f) {
        return v ? '<code class="small" title="' + esc(f.fuente_ip || 'fuente no registrada') + '">' + esc(v) + '</code>' : '—';
      } },
      { data: 'ubicacion', render: function (v, t, f) {
        return v ? '<span class="small" title="' + esc(f.fuente_geolocalizacion || 'fuente no registrada') + '">' + esc(v) + '</span>' : '—';
      } },
      { data: null, orderable: false, searchable: false, className: 'text-center', render: function (v, t, f) {
        s._detalles[f.id] = f;
        return '<button class="btn btn-sm btn-outline-secondary btn-aud-detalle" data-id="' + (+f.id) + '">Detalle</button>';
      } }
    ], { order: [[0, 'desc']] });

    // filas en rojo: inicios fallidos y accesos denegados
    $('#tb-aud').off('draw.dt.au').on('draw.dt.au', function () {
      s.tabla.rows({ page: 'current' }).every(function () {
        var d = this.data() || {};
        $(this.node()).toggleClass('au-mal', d.accion === 'login_fallido' || d.resultado === 'denegado' || d.resultado === 'rechazado');
      });
      var info = s.tabla.page.info();
      $('#au-contador').text(info.recordsDisplay + ' evento(s)');
    });

    $('#btn-filtrar-aud').off('click.au').on('click.au', function () { s._redibujar(); });
    $('#btn-limpiar-aud').off('click.au').on('click.au', function () { s._limpiar(); });
    $('#auf-usuario,#auf-tabla,#auf-accion,#auf-modulo,#auf-resultado,#auf-seguridad').off('change.au').on('change.au', function () { s._redibujar(); });
    $('#auf-desde,#auf-hasta').off('change.au').on('change.au', function () {
      $('#au-rangos button').removeClass('active');
      s._redibujar();
    });
    $('#auf-ubicacion').off('keyup.au').on('keyup.au', function (e) { if (e.key === 'Enter') s._redibujar(); });
    $('#au-rangos').off('click.au').on('click.au', 'button', function () {
      s._rango($(this).data('r'));
      s._redibujar();
    });
    $('#tb-aud').off('click.au').on('click.au', '.btn-aud-detalle', function () { s.verDetalle($(this).data('id')); });
    $('#au-objeto').off('click.au').on('click.au', '#au-obj-x', function (e) { e.preventDefault(); s.obj = null; s._badgeObjeto(); s._redibujar(); });
    $('#btn-exportar-auditoria').off('click.au').on('click.au', function () { s._exportar(); });

    $('.au-card').off('click.au').on('click.au', function () { s._tarjeta($(this).data('card'), $(this)); });
    s._resumen();
  },

  /* ---------------------------------------------------------- resumen y tarjetas */
  _resumen: function () {
    var s = this;
    Api.get('api/auditoria/resumen.php').then(function (r) {
      ['hoy', 'usuarios_hoy', 'fallidos_24h', 'denegados_24h', 'seguridad_7d'].forEach(function (k) {
        $('#au-raiz [data-k="' + k + '"]').text(r[k] || 0);
      });
      var max = 1;
      (r.serie || []).forEach(function (x) { if (x.n > max) max = x.n; });
      var h = '';
      (r.serie || []).forEach(function (x, i) {
        var f = x.dia.slice(8, 10) + '/' + x.dia.slice(5, 7);
        h += '<div class="b' + (i === r.serie.length - 1 ? ' hoy' : '') + '" data-d="' + esc(x.dia) + '" style="height:' + Math.max(4, Math.round(x.n / max * 100)) + '%" title="' + f + ': ' + x.n + ' evento(s)"></div>';
      });
      $('#au-serie').html(h);
      $('#au-serie').off('click.au').on('click.au', '.b', function () {
        var d = $(this).data('d');
        $('#au-rangos button').removeClass('active');
        $('#auf-desde').val(d); $('#auf-hasta').val(d);
        s._redibujar();
      });
    }).catch(function () {});
  },

  _tarjeta: function (k, $c) {
    var s = this;
    s._limpiar(true);
    $('.au-card').removeClass('activa');
    $c.addClass('activa');
    if (k === 'hoy') s._rango('hoy');
    else if (k === 'fallidos') { s._setSel('#auf-accion', 'login_fallido'); $('#auf-desde').val(s._fecha(1)); }
    else if (k === 'denegados') { s._setSel('#auf-resultado', 'denegado'); $('#auf-desde').val(s._fecha(1)); }
    else if (k === 'seguridad') { $('#auf-seguridad').prop('checked', true); s._rango('7'); }
    s._redibujar();
  },

  /* ---------------------------------------------------------- exportar */
  _exportar: function () {
    var s = this;
    var q = $.param($.extend({ csrf_token: CSRF.token, q: (s.tabla && s.tabla.search()) || '' }, s._filtros()));
    fetch(BASE_URL + 'api/auditoria/exportar.php?' + q, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) return r.json().then(function (j) { throw j; }, function () { throw { message: 'Error (' + r.status + ')' }; });
      var m = /filename="?([^";]+)"?/.exec(r.headers.get('Content-Disposition') || '');
      var nombre = m ? m[1] : 'auditoria.csv';
      return r.blob().then(function (b) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(b); a.download = nombre;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
        Toast.success('Auditoría exportada');
        s._resumen();
      });
    }).catch(function (e) { Toast.error((e && e.message) || 'No se pudo exportar'); });
  },

  /* ---------------------------------------------------------- detalle */
  _parse: function (v) {
    if (v === null || v === undefined || v === '') return null;
    if (typeof v !== 'string') return v;
    try { return JSON.parse(v); } catch (e) { return v; }
  },
  _txt: function (v) {
    if (v === null || v === undefined) return '—';
    if (typeof v === 'object') return JSON.stringify(v, null, 1);
    var t = String(v);
    return t === '' ? '(vacío)' : t;
  },

  /** Tabla campo | antes | después, solo con lo que cambió. */
  _cambios: function (ant, nue) {
    var s = this;
    var esObj = function (x) { return x !== null && typeof x === 'object' && !Array.isArray(x); };
    if (esObj(ant) && esObj(nue)) {
      var keys = Object.keys(ant).concat(Object.keys(nue).filter(function (k) { return !(k in ant); }));
      var filas = keys.filter(function (k) { return JSON.stringify(ant[k]) !== JSON.stringify(nue[k]); });
      if (!filas.length) return '<div class="text-muted small">No hay diferencias entre los datos anteriores y los nuevos.</div>';
      var h = '<div class="table-responsive"><table class="table table-sm table-bordered au-df mb-0"><thead><tr><th style="width:22%">Campo</th><th>Antes</th><th>Después</th></tr></thead><tbody>';
      filas.forEach(function (k) {
        h += '<tr><td><code>' + esc(k) + '</code></td><td class="antes"><pre>' + esc(s._txt(ant[k])) + '</pre></td><td class="desp"><pre>' + esc(s._txt(nue[k])) + '</pre></td></tr>';
      });
      return h + '</tbody></table></div>';
    }
    if (esObj(nue) && ant === null) {
      var h2 = '<div class="small text-muted mb-1">Datos registrados</div><div class="table-responsive"><table class="table table-sm table-bordered au-df mb-0"><tbody>';
      Object.keys(nue).forEach(function (k) {
        h2 += '<tr><td style="width:22%"><code>' + esc(k) + '</code></td><td class="desp"><pre>' + esc(s._txt(nue[k])) + '</pre></td></tr>';
      });
      return h2 + '</tbody></table></div>';
    }
    if (esObj(ant) && nue === null) {
      var h3 = '<div class="small text-muted mb-1">Datos que había</div><div class="table-responsive"><table class="table table-sm table-bordered au-df mb-0"><tbody>';
      Object.keys(ant).forEach(function (k) {
        h3 += '<tr><td style="width:22%"><code>' + esc(k) + '</code></td><td class="antes"><pre>' + esc(s._txt(ant[k])) + '</pre></td></tr>';
      });
      return h3 + '</tbody></table></div>';
    }
    var raw = '';
    if (ant !== null) raw += '<div class="small text-muted">Antes</div><pre class="small bg-light border rounded p-2">' + esc(s._txt(ant)) + '</pre>';
    if (nue !== null) raw += '<div class="small text-muted">Después</div><pre class="small bg-light border rounded p-2">' + esc(s._txt(nue)) + '</pre>';
    return raw || '<div class="text-muted small">Esta operación no guardó datos.</div>';
  },

  verDetalle: function (id) {
    var s = this, f = s._detalles[id];
    if (!f) return;
    var a = AUD_ACCION[f.accion] || [f.accion || '—', 'text-bg-light border'];
    var dato = function (t, v) { return '<div class="col-md-6"><span class="text-muted">' + t + ':</span> ' + v + '</div>'; };
    var h = '<div class="small"><div class="row g-1 mb-2">' +
      dato('Cuándo', esc(f.created_at || '—')) +
      dato('Quién', esc(f.usuario_nombre || f.usuario || 'sistema') + ' <span class="text-muted">(' + esc(f.rol_snapshot || 'rol no capturado') + ')</span>') +
      dato('Qué hizo', '<span class="badge ' + a[1] + '">' + esc(a[0]) + '</span> ' + esc(s._humana(f.accion_detalle && f.accion_detalle !== f.accion ? f.accion_detalle : '')) ) +
      dato('Resultado', f.resultado ? '<span class="badge ' + (AUD_RES[f.resultado] || 'text-bg-light border') + '">' + esc(f.resultado) + '</span>' : '—') +
      dato('Módulo', esc((f.modulo || '—') + (f.submodulo ? ' / ' + f.submodulo : ''))) +
      dato('Objeto', '<code>' + esc((f.tabla || '—') + (f.registro_id !== null && f.registro_id !== undefined ? ' #' + f.registro_id : '')) + '</code>') +
      dato('IP', esc(f.ip || '—') + ' <span class="text-muted">· ' + esc(f.fuente_ip || 'fuente no registrada') + '</span>') +
      dato('Ubicación estimada', esc(f.ubicacion || '—') + (f.fuente_geolocalizacion ? ' <span class="text-muted">· ' + esc(f.fuente_geolocalizacion) + '</span>' : '')) +
      (f.equipo_id ? dato('Equipo', esc(f.equipo_id)) : '') +
      (f.motivo ? '<div class="col-12"><span class="text-muted">Motivo:</span> ' + esc(f.motivo) + '</div>' : '') +
      (f.user_agent ? '<div class="col-12"><span class="text-muted">Navegador:</span> <span class="text-break">' + esc(f.user_agent) + '</span></div>' : '') +
      (f.session_ref ? '<div class="col-12"><span class="text-muted">Referencia de sesión:</span> <code>' + esc(f.session_ref) + '</code></div>' : '') +
      '</div>' +
      '<h6 class="mt-3 mb-2"><i class="bi bi-arrow-left-right"></i> Qué cambió</h6>' +
      s._cambios(s._parse(f.datos_anteriores), s._parse(f.datos_nuevos)) + '</div>' +
      '<div class="d-flex justify-content-between mt-3"><div>' +
      (f.tabla && f.registro_id !== null && f.registro_id !== undefined && f.registro_id !== ''
        ? '<button type="button" class="btn btn-outline-primary btn-sm" id="au-hist"><i class="bi bi-clock-history"></i> Ver historial de este registro</button>' : '') +
      '</div><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button></div>';
    Modal.abrir('Detalle de auditoría · ' + (f.created_at || ''), h, 'modal-lg');
    $('#au-hist').on('click', function () {
      Modal.cerrar();
      s._limpiar(true);
      s.obj = { tabla: f.tabla, id: f.registro_id };
      s._badgeObjeto();
      s._redibujar();
    });
  }
};
App.registrar('auditoria', Auditoria);
