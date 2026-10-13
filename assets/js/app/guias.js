/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
// ============================================================
// SIGTI - Centro de ayuda (guias)
// ============================================================
var Guias = {
  datos: null,
  cat: '',
  q: '',

  init: function () {
    var s = this;
    s.cat = ''; s.q = '';
    $('#gd-detalle').addClass('d-none').empty();
    $('#gd-lista').removeClass('d-none');
    Api.get('api/guias/catalogo.php').then(function (d) {
      s.datos = d;
      s._chips(); s._faq(); s._glosario(); s._pintar();
    });
    $('#gd-q').off('input.gd').on('input.gd', function () { s.q = $(this).val(); s._pintar(); });
    $('#gd-chips').off('click.gd').on('click.gd', '.gd-chip', function () {
      s.cat = $(this).attr('data-c') || ''; s._chips(); s._pintar();
    });
    $('#gd-grid').off('click.gd').on('click.gd', '.gd-card', function () { s.abrir($(this).attr('data-id')); });
    $('#gd-raiz').off('click.gdv')
      .on('click.gdv', '#gd-volver', function () { s._volver(); })
      .on('click.gdv', '.gd-ir', function (e) { e.preventDefault(); Router.ir($(this).attr('data-ruta')); })
      .on('click.gdv', '.gd-imp', function () { window.open(BASE_URL + 'views/guias/ver.php?id=' + encodeURIComponent($(this).attr('data-id')), '_blank'); })
      .on('click.gdv', '.gd-indice a', function (e) {
        e.preventDefault();
        var el = document.getElementById($(this).attr('data-t'));
        if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      })
      .on('click.gdv', '.gd-rel', function () { Guias.abrir($(this).attr('data-id')); });
  },

  _norm: function (t) {
    return String(t == null ? '' : t).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  },

  _texto: function (g) {
    var t = [g.titulo, g.resumen, g.categoria, g.para, (g.tags || []).join(' ')];
    (g.secciones || []).forEach(function (s) {
      t.push(s.titulo); (s.pasos || []).forEach(function (p) { t.push(p); });
      if (s.nota) t.push(s.nota); if (s.alerta) t.push(s.alerta);
    });
    return Guias._norm(t.join(' '));
  },

  /** Resalta las palabras buscadas (sobre texto ya escapado, sin etiquetas). */
  _resaltar: function (html) {
    var ps = Guias._norm(Guias.q).split(/\s+/).filter(function (x) { return x.length > 1; });
    var norm = Guias._norm(html);
    if (!ps.length || norm.length !== html.length) return html;
    var re = new RegExp(ps.map(function (p) { return p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|'), 'g');
    var out = '', last = 0, m;
    while ((m = re.exec(norm)) !== null) {
      if (!m[0].length) { re.lastIndex++; continue; }
      var amp = html.lastIndexOf('&', m.index);
      if (amp > last - 1 && amp >= 0 && html.indexOf(';', amp) >= m.index) continue; // dentro de una entidad (&amp;)
      out += html.slice(last, m.index) + '<mark class="gd-m">' + html.slice(m.index, m.index + m[0].length) + '</mark>';
      last = m.index + m[0].length;
    }
    return out + html.slice(last);
  },

  _chips: function () {
    var s = this, h = '<span class="gd-chip' + (s.cat ? '' : ' activo') + '" data-c="">Todas</span>';
    var hay = {};
    (s.datos.guias || []).forEach(function (g) { hay[g.categoria] = 1; });
    (s.datos.categorias || []).forEach(function (c) {
      if (hay[c]) h += '<span class="gd-chip' + (s.cat === c ? ' activo' : '') + '" data-c="' + esc(c) + '">' + esc(c) + '</span>';
    });
    $('#gd-chips').html(h);
  },

  _pintar: function () {
    var s = this, ps = s._norm(s.q).split(/\s+/).filter(Boolean), n = 0, h = '';
    (s.datos.guias || []).forEach(function (g) {
      if (s.cat && g.categoria !== s.cat) return;
      if (ps.length) {
        var t = s._texto(g);
        for (var i = 0; i < ps.length; i++) if (t.indexOf(ps[i]) < 0) return;
      }
      n++;
      h += '<div class="col-md-6 col-xl-4"><div class="gd-card" style="--gc:' + esc(g.color) + '" data-id="' + esc(g.id) + '">' +
        '<div class="d-flex gap-3"><div class="gd-ico"><i class="bi bi-' + esc(g.icono) + '"></i></div><div>' +
        '<div class="fw-bold">' + s._resaltar(esc(g.titulo)) + '</div>' +
        '<div class="small text-muted">' + esc(g.categoria) + (g.minutos ? ' · ' + g.minutos + ' min de lectura' : '') + '</div></div></div>' +
        '<p class="small mt-2 mb-0">' + s._resaltar(esc(g.resumen)) + '</p></div></div>';
    });
    $('#gd-grid').html(h);
    $('#gd-vacio').toggleClass('d-none', n > 0);
    // las preguntas frecuentes y el glosario se ocultan al buscar sin coincidencias en ellos
    var hayEx = !ps.length || s._faqCoincide(ps) || s._glosCoincide(ps);
    $('#gd-extra').toggleClass('d-none', !hayEx);
    s._faq(ps); s._glosario(ps);
  },

  _faqCoincide: function (ps) {
    return (this.datos.faq || []).some(function (f) {
      var t = Guias._norm(f.p + ' ' + f.r + ' ' + (f.tags || []).join(' '));
      return ps.every(function (p) { return t.indexOf(p) >= 0; });
    });
  },
  _glosCoincide: function (ps) {
    return (this.datos.glosario || []).some(function (g) {
      var t = Guias._norm(g.t + ' ' + g.d);
      return ps.every(function (p) { return t.indexOf(p) >= 0; });
    });
  },

  _faq: function (ps) {
    ps = ps || [];
    var h = '', k = 0;
    (this.datos.faq || []).forEach(function (f, i) {
      var t = Guias._norm(f.p + ' ' + f.r + ' ' + (f.tags || []).join(' '));
      if (!ps.every(function (p) { return t.indexOf(p) >= 0; })) return;
      k++;
      h += '<div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#gd-f' + i + '">' + esc(f.p) + '</button></h2>' +
        '<div id="gd-f' + i + '" class="accordion-collapse collapse' + (ps.length ? ' show' : '') + '"><div class="accordion-body small">' + esc(f.r) + '</div></div></div>';
    });
    $('#gd-faq').html(h || '<div class="text-muted small p-2">Sin coincidencias.</div>');
  },

  _glosario: function (ps) {
    ps = ps || [];
    var h = '';
    (this.datos.glosario || []).forEach(function (g) {
      var t = Guias._norm(g.t + ' ' + g.d);
      if (!ps.every(function (p) { return t.indexOf(p) >= 0; })) return;
      h += '<dt class="small">' + esc(g.t) + '</dt><dd class="small text-muted">' + esc(g.d) + '</dd>';
    });
    $('#gd-glosario').html(h || '<div class="text-muted small">Sin coincidencias.</div>');
  },

  _volver: function () {
    $('#gd-detalle').addClass('d-none').empty();
    $('#gd-lista').removeClass('d-none');
    window.scrollTo(0, 0);
  },

  abrir: function (id) {
    var s = this, g = null;
    (s.datos.guias || []).forEach(function (x) { if (x.id === id) g = x; });
    if (!g) return;
    // guias tecnicas: paginas imprimibles que ya existian
    if (g.url) { window.open(BASE_URL + g.url, '_blank'); return; }
    var h = '<div class="d-flex flex-wrap gap-2 align-items-center mb-3">' +
      '<button class="btn btn-outline-secondary btn-sm" id="gd-volver"><i class="bi bi-arrow-left"></i> Todas las guías</button>' +
      '<div class="ms-auto d-flex gap-2">' +
      (g.ruta ? '<a href="#" class="btn btn-primary btn-sm gd-ir" data-ruta="' + esc(g.ruta) + '"><i class="bi bi-box-arrow-up-right"></i> Ir al módulo</a>' : '') +
      '<button class="btn btn-outline-primary btn-sm gd-imp" data-id="' + esc(g.id) + '"><i class="bi bi-printer"></i> Imprimir / PDF</button></div></div>';
    h += '<div class="row g-3"><div class="col-lg-9"><div class="card shadow-sm"><div class="card-body p-4">' +
      '<div class="d-flex gap-3 align-items-center mb-2"><div class="gd-ico" style="--gc:' + esc(g.color) + '"><i class="bi bi-' + esc(g.icono) + '"></i></div>' +
      '<div><h4 class="mb-0">' + esc(g.titulo) + '</h4><div class="small text-muted">' + esc(g.categoria) + ' · Dirigida a: ' + esc(g.para) + (g.minutos ? ' · ' + g.minutos + ' min de lectura' : '') + '</div></div></div>' +
      '<p class="text-muted">' + esc(g.resumen) + '</p>';
    (g.secciones || []).forEach(function (sec, i) {
      h += '<div class="gd-sec" id="gd-s' + i + '"><h5>' + esc(sec.titulo) + '</h5>';
      (sec.pasos || []).forEach(function (p, j) {
        h += '<div class="gd-paso"><span class="n">' + (j + 1) + '</span><span>' + esc(p) + '</span></div>';
      });
      if (sec.nota) h += '<div class="gd-nota"><i class="bi bi-lightbulb me-1"></i><b>Consejo:</b> ' + esc(sec.nota) + '</div>';
      if (sec.alerta) h += '<div class="gd-alerta"><i class="bi bi-exclamation-triangle me-1"></i><b>Cuidado:</b> ' + esc(sec.alerta) + '</div>';
      h += '</div>';
    });
    h += '</div></div></div><div class="col-lg-3"><div class="card shadow-sm mb-3"><div class="card-header">En esta guía</div><div class="card-body p-2 gd-indice">';
    (g.secciones || []).forEach(function (sec, i) { h += '<a href="#" data-t="gd-s' + i + '">' + esc(sec.titulo) + '</a>'; });
    h += '</div></div>';
    var rel = (s.datos.guias || []).filter(function (x) { return x.id !== g.id && !x.url && x.categoria === g.categoria; }).slice(0, 4);
    if (rel.length) {
      h += '<div class="card shadow-sm"><div class="card-header">Guías relacionadas</div><div class="list-group list-group-flush">';
      rel.forEach(function (x) { h += '<a href="#" class="list-group-item list-group-item-action small gd-rel" data-id="' + esc(x.id) + '" onclick="return false"><i class="bi bi-' + esc(x.icono) + ' me-1"></i>' + esc(x.titulo) + '</a>'; });
      h += '</div></div>';
    }
    h += '</div></div>';
    $('#gd-lista').addClass('d-none');
    $('#gd-detalle').html(h).removeClass('d-none');
    window.scrollTo(0, 0);
  }
};
App.registrar('guias', Guias);
