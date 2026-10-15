/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - RR.HH. / Planillas (gestion interna, SIN validez tributaria/legal)
   Vista: views/rrhh/planillas.php · Boleta: views/rrhh/boleta.php
   API:   api/planillas/{conceptos,generar,listar,detalle,cerrar,resumen}.php
   ============================================================ */
var Planillas = {
  perm: { gestionar: false },
  tabla: null,
  _conceptos: [],

  _money: function (n) { return 'S/ ' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
  _periodoActual: function () { var d = new Date(), m = d.getMonth() + 1; return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m; },

  init: function () {
    var s = this, $r = $('#pla-raiz');
    if (!$r.length) return;
    s.perm = { gestionar: +$r.attr('data-gestionar') === 1 };
    $('#pla-periodo').val(s._periodoActual());

    $('#pla-tabs button[data-bs-toggle=tab]').off('shown.bs.tab.pla').on('shown.bs.tab.pla', function () {
      if ($(this).attr('data-vista') === 'cc') s._cargarConceptos();
    });

    s.tabla = DT.server('#pla-tb', 'api/planillas/listar.php', function () {
      return { periodo: $('#pla-periodo').val(), f_texto: $('#plaf-q').val() };
    }, [
      { data: null, render: function (r) { return '<div>' + esc(r.nombres + ' ' + r.apellidos) + '</div><div class="text-muted small">' + esc(r.dni) + (r.area ? ' · ' + esc(r.area) : '') + '</div>'; } },
      { data: null, className: 'text-end', render: function (r) { return Planillas._money(r.sueldo_base); } },
      { data: null, className: 'text-end', render: function (r) { return Planillas._money(r.total_ingresos); } },
      { data: null, className: 'text-end', render: function (r) { return Planillas._money(r.total_descuentos); } },
      { data: null, className: 'text-end fw-bold', render: function (r) { return Planillas._money(r.neto); } },
      { data: null, render: function (r) { return r.estado === 'cerrada' ? '<span class="badge text-bg-success">Cerrada</span>' : '<span class="badge text-bg-secondary">Borrador</span>'; } },
      { data: null, orderable: false, render: function (r) {
          var h = '<a class="btn btn-sm btn-outline-primary" target="_blank" href="' + BASE_URL + 'views/rrhh/boleta.php?id=' + r.id + '" title="Ver boleta"><i class="bi bi-receipt"></i></a>';
          if (Planillas.perm.gestionar && r.estado === 'cerrada') h += ' <button class="btn btn-sm btn-outline-secondary pla-reabrir" data-id="' + r.id + '" title="Reabrir"><i class="bi bi-unlock"></i></button>';
          return h;
        } }
    ], { order: [[0, 'asc']] });

    $('#pla-periodo').off('change.pla').on('change.pla', function () { s.tabla.draw(); s._resumen(); });
    $('#plaf-q').off('keyup.pla').on('keyup.pla', function (e) { if (e.key === 'Enter') s.tabla.draw(); });
    $('#pla-btn-generar').off('click.pla').on('click.pla', function () { s._generar(); });
    $('#pla-btn-cerrar').off('click.pla').on('click.pla', function () { s._cerrarPeriodo(); });
    $('#pla-tb').off('click.pla').on('click.pla', '.pla-reabrir', function () { s._reabrir(+$(this).attr('data-id')); });
    $('#pla-btn-nuevo-cc').off('click.pla').on('click.pla', function () { s._formConcepto(null); });
    $('#pla-tb-cc').off('click.pla')
      .on('click.pla', '.cc-editar', function () { s._formConcepto(s._conceptoPorId(+$(this).attr('data-id'))); })
      .on('click.pla', '.cc-toggle', function () { s._toggleConcepto(+$(this).attr('data-id'), $(this).attr('data-activo') === '1'); });

    s._resumen();
  },

  _resumen: function () {
    Api.get('api/planillas/resumen.php').then(function (r) {
      $('#pla-k-gen').text(r.generadas); $('#pla-k-cerr').text(r.cerradas); $('#pla-k-neto').text(Planillas._money(r.total_neto));
    }).catch(function () {});
  },

  _generar: function () {
    var s = this, periodo = $('#pla-periodo').val();
    if (!periodo) { Toast.warning('Elija el periodo.'); return; }
    Swal.fire({ title: 'Generar / recalcular planilla', text: 'Se generará (o recalculará) el borrador de ' + periodo + ' para el personal activo con contrato vigente.', icon: 'question', showCancelButton: true, confirmButtonText: 'Generar', cancelButtonText: 'Cancelar' })
      .then(function (r) { if (!r.isConfirmed) return;
        Api.post('api/planillas/generar.php', { periodo: periodo }).then(function (d) {
          s.tabla.draw(false); s._resumen(); Toast.success(d.generadas + ' planilla(s) generada(s)' + (d.omitidas_sin_contrato ? ', ' + d.omitidas_sin_contrato + ' omitida(s) sin contrato' : '') + '.');
        }).catch(function () {});
      });
  },

  _cerrarPeriodo: function () {
    var s = this, periodo = $('#pla-periodo').val();
    Swal.fire({ title: 'Cerrar periodo ' + periodo + '?', text: 'Las planillas en borrador de ese periodo quedarán cerradas (ya no se recalculan).', icon: 'warning', showCancelButton: true, confirmButtonText: 'Cerrar periodo', confirmButtonColor: '#dc3545', cancelButtonText: 'Cancelar' })
      .then(function (r) { if (!r.isConfirmed) return;
        Api.post('api/planillas/cerrar.php', { accion: 'cerrar', periodo: periodo }).then(function (d) {
          s.tabla.draw(false); s._resumen(); Toast.success(d.cerradas + ' planilla(s) cerrada(s).');
        }).catch(function () {});
      });
  },

  _reabrir: function (id) {
    var s = this;
    Swal.fire({ title: 'Reabrir esta planilla?', icon: 'question', showCancelButton: true, confirmButtonText: 'Reabrir', cancelButtonText: 'Cancelar' })
      .then(function (r) { if (!r.isConfirmed) return;
        Api.post('api/planillas/cerrar.php', { accion: 'reabrir', id: id }).then(function () { s.tabla.draw(false); s._resumen(); Toast.success('Planilla reabierta.'); }).catch(function () {});
      });
  },

  _cargarConceptos: function () {
    var s = this;
    Api.get('api/planillas/conceptos.php').then(function (lista) {
      s._conceptos = lista || [];
      var h = s._conceptos.map(function (c) {
        return '<tr>' +
          '<td>' + esc(c.nombre) + '</td>' +
          '<td>' + (c.tipo === 'ingreso' ? '<span class="badge text-bg-success">Ingreso</span>' : '<span class="badge text-bg-danger">Descuento</span>') + '</td>' +
          '<td>' + (c.modo === 'porcentaje' ? 'Porcentaje' : 'Monto fijo') + '</td>' +
          '<td class="text-end">' + (c.modo === 'porcentaje' ? Number(c.valor) + '%' : Planillas._money(c.valor)) + '</td>' +
          '<td>' + (+c.activo ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>') + '</td>' +
          '<td>' + (Planillas.perm.gestionar ?
            '<button class="btn btn-sm btn-outline-primary cc-editar" data-id="' + c.id + '" title="Editar"><i class="bi bi-pencil"></i></button> ' +
            '<button class="btn btn-sm btn-outline-secondary cc-toggle" data-id="' + c.id + '" data-activo="' + (+c.activo) + '" title="' + (+c.activo ? 'Desactivar' : 'Activar') + '"><i class="bi bi-' + (+c.activo ? 'eye-slash' : 'eye') + '"></i></button>' : '') +
          '</td></tr>';
      }).join('');
      $('#pla-tb-cc tbody').html(h || '<tr><td colspan="6" class="text-center text-muted py-3">Sin conceptos registrados.</td></tr>');
    }).catch(function () {});
  },

  _conceptoPorId: function (id) { for (var i = 0; i < this._conceptos.length; i++) if (+this._conceptos[i].id === id) return this._conceptos[i]; return null; },

  _formConcepto: function (c) {
    var s = this, esNuevo = !c;
    var html = '<form id="form-cc" onsubmit="return false"><div class="row">' +
      '<div class="col-12 mb-3"><label>Nombre *</label><input class="form-control" id="cc-nombre" maxlength="80" value="' + esc(c ? c.nombre : '') + '"></div>' +
      '<div class="col-md-6 mb-3"><label>Tipo *</label><select class="form-select" id="cc-tipo">' +
        '<option value="ingreso"' + (c && c.tipo === 'ingreso' ? ' selected' : '') + '>Ingreso</option>' +
        '<option value="descuento"' + (!c || c.tipo === 'descuento' ? ' selected' : '') + '>Descuento</option></select></div>' +
      '<div class="col-md-6 mb-3"><label>Modo *</label><select class="form-select" id="cc-modo">' +
        '<option value="fijo"' + (!c || c.modo === 'fijo' ? ' selected' : '') + '>Monto fijo (S/)</option>' +
        '<option value="porcentaje"' + (c && c.modo === 'porcentaje' ? ' selected' : '') + '>Porcentaje del sueldo base</option></select></div>' +
      '<div class="col-md-6 mb-3"><label>Valor *</label><input type="number" step="0.01" min="0" class="form-control" id="cc-valor" value="' + esc(c ? c.valor : 0) + '"></div>' +
      '<div class="col-md-6 mb-3"><label>Orden</label><input type="number" class="form-control" id="cc-orden" value="' + esc(c ? c.orden : 0) + '"></div>' +
      '</div><div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> ' +
      '<button type="button" class="btn btn-primary" id="cc-btn-guardar">Guardar</button></div></form>';
    Modal.abrir(esNuevo ? 'Nuevo concepto' : 'Editar concepto', html, 'modal-md');
    $('#cc-btn-guardar').on('click', function () {
      var $b = $(this).prop('disabled', true);
      Api.post('api/planillas/conceptos.php', {
        accion: 'guardar', id: c ? c.id : 0, nombre: $('#cc-nombre').val(), tipo: $('#cc-tipo').val(), modo: $('#cc-modo').val(),
        valor: $('#cc-valor').val(), orden: $('#cc-orden').val()
      }).then(function () { Modal.cerrar(); s._cargarConceptos(); Toast.success('Concepto guardado.'); })
        .catch(function (e) { $b.prop('disabled', false); if (e && e.cuerpo && e.cuerpo.errors) Toast.warning(Object.values(e.cuerpo.errors)[0]); });
    });
  },

  _toggleConcepto: function (id, activoActual) {
    var s = this;
    Api.post('api/planillas/conceptos.php', { accion: activoActual ? 'desactivar' : 'activar', id: id })
      .then(function () { s._cargarConceptos(); Toast.success('Concepto actualizado.'); }).catch(function () {});
  }
};
App.registrar('planillas', Planillas);
