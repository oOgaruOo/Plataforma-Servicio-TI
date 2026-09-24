// ============================================================
// SIGTI - Modulo Equipos v3: inventario + seleccion multiple
// + etiquetas con CODIGO DE BARRAS (JsBarcode)
// ============================================================

const ESTADOS_EQUIPO = {
  en_stock:             { txt: 'En stock',        cls: 'text-bg-success' },
  asignado:             { txt: 'Asignado',        cls: 'text-bg-primary' },
  en_prestamo:          { txt: 'Prestamo',        cls: 'text-bg-info' },
  en_revision:          { txt: 'En revision',     cls: 'text-bg-warning text-dark' },
  en_mantenimiento:     { txt: 'Mantenimiento',   cls: 'text-bg-warning text-dark' },
  en_reparacion_externa:{ txt: 'Repar. externa',  cls: 'text-bg-warning text-dark' },
  obsoleto:             { txt: 'Obsoleto',        cls: 'text-bg-secondary' },
  dado_de_baja:         { txt: 'De baja',         cls: 'text-bg-dark' }
};

const CONDICIONES_EQ = {
  nuevo: 'Nuevo', bueno: 'Bueno', regular: 'Regular',
  danado: 'Danado', irreparable: 'Irreparable'
};

const FAMILIAS = {
  computo:   { nombre: 'Equipos de computo', campos: [
    { k:'cpu', label:'Procesador', ph:'Ej: Core i5-1235U' },
    { k:'ram', label:'Memoria RAM', ph:'Ej: 16 GB DDR4' },
    { k:'disco', label:'Disco / almacenamiento', ph:'Ej: SSD 512 GB NVMe' },
    { k:'so', label:'Sistema operativo', ph:'Ej: Windows 11 Pro' },
    { k:'grafica', label:'Tarjeta grafica', ph:'Ej: Integrada / RTX 2050' } ] },
  movil:     { nombre: 'Equipos moviles', campos: [
    { k:'so', label:'Sistema operativo', ph:'Ej: Android 14' },
    { k:'almacenamiento', label:'Almacenamiento', ph:'Ej: 128 GB' },
    { k:'pantalla', label:'Pantalla', ph:'Ej: 6.5 AMOLED' },
    { k:'linea', label:'Linea telefonica', ph:'Ej: 987654321' },
    { k:'plan', label:'Plan de datos', ph:'Ej: Postpago 20GB' } ] },
  impresion: { nombre: 'Impresion e imagen', campos: [
    { k:'tecnologia', label:'Tecnologia', tipo:'select', opts:['Laser','Inyeccion de tinta','Matricial','Termica','LED'] },
    { k:'color', label:'Impresion a color', tipo:'select', opts:['No (B/N)','Si'] },
    { k:'conexion', label:'Conexion', tipo:'select', opts:['USB','Red (Ethernet)','WiFi','USB + Red','USB + WiFi'] },
    { k:'consumible', label:'Consumible / repuesto', ph:'Ej: Toner HP 106A' },
    { k:'rendimiento', label:'Rendimiento mensual', ph:'Ej: 1500 paginas' },
    { k:'duplex', label:'Impresion duplex', tipo:'select', opts:['No','Si'] } ] },
  red:       { nombre: 'Equipos de red', campos: [
    { k:'puertos', label:'Puertos', ph:'Ej: 8 puertos RJ45' },
    { k:'velocidad', label:'Velocidad', ph:'Ej: Gigabit' },
    { k:'ip_gestion', label:'IP de gestion', ph:'Ej: 192.168.1.1' },
    { k:'banda', label:'Banda WiFi', ph:'Ej: 2.4 / 5 GHz' },
    { k:'rack', label:'Montable en rack', tipo:'select', opts:['No','Si'] } ] },
  energia:   { nombre: 'Energia electrica', campos: [
    { k:'capacidad_va', label:'Capacidad (VA)', ph:'Ej: 750 VA' },
    { k:'potencia_w', label:'Potencia (W)', ph:'Ej: 500 W' },
    { k:'baterias', label:'Baterias', ph:'Ej: 1 bateria 12V 9Ah' },
    { k:'tomas', label:'Tomacorrientes', ph:'Ej: 6 tomas' } ] },
  visual:    { nombre: 'Visualizacion', campos: [
    { k:'pulgadas', label:'Pulgadas', ph:'Ej: 24 pulgadas' },
    { k:'resolucion', label:'Resolucion', ph:'Ej: 1920x1080' },
    { k:'panel', label:'Panel / tecnologia', tipo:'select', opts:['IPS','VA','TN','OLED','DLP','LCD','LED'] },
    { k:'video', label:'Conexion de video', ph:'Ej: HDMI + VGA' } ] },
  periferico:{ nombre: 'Perifericos', campos: [
    { k:'conexion_p', label:'Conexion', tipo:'select', opts:['USB','Inalambrico','Bluetooth','PS/2','Jack 3.5 mm'] } ] },
  telefonia: { nombre: 'Telefonia', campos: [
    { k:'extension', label:'Extension / linea', ph:'Ej: 101' },
    { k:'ip_tel', label:'IP del telefono', ph:'Ej: 192.168.1.50' },
    { k:'canales', label:'Canales / lineas', ph:'Ej: 2' } ] },
  otro:      { nombre: 'Otros equipos TI', campos: [
    { k:'descripcion_tecnica', label:'Descripcion tecnica', ph:'Ej: Camara IP 4MP dome POE' } ] }
};

const EQUIPOS_ = { _specs: {}, _accSel: [] };

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
        { data: null, orderable: false, className: 'text-center',
          render: (v, t, f) => '<input type="checkbox" class="form-check-input chk-etq" data-id="' + f.id + '">' },
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
            h += '<button class="btn btn-sm btn-outline-dark btn-etiqueta" title="Etiqueta con código de barras"><i class="bi bi-upc-scan"></i></button>';
            return h;
          } }
      ]);

    this._cargarStats();

    $('#btn-filtrar-equipos').on('click', () => { this.tabla.draw(); this._cargarStats(); });
    $('#ef-estado, #ef-tipo, #ef-garantia').on('change', () => { this.tabla.draw(); this._cargarStats(); });
    $('#btn-limpiar-equipos').on('click', () => {
      $('#ef-estado').val(''); $('#ef-tipo').val(''); $('#ef-garantia').val('');
      this.tabla.draw(); this._cargarStats();
    });

    $('#btn-nuevo-equipo').on('click', () => this._abrirForm(null));

    // ---- etiquetas: seleccion multiple ----
    $('#btn-etiquetas-lote').on('click', () => this._etiquetasLote());
    $('#tb-equipos').on('change', 'thead .chk-todos', function () {
      const marcado = $(this).is(':checked');
      $('#tb-equipos tbody .chk-etq').prop('checked', marcado);
      Equipos._contarSel();
    });
    $('#tb-equipos').on('change', '.chk-etq', () => this._contarSel());

    $('#tb-equipos').on('click', '.btn-ver, .btn-editar, .btn-baja, .btn-etiqueta', function () {
      const fila = Equipos.tabla.row($(this).closest('tr')).data();
      if (!fila) return;
      if      ($(this).hasClass('btn-ver'))      Equipos._verDetalle(fila.id);
      else if ($(this).hasClass('btn-editar'))   Equipos._abrirForm(fila);
      else if ($(this).hasClass('btn-baja'))     Equipos._abrirBaja(fila);
      else                                        Equipos._etiquetaIndividual(fila);
    });
  },

  _contarSel() {
    const n = $('#tb-equipos tbody .chk-etq:checked').length;
    $('#btn-etiquetas-lote').prop('disabled', n === 0)
      .html('<i class="bi bi-upc-scan"></i> Etiquetas (' + n + ')');
  },

  _etiquetasLote() {
    const ids = $('#tb-equipos tbody .chk-etq:checked').map((i, el) => $(el).data('id')).get();
    if (!ids.length) { Toast.warning('Seleccione equipos con los checkboxes.'); return; }
    window.open(BASE_URL + 'views/equipos/etiquetas.php?ids=' + ids.join(','), '_blank');
  },

  _etiquetaIndividual(fila) {
    window.open(BASE_URL + 'views/equipos/etiquetas.php?ids=' + fila.id, '_blank');
  },

  _cargarStats() {
    Api.get('api/equipos/stats.php', { tipo: $('#ef-tipo').val() || 0 }).then(s => {
      $('#equipos-stats [data-k]').each(function () {
        const k = $(this).data('k');
        $(this).text(k === 'valorizado' ? 'S/ ' + Number(s[k]).toLocaleString('es-PE') : s[k]);
      });
    });
  },

  // ================= FICHA =================
  _verDetalle(id) {
    Api.get('api/equipos/detalle.php', { id: id }).then(e => {

      const est = ESTADOS_EQUIPO[e.estado] || { txt: e.estado, cls: 'text-bg-light' };
      const specs = e.especificaciones ? JSON.parse(e.especificaciones) : {};
      const fam = FAMILIAS[e.familia] || FAMILIAS.otro;
      const puedeEditar = App.permisos.includes('equipos.editar') && e.estado !== 'dado_de_baja';

      let specsHtml = '';
      Object.keys(specs).forEach(k => {
        const def = fam.campos.find(c => c.k === k);
        const label = def ? def.label : k.replace(/_/g, ' ');
        specsHtml += '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center">' +
          '<div class="text-muted small">' + esc(label) + '</div>' +
          '<strong class="small">' + esc(specs[k]) + '</strong></div></div>';
      });

      let html = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <h5 class="mb-1">${esc(e.codigo)} <span class="text-muted small">· ${esc(e.tipo)}</span></h5>
          <div class="text-muted small">${esc(e.marca)} ${esc(e.modelo || '')} ${e.nro_serie ? '· S/N ' + esc(e.nro_serie) : ''}
            <span class="badge text-bg-light border ms-1">${esc(fam.nombre)}</span></div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-dark" id="ficha-btn-etiqueta" title="Imprimir etiqueta con código de barras">
            <i class="bi bi-upc-scan"></i> Etiqueta</button>
          <span class="badge ${est.cls} badge-estado-personal">${est.txt}</span>
        </div>
      </div>

      ${e.asignacion ? `
        <div class="alert ${e.asignacion.estado === 'vencida' ? 'alert-warning' : 'alert-primary'} py-2 small">
          <i class="bi bi-person-check"></i>
          <b>${e.asignacion.tipo === 'prestamo' ? 'En prestamo' : 'Asignado'}</b> a
          <b>${esc(e.asignacion.p_nombres ? e.asignacion.p_nombres + ' ' + e.asignacion.p_apellidos : (e.asignacion.area_nombre || '—'))}</b>
          desde ${esc(e.asignacion.fecha_entrega || '')}
        </div>` : ''}
      ${e.estado === 'en_stock' ? '<div class="alert alert-success py-2 small mb-2"><i class="bi bi-check-circle"></i> Disponible en stock para asignacion.</div>' : ''}

      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Condicion</div><strong>${esc(CONDICIONES_EQ[e.condicion] || '')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Compra</div><strong>${esc(e.fecha_compra || '—')}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Costo</div><strong>${e.costo ? 'S/ ' + Number(e.costo).toLocaleString('es-PE') : '—'}</strong></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Garantia</div><strong>${esc(e.garantia_hasta || '—')}</strong></div></div>
        ${specsHtml}
        ${e.imei ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">IMEI</div><strong class="small">' + esc(e.imei) + '</strong></div></div>' : ''}
        ${e.mac  ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">MAC</div><strong class="small">' + esc(e.mac) + '</strong></div></div>' : ''}
        ${e.activo_fijo ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Activo fijo</div><strong class="small">' + esc(e.activo_fijo) + '</strong></div></div>' : ''}
        ${e.ubicacion  ? '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="text-muted small">Ubicacion</div><strong class="small">' + esc(e.ubicacion) + '</strong></div></div>' : ''}
      </div>

      ${e.vecesMant > 0 ? `
      <div class="alert ${e.costo_mant_total > (e.costo || 0) ? 'alert-danger' : 'alert-light border'} py-2 small">
        <i class="bi bi-tools"></i> Mantenimientos: <b>${e.vecesMant}</b> ·
        Costo acumulado: <b>S/ ${Number(e.costo_mant_total).toLocaleString('es-PE')}</b>
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

      $('#ficha-btn-etiqueta').on('click', () => this._etiquetaIndividual(e));

      if (puedeEditar) this._bindearGestionDetalle(e.id, e.familia);
    });
  },

  _htmlAccesorios(e) {
    let h = '<table class="table tabla-mini table-sm table-bordered">';
    h += '<thead><tr><th>Accesorio</th><th>Estado</th><th class="text-center" style="width:90px"></th></tr></thead><tbody>';
    if (!e.accesorios.length) h += '<tr><td colspan="3" class="text-muted text-center">Sin accesorios registrados</td></tr>';
    e.accesorios.forEach(a => {
      h += `<tr data-acc="${a.id}">
        <td>${esc(a.nombre)}</td>
        <td>${+a.entregado === 1 ? '<span class="badge text-bg-info">Entregado</span>' : '<span class="badge text-bg-light border">En caja</span>'}</td>
        <td class="text-center">${App.permisos.includes('equipos.editar') && +a.entregado === 0 ? '<button class="btn btn-sm btn-outline-danger btn-quitar-acc" title="Quitar"><i class="bi bi-x-lg"></i></button>' : '—'}</td>
      </tr>`;
    });
    h += '</tbody></table>';
    if (App.permisos.includes('equipos.editar') && e.estado !== 'dado_de_baja') {
      h += `<div class="fila-dinamica mt-2">
        <input type="text" class="form-control form-control-sm" id="nuevo-acc" maxlength="80"
               list="dl-accesorios" placeholder="Escriba o seleccione del catalogo...">
        <datalist id="dl-accesorios"></datalist>
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
          <option value="perpetua">Perpetua</option><option value="suscripcion">Suscripcion</option><option value="oem">OEM</option></select></div>
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
    if (!e.historial.length) return '<div class="text-muted small p-2">Sin eventos aun.</div>';
    return '<div class="timeline">' + e.historial.map(h => {
      const cls = h.evento === 'baja' ? 'tl-baja' : (h.evento === 'creado' ? 'tl-ok' : '');
      return `<div class="tl-item ${cls}">
        <div class="tl-titulo">${esc(h.evento.replace(/_/g, ' '))} ${h.usuario ? '<span class="text-muted small">· ' + esc(h.usuario) + '</span>' : ''}</div>
        <div class="tl-detalle">${esc(h.detalle || '')}</div>
        <div class="tl-fecha">${esc(h.created_at)}</div>
      </div>`;
    }).join('') + '</div>';
  },

  _bindearGestionDetalle(equipoId, familia) {
    if (familia) {
      Api.get('api/equipos/accesorios-tipos.php', { familia: familia }).then(lista => {
        $('#dl-accesorios').html(lista.map(a => '<option value="' + esc(a.texto) + '">').join(''));
      });
    }

    $('#btn-agregar-acc').on('click', () => {
      const nombre = $.trim($('#nuevo-acc').val() || '');
      if (!nombre) { Toast.warning('Escriba o seleccione un accesorio.'); return; }
      Api.post('api/equipos/accesorio.php', { accion: 'agregar', equipo_id: equipoId, nombre: nombre })
        .then(() => this._refrescarDetalle(equipoId));
    });
    $('#modal-general-cuerpo').off('click', '.btn-quitar-acc')
      .on('click', '.btn-quitar-acc', function () {
        Api.post('api/equipos/accesorio.php', { accion: 'quitar', equipo_id: equipoId, accesorio_id: $(this).closest('tr').data('acc') })
          .then(() => Equipos._refrescarDetalle(equipoId));
      });

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
    const activa = $('#modal-general-cuerpo .nav-tabs .nav-link.active').data('bs-target') || '#eq-tab-acc';
    this._verDetalle(id);
    setTimeout(() => {
      $('#modal-general-cuerpo .nav-tabs .nav-link[data-bs-target="' + activa + '"]').tab('show');
    }, 250);
  },

  // ================= FORMULARIO ADAPTATIVO =================
  _abrirForm(fila) {
    const esNuevo = !fila;

    Api.get('api/catalogos/select.php', { tipo: 'tipos' }).then(tipos => {
      const promesaDet = esNuevo
        ? Promise.resolve(null)
        : Api.get('api/equipos/detalle.php', { id: fila.id });

      promesaDet.then(det => {

        const val = c => esNuevo ? '' : (fila[c] ?? '');
        EQUIPOS_._specs = (det && det.especificaciones) ? JSON.parse(det.especificaciones) : {};
        EQUIPOS_._accSel = (det && det.accesorios) ? det.accesorios.map(a => a.nombre) : [];

        const estadoBloqueado = !esNuevo && ['asignado','en_prestamo','en_mantenimiento','en_reparacion_externa','dado_de_baja'].includes(fila.estado);

        const html = `
        <form id="form-equipo" autocomplete="off" data-id="${esNuevo ? 0 : fila.id}">
          <div class="row">
            <div class="col-md-4 mb-3" data-campo="tipo_equipo_id">
              <label class="form-label">Tipo de equipo <span class="text-danger">*</span></label>
              <select class="form-select" id="fe-tipo">
                ${tipos.map(t => `<option value="${t.id}" data-familia="${esc(t.familia)}"
                  ${+val('tipo_equipo_id') === +t.id ? 'selected' : ''}>${esc(t.texto)}</option>`).join('')}
              </select>
              <div class="form-text">El formulario se adapta automaticamente al tipo.</div>
            </div>
            <div class="col-md-4 mb-3" data-campo="marca">
              <label class="form-label">Marca <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="fe-marca" value="${esc(val('marca'))}" maxlength="60">
            </div>
            <div class="col-md-4 mb-3" data-campo="modelo">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control" id="fe-modelo" value="${esc(val('modelo'))}" maxlength="80">
            </div>

            <div class="col-md-3 mb-3" data-campo="nro_serie">
              <label class="form-label">N de serie</label>
              <input type="text" class="form-control" id="fe-serie" value="${esc(val('nro_serie'))}" maxlength="80">
            </div>
            <div class="col-md-3 mb-3" data-campo="activo_fijo">
              <label class="form-label">Activo fijo (contable)</label>
              <input type="text" class="form-control" id="fe-af" value="${esc(val('activo_fijo'))}" maxlength="40">
            </div>
            <div class="col-md-3 mb-3" data-campo="imei">
              <label class="form-label">IMEI (moviles)</label>
              <input type="text" class="form-control" id="fe-imei" value="${esc(val('imei'))}" maxlength="20">
            </div>
            <div class="col-md-3 mb-3" data-campo="mac">
              <label class="form-label">MAC address</label>
              <input type="text" class="form-control" id="fe-mac" value="${esc(val('mac'))}" maxlength="20">
            </div>

            <div class="col-md-3 mb-3" data-campo="condicion">
              <label class="form-label">Condicion <span class="text-danger">*</span></label>
              <select class="form-select" id="fe-condicion">
                ${Object.entries(CONDICIONES_EQ).map(([k, t]) =>
                  `<option value="${k}" ${val('condicion') === k || (esNuevo && k === 'nuevo') ? 'selected' : ''}>${t}</option>`).join('')}
              </select>
            </div>
            <div class="col-md-3 mb-3" data-campo="estado">
              <label class="form-label">Estado</label>
              ${esNuevo
                ? '<input type="text" class="form-control" value="En stock (automatico)" disabled>'
                : `<select class="form-select" id="fe-estado" ${estadoBloqueado ? 'disabled' : ''}>
                     <option value="en_stock"    ${val('estado') === 'en_stock'    ? 'selected' : ''}>En stock</option>
                     <option value="en_revision" ${val('estado') === 'en_revision' ? 'selected' : ''}>En revision</option>
                     <option value="obsoleto"    ${val('estado') === 'obsoleto'    ? 'selected' : ''}>Obsoleto</option>
                   </select>`}
            </div>
            <div class="col-md-3 mb-3" data-campo="fecha_compra">
              <label class="form-label">Fecha de compra</label>
              <input type="date" class="form-control" id="fe-fcompra" value="${esc(val('fecha_compra'))}">
            </div>
            <div class="col-md-3 mb-3" data-campo="garantia_hasta">
              <label class="form-label">Garantia hasta</label>
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
              <label class="form-label">Ubicacion</label>
              <input type="text" class="form-control" id="fe-ubicacion" value="${esc(val('ubicacion'))}" maxlength="100">
            </div>
          </div>

          <div class="acc-seccion">
            <div class="form-label mb-2">
              <i class="bi bi-cpu me-1"></i>Especificaciones tecnicas
              <span class="badge text-bg-primary ms-1" id="fe-fam-nombre"></span>
            </div>
            <div class="row" id="fe-specs"></div>
          </div>

          <div class="acc-seccion">
            <div class="form-label mb-2">
              <i class="bi bi-plug me-1"></i>Accesorios que incluye
              <span class="text-muted small">(marque los aplicables — catalogo segun tipo de equipo)</span>
            </div>
            <div id="fe-acc-cat"></div>
            <div class="fila-dinamica mt-1">
              <input type="text" class="form-control form-control-sm" id="fe-acc-otro" maxlength="80" placeholder="Otro accesorio no listado">
              <button type="button" class="btn btn-outline-primary btn-sm nowrap" id="btn-acc-otro"><i class="bi bi-plus-lg"></i></button>
            </div>
            <div id="fe-acc-libres"></div>
          </div>

          <div class="col-12 mb-2" data-campo="observaciones">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" id="fe-obs" rows="2" maxlength="500">${esc(val('observaciones'))}</textarea>
          </div>

          ${esNuevo ? '<div class="alert alert-info py-2 small"><i class="bi bi-magic"></i> El codigo <b>EQ-AAAAA-#####</b> se genera automaticamente. Al terminar podra imprimir su <b>etiqueta con codigo de barras</b> desde la ficha.</div>' : ''}

          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btn-guardar-equipo"><i class="bi bi-check-lg"></i> ${esNuevo ? 'Registrar equipo' : 'Guardar cambios'}</button>
          </div>
        </form>`;

        Modal.abrir(esNuevo ? 'Registrar equipo en inventario' : 'Editar — ' + fila.codigo, html, 'modal-xl');

        this._renderFamilia($('#fe-tipo option:selected').data('familia') || 'otro');
        $('#fe-tipo').on('change', () => {
          this._renderFamilia($('#fe-tipo option:selected').data('familia') || 'otro');
        });

        const agregarLibre = () => {
          const n = $.trim($('#fe-acc-otro').val() || '');
          if (!n) return;
          if (EQUIPOS_._accSel.includes(n)) { Toast.warning('Ese accesorio ya esta agregado.'); return; }
          EQUIPOS_._accSel.push(n);
          $('#fe-acc-otro').val('');
          this._renderAccLibres();
        };
        $('#btn-acc-otro').on('click', agregarLibre);
        $('#fe-acc-otro').on('keyup', e => { if (e.key === 'Enter') { e.preventDefault(); agregarLibre(); } });

        $('#btn-guardar-equipo').on('click', () => this._guardar());
        $('#form-equipo').on('submit', e => { e.preventDefault(); this._guardar(); });
      });
    });
  },

  _renderFamilia(familia) {
    const fam = FAMILIAS[familia] || FAMILIAS.otro;
    $('#fe-fam-nombre').text(fam.nombre);

    let html = '';
    fam.campos.forEach(c => {
      const valor = EQUIPOS_._specs[c.k] || '';
      let control;
      if (c.tipo === 'select') {
        control = '<select class="form-select" data-k="' + c.k + '">' +
          '<option value="">—</option>' +
          c.opts.map(o => '<option value="' + esc(o) + '"' + (valor === o ? ' selected' : '') + '>' + esc(o) + '</option>').join('') +
          '</select>';
      } else {
        control = '<input type="text" class="form-control" data-k="' + c.k + '" value="' + esc(valor) +
                  '" maxlength="120" placeholder="' + esc(c.ph || '') + '">';
      }
      html += '<div class="col-md-4 mb-3"><label class="form-label">' + esc(c.label) + '</label>' + control + '</div>';
    });
    if (!fam.campos.length) {
      html = '<div class="col-12 text-muted small">Este tipo no requiere especificaciones adicionales.</div>';
    }
    $('#fe-specs').html(html);

    Api.get('api/equipos/accesorios-tipos.php', { familia: familia }).then(lista => {
      let accHtml = '';
      lista.forEach(a => {
        const marcado = EQUIPOS_._accSel.includes(a.texto);
        accHtml += '<label class="acc-check' + (marcado ? ' marcado' : '') +
          '" data-nombre="' + esc(a.texto) + '">' +
          '<input type="checkbox" ' + (marcado ? 'checked' : '') + '> ' + esc(a.texto) +
          (a.familia === 'general' ? ' <span class="text-muted small">(general)</span>' : '') +
          '</label>';
      });
      $('#fe-acc-cat').html(accHtml || '<div class="text-muted small mb-2">Sin accesorios tipicos para esta familia.</div>');

      $('#fe-acc-cat').off('change', 'input').on('change', 'input', function () {
        const $lab = $(this).closest('.acc-check');
        const nombre = $lab.data('nombre');
        if ($(this).is(':checked')) {
          $lab.addClass('marcado');
          if (!EQUIPOS_._accSel.includes(nombre)) EQUIPOS_._accSel.push(nombre);
        } else {
          $lab.removeClass('marcado');
          EQUIPOS_._accSel = EQUIPOS_._accSel.filter(n => n !== nombre);
        }
      });
    });

    this._renderAccLibres();
  },

  _renderAccLibres() {
    const catalogo = $('#fe-acc-cat .acc-check').map((i, el) => $(el).data('nombre')).get();
    const libres = EQUIPOS_._accSel.filter(n => !catalogo.includes(n));
    $('#fe-acc-libres').html(libres.map(n =>
      '<span class="acc-chip">' + esc(n) +
      ' <button type="button" data-quitar="' + esc(n) + '">&times;</button></span>'
    ).join(''));
    $('#fe-acc-libres').off('click', 'button').on('click', 'button', function () {
      const q = $(this).data('quitar');
      EQUIPOS_._accSel = EQUIPOS_._accSel.filter(n => n !== q);
      Equipos._renderAccLibres();
      $('#fe-acc-cat .acc-check[data-nombre="' + q + '"]').removeClass('marcado').find('input').prop('checked', false);
    });
  },

  _guardar() {
    const specs = {};
    $('#fe-specs [data-k]').each(function () {
      const v = $.trim($(this).val() || '');
      if (v !== '') specs[$(this).data('k')] = v;
    });

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
      observaciones:   $.trim($('#fe-obs').val() || ''),
      especificaciones: JSON.stringify(specs),
      accesorios:      JSON.stringify(EQUIPOS_._accSel.map(n => ({ nombre: n })))
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
        Se dara de baja al equipo <b>${esc(fila.codigo)}</b> (${esc(fila.tipo)} ${esc(fila.marca)}).
        Accion <b>definitiva</b>: queda como historico.
      </div>
      <div class="mb-3" data-campo="motivo">
        <label class="form-label">Motivo de la baja <span class="text-danger">*</span></label>
        <select class="form-select" id="fb-motivo">
          <option value="">— Seleccione —</option>
          <option value="obsolescencia">Obsolescencia</option>
          <option value="danado_irreparable">Danado irreparable</option>
          <option value="robo">Robo</option>
          <option value="venta">Venta / disposicion</option>
          <option value="perdida">Perdida</option>
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
          <input type="text" class="form-control" id="fb-destino" maxlength="100">
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
        id: fila.id,
        motivo: $('#fb-motivo').val() || '',
        fecha: $('#fb-fecha').val() || '',
        destino: $.trim($('#fb-destino').val() || ''),
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