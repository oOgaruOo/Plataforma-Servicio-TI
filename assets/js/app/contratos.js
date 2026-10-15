/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - RR.HH. / Contratos del personal
   Vista: views/rrhh/contratos.php
   API:   api/contratos/{listar,gestionar,resumen,vigente}.php
   ============================================================ */
// Debe coincidir con Contratos::TIPOS (core/Contratos.php); si se agrega un tipo nuevo alla, agregarlo tambien aqui.
var CTR_TIPOS = {indeterminado:'Plazo indeterminado',plazo_fijo:'Plazo fijo',practicante:'Practicante',locacion:'Locación de servicios',part_time:'Part time',por_horas:'Por horas'};
var CTR_COLOR = {verde:'text-bg-success',ambar:'text-bg-warning text-dark',rojo:'text-bg-danger',gris:'text-bg-secondary'};

var Contratos = {
  perm: { gestionar: false },
  tabla: null,
  tablaVigentes: null,

  _fecha: function (s) { s = String(s || '').slice(0, 10); return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s.slice(8,10)+'/'+s.slice(5,7)+'/'+s.slice(0,4) : '—'; },
  _money: function (n) { return n == null || n === '' ? '—' : 'S/ ' + Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); },

  // Columnas compartidas por «Personal contratado» (solo vigentes) e «Historial completo» (todos los estados).
  // responsivePriority: cuando la pantalla/ventana es angosta, DataTables esconde solo las columnas con el
  // numero mas alto (detras de un boton "+" que las muestra al tocarlo); Persona, Estado y Acciones son las
  // que se quedan visibles hasta el final.
  _columnas: function () {
    return [
      { data: null, responsivePriority: 1, render: function (r) { return '<div>' + esc(r.nombres + ' ' + r.apellidos) + '</div><div class="text-muted small">' + esc(r.dni) + (r.area ? ' · ' + esc(r.area) : '') + '</div>'; } },
      { data: null, responsivePriority: 5, render: function (r) { return esc(CTR_TIPOS[r.tipo] || r.tipo); } },
      { data: 'puesto', responsivePriority: 7, render: function (d) { return esc(d || '—'); } },
      { data: null, responsivePriority: 4, render: function (r) { return Contratos._fecha(r.fecha_inicio); } },
      { data: null, responsivePriority: 6, render: function (r) { return r.fecha_fin ? Contratos._fecha(r.fecha_fin) : '<span class="text-muted">Sin fin</span>'; } },
      { data: null, orderable: false, responsivePriority: 8, render: function (r) { return esc(r.antiguedad_txt || '—'); } },
      { data: null, orderable: false, responsivePriority: 9, render: function (r) {
          if (!r.vac_acumula) return '<span class="text-muted small" title="Su contrato actual no acumula vacaciones">No acumula</span>';
          return '<span class="badge ' + (r.vac_disponible > 0 ? 'text-bg-success' : 'text-bg-secondary') + '">' + r.vac_disponible + ' día(s)</span>';
        } },
      { data: null, responsivePriority: 2, render: function (r) { return '<span class="badge ' + (CTR_COLOR[r.estado_color] || 'text-bg-secondary') + '">' + esc(r.estado_txt) + '</span>'; } },
      { data: null, orderable: false, responsivePriority: 3, render: function (r) {
          var h = '';
          if (Contratos.perm.gestionar) {
            if (r.vigente) {
              h += '<button class="btn btn-sm btn-outline-primary ctr-editar" data-id="' + r.id + '" title="Editar"><i class="bi bi-pencil"></i></button> ';
              h += '<button class="btn btn-sm btn-outline-success ctr-renovar" data-pid="' + r.personal_id + '" title="Renovar"><i class="bi bi-arrow-repeat"></i></button> ';
              h += '<button class="btn btn-sm btn-outline-danger ctr-finalizar" data-id="' + r.id + '" title="Finalizar"><i class="bi bi-x-circle"></i></button>';
            } else {
              h = '<span class="text-muted small">—</span>';
            }
          }
          return h;
        } }
    ];
  },

  init: function () {
    var s = this, $r = $('#ctr-raiz');
    if (!$r.length) return;
    s.perm = { gestionar: +$r.attr('data-gestionar') === 1 };

    // Personal contratado: TODAS las personas con contrato activo (f_estado fijo en 'activos'), sin excluir
    // a quienes esten por vencer o ya vencidos (eso se ve en la columna "Estado" de cada fila, no se ocultan).
    s.tablaVigentes = DT.server('#ctr-tb-vigentes', 'api/contratos/listar.php', function () {
      return { f_estado: 'activos', f_texto: $('#ctrv-q').val() };
    }, s._columnas(), { order: [[1, 'asc']], responsive: true });

    // Historial completo: todos los contratos (vigentes, renovados y finalizados), con el filtro de estado.
    s.tabla = DT.server('#ctr-tb', 'api/contratos/listar.php', function () {
      return { f_estado: $('#ctrf-estado').val(), f_texto: $('#ctrf-q').val() };
    }, s._columnas(), { order: [[3, 'desc']], responsive: true });

    // Mientras una pestaña de Bootstrap esta oculta (display:none), DataTables no puede medir el ancho
    // disponible, asi que "Historial completo" (la que no esta activa al entrar) arranca creyendo que
    // tiene todo el ancho libre y no esconde ninguna columna. Al mostrarla hay que decirle que vuelva a medir.
    $('#ctr-tabs button[data-bs-toggle="tab"]').off('shown.bs.tab.ctr').on('shown.bs.tab.ctr', function (e) {
      var destino = $(e.target).attr('data-bs-target');
      if (destino === '#ctr-pane-historial' && s.tabla) s.tabla.columns.adjust().responsive.recalc();
      if (destino === '#ctr-pane-vigentes' && s.tablaVigentes) s.tablaVigentes.columns.adjust().responsive.recalc();
    });

    $('#ctrv-q').off('keyup.ctr').on('keyup.ctr', function (e) { if (e.key === 'Enter') s.tablaVigentes.draw(); });
    $('#ctrv-limpiar').off('click.ctr').on('click.ctr', function () { $('#ctrv-q').val(''); s.tablaVigentes.draw(); });

    $('#ctrf-estado').off('change.ctr').on('change.ctr', function () { s.tabla.draw(); });
    $('#ctrf-q').off('keyup.ctr').on('keyup.ctr', function (e) { if (e.key === 'Enter') s.tabla.draw(); });
    $('#ctrf-limpiar').off('click.ctr').on('click.ctr', function () { $('#ctrf-estado').val(''); $('#ctrf-q').val(''); s.tabla.draw(); });
    $('#ctr-btn-nuevo').off('click.ctr').on('click.ctr', function () { s._form(null); });
    $('#ctr-tb, #ctr-tb-vigentes').off('click.ctr')
      .on('click.ctr', '.ctr-editar', function () { s._form({ id: +$(this).attr('data-id') }); })
      .on('click.ctr', '.ctr-renovar', function () { s._formRenovar(+$(this).attr('data-pid')); })
      .on('click.ctr', '.ctr-finalizar', function () { s._finalizar(+$(this).attr('data-id')); });

    s._resumen();
  },

  _redibujar: function () { if (this.tabla) this.tabla.draw(false); if (this.tablaVigentes) this.tablaVigentes.draw(false); },

  _resumen: function () {
    Api.get('api/contratos/resumen.php').then(function (r) {
      $('#ctr-k-vigentes').text(r.vigentes); $('#ctr-k-porvencer').text(r.por_vencer);
      $('#ctr-k-vencidos').text(r.vencidos); $('#ctr-k-indet').text(r.indeterminados);
    }).catch(function () {});
  },

  _tiposOpts: function (sel) {
    return Object.keys(CTR_TIPOS).map(function (k) { return '<option value="' + k + '"' + (sel === k ? ' selected' : '') + '>' + CTR_TIPOS[k] + '</option>'; }).join('');
  },

  // fila = null (nuevo) | {id} (editar: se usa la fila ya cargada por la tabla)
  _form: function (fila) {
    var s = this, esNuevo = !fila;

    if (esNuevo) {
      Api.get('api/catalogos/select.php', { tipo: 'personal' }).then(function (personas) {
        var html = '<form id="form-ctr" onsubmit="return false"><div class="row">' +
          '<div class="col-12 mb-3"><label>Persona *</label><select class="form-select" id="ctr-f-persona"><option value="">— Seleccione —</option>' +
          personas.map(function (p) { return '<option value="' + p.id + '">' + esc(p.texto + ' — ' + p.dni) + '</option>'; }).join('') + '</select></div>' +
          '<div class="col-md-4 mb-3"><label>Tipo de contrato *</label><select class="form-select" id="ctr-f-tipo">' + s._tiposOpts('indeterminado') + '</select></div>' +
          '<div class="col-md-4 mb-3"><label>Puesto</label><input class="form-control" id="ctr-f-puesto" maxlength="80"></div>' +
          '<div class="col-md-4 mb-3"><label>Salario (S/)</label><input type="number" step="0.01" min="0" class="form-control" id="ctr-f-salario"></div>' +
          '<div class="col-md-6 mb-3"><label>Fecha de inicio *</label><input type="date" class="form-control" id="ctr-f-inicio" value="' + Contratos._hoyISO() + '"></div>' +
          '<div class="col-md-6 mb-3"><label>Fecha de fin</label><input type="date" class="form-control" id="ctr-f-fin"><div class="form-text">Déjelo vacío si el contrato es a plazo indeterminado.</div></div>' +
          '<div class="col-12 mb-3"><label>Observaciones</label><textarea class="form-control" id="ctr-f-obs" rows="2" maxlength="250"></textarea></div>' +
          '</div><div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> ' +
          '<button type="button" class="btn btn-primary" id="ctr-btn-guardar">Guardar</button></div></form>';
        Modal.abrir('Nuevo contrato', html, 'modal-lg');
        $('#ctr-btn-guardar').on('click', function () {
          var $b = $(this).prop('disabled', true);
          Api.post('api/contratos/gestionar.php', {
            accion: 'crear', personal_id: $('#ctr-f-persona').val(), tipo: $('#ctr-f-tipo').val(), puesto: $('#ctr-f-puesto').val(),
            salario: $('#ctr-f-salario').val(), fecha_inicio: $('#ctr-f-inicio').val(), fecha_fin: $('#ctr-f-fin').val(), observaciones: $('#ctr-f-obs').val()
          }).then(function () { Modal.cerrar(); s._redibujar(); s._resumen(); Toast.success('Contrato registrado.'); })
            .catch(function (e) { $b.prop('disabled', false); if (e && e.cuerpo && e.cuerpo.errors) Toast.warning(Object.values(e.cuerpo.errors)[0]); });
        });
      });
      return;
    }

    // editar: se pide la fila directo desde la tabla DataTables (ya tiene todos los campos)
    var row = s._filaPorId(fila.id);
    if (!row) return;
    var html = '<form id="form-ctr" onsubmit="return false"><div class="row">' +
      '<div class="col-12 mb-2 text-muted small">' + esc(row.nombres + ' ' + row.apellidos + ' — ' + row.dni) + '</div>' +
      '<div class="col-md-4 mb-3"><label>Tipo de contrato *</label><select class="form-select" id="ctr-f-tipo">' + s._tiposOpts(row.tipo) + '</select></div>' +
      '<div class="col-md-4 mb-3"><label>Puesto</label><input class="form-control" id="ctr-f-puesto" maxlength="80" value="' + esc(row.puesto || '') + '"></div>' +
      '<div class="col-md-4 mb-3"><label>Salario (S/)</label><input type="number" step="0.01" min="0" class="form-control" id="ctr-f-salario" value="' + esc(row.salario || '') + '"></div>' +
      '<div class="col-md-6 mb-3"><label>Fecha de inicio *</label><input type="date" class="form-control" id="ctr-f-inicio" value="' + esc(row.fecha_inicio || '') + '"></div>' +
      '<div class="col-md-6 mb-3"><label>Fecha de fin</label><input type="date" class="form-control" id="ctr-f-fin" value="' + esc(row.fecha_fin || '') + '"></div>' +
      '<div class="col-12 mb-3"><label>Observaciones</label><textarea class="form-control" id="ctr-f-obs" rows="2" maxlength="250">' + esc(row.observaciones || '') + '</textarea></div>' +
      '</div><div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> ' +
      '<button type="button" class="btn btn-primary" id="ctr-btn-guardar">Guardar</button></div></form>';
    Modal.abrir('Editar contrato', html, 'modal-lg');
    $('#ctr-btn-guardar').on('click', function () {
      var $b = $(this).prop('disabled', true);
      Api.post('api/contratos/gestionar.php', {
        accion: 'editar', id: row.id, tipo: $('#ctr-f-tipo').val(), puesto: $('#ctr-f-puesto').val(), salario: $('#ctr-f-salario').val(),
        fecha_inicio: $('#ctr-f-inicio').val(), fecha_fin: $('#ctr-f-fin').val(), observaciones: $('#ctr-f-obs').val()
      }).then(function () { Modal.cerrar(); s._redibujar(); Toast.success('Contrato actualizado.'); })
        .catch(function (e) { $b.prop('disabled', false); if (e && e.cuerpo && e.cuerpo.errors) Toast.warning(Object.values(e.cuerpo.errors)[0]); });
    });
  },

  _formRenovar: function (personalId) {
    var s = this;
    var row = s._filaPorPersona(personalId);
    var html = '<form id="form-ctrr" onsubmit="return false"><div class="row">' +
      (row ? '<div class="col-12 mb-2 text-muted small">' + esc(row.nombres + ' ' + row.apellidos + ' — ' + row.dni) + '</div>' : '') +
      '<div class="col-md-4 mb-3"><label>Tipo de contrato *</label><select class="form-select" id="ctrr-tipo">' + s._tiposOpts(row ? row.tipo : 'indeterminado') + '</select></div>' +
      '<div class="col-md-4 mb-3"><label>Puesto</label><input class="form-control" id="ctrr-puesto" maxlength="80" value="' + esc(row ? row.puesto || '' : '') + '"></div>' +
      '<div class="col-md-4 mb-3"><label>Salario (S/)</label><input type="number" step="0.01" min="0" class="form-control" id="ctrr-salario" value="' + esc(row ? row.salario || '' : '') + '"></div>' +
      '<div class="col-md-6 mb-3"><label>Fecha de inicio *</label><input type="date" class="form-control" id="ctrr-inicio" value="' + Contratos._hoyISO() + '"></div>' +
      '<div class="col-md-6 mb-3"><label>Fecha de fin</label><input type="date" class="form-control" id="ctrr-fin"></div>' +
      '<div class="col-12 mb-3"><label>Observaciones</label><textarea class="form-control" id="ctrr-obs" rows="2" maxlength="250"></textarea></div>' +
      '</div><div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> ' +
      '<button type="button" class="btn btn-success" id="ctr-btn-renovar">Renovar</button></div></form>';
    Modal.abrir('Renovar contrato', html, 'modal-lg');
    $('#ctr-btn-renovar').on('click', function () {
      var $b = $(this).prop('disabled', true);
      Api.post('api/contratos/gestionar.php', {
        accion: 'renovar', personal_id: personalId, tipo: $('#ctrr-tipo').val(), puesto: $('#ctrr-puesto').val(), salario: $('#ctrr-salario').val(),
        fecha_inicio: $('#ctrr-inicio').val(), fecha_fin: $('#ctrr-fin').val(), observaciones: $('#ctrr-obs').val()
      }).then(function () { Modal.cerrar(); s._redibujar(); s._resumen(); Toast.success('Contrato renovado.'); })
        .catch(function (e) { $b.prop('disabled', false); if (e && e.cuerpo && e.cuerpo.errors) Toast.warning(Object.values(e.cuerpo.errors)[0]); });
    });
  },

  _finalizar: function (id) {
    var s = this;
    var MOT = ['Cese de la persona', 'Cambio a otro tipo de contrato', 'Fin de plazo sin renovación', 'Error de registro', 'Otro'];
    Swal.fire({
      title: 'Finalizar contrato', icon: 'warning', showCancelButton: true, confirmButtonText: 'Finalizar', confirmButtonColor: '#dc3545', cancelButtonText: 'Cancelar',
      html: '<div class="text-start">' +
        '<label class="form-label small">Motivo</label>' +
        '<select id="ctrf-mot" class="form-select form-select-sm mb-2">' + MOT.map(function (m) { return '<option>' + m + '</option>'; }).join('') + '</select>' +
        '</div>',
      didOpen: function () { $('#ctrf-mot').trigger('focus'); },
      preConfirm: function () { return $('#ctrf-mot').val() || 'Finalizado'; }
    }).then(function (r) {
      if (!r.isConfirmed) return;
      Api.post('api/contratos/gestionar.php', { accion: 'finalizar', id: id, motivo: r.value }).then(function () {
        s._redibujar(); s._resumen(); Toast.success('Contrato finalizado.');
      }).catch(function () {});
    });
  },

  // Busca la fila en cualquiera de las dos tablas (Personal contratado o Historial), lo que este cargado la tenga.
  _filaPorId: function (id) {
    var tablas = [this.tablaVigentes, this.tabla];
    for (var t = 0; t < tablas.length; t++) {
      if (!tablas[t]) continue;
      var datos = tablas[t].rows().data().toArray();
      for (var i = 0; i < datos.length; i++) if (+datos[i].id === +id) return datos[i];
    }
    return null;
  },
  _filaPorPersona: function (pid) {
    var tablas = [this.tablaVigentes, this.tabla];
    for (var t = 0; t < tablas.length; t++) {
      if (!tablas[t]) continue;
      var datos = tablas[t].rows().data().toArray();
      for (var i = 0; i < datos.length; i++) if (+datos[i].personal_id === +pid && +datos[i].vigente === 1) return datos[i];
    }
    return null;
  },
  _hoyISO: function () { var d = new Date(), m = d.getMonth() + 1, dd = d.getDate(); return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (dd < 10 ? '0' : '') + dd; }
};
App.registrar('contratos', Contratos);
