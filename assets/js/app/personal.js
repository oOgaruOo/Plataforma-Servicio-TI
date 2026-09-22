// ============================================================
// SIGTI - Modulo Personal (vista: personal/lista)
// Reusa: esc(), Badges, pintarErroresForm(), DT, Modal, Api
// ============================================================

const ESTADOS_PERSONAL = {
  pre_ingreso:     { txt: 'Pre-ingreso',    cls: 'text-bg-secondary' },
  activo:          { txt: 'Activo',         cls: 'text-bg-success' },
  cese_programado: { txt: 'Cese programado',cls: 'text-bg-warning' },
  en_proceso_cese: { txt: 'En proc. cese',  cls: 'text-bg-warning text-dark' },
  cesado:          { txt: 'Cesado',         cls: 'text-bg-dark' }
};

const TIPOS_PERSONAL = {
  empleado: 'Empleado', contratista: 'Contratista',
  practicante: 'Practicante', tercero: 'Tercero'
};

const Personal = {

  tabla: null,

  init() {
    this.tabla = DT.server('#tb-personal', 'api/personal/listar.php',
      () => ({
        f_estado: $('#pf-estado').val() || '',
        f_area:   $('#pf-area').val()   || 0,
        f_campo:  $('#pf-campo').val()  || 'nombres',
        f_texto:  $.trim($('#pf-texto').val() || '')
      }),
      [
        { data: 'dni' },
        { data: 'apellidos' },
        { data: 'nombres' },
        { data: 'area', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'cargo' },
        { data: 'fecha_ingreso', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'tipo_personal', render: v => '<span class="badge text-bg-light border">' + esc(TIPOS_PERSONAL[v] || v) + '</span>' },
        { data: 'estado', render: v => {
            const e = ESTADOS_PERSONAL[v] || { txt: v, cls: 'text-bg-light' };
            return '<span class="badge ' + e.cls + ' badge-estado-personal">' + e.txt + '</span>';
          } },
        { data: null, className: 'text-center', orderable: false,
          render: (v, t, fila) => {
            if (!fila.check_total) return '<span class="text-muted">—</span>';
            const pct = Math.round(100 * fila.check_ok / fila.check_total);
            const color = pct === 100 ? 'bg-success' : 'bg-primary';
            return '<div class="d-flex align-items-center gap-2" style="min-width:130px">' +
                   '<div class="progress flex-fill"><div class="progress-bar ' + color +
                   '" style="width:' + pct + '%"></div></div>' +
                   '<small class="text-muted nowrap">' + fila.check_ok + '/' + fila.check_total + '</small></div>';
          } },
        { data: null, orderable: false, className: 'text-nowrap text-center',
          render: (v, t, fila) => {
            const cesado = fila.estado === 'cesado';
            let h =
              '<button class="btn btn-sm btn-outline-primary btn-ver me-1" title="Ficha y checklist"><i class="bi bi-eye"></i></button>';
            if (!cesado && App.permisos.includes('personal.editar')) {
              h += '<button class="btn btn-sm btn-outline-secondary btn-editar me-1" title="Editar"><i class="bi bi-pencil"></i></button>';
            }
            if (fila.estado === 'pre_ingreso' && App.permisos.includes('personal.eliminar')) {
              h += '<button class="btn btn-sm btn-outline-danger btn-eliminar" title="Eliminar (solo pre-ingreso)"><i class="bi bi-trash"></i></button>';
            }
            return h;
          } }
      ]);

    // ---- Filtros ----
    $('#btn-filtrar-personal').on('click', () => this.tabla.draw());
    $('#pf-texto').on('keyup', e => { if (e.key === 'Enter') this.tabla.draw(); });
    $('#pf-estado, #pf-area, #pf-campo').on('change', () => this.tabla.draw());
    $('#btn-limpiar-personal').on('click', () => {
      $('#pf-estado').val(''); $('#pf-area').val(''); $('#pf-campo').val('nombres'); $('#pf-texto').val('');
      this.tabla.draw();
    });

    // ---- Nuevo registro ----
    $('#btn-nuevo-personal').on('click', () => this._abrirForm(null));

    // ---- Acciones de fila ----
    $('#tb-personal').on('click', '.btn-ver, .btn-editar, .btn-eliminar', function () {
      const fila = Personal.tabla.row($(this).closest('tr')).data();
      if (!fila) return;
      if      ($(this).hasClass('btn-ver'))      Personal._verDetalle(fila.id);
      else if ($(this).hasClass('btn-editar'))   Personal._abrirForm(fila);
      else                                        Personal._eliminar(fila);
    });
  },

  // ================= FICHA + CHECKLIST =================
  _verDetalle(id) {
    Api.get('api/personal/detalle.php', { id: id }).then(p => {

      const e = ESTADOS_PERSONAL[p.estado] || { txt: p.estado, cls: 'text-bg-light' };
      const pct = p.check_total ? Math.round(100 * p.check_ok / p.check_total) : 0;

      let html = `
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <h5 class="mb-1">${esc(p.nombres)} ${esc(p.apellidos)}</h5>
          <div class="text-muted small">DNI ${esc(p.dni)} · ${esc(p.cargo || '')} · ${esc(p.area || 'sin área')}</div>
        </div>
        <span class="badge ${e.cls} badge-estado-personal">${e.txt}</span>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
          <div class="text-muted small">Ingreso</div><strong>${esc(p.fecha_ingreso || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
          <div class="text-muted small">Cese</div><strong>${esc(p.fecha_cese || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
          <div class="text-muted small">Tipo</div><strong>${esc(TIPOS_PERSONAL[p.tipo_personal] || '')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
          <div class="text-muted small">Jefe</div><strong>${esc(p.jefe || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
          <div class="text-muted small">Correo corp.</div>
          <strong class="small">${esc(p.correo_corporativo || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
          <div class="text-muted small">Teléfono</div><strong>${esc(p.telefono || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
          <div class="text-muted small">Cuentas sistema</div><strong>${esc(p.cuentas_sistema || '0')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
          <div class="text-muted small">Observaciones</div>
          <strong class="small">${esc(p.observaciones || '—')}</strong></div></div>
      </div>

      ${p.check_total ? `
      <div class="d-flex align-items-center gap-2 mb-2">
        <strong><i class="bi bi-list-check me-1"></i>Checklist de ingreso (onboarding)</strong>
        <div class="progress flex-fill"><div class="progress-bar" style="width:${pct}%"></div></div>
        <span class="badge text-bg-primary">${p.check_ok}/${p.check_total}</span>
      </div>
      <div id="lista-checklist">` +
        p.checklist.map(c => this._itemHtml(c)).join('') +
      `</div>` : `
      <div class="alert alert-secondary py-2 small mb-0">
        Esta persona no tiene checklist de ingreso asignado (fue registrada antes de la implementación del módulo).
      </div>`}
      `;

      Modal.abrir('Ficha de personal', html, 'modal-lg');

      if (p.check_total) this._bindearChecklist(p.estado);
    });
  },

  _itemHtml(c) {
    const ok      = +c.completado === 1;
    const editable = App.permisos.includes('personal.editar');
    const disabled = (!editable || ok === false) ? '' : '';
    return `
    <div class="item-checklist ${ok ? 'completado' : ''} mb-2" data-id="${c.id}">
      <input type="checkbox" class="item-check form-check-input" ${ok ? 'checked' : ''} ${editable ? '' : 'disabled'}>
      <div class="flex-fill">
        <div class="item-texto">${esc(c.item)}
          ${+c.obligatorio === 1 ? '<span class="text-danger">*</span>' : ''}
          <span class="badge text-bg-light border ms-1">${esc(c.responsable_definido || 'Sistemas')}</span>
        </div>
        <div class="item-meta">
          ${c.fecha_completado ? 'Completado ' + esc(c.fecha_completado) + (c.responsable_nombre ? ' por ' + esc(c.responsable_nombre) : '') : 'Pendiente'}
        </div>
        ${ok && editable ? `
          <input type="text" class="form-control form-control-sm item-obs mt-1" maxlength="250"
                 placeholder="Observación (opcional) — se guarda al marcar/desmarcar"
                 value="${esc(c.observacion || '')}">` : ''}
      </div>
    </div>`;
  },

  _bindearChecklist(estadoPersona) {
    if (estadoPersona === 'cesado') return;    // historico: solo lectura

    $('#modal-general-cuerpo').off('change', '.item-check').on('change', '.item-check', function () {
      const $item = $(this).closest('.item-checklist');
      const id    = $item.data('id');
      const estado = $(this).is(':checked') ? 1 : 0;
      const obs   = $.trim($item.find('.item-obs').val() || '');

      Personal._toggleItem(id, estado, obs, $item);
    });
  },

  _toggleItem(id, estado, obs, $item) {
    Api.post('api/personal/checklist.php', { id: id, completado: estado, observacion: obs })
      .then(data => {
        // actualizar la fila visualmente sin recargar nada
        if (estado === 1) {
          $item.addClass('completado');
          $item.find('.item-meta').text('Completado ' + data.fecha || 'ahora');
        } else {
          $item.removeClass('completado');
          $item.find('.item-meta').text('Pendiente');
          $item.find('.item-obs').remove();
        }
        // actualizar barra de progreso del modal
        const pct = data.total ? Math.round(100 * data.ok / data.total) : 0;
        $('#modal-general-cuerpo .progress-bar').css('width', pct + '%');
        $('#modal-general-cuerpo .badge.text-bg-primary').text(data.ok + '/' + data.total);
        if (data.ok === data.total && data.total > 0) {
          $('#modal-general-cuerpo .progress-bar').addClass('bg-success');
          Toast.success('¡Checklist de ingreso completado al 100%! ' +
            'La persona está lista para pasar a estado «Activo».');
        } else {
          $('#modal-general-cuerpo .progress-bar').removeClass('bg-success');
        }
      });
  },

  // ================= FORMULARIO ALTA / EDICION =================
  _abrirForm(fila) {
    const esNuevo = !fila;

    Promise.all([
      Api.get('api/catalogos/select.php', { tipo: 'areas' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'personal' }).catch(() => [])
    ]).then(([areas, jefes]) => {

      const val = c => esNuevo ? '' : (fila[c] ?? '');
      const estadoSel = esNuevo ? 'pre_ingreso' : val('estado');
      const hoy = new Date().toISOString().slice(0, 10);

      const html = `
      <form id="form-personal" autocomplete="off" data-id="${esNuevo ? 0 : fila.id}">
        <div class="row">
          <div class="col-md-4 mb-3" data-campo="dni">
            <label class="form-label">DNI <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="fp-dni" value="${esc(val('dni'))}"
                   maxlength="8" placeholder="8 dígitos">
          </div>
          <div class="col-md-4 mb-3" data-campo="nombres">
            <label class="form-label">Nombres <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="fp-nombres" value="${esc(val('nombres'))}" maxlength="80">
          </div>
          <div class="col-md-4 mb-3" data-campo="apellidos">
            <label class="form-label">Apellidos <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="fp-apellidos" value="${esc(val('apellidos'))}" maxlength="80">
          </div>

          <div class="col-md-6 mb-3" data-campo="correo_personal">
            <label class="form-label">Correo personal</label>
            <input type="email" class="form-control" id="fp-correo-p" value="${esc(val('correo_personal'))}" maxlength="120">
          </div>
          <div class="col-md-6 mb-3" data-campo="correo_corporativo">
            <label class="form-label">Correo corporativo</label>
            <input type="email" class="form-control" id="fp-correo-c" value="${esc(val('correo_corporativo'))}"
                   maxlength="120" placeholder="Se crea en el checklist de ingreso">
          </div>

          <div class="col-md-4 mb-3" data-campo="area_id">
            <label class="form-label">Área <span class="text-danger">*</span></label>
            <select class="form-select" id="fp-area">
              ${areas.map(a => `<option value="${a.id}" ${+val('area_id') === +a.id ? 'selected' : ''}>${esc(a.texto)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-4 mb-3" data-campo="cargo">
            <label class="form-label">Cargo <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="fp-cargo" value="${esc(val('cargo'))}" maxlength="80">
          </div>
          <div class="col-md-4 mb-3" data-campo="jefe_id">
            <label class="form-label">Jefe inmediato</label>
            <select class="form-select" id="fp-jefe">
              <option value="">— Sin jefe asignado —</option>
              ${jefes.filter(j => !esNuevo || true).map(j =>
                `<option value="${j.id}" ${+val('jefe_id') === +j.id ? 'selected' : ''}>${esc(j.texto)}</option>`).join('')}
            </select>
          </div>

          <div class="col-md-3 mb-3" data-campo="fecha_ingreso">
            <label class="form-label">Fecha de ingreso</label>
            <input type="date" class="form-control" id="fp-fingreso" value="${esc(val('fecha_ingreso') || (esNuevo ? hoy : ''))}">
          </div>
          <div class="col-md-3 mb-3" data-campo="fecha_cese">
            <label class="form-label">Fecha de cese</label>
            <input type="date" class="form-control" id="fp-fcese" value="${esc(val('fecha_cese'))}">
          </div>
          <div class="col-md-3 mb-3" data-campo="tipo_personal">
            <label class="form-label">Tipo de personal <span class="text-danger">*</span></label>
            <select class="form-select" id="fp-tipo">
              ${Object.entries(TIPOS_PERSONAL).map(([k, t]) =>
                `<option value="${k}" ${val('tipo_personal') === k || (esNuevo && k === 'empleado') ? 'selected' : ''}>${t}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-3 mb-3" data-campo="estado">
            <label class="form-label">Estado <span class="text-danger">*</span></label>
            <select class="form-select" id="fp-estado" ${!esNuevo ? '' : 'disabled'}>
              ${Object.entries(ESTADOS_PERSONAL).map(([k, e]) =>
                `<option value="${k}" ${estadoSel === k ? 'selected' : ''}>${e.txt}</option>`).join('')}
            </select>
            ${esNuevo ? '<input type="hidden" id="fp-estado-h" value="pre_ingreso">'
                      : '<div class="form-text">El cambio a «Cesado» se realiza desde el flujo de cese (Paso 10).</div>'}
          </div>

          <div class="col-12 mb-2" data-campo="observaciones">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" id="fp-obs" rows="2" maxlength="500">${esc(val('observaciones'))}</textarea>
          </div>
        </div>

        ${esNuevo ? `<div class="alert alert-info py-2 small">
          <i class="bi bi-magic"></i> Al guardar se generará automáticamente el
          <b>checklist de onboarding</b> (correo, dominio/AD, accesos, entrega de equipos...) y se abrirá su ficha.
        </div>` : ''}

        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-guardar-personal">
            <i class="bi bi-check-lg"></i> ${esNuevo ? 'Registrar persona' : 'Guardar cambios'}
          </button>
        </div>
      </form>`;

      Modal.abrir(esNuevo ? 'Registrar ingreso de personal' : 'Editar — ' + fila.apellidos + ', ' + fila.nombres, html);

      $('#btn-guardar-personal').on('click', () => this._guardar());
      $('#form-personal').on('submit', e => { e.preventDefault(); this._guardar(); });
    });
  },

  _guardar() {
    const esNuevo = +($('#form-personal').data('id') || 0) === 0;
    const datos = {
      id:                $('#form-personal').data('id') || 0,
      dni:               $.trim($('#fp-dni').val() || ''),
      nombres:           $.trim($('#fp-nombres').val() || ''),
      apellidos:         $.trim($('#fp-apellidos').val() || ''),
      correo_personal:   $.trim($('#fp-correo-p').val() || ''),
      correo_corporativo:$.trim($('#fp-correo-c').val() || ''),
      area_id:           $('#fp-area').val() || '',
      cargo:             $.trim($('#fp-cargo').val() || ''),
      jefe_id:           $.trim($('#fp-jefe').val() || ''),
      fecha_ingreso:     $('#fp-fingreso').val() || '',
      fecha_cese:        $('#fp-fcese').val() || '',
      tipo_personal:     $('#fp-tipo').val() || '',
      estado:            esNuevo ? 'pre_ingreso' : ($('#fp-estado').val() || 'pre_ingreso'),
      observaciones:     $.trim($('#fp-obs').val() || '')
    };

    Api.post('api/personal/guardar.php', datos)
      .then(data => {
        Modal.cerrar();
        this.tabla.draw();
        // al crear: abrir directamente el checklist para empezar el onboarding
        if (data && data.abrir_detalle) {
          setTimeout(() => this._verDetalle(data.id), 350);
        }
      })
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-personal', err.cuerpo.errors);
      });
  },

  // ================= ELIMINAR (solo pre-ingreso) =================
  _eliminar(fila) {
    Swal.fire({
      title: '¿Eliminar este pre-ingreso?',
      html: `<strong>${esc(fila.nombres)} ${esc(fila.apellidos)}</strong> (DNI ${esc(fila.dni)})<br>
             <small>Solo es posible si no tiene actividad registrada.</small>`,
      icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626'
    }).then(r => {
      if (!r.isConfirmed) return;
      Api.post('api/personal/eliminar.php', { id: fila.id }).then(() => this.tabla.draw());
    });
  }
};

App.registrar('personal', Personal);