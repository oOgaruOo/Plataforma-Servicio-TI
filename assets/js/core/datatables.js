// ============================================================
// SIGTI - DataTables: idioma espanol + helper server-side
// ============================================================

const DATATABLES_ES = {
  processing:    'Procesando...',
  search:        'Buscar:',
  lengthMenu:    'Mostrar _MENU_ registros',
  info:          'Mostrando _START_ a _END_ de _TOTAL_ registros',
  infoEmpty:     'Mostrando 0 a 0 de 0 registros',
  infoFiltered:  '(filtrado de _MAX_ registros en total)',
  loadingRecords:'Cargando...',
  zeroRecords:   'No se encontraron registros',
  emptyTable:    'No hay registros disponibles',
  paginate: { first:'Primero', last:'Ultimo', next:'Siguiente', previous:'Anterior' },
  aria: { sortAscending:': activar para ordenar ascendente',
          sortDescending:': activar para ordenar descendente' }
};

const DT = {
  server(selector, url, extraData, columns, opts = {}) {
    return $(selector).DataTable({
      processing: true,
      serverSide: true,
      language: DATATABLES_ES,
      pageLength: 10,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      ajax: {
        url: BASE_URL + url,
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF.token },
        data: d => Object.assign(d, typeof extraData === 'function' ? extraData() : (extraData || {})),
        error: xhr => {
          if (xhr.status === 401) { App.sesionExpirada(); return; }
          if (xhr.status === 419) {
            Toast.error('Sesion de seguridad expirada. Recargando...');
            setTimeout(() => location.reload(), 1500);
            return;
          }
          let msg = 'Error al obtener los datos de la tabla (' + xhr.status + ').';
          try {
            const r = JSON.parse(xhr.responseText);
            if (r && r.message) msg += ' ' + r.message;
          } catch (_) {}
          Toast.error(msg);
        }
      },
      columns,
      ...opts
    });
  }
};