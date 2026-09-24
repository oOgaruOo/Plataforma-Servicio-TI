// ============================================================
// SIGTI - Importar Excel / Exportar inventario de equipos
// (SheetJS lee el archivo EN EL NAVEGADOR y envia JSON validado)
// ============================================================

(function () {

  // ---------- normalizadores ----------
  const norm = s => String(s || '').toLowerCase().normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9 ]/g, ' ')
    .replace(/\s+/g, ' ').trim();

  const MAPA_COLUMNAS = {
    'tipo de equipo': 'tipo', 'tipo': 'tipo',
    'marca': 'marca', 'modelo': 'modelo',
    'numero de serie': 'serie', 'serie': 'serie', 'n serie': 'serie', 'no de serie': 'serie', 'n de serie': 'serie',
    'codigo activo fijo': 'activo_fijo', 'activo fijo': 'activo_fijo', 'codigo patrimonial': 'activo_fijo',
    'imei': 'imei',
    'mac': 'mac', 'mac address': 'mac', 'direccion mac': 'mac',
    'condicion': 'condicion',
    'fecha compra': 'fecha_compra', 'fecha de compra': 'fecha_compra',
    'proveedor compra': 'proveedor', 'proveedor': 'proveedor', 'proveedor de compra': 'proveedor',
    'costo s': 'costo', 'costo': 'costo', 'costo soles': 'costo',
    'garantia hasta': 'garantia', 'garantia': 'garantia', 'garantia hasta yyyy mm dd': 'garantia',
    'ubicacion': 'ubicacion',
    'procesador': 'cpu', 'cpu': 'cpu',
    'ram': 'ram', 'memoria ram': 'ram',
    'disco': 'disco', 'disco duro': 'disco', 'disco ssd': 'disco', 'almacenamiento': 'disco',
    'sistema operativo': 'so', 'so': 'so',
    'accesorios': 'accesorios', 'accesorios separados por coma': 'accesorios',
  };

  const CONDICIONES_OK = ['nuevo', 'bueno', 'regular', 'danado', 'irreparable'];

  function normFecha(v) {
    v = String(v || '').trim();
    if (!v) return '';
    let m = v.match(/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/);
    if (m) return m[1] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[3]).padStart(2, '0');
    m = v.match(/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/);
    if (m) return m[3] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
    return '';   // no reconocida -> se ignora
  }

  function normCosto(v) {
    v = String(v || '').replace(/s\/?/gi, '').replace(/\s/g, '');
    if (!v) return '';
    if (v.includes(',') && v.includes('.')) v = v.replace(/,/g, '');
    else if (v.includes(',')) v = v.replace(',', '.');
    const n = parseFloat(v);
    return isNaN(n) ? '' : String(n);
  }

  // ---------- plantilla descargable (.xlsx real) ----------
  function descargarPlantilla() {
    if (typeof XLSX === 'undefined') { Toast.error('La libreria de Excel no cargo (revisa tu conexion).'); return; }
    const filas = [
      ['Tipo de Equipo*', 'Marca*', 'Modelo', 'Numero de Serie', 'Codigo Activo Fijo', 'IMEI', 'MAC',
       'Condicion', 'Fecha Compra (YYYY-MM-DD)', 'Proveedor Compra', 'Costo (S/)',
       'Garantia Hasta (YYYY-MM-DD)', 'Ubicacion', 'Procesador', 'RAM', 'Disco',
       'Sistema Operativo', 'Accesorios (separados por coma)'],
      ['Laptop', 'HP', 'ProBook 450 G9', '5CD1234ABC', 'AF-00123', '', '', 'Bueno',
       '2025-01-15', 'Distribuidora SAC', 3500, '2027-01-15', 'Almacen TI',
       'Core i5-1235U', '16 GB', 'SSD 512 GB', 'Windows 11 Pro', 'Cargador, Maletin'],
      ['Impresora Multifuncional', 'HP', 'LaserJet M141w', 'CNB123XYZ', '', '', '', 'Nuevo',
       '2025-02-01', '', 850, '', 'Piso 2 - Administracion', '', '', '', '', 'Cable de poder']
    ];
    const ws = XLSX.utils.aoa_to_sheet(filas);
    ws['!cols'] = [{ wch: 24 }, { wch: 12 }, { wch: 18 }, { wch: 16 }, { wch: 14 }, { wch: 14 },
                   { wch: 14 }, { wch: 10 }, { wch: 14 }, { wch: 20 }, { wch: 10 }, { wch: 14 },
                   { wch: 22 }, { wch: 16 }, { wch: 10 }, { wch: 14 }, { wch: 18 }, { wch: 30 }];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Inventario');
    XLSX.writeFile(wb, 'plantilla_inventario_equipos.xlsx');
    Toast.success('Plantilla descargada. Complete las filas y subala aqui.');
  }

  // ---------- leer el archivo y armar filas ----------
  let filasImport = [];

  function leerArchivo(file) {
    const reader = new FileReader();
    reader.onload = e => {
      try {
        let data = new Uint8Array(e.target.result);
        let texto = '';

        // CSV: normalizar separador ';' -> ',' si no hay comas
        const nombre = (file.name || '').toLowerCase();
        if (nombre.endsWith('.csv') || nombre.endsWith('.txt')) {
          texto = new TextDecoder('utf-8').decode(data).replace(/^\uFEFF/, '');
          const primera = texto.split(/\r?\n/)[0] || '';
          const hayComa = (primera.match(/,/g) || []).length;
          const hayPuntoComa = (primera.match(/;/g) || []).length;
          if (hayPuntoComa > 0 && hayComa === 0) texto = texto.replace(/;/g, ',');
          data = texto;
        }

        const wb = XLSX.read(data, { type: nombre.endsWith('.csv') || nombre.endsWith('.txt') ? 'string' : 'array', raw: false });
        const ws = wb.Sheets[wb.SheetNames[0]];
        const aoa = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', raw: false });

        if (!aoa.length) { Toast.error('El archivo esta vacio.'); return; }

        // mapear encabezados
        const encabezados = aoa[0].map(norm);
        const mapa = {};
        encabezados.forEach((h, i) => {
          if (MAPA_COLUMNAS[h] !== undefined) mapa[MAPA_COLUMNAS[h]] = i;
        });
        if (mapa['tipo'] === undefined || mapa['marca'] === undefined) {
          Toast.error('El archivo no tiene las columnas «Tipo de Equipo» y «Marca». Use la plantilla.');
          return;
        }

        // tipos del catalogo para validar
        Api.get('api/catalogos/select.php', { tipo: 'tipos' }).then(tipos => {
          const tiposSet = new Set(tipos.map(t => norm(t.texto)));

          filasImport = [];
          const errores = [];
          const seriesVistas = {};

          for (let i = 1; i < aoa.length; i++) {
            const fila = aoa[i];
            if (!fila || fila.every(c => String(c || '').trim() === '')) continue;   // fila vacia

            const obj = {};
            Object.keys(mapa).forEach(k => { obj[k] = String(fila[mapa[k]] ?? '').trim(); });

            const errs = [];
            const tipoNorm = norm(obj.tipo);
            if (!obj.tipo) errs.push('Falta tipo');
            else if (!tiposSet.has(tipoNorm)) errs.push('Tipo no esta en el catalogo');

            if (!obj.marca) errs.push('Falta marca');

            const serie = (obj.serie || '').trim();
            if (serie) {
              if (seriesVistas[serie.toLowerCase()]) errs.push('Serie repetida en el archivo');
              seriesVistas[serie.toLowerCase()] = true;
            }
            if (obj.condicion && !CONDICIONES_OK.includes(norm(obj.condicion))) {
              obj.condicion = '';   // invalida -> default bueno
            }

            filasImport.push({
              _fila: i + 1,
              _errs: errs,
              tipo: obj.tipo, marca: obj.marca, modelo: obj.modelo,
              serie: serie, activo_fijo: obj.activo_fijo, imei: obj.imei, mac: obj.mac,
              condicion: obj.condicion, fecha_compra: normFecha(obj.fecha_compra),
              proveedor: obj.proveedor, costo: normCosto(obj.costo),
              garantia: normFecha(obj.garantia), ubicacion: obj.ubicacion,
              cpu: obj.cpu, ram: obj.ram, disco: obj.disco, so: obj.so,
              accesorios: obj.accesorios
            });
          }

          if (!filasImport.length) { Toast.error('No se encontraron filas con datos.'); return; }
          pintarPreview(errores);
        });
      } catch (err) {
        Toast.error('No se pudo leer el archivo: ' + err.message);
      }
    };
    reader.readAsArrayBuffer(file);
  }

  function pintarPreview() {
    const ok = filasImport.filter(f => !f._errs.length).length;
    const mal = filasImport.length - ok;

    let html = `
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span><b>${filasImport.length}</b> fila(s) leidas ·
        <span class="text-success"><b>${ok}</b> listas</span> ·
        <span class="text-danger"><b>${mal}</b> con error</span></span>
      <button class="btn btn-sm btn-outline-primary" id="imp-descargar-plantilla2">
        <i class="bi bi-file-earmark-arrow-down"></i> Plantilla</button>
    </div>

    <div class="imp-preview">
      <table class="table table-sm">
        <thead><tr>
          <th>#</th><th>Estado</th><th>Tipo</th><th>Marca</th><th>Modelo</th>
          <th>Serie</th><th>Cond.</th><th>Costo</th><th>Ubicacion</th><th>Accesorios</th>
        </tr></thead>
        <tbody>`;
    filasImport.forEach(f => {
      const mala = f._errs.length > 0;
      html += `<tr class="${mala ? 'imp-fila-err' : ''}">
        <td>${f._fila}</td>
        <td class="imp-estado">${mala
          ? '<span class="badge text-bg-danger" title="' + esc(f._errs.join('; ')) + '">ERROR</span>'
          : '<span class="badge text-bg-success">OK</span>'}</td>
        <td>${esc(f.tipo)}</td><td>${esc(f.marca)}</td><td>${esc(f.modelo)}</td>
        <td>${esc(f.serie)}</td><td>${esc(f.condicion || 'bueno')}</td>
        <td>${esc(f.costo)}</td><td>${esc(f.ubicacion)}</td>
        <td title="${esc(f.accesorios)}">${esc((f.accesorios || '').slice(0, 40))}</td>
      </tr>`;
    });
    html += `</tbody></table></div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" class="btn btn-primary" id="imp-confirmar" ${ok === 0 ? 'disabled' : ''}>
        <i class="bi bi-cloud-upload"></i> Importar ${ok} equipo(s)</button>
    </div>`;

    $('#imp-zona-preview').html(html);

    $('#imp-descargar-plantilla2').on('click', descargarPlantilla);
    $('#imp-confirmar').on('click', confirmarImport);
  }

  function confirmarImport() {
    const validas = filasImport.filter(f => !f._errs.length)
      .map(f => ({
        tipo: f.tipo, marca: f.marca, modelo: f.modelo, serie: f.serie,
        activo_fijo: f.activo_fijo, imei: f.imei, mac: f.mac, condicion: f.condicion,
        fecha_compra: f.fecha_compra, proveedor: f.proveedor, costo: f.costo,
        garantia: f.garantia, ubicacion: f.ubicacion,
        cpu: f.cpu, ram: f.ram, disco: f.disco, so: f.so, accesorios: f.accesorios
      }));

    Loader.show();
    $.ajax({
      url: BASE_URL + 'api/equipos/importar.php',
      type: 'POST',
      contentType: 'application/json; charset=utf-8',
      dataType: 'json',
      headers: { 'X-CSRF-Token': CSRF.token },
      data: JSON.stringify({ filas: validas }),
      complete: () => Loader.hide(),
      success: res => {
        if (!res.success) { Toast.error(res.message || 'Error al importar'); return; }
        Toast.success(res.message);
        mostrarResultado(res.data);
      },
      error: () => Toast.error('Error de conexion al importar.')
    });
  }

  function mostrarResultado(d) {
    let html = `
    <div class="alert ${d.errores.length ? 'alert-warning' : 'alert-success'} py-2">
      <b>${d.insertados}</b> de <b>${d.total_filas}</b> equipo(s) importados correctamente.
      ${d.errores.length ? '<br>' + d.errores.length + ' fila(s) no se importaron:' : ''}
    </div>`;
    if (d.errores.length) {
      html += '<div class="imp-preview"><table class="table table-sm"><thead><tr>' +
              '<th>Fila</th><th>Error</th></tr></thead><tbody>' +
              d.errores.map(e => '<tr class="imp-fila-err"><td>' + e.fila + '</td><td>' + esc(e.error) + '</td></tr>').join('') +
              '</tbody></table></div>';
    }
    html += '<div class="d-flex justify-content-end mt-3">' +
            '<button class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button></div>';

    $('#imp-zona-preview').html(html);

    // refrescar tabla y metricas del modulo
    if (App.modulos.equipos && App.modulos.equipos.tabla) {
      App.modulos.equipos.tabla.draw();
      if (App.modulos.equipos._cargarStats) App.modulos.equipos._cargarStats();
    }
  }

  // ---------- modal principal ----------
  $(document).on('click', '#btn-importar-equipos', function () {
    const html = `
    <div class="alert alert-light border py-2 small">
      <i class="bi bi-info-circle text-success"></i>
      <b>Carga masiva de equipos desde Excel.</b><br>
      1. Descargue la <b>plantilla</b> (columnas correctas + ejemplos).<br>
      2. Complete una fila por equipo. El <b>Tipo de Equipo</b> debe existir en
      Catálogos (sirve cualquier combinación de mayúsculas).<br>
      3. Suba el archivo aquí: verá una <b>vista previa validada</b> antes de importar.<br>
      <span class="text-muted">Acepta .xlsx, .xls y .csv · máximo 500 filas · los códigos EQ se generan solos ·
      las series/IMEI duplicados se rechazan.</span>
    </div>

    <div class="d-flex justify-content-center gap-2 mb-3">
      <button class="btn btn-outline-primary btn-sm" id="imp-descargar-plantilla">
        <i class="bi bi-file-earmark-arrow-down"></i> Descargar plantilla (.xlsx)</button>
      <label class="btn btn-success btn-sm mb-0">
        <i class="bi bi-file-earmark-arrow-up"></i> Seleccionar archivo…
        <input type="file" id="imp-file" accept=".xlsx,.xls,.csv" hidden>
      </label>
    </div>

    <div id="imp-zona-preview"></div>`;

    Modal.abrir('Importar equipos desde Excel', html, 'modal-xl');
    filasImport = [];

    $('#imp-descargar-plantilla').on('click', descargarPlantilla);
    $('#imp-file').on('change', function () {
      if (this.files && this.files[0]) leerArchivo(this.files[0]);
      $(this).val('');
    });
  });

  // ---------- exportar (con filtros actuales) ----------
  function urlExport(formato) {
    const p = new URLSearchParams({
      formato: formato,
      f_estado: $('#ef-estado').val() || '',
      f_tipo: $('#ef-tipo').val() || 0,
      f_garantia: $('#ef-garantia').val() || 0,
      csrf_token: CSRF.token
    });
    return BASE_URL + 'api/equipos/exportar.php?' + p.toString();
  }

  $(document).on('click', '#btn-exportar-csv', function (e) {
    e.preventDefault();
    window.open(urlExport('csv'), '_blank');
  });
  $(document).on('click', '#btn-exportar-xls', function (e) {
    e.preventDefault();
    window.open(urlExport('excel'), '_blank');
  });

})();