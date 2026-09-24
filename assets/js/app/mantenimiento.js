// ============================================================
// SIGTI - Modulo Mantenimiento de Equipos
// ============================================================

const ESTADOS_MT = {
  solicitado:        { txt: 'Solicitado',       cls: 'text-bg-secondary' },
  en_evaluacion:     { txt: 'En evaluación',    cls: 'text-bg-info' },
  en_proceso:        { txt: 'En proceso',       cls: 'text-bg-primary' },
  enviado_proveedor: { txt: 'En proveedor',     cls: 'text-bg-warning text-dark' },
  cotizado:          { txt: 'Cotizado',         cls: 'text-bg-warning text-dark' },
  aprobado:          { txt: 'Aprobado',         cls: 'text-bg-success' },
  rechazado:         { txt: 'Rechazado',        cls: 'text-bg-danger' },
  en_reparacion:     { txt: 'En reparación',    cls: 'text-bg-warning text-dark' },
  recibido_reparado: { txt: 'Recibido',         cls: 'text-bg-info' },
  listo:             { txt: 'Listo',            cls: 'text-bg-success' },
  entregado:         { txt: 'Entregado',        cls: 'text-bg-primary' },
  devuelto_stock:    { txt: 'Devuelto stock',   cls: 'text-bg-success' },
  cerrado:           { txt: 'Cerrado',          cls: 'text-bg-dark' },
  cancelado:         { txt: 'Cancelado',        cls: 'text-bg-danger' },
  no_reparable:      { txt: 'No reparable',     cls: 'text-bg-dark' }
};

const TIPOS_MT = { correctivo: 'Correctivo', preventivo: 'Preventivo',
                   limpieza: 'Limpieza', actualizacion: 'Actualización' };

const PERMITIDOS_MT = {
  solicitado:        [['en_evaluacion','Evaluar'],['cancelado','Cancelar']],
  en_evaluacion:     [['en_proceso','Taller interno'],['enviado_proveedor','Enviar a proveedor'],['no_reparable','No reparable'],['cancelado','Cancelar']],
  en_proceso:        [['listo','Marcar listo'],['no_reparable','No reparable'],['cancelado','Cancelar']],
  enviado_proveedor: [['cotizado','Registrar cotización'],['cancelado','Cancelar']],
  cotizado:          [['aprobado','Aprobar costo'],['rechazado','Rechazar'],['cancelado','Cancelar']],
  aprobado:          [['en_reparacion','En reparación'],['cancelado','Cancelar']],
  rechazado:         [['en_evaluacion','Re-evaluar'],['cancelado','Cancelar']],
  en_reparacion:     [['recibido_reparado','Recibido del proveedor'],['no_reparable','No reparable'],['cancelado','Cancelar']],
  recibido_reparado: [['listo','Marcar listo']],
  listo:             [['entregado','Entregar a usuario'],['devuelto_stock','Devolver a stock']],
  entregado:         [['cerrado','Cerrar MT']],
  devuelto_stock:    [['cerrado','Cerrar MT']],
  cerrado: [], cancelado: [], no_reparable: []
};

const Mantenimiento = {

  tabla: null,

  init() {
    this.tabla = DT.server('#tb-mt', 'api/mantenimiento/listar.php',
      () => ({
        f_estado:    $('#mtf-estado').val()    || '',
        f_tipo:      $('#mtf-tipo').val()      || '',
        f_proveedor: $('#mtf-proveedor').val() || 0,
        f_atrasados: $('#mtf-atrasados').is(':checked') ? 1 : 0
      }),
      [
        { data: 'codigo', render: v => '<strong class="nowrap">' + esc(v) + '</strong>' },
        { data: null, render: (v, t, f) =>
            '<span class="small"><b>' + esc(f.equipo_codigo) + '</b><br>' +
            esc(f.equipo_tipo + ' ' + f.equipo_marca) + '</span>' },
        { data: 'tipo', render: v => '<span class="badge text-bg-light border">' + esc(TIPOS_MT[v] || v) + '</span>' },
        { data: 'estado', render: v => {
            const e = ESTADOS_MT[v] || { txt: v, cls: 'text-bg-light' };
            return '<span class="badge ' + e.cls + ' mt-estado nowrap">' + e.txt + '</span>';
          } },
        { data: 'proveedor', render: v => v ? '<span class="small">' + esc(v) + '</span>' : '<span class="text-muted">—</span>' },
        { data: 'tecnico', render: v => v ? '<span class="small">' + esc(v) + '</span>' : '<span class="text-muted">—</span>' },
        { data: 'fecha_retorno_estimada', className: 'nowrap',
          render: (v, t, f) => {
            if (!v) return '<span class="text-muted">—</span>';
            return +f.atrasado === 1
              ? '<span class="badge text-bg-danger">' + esc(v) + ' ATASADO</span>'
              : '<span class="small">' + esc(v) + '</span>';
          } },
        { data: 'costo_total', className: 'text-end',
          render: v => v > 0 ? '<span class="small"><b>S/ ' + Number(v).toLocaleString('es-PE') + '</b></span>' : '<span class="text-muted">—</span>' },
        { data: 'fecha_inicio', className: 'nowrap', render: v => '<span class="small text-muted">' + esc(v) + '</span>' },
        { data: null, orderable: false, className: 'text-nowrap text-center',
          render: () => '<button class="btn btn-sm btn-outline-primary btn-ver-mt" title="Ver"><i class="bi bi-eye"></i></button>' }
      ]);

    this._cargarStats();

    $('#btn-filtrar-mt').on('click', () => { this.tabla.draw(); this._cargarStats(); });
    $('#mtf-estado, #mtf-tipo, #mtf-proveedor').on('change', () => { this.tabla.draw(); this._cargarStats(); });
    $('#mtf-atrasados').on('change', () => { this.tabla.draw(); this._cargarStats(); });
    $('#btn-limpiar-mt').on('click', () => {
      $('#mtf-estado').val(''); $('#mtf-tipo').val(''); $('#mtf-proveedor').val(''); $('#mtf-atrasados').prop('checked', false);
      this.tabla.draw(); this._cargarStats();
    });

    $('#btn-nuevo-mt').on('click', () => this._abrirForm());
    $('#tb-mt').on('click', '.btn-ver-mt', function () {
      const fila = Mantenimiento.tabla.row($(this).closest('tr')).data();
      if (fila) Mantenimiento._verDetalle(fila.id);
    });

    // ---- Preventivos programados ----
    $('#btn-nuevo-prog').on('click', () => this._abrirProg(null));
    $('#tab-prev').on('click', '.btn-ejecutar-prog', function () {
      Mantenimiento._ejecutarProg($(this).data('id'), $(this).data('equipo'));
    });
    $('#tab-prev').on('click', '.btn-editar-prog', function () {
      Mantenimiento._abrirProg($(this).data('id'));
    });
    $('#tab-prev').on('click', '.btn-toggle-prog', function () {
      Api.post('api/mantenimiento/programados.php', { accion: 'toggle', id: $(this).data('id') })
        .then(() => Router.ir('mantenimiento/lista'));
    });
  },

  _cargarStats() {
    Api.get('api/mantenimiento/stats.php').then(s => {
      $('#mt-stats [data-k]').each(function () {
        const k = $(this).data('k');
        $(this).text(k === 'costo_mes' ? 'S/ ' + Number(s[k]).toLocaleString('es-PE') : s[k]);
      });
    });
  },

  // ================= NUEVO MANTENIMIENTO =================
  _abrirForm() {
    Promise.all([
      Api.get('api/equipos/select.php', { modo: 'activos' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'tecnicos' }).catch(() => [])
    ]).then(([equipos, tecnicos]) => {

      const html = `
      <form id="form-mt" autocomplete="off">
        <div class="row">
          <div class="col-md-6 mb-3" data-campo="equipo_id">
            <label class="form-label">Equipo <span class="text-danger">*</span></label>
            <select class="form-select" id="fm-equipo">
              <option value="">— Seleccione —</option>
              ${equipos.map(e => `<option value="${e.id}">${esc(e.texto)}</option>`).join('')}
            </select>
            <div class="form-text">El equipo quedará <b>bloqueado</b> para asignaciones mientras esté en mantenimiento.</div>
          </div>
          <div class="col-md-3 mb-3" data-campo="tipo">
            <label class="form-label">Tipo <span class="text-danger">*</span></label>
            <select class="form-select" id="fm-tipo">
              ${Object.entries(TIPOS_MT).map(([k, t]) => `<option value="${k}">${t}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-3 mb-3" data-campo="tecnico_id">
            <label class="form-label">Técnico asignado</label>
            <select class="form-select" id="fm-tecnico">
              <option value="">— Sin asignar —</option>
              ${tecnicos.map(t => `<option value="${t.id}">${esc(t.texto)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-4 mb-3" data-campo="ticket_codigo">
            <label class="form-label">Ticket relacionado (opcional)</label>
            <input type="text" class="form-control" id="fm-ticket" maxlength="20" placeholder="Ej: TK-2025-00001">
          </div>
          <div class="col-12 mb-3" data-campo="diagnostico">
            <label class="form-label">Diagnóstico inicial</label>
            <textarea class="form-control" id="fm-diagnostico" rows="3" maxlength="2000"
                      placeholder="Falla reportada o motivo del mantenimiento..."></textarea>
          </div>
        </div>
        <div class="alert alert-info py-2 small">
          <i class="bi bi-magic"></i> El código <b>MT-2025-#####</b> se genera automáticamente
          y el estado inicia en <b>Solicitado</b>.
        </div>
        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-guardar-mt"><i class="bi bi-check-lg"></i> Registrar</button>
        </div>
      </form>`;

      Modal.abrir('Registrar mantenimiento', html, 'modal-lg');

      $('#btn-guardar-mt').on('click', () => {
        const datos = {
          equipo_id:   $('#fm-equipo').val() || '',
          tipo:        $('#fm-tipo').val() || '',
          tecnico_id:  $('#fm-tecnico').val() || '',
          ticket_codigo: $.trim($('#fm-ticket').val() || ''),
          diagnostico: $.trim($('#fm-diagnostico').val() || '')
        };
        if (!datos.equipo_id) { Toast.warning('Seleccione el equipo.'); return; }

        Api.post('api/mantenimiento/guardar.php', datos)
          .then(data => {
            Modal.cerrar();
            this.tabla.draw();
            this._cargarStats();
            if (data.abrir_detalle) setTimeout(() => this._verDetalle(data.id), 300);
          })
          .catch(err => {
            if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-mt', err.cuerpo.errors);
          });
      });
    });
  },

  // ================= DETALLE =================
  _verDetalle(id) {
    Api.get('api/mantenimiento/detalle.php', { id: id }).then(m => {

      const est = ESTADOS_MT[m.estado] || { txt: m.estado, cls: 'text-bg-light' };
      const puedeEditar = App.permisos.includes('mantenimiento.editar');
      const puedeEstado = App.permisos.includes('mantenimiento.cambiar_estado');
      const historico = ['cerrado','cancelado','no_reparable'].includes(m.estado);
      const acciones = (puedeEstado && !historico) ? (PERMITIDOS_MT[m.estado] || []) : [];

      let html = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <h5 class="mb-1">${esc(m.codigo)}
            <span class="text-muted small">· ${esc(m.equipo_tipo)} ${esc(m.equipo_marca)} ${esc(m.equipo_modelo || '')}</span></h5>
          <div class="text-muted small">
            Equipo <b>${esc(m.equipo_codigo)}</b> · iniciado ${esc(m.fecha_inicio)}
            ${m.ticket_codigo ? ' · ticket <b>' + esc(m.ticket_codigo) + '</b>' : ''}
          </div>
        </div>
        <span class="badge ${est.cls} mt-estado">${est.txt}</span>
      </div>

      ${m.fecha_retorno_estimada && ['enviado_proveedor','cotizado','aprobado','en_reparacion'].includes(m.estado) ? `
      <div class="alert ${m.fecha_retorno_estimada < new Date().toISOString().slice(0,10) ? 'alert-danger' : 'alert-warning'} py-2 small">
        <i class="bi bi-truck"></i> En <b>${esc(m.proveedor || 'proveedor')}</b> · retorno estimado
        <b>${esc(m.fecha_retorno_estimada)}</b>
        ${m.fecha_retorno_estimada < new Date().toISOString().slice(0,10) ? ' · <b>ATRASADO</b>' : ''}
        ${m.proveedor_tel ? ' · tel: ' + esc(m.proveedor_tel) : ''}
      </div>` : ''}

      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Tipo</div><strong class="small">${esc(TIPOS_MT[m.tipo] || m.tipo)}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Técnico</div><strong class="small">${m.tecnico ? esc(m.tecnico) : '—'}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Equipo ahora</div><strong class="small">${esc(m.equipo_estado || '')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Finalizado</div><strong class="small">${esc(m.fecha_fin || '—')}</strong></div></div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="border rounded p-2 h-100">
            <div class="text-muted small"><b>Diagnóstico</b></div>
            <div style="white-space:pre-wrap;font-size:13.5px">${esc(m.diagnostico || '—')}</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="border rounded p-2 h-100">
            <div class="text-muted small"><b>Solución / trabajo realizado</b></div>
            <div style="white-space:pre-wrap;font-size:13.5px">${esc(m.solucion || '—')}</div>
          </div>
        </div>
      </div>

      ${m.cotizacion_monto ? `
      <div class="alert alert-light border py-2 small mb-3">
        <i class="bi bi-file-earmark-text"></i> Cotización del proveedor:
        <b>S/ ${Number(m.cotizacion_monto).toLocaleString('es-PE')}</b>
        ${m.cotizacion_archivo ? ' · <a href="' + BASE_URL + 'api/mantenimiento/ver-archivo.php?tipo=cotizacion&id=' + m.id + '" target="_blank">ver archivo</a>' : ''}
        ${m.autorizado_por_nombre ? ' · aprobado por <b>' + esc(m.autorizado_por_nombre) + '</b>' : ''}
      </div>` : ''}
      ${m.motivo_cancelacion ? `
      <div class="alert ${m.estado === 'no_reparable' ? 'alert-dark' : 'alert-danger'} py-2 small mb-3">
        <b>${m.estado === 'no_reparable' ? 'NO REPARABLE' : 'Cancelado'}:</b> ${esc(m.motivo_cancelacion)}
        ${m.estado === 'no_reparable' ? '<br><small>Daría de baja al equipo desde el módulo de Inventario.</small>' : ''}
      </div>` : ''}

      ${acciones.length ? `
      <div class="d-flex gap-1 flex-wrap mb-3">
        ${acciones.map(([estado, texto]) =>
          '<button class="btn btn-sm btn-outline-' +
          (['cancelado','no_reparable'].includes(estado) ? 'danger' :
           ['aprobado','listo','devuelto_stock','cerrado'].includes(estado) ? 'success' :
           estado.startsWith('pendiente') || ['enviado_proveedor','cotizado','en_reparacion','rechazado'].includes(estado) ? 'warning text-dark' : 'primary') +
          ' btn-estado-mt" data-estado="' + estado + '" data-texto="' + texto + '">' + texto + '</button>').join('')}
        ${puedeEditar ? '<button class="btn btn-sm btn-outline-secondary btn-editar-mt"><i class="bi bi-pencil"></i> Editar info</button>' : ''}
      </div>` : (!historico && puedeEditar ? `
      <div class="mb-3"><button class="btn btn-sm btn-outline-secondary btn-editar-mt"><i class="bi bi-pencil"></i> Editar info</button></div>` : '')}

      <ul class="nav nav-tabs mb-2">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mt-tab-rep" type="button">Repuestos <span class="badge text-bg-light border">${m.repuestos.length}</span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mt-tab-ev" type="button">Evidencias <span class="badge text-bg-light border">${m.evidencias.length}</span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mt-tab-his" type="button">Historial</button></li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="mt-tab-rep">` + this._htmlRepuestos(m, puedeEditar && !historico) + `</div>
        <div class="tab-pane fade" id="mt-tab-ev">` + this._htmlEvidencias(m, puedeEditar && !['cerrado','no_reparable'].includes(m.estado)) + `</div>
        <div class="tab-pane fade" id="mt-tab-his">` + this._htmlHistorial(m) + `</div>
      </div>`;

      Modal.abrir('Mantenimiento ' + m.codigo, html, 'modal-xl');

      // bindeos
      $('#modal-general-cuerpo').off('click', '.btn-estado-mt').on('click', '.btn-estado-mt', function () {
        Mantenimiento._accionEstado(m, $(this).data('estado'), $(this).data('texto'));
      });
      $('#modal-general-cuerpo').off('click', '.btn-editar-mt').on('click', '.btn-editar-mt', () => this._editarInfo(m));

      if (puedeEditar && !historico) {
        $('#btn-agregar-rep').on('click', () => this._agregarRepuesto(m));
        $('#modal-general-cuerpo').off('click', '.btn-quitar-rep').on('click', '.btn-quitar-rep', function () {
          Api.post('api/mantenimiento/repuesto.php', { accion: 'quitar', mantenimiento_id: m.id, repuesto_id: $(this).closest('tr').data('rep') })
            .then(() => Mantenimiento._refrescar(m.id));
        });
      }
      if (puedeEditar && !['cerrado','no_reparable'].includes(m.estado)) {
        $('#btn-subir-ev').on('click', () => this._subirEvidencia(m));
        $('#modal-general-cuerpo').off('click', '.btn-quitar-ev').on('click', '.btn-quitar-ev', function () {
          Api.post('api/mantenimiento/evidencia.php', { accion: 'quitar', mantenimiento_id: m.id, evidencia_id: $(this).data('ev') })
            .then(() => Mantenimiento._refrescar(m.id));
        });
      }
    });
  },

  _htmlRepuestos(m, editable) {
    let h = '<div class="costos-box mb-2 d-flex justify-content-around text-center">' +
      '<div><div class="text-muted small">Mano de obra</div><b>S/ ' + Number(m.costo_mano_obra).toLocaleString('es-PE') + '</b></div>' +
      '<div><div class="text-muted small">Repuestos</div><b>S/ ' + Number(m.costo_repuestos).toLocaleString('es-PE') + '</b></div>' +
      '<div><div class="text-muted small">TOTAL</div><b class="text-primary">S/ ' + Number(m.costo_total).toLocaleString('es-PE') + '</b></div>' +
      '</div>';
    h += '<table class="table tabla-mini table-sm table-bordered"><thead><tr>' +
      '<th>Repuesto</th><th>Cant.</th><th class="text-end">C.Unit</th><th class="text-end">Subtotal</th>' +
      '<th>Proveedor</th><th class="text-center" style="width:60px"></th></tr></thead><tbody>';
    if (!m.repuestos.length) h += '<tr><td colspan="6" class="text-muted text-center">Sin repuestos registrados</td></tr>';
    m.repuestos.forEach(r => {
      h += `<tr data-rep="${r.id}">
        <td>${esc(r.descripcion)}</td>
        <td class="text-center">${r.cantidad}</td>
        <td class="text-end">S/ ${Number(r.costo_unitario).toLocaleString('es-PE')}</td>
        <td class="text-end"><b>S/ ${Number(r.subtotal).toLocaleString('es-PE')}</b></td>
        <td>${r.proveedor ? esc(r.proveedor) : '—'}</td>
        <td class="text-center">${editable ? '<button class="btn btn-sm btn-outline-danger btn-quitar-rep"><i class="bi bi-x-lg"></i></button>' : '—'}</td>
      </tr>`;
    });
    h += '</tbody></table>';
    if (editable) {
      h += `
      <div class="row g-2">
        <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="nr-desc" maxlength="150" placeholder="Repuesto * (Ej: Teclado)"></div>
        <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="nr-cant" min="0.01" step="0.01" value="1" placeholder="Cant"></div>
        <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="nr-cost" min="0" step="0.01" placeholder="S/ unit."></div>
        <div class="col-md-3"><select class="form-select form-select-sm" id="nr-prov"><option value="">Proveedor</option></select></div>
        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100" id="btn-agregar-rep"><i class="bi bi-plus-lg"></i></button></div>
      </div>`;
    }
    return h;
  },

  _htmlEvidencias(m, editable) {
    if (!m.evidencias.length && !editable) return '<div class="text-muted small p-2">Sin evidencias registradas.</div>';
    let h = '<div class="ev-grid mb-2">';
    m.evidencias.forEach(e => {
      const esImg = /\.(jpg|jpeg|png|gif)$/i.test(e.archivo);
      h += `<div class="ev-thumb">
        <a class="ev-img" href="${BASE_URL}api/mantenimiento/ver-archivo.php?tipo=evidencia&id=${e.id}" target="_blank">
          ${esImg ? '<img src="' + BASE_URL + 'api/mantenimiento/ver-archivo.php?tipo=evidencia&id=' + e.id + '" alt="ev">'
                  : '<i class="bi bi-file-earmark-pdf"></i>'}
        </a>
        <div class="ev-meta">
          <span class="ev-tipo ${e.tipo}">${e.tipo}</span>
          ${e.descripcion ? '<br>' + esc(e.descripcion) : ''}
          ${editable ? '<br><button class="btn btn-sm btn-outline-danger py-0 px-1 btn-quitar-ev" data-ev="' + e.id + '"><i class="bi bi-x-lg"></i></button>' : ''}
        </div>
      </div>`;
    });
    h += '</div>';
    if (editable) {
      h += `
      <div class="row g-2">
        <div class="col-md-2"><select class="form-select form-select-sm" id="ne-tipo">
          <option value="antes">ANTES</option><option value="despues">DESPUÉS</option><option value="otro">Otro</option></select></div>
        <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="ne-desc" maxlength="200" placeholder="Descripción (opcional)"></div>
        <div class="col-md-4"><input type="file" class="form-control form-control-sm" id="ne-file" accept=".jpg,.jpeg,.png,.gif,.pdf"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" id="btn-subir-ev"><i class="bi bi-upload"></i> Subir</button></div>
      </div>`;
    }
    return h;
  },

  _htmlHistorial(m) {
    if (!m.historial.length) return '<div class="text-muted small p-2">Sin eventos.</div>';
    return '<div class="timeline">' + m.historial.map(h => {
      const datos = h.datos_nuevos ? JSON.parse(h.datos_nuevos) : {};
      const det = datos.estado ? ('estado → ' + datos.estado)
                 : (datos.codigo ? 'creado ' + datos.codigo : '');
      return `<div class="tl-item">
        <div class="tl-titulo">${esc(h.accion.replace(/_/g, ' '))}</div>
        ${det ? '<div class="tl-detalle">' + esc(det) + '</div>' : ''}
        <div class="tl-fecha">${esc(h.created_at)}</div>
      </div>`;
    }).join('') + '</div>';
  },

  _refrescar(id) {
    const activa = $('#modal-general-cuerpo .nav-tabs .nav-link.active').data('bs-target') || '#mt-tab-rep';
    this._verDetalle(id);
    setTimeout(() => {
      $('#modal-general-cuerpo .nav-tabs .nav-link[data-bs-target="' + activa + '"]').tab('show');
    }, 250);
    this.tabla.draw();
    this._cargarStats();
  },

  // ================= ACCIONES DE ESTADO =================
  _accionEstado(m, nuevo, texto) {
    const conArchivo = nuevo === 'cotizado';

    let campos = '';
    if (nuevo === 'enviado_proveedor') {
      campos = `
      <div class="mb-3" data-campo="proveedor_id">
        <label class="form-label">Proveedor <span class="text-danger">*</span></label>
        <select class="form-select" id="ae-proveedor"><option value="">— Seleccione —</option></select>
      </div>
      <div class="mb-3" data-campo="fecha_retorno">
        <label class="form-label">Retorno estimado <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="ae-retorno">
      </div>`;
    }
    if (nuevo === 'cotizado') {
      campos = `
      <div class="mb-3" data-campo="cotizacion_monto">
        <label class="form-label">Monto cotizado (S/) <span class="text-danger">*</span></label>
        <input type="number" class="form-control" id="ae-monto" min="0.01" step="0.01">
      </div>
      <div class="mb-3" data-campo="cotizacion_archivo">
        <label class="form-label">Archivo de cotización <span class="text-danger">*</span></label>
        <input type="file" class="form-control" id="ae-file" accept=".jpg,.jpeg,.png,.pdf">
      </div>`;
    }
    if (['cancelado','rechazado','no_reparable'].includes(nuevo)) {
      campos = `
      <div class="mb-3" data-campo="motivo">
        <label class="form-label">Motivo <span class="text-danger">*</span></label>
        <textarea class="form-control" id="ae-motivo" rows="2" maxlength="250"></textarea>
      </div>`;
    }
    if (nuevo === 'listo') {
      campos = `
      <div class="mb-3" data-campo="solucion">
        <label class="form-label">Trabajo realizado <span class="text-danger">*</span></label>
        <textarea class="form-control" id="ae-solucion" rows="3" maxlength="2000"
                  placeholder="Ej: Cambio de teclado y limpieza interna."></textarea>
      </div>`;
    }

    const html = `
    <form id="form-estado-mt">
      <div class="alert alert-${['cancelado','no_reparable'].includes(nuevo) ? 'danger' : 'primary'} py-2 small">
        ${esc(m.codigo)}: <b>${esc((ESTADOS_MT[m.estado] || {}).txt || m.estado)}</b> →
        <b>${esc((ESTADOS_MT[nuevo] || {}).txt || nuevo)}</b>
      </div>
      ${campos}
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="ae-confirmar">${esc(texto)}</button>
      </div>
    </form>`;

    Modal.abrir('Cambiar estado', html, 'modal-md');

    if (nuevo === 'enviado_proveedor') {
      Api.get('api/catalogos/select.php', { tipo: 'proveedores' }).then(list => {
        $('#ae-proveedor').html('<option value="">— Seleccione —</option>' +
          list.map(p => '<option value="' + p.id + '">' + esc(p.texto) + '</option>').join(''));
      });
    }

    $('#ae-confirmar').on('click', () => {
      const enviar = () => {
        Api.post('api/mantenimiento/estado.php', datos)
          .then(() => { Modal.cerrar(); Mantenimiento._refrescar(m.id); })
          .catch(err => {
            if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-estado-mt', err.cuerpo.errors);
          });
      };

      if (conArchivo) {
        const fd = new FormData();
        Object.entries(datos).forEach(([k, v]) => fd.append(k, v));
        fd.append('cotizacion_archivo', $('#ae-file')[0].files[0]);
        $.ajax({
          url: BASE_URL + 'api/mantenimiento/estado.php',
          type: 'POST', data: fd, contentType: false, processData: false,
          headers: { 'X-CSRF-Token': CSRF.token },
          beforeSend: () => Loader.show(), complete: () => Loader.hide(),
          success: res => {
            if (res.success) { Toast.success(res.message); Modal.cerrar(); Mantenimiento._refrescar(m.id); }
            else { Toast.error(res.message); pintarErroresForm('#form-estado-mt', res.errors || {}); }
          }
        });
      } else enviar();
    });
  },

  // ================= EDITAR INFO =================
  _editarInfo(m) {
    Api.get('api/catalogos/select.php', { tipo: 'tecnicos' }).then(tecnicos => {
      const html = `
      <form id="form-edit-mt">
        <div class="mb-3" data-campo="diagnostico">
          <label class="form-label">Diagnóstico</label>
          <textarea class="form-control" id="em-diag" rows="2" maxlength="2000">${esc(m.diagnostico || '')}</textarea>
        </div>
        <div class="mb-3" data-campo="solucion">
          <label class="form-label">Solución / trabajo realizado</label>
          <textarea class="form-control" id="em-sol" rows="2" maxlength="2000">${esc(m.solucion || '')}</textarea>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3" data-campo="costo_mano_obra">
            <label class="form-label">Mano de obra (S/)</label>
            <input type="number" class="form-control" id="em-mo" min="0" step="0.01" value="${esc(m.costo_mano_obra)}">
          </div>
          <div class="col-md-6 mb-3" data-campo="tecnico_id">
            <label class="form-label">Técnico</label>
            <select class="form-select" id="em-tec">
              <option value="">— Sin asignar —</option>
              ${tecnicos.map(t => `<option value="${t.id}" ${m.tecnico_usuario_id === t.id ? 'selected' : ''}>${esc(t.texto)}</option>`).join('')}
            </select>
          </div>
        </div>
        <div class="mb-3" data-campo="observaciones">
          <label class="form-label">Observaciones</label>
          <textarea class="form-control" id="em-obs" rows="2" maxlength="1000">${esc(m.observaciones || '')}</textarea>
        </div>
        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="em-guardar">Guardar</button>
        </div>
      </form>`;

      Modal.abrir('Editar — ' + m.codigo, html, 'modal-lg');

      $('#em-guardar').on('click', () => {
        Api.post('api/mantenimiento/actualizar.php', {
          id: m.id,
          diagnostico: $('#em-diag').val(),
          solucion: $('#em-sol').val(),
          costo_mano_obra: $('#em-mo').val(),
          tecnico_id: $('#em-tec').val() || '',
          observaciones: $('#em-obs').val()
        }).then(() => Mantenimiento._refrescar(m.id))
          .catch(err => {
            if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-edit-mt', err.cuerpo.errors);
          });
      });
    });
  },

  // ================= REPUESTOS / EVIDENCIAS =================
  _agregarRepuesto(m) {
    const desc = $.trim($('#nr-desc').val() || '');
    if (!desc) { Toast.warning('Indique el repuesto.'); return; }
    Api.post('api/mantenimiento/repuesto.php', {
      accion: 'agregar', mantenimiento_id: m.id,
      descripcion: desc,
      cantidad: $('#nr-cant').val() || 1,
      costo_unitario: $('#nr-cost').val() || 0,
      proveedor_id: $('#nr-prov').val() || ''
    }).then(() => Mantenimiento._refrescar(m.id))
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) Toast.warning(Object.values(err.cuerpo.errors)[0]);
      });
  },

  _subirEvidencia(m) {
    if (!$('#ne-file')[0].files.length) { Toast.warning('Seleccione el archivo.'); return; }
    const fd = new FormData();
    fd.append('accion', 'subir');
    fd.append('mantenimiento_id', m.id);
    fd.append('tipo', $('#ne-tipo').val());
    fd.append('descripcion', $('#ne-desc').val());
    fd.append('archivo', $('#ne-file')[0].files[0]);

    $.ajax({
      url: BASE_URL + 'api/mantenimiento/evidencia.php',
      type: 'POST', data: fd, contentType: false, processData: false,
      headers: { 'X-CSRF-Token': CSRF.token },
      beforeSend: () => Loader.show(), complete: () => Loader.hide(),
      success: res => {
        if (res.success) { Toast.success(res.message); Mantenimiento._refrescar(m.id); }
        else Toast.error(res.message);
      },
      error: () => Toast.error('Error de conexión al subir la evidencia.')
    });
  },

  // ================= PREVENTIVOS PROGRAMADOS =================
  _abrirProg(progId) {
    Promise.all([
      Api.get('api/equipos/select.php', { modo: 'activos' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'tecnicos' }).catch(() => []),
      progId ? Api.get('api/mantenimiento/programados.php').catch(() => null) : Promise.resolve(null)
    ]).then(([equipos, tecnicos, todos]) => {
      const p = (todos || []).find(x => x.id === progId) || null;

      const html = `
      <form id="form-prog" data-id="${p ? p.id : 0}">
        <div class="row">
          <div class="col-md-6 mb-3" data-campo="equipo_id">
            <label class="form-label">Equipo <span class="text-danger">*</span></label>
            <select class="form-select" id="pg-equipo">
              <option value="">— Seleccione —</option>
              ${equipos.map(e => `<option value="${e.id}" ${p && +p.equipo_id === +e.id ? 'selected' : ''}>${esc(e.texto)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-3 mb-3" data-campo="tipo">
            <label class="form-label">Tipo <span class="text-danger">*</span></label>
            <select class="form-select" id="pg-tipo">
              <option value="preventivo" ${p && p.tipo === 'preventivo' ? 'selected' : ''}>Preventivo</option>
              <option value="limpieza" ${p && p.tipo === 'limpieza' ? 'selected' : ''}>Limpieza</option>
              <option value="actualizacion" ${p && p.tipo === 'actualizacion' ? 'selected' : ''}>Actualización</option>
            </select>
          </div>
          <div class="col-md-3 mb-3" data-campo="frecuencia">
            <label class="form-label">Frecuencia <span class="text-danger">*</span></label>
            <select class="form-select" id="pg-frec">
              <option value="mensual" ${p && p.frecuencia === 'mensual' ? 'selected' : ''}>Mensual</option>
              <option value="trimestral" ${p && p.frecuencia === 'trimestral' ? 'selected' : ''}>Trimestral</option>
              <option value="semestral" ${p && (!p.frecuencia || p.frecuencia === 'semestral') ? 'selected' : ''}>Semestral</option>
              <option value="anual" ${p && p.frecuencia === 'anual' ? 'selected' : ''}>Anual</option>
            </select>
          </div>
          <div class="col-md-4 mb-3" data-campo="proxima_fecha">
            <label class="form-label">Próxima fecha <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="pg-proxima" value="${p ? esc(p.proxima_fecha) : ''}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Responsable</label>
            <select class="form-select" id="pg-resp">
              <option value="">— Sin asignar —</option>
              ${tecnicos.map(t => `<option value="${t.id}" ${p && +p.responsable_id === +t.id ? 'selected' : ''}>${esc(t.texto)}</option>`).join('')}
            </select>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="pg-guardar">Guardar programación</button>
        </div>
      </form>`;

      Modal.abrir(p ? 'Editar programación' : 'Programar mantenimiento preventivo', html, 'modal-lg');

      $('#pg-guardar').on('click', () => {
        Api.post('api/mantenimiento/programados.php', {
          accion: 'guardar',
          id: $('#form-prog').data('id'),
          equipo_id: $('#pg-equipo').val() || '',
          tipo: $('#pg-tipo').val() || '',
          frecuencia: $('#pg-frec').val() || '',
          proxima_fecha: $('#pg-proxima').val() || '',
          responsable_id: $('#pg-resp').val() || ''
        }).then(() => { Modal.cerrar(); Router.ir('mantenimiento/lista'); })
          .catch(err => {
            if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-prog', err.cuerpo.errors);
          });
      });
    });
  },

  _ejecutarProg(id, equipo) {
    Swal.fire({
      title: '¿Ejecutar el preventivo ahora?',
      html: `Se generará el mantenimiento de <b>${esc(equipo)}</b> y se reprogramará la próxima fecha automáticamente.`,
      icon: 'question', showCancelButton: true,
      confirmButtonText: 'Sí, ejecutar', cancelButtonText: 'Cancelar', confirmButtonColor: '#2563eb'
    }).then(r => {
      if (!r.isConfirmed) return;
      Api.post('api/mantenimiento/programados.php', { accion: 'ejecutar', id: id })
        .then(data => {
          Toast.success(data.message || 'Generado');
          Router.ir('mantenimiento/lista');
        });
    });
  }
};

// datalist de proveedores para el repuesto rapido (se carga al abrir detalle)
 $(document).on('shown.bs.modal', '#modal-general', () => {
  if ($('#nr-prov').length) {
    Api.get('api/catalogos/select.php', { tipo: 'proveedores' }).then(list => {
      $('#nr-prov').html('<option value="">Proveedor</option>' +
        list.map(p => '<option value="' + p.id + '">' + esc(p.texto) + '</option>').join(''));
    }).catch(() => {});
  }
});

App.registrar('mantenimiento', Mantenimiento);