// ============================================================
// SIGTI - Seguimiento de ubicacion (laptops/PCs) - v2
// v2: boton "Asignar" para equipos en stock + ubicacion default
// ============================================================

const Seguimiento = {

  tabla: null,

  init() {
    this._cargarTabla();
    $('#sg-filtro').on('change', () => this._cargarTabla());
    $('#btn-seg-gestion-ubica').on('click', () => this._gestionUbicaciones());

    $('#tb-seg').on('click', '.btn-verificar, .btn-trasladar, .btn-historial', function () {
      const d = $(this).data();
      if      ($(this).hasClass('btn-verificar')) Seguimiento._verificar(d);
      else if ($(this).hasClass('btn-trasladar')) Seguimiento._trasladar(d);
      else                                        Seguimiento._historial(d.equipoId, d.codigo);
    });

    // boton asignar -> ir al modulo de asignaciones
    $('#tb-seg').on('click', '.btn-asignar', function () {
      const codigo = $(this).data('codigo');
      Router.ir('asignaciones/lista');
      setTimeout(() => {
        if (App.modulos.asignaciones && App.modulos.asignaciones._abrirEntrega) {
          App.modulos.asignaciones._abrirEntrega();
          Toast.info('Asigne el equipo ' + codigo + ' desde el formulario de entrega.');
        }
      }, 600);
    });
  },

  _cargarTabla() {
    if ($.fn.DataTable.isDataTable('#tb-seg')) {
      $('#tb-seg').DataTable().clear().destroy();
    }

    Api.get('api/equipos/seguimiento.php', {
      accion: 'panel', f_verif: $('#sg-filtro').val() || 'todos'
    }).then(data => {

      $('#seg-stats [data-k]').each(function () { $(this).text(data.stats[$(this).data('k')]); });

      const SEM = {
        verde: '<span class="badge text-bg-success">',
        ambar: '<span class="badge text-bg-warning text-dark">',
        rojo:  '<span class="badge text-bg-danger">'
      };

      const filas = data.equipos.map(e => {
        // columna "con quien"
        let conQuien;
        if (e.asignado) {
          conQuien = '<b>' + esc(e.asignado) + '</b><br><span class="text-muted small">' +
                     esc(e.asignado_cargo || '') + ' · ' + esc(e.area_asignado || '') + '</span>';
        } else if (e.area_directa) {
          conQuien = '<span class="badge text-bg-info">ÁREA</span><br><b>' + esc(e.area_directa) +
                     '</b><br><span class="text-muted small">equipo de uso común</span>';
        } else if (App.permisos.includes('asignaciones.crear')) {
          conQuien = '<span class="text-muted small">En stock</span><br>' +
                     '<button class="btn btn-sm btn-outline-primary btn-asignar mt-1" data-codigo="' +
                     esc(e.codigo) + '" title="Ir a asignar este equipo"><i class="bi bi-person-plus"></i> Asignar</button>';
        } else {
          conQuien = '<span class="text-muted">En stock (sin asignar)</span>';
        }

        // columna ubicacion (nunca en blanco)
        const ubic = e.ubicacion
          ? esc(e.ubicacion)
          : '<span class="text-muted">— sin verificar —</span>';

        // columna verificacion
        const verif = (SEM[e.sem] || '<span class="badge text-bg-light">') +
          (e.sem_txt || '') + '</span>' +
          (e.verificado_ubicacion
            ? '<br><span class="text-muted small">' + esc(e.verificado_ubicacion) + '</span>'
            : '');

        // acciones
        let acciones = '';
        if (App.permisos.includes('equipos.editar')) {
          acciones += '<button class="btn btn-sm btn-success btn-verificar me-1" data-equipo-id="' + e.id +
            '" data-ubicacion="' + esc(e.ubicacion || '') + '" data-codigo="' + esc(e.codigo) +
            '" title="Confirmar que esta aqui"><i class="bi bi-check-lg"></i></button>';
          acciones += '<button class="btn btn-sm btn-outline-primary btn-trasladar me-1" data-equipo-id="' + e.id +
            '" data-ubicacion="' + esc(e.ubicacion || '') + '" data-codigo="' + esc(e.codigo) +
            '" title="Registrar traslado"><i class="bi bi-arrow-left-right"></i></button>';
        }
        acciones += '<button class="btn btn-sm btn-outline-secondary btn-historial" data-equipo-id="' + e.id +
          '" data-codigo="' + esc(e.codigo) +
          '" title="Historial de movimientos"><i class="bi bi-clock-history"></i></button>';

        return [
          '<strong>' + esc(e.codigo) + '</strong><br><span class="text-muted small">' +
            esc(e.marca + ' ' + (e.modelo || '')) + '</span>',
          ubic,
          conQuien,
          '<span class="small">' + esc(e.estado) + '</span>',
          verif,
          acciones
        ];
      });

      this.tabla = $('#tb-seg').DataTable({
        data: filas,
        language: DATATABLES_ES,
        pageLength: 25,
        order: [[4, 'asc']],
        columns: [
          { title: 'Equipo' }, { title: 'Ubicacion' }, { title: 'Con quien' },
          { title: 'Estado' }, { title: 'Verificacion' },
          { title: '', orderable: false, className: 'text-nowrap text-center' }
        ]
      });
    });
  },

  // ---------------- VERIFICAR (1 clic) ----------------
  _verificar(d) {
    const html = `
    <div class="alert alert-success py-2 small">
      <i class="bi bi-geo-alt"></i> Confirmar que <b>${esc(d.codigo || 'el equipo')}</b> esta fisicamente en:
    </div>
    <div class="mb-3">
      <label class="form-label">Ubicacion confirmada <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="sv-ubicacion" value="${esc(d.ubicacion || '')}" maxlength="150"
             list="dl-ubicaciones" placeholder="Escriba o elija del catalogo">
      <datalist id="dl-ubicaciones"></datalist>
    </div>
    <div class="d-flex justify-content-end gap-2">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-success" id="sv-ok"><i class="bi bi-check-lg"></i> Esta aqui</button>
    </div>`;

    Modal.abrir('Verificar ubicacion', html, 'modal-md');
    Api.get('api/equipos/ubicaciones.php').then(list => {
      $('#dl-ubicaciones').html(list.map(u => '<option value="' + esc(u.texto) + '">').join(''));
    }).catch(() => {});

    $('#sv-ok').on('click', () => {
      const ub = $.trim($('#sv-ubicacion').val() || '');
      if (!ub) { Toast.warning('Indique donde esta el equipo.'); return; }
      Api.post('api/equipos/seguimiento.php', { accion: 'verificar', equipo_id: d.equipoId, ubicacion: ub })
        .then(() => { Modal.cerrar(); this._cargarTabla(); });
    });
  },

  // ---------------- TRASLADAR ----------------
  _trasladar(d) {
    const html = `
    <div class="alert alert-primary py-2 small">
      <i class="bi bi-arrow-left-right"></i> Traslado de <b>${esc(d.codigo)}</b>.
      Actual: <b>${esc(d.ubicacion || '—')}</b>. El movimiento queda en bitacora.
    </div>
    <div class="mb-3">
      <label class="form-label">Nueva ubicacion <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="st-ubicacion" maxlength="150"
             list="dl-ubicaciones2" placeholder="Escriba o elija del catalogo">
      <datalist id="dl-ubicaciones2"></datalist>
    </div>
    <div class="mb-3">
      <label class="form-label">Motivo / observacion</label>
      <input type="text" class="form-control" id="st-obs" maxlength="200" placeholder="Ej: Cambio de oficina a Piso 3">
    </div>
    <div class="d-flex justify-content-end gap-2">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-primary" id="st-ok"><i class="bi bi-arrow-left-right"></i> Registrar traslado</button>
    </div>`;

    Modal.abrir('Trasladar — ' + d.codigo, html, 'modal-md');
    Api.get('api/equipos/ubicaciones.php').then(list => {
      $('#dl-ubicaciones2').html(list.map(u => '<option value="' + esc(u.texto) + '">').join(''));
    }).catch(() => {});

    $('#st-ok').on('click', () => {
      const ub = $.trim($('#st-ubicacion').val() || '');
      if (!ub) { Toast.warning('Indique la nueva ubicacion.'); return; }
      Api.post('api/equipos/seguimiento.php', {
        accion: 'trasladar', equipo_id: d.equipoId,
        ubicacion: ub, observaciones: $.trim($('#st-obs').val() || '')
      }).then(() => { Modal.cerrar(); this._cargarTabla(); });
    });
  },

  // ---------------- HISTORIAL ----------------
  _historial(equipoId, codigo) {
    Api.get('api/equipos/seguimiento.php', { accion: 'historial', equipo_id: equipoId }).then(h => {

      const ICONOS = {
        verificacion: ['bi-geo-alt', '#16a34a'],
        traslado:     ['bi-arrow-left-right', '#2563eb'],
        asignacion:   ['bi-person-check', '#2563eb'],
        devolucion:   ['bi-box-arrow-in-left', '#d97706'],
        mantenimiento:['bi-tools', '#d97706'],
        alta:         ['bi-plus-circle', '#16a34a']
      };

      let html = '<div class="timeline">';
      if (!h.length) html += '<div class="text-muted small">Sin movimientos registrados.</div>';
      h.forEach(ev => {
        const par = ICONOS[ev.tipo] || ['bi-activity', '#64748b'];
        html += '<div class="tl-item">' +
          '<div class="tl-titulo"><i class="bi ' + par[0] + '" style="color:' + par[1] + '"></i> ' +
          esc(ev.tipo) + ' — ' + esc(ev.ubicacion) + '</div>' +
          (ev.con_quien ? '<div class="tl-detalle">Con: ' + esc(ev.con_quien) + '</div>' : '') +
          (ev.observaciones ? '<div class="tl-detalle">' + esc(ev.observaciones) + '</div>' : '') +
          '<div class="tl-fecha">' + esc(ev.created_at) +
          (ev.registrado_por ? ' · ' + esc(ev.registrado_por) : '') + '</div></div>';
      });
      html += '</div>';

      Modal.abrir('Historial de ubicacion — ' + codigo, html, 'modal-lg');
    });
  },

  // ---------------- GESTION DE UBICACIONES ----------------
  _gestionUbicaciones() {
    Api.get('api/equipos/ubicaciones.php').then(list => {
      const TIPOS = { sede:'Sede', piso:'Piso', oficina:'Oficina', almacen:'Almacen', remoto:'Remoto', otro:'Otro' };

      const fila = u => `
        <tr data-id="${u.id}">
          <td>${esc(u.texto)}</td>
          <td><span class="badge text-bg-light border">${esc(TIPOS[u.tipo] || u.tipo)}</span></td>
          <td>${u.estado === 'activo' ? '<span class="badge text-bg-success">Activa</span>' : '<span class="badge text-bg-secondary">Inactiva</span>'}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary btn-ub-editar me-1" data-id="${u.id}" data-nombre="${esc(u.texto)}" data-tipo="${u.tipo}"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-secondary btn-ub-toggle" data-id="${u.id}"><i class="bi bi-power"></i></button>
          </td>
        </tr>`;

      const html = `
      <table class="table tabla-mini table-bordered">
        <thead><tr><th>Ubicacion</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
        <tbody>${list.map(fila).join('')}</tbody>
      </table>
      <div class="row g-2 mt-2">
        <div class="col-md-6"><input type="text" class="form-control form-control-sm" id="ub-nombre" maxlength="100" placeholder="Nueva ubicacion * (Ej: Piso 4 - Contabilidad)"></div>
        <div class="col-md-4"><select class="form-select form-select-sm" id="ub-tipo">
          ${Object.entries(TIPOS).map(([k,v]) => '<option value="' + k + '">' + v + '</option>').join('')}
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" id="ub-guardar"><i class="bi bi-plus-lg"></i></button></div>
      </div>`;

      Modal.abrir('Catalogo de ubicaciones fisicas', html, 'modal-lg');

      const guardar = () => {
        const nombre = $.trim($('#ub-nombre').val() || '');
        if (!nombre) { Toast.warning('Escriba el nombre.'); return; }
        Api.post('api/equipos/ubicaciones.php', {
          accion: 'guardar', id: $('#ub-guardar').data('editando') || 0,
          nombre: nombre, tipo: $('#ub-tipo').val()
        }).then(() => { Modal.cerrar(); this._gestionUbicaciones(); });
      };
      $('#ub-guardar').on('click', guardar);
      $('#ub-nombre').on('keyup', e => { if (e.key === 'Enter') guardar(); });

      $('#modal-general-cuerpo').off('click', '.btn-ub-editar, .btn-ub-toggle')
        .on('click', '.btn-ub-editar', function () {
          $('#ub-nombre').val($(this).data('nombre'));
          $('#ub-tipo').val($(this).data('tipo'));
          $('#ub-guardar').data('editando', $(this).data('id')).html('<i class="bi bi-check-lg"></i>');
        })
        .on('click', '.btn-ub-toggle', function () {
          Api.post('api/equipos/ubicaciones.php', { accion: 'toggle', id: $(this).closest('tr').data('id') })
            .then(() => Seguimiento._gestionUbicaciones());
        });
    });
  }
};

App.registrar('seguimiento', Seguimiento);