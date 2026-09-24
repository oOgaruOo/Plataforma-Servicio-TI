// ============================================================
// SIGTI - Modulo Actas (plantillas LUMAT)
// ============================================================

const TIPOS_ACTA = {
  entrega:    { txt: 'Entrega',    cls: 'text-bg-primary' },
  cambio:     { txt: 'Reemplazo', cls: 'text-bg-info' },
  devolucion: { txt: 'Devolución',cls: 'text-bg-warning text-dark' }
};

const Actas = {

  tabla: null,

  init() {
    this.tabla = DT.server('#tb-actas', 'api/actas/listar.php',
      () => ({ f_tipo: $('#af-tipo').val() || '' }),
      [
        { data: 'codigo', render: v => '<strong class="nowrap">' + esc(v) + '</strong>' },
        { data: 'tipo', render: v => {
            const t = TIPOS_ACTA[v] || { txt: v, cls: 'text-bg-light' };
            return '<span class="badge ' + t.cls + ' ticket-estado">' + t.txt + '</span>';
          } },
        { data: 'colaborador' },
        { data: 'area', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'responsable', render: v => v ? '<span class="small">' + esc(v) + '</span>' : '<span class="text-muted">—</span>' },
        { data: 'fecha', className: 'nowrap', render: v => '<span class="small text-muted">' + esc(v) + '</span>' },
        { data: null, orderable: false, className: 'text-nowrap text-center',
          render: (v, t, f) =>
            '<button class="btn btn-sm btn-outline-primary btn-ver-acta me-1" title="Ver / Imprimir / PDF"><i class="bi bi-file-earmark-text"></i></button>' +
            '<button class="btn btn-sm btn-outline-success btn-wa-acta me-1" title="Enviar por WhatsApp"><i class="bi bi-whatsapp"></i></button>' +
            '<button class="btn btn-sm btn-outline-secondary btn-mail-acta" title="Enviar por correo"><i class="bi bi-envelope"></i></button>' }
      ]);

    $('#btn-filtrar-actas').on('click', () => this.tabla.draw());
    $('#af-tipo').on('change', () => this.tabla.draw());
    $('#btn-limpiar-actas').on('click', () => { $('#af-tipo').val(''); this.tabla.draw(); });

    $('#btn-nueva-acta').on('click', () => this._abrirForm());

    $('#tb-actas').on('click', '.btn-ver-acta, .btn-wa-acta, .btn-mail-acta', function () {
      const f = Actas.tabla.row($(this).closest('tr')).data();
      if (!f) return;
      if      ($(this).hasClass('btn-ver-acta'))  window.open(BASE_URL + 'acta.php?token=' + f.token, '_blank');
      else if ($(this).hasClass('btn-wa-acta'))   Actas._whatsapp(f);
      else                                        Actas._correo(f);
    });
  },

  _whatsapp(f) {
    const url = encodeURIComponent(location.origin + BASE_URL + 'acta.php?token=' + f.token);
    const msg = encodeURIComponent(
      'Adjunto el acta ' + f.codigo + ' (' + TIPOS_ACTA[f.tipo].txt.toLowerCase() +
      ') de equipos TI para ' + f.colaborador + '. Puede verla o imprimirla aquí:\n' + url);
    window.open('https://wa.me/?text=' + msg, '_blank');
  },

  _correo(f) {
    const url = encodeURIComponent(location.origin + BASE_URL + 'acta.php?token=' + f.token);
    const s = encodeURIComponent('Acta ' + f.codigo + ' — ' + TIPOS_ACTA[f.tipo].txt + ' de equipos TI');
    const b = encodeURIComponent(
      'Buen día,\n\nAdjunto el enlace del acta ' + f.codigo + ' correspondiente a ' +
      f.colaborador + ':\n' + url + '\n\nEl documento se puede ver, imprimir o guardar en PDF.\n\nSaludos,\nÁrea de Sistemas');
    window.location = 'mailto:?subject=' + s + '&body=' + b;
  },

  // ================= FORMULARIO DE GENERACION =================
  _abrirForm() {
    Api.get('api/catalogos/select.php', { tipo: 'personal' }).then(personal => {

      const html = `
      <form id="form-acta" autocomplete="off">
        <div class="row">
          <div class="col-md-4 mb-3" data-campo="tipo">
            <label class="form-label">Tipo de acta <span class="text-danger">*</span></label>
            <select class="form-select" id="ag-tipo">
              <option value="entrega">Entrega de equipo</option>
              <option value="cambio">Reemplazo de equipo (devuelve + recibe)</option>
              <option value="devolucion">Devolución por cese</option>
            </select>
          </div>
          <div class="col-md-8 mb-3" data-campo="personal_id">
            <label class="form-label">Colaborador <span class="text-danger">*</span></label>
            <select class="form-select" id="ag-personal">
              ${personal.map(p => `<option value="${p.id}">${esc(p.texto)} (${esc(p.dni)})</option>`).join('')}
            </select>
          </div>

          <div class="col-12 mb-3" data-campo="motivo" id="ag-zona-motivo"></div>

          <div class="col-12 mb-3">
            <div class="border rounded p-2">
              <div class="form-label mb-2" id="ag-titulo-dev">Equipos devueltos
                <span class="text-muted small">(los asignados al colaborador)</span></div>
              <div id="ag-equipos-dev" class="small">Seleccione un colaborador…</div>
            </div>
          </div>

          <div class="col-12 mb-3" id="ag-zona-ent">
            <div class="border rounded p-2">
              <div class="form-label mb-2">Equipos a entregar
                <span class="text-muted small">(disponibles en stock)</span></div>
              <div id="ag-equipos-ent" class="small">Cargando…</div>
            </div>
          </div>

          <div class="col-12 mb-3" id="ag-zona-checklists"></div>

          <div class="col-12 mb-3" data-campo="observaciones">
            <label class="form-label">Observaciones del acta</label>
            <textarea class="form-control" id="ag-obs" rows="2" maxlength="1000"></textarea>
          </div>
        </div>

        <div class="alert alert-info py-2 small">
          <i class="bi bi-magic"></i> Al generar se crea el documento con formato LUMAT
          (A4 imprimible → PDF) y un <b>enlace público</b> para enviarlo por WhatsApp o correo.
        </div>

        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-generar-acta">
            <i class="bi bi-file-earmark-text"></i> Generar acta</button>
        </div>
      </form>`;

      Modal.abrir('Generar acta', html, 'modal-xl');

      const pintarMotivo = () => {
        const tipo = $('#ag-tipo').val();
        if (tipo === 'cambio') {
          $('#ag-zona-motivo').html(`
            <label class="form-label">Motivo del movimiento <span class="text-danger">*</span></label>
            <select class="form-select" id="ag-motivo">
              <option value="cese">Cese de personal</option>
              <option value="renovacion">Reemplazo por renovación tecnológica</option>
              <option value="falla">Equipo con falla técnica</option>
              <option value="sin_reparacion">Equipo sin reparación</option>
              <option value="reasignacion">Cambio por reasignación interna</option>
              <option value="ascenso">Ascenso o cambio de puesto</option>
              <option value="otro">Otro</option>
            </select>
            <input type="text" class="form-control mt-2" id="ag-motivo-texto" maxlength="200" placeholder="Detalle (si es Otro)">`);
        } else {
          $('#ag-zona-motivo').empty();
        }
        // mostrar/ocultar zonas segun tipo
        $('#ag-zona-ent').toggle(tipo !== 'devolucion');

        // checklists de validacion
        let chk = '';
        if (tipo === 'cambio') {
          chk = `<div class="border rounded p-2">
            <div class="form-label mb-1">Validación de TI (marque lo realizado)</div>
            <div class="row small">
              ${['verificado|Se verificó el estado del equipo devuelto','respaldo|Respaldo de información institucional',
                 'borrado|Borrado de información del equipo anterior','configurado|Configuración del nuevo equipo',
                 'aplicaciones|Aplicaciones corporativas','antivirus|Antivirus','m365|Microsoft 365',
                 'vpn|VPN','impresoras|Impresoras','inventario|Actualización de inventario']
                .map(x => { const [v, t] = x.split('|');
                  return '<div class="col-md-6"><div class="form-check"><input class="form-check-input ag-val" type="checkbox" value="' + v + '" id="v-' + v + '">' +
                         '<label class="form-check-label" for="v-' + v + '">' + t + '</label></div></div>'; }).join('')}
            </div></div>`;
        }
        if (tipo === 'devolucion') {
          chk = `<div class="border rounded p-2">
            <div class="form-label mb-1">Verificación de accesos (cese)</div>
            <div class="row small">
              ${['credenciales|Devolución de credenciales','bloqueo|Bloqueo de cuentas corporativas',
                 'token|Entrega de token MFA','vpn|Revocación de VPN','sistemas|Revocación de accesos a sistemas',
                 'sesiones|Cierre de sesiones activas','lineas|Cierre de líneas telefónicas']
                .map(x => { const [v, t] = x.split('|');
                  return '<div class="col-md-6"><div class="form-check"><input class="form-check-input ag-acceso" type="checkbox" value="' + v + '" id="a-' + v + '">' +
                         '<label class="form-check-label" for="a-' + v + '">' + t + '</label></div></div>'; }).join('')}
            </div></div>`;
        }
        $('#ag-zona-checklists').html(chk);
      };
      $('#ag-tipo').on('change', pintarMotivo);
      pintarMotivo();

      // equipos de stock (para entrega/cambio)
      const cargarStock = () => Api.get('api/equipos/select.php', { modo: 'disponibles' })
        .then(eqs => {
          $('#ag-equipos-ent').html(eqs.length ? eqs.map(e =>
            '<div class="form-check"><input class="form-check-input ag-ent" type="checkbox" value="' + e.id + '" id="e-' + e.id + '">' +
            '<label class="form-check-label" for="e-' + e.id + '">' + esc(e.texto) + '</label></div>').join('')
            : '<span class="text-muted">No hay equipos disponibles en stock.</span>');
        }).catch(() => $('#ag-equipos-ent').html('<span class="text-muted">Error al cargar.</span>'));
      cargarStock();

      // equipos asignados al colaborador (devolución)
      const cargarAsignados = pid => {
        $('#ag-equipos-dev').html('<span class="text-muted">Cargando…</span>');
        $.get(BASE_URL + 'api/actas/asignados.php?personal=' + pid, res => {
          if (!res.success) { $('#ag-equipos-dev').html('<span class="text-muted">—</span>'); return; }
          const eqs = res.data;
          $('#ag-equipos-dev').html(eqs.length ? eqs.map(e =>
            '<div class="border rounded p-2 mb-2">' +
            '<div class="form-check"><input class="form-check-input ag-dev" type="checkbox" value="' + e.id + '" id="d-' + e.id + '">' +
            '<label class="form-check-label" for="d-' + e.id + '"><b>' + esc(e.codigo) + '</b> — ' +
            esc(e.tipo + ' ' + e.marca + ' ' + (e.modelo || '')) + (e.serie ? ' · S/N ' + esc(e.serie) : '') + '</label></div>' +
            '<div class="row g-2 mt-1 dev-extra-' + e.id + '" style="display:none">' +
            '<div class="col-5"><select class="form-select form-select-sm cond-dev" data-eq="' + e.id + '">' +
            '<option value="">Condición…</option><option>Bueno</option><option>Regular</option><option>Malo</option><option>Faltante</option></select></div>' +
            '<div class="col-7"><input type="text" class="form-control form-control-sm obs-dev" data-eq="' + e.id + '" maxlength="150" placeholder="Observación de devolución"></div>' +
            '</div></div>').join('')
            : '<span class="text-muted">El colaborador no tiene equipos asignados.</span>');

          // mostrar extras al marcar
          $('#ag-equipos-dev').off('change', '.ag-dev').on('change', '.ag-dev', function () {
            $('.dev-extra-' + $(this).val()).toggle($(this).is(':checked'));
          });
        }).fail(() => $('#ag-equipos-dev').html('<span class="text-muted">Error al cargar.</span>'));
      };
      $('#ag-personal').on('change', function () { cargarAsignados($(this).val()); });
      cargarAsignados($('#ag-personal').val());

      $('#btn-generar-acta').on('click', () => this._generar());
    });
  },

  _generar() {
    const tipo = $('#ag-tipo').val();

    const datos = {
      tipo:        tipo,
      personal_id: $('#ag-personal').val() || '',
      motivo:      $('#ag-motivo').val() || '',
      motivo_texto: $.trim($('#ag-motivo-texto').val() || ''),
      observaciones: $.trim($('#ag-obs').val() || ''),
      equipos_ent:  JSON.stringify($('.ag-ent:checked').map((i, el) => $(el).val()).get()),
      equipos_dev:  JSON.stringify($('.ag-dev:checked').map((i, el) => $(el).val()).get()),
      validacion_ti:  JSON.stringify($('.ag-val:checked').map((i, el) => $(el).val()).get()),
      verif_accesos:  JSON.stringify($('.ag-acceso:checked').map((i, el) => $(el).val()).get())
    };

    // condicion/observacion por equipo devuelto
    $('.ag-dev:checked').each(function () {
      const id = $(this).val();
      datos['cond_dev_' + id] = $('.cond-dev[data-eq="' + id + '"]').val() || '';
      datos['obs_dev_' + id]  = $.trim($('.obs-dev[data-eq="' + id + '"]').val() || '');
    });

    if (tipo === 'entrega' && JSON.parse(datos.equipos_ent).length === 0) {
      Toast.warning('Seleccione al menos un equipo a entregar.'); return;
    }
    if ((tipo === 'devolucion' || tipo === 'cambio') && JSON.parse(datos.equipos_dev).length === 0) {
      Toast.warning('Seleccione al menos un equipo devuelto.'); return;
    }

    Api.post('api/actas/generar.php', datos)
      .then(d => {
        Modal.cerrar();
        this.tabla.draw();
        // abrir el documento generado
        setTimeout(() => window.open(BASE_URL + 'acta.php?token=' + d.token, '_blank'), 400);
      })
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-acta', err.cuerpo.errors);
      });
  }
};

App.registrar('actas', Actas);