// ============================================================
// SIGTI - Modulo Tickets (Mesa de Ayuda + SLA en vivo)
// Reusa: esc(), pintarErroresForm(), DT, Modal, Api, Toast
// ============================================================

const ESTADOS_TICKET = {
  abierto:             { txt: 'Abierto',          cls: 'text-bg-secondary' },
  asignado:            { txt: 'Asignado',         cls: 'text-bg-info' },
  en_atencion:         { txt: 'En atención',      cls: 'text-bg-primary' },
  pendiente_usuario:   { txt: 'Pend. usuario',    cls: 'text-bg-warning text-dark' },
  pendiente_proveedor: { txt: 'Pend. proveedor',  cls: 'text-bg-warning text-dark' },
  resuelto:            { txt: 'Resuelto',         cls: 'text-bg-success' },
  cerrado:             { txt: 'Cerrado',          cls: 'text-bg-dark' },
  cancelado:           { txt: 'Cancelado',        cls: 'text-bg-danger' }
};

const IMPACTOS = { individual: 'Individual', area: 'Área completa', empresa: 'Empresa' };

const Tickets = {

  tabla: null,
  _timerSla: null,

  init() {
    this.tabla = DT.server('#tb-tickets', 'api/tickets/listar.php',
      () => ({
        f_estado:    $('#tf-estado').val()    || '',
        f_prioridad: $('#tf-prioridad').val() || 0,
        f_categoria: $('#tf-categoria').val() || 0,
        f_tecnico:   $('#tf-tecnico').val()   ?? -1,
        f_mios:      $('#tf-mios').is(':checked') ? 1 : 0
      }),
      [
        { data: 'codigo', render: v => '<strong class="nowrap">' + esc(v) + '</strong>' },
        { data: 'titulo', render: v => '<span class="small">' + esc(v) + '</span>' },
        { data: 'categoria', render: v => '<span class="badge text-bg-light border">' + esc(v) + '</span>' },
        { data: 'prioridad', render: (v, t, f) =>
            '<span class="badge badge-estado-personal" style="background:' + esc(f.prioridad_color) + ';color:#fff">' + esc(v) + '</span>' },
        { data: 'solicitante', render: v => '<span class="small">' + esc(v) + '</span>' },
        { data: 'area', render: v => '<span class="small">' + esc(v) + '</span>' },
        { data: 'tecnico', render: v => v ? '<span class="small">' + esc(v) + '</span>' : '<span class="text-muted small">sin asignar</span>' },
        { data: 'estado', render: v => {
            const e = ESTADOS_TICKET[v] || { txt: v, cls: 'text-bg-light' };
            return '<span class="badge ' + e.cls + ' ticket-estado nowrap">' + e.txt + '</span>';
          } },
        { data: 'fecha_creacion', className: 'nowrap', render: v => '<span class="small text-muted">' + esc(v) + '</span>' },
        { data: 'sla_limite',
          render: (v, t, f) => this._slaHtml(v, f.estado, f.sla_vencido),
          className: 'nowrap sla-cell' },
        { data: null, orderable: false, className: 'text-nowrap text-center',
          render: () => '<button class="btn btn-sm btn-outline-primary btn-ver" title="Ver ticket"><i class="bi bi-eye"></i></button>' }
      ]);

    // timers SLA con color al terminar cada draw
    this.tabla.on('draw', () => this._refrescarTimers());
    this._refrescarTimers();
    this._cargarStats();

    $('#btn-filtrar-tickets').on('click', () => { this.tabla.draw(); this._cargarStats(); });
    $('#tf-estado, #tf-prioridad, #tf-categoria, #tf-tecnico').on('change', () => { this.tabla.draw(); this._cargarStats(); });
    $('#tf-mios').on('change', () => { this.tabla.draw(); this._cargarStats(); });
    $('#btn-limpiar-tickets').on('click', () => {
      $('#tf-estado').val(''); $('#tf-prioridad').val(''); $('#tf-categoria').val('');
      $('#tf-tecnico').val('-1'); $('#tf-mios').prop('checked', false);
      this.tabla.draw(); this._cargarStats();
    });

    $('#btn-nuevo-ticket').on('click', () => this._abrirForm(null));

    $('#tb-tickets').on('click', '.btn-ver', function () {
      const fila = Tickets.tabla.row($(this).closest('tr')).data();
      if (fila) Tickets._verDetalle(fila.id);
    });

    clearInterval(this._timerSla);
    this._timerSla = setInterval(() => this._refrescarTimers(), 60000);
  },

  // ---------------- SLA en vivo ----------------
  _slaHtml(limite, estado) {
    if (!limite || ['resuelto','cerrado','cancelado'].includes(estado)) {
      return '<span class="text-muted small">—</span>';
    }
    return '<span class="sla-timer" data-limite="' + esc(limite) + '">' +
           this._slaTexto(limite) + '</span>';
  },

  _slaTexto(limite) {
    const restante = (new Date(limite) - Date.now()) / 1000;
    if (restante <= 0) return 'VENCIDO';
    const h = Math.floor(restante / 3600);
    const m = Math.floor((restante % 3600) / 60);
    return (h > 0 ? h + 'h ' : '') + m + 'm';
  },

  _refrescarTimers() {
    $('#tb-tickets .sla-timer').each((i, el) => {
      const $el = $(el);
      $el.text(this._slaTexto($el.data('limite')));
      const restante = (new Date($el.data('limite')) - Date.now()) / 1000;
      $el.removeClass('sla-ok sla-warn sla-vencido');
      if (restante <= 0)        $el.addClass('sla-vencido');
      else if (restante < 7200) $el.addClass('sla-warn');
      else                      $el.addClass('sla-ok');
    });
  },

  _cargarStats() {
    Api.get('api/tickets/stats.php').then(s => {
      $('#tickets-stats [data-k]').each(function () { $(this).text(s[$(this).data('k')]); });
    });
  },

  // ================= FORMULARIO =================
  _abrirForm(fila) {
    const esNuevo = !fila;
    const promesaDet = esNuevo
      ? Promise.resolve(null)
      : Api.get('api/tickets/detalle.php', { id: fila.id }).catch(() => null);

    Promise.all([
      Api.get('api/catalogos/select.php', { tipo: 'personal' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'areas' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'categorias' }).catch(() => []),
      promesaDet
    ]).then(([personal, areas, categorias, det]) => {

      const val = c => esNuevo ? '' : ((det || fila)[c] ?? '');

      const html = `
      <form id="form-ticket" autocomplete="off" data-id="${esNuevo ? 0 : fila.id}">
        <div class="row">
          <div class="col-md-6 mb-3" data-campo="titulo">
            <label class="form-label">Título <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="ft-titulo" value="${esc(val('titulo'))}" maxlength="150"
                   placeholder="Ej: No imprime desde la laptop de Ana">
          </div>
          <div class="col-md-3 mb-3" data-campo="solicitante_id">
            <label class="form-label">Solicitante <span class="text-danger">*</span></label>
            <select class="form-select" id="ft-solicitante">
              ${personal.map(p => `<option value="${p.id}" ${+val('solicitante_personal_id') === +p.id ? 'selected' : ''}>${esc(p.texto)} (${esc(p.dni)})</option>`).join('')}
            </select>
          </div>
          <div class="col-md-3 mb-3" data-campo="area_id">
            <label class="form-label">Área afectada <span class="text-danger">*</span></label>
            <select class="form-select" id="ft-area">
              ${areas.map(a => `<option value="${a.id}" ${+val('area_afectada_id') === +a.id ? 'selected' : ''}>${esc(a.texto)}</option>`).join('')}
            </select>
          </div>

          <div class="col-md-3 mb-3" data-campo="categoria_id">
            <label class="form-label">Categoría <span class="text-danger">*</span></label>
            <select class="form-select" id="ft-categoria">
              ${categorias.map(c => `<option value="${c.id}" ${+val('categoria_id') === +c.id ? 'selected' : ''}>${esc(c.texto)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-3 mb-3" data-campo="subcategoria_id">
            <label class="form-label">Subcategoría</label>
            <select class="form-select" id="ft-subcategoria">
              <option value="">— Seleccione categoría primero —</option>
            </select>
          </div>
          <div class="col-md-3 mb-3" data-campo="prioridad_id">
            <label class="form-label">Prioridad <span class="text-danger">*</span></label>
            <select class="form-select" id="ft-prioridad"></select>
            <div class="form-text">Define el SLA (tiempo máximo de resolución).</div>
          </div>
          <div class="col-md-3 mb-3" data-campo="impacto">
            <label class="form-label">Impacto <span class="text-danger">*</span></label>
            <select class="form-select" id="ft-impacto">
              ${Object.entries(IMPACTOS).map(([k, t]) =>
                `<option value="${k}" ${val('impacto') === k || (esNuevo && k === 'individual') ? 'selected' : ''}>${t}</option>`).join('')}
            </select>
          </div>

          <div class="col-md-6 mb-3" data-campo="equipo_id">
            <label class="form-label">Equipo relacionado (opcional)</label>
            <select class="form-select" id="ft-equipo">
              <option value="">— Ninguno —</option>
            </select>
          </div>

          <div class="col-12 mb-3" data-campo="descripcion">
            <label class="form-label">Descripción del problema <span class="text-danger">*</span></label>
            <textarea class="form-control" id="ft-descripcion" rows="4" maxlength="5000"
                      placeholder="Detalle del incidente: qué pasó, desde cuándo, mensajes de error...">${esc(val('descripcion'))}</textarea>
          </div>

          ${esNuevo ? `
          <div class="col-12 mb-3" data-campo="adjuntos">
            <label class="form-label"><i class="bi bi-paperclip me-1"></i>Adjuntos (capturas, archivos)</label>
            <input type="file" class="form-control" id="ft-adjuntos" name="adjuntos[]" multiple>
            <div class="form-text">Formatos y tamaño según configuración del sistema.</div>
          </div>` : ''}
        </div>

        ${esNuevo ? '<div class="alert alert-info py-2 small"><i class="bi bi-clock-history"></i> El código <b>TK-2025-#####</b> y el <b>SLA límite</b> se calculan automáticamente según la prioridad elegida.</div>' : ''}

        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-guardar-ticket"><i class="bi bi-check-lg"></i> ${esNuevo ? 'Crear ticket' : 'Guardar cambios'}</button>
        </div>
      </form>`;

      Modal.abrir(esNuevo ? 'Nuevo ticket de soporte' : 'Editar — ' + fila.codigo, html, 'modal-xl');

      // ---- prioridades ----
      $.get(BASE_URL + 'api/catalogos/select.php?tipo=prioridades', res => {
        if (res.success) {
          $('#ft-prioridad').html(res.data.map(p =>
            '<option value="' + p.id + '"' +
            (+val('prioridad_id') === +p.id ? ' selected' : '') + '>' +
            esc(p.texto) + '</option>').join(''));
        }
      }).fail(() => Toast.error('No se pudieron cargar las prioridades.'));

      // ---- subcategorías dinámicas por categoría ----
      const cargarSubs = catId => {
        if (!catId) { $('#ft-subcategoria').html('<option value="">— Seleccione categoría —</option>'); return; }
        $('#ft-subcategoria').html('<option value="">— Cargando... —</option>');
        $.get(BASE_URL + 'api/tickets/subcategorias.php?categoria=' + catId, res => {
          if (res.success) {
            let h = '<option value="">— Sin subcategoría —</option>';
            res.data.forEach(s => {
              h += '<option value="' + s.id + '"' +
                   (+val('subcategoria_id') === +s.id ? ' selected' : '') + '>' +
                   esc(s.texto) + '</option>';
            });
            $('#ft-subcategoria').html(h);
          }
        }).fail(() => $('#ft-subcategoria').html('<option value="">— Error al cargar —</option>'));
      };
      $('#ft-categoria').on('change', function () { cargarSubs($(this).val()); });
      cargarSubs($('#ft-categoria').val());

      // ---- equipos activos ----
      Api.get('api/equipos/select.php', { modo: 'activos' }).then(eqs => {
        $('#ft-equipo').html('<option value="">— Ninguno —</option>' +
          eqs.map(e => '<option value="' + e.id + '"' +
            (+val('equipo_id') === +e.id ? ' selected' : '') + '>' +
            esc(e.texto) + '</option>').join(''));
      }).catch(() => {});

      // ---- guardar ----
      $('#btn-guardar-ticket').on('click', () => this._guardar());
      $('#form-ticket').on('submit', e => { e.preventDefault(); this._guardar(); });
    });
  },

  _guardar() {
    const esNuevo = +($('#form-ticket').data('id') || 0) === 0;
    const hayArchivos = esNuevo && $('#ft-adjuntos')[0] && $('#ft-adjuntos')[0].files.length > 0;

    if ($.trim($('#ft-titulo').val() || '').length < 5) { Toast.warning('El título necesita al menos 5 caracteres.'); return; }
    if ($.trim($('#ft-descripcion').val() || '').length < 10) { Toast.warning('La descripción necesita al menos 10 caracteres.'); return; }

    const data = {
      id:              $('#form-ticket').data('id') || 0,
      titulo:          $.trim($('#ft-titulo').val() || ''),
      descripcion:     $.trim($('#ft-descripcion').val() || ''),
      solicitante_id:  $('#ft-solicitante').val() || '',
      area_id:         $('#ft-area').val() || '',
      categoria_id:    $('#ft-categoria').val() || '',
      subcategoria_id: $('#ft-subcategoria').val() || '',
      prioridad_id:    $('#ft-prioridad').val() || '',
      impacto:         $('#ft-impacto').val() || '',
      equipo_id:       $('#ft-equipo').val() || ''
    };

    const exito = () => {
      Modal.cerrar();
      this.tabla.draw();
      this._cargarStats();
    };
    const fallo = err => {
      if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-ticket', err.cuerpo.errors);
    };

    if (hayArchivos) {
      const fd = new FormData();
      Object.entries(data).forEach(([k, v]) => fd.append(k, v));
      $.each($('#ft-adjuntos')[0].files, (i, file) => fd.append('adjuntos[]', file));

      $.ajax({
        url: BASE_URL + 'api/tickets/guardar.php',
        type: 'POST', data: fd, contentType: false, processData: false,
        headers: { 'X-CSRF-Token': CSRF.token },
        beforeSend: () => Loader.show(), complete: () => Loader.hide(),
        success: res => {
          if (res.success) { Toast.success(res.message); exito(); }
          else { Toast.error(res.message || 'Error'); fallo({ cuerpo: res }); }
        },
        error: () => Toast.error('Error de conexión al crear el ticket.')
      });
    } else {
      Api.post('api/tickets/guardar.php', data).then(exito).catch(fallo);
    }
  },

  // ================= DETALLE (hilo) =================
  _verDetalle(id) {
    Api.get('api/tickets/detalle.php', { id: id }).then(t => {

      const est = ESTADOS_TICKET[t.estado] || { txt: t.estado, cls: 'text-bg-light' };
      const puedeSoporte = App.permisos.includes('tickets.ver') || App.usuario.rol === 'super_admin';
      const puedeCambiar = puedeSoporte && App.permisos.includes('tickets.cambiar_estado');
      const puedeAsignar = App.permisos.includes('tickets.asignar');
      const abierto = !['cerrado','cancelado'].includes(t.estado);

      let slaHtml = '<span class="text-muted">—</span>';
      if (t.sla_limite && !['resuelto','cerrado','cancelado'].includes(t.estado)) {
        const restante = (new Date(t.sla_limite) - Date.now()) / 1000;
        const cls = restante <= 0 ? 'sla-vencido' : (restante < 7200 ? 'sla-warn' : 'sla-ok');
        slaHtml = '<span class="sla-timer ' + cls + '">' + this._slaTexto(t.sla_limite) + '</span>';
      }

      const html = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <h5 class="mb-1">${esc(t.codigo)} — ${esc(t.titulo)}</h5>
          <div class="text-muted small">
            ${esc(t.solicitante)} · ${esc(t.area)} · creado ${esc(t.fecha_creacion)}
            ${t.reaperturas > 0 ? ' · <span class="badge text-bg-warning text-dark">reabierto ' + t.reaperturas + 'x</span>' : ''}
          </div>
        </div>
        <div class="text-end">
          <span class="badge ${est.cls} ticket-estado">${est.txt}</span><br>
          <span class="badge mt-1" style="background:${esc(t.prioridad_color)};color:#fff">${esc(t.prioridad)}</span>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Categoría</div><strong class="small">${esc(t.categoria)}${t.subcategoria ? ' / ' + esc(t.subcategoria) : ''}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Impacto</div><strong class="small">${esc(IMPACTOS[t.impacto] || t.impacto)}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Técnico</div><strong class="small">${t.tecnico ? esc(t.tecnico) : 'sin asignar'}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">SLA restante</div>${slaHtml}</div></div>
      </div>

      <div class="border rounded p-3 mb-3 bg-light">
        <div class="text-muted small mb-1"><b>Descripción</b></div>
        <div style="white-space:pre-wrap;font-size:14px">${esc(t.descripcion)}</div>
      </div>

      ${t.solucion ? `
      <div class="border rounded p-3 mb-3" style="background:#f0fdf4">
        <div class="small mb-1"><b>Solución aplicada</b> <span class="text-muted">(${esc(t.fecha_resolucion || '')})</span></div>
        <div style="white-space:pre-wrap;font-size:14px">${esc(t.solucion)}</div>
      </div>` : ''}
      ${t.motivo_cancelacion ? `
      <div class="alert alert-danger py-2 small mb-3"><b>Motivo de cancelación:</b> ${esc(t.motivo_cancelacion)}</div>` : ''}

      ${t.adjuntos.length ? `
      <div class="mb-3 adj-lista">
        <b class="small"><i class="bi bi-paperclip me-1"></i>Adjuntos (${t.adjuntos.length}):</b><br>
        ${t.adjuntos.map(a => '<a href="' + BASE_URL + 'api/tickets/descargar.php?id=' + a.id +
          '" target="_blank" class="badge text-bg-light border me-1 mt-1 text-decoration-none">' +
          esc(a.nombre_original) + ' (' + a.tamano_kb + ' KB)</a>').join('')}
      </div>` : ''}

      ${abierto ? `
      <div class="row g-2 mb-3">
        ${puedeAsignar ? `
        <div class="col-md-4">
          <button class="btn btn-outline-primary btn-sm w-100" id="tk-btn-asignar">
            <i class="bi bi-person-check"></i> ${t.tecnico ? 'Reasignar técnico' : 'Asignar técnico'}
          </button>
        </div>` : ''}
        ${puedeCambiar ? `
        <div class="col-md-8 d-flex gap-1 flex-wrap">
          ${this._botonesEstado(t)}
        </div>` : ''}
      </div>` : (puedeCambiar && t.estado === 'cerrado' ? `
      <div class="mb-3"><button class="btn btn-outline-warning btn-sm" id="tk-btn-reabrir"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button></div>` : '')}

      <div class="mb-2"><b><i class="bi bi-chat-dots me-1"></i>Seguimiento (${t.comentarios.length})</b></div>
      <div id="tk-hilo" style="max-height:300px;overflow-y:auto" class="mb-3">
        ${t.comentarios.map(c => this._htmlComentario(c)).join('') || '<div class="text-muted small">Sin comentarios aún.</div>'}
      </div>

      ${abierto ? `
      <div class="border rounded p-2">
        <textarea class="form-control form-control-sm mb-2" id="tk-nuevo-comentario" rows="2"
                  maxlength="3000" placeholder="Escriba el seguimiento o respuesta..."></textarea>
        <div class="d-flex justify-content-between align-items-center">
          ${puedeSoporte ? `
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="tk-interno">
            <label class="form-check-label small" for="tk-interno">
              <i class="bi bi-eye-slash"></i> Nota interna (solo soporte)
            </label>
          </div>` : '<span></span>'}
          <button class="btn btn-primary btn-sm" id="tk-btn-comentar"><i class="bi bi-send"></i> Comentar</button>
        </div>
      </div>` : ''}
      `;

      Modal.abrir('Ticket ' + t.codigo, html, 'modal-xl');
      setTimeout(() => { const h = $('#tk-hilo'); h.scrollTop(h[0].scrollHeight); }, 100);

      $('#tk-btn-comentar').on('click', () => this._comentar(t.id));
      $('#tk-interno').on('change', function () {
        $(this).closest('.border').toggleClass('bg-warning-subtle', $(this).is(':checked'));
      });

      if (puedeAsignar) $('#tk-btn-asignar').on('click', () => this._asignar(t));
      $('#modal-general-cuerpo').off('click', '.tk-btn-estado')
        .on('click', '.tk-btn-estado', function () {
          Tickets._cambiarEstado(t, $(this).data('estado'), $(this).data('texto'));
        });
      if (t.estado === 'cerrado' && puedeCambiar) {
        $('#tk-btn-reabrir').on('click', () => this._cambiarEstado(t, 'en_atencion', 'Reabrir'));
      }
    });
  },

  _botonesEstado(t) {
    const PERMITIDOS = {
      abierto:             [['asignado','Asignar'],['cancelado','Cancelar']],
      asignado:            [['en_atencion','Atender'],['pendiente_proveedor','Espera proveedor'],['cancelado','Cancelar']],
      en_atencion:         [['pendiente_usuario','Espera usuario'],['pendiente_proveedor','Espera proveedor'],['resuelto','Resolver']],
      pendiente_usuario:   [['en_atencion','Reactivar'],['resuelto','Resolver'],['cancelado','Cancelar']],
      pendiente_proveedor: [['en_atencion','Reactivar'],['resuelto','Resolver'],['cancelado','Cancelar']],
      resuelto:            [['cerrado','Cerrar'],['en_atencion','Reabrir']],
      cerrado:             [],
      cancelado:           []
    };
    return (PERMITIDOS[t.estado] || [])
      .map(([estado, texto]) =>
        '<button class="btn btn-sm btn-outline-' +
        (estado === 'resuelto' ? 'success' : estado === 'cerrado' ? 'dark' :
         estado === 'cancelado' ? 'danger' : estado.startsWith('pendiente') ? 'warning text-dark' : 'primary') +
        ' tk-btn-estado" data-estado="' + estado + '" data-texto="' + texto + '">' + texto + '</button>')
      .join('');
  },

  _htmlComentario(c) {
    const cls = +c.interno === 1 ? 'c-interno' : 'c-usuario';
    const icono = +c.interno === 1 ? ' <span class="badge text-bg-warning text-dark">interno</span>' : '';
    return `<div class="comentario ${cls}">
      <div class="d-flex justify-content-between">
        <span class="c-autor">${esc(c.autor || 'Sistema')}${icono}</span>
        <span class="c-fecha">${esc(c.created_at)}</span>
      </div>
      <div class="c-mensaje">${esc(c.mensaje)}</div>
    </div>`;
  },

  _comentar(id) {
    const msg = $.trim($('#tk-nuevo-comentario').val() || '');
    if (msg.length < 2) { Toast.warning('Escriba el comentario primero.'); return; }
    Api.post('api/tickets/comentario.php', {
      id: id, mensaje: msg,
      interno: $('#tk-interno').is(':checked') ? 1 : 0
    }).then(() => this._verDetalle(id));
  },

  _asignar(t) {
    $.get(BASE_URL + 'api/catalogos/select.php?tipo=tecnicos', res => {
      if (!res.success) { Toast.error('No se pudo cargar los técnicos.'); return; }
      const tecnicos = res.data;

      const html = `
      <div class="mb-3" data-campo="tecnico_id">
        <label class="form-label">Técnico <span class="text-danger">*</span></label>
        <select class="form-select" id="ta-tecnico">
          ${tecnicos.map(u => `<option value="${u.id}">${esc(u.texto)}</option>`).join('')}
        </select>
      </div>
      <div class="mb-3" data-campo="nivel">
        <label class="form-label">Nivel de atención</label>
        <select class="form-select" id="ta-nivel">
          <option value="1" ${t.nivel_atencion == 1 ? 'selected' : ''}>N1 — Mesa de ayuda</option>
          <option value="2" ${t.nivel_atencion == 2 ? 'selected' : ''}>N2 — Soporte técnico</option>
          <option value="3" ${t.nivel_atencion == 3 ? 'selected' : ''}>N3 — Especialista</option>
        </select>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="ta-confirmar">Asignar</button>
      </div>`;

      Modal.abrir('Asignar — ' + t.codigo, html, 'modal-sm');

      $('#ta-confirmar').on('click', () => {
        Api.post('api/tickets/asignar.php', {
          id: t.id, tecnico_id: $('#ta-tecnico').val(), nivel: $('#ta-nivel').val()
        }).then(() => { this._verDetalle(t.id); this.tabla.draw(); this._cargarStats(); });
      });
    }).fail(() => Toast.error('Error al cargar técnicos.'));
  },

  _cambiarEstado(t, nuevo, textoBoton) {
    const esResolver  = nuevo === 'resuelto';
    const esCancelar  = nuevo === 'cancelado';
    const requiereMotivo = ['cancelado','pendiente_usuario','pendiente_proveedor'].includes(nuevo) ||
                           (nuevo === 'en_atencion' && ['resuelto','cerrado'].includes(t.estado));

    const html = `
    <form id="tk-form-estado">
      <div class="alert ${esCancelar ? 'alert-danger' : esResolver ? 'alert-success' : 'alert-primary'} py-2 small">
        ${esResolver ? 'Describa la solución aplicada para resolver el ticket.' : ''}
        ${esCancelar ? 'El ticket quedará anulado. Se requiere el motivo.' : ''}
        ${requiereMotivo && !esResolver && !esCancelar ? 'Indique el motivo de este cambio.' : ''}
        ${!requiereMotivo ? 'Confirmar el cambio de estado.' : ''}
      </div>
      ${esResolver ? `
      <div class="mb-3" data-campo="solucion">
        <label class="form-label">Solución aplicada <span class="text-danger">*</span></label>
        <textarea class="form-control" id="te-solucion" rows="3" maxlength="3000"
                  placeholder="Ej: Se reinstaló el driver y se limpió la cola de impresión."></textarea>
      </div>` : ''}
      ${requiereMotivo ? `
      <div class="mb-3" data-campo="motivo">
        <label class="form-label">Motivo <span class="text-danger">*</span></label>
        <textarea class="form-control" id="te-motivo" rows="2" maxlength="250"
                  placeholder="${esCancelar ? 'Ej: El usuario cerró por duplicado...' : 'Ej: Se espera la respuesta del usuario...'}"></textarea>
      </div>` : ''}
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn ${esCancelar ? 'btn-danger' : 'btn-primary'}" id="te-confirmar">
          ${esc(textoBoton || 'Confirmar')}
        </button>
      </div>
    </form>`;

    Modal.abrir('Cambiar estado — ' + t.codigo, html, 'modal-md');

    $('#te-confirmar').on('click', () => {
      Api.post('api/tickets/estado.php', {
        id: t.id, estado: nuevo,
        solucion: $.trim($('#te-solucion').val() || ''),
        motivo: $.trim($('#te-motivo').val() || '')
      }).then(() => {
        Modal.cerrar();
        this.tabla.draw();
        this._cargarStats();
        setTimeout(() => this._verDetalle(t.id), 250);
      }).catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#tk-form-estado', err.cuerpo.errors);
      });
    });
  }
};

App.registrar('tickets', Tickets);