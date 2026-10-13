/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Capacitaciones: cualquier persona (de cualquier area) pide una capacitacion a Sistemas
   y ve en que va; Sistemas revisa, programa, cierra y administra los temas.
   Vista: views/capacitaciones/lista.php
   API:   api/capacitaciones/{resumen,solicitudes,gestionar,temas}.php
   ============================================================ */
var CAP_PASOS = ['solicitada', 'en_revision', 'programada', 'realizada'];
var CAP_ETQ = { solicitada: 'Enviada', en_revision: 'En revisión', programada: 'Programada', realizada: 'Realizada', rechazada: 'Rechazada', cancelada: 'Cancelada' };
var CAP_CLR = { azul: 'text-bg-primary', ambar: 'text-bg-warning text-dark', verde: 'text-bg-success', gris: 'text-bg-secondary', rojo: 'text-bg-danger' };
var CAP_PRIO = { baja: 'Baja', normal: 'Normal', alta: 'Alta' };
var CAP_MODAL = { indistinto: 'Indistinto', presencial: 'Presencial', virtual: 'Virtual' };

var Capacitaciones = {
  perm: { solicitar: false, gestionar: false, todas: false },
  _mias: [], _todas: [], _temas: [], _res: null,
  _grupoMias: 'curso', _tTimer: null,

  /* ---------------------------------------------------------- utilidades */
  _fecha: function (s) {
    s = String(s || '').slice(0, 10);
    return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s.slice(8, 10) + '/' + s.slice(5, 7) + '/' + s.slice(0, 4) : '';
  },
  _hora: function (s) {
    var m = /(\d{2}:\d{2})/.exec(String(s || '').slice(11)); return m ? m[1] : '';
  },
  _fh: function (s) { return s ? this._fecha(s) + ' ' + this._hora(s) : ''; },
  _hoy: function () {
    var d = new Date(), m = d.getMonth() + 1, dd = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (dd < 10 ? '0' : '') + dd;
  },
  _opt: function (v, t, sel) {
    return '<option value="' + esc(v) + '"' + (String(sel) === String(v) ? ' selected' : '') + '>' + esc(t) + '</option>';
  },
  _badge: function (f) {
    return '<span class="badge ' + (CAP_CLR[f.estado_color] || 'text-bg-secondary') + '">' + esc(f.estado_texto || CAP_ETQ[f.estado] || f.estado) + '</span>';
  },
  _prio: function (p) {
    return p === 'alta' ? ' <span class="badge text-bg-danger">Urgente</span>' : '';
  },
  _post: function (u, d) { return Api.post(u, d); },
  _errores: function (pref, e) {
    $('[id^="' + pref + '"]').removeClass('is-invalid');
    $('.cap-fb').remove();
    var er = e && e.cuerpo && e.cuerpo.errors;
    if (!er) return false;
    var alguno = false;
    Object.keys(er).forEach(function (k) {
      var $i = $('#' + pref + k);
      if ($i.length) { $i.addClass('is-invalid').after('<div class="invalid-feedback d-block cap-fb">' + esc(er[k]) + '</div>'); alguno = true; }
    });
    return alguno;
  },
  _refrescar: function () {
    var s = this;
    s._resumen();
    if (s.perm.solicitar) s._cargarMias();
    if (s.perm.todas) s._cargarTodas();
    if (s.perm.gestionar && $('#cap-p-temas').hasClass('show')) s._cargarTemas();
  },

  /* ---------------------------------------------------------- linea de pasos que ve quien solicita */
  _pasos: function (f) {
    var est = f.estado, h = '', i;
    if (est === 'rechazada' || est === 'cancelada') {
      return '<div class="cap-paso"><span class="p hecho"><span class="c"><i class="bi bi-check"></i></span>Enviada</span><span class="sep hecho"></span>' +
        '<span class="p fin-mal"><span class="c"><i class="bi bi-x"></i></span>' + esc(CAP_ETQ[est]) + '</span></div>';
    }
    var act = CAP_PASOS.indexOf(est);
    h = '<div class="cap-paso">';
    CAP_PASOS.forEach(function (p, k) {
      var cls = k < act || (est === 'realizada' && k <= act) ? 'hecho' : (k === act ? 'actual' : '');
      h += (k ? '<span class="sep' + (k <= act ? ' hecho' : '') + '"></span>' : '') +
        '<span class="p ' + cls + '"><span class="c">' + (cls === 'hecho' ? '<i class="bi bi-check"></i>' : (k + 1)) + '</span>' + esc(CAP_ETQ[p]) + '</span>';
    });
    return h + '</div>';
  },
  _datosSesion: function (f) {
    if (!f.fecha_programada) return '';
    return '<div class="small mt-1"><i class="bi bi-calendar-event text-primary"></i> <b>' + esc(this._fh(f.fecha_programada)) + '</b>' +
      (f.duracion_min ? ' · ' + f.duracion_min + ' min' : '') + (f.lugar ? ' · <i class="bi bi-geo-alt"></i> ' + esc(f.lugar) : '') +
      (f.capacitador ? ' · <i class="bi bi-person-video3"></i> ' + esc(f.capacitador) : '') + '</div>';
  },

  /* ---------------------------------------------------------- init */
  init: function () {
    var s = this, $r = $('#cap-raiz');
    if (!$r.length) return;
    s.perm = { solicitar: +$r.attr('data-solicitar') === 1, gestionar: +$r.attr('data-gestionar') === 1, todas: +$r.attr('data-todas') === 1 };
    s._grupoMias = 'curso';

    $('#cap-tabs button[data-bs-toggle=tab]').off('shown.bs.tab.cap').on('shown.bs.tab.cap', function () { s._activar($(this).attr('data-vista')); });

    $('#cap-btn-nueva').off('click.cap').on('click.cap', function () { s._formNueva(); });
    $('#cap-filtro-mias').off('click.cap').on('click.cap', 'button', function () {
      $('#cap-filtro-mias button').removeClass('active'); $(this).addClass('active');
      s._grupoMias = $(this).attr('data-g') || ''; s._cargarMias();
    });
    $('#cap-lista-mias, #cap-tb tbody, #cap-agenda').off('click.cap').on('click.cap', '[data-ver]', function (e) {
      if ($(e.target).closest('[data-accion]').length) return;
      s._detalle(+$(this).attr('data-ver'));
    });
    $('#cap-lista-mias, #cap-tb tbody').on('click.cap', '[data-accion]', function (e) {
      e.preventDefault(); e.stopPropagation();
      s._accion($(this).attr('data-accion'), +$(this).attr('data-id'));
    });
    $('#capf-estado, #capf-area').off('change.cap').on('change.cap', function () { s._cargarTodas(); });
    $('#capf-q').off('input.cap').on('input.cap', function () { clearTimeout(s._tTimer); s._tTimer = setTimeout(function () { s._cargarTodas(); }, 300); });
    $('#capf-limpiar').off('click.cap').on('click.cap', function () { $('#capf-estado,#capf-area').val(''); $('#capf-q').val(''); s._cargarTodas(); });
    $('#cap-kpi-todas').off('click.cap').on('click.cap', '[data-est]', function () {
      $('#capf-estado').val($(this).attr('data-est')); s._cargarTodas();
    });
    $('#cap-btn-tema').off('click.cap').on('click.cap', function () { s._formTema(); });
    $('#cap-tb-temas tbody').off('click.cap').on('click.cap', '[data-tema]', function () {
      var id = +$(this).attr('data-id'), a = $(this).attr('data-tema');
      if (a === 'edit') s._formTema(s._temas.filter(function (t) { return +t.id === id; })[0]);
      else if (a === 'toggle') s._post('api/capacitaciones/temas.php', { accion: 'toggle', id: id }).then(function (r) { Toast.success(r.mensaje); s._cargarTemas(); }).catch(function () { });
      else if (a === 'del') Swal.fire({ title: '¿Eliminar este tema?', text: 'Solo se puede si ninguna solicitud lo usó.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626' })
        .then(function (r) { if (r.isConfirmed) s._post('api/capacitaciones/temas.php', { accion: 'eliminar', id: id }).then(function (x) { Toast.success(x.mensaje); s._cargarTemas(); }).catch(function () { }); });
    });

    if (s.perm.todas) s._cargarAreas();
    s._resumen();
    var ini = $r.attr('data-inicial') || 'agenda';
    var el = document.getElementById('cap-t-' + ini);
    if (el && window.bootstrap) bootstrap.Tab.getOrCreateInstance(el).show();
  },

  _activar: function (v) {
    if (v === 'mias') this._cargarMias();
    else if (v === 'todas') this._cargarTodas();
    else if (v === 'agenda') this._renderAgenda();
    else if (v === 'temas') this._cargarTemas();
  },

  /* ---------------------------------------------------------- resumen (tarjetas, avisos y agenda) */
  _resumen: function () {
    var s = this;
    return Api.get('api/capacitaciones/resumen.php').then(function (r) {
      s._res = r;
      var m = r.mias || {}, h = '';
      [['en_curso', 'En curso', 'text-primary'], ['programadas', 'Programadas', 'text-success'], ['por_calificar', 'Por calificar', 'text-warning']].forEach(function (k) {
        h += '<div class="col-6 col-md-3"><div class="cap-kpi"><div class="n ' + k[2] + '">' + (m[k[0]] || 0) + '</div><div class="small text-muted">' + k[1] + '</div></div></div>';
      });
      $('#cap-kpi-mias').html(h);
      $('#cap-b-mias').text(m.en_curso || '').toggleClass('d-none', !m.en_curso);
      if (r.todas) {
        var t = r.todas, k2 = '';
        [['nuevas', 'Nuevas', 'text-primary', 'solicitada'], ['en_revision', 'En revisión', 'text-warning', 'en_revision'], ['programadas', 'Programadas', 'text-success', 'programada'],
          ['por_cerrar', 'Por cerrar', 'text-danger', 'programada'], ['realizadas_mes', 'Realizadas este mes', 'text-secondary', 'realizada']].forEach(function (k) {
          k2 += '<div class="col-6 col-md"><div class="cap-kpi clic" data-est="' + k[3] + '"><div class="n ' + k[2] + '">' + (t[k[0]] || 0) + '</div><div class="small text-muted">' + k[1] + '</div></div></div>';
        });
        k2 += '<div class="col-6 col-md"><div class="cap-kpi"><div class="n">' + (t.calificacion != null ? t.calificacion + ' <i class="bi bi-star-fill text-warning fs-6"></i>' : '—') + '</div><div class="small text-muted">Calificación media</div></div></div>';
        $('#cap-kpi-todas').html(k2);
        $('#cap-b-todas').text(t.nuevas || '').toggleClass('d-none', !t.nuevas);
      }
      if ($('#cap-p-agenda').hasClass('show')) s._renderAgenda();
    }).catch(function () { });
  },

  /* ---------------------------------------------------------- mis solicitudes */
  _cargarMias: function () {
    var s = this;
    return Api.get('api/capacitaciones/solicitudes.php', { vista: 'mias', grupo: s._grupoMias }).then(function (l) {
      s._mias = l || []; s._renderMias();
    }).catch(function () { });
  },
  _renderMias: function () {
    var s = this, h = '';
    s._mias.forEach(function (f) {
      h += '<div class="cap-card' + (f.por_cerrar ? '' : '') + '" data-ver="' + f.id + '" style="cursor:pointer">' +
        '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap"><div><span class="text-muted small">' + esc(f.codigo) + '</span> ' + s._prio(f.prioridad) +
        '<div class="fw-semibold">' + esc(f.tema) + '</div></div><div>' + s._badge(f) + '</div></div>' +
        '<div class="small text-muted">' + f.participantes + ' participante(s) · ' + esc(f.modalidad_texto) + (f.fecha_deseada ? ' · deseada ' + esc(s._fecha(f.fecha_deseada)) : '') + ' · enviada ' + esc(s._fecha(f.created_at)) + '</div>' +
        s._pasos(f) + '<div class="cap-ayuda">' + esc(f.estado_ayuda) + (f.estado === 'rechazada' && f.motivo ? ' <b>Motivo:</b> ' + esc(f.motivo) : '') + '</div>' + s._datosSesion(f) +
        '<div class="mt-2 text-end">' +
        (f.acciones.indexOf('evaluar') >= 0 ? '<button class="btn btn-sm btn-warning me-1" data-accion="evaluar" data-id="' + f.id + '"><i class="bi bi-star me-1"></i>Calificar</button>' : '') +
        (f.acciones.indexOf('cancelar') >= 0 ? '<button class="btn btn-sm btn-outline-danger me-1" data-accion="cancelar" data-id="' + f.id + '"><i class="bi bi-x-circle me-1"></i>Cancelar</button>' : '') +
        '<button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Ver detalle</button></div></div>';
    });
    $('#cap-lista-mias').html(h || '<div class="text-center text-muted py-4"><i class="bi bi-mortarboard fs-1 d-block mb-2"></i>' +
      (s._grupoMias === 'curso' ? 'No tiene solicitudes en curso.' : 'No hay solicitudes con ese filtro.') + '<br>Use «Solicitar capacitación» para pedir una.</div>');
  },

  /* ---------------------------------------------------------- solicitudes (todas / de mi area) */
  _cargarAreas: function () {
    Api.get('api/catalogos/select.php', { tipo: 'areas' }).then(function (l) {
      var h = '<option value="">Todas las áreas</option>';
      (l || []).forEach(function (a) { h += '<option value="' + esc(a.id) + '">' + esc(a.texto) + '</option>'; });
      $('#capf-area').html(h);
    }).catch(function () { });
  },
  _cargarTodas: function () {
    var s = this;
    return Api.get('api/capacitaciones/solicitudes.php', { vista: 'todas', estado: $('#capf-estado').val() || '', area_id: $('#capf-area').val() || '', q: $.trim($('#capf-q').val() || '') }).then(function (l) {
      s._todas = l || []; s._renderTodas();
    }).catch(function () { });
  },
  _renderTodas: function () {
    var s = this, h = '';
    s._todas.forEach(function (f) {
      var cuando = f.fecha_programada ? '<b>' + esc(s._fh(f.fecha_programada)) + '</b>' + (f.por_cerrar ? ' <span class="badge text-bg-warning text-dark">Por cerrar</span>' : '') :
        (f.fecha_deseada ? '<span class="text-muted">desea ' + esc(s._fecha(f.fecha_deseada)) + '</span>' : '<span class="text-muted">—</span>');
      var bt = '';
      if (f.acciones.indexOf('tomar') >= 0) bt += '<button class="btn btn-sm btn-outline-secondary me-1" data-accion="tomar" data-id="' + f.id + '" title="Empezar a revisar"><i class="bi bi-eye"></i></button>';
      if (f.acciones.indexOf('programar') >= 0) bt += '<button class="btn btn-sm btn-outline-success me-1" data-accion="programar" data-id="' + f.id + '" title="' + (f.estado === 'programada' ? 'Reprogramar' : 'Programar') + '"><i class="bi bi-calendar-plus"></i></button>';
      if (f.acciones.indexOf('realizar') >= 0) bt += '<button class="btn btn-sm btn-outline-primary me-1" data-accion="realizar" data-id="' + f.id + '" title="Marcar como realizada"><i class="bi bi-check2-circle"></i></button>';
      if (f.acciones.indexOf('rechazar') >= 0) bt += '<button class="btn btn-sm btn-outline-danger me-1" data-accion="rechazar" data-id="' + f.id + '" title="Rechazar"><i class="bi bi-x-octagon"></i></button>';
      h += '<tr data-ver="' + f.id + '" style="cursor:pointer"><td class="text-nowrap"><b>' + esc(f.codigo) + '</b>' + s._prio(f.prioridad) + '</td><td>' + esc(f.tema) + '<div class="small text-muted">' + esc(f.modalidad_texto) + '</div></td>' +
        '<td>' + esc(f.solicitante || '') + '</td><td class="small">' + esc(f.area || '—') + '</td><td class="text-center">' + f.participantes + '</td><td class="small">' + cuando + '</td><td>' + s._badge(f) + '</td>' +
        '<td class="text-nowrap text-end">' + bt + '</td></tr>';
    });
    $('#cap-tb tbody').html(h || '<tr><td colspan="8" class="text-center text-muted py-3">No hay solicitudes con ese filtro.</td></tr>');
  },

  /* ---------------------------------------------------------- agenda */
  _renderAgenda: function () {
    var s = this, ag = (s._res && s._res.agenda) || [], h = '', dia = '';
    var hoy = s._hoy();
    ag.forEach(function (f) {
      var d = String(f.fecha_programada).slice(0, 10);
      if (d !== dia) {
        dia = d;
        var rotulo = d === hoy ? 'Hoy' : (d < hoy ? 'Atrasada' : '');
        h += '<div class="cap-dia">' + esc(s._fecha(d)) + (rotulo ? ' <span class="badge ' + (d < hoy ? 'text-bg-warning text-dark' : 'text-bg-primary') + '">' + rotulo + '</span>' : '') + '</div>';
      }
      h += '<div class="cap-ses" data-ver="' + f.id + '" style="cursor:pointer"><div class="h">' + esc(s._hora(f.fecha_programada)) + '</div><div class="flex-grow-1">' +
        '<div class="fw-semibold">' + esc(f.tema) + '</div><div class="small text-muted">' + esc(f.codigo) + ' · ' + (f.participantes) + ' participante(s)' +
        (f.solicitante ? ' · ' + esc(f.solicitante) + (f.area ? ' (' + esc(f.area) + ')' : '') : '') + '</div>' +
        '<div class="small"><i class="bi bi-geo-alt"></i> ' + esc(f.lugar || 'Lugar por confirmar') + (f.capacitador ? ' · <i class="bi bi-person-video3"></i> ' + esc(f.capacitador) : '') + (f.duracion_min ? ' · ' + f.duracion_min + ' min' : '') + '</div></div>' +
        (f.por_cerrar ? '<span class="badge text-bg-warning text-dark">Por cerrar</span>' : '') + '</div>';
    });
    $('#cap-agenda').html(h || '<div class="text-center text-muted py-4"><i class="bi bi-calendar-x fs-1 d-block mb-2"></i>No hay capacitaciones programadas.</div>');
  },

  /* ---------------------------------------------------------- detalle */
  _detalle: function (id) {
    var s = this;
    Api.get('api/capacitaciones/solicitudes.php', { id: id }).then(function (f) {
      var h = '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2"><div><span class="text-muted">' + esc(f.codigo) + '</span>' + s._prio(f.prioridad) +
        '<h5 class="mb-0">' + esc(f.tema) + '</h5></div><div class="fs-6">' + s._badge(f) + '</div></div>' + s._pasos(f) +
        '<div class="cap-ayuda mb-2">' + esc(f.estado_ayuda) + (f.motivo && (f.estado === 'rechazada' || f.estado === 'cancelada') ? ' <b>Motivo:</b> ' + esc(f.motivo) : '') + '</div>' + s._datosSesion(f) +
        '<div class="row g-2 mt-1 small">' +
        '<div class="col-md-6"><span class="text-muted">Solicitante:</span> ' + esc(f.solicitante || '') + (f.area ? ' (' + esc(f.area) + ')' : '') + '</div>' +
        '<div class="col-md-6"><span class="text-muted">Participantes:</span> ' + f.participantes + ' · ' + esc(f.modalidad_texto) + '</div>' +
        '<div class="col-md-6"><span class="text-muted">Fecha deseada:</span> ' + esc(s._fecha(f.fecha_deseada) || '—') + '</div>' +
        '<div class="col-md-6"><span class="text-muted">Enviada:</span> ' + esc(s._fh(f.created_at)) + '</div></div>' +
        '<div class="mt-2"><div class="text-muted small">Qué necesitan aprender</div><div style="white-space:pre-wrap">' + esc(f.detalle || '') + '</div></div>';
      if (f.estado === 'realizada') {
        h += '<div class="mt-2 small"><span class="text-muted">Asistentes:</span> ' + (f.asistentes != null ? f.asistentes : '—') + (f.resultado ? ' · <span class="text-muted">Resultado:</span> ' + esc(f.resultado) : '') + '</div>';
        if (f.calificacion != null) h += '<div class="mt-1">' + s._estrellas(f.calificacion) + (f.comentario_eval ? ' <span class="small text-muted">«' + esc(f.comentario_eval) + '»</span>' : '') + '</div>';
      }
      h += '<hr><div class="fw-semibold small mb-2">Historial</div><div class="cap-linea">';
      (f.historial || []).forEach(function (x) {
        h += '<div class="it"><div class="small"><b>' + esc(x.estado_texto) + '</b> <span class="text-muted">· ' + esc(s._fh(x.created_at)) + (x.usuario ? ' · ' + esc(x.usuario) : '') + '</span></div>' +
          (x.comentario ? '<div class="small text-muted">' + esc(x.comentario) + '</div>' : '') + '</div>';
      });
      h += '</div><div class="text-end mt-2" id="cap-det-acc">';
      var acc = { tomar: ['secondary', 'eye', 'Empezar a revisar'], programar: ['success', 'calendar-plus', f.estado === 'programada' ? 'Reprogramar' : 'Programar'],
        realizar: ['primary', 'check2-circle', 'Marcar realizada'], rechazar: ['danger', 'x-octagon', 'Rechazar'], cancelar: ['outline-danger', 'x-circle', 'Cancelar solicitud'], evaluar: ['warning', 'star', 'Calificar'] };
      (f.acciones || []).forEach(function (a) {
        if (!acc[a]) return;
        h += '<button class="btn btn-sm btn-' + acc[a][0] + ' ms-1" data-det-accion="' + a + '"><i class="bi bi-' + acc[a][1] + ' me-1"></i>' + acc[a][2] + '</button>';
      });
      h += '</div>';
      Modal.abrir('Capacitación ' + f.codigo, h, 'modal-lg');
      $('#cap-det-acc').off('click.cap').on('click.cap', '[data-det-accion]', function () {
        var a = $(this).attr('data-det-accion');
        Modal.cerrar();
        setTimeout(function () { s._accion(a, id, f); }, 350);
      });
    }).catch(function () { });
  },
  _estrellas: function (n) {
    var h = '';
    for (var i = 1; i <= 5; i++) h += '<i class="bi bi-star' + (i <= n ? '-fill' : '') + ' text-warning"></i>';
    return h;
  },

  /* ---------------------------------------------------------- acciones */
  _accion: function (a, id, f) {
    var s = this;
    var buscar = function () {
      return f || s._mias.concat(s._todas).filter(function (x) { return +x.id === +id; })[0] || { id: id };
    };
    var enviar = function (url, datos) {
      return s._post(url, datos).then(function (r) { Toast.success((r && r.mensaje) || 'Listo'); s._refrescar(); return r; });
    };
    if (a === 'tomar') { enviar('api/capacitaciones/gestionar.php', { accion: 'tomar', id: id }).catch(function () { }); }
    else if (a === 'programar') s._formProgramar(buscar());
    else if (a === 'realizar') s._formRealizar(buscar());
    else if (a === 'evaluar') s._formEvaluar(buscar());
    else if (a === 'rechazar') {
      Swal.fire({ title: 'Rechazar la solicitud', input: 'textarea', inputLabel: 'Motivo (lo verá quien la pidió)', inputAttributes: { maxlength: 255 }, showCancelButton: true,
        confirmButtonText: 'Rechazar', cancelButtonText: 'Volver', confirmButtonColor: '#dc2626',
        inputValidator: function (v) { if (!v || $.trim(v).length < 5) return 'Escriba el motivo (mínimo 5 letras).'; } })
        .then(function (r) { if (r.isConfirmed) enviar('api/capacitaciones/gestionar.php', { accion: 'rechazar', id: id, motivo: r.value }).catch(function () { }); });
    } else if (a === 'cancelar') {
      Swal.fire({ title: '¿Cancelar esta solicitud?', input: 'text', inputLabel: 'Motivo (opcional)', inputAttributes: { maxlength: 255 }, icon: 'question', showCancelButton: true,
        confirmButtonText: 'Sí, cancelar', cancelButtonText: 'No', confirmButtonColor: '#dc2626' })
        .then(function (r) { if (r.isConfirmed) enviar('api/capacitaciones/solicitudes.php', { accion: 'cancelar', id: id, motivo: r.value || '' }).catch(function () { }); });
    }
  },

  /* ---------------------------------------------------------- formularios */
  _formNueva: function () {
    var s = this;
    Api.get('api/capacitaciones/temas.php').then(function (temas) {
      var h = '<form id="form-cap-nueva" autocomplete="off"><div class="row g-3">' +
        '<div class="col-md-7"><label class="form-label">Tema <span class="text-danger">*</span></label><select class="form-select" id="capn-tema_id"><option value="">— Elija un tema —</option>';
      (temas || []).forEach(function (t) { h += '<option value="' + esc(t.id) + '">' + esc(t.nombre) + (t.duracion_horas ? ' (' + t.duracion_horas + ' h)' : '') + '</option>'; });
      h += '<option value="0">Otro (escribir cuál)…</option></select><div class="form-text" id="capn-tema-ayuda"></div></div>' +
        '<div class="col-md-5 d-none" id="capn-otro-col"><label class="form-label">¿Cuál tema?</label><input class="form-control" id="capn-tema_otro" maxlength="150" placeholder="Ej. Excel avanzado"></div>' +
        '<div class="col-12"><label class="form-label">¿Qué necesitan aprender? <span class="text-danger">*</span></label><textarea class="form-control" id="capn-detalle" rows="3" maxlength="500" placeholder="Cuéntenos el objetivo, el nivel del grupo y para qué lo necesitan"></textarea></div>' +
        '<div class="col-md-4"><label class="form-label">Participantes <span class="text-danger">*</span></label><input type="number" class="form-control" id="capn-participantes" min="1" max="500" value="1"></div>' +
        '<div class="col-md-4"><label class="form-label">Modalidad</label><select class="form-select" id="capn-modalidad">' + Object.keys(CAP_MODAL).map(function (k) { return s._opt(k, CAP_MODAL[k], 'indistinto'); }).join('') + '</select></div>' +
        '<div class="col-md-4"><label class="form-label">Fecha deseada</label><input type="date" class="form-control" id="capn-fecha_deseada" min="' + s._hoy() + '"><div class="form-text">Opcional.</div></div>' +
        '<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="capn-urgente"><label class="form-check-label" for="capn-urgente">Es urgente (hay una fecha límite cercana)</label></div></div></div>' +
        '<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Su solicitud llegará a Sistemas. Podrá ver aquí cada cambio de estado y recibirá un aviso en la campana.</div>' +
        '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary" id="capn-ok"><i class="bi bi-send me-1"></i>Enviar solicitud</button></div></form>';
      Modal.abrir('Solicitar capacitación', h, 'modal-lg');
      $('#capn-tema_id').on('change', function () {
        var otro = $(this).val() === '0';
        $('#capn-otro-col').toggleClass('d-none', !otro);
        var t = (temas || []).filter(function (x) { return String(x.id) === $('#capn-tema_id').val(); })[0];
        $('#capn-tema-ayuda').text(t && t.descripcion ? t.descripcion : '');
      });
      $('#form-cap-nueva').off('submit').on('submit', function (e) {
        e.preventDefault();
        var d = { accion: 'crear', tema_id: $('#capn-tema_id').val() === '0' ? '' : $('#capn-tema_id').val(), tema_otro: $.trim($('#capn-tema_otro').val() || ''),
          detalle: $.trim($('#capn-detalle').val()), participantes: $('#capn-participantes').val(), modalidad: $('#capn-modalidad').val(),
          fecha_deseada: $('#capn-fecha_deseada').val(), prioridad: $('#capn-urgente').is(':checked') ? 'alta' : 'normal' };
        if (!$('#capn-tema_id').val()) { Toast.warning('Elija un tema o «Otro»'); return; }
        $('#capn-ok').prop('disabled', true);
        s._post('api/capacitaciones/solicitudes.php', d).then(function (r) {
          Modal.cerrar(); Toast.success(r.mensaje || 'Solicitud enviada'); s._grupoMias = 'curso';
          $('#cap-filtro-mias button').removeClass('active').filter('[data-g=curso]').addClass('active');
          s._refrescar();
        }).catch(function (er) { $('#capn-ok').prop('disabled', false); s._errores('capn-', er); });
      });
    }).catch(function () { });
  },

  _formProgramar: function (f) {
    var s = this;
    Api.get('api/catalogos/select.php', { tipo: 'tecnicos' }).then(function (tec) {
      var fp = f.fecha_programada || '';
      var h = '<form id="form-cap-prog" autocomplete="off"><div class="mb-2"><b>' + esc(f.codigo || '') + '</b> · ' + esc(f.tema || '') + '<div class="small text-muted">' + (f.participantes || '') + ' participante(s) · ' + esc(f.modalidad_texto || '') +
        (f.fecha_deseada ? ' · desea ' + esc(s._fecha(f.fecha_deseada)) : '') + '</div></div><div class="row g-3">' +
        '<div class="col-md-4"><label class="form-label">Fecha <span class="text-danger">*</span></label><input type="date" class="form-control" id="capp-fecha" min="' + s._hoy() + '" value="' + esc(String(fp).slice(0, 10)) + '"></div>' +
        '<div class="col-md-4"><label class="form-label">Hora <span class="text-danger">*</span></label><input type="time" class="form-control" id="capp-hora" value="' + esc(s._hora(fp)) + '"></div>' +
        '<div class="col-md-4"><label class="form-label">Duración (min)</label><input type="number" class="form-control" id="capp-duracion_min" min="15" max="720" step="15" value="' + esc(f.duracion_min || 60) + '"></div>' +
        '<div class="col-md-7"><label class="form-label">Lugar o enlace <span class="text-danger">*</span></label><input class="form-control" id="capp-lugar" maxlength="150" value="' + esc(f.lugar || '') + '" placeholder="Sala de reuniones 2 / enlace de Teams"></div>' +
        '<div class="col-md-5"><label class="form-label">Capacitador</label><select class="form-select" id="capp-capacitador_usuario_id"><option value="">Yo</option>' +
        (tec || []).map(function (t) { return s._opt(t.id, t.texto, f.capacitador_usuario_id); }).join('') + '</select></div></div>' +
        '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-success" id="capp-ok"><i class="bi bi-calendar-check me-1"></i>' + (f.estado === 'programada' ? 'Reprogramar' : 'Programar') + '</button></div></form>';
      Modal.abrir(f.estado === 'programada' ? 'Reprogramar capacitación' : 'Programar capacitación', h, 'modal-lg');
      $('#form-cap-prog').off('submit').on('submit', function (e) {
        e.preventDefault();
        $('#capp-ok').prop('disabled', true);
        s._post('api/capacitaciones/gestionar.php', { accion: 'programar', id: f.id, fecha: $('#capp-fecha').val(), hora: $('#capp-hora').val(), duracion_min: $('#capp-duracion_min').val(),
          lugar: $.trim($('#capp-lugar').val()), capacitador_usuario_id: $('#capp-capacitador_usuario_id').val() }).then(function (r) {
          Modal.cerrar(); Toast.success(r.mensaje); s._refrescar();
        }).catch(function (er) { $('#capp-ok').prop('disabled', false); s._errores('capp-', er); });
      });
    }).catch(function () { });
  },

  _formRealizar: function (f) {
    var s = this;
    var h = '<form id="form-cap-real" autocomplete="off"><div class="mb-2"><b>' + esc(f.codigo || '') + '</b> · ' + esc(f.tema || '') + '</div><div class="row g-3">' +
      '<div class="col-md-4"><label class="form-label">Asistentes</label><input type="number" class="form-control" id="capr-asistentes" min="0" max="1000" value="' + esc(f.participantes || '') + '"></div>' +
      '<div class="col-12"><label class="form-label">Resultado / observaciones</label><textarea class="form-control" id="capr-resultado" rows="3" maxlength="500" placeholder="Qué se vio, pendientes, material entregado…"></textarea></div></div>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary" id="capr-ok"><i class="bi bi-check2-circle me-1"></i>Marcar realizada</button></div></form>';
    Modal.abrir('Cerrar capacitación', h, '');
    $('#form-cap-real').off('submit').on('submit', function (e) {
      e.preventDefault();
      $('#capr-ok').prop('disabled', true);
      s._post('api/capacitaciones/gestionar.php', { accion: 'realizar', id: f.id, asistentes: $('#capr-asistentes').val(), resultado: $.trim($('#capr-resultado').val()) }).then(function (r) {
        Modal.cerrar(); Toast.success(r.mensaje); s._refrescar();
      }).catch(function (er) { $('#capr-ok').prop('disabled', false); s._errores('capr-', er); });
    });
  },

  _formEvaluar: function (f) {
    var s = this, nota = 0;
    var h = '<form id="form-cap-eval"><div class="mb-2"><b>' + esc(f.codigo || '') + '</b> · ' + esc(f.tema || '') + '</div>' +
      '<div class="cap-estrellas mb-2" id="cape-est">' + [1, 2, 3, 4, 5].map(function (i) { return '<i class="bi bi-star" data-n="' + i + '"></i>'; }).join('') + '</div>' +
      '<textarea class="form-control" id="cape-comentario" rows="2" maxlength="300" placeholder="Comentario (opcional)"></textarea>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ahora no</button> <button type="submit" class="btn btn-warning" id="cape-ok"><i class="bi bi-star-fill me-1"></i>Enviar calificación</button></div></form>';
    Modal.abrir('¿Cómo estuvo la capacitación?', h, '');
    $('#cape-est').on('click', 'i', function () {
      nota = +$(this).attr('data-n');
      $('#cape-est i').each(function () { var on = +$(this).attr('data-n') <= nota; $(this).toggleClass('on', on).toggleClass('bi-star-fill', on).toggleClass('bi-star', !on); });
    });
    $('#form-cap-eval').off('submit').on('submit', function (e) {
      e.preventDefault();
      if (!nota) { Toast.warning('Elija de 1 a 5 estrellas'); return; }
      $('#cape-ok').prop('disabled', true);
      s._post('api/capacitaciones/solicitudes.php', { accion: 'evaluar', id: f.id, calificacion: nota, comentario: $.trim($('#cape-comentario').val()) }).then(function (r) {
        Modal.cerrar(); Toast.success(r.mensaje); s._refrescar();
      }).catch(function () { $('#cape-ok').prop('disabled', false); });
    });
  },

  /* ---------------------------------------------------------- temas */
  _cargarTemas: function () {
    var s = this;
    return Api.get('api/capacitaciones/temas.php', { todos: 1 }).then(function (l) { s._temas = l || []; s._renderTemas(); }).catch(function () { });
  },
  _renderTemas: function () {
    var h = '';
    this._temas.forEach(function (t) {
      h += '<tr' + (+t.activo ? '' : ' class="table-secondary"') + '><td><b>' + esc(t.nombre) + '</b></td><td class="small">' + esc(t.descripcion || '') + '</td><td class="text-center">' + (t.duracion_horas != null ? t.duracion_horas : '—') +
        '</td><td class="text-center">' + t.usos + '</td><td>' + (+t.activo ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>') + '</td>' +
        '<td class="text-nowrap text-end"><button class="btn btn-sm btn-outline-secondary me-1" data-tema="edit" data-id="' + t.id + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
        '<button class="btn btn-sm btn-outline-' + (+t.activo ? 'warning' : 'success') + ' me-1" data-tema="toggle" data-id="' + t.id + '" title="' + (+t.activo ? 'Desactivar' : 'Activar') + '"><i class="bi bi-' + (+t.activo ? 'slash-circle' : 'arrow-counterclockwise') + '"></i></button>' +
        '<button class="btn btn-sm btn-outline-danger" data-tema="del" data-id="' + t.id + '" title="Eliminar (solo si nadie lo usó)"><i class="bi bi-trash3"></i></button></td></tr>';
    });
    $('#cap-tb-temas tbody').html(h || '<tr><td colspan="6" class="text-center text-muted py-3">No hay temas.</td></tr>');
  },
  _formTema: function (t) {
    var s = this, nuevo = !t;
    t = t || { nombre: '', descripcion: '', duracion_horas: '' };
    var h = '<form id="form-cap-tema" autocomplete="off"><div class="row g-3">' +
      '<div class="col-md-8"><label class="form-label">Nombre del tema <span class="text-danger">*</span></label><input class="form-control" id="capt-nombre" maxlength="120" value="' + esc(t.nombre) + '"></div>' +
      '<div class="col-md-4"><label class="form-label">Duración (horas)</label><input type="number" class="form-control" id="capt-duracion_horas" min="0.5" max="99" step="0.5" value="' + esc(t.duracion_horas == null ? '' : t.duracion_horas) + '"></div>' +
      '<div class="col-12"><label class="form-label">Descripción</label><input class="form-control" id="capt-descripcion" maxlength="255" value="' + esc(t.descripcion || '') + '"></div></div>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary" id="capt-ok"><i class="bi bi-check-lg"></i> ' + (nuevo ? 'Agregar tema' : 'Guardar') + '</button></div></form>';
    Modal.abrir(nuevo ? 'Nuevo tema' : 'Editar tema', h, '');
    $('#form-cap-tema').off('submit').on('submit', function (e) {
      e.preventDefault();
      $('#capt-ok').prop('disabled', true);
      s._post('api/capacitaciones/temas.php', { accion: 'guardar', id: nuevo ? 0 : t.id, nombre: $.trim($('#capt-nombre').val()), descripcion: $.trim($('#capt-descripcion').val()), duracion_horas: $('#capt-duracion_horas').val() }).then(function (r) {
        Modal.cerrar(); Toast.success(r.mensaje); s._cargarTemas();
      }).catch(function (er) { $('#capt-ok').prop('disabled', false); s._errores('capt-', er); });
    });
  }
};
App.registrar('capacitaciones', Capacitaciones);
