// ============================================================
// SIGTI - Modulo Asignaciones v3 (COMPLETO)
// v3: destino PERSONA o AREA + entrega/prestamo/cambio/devolucion
// ============================================================

const ESTADOS_ASIG = {
  activa:   { txt: 'Activa',    cls: 'text-bg-success' },
  vencida:  { txt: 'Vencida',   cls: 'text-bg-danger' },
  devuelta: { txt: 'Devuelta',  cls: 'text-bg-secondary' },
  cancelada:{ txt: 'Cancelada', cls: 'text-bg-dark' }
};

const Asignaciones = {

  tabla: null,

  init() {
    this.tabla = DT.server('#tb-asig', 'api/asignaciones/listar.php',
      () => ({
        f_estado: $('#asf-estado').val() || '',
        f_tipo:   $('#asf-tipo').val()   || ''
      }),
      [
        { data: 'equipo_codigo', render: (v, t, f) =>
            '<strong class="nowrap">' + esc(v) + '</strong><br>' +
            '<span class="text-muted small">' + esc(f.equipo_tipo + ' ' + f.marca) + '</span>' },
        { data: 'equipo_tipo', visible: false },
        { data: 'colaborador', render: (v, t, f) =>
            v ? esc(v) + '<br><span class="text-muted small">DNI ' + esc(f.dni) + '</span>'
            : (f.area_asignada
                ? '<span class="badge text-bg-info">ÁREA</span><br><b>' + esc(f.area_asignada) + '</b>'
                : '<span class="text-muted">—</span>') },
        { data: 'area', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'tipo', render: v => v === 'prestamo'
            ? '<span class="badge text-bg-info">Préstamo</span>'
            : '<span class="badge text-bg-primary">Permanente</span>' },
        { data: 'fecha_entrega', className: 'nowrap', render: v => '<span class="small">' + esc(v) + '</span>' },
        { data: 'fecha_devolucion_esperada', className: 'nowrap',
          render: (v, t, f) => {
            if (!v) return '<span class="text-muted">—</span>';
            return +f.vencido === 1
              ? '<span class="badge text-bg-danger">' + esc(v) + ' VENCIDO</span>'
              : '<span class="small">' + esc(v) + '</span>';
          } },
        { data: 'estado', render: (v, t, f) => {
            if (+f.vencido === 1) return '<span class="badge text-bg-danger ticket-estado">VENCIDO</span>';
            const e = ESTADOS_ASIG[v] || { txt: v, cls: 'text-bg-light' };
            return '<span class="badge ' + e.cls + ' ticket-estado">' + e.txt + '</span>';
          } },
        { data: null, orderable: false, className: 'text-nowrap text-center',
          render: (v, t, f) => {
            let h = '';
            if (['activa','vencida'].includes(f.estado) && App.permisos.includes('asignaciones.devolver')) {
              h += '<button class="btn btn-sm btn-outline-warning btn-devolver" title="Registrar devolución"><i class="bi bi-box-arrow-in-left"></i></button>';
            }
            return h || '—';
          } }
      ]);

    this._cargarStats();

    $('#btn-filtrar-asig').on('click', () => { this.tabla.draw(); this._cargarStats(); });
    $('#asf-estado, #asf-tipo').on('change', () => { this.tabla.draw(); this._cargarStats(); });
    $('#btn-limpiar-asig').on('click', () => {
      $('#asf-estado').val(''); $('#asf-tipo').val('');
      this.tabla.draw(); this._cargarStats();
    });

    $('#btn-nueva-asignacion').on('click', () => this._abrirEntrega());
    $('#btn-nueva-solicitud').on('click', () => this._abrirSolicitud());

    $('#tb-asig').on('click', '.btn-devolver', function () {
      const f = Asignaciones.tabla.row($(this).closest('tr')).data();
      if (f) Asignaciones._abrirDevolucion(f);
    });

    $('#tab-sol').on('click', '.btn-aprobar-sol, .btn-rechazar-sol', function () {
      const id = $(this).data('id');
      const codigo = $(this).data('codigo');
      const aprobar = $(this).hasClass('btn-aprobar-sol');

      Swal.fire({
        title: (aprobar ? '¿Aprobar' : '¿Rechazar') + ' la solicitud ' + codigo + '?',
        input: 'textarea',
        inputPlaceholder: aprobar ? 'Observación (opcional)...' : 'Motivo del rechazo (opcional)...',
        showCancelButton: true,
        confirmButtonText: aprobar ? 'Sí, aprobar' : 'Sí, rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: aprobar ? '#16a34a' : '#dc2626'
      }).then(r => {
        if (!r.isConfirmed) return;
        Api.post('api/asignaciones/solicitudes.php', {
          accion: aprobar ? 'aprobar' : 'rechazar',
          id: id,
          observaciones: r.value || ''
        }).then(() => Router.ir('asignaciones/lista'));
      });
    });
  },

  _cargarStats() {
    Api.get('api/asignaciones/stats.php').then(s => {
      $('#asig-stats [data-k]').each(function () { $(this).text(s[$(this).data('k')]); });
      const badge = $('#badge-sol-pend');
      if (badge.length) badge.text((s.solicitudes_pendientes || 0) + (s.solicitudes_aprobadas || 0));
    });
  },

  // ================= ENTREGA / PRESTAMO / CAMBIO (persona o area) =================
  _abrirEntrega() {
    Promise.all([
      Api.get('api/catalogos/select.php', { tipo: 'personal' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'areas' }).catch(() => []),
      Api.get('api/equipos/select.php', { modo: 'disponibles' }).catch(() => [])
    ]).then(([personal, areas, equipos]) => {

      const html = `
      <form id="form-entrega" autocomplete="off">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Operación <span class="text-danger">*</span></label>
            <select class="form-select" id="ae-operacion">
              <option value="entrega">Entrega permanente</option>
              <option value="prestamo">Préstamo temporal</option>
              <option value="cambio">Cambio de equipo</option>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Asignar a <span class="text-danger">*</span></label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="destino" id="dst-persona" value="persona" checked>
              <label class="btn btn-outline-primary" for="dst-persona"><i class="bi bi-person"></i> Persona</label>
              <input type="radio" class="btn-check" name="destino" id="dst-area" value="area">
              <label class="btn btn-outline-primary" for="dst-area"><i class="bi bi-people"></i> Área</label>
            </div>
          </div>
          <div class="col-md-6 mb-3" data-campo="personal_id" id="ae-zona-persona">
            <label class="form-label">Colaborador <span class="text-danger">*</span></label>
            <select class="form-select" id="ae-personal">
              ${personal.map(p => `<option value="${p.id}">${esc(p.texto)} (${esc(p.dni)})</option>`).join('')}
            </select>
          </div>
          <div class="col-md-6 mb-3 d-none" data-campo="area_id" id="ae-zona-area">
            <label class="form-label">Área <span class="text-danger">*</span></label>
            <select class="form-select" id="ae-area">
              ${areas.map(a => `<option value="${a.id}">${esc(a.texto)}</option>`).join('')}
            </select>
            <div class="form-text">Impresoras, switches, módems y equipos de uso común del área.</div>
          </div>

          <div class="col-md-6 mb-3" data-campo="equipo_id">
            <label class="form-label">Equipo a entregar <span class="text-danger">*</span></label>
            <select class="form-select" id="ae-equipo">
              ${equipos.length
                ? equipos.map(e => `<option value="${e.id}">${esc(e.texto)}</option>`).join('')
                : '<option value="">— Sin stock disponible —</option>'}
            </select>
          </div>

          <div class="col-md-3 mb-3 d-none" data-campo="fecha_retorno" id="ae-zona-retorno">
            <label class="form-label">Retorno esperado <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="ae-retorno">
          </div>
          <div class="col-md-3 mb-3 d-none"></div>

          <div class="col-12 d-none" id="ae-zona-cambio">
            <div class="border rounded p-2 mb-3">
              <div class="form-label mb-2"><i class="bi bi-arrow-return-left me-1"></i>Equipo que DEVUELVE
                <span class="text-muted small">(asignado al colaborador)</span></div>
              <div id="ae-equipos-actuales" class="small text-muted">Seleccione el colaborador…</div>
            </div>
            <div class="row mb-2">
              <div class="col-md-5 mb-2" data-campo="motivo">
                <label class="form-label">Motivo del cambio <span class="text-danger">*</span></label>
                <select class="form-select" id="ae-motivo">
                  <option value="renovacion">Renovación tecnológica</option>
                  <option value="falla">Falla técnica</option>
                  <option value="sin_reparacion">Sin reparación</option>
                  <option value="reasignacion">Reasignación interna</option>
                  <option value="ascenso">Ascenso / cambio de puesto</option>
                  <option value="otro">Otro</option>
                </select>
              </div>
              <div class="col-md-7 mb-2">
                <label class="form-label">Validación de TI (marque lo realizado)</label>
                <div class="row small">
                  ${['verificado|Verificación del equipo devuelto','respaldo|Respaldo de información',
                     'borrado|Borrado del equipo anterior','configurado|Configuración del nuevo',
                     'aplicaciones|Aplicaciones corporativas','antivirus|Antivirus','m365|Microsoft 365',
                     'vpn|VPN','impresoras|Impresoras','inventario|Actualización de inventario']
                    .map(x => { const [v, t] = x.split('|');
                      return '<div class="col-md-6"><div class="form-check"><input class="form-check-input ae-val" type="checkbox" value="' + v + '" id="cv-' + v + '">' +
                             '<label class="form-check-label" for="cv-' + v + '">' + t + '</label></div></div>'; }).join('')}
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 mb-3" data-campo="observaciones">
            <label class="form-label">Observaciones del acta</label>
            <textarea class="form-control" id="ae-obs" rows="2" maxlength="1000"></textarea>
          </div>
        </div>

        <div class="alert alert-info py-2 small">
          <i class="bi bi-magic"></i> El sistema registrará la asignación, actualizará el inventario
          y generará el <b>acta LUMAT automáticamente</b> (se abre para imprimir y recoger firmas).
        </div>

        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="ae-guardar"><i class="bi bi-check-lg"></i> Registrar</button>
        </div>
      </form>`;

      Modal.abrir('Registrar entrega de equipo', html, 'modal-xl');

      // ---- toggle destino: persona / area ----
      const pintarDestino = () => {
        const esArea = $('input[name="destino"]:checked').val() === 'area';
        $('#ae-zona-persona').toggleClass('d-none', esArea);
        $('#ae-zona-area').toggleClass('d-none', !esArea);
        if (esArea && $('#ae-operacion').val() === 'cambio') {
          $('#ae-operacion').val('entrega');
          pintarOperacion();
          Toast.info('El cambio de equipo solo aplica a personas.');
        }
      };
      $('input[name="destino"]').on('change', pintarDestino);

      // ---- operacion ----
      const pintarOperacion = () => {
        const op = $('#ae-operacion').val();
        $('#ae-zona-retorno').toggleClass('d-none', op !== 'prestamo');
        $('#ae-zona-cambio').toggleClass('d-none', op !== 'cambio');
        if (op === 'cambio') {
          $('#dst-persona').prop('checked', true);
          pintarDestino();
        }
      };
      $('#ae-operacion').on('change', pintarOperacion);
      pintarOperacion();

      // ---- equipos actuales del colaborador (para cambio) ----
      const cargarActuales = pid => {
        if (!pid) { $('#ae-equipos-actuales').html('<span class="text-muted">—</span>'); return; }
        $('#ae-equipos-actuales').html('<span class="text-muted">Cargando…</span>');
        $.get(BASE_URL + 'api/actas/asignados.php?personal=' + pid, res => {
          if (!res.success) return;
          const eqs = res.data;
          $('#ae-equipos-actuales').html(eqs.length ? eqs.map(e =>
            '<div class="border rounded p-2 mb-2">' +
            '<div class="form-check"><input class="form-check-input ae-viejo" type="radio" name="viejo" value="' + e.id + '" id="v-' + e.id + '">' +
            '<label class="form-check-label" for="v-' + e.id + '"><b>' + esc(e.codigo) + '</b> — ' +
            esc(e.tipo + ' ' + e.marca + ' ' + (e.modelo || '')) + '</label></div>' +
            '<div class="row g-2 mt-1 extra-' + e.id + '" style="display:none">' +
            '<div class="col-5"><select class="form-select form-select-sm cond-dev" data-eq="' + e.id + '">' +
            '<option value="">Condición…</option><option>bueno</option><option>regular</option><option>malo</option></select></div>' +
            '<div class="col-7"><input type="text" class="form-control form-control-sm obs-dev" data-eq="' + e.id + '" maxlength="150" placeholder="Observación"></div>' +
            '</div></div>').join('')
            : '<span class="text-muted">El colaborador no tiene equipos asignados.</span>');

          $('#ae-equipos-actuales').off('change', '.ae-viejo').on('change', '.ae-viejo', function () {
            $('div[class^="extra-"]').hide();
            $('.extra-' + $(this).val()).show();
          });
        });
      };
      $('#ae-personal').on('change', function () { cargarActuales($(this).val()); });
      cargarActuales($('#ae-personal').val());

      $('#ae-guardar').on('click', () => this._guardarEntrega());
    });
  },

  _guardarEntrega() {
    const operacion = $('#ae-operacion').val();
    const destino = $('input[name="destino"]:checked').val() || 'persona';
    const equipoId = $('#ae-equipo').val();

    // ---- validaciones visibles ----
    if (!equipoId) { Toast.warning('Seleccione el equipo a entregar (no hay stock disponible).'); return; }
    if (destino === 'area' && !$('#ae-area').val()) { Toast.warning('Seleccione el área.'); return; }
    if (destino === 'persona' && !$('#ae-personal').val()) { Toast.warning('Seleccione el colaborador.'); return; }

    const datos = {
      operacion:   operacion,
      destino:     destino,
      personal_id: destino === 'persona' ? ($('#ae-personal').val() || '') : '',
      area_id:     destino === 'area' ? ($('#ae-area').val() || '') : '',
      equipo_id:   equipoId,
      fecha_retorno: $('#ae-retorno').val() || '',
      motivo:      $('#ae-motivo').val() || 'renovacion',
      observaciones: $.trim($('#ae-obs').val() || ''),
      validacion_ti: JSON.stringify($('.ae-val:checked').map((i, el) => $(el).val()).get())
    };

    if (operacion === 'cambio') {
      const $viejo = $('.ae-viejo:checked');
      if (!$viejo.length) { Toast.warning('Seleccione el equipo que devuelve el colaborador.'); return; }
      datos.equipo_viejo_id = $viejo.val();
      datos.cond_dev = $('.cond-dev[data-eq="' + $viejo.val() + '"]').val() || 'bueno';
      datos.obs_dev  = $.trim($('.obs-dev[data-eq="' + $viejo.val() + '"]').val() || '');
    }

    Api.post('api/asignaciones/entregar.php', datos)
      .then(d => {
        Modal.cerrar();
        this.tabla.draw();
        this._cargarStats();
        setTimeout(() => window.open(d.acta_url, '_blank'), 400);
      })
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-entrega', err.cuerpo.errors);
      });
  },

  // ================= DEVOLUCION =================
  _abrirDevolucion(f) {
    const esArea = !f.colaborador;
    const receptor = f.colaborador || (f.area_asignada ? 'área ' + f.area_asignada : '—');

    const html = `
    <form id="form-devolucion" data-id="${f.id}">
      <div class="alert alert-warning py-2 small">
        <i class="bi bi-box-arrow-in-left"></i>
        Recibir el equipo <b>${esc(f.equipo_codigo)}</b> (${esc(f.equipo_tipo)} ${esc(f.marca)})
        ${esArea ? 'asignado al <b>' + esc(f.area_asignada || 'área') + '</b>' : 'de <b>' + esc(f.colaborador) + '</b>'}.
        ${+f.vencido === 1 ? '<br><b>PRÉSTAMO VENCIDO</b> desde ' + esc(f.fecha_devolucion_esperada || '') : ''}
      </div>
      <div class="mb-3" data-campo="condicion">
        <label class="form-label">Condición del equipo devuelto <span class="text-danger">*</span></label>
        <div class="row g-2">
          <div class="col-6 col-md"><label class="form-check border rounded p-2 d-block">
            <input class="form-check-input" type="radio" name="cond" value="nuevo"> Nuevo/Excelente</label></div>
          <div class="col-6 col-md"><label class="form-check border rounded p-2 d-block">
            <input class="form-check-input" type="radio" name="cond" value="bueno" checked> Bueno</label></div>
          <div class="col-6 col-md"><label class="form-check border rounded p-2 d-block">
            <input class="form-check-input" type="radio" name="cond" value="regular"> Regular</label></div>
          <div class="col-6 col-md"><label class="form-check border rounded p-2 d-block">
            <input class="form-check-input" type="radio" name="cond" value="danado"> Dañado</label></div>
          <div class="col-6 col-md"><label class="form-check border rounded p-2 d-block">
            <input class="form-check-input" type="radio" name="cond" value="faltante"> Faltante/no entregado</label></div>
        </div>
        <div class="form-text">Dañado/Faltante → el equipo pasa a <b>revisión</b>.</div>
      </div>
      <div class="mb-3" data-campo="observaciones">
        <label class="form-label">Observaciones</label>
        <textarea class="form-control" id="dv-obs" rows="2" maxlength="300"></textarea>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="dv-guardar"><i class="bi bi-box-arrow-in-left"></i> Registrar devolución</button>
      </div>
    </form>`;

    Modal.abrir('Registrar devolución — ' + f.equipo_codigo, html);

    $('#dv-guardar').on('click', () => {
      const cond = $('input[name="cond"]:checked').val();
      if (!cond) { Toast.warning('Seleccione la condición del equipo.'); return; }

      Api.post('api/asignaciones/devolver.php', {
        asignacion_id: f.id,
        condicion: cond,
        observaciones: $.trim($('#dv-obs').val() || '')
      }).then(() => {
        Modal.cerrar();
        Asignaciones.tabla.draw();
        Asignaciones._cargarStats();
      }).catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-devolucion', err.cuerpo.errors);
      });
    });
  },

  // ================= NUEVA SOLICITUD =================
  _abrirSolicitud() {
    Promise.all([
      Api.get('api/catalogos/select.php', { tipo: 'personal' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'tipos' }).catch(() => [])
    ]).then(([personal, tipos]) => {

      const html = `
      <form id="form-solicitud">
        <div class="row">
          <div class="col-md-5 mb-3" data-campo="solicitante_id">
            <label class="form-label">Solicitante <span class="text-danger">*</span></label>
            <select class="form-select" id="sl-personal">
              ${personal.map(p => `<option value="${p.id}">${esc(p.texto)} (${esc(p.dni)})</option>`).join('')}
            </select>
          </div>
          <div class="col-md-4 mb-3" data-campo="tipo_equipo_id">
            <label class="form-label">Tipo de equipo <span class="text-danger">*</span></label>
            <select class="form-select" id="sl-tipo">
              ${tipos.map(t => `<option value="${t.id}">${esc(t.texto)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-3 mb-3" data-campo="cantidad">
            <label class="form-label">Cantidad</label>
            <input type="number" class="form-control" id="sl-cant" min="1" max="10" value="1">
          </div>
          <div class="col-12 mb-3" data-campo="justificacion">
            <label class="form-label">Justificación <span class="text-danger">*</span></label>
            <textarea class="form-control" id="sl-just" rows="3" maxlength="300"
                      placeholder="Ej: Nuevo ingreso al área, no cuenta con equipo de cómputo."></textarea>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="sl-guardar"><i class="bi bi-inbox"></i> Enviar solicitud</button>
        </div>
      </form>`;

      Modal.abrir('Solicitar equipo', html, 'modal-lg');

      $('#sl-guardar').on('click', () => {
        if ($.trim($('#sl-just').val() || '').length < 10) {
          Toast.warning('La justificación necesita al menos 10 caracteres.'); return;
        }
        Api.post('api/asignaciones/solicitudes.php', {
          accion: 'crear',
          solicitante_id: $('#sl-personal').val() || '',
          tipo_equipo_id: $('#sl-tipo').val() || '',
          cantidad: $('#sl-cant').val() || 1,
          justificacion: $.trim($('#sl-just').val() || '')
        }).then(() => { Modal.cerrar(); Router.ir('asignaciones/lista'); })
          .catch(err => {
            if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-solicitud', err.cuerpo.errors);
          });
      });
    });
  }
};

App.registrar('asignaciones', Asignaciones);