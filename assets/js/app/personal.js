// ============================================================
// SIGTI - Personal v3: altas + checklist + FICHA + CESES
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
            if (!cesado && !['cese_programado','en_proceso_cese'].includes(fila.estado)
                && App.permisos.includes('personal.cese')) {
              h += '<button class="btn btn-sm btn-outline-warning btn-cese me-1" title="Registrar cese"><i class="bi bi-calendar-x"></i></button>';
            }
            if (['cese_programado','en_proceso_cese'].includes(fila.estado)
                && App.permisos.includes('personal.cese')) {
              h += '<button class="btn btn-sm btn-outline-danger btn-procesar-cese" title="Procesar cese (devolver equipos)"><i class="bi bi-box-arrow-in-left"></i></button>';
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

    $('#btn-nuevo-personal').on('click', () => this._abrirForm(null));
    $('#btn-ver-pendientes').on('click', () => this._verPendientes());

    $('#tb-personal').on('click', '.btn-ver, .btn-editar, .btn-eliminar, .btn-cese, .btn-procesar-cese', function () {
      const fila = Personal.tabla.row($(this).closest('tr')).data();
      if (!fila) return;
      if      ($(this).hasClass('btn-ver'))           Personal._verDetalle(fila.id);
      else if ($(this).hasClass('btn-editar'))        Personal._abrirForm(fila);
      else if ($(this).hasClass('btn-cese'))          Personal._registrarCese(fila);
      else if ($(this).hasClass('btn-procesar-cese')) Personal._procesarCese(fila);
      else                                             Personal._eliminar(fila);
    });
  },

  // ================= REGISTRAR CESE =================
  _registrarCese(fila) {
    const hoy = new Date();
    const en15 = new Date(hoy.getTime() + 15 * 86400000).toISOString().slice(0, 10);

    const html = `
    <form id="form-cese">
      <div class="alert alert-warning py-2 small">
        <i class="bi bi-calendar-x"></i>
        Programar el cese de <b>${esc(fila.nombres)} ${esc(fila.apellidos)}</b>
        (${esc(fila.cargo || '')} · ${esc(fila.area || '—')}).
        Al registrar: el estado pasa a <b>Cese programado</b> y se genera el
        <b>checklist de salida</b> (bloqueos, devolución de equipos...).
      </div>
      <div class="row">
        <div class="col-md-4 mb-3" data-campo="fecha_cese">
          <label class="form-label">Fecha de cese <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="fc-fecha" value="${en15}" min="${hoy.toISOString().slice(0,10)}">
        </div>
        <div class="col-md-4 mb-3" data-campo="tipo_cese">
          <label class="form-label">Tipo de cese</label>
          <select class="form-select" id="fc-tipo">
            <option value="renuncia">Renuncia</option>
            <option value="despido">Despido</option>
            <option value="fin_contrato">Fin de contrato</option>
            <option value="jubilacion">Jubilación</option>
            <option value="otros">Otros</option>
          </select>
        </div>
        <div class="col-md-4 mb-3" data-campo="motivo">
          <label class="form-label">Detalle/motivo</label>
          <input type="text" class="form-control" id="fc-motivo" maxlength="250">
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-warning" id="fc-guardar"><i class="bi bi-calendar-x"></i> Programar cese</button>
      </div>
    </form>`;

    Modal.abrir('Registrar cese — ' + fila.apellidos, html);

    $('#fc-guardar').on('click', () => {
      Api.post('api/personal/cese.php', {
        id: fila.id,
        fecha_cese: $('#fc-fecha').val() || '',
        tipo_cese: $('#fc-tipo').val() || '',
        motivo: $.trim($('#fc-motivo').val() || '')
      }).then(data => {
        Modal.cerrar();
        this.tabla.draw();
        Toast.info(data.equipos + ' equipo(s) y ' + data.cuentas + ' cuenta(s) por recuperar/bloquear.');
      }).catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-cese', err.cuerpo.errors);
      });
    });
  },

  // ================= PROCESAR CESE (devolucion masiva) =================
  _procesarCese(fila) {
    Api.get('api/personal/detalle.php', { id: fila.id }).then(p => {

      const equipos = p.equipos || [];
      const cuentas = p.cuentas || [];
      const checklistCese = p.checklist_cese || [];

      let equiposHtml = '';
      if (equipos.length === 0) {
        equiposHtml = '<div class="alert alert-success py-2 small">Sin equipos asignados.</div>';
      } else {
        equiposHtml = equipos.map(e => `
        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-center">
            <div><b>${esc(e.codigo)}</b> — ${esc(e.tipo_eq)} ${esc(e.marca)} ${esc(e.modelo || '')}
              ${e.nro_serie ? ' · S/N ' + esc(e.nro_serie) : ''}</div>
            <span class="badge text-bg-info">${e.tipo === 'prestamo' ? 'Préstamo' : 'Permanente'}</span>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-md-4">
              <select class="form-select form-select-sm cond-dev" data-asig="${e.asig_id}">
                <option value="bueno">Devuelto — Bueno</option>
                <option value="regular">Devuelto — Regular</option>
                <option value="danado">Devuelto — Dañado</option>
                <option value="faltante">NO ENTREGADO (faltante)</option>
              </select>
            </div>
            <div class="col-md-8">
              <input type="text" class="form-control form-control-sm obs-dev" data-asig="${e.asig_id}"
                     maxlength="200" placeholder="Observación (accesorios faltantes, daño...)">
            </div>
          </div>
        </div>`).join('');
      }

      let cuentasHtml = cuentas.length
        ? cuentas.map(c => `
          <span class="badge ${c.estado === 'activo' ? 'text-bg-danger' : 'text-bg-secondary'} me-1 mb-1">
            ${esc(c.usuario)} (${esc(c.estado)})</span>`).join('')
        : '<span class="text-muted small">Sin cuentas de sistema.</span>';

      let checksHtml = '';
      if (checklistCese.length) {
        checksHtml = '<div class="border rounded p-2 mb-3"><div class="form-label mb-1">' +
          '<i class="bi bi-shield-x me-1"></i>Verificación de accesos (marque lo realizado)</div>' +
          '<div class="row small">' + checklistCese.map((c, i) =>
            '<div class="col-md-6"><div class="form-check">' +
            '<input class="form-check-input verif-acc" type="checkbox" value="' + i + '" id="va-' + i + '">' +
            '<label class="form-check-label" for="va-' + i + '">' + esc(c.item) + '</label></div></div>'
          ).join('') + '</div></div>';
      }

      const html = `
      <form id="form-procesar-cese" data-id="${fila.id}">
        <div class="alert alert-danger py-2 small">
          <i class="bi bi-exclamation-triangle"></i>
          Procesar el cese de <b>${esc(p.nombres)} ${esc(p.apellidos)}</b>:
          se cerrarán <b>${equipos.length}</b> asignación(es), se bloquearán <b>${cuentas.filter(c=>c.estado==='activo').length}</b> cuenta(s),
          se generará el <b>Acta LUMAT-TI-FOR-001</b> y el personal quedará <b>CESADO</b> (histórico).
        </div>

        <div class="form-label mb-1"><i class="bi bi-pc-display me-1"></i>Equipos a recibir
          <span class="text-muted small">(indique condición de cada uno)</span></div>
        ${equiposHtml}

        <div class="border rounded p-2 mb-3">
          <div class="form-label mb-1"><i class="bi bi-person-x me-1"></i>Cuentas a bloquear</div>
          ${cuentasHtml}
        </div>

        ${checksHtml}

        <div class="mb-3" data-campo="observaciones">
          <label class="form-label">Observaciones del acta</label>
          <textarea class="form-control" id="pc-obs" rows="2" maxlength="1000"></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-danger" id="pc-confirmar">
            <i class="bi bi-box-arrow-in-left"></i> PROCESAR CESE definitivo</button>
        </div>
      </form>`;

      Modal.abrir('Procesar cese — ' + fila.apellidos + ', ' + fila.nombres, html, 'modal-xl');

      $('#pc-confirmar').on('click', () => {
        // condiciones por equipo
        const condiciones = [];
        $('#form-procesar-cese .cond-dev').each(function () {
          condiciones.push({
            asig_id: $(this).data('asig'),
            condicion: $(this).val(),
            obs: $.trim($('[data-asig="' + $(this).data('asig') + '"].obs-dev').val() || '')
          });
        });

        // verificación de accesos (indices del checklist_cese)
        const verifAcc = [];
        $('.verif-acc:checked').each(function () {
          const i = +$(this).val();
          if (checklistCese[i]) verifAcc.push(checklistCese[i].item);
        });

        const faltantes = condiciones.filter(c => c.condicion === 'faltante').length;
        const confirmar = () => {
          Api.post('api/personal/procesar-cese.php', {
            id: fila.id,
            condiciones: JSON.stringify(condiciones),
            verificacion_accesos: JSON.stringify(verifAcc),
            observaciones: $.trim($('#pc-obs').val() || '')
          }).then(data => {
            Modal.cerrar();
            this.tabla.draw();
            if (data.faltantes && data.faltantes.length) {
              Swal.fire({
                title: 'Cese procesado con PENDIENTES',
                html: '<b>Equipos NO entregados:</b><br>' + data.faltantes.join('<br>') +
                      '<br><br>Quedaron en estado <b>En revisión</b>. El acta los registra como faltantes.',
                icon: 'warning'
              });
            }
            setTimeout(() => window.open(data.acta_url, '_blank'), 400);
          }).catch(err => {
            if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-procesar-cese', err.cuerpo.errors);
          });
        };

        if (faltantes > 0) {
          Swal.fire({
            title: '¿' + faltantes + ' equipo(s) marcados como NO entregados?',
            text: 'Quedarán en revisión y registrados como FALTANTES en el acta (para descuentos/reclamos).',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Sí, continuar', cancelButtonText: 'Revisar'
          }).then(r => { if (r.isConfirmed) confirmar(); });
        } else {
          Swal.fire({
            title: '¿Procesar el cese definitivamente?',
            html: 'Se generarán todos los cambios y el <b>acta LUMAT-TI-FOR-001</b>.<br>Esta acción es irreversible.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Sí, procesar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626'
          }).then(r => { if (r.isConfirmed) confirmar(); });
        }
      });
    });
  },

  // ================= PENDIENTES (reporte critico) =================
  _verPendientes() {
    Api.get('api/personal/ceses-pendientes.php').then(data => {

      let html = `
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="border rounded p-2 h-100">
            <div class="form-label mb-1"><i class="bi bi-calendar-x text-warning"></i> Ceses próximos (30 días)</div>`;
      if (!data.proximos.length) {
        html += '<div class="text-muted small p-2">Sin ceses programados.</div>';
      } else {
        html += '<table class="table tabla-mini table-sm mb-0"><thead><tr>' +
                '<th>Persona</th><th>Fecha</th><th>Días</th><th>Equipos</th><th>Cuentas</th></tr></thead><tbody>';
        data.proximos.forEach(c => {
          html += '<tr><td>' + esc(c.nombre) + '<br><span class="text-muted small">' + esc(c.area || '') + '</span></td>' +
                  '<td class="nowrap"><b>' + esc(c.fecha_cese) + '</b></td>' +
                  '<td><span class="badge ' + (c.dias_restantes <= 7 ? 'text-bg-danger' : 'text-bg-warning text-dark') + '">' +
                  c.dias_restantes + 'd</span></td>' +
                  '<td class="text-center">' + c.equipos + '</td><td class="text-center">' + c.cuentas + '</td></tr>';
        });
        html += '</tbody></table>';
      }
      html += '</div></div>';

      html += `
        <div class="col-md-6">
          <div class="border rounded p-2 h-100 ${data.total_pendientes ? 'border-danger' : ''}">
            <div class="form-label mb-1">
              <i class="bi bi-exclamation-octagon ${data.total_pendientes ? 'text-danger' : 'text-success'}"></i>
              Cesados con equipos PENDIENTES
              ${data.total_pendientes ? '<span class="badge text-bg-danger">' + data.total_pendientes + '</span>' : ''}
            </div>`;
      if (!data.pendientes.length) {
        html += '<div class="text-muted small p-2">Sin pendientes. Toda devolución completada. ✔</div>';
      } else {
        html += '<table class="table tabla-mini table-sm mb-0"><thead><tr>' +
                '<th>Persona</th><th>Cesado</th><th>Pendientes</th><th>Equipos</th></tr></thead><tbody>';
        data.pendientes.forEach(c => {
          html += '<tr class="table-danger"><td>' + esc(c.nombre) + '</td>' +
                  '<td class="nowrap">' + esc(c.fecha_cese || '') + '</td>' +
                  '<td class="text-center"><span class="badge text-bg-danger">' + c.equipos_pendientes + '</span></td>' +
                  '<td class="small">' + esc(c.equipos_lista || '') + '</td></tr>';
        });
        html += '</tbody></table>';
      }
      html += '</div></div></div>';

      Modal.abrir('Ceses — próximos y pendientes', html, 'modal-xl');
    });
  },

  // ================= FICHA (con cese) =================
  _verDetalle(id) {
    Api.get('api/personal/detalle.php', { id: id }).then(p => {

      const e = ESTADOS_PERSONAL[p.estado] || { txt: p.estado, cls: 'text-bg-light' };
      const pct = p.check_total ? Math.round(100 * p.check_ok / p.check_total) : 0;

      let equiposHtml = '';
      if (p.equipos && p.equipos.length) {
        equiposHtml = '<table class="table tabla-mini table-sm table-bordered"><thead><tr>' +
          '<th>Equipo</th><th>Modalidad</th><th>Desde</th><th>Retorno</th></tr></thead><tbody>' +
          p.equipos.map(e2 =>
            '<tr><td><b>' + esc(e2.codigo) + '</b> — ' + esc(e2.tipo_eq + ' ' + e2.marca) + '</td>' +
            '<td>' + (e2.tipo === 'prestamo' ? 'Préstamo' : 'Permanente') + '</td>' +
            '<td class="small">' + esc(e2.fecha_entrega || '') + '</td>' +
            '<td class="small">' + esc(e2.fecha_devolucion_esperada || '—') + '</td></tr>'
          ).join('') + '</tbody></table>';
      }

      let cuentasHtml = '';
      if (p.cuentas && p.cuentas.length) {
        cuentasHtml = p.cuentas.map(c =>
          '<span class="badge ' + (c.estado === 'activo' ? 'text-bg-success' :
            c.estado === 'bloqueado' ? 'text-bg-danger' : 'text-bg-secondary') +
          ' me-1 mb-1">' + esc(c.usuario) + ' · ' + esc(c.rol) + '</span>').join('');
      }

      const checkHtml = (lista) => lista.length ? lista.map(c => `
        <div class="item-checklist ${+c.completado === 1 ? 'completado' : ''} mb-2">
          <input type="checkbox" class="item-check form-check-input" ${+c.completado === 1 ? 'checked' : ''}>
          <div class="flex-fill">
            <div class="item-texto">${esc(c.item)}
              ${+c.obligatorio === 1 ? '<span class="text-danger">*</span>' : ''}</div>
            <div class="item-meta">${c.fecha_completado ? 'Completado ' + esc(c.fecha_completado) : 'Pendiente'}</div>
          </div>
        </div>`).join('') : '<div class="text-muted small">Sin ítems.</div>';

      const html = `
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <h5 class="mb-1">${esc(p.nombres)} ${esc(p.apellidos)}</h5>
          <div class="text-muted small">DNI ${esc(p.dni)} · ${esc(p.cargo || '')} · ${esc(p.area || 'sin área')}</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
          ${['cese_programado','en_proceso_cese'].includes(p.estado) && App.permisos.includes('personal.cese') ?
            '<button class="btn btn-sm btn-danger" id="ficha-procesar-cese"><i class="bi bi-box-arrow-in-left"></i> Procesar cese</button>' : ''}
          <span class="badge ${e.cls} badge-estado-personal">${e.txt}</span>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Ingreso</div><strong>${esc(p.fecha_ingreso || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Cese</div><strong>${esc(p.fecha_cese || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Tipo</div><strong>${esc(TIPOS_PERSONAL[p.tipo_personal] || '')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Equipos activos</div><strong>${(p.equipos || []).length}</strong></div></div>
      </div>

      ${equiposHtml ? '<div class="mb-3"><b class="small"><i class="bi bi-pc-display me-1"></i>Equipos asignados (${p.equipos.length})</b>' + equiposHtml + '</div>' : ''}
      ${cuentasHtml ? '<div class="mb-3"><b class="small"><i class="bi bi-person-badge me-1"></i>Cuentas de sistema</b><br>' + cuentasHtml + '</div>' : ''}

      <div class="d-flex align-items-center gap-2 mb-2">
        <strong><i class="bi bi-list-check me-1"></i>Checklist de ingreso</strong>
        <div class="progress flex-fill"><div class="progress-bar" style="width:${pct}%"></div></div>
        <span class="badge text-bg-primary">${p.check_ok}/${p.check_total}</span>
      </div>
      ${checkHtml(p.checklist_ingreso || [])}

      ${(p.checklist_cese || []).length ? `
      <div class="d-flex align-items-center gap-2 mb-2 mt-3">
        <strong><i class="bi bi-shield-x me-1"></i>Checklist de salida (cese)</strong>
      </div>
      ${checkHtml(p.checklist_cese)}` : ''}
      `;

      Modal.abrir('Ficha de personal', html, 'modal-lg');

      if ($('#ficha-procesar-cese').length) {
        $('#ficha-procesar-cese').on('click', () => {
          Modal.cerrar();
          setTimeout(() => this._procesarCese({ id: p.id, nombres: p.nombres, apellidos: p.apellidos,
            cargo: p.cargo, area: p.area }), 250);
        });
      }

      if (p.estado !== 'cesado') this._bindearChecklist();
    });
  },

  _bindearChecklist() {
    $('#modal-general-cuerpo').off('change', '.item-check').on('change', '.item-check', function () {
      const $item = $(this).closest('.item-checklist');
      const id = $item.data('id') || null;
      if (!id) return;  // checklist de cese: solo lectura (se marca al procesar)
      const estado = $(this).is(':checked') ? 1 : 0;
      const obs = $.trim($item.find('.item-obs').val() || '');
      Personal._toggleItem(id, estado, obs, $item);
    });
  },

  _toggleItem(id, estado, obs, $item) {
    Api.post('api/personal/checklist.php', { id: id, completado: estado, observacion: obs })
      .then(data => {
        if (estado === 1) $item.addClass('completado');
        else { $item.removeClass('completado'); $item.find('.item-obs').remove(); }
        const pct = data.total ? Math.round(100 * data.ok / data.total) : 0;
        $('#modal-general-cuerpo .progress-bar').css('width', pct + '%');
        $('#modal-general-cuerpo .badge.text-bg-primary').text(data.ok + '/' + data.total);
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
      const hoy = new Date().toISOString().slice(0, 10);

      const html = `
      <form id="form-personal" autocomplete="off" data-id="${esNuevo ? 0 : fila.id}">
        <div class="row">
          <div class="col-md-4 mb-3" data-campo="dni">
            <label class="form-label">DNI <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="fp-dni" value="${esc(val('dni'))}" maxlength="8" placeholder="8 dígitos">
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
            <input type="email" class="form-control" id="fp-correo-c" value="${esc(val('correo_corporativo'))}" maxlength="120">
          </div>
          <div class="col-md-4 mb-3" data-campo="telefono">
            <label class="form-label">Celular</label>
            <input type="text" class="form-control" id="fp-telefono" value="${esc(val('telefono'))}" maxlength="20">
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
              <option value="">— Sin jefe —</option>
              ${jefes.filter(j => !fila || j.id !== fila.id).map(j =>
                `<option value="${j.id}" ${+val('jefe_id') === +j.id ? 'selected' : ''}>${esc(j.texto)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-4 mb-3" data-campo="fecha_ingreso">
            <label class="form-label">Fecha de ingreso</label>
            <input type="date" class="form-control" id="fp-fingreso" value="${esc(val('fecha_ingreso') || (esNuevo ? hoy : ''))}">
          </div>
          <div class="col-md-4 mb-3" data-campo="tipo_personal">
            <label class="form-label">Tipo de personal <span class="text-danger">*</span></label>
            <select class="form-select" id="fp-tipo">
              ${Object.entries(TIPOS_PERSONAL).map(([k, t]) =>
                `<option value="${k}" ${val('tipo_personal') === k || (esNuevo && k === 'empleado') ? 'selected' : ''}>${t}</option>`).join('')}
            </select>
          </div>
          <div class="col-12 mb-2" data-campo="observaciones">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" id="fp-obs" rows="2" maxlength="500">${esc(val('observaciones'))}</textarea>
          </div>
        </div>

        ${esNuevo ? '<div class="alert alert-info py-2 small"><i class="bi bi-magic"></i> Al guardar se genera el <b>checklist de onboarding</b> automáticamente.</div>' : ''}

        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-guardar-personal">
            <i class="bi bi-check-lg"></i> ${esNuevo ? 'Registrar persona' : 'Guardar cambios'}</button>
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
      telefono:          $.trim($('#fp-telefono').val() || ''),
      area_id:           $('#fp-area').val() || '',
      cargo:             $.trim($('#fp-cargo').val() || ''),
      jefe_id:           $.trim($('#fp-jefe').val() || ''),
      fecha_ingreso:     $('#fp-fingreso').val() || '',
      fecha_cese:        '',
      tipo_personal:     $('#fp-tipo').val() || '',
      estado:            esNuevo ? 'pre_ingreso' : 'activo',
      observaciones:     $.trim($('#fp-obs').val() || '')
    };

    Api.post('api/personal/guardar.php', datos)
      .then(data => {
        Modal.cerrar();
        this.tabla.draw();
        if (data && data.abrir_detalle) {
          setTimeout(() => this._verDetalle(data.id), 350);
        }
      })
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-personal', err.cuerpo.errors);
      });
  },

  _eliminar(fila) {
    Swal.fire({
      title: '¿Eliminar este pre-ingreso?',
      html: `<strong>${esc(fila.nombres)} ${esc(fila.apellidos)}</strong> (DNI ${esc(fila.dni)})`,
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