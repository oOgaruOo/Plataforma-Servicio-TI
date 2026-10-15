/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - RR.HH. / Asistencia del personal
   Vista: views/rrhh/asistencia.php
   API:   api/asistencia/{dia,guardar,resumen}.php
   ============================================================ */
var ASI_TIPOS = {normal:'Normal',tardanza:'Tardanza',falta:'Falta',falta_justificada:'Falta justificada',permiso:'Permiso',vacaciones:'Vacaciones'};

var Asistencia = {
  perm: { gestionar: false },
  _personas: [],

  _hoyISO: function () { var d = new Date(), m = d.getMonth() + 1, dd = d.getDate(); return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (dd < 10 ? '0' : '') + dd; },

  /** Mismo criterio que Asistencia::calcularTipoEntrada() en PHP: tolerancia de 15 min sobre las 8:00 (hasta 8:15 = Normal). */
  _calcularTipo: function (hora) { return hora ? (hora <= '08:15' ? 'normal' : 'tardanza') : null; },

  init: function () {
    var s = this, $r = $('#asi-raiz');
    if (!$r.length) return;
    s.perm = { gestionar: +$r.attr('data-gestionar') === 1 };
    $('#asi-fecha').val(s._hoyISO());

    $('#asi-fecha,#asi-area').off('change.asi').on('change.asi', function () { s._cargar(); });
    $('#asi-btn-guardar').off('click.asi').on('click.asi', function () { s._guardar(); });
    $('#asi-btn-importar').off('click.asi').on('click.asi', function () { s._importar(); });
    $('#asi-tb').off('change.asi').on('change.asi', '.asi-hora', function () {
      var $sel = $(this).closest('tr').find('.asi-tipo'), actual = $sel.val();
      if (actual === 'normal' || actual === 'tardanza' || !actual) {
        var t = s._calcularTipo($(this).val());
        if (t) $sel.val(t);
      }
    });

    s._cargar();
    s._resumenHoy();
  },

  _cargar: function () {
    var s = this;
    Api.get('api/asistencia/dia.php', { fecha: $('#asi-fecha').val(), area_id: $('#asi-area').val() }).then(function (r) {
      s._personas = r.personas || [];
      var h = s._personas.map(function (p) {
        var tipoSel = p.tipo_efectivo || 'normal';
        return '<tr data-pid="' + p.personal_id + '">' +
          '<td>' + esc(p.nombres + ' ' + p.apellidos) + '<div class="text-muted small">' + esc(p.dni) + '</div></td>' +
          '<td>' + esc(p.area || '—') + '</td>' +
          '<td><select class="form-select form-select-sm asi-tipo" ' + (s.perm.gestionar ? '' : 'disabled') + '>' +
            Object.keys(ASI_TIPOS).map(function (k) { return '<option value="' + k + '"' + (tipoSel === k ? ' selected' : '') + '>' + ASI_TIPOS[k] + '</option>'; }).join('') +
          '</select>' + (p.automatico ? '<div class="text-muted small"><i class="bi bi-magic"></i> Vacaciones aprobadas (automático)</div>' : '') + '</td>' +
          '<td><input type="time" class="form-control form-control-sm asi-hora" value="' + esc(p.hora_entrada || '') + '" ' + (s.perm.gestionar ? '' : 'disabled') + '></td>' +
          '<td><input type="time" class="form-control form-control-sm asi-hora-sal-alm" value="' + esc(p.hora_salida_almuerzo || '') + '" ' + (s.perm.gestionar ? '' : 'disabled') + '></td>' +
          '<td><input type="time" class="form-control form-control-sm asi-hora-ent-alm" value="' + esc(p.hora_entrada_almuerzo || '') + '" ' + (s.perm.gestionar ? '' : 'disabled') + '></td>' +
          '<td><input type="time" class="form-control form-control-sm asi-hora-sal" value="' + esc(p.hora_salida || '') + '" ' + (s.perm.gestionar ? '' : 'disabled') + '></td>' +
          '<td><input type="text" class="form-control form-control-sm asi-obs" maxlength="200" value="' + esc(p.observacion || '') + '" ' + (s.perm.gestionar ? '' : 'disabled') + '></td>' +
        '</tr>';
      }).join('');
      $('#asi-tb tbody').html(h || '<tr><td colspan="8" class="text-center text-muted py-3">Sin personal activo en ese filtro.</td></tr>');
    }).catch(function () {});
  },

  _resumenHoy: function () {
    Api.get('api/asistencia/resumen.php').then(function (r) {
      var h = r.hoy || {};
      $('#asi-k-normal').text(h.normal || 0); $('#asi-k-tardanza').text(h.tardanza || 0);
      $('#asi-k-falta').text((h.falta || 0) + (h.falta_justificada || 0));
      $('#asi-k-vacaciones').text(h.vacaciones || 0); $('#asi-k-sin').text(h.sin_marcar || 0);
    }).catch(function () {});
  },

  _importar: function () {
    var s = this;
    var html = '<div class="text-start">' +
      '<p class="small text-muted">Suba el Excel (.xlsx) o CSV que exporta su marcador de huella (o cualquier otro equipo). ' +
      'Columnas esperadas: <b>DNI</b>, <b>Fecha</b>, y opcionalmente Hora Entrada, Hora Salida Almuerzo, Hora Entrada Almuerzo, Hora Salida, ' +
      'Tipo (Normal/Tardanza/Falta/Falta justificada/Permiso) y Observación. Si no viene el Tipo, el sistema lo calcula solo según la hora de ' +
      'entrada y la tolerancia de 15 minutos. El DNI debe coincidir con el de Personal. ' +
      '<a href="api/asistencia/plantilla.php" target="_blank">Descargar plantilla de ejemplo</a>.</p>' +
      '<input type="file" class="form-control" id="asi-imp-archivo" accept=".csv,.txt,.xlsx">' +
      '<div id="asi-imp-resultado" class="mt-3"></div>' +
      '</div><div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> ' +
      '<button type="button" class="btn btn-primary" id="asi-btn-importar-ok"><i class="bi bi-upload me-1"></i>Importar</button></div>';
    Modal.abrir('Importar asistencia desde Excel/CSV', html, 'modal-lg');

    $('#asi-btn-importar-ok').on('click', function () {
      var archivo = $('#asi-imp-archivo')[0] && $('#asi-imp-archivo')[0].files && $('#asi-imp-archivo')[0].files[0];
      if (!archivo) { Toast.warning('Elija un archivo .csv o .xlsx.'); return; }
      if (archivo.size > 10 * 1024 * 1024) { Toast.warning('El archivo supera los 10 MB.'); return; }
      var $b = $(this).prop('disabled', true);
      var fd = new FormData(); fd.append('archivo', archivo);
      Api.postFiles('api/asistencia/importar.php', fd).then(function (r) {
        $b.prop('disabled', false);
        var h = '<div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle me-1"></i>' + (r.guardadas || 0) + ' registro(s) importado(s).</div>';
        if (r.errores && r.errores.length) {
          h += '<div class="alert alert-warning py-2 mb-0"><b>' + r.errores.length + ' fila(s) con problemas:</b><ul class="mb-0 small">' +
            r.errores.slice(0, 20).map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') +
            (r.errores.length > 20 ? '<li>…y ' + (r.errores.length - 20) + ' más.</li>' : '') + '</ul></div>';
        }
        $('#asi-imp-resultado').html(h);
        s._cargar(); s._resumenHoy();
      }).catch(function (e) {
        $b.prop('disabled', false);
        var msg = (e && e.cuerpo && e.cuerpo.errors) ? Object.values(e.cuerpo.errors)[0] : ((e && e.cuerpo && e.cuerpo.message) || 'No se pudo importar el archivo.');
        $('#asi-imp-resultado').html('<div class="alert alert-danger py-2 mb-0">' + esc(msg) + '</div>');
      });
    });
  },

  _guardar: function () {
    var s = this, fecha = $('#asi-fecha').val(), filas = [];
    $('#asi-tb tbody tr').each(function () {
      var $tr = $(this), pid = +$tr.attr('data-pid');
      if (!pid) return;
      filas.push({
        personal_id: pid, tipo: $tr.find('.asi-tipo').val(), hora_entrada: $tr.find('.asi-hora').val(),
        hora_salida_almuerzo: $tr.find('.asi-hora-sal-alm').val(), hora_entrada_almuerzo: $tr.find('.asi-hora-ent-alm').val(),
        hora_salida: $tr.find('.asi-hora-sal').val(), observacion: $tr.find('.asi-obs').val()
      });
    });
    if (!filas.length) { Toast.info('No hay personal para guardar.'); return; }
    var $b = $('#asi-btn-guardar').prop('disabled', true);
    Api.post('api/asistencia/guardar.php', { fecha: fecha, filas: JSON.stringify(filas) })
      .then(function (r) { Toast.success(r && r.guardadas ? (r.guardadas + ' registro(s) guardado(s).') : 'Guardado.'); if (fecha === s._hoyISO()) s._resumenHoy(); })
      .catch(function () {})
      .then(function () { $b.prop('disabled', false); });
  }
};
App.registrar('asistencia', Asistencia);
