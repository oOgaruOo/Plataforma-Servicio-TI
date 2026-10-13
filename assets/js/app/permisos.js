/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Permisos: catálogo, matriz por rol y ajustes por usuario
   Vista: views/permisos/lista.php
   API:   api/configuracion/permisos.php (vista=catalogo|matriz|ajustes|quien, op=guardar|eliminar|toggle_rol|quitar_ajuste)
   ============================================================ */
var Permisos = {
  cat: null,          // respuesta de vista=catalogo
  mat: null,          // respuesta de vista=matriz
  aj: null,           // respuesta de vista=ajustes
  filtro: 'todos',
  _url: 'api/configuracion/permisos.php',

  init: function () {
    var s = this;
    s.cat = s.mat = s.aj = null;
    s.filtro = 'todos';

    $('#btn-nuevo-permiso').off('click.pm').on('click.pm', function () { s._nuevo(); });
    $('#pm-q,#pm-mod').off('input.pm change.pm').on('input.pm change.pm', function () { s._pintarCatalogo(); });
    $('#pm-chips').off('click.pm').on('click.pm', 'button', function () {
      s.filtro = $(this).data('f');
      $('#pm-chips button').removeClass('active');
      $(this).addClass('active');
      s._pintarCatalogo();
    });
    $('#lista-permisos').off('click.pm').on('click.pm', '.pm-quien,.pm-editar,.pm-borrar', function () {
      var id = +$(this).closest('.pm-fila').data('id');
      var p = s._permiso(id);
      if (!p) return;
      if ($(this).hasClass('pm-quien')) s._quien(p);
      else if ($(this).hasClass('pm-editar')) s._editar(p);
      else s._borrar(p);
    });

    $('#mx-q,#mx-mod').off('input.pm change.pm').on('input.pm change.pm', function () { s._pintarMatriz(); });
    $('#pm-matriz').off('change.pm').on('change.pm', 'input.mx-chk', function () { s._toggle($(this)); });

    $('#aj-q,#aj-tipo').off('input.pm change.pm').on('input.pm change.pm', function () { s._pintarAjustes(); });
    $('#pm-ajustes').off('click.pm').on('click.pm', '.aj-quitar', function () {
      s._quitarAjuste(+$(this).data('u'), +$(this).data('p'), $(this).data('nombre'));
    });

    $('#pm-tabs button[data-bs-toggle=tab]').off('shown.bs.tab.pm').on('shown.bs.tab.pm', function () {
      var v = $(this).data('vista');
      if (v === 'mat') s._cargarMatriz();
      else if (v === 'aj') s._cargarAjustes();
      else s._cargar();
    });

    s._cargar();
  },

  /* ---------------------------------------------------------- utilidades */
  _permiso: function (id) {
    var l = (this.cat && this.cat.permisos) || [];
    for (var i = 0; i < l.length; i++) if (+l[i].id === +id) return l[i];
    return null;
  },
  _msg: function (e, d) {
    if (e && e.cuerpo && e.cuerpo.errors) { Toast.warning(Object.values(e.cuerpo.errors)[0]); return; }
    if (e && e.cuerpo && e.cuerpo.message) { Toast.error(e.cuerpo.message); return; }
    Toast.error(d || 'No se pudo completar la operación.');
  },
  _llenarModulos: function (sel, modulos) {
    var $s = $(sel), v = $s.val() || '';
    var h = '<option value="">Todos los módulos</option>';
    Object.keys(modulos || {}).forEach(function (k) {
      h += '<option value="' + esc(k) + '"' + (k === v ? ' selected' : '') + '>' + esc(modulos[k]) + '</option>';
    });
    $s.html(h);
  },

  /* ---------------------------------------------------------- catálogo */
  _cargar: function () {
    var s = this;
    Api.get(s._url, { vista: 'catalogo' }).then(function (r) {
      s.cat = r;
      var l = r.permisos || [];
      $('#pm-stats [data-k=total]').text(l.length);
      $('#pm-stats [data-k=modulos]').text(Object.keys(r.modulos || {}).length);
      $('#pm-stats [data-k=sensibles]').text(l.filter(function (p) { return +p.sensible; }).length);
      $('#pm-stats [data-k=sinuso]').text(l.filter(function (p) { return +p.n_roles_reales === 0; }).length);
      $('#pm-stats [data-k=ajustes]').text(l.reduce(function (a, p) { return a + (+p.aj_concedidos) + (+p.aj_revocados); }, 0));
      s._llenarModulos('#pm-mod', r.modulos);
      s._pintarCatalogo();
    }).catch(function () { $('#lista-permisos').html('<div class="text-danger">No se pudieron cargar los permisos.</div>'); });
  },

  _pintarCatalogo: function () {
    var s = this;
    if (!s.cat) return;
    var q = ($('#pm-q').val() || '').toLowerCase().trim(), mod = $('#pm-mod').val() || '', f = s.filtro;
    var l = s.cat.permisos.filter(function (p) {
      if (mod && p.modulo !== mod) return false;
      if (q && (p.modulo + '.' + p.accion + ' ' + (p.descripcion || '') + ' ' + p.modulo_nombre).toLowerCase().indexOf(q) < 0) return false;
      if (f === 'sensibles' && !+p.sensible) return false;
      if (f === 'sinuso' && +p.n_roles_reales !== 0) return false;
      if (f === 'ajustes' && !(+p.aj_concedidos + +p.aj_revocados)) return false;
      if (f === 'propios' && +p.sistema) return false;
      return true;
    });
    if (!l.length) { $('#lista-permisos').html('<div class="text-muted p-3 text-center">No hay permisos con ese filtro.</div>'); return; }

    var grupos = {}, orden = [];
    l.forEach(function (p) { if (!grupos[p.modulo]) { grupos[p.modulo] = []; orden.push(p.modulo); } grupos[p.modulo].push(p); });
    var h = '';
    orden.forEach(function (m) {
      var g = grupos[m];
      h += '<div class="border rounded p-2 mb-2 pm-grupo"><div class="d-flex justify-content-between align-items-center mb-1">' +
        '<b class="text-uppercase small text-primary">' + esc(g[0].modulo_nombre) + ' <span class="text-muted fw-normal">· ' + esc(m) + '</span></b>' +
        '<span class="badge text-bg-light border">' + g.length + '</span></div>';
      g.forEach(function (p) {
        var chips = '';
        (p.roles || []).slice(0, 4).forEach(function (n) { chips += '<span class="pm-chip' + (n === 'super_admin' ? ' pm-super' : '') + '">' + esc(n) + '</span>'; });
        if ((p.roles || []).length > 4) chips += '<span class="pm-chip">+' + ((p.roles || []).length - 4) + '</span>';
        if (+p.n_roles_reales === 0) chips += '<span class="badge text-bg-light border text-muted">sin asignar</span>';
        var aj = '';
        if (+p.aj_concedidos) aj += ' <span class="badge text-bg-success" title="Usuarios a los que se les concedió además de su rol">+' + p.aj_concedidos + ' usuario(s)</span>';
        if (+p.aj_revocados) aj += ' <span class="badge text-bg-danger" title="Usuarios a los que se les quitó aunque su rol lo tiene">−' + p.aj_revocados + ' usuario(s)</span>';
        h += '<div class="d-flex justify-content-between align-items-start gap-2 py-1 border-bottom pm-fila" data-id="' + p.id + '">' +
          '<div class="small flex-grow-1"><code>' + esc(p.modulo + '.' + p.accion) + '</code>' +
          (+p.sensible ? ' <span class="badge text-bg-warning text-dark" title="Da control amplio o borra datos">Sensible</span>' : '') +
          (+p.sistema ? ' <span class="badge text-bg-secondary" title="Lo usa el programa">Sistema</span>' : '') +
          (p.descripcion ? '<span class="text-muted"> — ' + esc(p.descripcion) + '</span>' : '<span class="text-muted fst-italic"> — sin descripción</span>') + aj +
          '<div class="mt-1">' + chips + '</div></div>' +
          '<div class="text-nowrap">' +
          '<button class="btn btn-sm btn-outline-secondary pm-quien" title="Quién lo tiene"><i class="bi bi-people"></i></button> ' +
          '<button class="btn btn-sm btn-outline-secondary pm-editar" title="Editar descripción"><i class="bi bi-pencil"></i></button> ' +
          '<button class="btn btn-sm btn-outline-danger pm-borrar" title="' + (+p.sistema ? 'Lo usa el sistema: no se puede eliminar' : 'Eliminar') + '"' + (+p.sistema ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>' +
          '</div></div>';
      });
      h += '</div>';
    });
    $('#lista-permisos').html(h);
  },

  _quien: function (p) {
    Api.get(this._url, { vista: 'quien', id: p.id }).then(function (r) {
      var h = '<div class="small"><div class="mb-2">' + (r.permiso.descripcion ? esc(r.permiso.descripcion) : '<span class="text-muted">Sin descripción</span>') + '</div>' +
        '<div class="fw-semibold mb-1"><i class="bi bi-shield-check"></i> Roles que lo tienen</div>';
      if (!r.roles.length) h += '<div class="text-muted mb-2">Ningún rol.</div>';
      else {
        h += '<ul class="list-group mb-3">';
        r.roles.forEach(function (x) {
          h += '<li class="list-group-item d-flex justify-content-between align-items-center py-1"><span>' + esc(x.nombre) + '</span>' +
            '<span class="badge text-bg-light border">' + (+x.n_usuarios) + ' usuario(s)</span></li>';
        });
        h += '</ul>';
      }
      h += '<div class="fw-semibold mb-1"><i class="bi bi-person-gear"></i> Ajustes individuales</div>';
      if (!r.usuarios.length) h += '<div class="text-muted">Nadie tiene un ajuste para este permiso.</div>';
      else {
        h += '<ul class="list-group">';
        r.usuarios.forEach(function (u) {
          h += '<li class="list-group-item d-flex justify-content-between align-items-center py-1"><span>' + esc(u.nombre_completo) + ' <span class="text-muted">(' + esc(u.usuario) + ' · ' + esc(u.rol) + ')</span></span>' +
            (+u.concedido ? '<span class="badge text-bg-success">concedido</span>' : '<span class="badge text-bg-danger">quitado</span>') + '</li>';
        });
        h += '</ul>';
      }
      h += '</div><div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>';
      Modal.abrir('Quién tiene ' + p.modulo + '.' + p.accion, h, 'modal-lg');
    }).catch(function (e) { Permisos._msg(e); });
  },

  _nuevo: function () {
    var s = this;
    var mods = Object.keys((s.cat && s.cat.modulos) || {});
    var dl = mods.map(function (m) { return '<option value="' + esc(m) + '">'; }).join('');
    var h = '<form id="form-permiso" autocomplete="off"><div class="row g-3">' +
      '<div class="col-md-6"><label class="form-label">Módulo *</label><input class="form-control" id="np-modulo" list="np-mods" maxlength="40" placeholder="ej: inventario">' +
      '<datalist id="np-mods">' + dl + '</datalist><div class="invalid-feedback"></div><div class="form-text">Minúsculas, números y _. Elija uno existente o escriba uno nuevo.</div></div>' +
      '<div class="col-md-6"><label class="form-label">Acción *</label><input class="form-control" id="np-accion" maxlength="20" placeholder="ej: ver, usar, exportar">' +
      '<div class="invalid-feedback"></div></div>' +
      '<div class="col-12"><label class="form-label">Descripción</label><input class="form-control" id="np-desc" maxlength="200" placeholder="Qué permite hacer"></div>' +
      '<div class="col-12"><div class="alert alert-light border py-2 small mb-0">Se creará: <code id="np-codigo">modulo.accion</code>. ' +
      'Luego asígnelo a un rol en «Matriz por rol».</div></div></div>' +
      '<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> ' +
      '<button type="submit" class="btn btn-primary" id="np-ok"><i class="bi bi-check-lg"></i> Crear permiso</button></div></form>';
    Modal.abrir('Nuevo permiso', h, 'modal-lg');
    var norm = function (v) { return (v || '').toLowerCase().replace(/\s+/g, '_'); };
    $('#np-modulo,#np-accion').on('input', function () {
      $('#np-codigo').text((norm($('#np-modulo').val()) || 'modulo') + '.' + (norm($('#np-accion').val()) || 'accion'));
    });
    $('#form-permiso').on('submit', function (e) {
      e.preventDefault();
      $('#form-permiso .is-invalid').removeClass('is-invalid');
      var $b = $('#np-ok').prop('disabled', true);
      Api.post(s._url, { op: 'guardar', id: 0, modulo: norm($('#np-modulo').val()), accion: norm($('#np-accion').val()), descripcion: $.trim($('#np-desc').val()) })
        .then(function (r) {
          Modal.cerrar();
          Toast.success((r && r.mensaje) || 'Permiso creado.');
          s.mat = null;
          s._cargar();
        }).catch(function (err) {
          $b.prop('disabled', false);
          var er = err && err.cuerpo && err.cuerpo.errors;
          if (er) {
            if (er.modulo) $('#np-modulo').addClass('is-invalid').siblings('.invalid-feedback').text(er.modulo);
            if (er.accion) $('#np-accion').addClass('is-invalid').siblings('.invalid-feedback').text(er.accion);
          } else s._msg(err);
        });
    });
    setTimeout(function () { $('#np-modulo').trigger('focus'); }, 200);
  },

  _editar: function (p) {
    var s = this;
    Swal.fire({
      title: 'Descripción de ' + p.modulo + '.' + p.accion,
      input: 'text', inputValue: p.descripcion || '', inputAttributes: { maxlength: 200 },
      inputPlaceholder: 'Qué permite hacer',
      showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar', confirmButtonColor: '#2563eb'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      Api.post(s._url, { op: 'guardar', id: p.id, modulo: p.modulo, accion: p.accion, descripcion: r.value || '' })
        .then(function (x) { Toast.success((x && x.mensaje) || 'Guardado.'); s._cargar(); })
        .catch(function (e) { s._msg(e); });
    });
  },

  _borrar: function (p) {
    var s = this;
    var impacto = [];
    if (+p.n_roles_reales) impacto.push('se quita de ' + p.n_roles_reales + ' rol(es)');
    if (+p.aj_concedidos + +p.aj_revocados) impacto.push('se borran ' + (+p.aj_concedidos + +p.aj_revocados) + ' ajuste(s) por usuario');
    Swal.fire({
      title: '¿Eliminar «' + p.modulo + '.' + p.accion + '»?',
      text: impacto.length ? ('Al eliminarlo ' + impacto.join(' y ') + '.') : 'No lo tiene ningún rol ni usuario.',
      icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      Api.post(s._url, { op: 'eliminar', id: p.id })
        .then(function (x) { Toast.success((x && x.mensaje) || 'Eliminado.'); s.mat = null; s.aj = null; s._cargar(); })
        .catch(function (e) { s._msg(e); });
    });
  },

  /* ---------------------------------------------------------- matriz */
  _cargarMatriz: function () {
    var s = this;
    if (s.mat) { s._pintarMatriz(); return; }
    Api.get(s._url, { vista: 'matriz' }).then(function (r) {
      s.mat = r;
      var mods = {};
      r.permisos.forEach(function (p) { mods[p.modulo] = p.modulo_nombre; });
      s._llenarModulos('#mx-mod', mods);
      $('#mx-aviso').html(r.puede_editar ? '' : '<i class="bi bi-lock"></i> Solo lectura: para cambiar roles necesita el permiso «usuarios.gestionar».');
      s._pintarMatriz();
    }).catch(function () { $('#pm-matriz').html('<div class="text-danger">No se pudo cargar la matriz.</div>'); });
  },

  _pintarMatriz: function () {
    var s = this, m = s.mat;
    if (!m) return;
    var q = ($('#mx-q').val() || '').toLowerCase().trim(), mod = $('#mx-mod').val() || '';
    var tiene = {};
    m.roles.forEach(function (r) { tiene[r.id] = {}; (m.asignado[r.id] || []).forEach(function (id) { tiene[r.id][id] = 1; }); });
    var permisos = m.permisos.filter(function (p) {
      if (mod && p.modulo !== mod) return false;
      return !q || (p.modulo + '.' + p.accion + ' ' + (p.descripcion || '')).toLowerCase().indexOf(q) >= 0;
    });
    var total = m.permisos.length;
    var h = '<table class="pm-matriz table-sm"><thead><tr><th>Permiso</th>';
    m.roles.forEach(function (r) {
      var n = r.super ? total : (m.asignado[r.id] || []).length;
      h += '<th title="' + esc(r.descripcion || '') + '"><div>' + esc(r.nombre) + (r.super ? ' <i class="bi bi-lock-fill text-warning"></i>' : '') + '</div>' +
        '<div class="text-muted fw-normal" style="font-size:.7rem">' + n + '/' + total + ' · ' + r.n_usuarios + ' usuario(s)</div></th>';
    });
    h += '</tr></thead><tbody>';
    if (!permisos.length) h += '<tr><td colspan="' + (m.roles.length + 1) + '" class="text-muted text-center p-3">No hay permisos con ese filtro.</td></tr>';
    var ultimo = '';
    permisos.forEach(function (p) {
      if (p.modulo !== ultimo) {
        h += '<tr class="pm-mod"><td colspan="' + (m.roles.length + 1) + '">' + esc(p.modulo_nombre) + '</td></tr>';
        ultimo = p.modulo;
      }
      h += '<tr class="' + (+p.sensible ? 'pm-sens' : '') + '"><td><code>' + esc(p.modulo + '.' + p.accion) + '</code>' +
        (+p.sensible ? ' <i class="bi bi-exclamation-triangle-fill text-warning" title="Permiso sensible"></i>' : '') +
        (p.descripcion ? '<div class="text-muted" style="font-size:.75rem">' + esc(p.descripcion) + '</div>' : '') + '</td>';
      m.roles.forEach(function (r) {
        var on = r.super || tiene[r.id][p.id];
        var bloqueado = r.super || !m.puede_editar;
        h += '<td class="' + (r.super ? 'pm-super' : '') + '"><input type="checkbox" class="form-check-input mx-chk" data-r="' + r.id + '" data-p="' + p.id + '"' +
          (on ? ' checked' : '') + (bloqueado ? ' disabled' : '') +
          ' aria-label="' + esc(r.nombre + ' · ' + p.modulo + '.' + p.accion) + '"></td>';
      });
      h += '</tr>';
    });
    h += '</tbody></table>';
    $('#pm-matriz').removeClass('p-3 small text-muted').html(h);
  },

  _toggle: function ($c) {
    var s = this, m = s.mat;
    var rid = +$c.data('r'), pid = +$c.data('p'), on = $c.prop('checked');
    $c.prop('disabled', true);
    Api.post(s._url, { op: 'toggle_rol', rol_id: rid, permiso_id: pid, concedido: on ? 1 : 0 })
      .then(function (r) {
        var l = m.asignado[rid] = m.asignado[rid] || [];
        var i = l.indexOf(pid);
        if (on && i < 0) l.push(pid);
        if (!on && i >= 0) l.splice(i, 1);
        $c.prop('disabled', false);
        Toast.success((r && r.mensaje) || 'Guardado.');
        s._pintarMatriz();
        s.cat = null; s.aj = null;
      }).catch(function (e) {
        $c.prop('checked', !on).prop('disabled', false);
        s._msg(e);
      });
  },

  /* ---------------------------------------------------------- ajustes por usuario */
  _cargarAjustes: function () {
    var s = this;
    if (s.aj) { s._pintarAjustes(); return; }
    Api.get(s._url, { vista: 'ajustes' }).then(function (r) { s.aj = r; s._pintarAjustes(); })
      .catch(function () { $('#pm-ajustes').html('<div class="text-danger">No se pudieron cargar los ajustes.</div>'); });
  },

  _pintarAjustes: function () {
    var s = this, a = s.aj;
    if (!a) return;
    var q = ($('#aj-q').val() || '').toLowerCase().trim(), t = $('#aj-tipo').val();
    var l = a.ajustes.filter(function (x) {
      if (t !== '' && String(x.concedido) !== t) return false;
      return !q || (x.usuario + ' ' + x.nombre_completo + ' ' + x.permiso + ' ' + x.rol).toLowerCase().indexOf(q) >= 0;
    });
    if (!l.length) { $('#pm-ajustes').html('<div class="text-muted p-3 text-center">' + (a.ajustes.length ? 'No hay ajustes con ese filtro.' : 'Nadie tiene ajustes individuales: todos usan los permisos de su rol.') + '</div>'); return; }
    var h = '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><thead><tr><th>Usuario</th><th>Rol</th><th>Permiso</th><th>Ajuste</th><th></th></tr></thead><tbody>';
    l.forEach(function (x) {
      h += '<tr><td>' + esc(x.nombre_completo) + ' <span class="text-muted small">(' + esc(x.usuario) + ')</span></td><td>' + esc(x.rol) + '</td>' +
        '<td><code>' + esc(x.permiso) + '</code>' + (+x.sensible ? ' <span class="badge text-bg-warning text-dark">Sensible</span>' : '') + '</td>' +
        '<td>' + (+x.concedido ? '<span class="badge text-bg-success">Concedido</span>' : '<span class="badge text-bg-danger">Quitado</span>') +
        (+x.redundante ? ' <span class="badge text-bg-light border text-muted" title="Su rol ya da el mismo resultado">sin efecto</span>' : '') + '</td>' +
        '<td class="text-center">' + (a.puede_quitar ? '<button class="btn btn-sm btn-outline-danger aj-quitar" data-u="' + x.usuario_id + '" data-p="' + x.permiso_id + '" data-nombre="' + esc(x.usuario + ' · ' + x.permiso) + '" title="Quitar el ajuste"><i class="bi bi-x-lg"></i></button>' : '') + '</td></tr>';
    });
    h += '</tbody></table></div>';
    $('#pm-ajustes').removeClass('small text-muted').html(h);
  },

  _quitarAjuste: function (uid, pid, nombre) {
    var s = this;
    Swal.fire({
      title: '¿Quitar este ajuste?', text: nombre + ': la persona vuelve a tener lo que le da su rol.',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#2563eb'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      Api.post(s._url, { op: 'quitar_ajuste', usuario_id: uid, permiso_id: pid })
        .then(function (x) { Toast.success((x && x.mensaje) || 'Ajuste quitado.'); s.aj = null; s.cat = null; s._cargarAjustes(); })
        .catch(function (e) { s._msg(e); });
    });
  }
};
App.registrar('permisos', Permisos);
