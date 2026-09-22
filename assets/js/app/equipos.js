// ============================================================
// SIGTI - Modulo Equipos / Inventario
// Reusa: esc(), Badges, pintarErroresForm(), DT, Modal, Api
// ============================================================

const ESTADOS_EQUIPO = {
  en_stock:             { txt: 'En stock',        cls: 'text-bg-success' },
  asignado:             { txt: 'Asignado',        cls: 'text-bg-primary' },
  en_prestamo:          { txt: 'Préstamo',        cls: 'text-bg-info' },
  en_revision:          { txt: 'En revisión',     cls: 'text-bg-warning text-dark' },
  en_mantenimiento:     { txt: 'Mantenimiento',   cls: 'text-bg-warning text-dark' },
  en_reparacion_externa:{ txt: 'Repar. externa',  cls: 'text-bg-warning text-dark' },
  obsoleto:             { txt: 'Obsoleto',        cls: 'text-bg-secondary' },
  dado_de_baja:         { txt: 'De baja',         cls: 'text-bg-dark' }
};

const CONDICIONES_EQ = {
  nuevo: 'Nuevo', bueno: 'Bueno', regular: 'Regular',
  danado: 'Dañado', irreparable: 'Irreparable'
};

const Equipos = {

  tabla: null,

  init() {
    this.tabla = DT.server('#tb-equipos', 'api/equipos/listar.php',
      () => ({
        f_estado:   $('#ef-estado').val()  || '',
        f_tipo:     $('#ef-tipo').val()    || 0,
        f_garantia: $('#ef-garantia').val() || 0
      }),
      [
        { data: 'codigo', render: v => '<strong class="nowrap">' + esc(v) + '</strong>' },
        { data: 'tipo' },
        { data: null, render: (v, t, f) => esc(f.marca) + (f.modelo ? ' ' + esc(f.modelo) : '') },
        { data: 'nro_serie', render: v => v ? '<span class="small">' + esc(v) + '</span>' : '<span class="text-muted">—</span>' },
        { data: 'estado', render: v => {
            const e = ESTADOS_EQUIPO[v] || { txt: v, cls: 'text-bg-light' };
            return '<span class="badge ' + e.cls + ' badge-estado-personal nowrap">' + e.txt + '</span>';
          } },
        { data: 'condicion', render: v => {
            const cls = { nuevo:'success', bueno:'success', regular:'warning text-dark', danado:'danger', irreparable:'dark' }[v] || 'light';
            return '<span class="badge text-bg-' + cls + ' badge-estado-personal">' + esc(CONDICIONES_EQ[v] || v) + '</span>';
          } },
        { data: 'asignado_a', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'garantia_hasta', className: 'nowrap',
          render: v => {
            if (!v) return '<span class="text-muted">—</span>';
            const dias = Math.round((new Date(v) - new Date()) / 86400000);
            if (dias < 0)   return '<span class="badge text-bg-danger">Vencida</span>';
            if (dias <= 90) return '<span class="badge text-bg-warning text-dark">' + dias + 'd</span>';
            return esc(v);
          } },
        { data: null, orderable: false, className: 'text-nowrap text-center',
          render: (v, t, f) => {
            const cesado = f.estado === 'dado_de_baja';
            let h = '<button class="btn btn-sm btn-outline-primary btn-ver me-1" title="Ficha completa"><i class="bi bi-eye"></i></button>';
            if (!cesado && App.permisos.includes('equipos.editar'))
              h += '<button class="btn btn-sm btn-outline-secondary btn-editar me-1" title="Editar"><i class="bi bi-pencil"></i></button>';
            if (!cesado && App.permisos.includes('equipos.baja'))
              h += '<button class="btn btn-sm btn-outline-danger btn-baja" title="Dar de baja"><i class="bi bi-trash3"></i></button>';
            return h;
          } }
      ]);

    this._cargarStats();

    // ---- Filtros ----
    $('#btn-filtrar-equipos').on('click', () => { this.tabla.draw(); this._cargarStats(); });
    $('#ef-estado, #ef-tipo, #ef-garantia').on('change', () => { this.tabla.draw(); this._cargarStats(); });
    $('#btn-limpiar-equipos').on('click', () => {
      $('#ef-estado').val(''); $('#ef-tipo').val(''); $('#ef-garantia').val('');
      this.tabla.draw(); this._cargarStats();
    });

    $('#btn-nuevo-equipo').on('click', () => this._abrirForm(null));

    $('#tb-equipos').on('click', '.btn-ver, .btn-editar, .btn-baja', function () {
      const fila = Equipos.tabla.row($(this).closest('tr')).data();
      if (!fila) return;
      if      ($(this).hasClass('btn-ver'))    Equipos._verDetalle(fila.id);
      else if ($(this).hasClass('btn-editar')) Equipos._abrirForm(fila);
      else                                      Equipos._abrirBaja(fila);
    });
  },

  _cargarStats() {
    Api.get('api/equipos/stats.php', { tipo: $('#ef-tipo').val() || 0 }).then(s => {
      $('#equipos-stats [data-k]').each(function () {
        const k = $(this).data('k');
        $(this).text(k === 'valorizado' ? 'S/ ' + Number(s[k]).toLocaleString('es-PE') : s[k]);
      });
    });
  },

  // ================= FICHA COMPLETA =================
  _verDetalle(id) {
    Api.get('api/equipos/detalle.php', { id: id }).then(e => {

      const est = ESTADOS_EQUIPO[e.estado] || { txt: e.estado, cls: 'text-bg-light' };
      const specs = e.especificaciones ? JSON.parse(e.especificaciones) : {};
      const puedeEditar = App.permisos.includes('equipos.editar') && e.estado !== 'dado_de_baja';

      let html = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <h5 class="mb-1">${esc(e.codigo)} <span class="text-muted small">· ${esc(e.tipo)}</span></h5>
          <div class="text-muted small">${esc(e.marca)} ${esc(e.modelo || '')} ${e.nro_serie ? '· S/N ' + esc(e.nro_serie) : ''}</div>
        </div>
        <span class="badge ${est.cls} badge-estado-personal">${est.txt}</span>
      </div>

      ${e.asignacion ? `
        <div class="alert ${e.asignacion.estado === 'vencida' ? 'alert-warning' : 'alert-primary'} py-2 small">
          <i class="bi bi-person-check"></i>
          <b>${e.asignacion.tipo === 'prestamo' ? 'En préstamo' : 'Asignado'}</b> a
          <b>${esc(e.asignacion.p_nombres ? e.asignacion.p_nombres + ' ' + e.asignacion.p_apellidos : (e.asignacion.area_nombre || '—'))}</b>
          desde ${esc(e.asignacion.fecha_entrega || '')}
          ${e.asignacion.fecha_devolucion_esperada ? ' · retorno esperado: ' + esc(e.asignacion.fecha_devolucion_esperada) : ''}
          ${e.asignacion.estado === 'vencida' ? ' · <b>PRÉSTAMO VENCIDO</b>' : ''}
        </div>` : ''}
      ${e.estado === 'en_stock' ? '<div class="alert alert-success py-2 small mb-2"><i class="bi bi-check-circle"></i> Disponible en stock para asignación.</div>' : ''}

      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Condición</div><strong>${esc(CONDICIONES_EQ[e.condicion] || '')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Compra</div><strong>${esc(e.fecha_compra || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Costo</div><strong>${e.costo ? 'S/ ' + Number(e.costo).toLocaleString('es-PE') : '—'}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Garantía</div><strong>${esc(e.garantia_hasta || '—')}</strong></div></div>
        ${specs.cpu   ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">CPU</div><strong class="small">' + esc(specs.cpu) + '</strong></div></div>'   : ''}
        ${specs.ram   ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">RAM</div><strong class="small">' + esc(specs.ram) + '</strong></div></div>'   : ''}
        ${specs.disco ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Disco</div><strong class="small">' + esc(specs.disco) + '</strong></div></div>' : ''}
        ${specs.so    ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">S.O.</div><strong class="small">' + esc(specs.so) + '</strong></div></div>'    : ''}
        ${e.imei ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">IMEI</div><strong class="small">' + esc(e.imei) + '</strong></div></div>' : ''}
        ${e.mac  ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">MAC</div><strong class="small">' + esc(e.mac) + '</strong></div></div>'  : ''}
        ${e.activo_fijo ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Activo fijo</div><strong class="small">' + esc(e.activo_fijo) + '</strong></div></div>' : ''}
        ${e.ubicacion  ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Ubicación</div><strong class="small">' + esc(e.ubicacion) + '</strong></div></div>'  : ''}
      </div>

      ${e.vecesMant > 0 ? `
      <div class="alert ${e.costo_mant_total > (e.costo || 0) ? 'alert-danger' : 'alert-light border'} py-2 small">
        <i class="bi bi-tools"></i> Mantenimientos: <b>${e.vecesMant}</b> ·
        Costo acumulado: <b>S/ ${Number(e.costo_mant_total).toLocaleString('es-PE')}</b>
        ${e.costo ? ' de un equipo de S/ ' + Number(e.costo).toLocaleString('es-PE') : ''}
        ${e.costo_mant_total > (e.costo || 0) ? ' · <b>⚠ El gasto supera el valor del equipo: evaluar renovación</b>' : ''}
      </div>` : ''}

      <ul class="nav nav-tabs mb-2" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#eq-tab-acc" type="button">Accesorios <span class="badge text-bg-light border">${e.accesorios.length}</span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#eq-tab-lic" type="button">Licencias <span class="badge text-bg-light border">${e.licencias.length}</span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#eq-tab-his" type="button">Historial</button></li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="eq-tab-acc">` + this._htmlAccesorios(e) + `</div>
        <div class="tab-pane fade" id="eq-tab-lic">` + this._htmlLicencias(e) + `</div>
        <div class="tab-pane fade" id="eq-tab-his">` + this._htmlHistorial(e) + `</div>
      </div>`;

      Modal.abrir('Ficha del equipo', html, 'modal-xl');

      if (puedeEditar) this._bindearGestionDetalle(e.id);
    });
  },

  _htmlAccesorios(e) {
    let h = '<div class="tabla-mini-wrap"><table class="table tabla-mini table-sm table-bordered">';
    h += '<thead><tr><th>Accesorio</th><th>Estado</th><th class="text-center" style="width:90px"></th></tr></thead><tbody>';
    if (!e.accesorios.length) h += '<tr><td colspan="3" class="text-muted text-center">Sin accesorios registrados</td></tr>';
    e.accesorios.forEach(a => {
      h += `<tr data-acc="${a.id}">
        <td>${esc(a.nombre)}</td>
        <td>${+a.entregado === 1 ? '<span class="badge text-bg-info">Entregado</span>' : '<span class="badge text-bg-light border">En caja</span>'}</td>
        <td class="text-center">${App.permisos.includes('equipos.editar') && +a.entregado === 0 ? '<button class="btn btn-sm btn-outline-danger btn-quitar-acc" title="Quitar"><i class="bi bi-x-lg"></i></button>' : '—'}</td>
      </tr>`;
    });
    h += '</tbody></table></div>';
    if (App.permisos.includes('equipos.editar') && e.estado !== 'dado_de_baja') {
      h += `<div class="fila-dinamica mt-2">
        <input type="text" class="form-control form-control-sm" id="nuevo-acc" maxlength="80" placeholder="Ej: Cargador, maletín, cable HDMI…">
        <button class="btn btn-sm btn-primary nowrap" id="btn-agregar-acc"><i class="bi bi-plus-lg"></i> Agregar</button>
      </div>`;
    }
    return h;
  },

  _htmlLicencias(e) {
    let h = '<table class="table tabla-mini table-sm table-bordered">';
    h += '<thead><tr><th>Software</th><th>Tipo</th><th>Vence</th><th class="text-end">Costo</th><th class="text-center" style="width:90px"></th></tr></thead><tbody>';
    if (!e.licencias.length) h += '<tr><td colspan="5" class="text-muted text-center">Sin licencias registradas</td></tr>';
    e.licencias.forEach(l => {
      let vence = '—';
      if (l.fecha_vencimiento) {
        const dias = Math.round((new Date(l.fecha_vencimiento) - new Date()) / 86400000);
        vence = dias < 0 ? '<span class="badge text-bg-danger">Vencida</span>'
              : dias <= 30 ? '<span class="badge text-bg-warning text-dark">' + l.fecha_vencimiento + '</span>'
              : esc(l.fecha_vencimiento);
      }
      h += `<tr data-lic="${l.id}">
        <td>${esc(l.software)}</td>
        <td><span class="badge text-bg-light border">${esc(l.tipo)}</span></td>
        <td>${vence}</td>
        <td class="text-end">${l.costo ? 'S/ ' + Number(l.costo).toLocaleString('es-PE') : '—'}</td>
        <td class="text-center">${App.permisos.includes('equipos.editar') && e.estado !== 'dado_de_baja' ? '<button class="btn btn-sm btn-outline-danger btn-quitar-lic" title="Quitar"><i class="bi bi-x-lg"></i></button>' : '—'}</td>
      </tr>`;
    });
    h += '</tbody></table>';
    if (App.permisos.includes('equipos.editar') && e.estado !== 'dado_de_baja') {
      h += `
      <div class="row g-2 mt-2">
        <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="nl-software" maxlength="80" placeholder="Software * (Ej: Office 2021)"></div>
        <div class="col-md-2"><select id="nl-tipo" class="form-select form-select-sm">
          <option value="perpetua">Perpetua</option><option value="suscripcion">Suscripción</option><option value="oem">OEM</option></select></div>
        <div class="col-md-2"><input type="date" class="form-control form-control-sm" id="nl-inicio"></div>
        <div class="col-md-2"><input type="date" class="form-control form-control-sm" id="nl-vence"></div>
        <div class="col-md-2 d-flex gap-1">
          <input type="number" class="form-control form-control-sm" id="nl-costo" min="0" step="0.01" placeholder="S/">
          <button class="btn btn-sm btn-primary nowrap" id="btn-agregar-lic"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>`;
    }
    return h;
  },

  _htmlHistorial(e) {
    if (!e.historial.length) return '<div class="text-muted small p-2">Sin eventos aún.</div>';
    return '<div class="timeline">' + e.historial.map(h => {
      const cls = h.evento === 'baja' ? 'tl-baja' : (h.evento === 'creado' ? 'tl-ok' : '');
      return `<div class="tl-item ${cls}">
        <div class="tl-titulo">${esc(h.evento.replace(/_/g, ' '))} ${h.usuario ? '<span class="text-muted small">· ' + esc(h.usuario) + '</span>' : ''}</div>
        <div class="tl-detalle">${esc(h.detalle || '')}</div>
        <div class="tl-fecha">${esc(h.created_at)}</div>
      </div>`;
    }).join('') + '</div>';
  },

  _bindearGestionDetalle(equipoId) {
    // ---- Accesorios ----
    $('#btn-agregar-acc').on('click', () => {
      const nombre = $.trim($('#nuevo-acc').val() || '');
      if (!nombre) { Toast.warning('Escriba el nombre del accesorio.'); return; }
      Api.post('api/equipos/accesorio.php', { accion: 'agregar', equipo_id: equipoId, nombre: nombre })
        .then(() => this._refrescarDetalle(equipoId));
    });
    $('#modal-general-cuerpo').off('click', '.btn-quitar-acc')
      .on('click', '.btn-quitar-acc', function () {
        Api.post('api/equipos/accesorio.php', { accion: 'quitar', equipo_id: equipoId, accesorio_id: $(this).closest('tr').data('acc') })
          .then(() => Equipos._refrescarDetalle(equipoId));
      });

    // ---- Licencias ----
    $('#btn-agregar-lic').on('click', () => {
      const software = $.trim($('#nl-software').val() || '');
      if (!software) { Toast.warning('Indique el nombre del software.'); return; }
      Api.post('api/equipos/licencia.php', {
        accion: 'agregar', equipo_id: equipoId, software: software,
        tipo: $('#nl-tipo').val(), clave: '',
        fecha_inicio: $('#nl-inicio').val() || '',
        fecha_vencimiento: $('#nl-vence').val() || '',
        costo: $('#nl-costo').val() || ''
      }).then(() => this._refrescarDetalle(equipoId));
    });
    $('#modal-general-cuerpo').off('click', '.btn-quitar-lic')
      .on('click', '.btn-quitar-lic', function () {
        Api.post('api/equipos/licencia.php', { accion: 'quitar', equipo_id: equipoId, licencia_id: $(this).closest('tr').data('lic') })
          .then(() => Equipos._refrescarDetalle(equipoId));
      });
  },

  _refrescarDetalle(id) {
    // re-render de la ficha conservando la pestaña activa
    const activa = $('#modal-general-cuerpo .nav-tabs .nav-link.active').data('bs-target') || '#eq-tab-acc';
    this._verDetalle(id);
    setTimeout(() => {
      $('#modal-general-cuerpo .nav-tabs .nav-link[data-bs-target="' + activa + '"]').tab('show');
    }, 250);
  },

  // ================= FORMULARIO ALTA / EDICION =================
  _abrirForm(fila) {
    const esNuevo = !fila;

    Api.get('api/catalogos/select.php', { tipo: 'tipos' }).then(tipos => {
      // si es edición, pedimos la ficha para specs + accesorios actuales
      const promesaDet = esNuevo
        ? Promise.resolve(null)
        : Api.get('api/equipos/detalle.php', { id: fila.id });

      promesaDet.then(det => {

        const val = c => esNuevo ? '' : (fila[c] ?? '');
        const specs = (det && det.especificaciones) ? JSON.parse(det.especificaciones) : {};
        const accesorios = (det && det.accesorios) ? det.accesorios.map(a => a.nombre) : [];

        const estadoBloqueado = !esNuevo && ['asignado','en_prestamo','en_mantenimiento','en_reparacion_externa','dado_de_baja'].includes(fila.estado);

        const html = `
        <form id="form-equipo" autocomplete="off" data-id="${esNuevo ? 0 : fila.id}">
          <div class="row">
            <div class="col-md-4 mb-3" data-campo="tipo_equipo_id">
              <label class="form-label">Tipo <span class="text-danger">*</span></label>
              <select class="form-select" id="fe-tipo">
                ${tipos.map(t => `<option value="${t.id}" ${+val('tipo_equipo_id') === +t.id ? 'selected' : ''}>${esc(t.texto)}</option>`).join('')}
              </select>
            </div>
            <div class="col-md-4 mb-3" data-campo="marca">
              <label class="form-label">Marca <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="fe-marca" value="${esc(val('marca'))}" maxlength="60" placeholder="Ej: HP, Dell, Samsung…">
            </div>
            <div class="col-md-4 mb-3" data-campo="modelo">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control" id="fe-modelo" value="${esc(val('modelo'))}" maxlength="80">
            </div>

            <div class="col-md-3 mb-3" data-campo="nro_serie">
              <label class="form-label">N° de serie</label>
              <input type="text" class="form-control" id="fe-serie" value="${esc(val('nro_serie'))}" maxlength="80">
            </div>
            <div class="col-md-3 mb-3" data-campo="activo_fijo">
              <label class="form-label">Activo fijo (contable)</label>
              <input type="text" class="form-control" id="fe-af" value="${esc(val('activo_fijo'))}" maxlength="40">
            </div>
            <div class="col-md-3 mb-3" data-campo="imei">
              <label class="form-label">IMEI (celulares)</label>
              <input type="text" class="form-control" id="fe-imei" value="${esc(val('imei'))}" maxlength="20">
            </div>
            <div class="col-md-3 mb-3" data-campo="mac">
              <label class="form-label">MAC address</label>
              <input type="text" class="form-control" id="fe-mac" value="${esc(val('mac'))}" maxlength="20">
            </div>

            <div class="col-md-3 mb-3" data-campo="condicion">
              <label class="form-label">Condición <span class="text-danger">*</span></label>
              <select class="form-select" id="fe-condicion">
                ${Object.entries(CONDICIONES_EQ).map(([k, t]) =>
                  `<option value="${k}" ${val('condicion') === k || (esNuevo && k === 'nuevo') ? 'selected' : ''}>${t}</option>`).join('')}
              </select>
            </div>
            <div class="col-md-3 mb-3" data-campo="estado">
              <label class="form-label">Estado</label>
              ${esNuevo
                ? '<input type="text" class="form-control" value="En stock (automático)" disabled>'
                : `<select class="form-select" id="fe-estado" ${estadoBloqueado ? 'disabled' : ''}>
                     <option value="en_stock"    ${val('estado') === 'en_stock'    ? 'selected' : ''}>En stock</option>
                     <option value="en_revision" ${val('estado') === 'en_revision' ? 'selected' : ''}>En revisión</option>
                     <option value="obsoleto"    ${val('estado') === 'obsoleto'    ? 'selected' : ''}>Obsoleto</option>
                   </select>
                   ${estadoBloqueado ? '<div class="form-text">Gestionado por asignaciones/mantenimiento.</div>' : ''}`}
            </div>
            <div class="col-md-3 mb-3" data-campo="fecha_compra">
              <label class="form-label">Fecha de compra</label>
              <input type="date" class="form-control" id="fe-fcompra" value="${esc(val('fecha_compra'))}">
            </div>
            <div class="col-md-3 mb-3" data-campo="garantia_hasta">
              <label class="form-label">Garantía hasta</label>
              <input type="date" class="form-control" id="fe-garantia" value="${esc(val('garantia_hasta'))}">
            </div>

            <div class="col-md-4 mb-3" data-campo="proveedor_compra">
              <label class="form-label">Proveedor de compra</label>
              <input type="text" class="form-control" id="fe-proveedor" value="${esc(val('proveedor_compra'))}" maxlength="100">
            </div>
            <div class="col-md-4 mb-3" data-campo="costo">
              <label class="form-label">Costo (S/)</label>
              <input type="number" class="form-control" id="fe-costo" min="0" step="0.01" value="${esc(val('costo'))}">
            </div>
            <div class="col-md-4 mb-3" data-campo="ubicacion">
              <label class="form-label">Ubicación</label>
              <input type="text" class="form-control" id="fe-ubicacion" value="${esc(val('ubicacion'))}" maxlength="100" placeholder="Ej: Almacén TI / Piso 2">
            </div>

            <div class="col-12"><hr class="my-1"><div class="form-label mb-2 mt-1"><i class="bi bi-cpu me-1"></i>Especificaciones (opcional)</div></div>
            <div class="col-md-3 mb-3" data-campo="cpu">
              <label class="form-label">Procesador</label>
              <input type="text" class="form-control" id="fe-cpu" value="${esc(specs.cpu || '')}" maxlength="80" placeholder="Ej: Core i5 11va">
            </div>
            <div class="col-md-3 mb-3" data-campo="ram">
              <label class="form-label">RAM</label>
              <input type="text" class="form-control" id="fe-ram" value="${esc(specs.ram || '')}" maxlength="40" placeholder="Ej: 16 GB">
            </div>
            <div class="col-md-3 mb-3" data-campo="disco">
              <label class="form-label">Disco</label>
              <input type="text" class="form-control" id="fe-disco" value="${esc(specs.disco || '')}" maxlength="60" placeholder="Ej: SSD 512 GB">
            </div>
            <div class="col-md-3 mb-3" data-campo="so">
              <label class="form-label">Sistema operativo</label>
              <input type="text" class="form-control" id="fe-so" value="${esc(specs.so || '')}" maxlength="80" placeholder="Ej: Windows 11 Pro">
            </div>

            <div class="col-12"><hr class="my-1"><div class="form-label mb-2 mt-1"><i class="bi bi-plug me-1"></i>Accesorios que incluye</div></div>
            <div class="col-12 mb-2" id="fe-accesorios">
              ${accesorios.map(n => this._filaAccesorioForm(n)).join('')}
              ${accesorios.length === 0 ? this._filaAccesorioForm('') : ''}
            </div>
            <div class="col-12 mb-2">
              <button type="button" class="btn btn-sm btn-outline-primary" id="btn-mas-accesorio"><i class="bi bi-plus"></i> Otro accesorio</button>
            </div>

            <div class="col-12 mb-2" data-campo="observaciones">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" id="fe-obs" rows="2" maxlength="500">${esc(val('observaciones'))}</textarea>
            </div>
          </div>

          ${esNuevo ? '<div class="alert alert-info py-2 small"><i class="bi bi-magic"></i> El código <b>EQ-2025-#####</b> se genera automáticamente y el equipo entra en <b>stock</b>. Las licencias de software se gestionan desde la ficha.</div>' : ''}

          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btn-guardar-equipo"><i class="bi bi-check-lg"></i> ${esNuevo ? 'Registrar equipo' : 'Guardar cambios'}</button>
          </div>
        </form>`;

        Modal.abrir(esNuevo ? 'Registrar equipo en inventario' : 'Editar — ' + fila.codigo, html, 'modal-xl');

        $('#btn-mas-accesorio').on('click', () => {
          $('#fe-accesorios').append(this._filaAccesorioForm(''));
        });
        $('#fe-accesorios').on('click', '.btn-quitar-acc-form', function () {
          if ($('#fe-accesorios .fila-acc').length > 1) $(this).closest('.fila-acc').remove();
        });

        $('#btn-guardar-equipo').on('click', () => this._guardar());
        $('#form-equipo').on('submit', e => { e.preventDefault(); this._guardar(); });
      });
    });
  },

  _filaAccesorioForm(nombre) {
    return `
    <div class="fila-dinamica fila-acc">
      <input type="text" class="form-control form-control-sm acc-nombre" maxlength="80" value="${esc(nombre)}" placeholder="Ej: Cargador original, maletín…">
      <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-acc-form"><i class="bi bi-x-lg"></i></button>
    </div>`;
  },

  _guardar() {
    const accesorios = $('#fe-accesorios .acc-nombre')
      .map((i, el) => ({ nombre: $.trim($(el).val() || '') }))
      .get()
      .filter(a => a.nombre !== '');

    const datos = {
      id:              $('#form-equipo').data('id') || 0,
      tipo_equipo_id:  $('#fe-tipo').val() || '',
      marca:           $.trim($('#fe-marca').val() || ''),
      modelo:          $.trim($('#fe-modelo').val() || ''),
      nro_serie:       $.trim($('#fe-serie').val() || ''),
      activo_fijo:     $.trim($('#fe-af').val() || ''),
      imei:            $.trim($('#fe-imei').val() || ''),
      mac:             $.trim($('#fe-mac').val() || ''),
      condicion:       $('#fe-condicion').val() || '',
      estado:          $('#fe-estado').val() || '',
      fecha_compra:    $('#fe-fcompra').val() || '',
      garantia_hasta:  $('#fe-garantia').val() || '',
      proveedor_compra:$.trim($('#fe-proveedor').val() || ''),
      costo:           $('#fe-costo').val() || '',
      ubicacion:       $.trim($('#fe-ubicacion').val() || ''),
      cpu:             $.trim($('#fe-cpu').val() || ''),
      ram:             $.trim($('#fe-ram').val() || ''),
      disco:           $.trim($('#fe-disco').val() || ''),
      so:              $.trim($('#fe-so').val() || ''),
      observaciones:   $.trim($('#fe-obs').val() || ''),
      accesorios:      JSON.stringify(accesorios)
    };

    Api.post('api/equipos/guardar.php', datos)
      .then(data => {
        Modal.cerrar();
        this.tabla.draw();
        this._cargarStats();
        if (data && data.abrir_detalle) {
          setTimeout(() => this._verDetalle(data.id), 350);
        }
      })
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-equipo', err.cuerpo.errors);
      });
  },

  // ================= BAJA =================
  _abrirBaja(fila) {
    const hoy = new Date().toISOString().slice(0, 10);
    const html = `
    <form id="form-baja" autocomplete="off" data-id="${fila.id}">
      <div class="alert alert-danger py-2 small">
        <i class="bi bi-exclamation-triangle"></i>
        Se dará de baja al equipo <b>${esc(fila.codigo)}</b> (${esc(fila.tipo)} ${esc(fila.marca)}).
        Esta acción es <b>definitiva</b>: el equipo queda como histórico y ya no puede asignarse ni editarse.
        Si está asignado, el sistema lo bloqueará.
      </div>
      <div class="mb-3" data-campo="motivo">
        <label class="form-label">Motivo de la baja <span class="text-danger">*</span></label>
        <select class="form-select" id="fb-motivo">
          <option value="">— Seleccione —</option>
          <option value="obsolescencia">Obsolescencia (muy antiguo)</option>
          <option value="danado_irreparable">Dañado irreparable</option>
          <option value="robo">Robo</option>
          <option value="venta">Venta / disposición</option>
          <option value="perdida">Pérdida</option>
          <option value="otros">Otros</option>
        </select>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3" data-campo="fecha">
          <label class="form-label">Fecha de baja <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="fb-fecha" value="${hoy}" required>
        </div>
        <div class="col-md-6 mb-3" data-campo="destino">
          <label class="form-label">Destino</label>
          <input type="text" class="form-control" id="fb-destino" maxlength="100" placeholder="Ej: Almacén de scrap / Venta a proveedor">
        </div>
      </div>
      <div class="mb-3" data-campo="observaciones">
        <label class="form-label">Observaciones</label>
        <textarea class="form-control" id="fb-obs" rows="2" maxlength="300"></textarea>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btn-confirmar-baja"><i class="bi bi-trash3"></i> Confirmar baja</button>
      </div>
    </form>`;

    Modal.abrir('Dar de baja — ' + fila.codigo, html);

    $('#btn-confirmar-baja').on('click', () => {
      const datos = {
        id:            fila.id,
        motivo:        $('#fb-motivo').val() || '',
        fecha:         $('#fb-fecha').val() || '',
        destino:       $.trim($('#fb-destino').val() || ''),
        observaciones: $.trim($('#fb-obs').val() || '')
      };
      Api.post('api/equipos/baja.php', datos)
        .then(() => { Modal.cerrar(); this.tabla.draw(); this._cargarStats(); })
        .catch(err => {
          if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-baja', err.cuerpo.errors);
        });
    });
  }
};

App.registrar('equipos', Equipos);