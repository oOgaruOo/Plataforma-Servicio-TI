/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Anuncios del sistema
   Banners del dashboard + generador / gestor de anuncios.
   API: api/anuncios/{listar,guardar,cerrar}.php
   Uso: Anuncios.cargar('#zona')            -> pinta los anuncios que le llegan a la persona
        Anuncios.gestionar(function(){...}) -> modal para crear / editar / programar (configuracion.gestionar)
   ============================================================ */
var Anuncios = {
  TIPOS: {
    info:          { t: 'Información',   cls: 'alert-primary', ico: 'bi-info-circle',        badge: 'text-bg-primary' },
    aviso:         { t: 'Aviso',         cls: 'alert-warning', ico: 'bi-exclamation-triangle', badge: 'text-bg-warning text-dark' },
    urgente:       { t: 'Urgente',       cls: 'alert-danger',  ico: 'bi-exclamation-octagon', badge: 'text-bg-danger' },
    mantenimiento: { t: 'Mantenimiento', cls: 'alert-info',    ico: 'bi-tools',              badge: 'text-bg-info' }
  },
  ESTADOS: {
    vigente:    ['Vigente', 'text-bg-success'],
    programado: ['Programado', 'text-bg-info'],
    vencido:    ['Vencido', 'text-bg-secondary'],
    oculto:     ['Oculto', 'text-bg-dark']
  },
  PLANTILLAS: [
    { n: 'Mantenimiento programado', tipo: 'mantenimiento', titulo: 'Mantenimiento programado del sistema',
      msg: 'El [fecha] de [hora inicio] a [hora fin] el sistema estará en mantenimiento y puede no estar disponible. Guarde su trabajo antes de ese horario.' },
    { n: 'Servicio interrumpido', tipo: 'urgente', titulo: 'Interrupción del servicio de [servicio]',
      msg: 'Estamos atendiendo una interrupción de [servicio]. TI está trabajando en la solución y se avisará cuando se restablezca.' },
    { n: 'Cambio de contraseña', tipo: 'aviso', titulo: 'Cambie su contraseña antes del [fecha]',
      msg: 'Por seguridad, todas las contraseñas deben renovarse antes del [fecha]. Use una contraseña que no haya usado antes y no la comparta con nadie.' },
    { n: 'Cómo pedir apoyo de TI', tipo: 'info', titulo: 'Solicite apoyo de TI desde el sistema',
      msg: 'Para pedir ayuda de Sistemas registre un ticket en el menú Tickets. Así queda registrado, se prioriza y puede seguir su atención.' },
    { n: 'Novedad del sistema', tipo: 'info', titulo: 'Nueva mejora disponible: [nombre]',
      msg: 'Ya puede usar [descripción de la mejora]. Pulse Ctrl+F5 si no la ve en su pantalla.' }
  ],
  datos: null,
  filtro: 'todos',
  _cb: null,

  /* ---------------------------------------------------------- utilidades */
  _f: function (d) {   // 2026-10-12 -> 12/10/2026
    if (!d) return '';
    var m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] : String(d);
  },
  _vigencia: function (a) {
    var d = a.fecha_desde ? Anuncios._f(a.fecha_desde) + (a.hora_desde ? ' ' + a.hora_desde : '') : '';
    var h = a.fecha_hasta ? Anuncios._f(a.fecha_hasta) + (a.hora_hasta ? ' ' + a.hora_hasta : '') : '';
    if (!d && !h) return 'Sin límite';
    return (d ? 'Desde ' + d : 'Desde ya') + (h ? ' · hasta ' + h : ' · sin fin');
  },
  _msg: function (e, d) {
    if (e && e.cuerpo && e.cuerpo.errors) { Toast.warning(Object.values(e.cuerpo.errors)[0]); return; }
    if (e && e.cuerpo && e.cuerpo.message) { Toast.error(e.cuerpo.message); return; }
    Toast.error(d || 'No se pudo completar la operación.');
  },

  /** Banner (igual en el dashboard y en la vista previa). */
  html: function (a, conCierre) {
    var T = Anuncios.TIPOS[a.tipo] || Anuncios.TIPOS.info;
    var pie = '';
    if (a.fecha_hasta) pie = '<div class="opacity-75" style="font-size:.75rem">Vigente hasta ' + esc(Anuncios._f(a.fecha_hasta)) + (a.hora_hasta ? ' ' + esc(a.hora_hasta) : '') + '</div>';
    var cierre = +a.fijo
      ? '<i class="bi bi-pin-angle-fill opacity-75" title="Anuncio fijo: se muestra hasta que termine su vigencia"></i>'
      : (conCierre ? '<button type="button" class="btn-close btn-sm" data-anuncio-cerrar="' + (+a.id) + '" aria-label="Cerrar"></button>' : '');
    return '<div class="alert ' + T.cls + ' py-2 small mb-2 d-flex gap-2 align-items-start" data-anuncio="' + (+a.id || 0) + '">' +
      '<i class="bi ' + T.ico + ' mt-1"></i>' +
      '<div class="flex-grow-1"><b>' + esc(a.titulo) + '</b>' +
      '<div style="white-space:pre-line">' + esc(a.mensaje) + '</div>' + pie + '</div>' + cierre + '</div>';
  },

  /* ---------------------------------------------------------- dashboard */
  cargar: function (sel) {
    var $z = $(sel);
    return Api.get('api/anuncios/listar.php').then(function (list) {
      if (!list || !list.length) { $z.empty(); return; }
      $z.html(list.map(function (a) { return Anuncios.html(a, true); }).join(''));
      $z.off('click.anun').on('click.anun', '[data-anuncio-cerrar]', function () {
        var $a = $(this).closest('.alert');
        var id = +$(this).data('anuncio-cerrar');
        $a.remove();
        Api.post('api/anuncios/cerrar.php', { id: id }).catch(function (e) { Anuncios._msg(e, 'No se pudo recordar el cierre del anuncio.'); });
      });
    }).catch(function () {});
  },

  /* ---------------------------------------------------------- gestión */
  gestionar: function (cb) {
    var s = this;
    s._cb = cb || null;
    s.filtro = 'todos';
    Api.get('api/anuncios/listar.php', { todos: 1 }).then(function (r) {
      s.datos = r;
      var h = '<div id="an-raiz"><div id="an-vista-lista"></div><div id="an-vista-form" class="d-none"></div></div>';
      Modal.abrir('Anuncios del sistema', h, 'modal-xl');
      s._pintarLista();
      s._eventosLista();
    }).catch(function (e) { s._msg(e, 'No se pudieron cargar los anuncios.'); });
  },

  _recargar: function () {
    var s = this;
    return Api.get('api/anuncios/listar.php', { todos: 1 }).then(function (r) {
      s.datos = r;
      s._pintarLista();
      if (s._cb) s._cb();
    });
  },

  _anuncio: function (id) {
    var l = (this.datos && this.datos.anuncios) || [];
    for (var i = 0; i < l.length; i++) if (+l[i].id === +id) return l[i];
    return null;
  },

  _pintarLista: function () {
    var s = this, l = s.datos.anuncios;
    var cuenta = { todos: l.length, vigente: 0, programado: 0, vencido: 0, oculto: 0 };
    l.forEach(function (a) { cuenta[a.estado]++; });
    var chip = function (k, t) {
      return '<button type="button" class="btn btn-outline-secondary' + (s.filtro === k ? ' active' : '') + '" data-f="' + k + '">' + t + ' <span class="badge text-bg-light border">' + cuenta[k] + '</span></button>';
    };
    var h = '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">' +
      '<div class="btn-group btn-group-sm flex-wrap" id="an-chips">' + chip('todos', 'Todos') + chip('vigente', 'Vigentes') + chip('programado', 'Programados') + chip('vencido', 'Vencidos') + chip('oculto', 'Ocultos') + '</div>' +
      '<button type="button" class="btn btn-primary btn-sm" id="an-nuevo"><i class="bi bi-megaphone"></i> Nuevo anuncio</button></div>';
    var vis = l.filter(function (a) { return s.filtro === 'todos' || a.estado === s.filtro; });
    if (!vis.length) {
      h += '<div class="text-center text-muted border rounded p-4">' + (l.length ? 'No hay anuncios con ese estado.' : 'Todavía no hay anuncios. Pulse «Nuevo anuncio» para publicar el primero.') + '</div>';
    } else {
      h += '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><thead><tr><th>Anuncio</th><th>Vigencia</th><th>Llega a</th><th>Estado</th><th class="text-center" title="Personas que lo cerraron">Cerrado por</th><th></th></tr></thead><tbody>';
      vis.forEach(function (a) {
        var T = s.TIPOS[a.tipo] || s.TIPOS.info, E = s.ESTADOS[a.estado] || ['', ''];
        h += '<tr data-id="' + a.id + '"><td><span class="badge ' + T.badge + '">' + esc(T.t) + '</span>' + (+a.fijo ? ' <i class="bi bi-pin-angle-fill text-muted" title="Fijo: no se puede cerrar"></i>' : '') +
          ' <b>' + esc(a.titulo) + '</b><div class="text-muted small text-truncate" style="max-width:320px" title="' + esc(a.mensaje) + '">' + esc(a.mensaje) + '</div>' +
          '<div class="text-muted" style="font-size:.7rem">' + esc(a.autor || '—') + ' · ' + esc(a.created_at || '') + '</div></td>' +
          '<td class="small">' + esc(s._vigencia(a)) + '</td><td class="small">' + esc(a.destino_texto) + '</td>' +
          '<td><span class="badge ' + E[1] + '">' + E[0] + '</span></td><td class="text-center">' + (+a.cierres) + '</td>' +
          '<td class="text-nowrap text-end">' +
          '<button class="btn btn-sm btn-outline-primary an-edit" title="Editar"><i class="bi bi-pencil"></i></button> ' +
          '<button class="btn btn-sm btn-outline-secondary an-dup" title="Duplicar"><i class="bi bi-files"></i></button> ' +
          '<button class="btn btn-sm btn-outline-secondary an-toggle" title="' + (+a.activo ? 'Ocultar' : 'Activar') + '"><i class="bi bi-' + (+a.activo ? 'eye-slash' : 'eye') + '"></i></button> ' +
          '<button class="btn btn-sm btn-outline-danger an-del" title="Eliminar"><i class="bi bi-trash"></i></button></td></tr>';
      });
      h += '</tbody></table></div>';
    }
    $('#an-vista-lista').html(h);
  },

  _eventosLista: function () {
    var s = this;
    $('#an-raiz').off('click.an')
      .on('click.an', '#an-chips button', function () { s.filtro = $(this).data('f'); s._pintarLista(); })
      .on('click.an', '#an-nuevo', function () { s._form(null); })
      .on('click.an', '.an-edit', function () { s._form(s._anuncio($(this).closest('tr').data('id'))); })
      .on('click.an', '.an-dup', function () {
        Api.post('api/anuncios/guardar.php', { accion: 'duplicar', id: $(this).closest('tr').data('id') })
          .then(function (r) { Toast.success((r && r.mensaje) || 'Copia creada.'); return s._recargar(); })
          .catch(function (e) { s._msg(e); });
      })
      .on('click.an', '.an-toggle', function () {
        Api.post('api/anuncios/guardar.php', { accion: 'toggle', id: $(this).closest('tr').data('id') })
          .then(function (r) { Toast.success((r && r.mensaje) || 'Listo.'); return s._recargar(); })
          .catch(function (e) { s._msg(e); });
      })
      .on('click.an', '.an-del', function () {
        var a = s._anuncio($(this).closest('tr').data('id'));
        if (!a) return;
        Swal.fire({
          title: '¿Eliminar este anuncio?', text: '«' + a.titulo + '» dejará de mostrarse y no se puede recuperar. Si solo quiere esconderlo, use «Ocultar».',
          icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626'
        }).then(function (x) {
          if (!x.isConfirmed) return;
          Api.post('api/anuncios/guardar.php', { accion: 'eliminar', id: a.id })
            .then(function (r) { Toast.success((r && r.mensaje) || 'Eliminado.'); return s._recargar(); })
            .catch(function (e) { s._msg(e); });
        });
      });
  },

  /* ---------------------------------------------------------- formulario */
  _form: function (a) {
    var s = this, nuevo = !a;
    a = a || { id: 0, titulo: '', mensaje: '', tipo: 'info', fecha_desde: '', hora_desde: '', fecha_hasta: '', hora_hasta: '', destino: 'todos', destino_ids: [], fijo: 0 };
    var ids = (a.destino_ids || []).map(Number);
    var tipos = Object.keys(s.TIPOS).map(function (k) {
      return '<input type="radio" class="btn-check" name="an-tipo" id="an-t-' + k + '" value="' + k + '"' + (a.tipo === k ? ' checked' : '') + '>' +
        '<label class="btn btn-outline-secondary btn-sm" for="an-t-' + k + '"><i class="bi ' + s.TIPOS[k].ico + '"></i> ' + s.TIPOS[k].t + '</label>';
    }).join('');
    var plant = nuevo ? '<div class="col-12"><select class="form-select form-select-sm" id="an-plantilla"><option value="">Empezar desde una plantilla (opcional)…</option>' +
      s.PLANTILLAS.map(function (p, i) { return '<option value="' + i + '">' + esc(p.n) + '</option>'; }).join('') + '</select></div>' : '';
    var lista = function (arr, cls) {
      return arr.map(function (x) {
        return '<div class="form-check"><input class="form-check-input an-dest ' + cls + '" type="checkbox" value="' + x.id + '" id="an-d-' + cls + x.id + '"' + (ids.indexOf(+x.id) >= 0 ? ' checked' : '') + '>' +
          '<label class="form-check-label small" for="an-d-' + cls + x.id + '">' + esc(x.nombre) + '</label></div>';
      }).join('') || '<span class="text-muted small">No hay datos.</span>';
    };
    var campo = function (id, t, tipo, val, extra) {
      return '<label class="form-label small mb-0" for="' + id + '">' + t + '</label><input type="' + tipo + '" class="form-control form-control-sm" id="' + id + '" value="' + esc(val || '') + '" ' + (extra || '') + '><div class="invalid-feedback"></div>';
    };
    var h = '<div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">' + (nuevo ? 'Nuevo anuncio' : 'Editar anuncio') + '</h6>' +
      '<button type="button" class="btn btn-sm btn-outline-secondary" id="an-volver"><i class="bi bi-arrow-left"></i> Volver a la lista</button></div>' +
      '<form id="form-anuncio" autocomplete="off"><div class="row g-3"><div class="col-lg-7"><div class="row g-2">' + plant +
      '<div class="col-12"><label class="form-label small mb-0" for="an-titulo">Título *</label><input class="form-control" id="an-titulo" maxlength="150" value="' + esc(a.titulo) + '" placeholder="Ej: Mantenimiento del sistema el sábado"><div class="invalid-feedback"></div>' +
      '<div class="form-text text-end"><span id="an-c-titulo">0</span>/150</div></div>' +
      '<div class="col-12"><div class="small mb-1">Tipo</div><div class="btn-group flex-wrap" role="group">' + tipos + '</div></div>' +
      '<div class="col-12"><label class="form-label small mb-0" for="an-mensaje">Mensaje *</label><textarea class="form-control" id="an-mensaje" rows="4" maxlength="2000" placeholder="Qué debe saber la gente, desde cuándo y qué hacer.">' + esc(a.mensaje) + '</textarea><div class="invalid-feedback"></div>' +
      '<div class="form-text text-end"><span id="an-c-msg">0</span>/2000</div></div>' +
      '<div class="col-12"><div class="small fw-semibold mt-1">¿Cuándo se muestra?</div><div class="form-text mt-0">Déjelo vacío para mostrarlo desde ahora y sin fecha de fin.</div></div>' +
      '<div class="col-6 col-md-3">' + campo('an-desde', 'Desde (fecha)', 'date', a.fecha_desde) + '</div>' +
      '<div class="col-6 col-md-3">' + campo('an-hdesde', 'Hora', 'time', a.hora_desde) + '</div>' +
      '<div class="col-6 col-md-3">' + campo('an-hasta', 'Hasta (fecha)', 'date', a.fecha_hasta) + '</div>' +
      '<div class="col-6 col-md-3">' + campo('an-hhasta', 'Hora', 'time', a.hora_hasta) + '</div>' +
      '<div class="col-12 small"><span class="text-muted">Atajos para el fin:</span> <a href="#" class="an-atajo" data-d="0">hoy</a> · <a href="#" class="an-atajo" data-d="1">mañana</a> · <a href="#" class="an-atajo" data-d="7">en 7 días</a> · <a href="#" class="an-atajo" data-d="30">en 30 días</a> · <a href="#" class="an-atajo" data-d="-1">sin fin</a></div>' +
      '<div class="col-12"><div class="small fw-semibold mt-1">¿A quién le llega?</div>' +
      '<div class="btn-group btn-group-sm mt-1" role="group">' +
      ['todos:Todos', 'roles:Por rol', 'areas:Por área'].map(function (x) { var p = x.split(':'); return '<input type="radio" class="btn-check" name="an-destino" id="an-dd-' + p[0] + '" value="' + p[0] + '"' + (a.destino === p[0] ? ' checked' : '') + '><label class="btn btn-outline-secondary" for="an-dd-' + p[0] + '">' + p[1] + '</label>'; }).join('') + '</div>' +
      '<div class="border rounded p-2 mt-2 an-zona-roles ' + (a.destino === 'roles' ? '' : 'd-none') + '" style="max-height:130px;overflow:auto">' + lista(s.datos.roles, 'r') + '</div>' +
      '<div class="border rounded p-2 mt-2 an-zona-areas ' + (a.destino === 'areas' ? '' : 'd-none') + '" style="max-height:130px;overflow:auto">' + lista(s.datos.areas, 'a') + '</div>' +
      '<div class="text-danger small d-none" id="an-err-dest"></div></div>' +
      '<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="an-fijo"' + (+a.fijo ? ' checked' : '') + '><label class="form-check-label small" for="an-fijo">Fijo: la gente no puede cerrarlo (úselo solo para avisos importantes)</label></div>' +
      '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="an-notificar"' + (nuevo ? ' checked' : '') + '><label class="form-check-label small" for="an-notificar">' + (nuevo ? 'Avisar por notificación a quienes les llega' : 'Volver a avisar por notificación') + '</label></div></div>' +
      '</div></div>' +
      '<div class="col-lg-5"><div class="small fw-semibold mb-1">Así se verá</div><div id="an-previa"></div>' +
      '<div class="small text-muted" id="an-previa-info"></div></div></div>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" id="an-cancelar">Cancelar</button> ' +
      '<button type="submit" class="btn btn-primary" id="an-guardar"><i class="bi bi-megaphone"></i> ' + (nuevo ? 'Publicar' : 'Guardar cambios') + '</button></div></form>';

    $('#an-vista-lista').addClass('d-none');
    $('#an-vista-form').removeClass('d-none').html(h);

    var volver = function () { $('#an-vista-form').addClass('d-none').empty(); $('#an-vista-lista').removeClass('d-none'); };
    var tipoSel = function () { return $('input[name=an-tipo]:checked').val() || 'info'; };
    var destSel = function () { return $('input[name=an-destino]:checked').val() || 'todos'; };
    var idsSel = function () {
      var d = destSel();
      return $(d === 'roles' ? '.an-dest.r:checked' : '.an-dest.a:checked').map(function () { return this.value; }).get();
    };
    var leer = function () {
      return {
        id: a.id, titulo: $.trim($('#an-titulo').val()), mensaje: $.trim($('#an-mensaje').val()), tipo: tipoSel(),
        fecha_desde: $('#an-desde').val() || '', hora_desde: $('#an-hdesde').val() || '',
        fecha_hasta: $('#an-hasta').val() || '', hora_hasta: $('#an-hhasta').val() || '',
        fijo: $('#an-fijo').prop('checked') ? 1 : 0
      };
    };
    var previa = function () {
      var d = leer();
      $('#an-c-titulo').text(d.titulo.length); $('#an-c-msg').text(d.mensaje.length);
      var p = $.extend({}, d, { titulo: d.titulo || 'Título del anuncio', mensaje: d.mensaje || 'Aquí aparecerá el mensaje.', hora_desde: d.hora_desde, hora_hasta: d.hora_hasta });
      $('#an-previa').html(Anuncios.html(p, true));
      var dest = destSel(), n = idsSel().length;
      $('#an-previa-info').html(
        '<i class="bi bi-calendar-event"></i> ' + esc(Anuncios._vigencia(d)) + '<br>' +
        '<i class="bi bi-people"></i> ' + (dest === 'todos' ? 'Todos los usuarios' : (dest === 'roles' ? n + ' rol(es) elegido(s)' : n + ' área(s) elegida(s)')) +
        (d.fijo ? '<br><i class="bi bi-pin-angle-fill"></i> Fijo: no se puede cerrar' : ''));
    };
    var zonas = function () {
      var d = destSel();
      $('.an-zona-roles').toggleClass('d-none', d !== 'roles');
      $('.an-zona-areas').toggleClass('d-none', d !== 'areas');
    };

    $('#an-volver,#an-cancelar').on('click', volver);
    $('#form-anuncio').on('input change', previa);
    $('input[name=an-destino]').on('change', function () { zonas(); previa(); });
    $('#an-plantilla').on('change', function () {
      var p = s.PLANTILLAS[+this.value];
      if (!p) return;
      $('#an-titulo').val(p.titulo); $('#an-mensaje').val(p.msg);
      $('#an-t-' + p.tipo).prop('checked', true);
      previa();
      Toast.info('Complete los datos entre [corchetes] antes de publicar.');
    });
    $('.an-atajo').on('click', function (e) {
      e.preventDefault();
      var d = +$(this).data('d');
      if (d < 0) { $('#an-hasta,#an-hhasta').val(''); }
      else {
        var f = new Date(); f.setDate(f.getDate() + d);
        var m = f.getMonth() + 1, dd = f.getDate();
        $('#an-hasta').val(f.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (dd < 10 ? '0' : '') + dd);
      }
      previa();
    });

    $('#form-anuncio').on('submit', function (e) {
      e.preventDefault();
      $('#form-anuncio .is-invalid').removeClass('is-invalid');
      $('#an-err-dest').addClass('d-none');
      var d = leer();
      if (/\[[^\]]+\]/.test(d.titulo + ' ' + d.mensaje) && !s._avisadoCorchetes) {
        s._avisadoCorchetes = true;
        Toast.warning('El texto aún tiene datos entre [corchetes]. Revíselo; si es correcto, pulse Publicar otra vez.');
        return;
      }
      s._avisadoCorchetes = false;
      var dest = destSel();
      var $b = $('#an-guardar').prop('disabled', true);
      Api.post('api/anuncios/guardar.php', $.extend({ accion: 'guardar' }, d, {
        destino: dest, destino_ids: dest === 'todos' ? '' : idsSel().join(','),
        notificar: $('#an-notificar').prop('checked') ? 1 : 0
      })).then(function (r) {
        Toast.success((r && r.mensaje) || 'Guardado.');
        return s._recargar().then(volver);
      }).catch(function (err) {
        $b.prop('disabled', false);
        var er = err && err.cuerpo && err.cuerpo.errors;
        if (!er) { s._msg(err); return; }
        var mapa = { titulo: '#an-titulo', mensaje: '#an-mensaje', fecha_desde: '#an-desde', hora_desde: '#an-hdesde', fecha_hasta: '#an-hasta', hora_hasta: '#an-hhasta' };
        Object.keys(er).forEach(function (k) {
          if (mapa[k]) $(mapa[k]).addClass('is-invalid').siblings('.invalid-feedback').text(er[k]);
          else if (k === 'destino_ids') $('#an-err-dest').removeClass('d-none').text(er[k]);
          else Toast.warning(er[k]);
        });
      });
    });
    previa();
  }
};
