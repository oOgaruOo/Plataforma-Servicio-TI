// ============================================================
// SIGTI - Modulo Catalogos (vista: configuracion/principal)
// ============================================================

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, m =>
    ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m]));
}

const Badges = {
  estado(v) {
    return v === 'activo'
      ? '<span class="badge text-bg-success">Activo</span>'
      : '<span class="badge text-bg-secondary">Inactivo</span>';
  },
  bool(v) {
    return +v === 1
      ? '<span class="badge text-bg-success">Activo</span>'
      : '<span class="badge text-bg-secondary">Inactivo</span>';
  },
  usuario(v) {
    const map = {
      activo:    '<span class="badge text-bg-success">Activo</span>',
      bloqueado: '<span class="badge text-bg-danger">Bloqueado</span>',
      inactivo:  '<span class="badge text-bg-secondary">Inactivo</span>'
    };
    return map[v] || esc(v);
  }
};

function pintarErroresForm(selectorForm, errores) {
  $(selectorForm + ' .form-error').remove();
  $(selectorForm + ' .is-invalid').removeClass('is-invalid');
  $.each(errores || {}, (campo, msg) => {
    const $c = $(selectorForm + ' [data-campo="' + campo + '"]');
    if ($c.length) {
      $c.append('<div class="form-error"><i class="bi bi-exclamation-circle me-1"></i>' + esc(msg) + '</div>');
      $c.find('.form-control, .form-select').addClass('is-invalid');
    } else {
      Toast.warning(msg);
    }
  });
}

const COL_ACCIONES = {
  data: null, orderable: false, className: 'text-nowrap text-center',
  render: () =>
    '<button class="btn btn-sm btn-outline-primary btn-editar me-1" title="Editar"><i class="bi bi-pencil"></i></button>' +
    '<button class="btn btn-sm btn-outline-secondary btn-toggle" title="Activar / desactivar"><i class="bi bi-power"></i></button>' +
    '<button class="btn btn-sm btn-outline-danger btn-eliminar" title="Eliminar definitivamente"><i class="bi bi-trash"></i></button>'
};

const Catalogos = {

  CONFIG: {
    areas: {
      singular: 'area',
      columnas: [
        { data: 'nombre' },
        { data: 'descripcion', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'estado', render: v => Badges.estado(v), className: 'text-center' },
        COL_ACCIONES
      ],
      campos: [
        { name: 'nombre', label: 'Nombre', req: true, maxlen: 80, ph: 'Ej: Recursos Humanos' },
        { name: 'descripcion', label: 'Descripcion', type: 'textarea', maxlen: 200 },
        { name: 'estado', label: 'Estado', type: 'select', opts: [['activo','Activo'],['inactivo','Inactivo']], def: 'activo' }
      ]
    },
    tipos: {
      singular: 'tipo de equipo',
      columnas: [
        { data: 'nombre' },
        { data: 'activo', render: v => Badges.bool(v), className: 'text-center' },
        COL_ACCIONES
      ],
      campos: [
        { name: 'nombre', label: 'Nombre del tipo', req: true, maxlen: 60, ph: 'Ej: Laptop, Impresora, Monitor...' },
        { name: 'activo', label: 'Activo (visible en formularios)', type: 'check', def: 1 }
      ]
    },
    categorias: {
      singular: 'categoria',
      columnas: [
        { data: 'nombre' },
        { data: 'descripcion', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'activo', render: v => Badges.bool(v), className: 'text-center' },
        COL_ACCIONES
      ],
      campos: [
        { name: 'nombre', label: 'Nombre de la categoria', req: true, maxlen: 60, ph: 'Ej: Hardware, Correo...' },
        { name: 'descripcion', label: 'Descripcion', type: 'textarea', maxlen: 200 },
        { name: 'activo', label: 'Activa', type: 'check', def: 1 }
      ]
    },
    subcategorias: {
      singular: 'subcategoria',
      columnas: [
        { data: 'nombre' },
        { data: 'categoria' },
        { data: 'activo', render: v => Badges.bool(v), className: 'text-center' },
        COL_ACCIONES
      ],
      campos: [
        { name: 'categoria_id', label: 'Categoria', type: 'lista', lista: 'categorias', req: true },
        { name: 'nombre', label: 'Nombre', req: true, maxlen: 80, ph: 'Ej: Sin internet, Buzon lleno...' },
        { name: 'activo', label: 'Activa', type: 'check', def: 1 }
      ]
    },
    prioridades: {
      singular: 'prioridad',
      columnas: [
        { data: 'nombre' },
        { data: 'nivel', className: 'text-center' },
        { data: 'color', className: 'text-center',
          render: v => '<span class="swatch" style="background:' + esc(v) + '"></span>' + esc(v) },
        { data: 'sla_horas', className: 'text-center' },
        { data: 'activo', render: v => Badges.bool(v), className: 'text-center' },
        COL_ACCIONES
      ],
      campos: [
        { name: 'nombre', label: 'Nombre', req: true, maxlen: 20, ph: 'Ej: Critica, Alta...' },
        { name: 'nivel', label: 'Nivel (1 = mas urgente)', type: 'numero', req: true },
        { name: 'color', label: 'Color de identificacion', type: 'color', req: true },
        { name: 'sla_horas', label: 'SLA en horas (limite de resolucion)', type: 'numero', req: true },
        { name: 'activo', label: 'Activa', type: 'check', def: 1 }
      ]
    },
    proveedores: {
      singular: 'proveedor',
      columnas: [
        { data: 'ruc', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'nombre' },
        { data: 'contacto', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'telefono', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'correo', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'especialidad', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
        { data: 'estado', render: v => Badges.estado(v), className: 'text-center' },
        COL_ACCIONES
      ],
      campos: [
        { name: 'ruc', label: 'RUC', maxlen: 15, ph: 'Ej: 20512345678' },
        { name: 'nombre', label: 'Razon social / nombre', req: true, maxlen: 120 },
        { name: 'contacto', label: 'Persona de contacto', maxlen: 100 },
        { name: 'telefono', label: 'Telefono', maxlen: 30 },
        { name: 'correo', label: 'Correo', type: 'email', maxlen: 120 },
        { name: 'especialidad', label: 'Especialidad', maxlen: 120, ph: 'Ej: Reparacion de laptops' },
        { name: 'estado', label: 'Estado', type: 'select', opts: [['activo','Activo'],['inactivo','Inactivo']], def: 'activo' }
      ]
    }
  },

  tablas: {},

  init() {
    const self = this;

    const tipoInicial = $('.nav-tabs .nav-link.active').data('tipo');
    if (tipoInicial) this._initTabla(tipoInicial);

    $('.nav-tabs .nav-link').on('shown.bs.tab', function () {
      const tipo = $(this).data('tipo');
      if (!self.tablas[tipo]) self._initTabla(tipo);
      else self.tablas[tipo].columns.adjust();
    });

    $('.btn-nuevo').on('click', function () {
      self._abrirForm($(this).data('tipo'), null);
    });

    Object.keys(this.CONFIG).forEach(tipo => {
      $('#tb-' + tipo).on('click', '.btn-editar, .btn-toggle, .btn-eliminar', function () {
        if (!self.tablas[tipo]) return;
        const fila = self.tablas[tipo].row($(this).closest('tr')).data();
        if (!fila) return;
        if ($(this).hasClass('btn-editar')) self._abrirForm(tipo, fila);
        else if ($(this).hasClass('btn-eliminar')) self._eliminar(tipo, fila);
        else                                self._toggle(tipo, fila);
      });
    });
  },

  _initTabla(tipo) {
    this.tablas[tipo] = DT.server('#tb-' + tipo, 'api/catalogos/listar.php',
      () => ({ tipo }), this.CONFIG[tipo].columnas);
  },

  _abrirForm(tipo, fila) {
    const c = this.CONFIG[tipo];
    const esNuevo = !fila;

    const pendientes = c.campos
      .filter(cam => cam.type === 'lista')
      .map(cam => Api.get('api/catalogos/select.php', { tipo: cam.lista })
        .then(lista => { cam._opciones = lista; })
        .catch(() => { cam._opciones = []; }));

    Promise.all(pendientes).finally(() => {
      let html = '<form id="form-catalogo" autocomplete="off" data-id="' + (esNuevo ? 0 : fila.id) + '">';
      c.campos.forEach(cam => {
        const valor = esNuevo ? (cam.def ?? '') : (fila[cam.name] ?? '');
        html += this._campoHtml(cam, valor);
      });
      html += `
        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-guardar-catalogo">
            <i class="bi bi-check-lg"></i> Guardar
          </button>
        </div>
      </form>`;

      Modal.abrir((esNuevo ? 'Nuevo — ' : 'Editar — ') + c.singular, html);

      $('#btn-guardar-catalogo').on('click', () => this._guardar(tipo));
      $('#form-catalogo').on('submit', e => { e.preventDefault(); this._guardar(tipo); });
    });
  },

  _campoHtml(cam, valor) {
    const req = cam.req ? ' <span class="text-danger">*</span>' : '';

    if (cam.type === 'check') {
      const marcado = (valor === 1 || valor === '1');
      return '<div class="form-check mt-2" data-campo="' + cam.name + '">' +
        '<input class="form-check-input" type="checkbox" id="fc-' + cam.name + '" ' + (marcado ? 'checked' : '') + '>' +
        '<label class="form-check-label" for="fc-' + cam.name + '">' + esc(cam.label) + '</label></div>';
    }

    let control = '';
    switch (cam.type) {
      case 'textarea':
        control = '<textarea class="form-control" id="fc-' + cam.name + '" rows="2" maxlength="' +
                  (cam.maxlen || 250) + '">' + esc(valor) + '</textarea>';
        break;
      case 'select':
        control = '<select class="form-select" id="fc-' + cam.name + '">' +
          cam.opts.map(([v, t]) =>
            '<option value="' + esc(v) + '"' + (String(valor) === String(v) ? ' selected' : '') + '">' + esc(t) + '</option>'
          ).join('') + '</select>';
        break;
      case 'lista':
        control = '<select class="form-select" id="fc-' + cam.name + '">' +
          (cam._opciones || []).map(o =>
            '<option value="' + esc(o.id) + '"' + (String(valor) === String(o.id) ? ' selected' : '') + '>' +
            esc(o.texto || o.nombre) + '</option>'
          ).join('') + '</select>';
        break;
      case 'numero':
        control = '<input type="number" class="form-control" id="fc-' + cam.name + '" min="1" value="' + esc(valor) + '">';
        break;
      case 'color':
        control = '<input type="color" class="form-control form-control-color w-100" id="fc-' + cam.name +
                  '" value="' + (valor || '#2563eb') + '">';
        break;
      case 'email':
        control = '<input type="email" class="form-control" id="fc-' + cam.name + '" value="' + esc(valor) +
                  '" maxlength="' + (cam.maxlen || 120) + '">';
        break;
      default:
        control = '<input type="text" class="form-control" id="fc-' + cam.name + '" value="' + esc(valor) +
                  '" maxlength="' + (cam.maxlen || 100) + '" placeholder="' + esc(cam.ph || '') + '">';
    }

    return '<div class="mb-3" data-campo="' + cam.name + '">' +
           '<label class="form-label">' + esc(cam.label) + req + '</label>' + control + '</div>';
  },

  _guardar(tipo) {
    const c = this.CONFIG[tipo];
    const datos = { tipo: tipo, id: $('#form-catalogo').data('id') || 0 };

    c.campos.forEach(cam => {
      const $el = $('#fc-' + cam.name);
      if (cam.type === 'check') datos[cam.name] = $el.is(':checked') ? 1 : 0;
      else                      datos[cam.name] = $.trim($el.val() || '');
    });

    Api.post('api/catalogos/guardar.php', datos)
      .then(() => { Modal.cerrar(); this.tablas[tipo].draw(); })
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-catalogo', err.cuerpo.errors);
      });
  },

  _eliminar(tipo, fila) {
    Swal.fire({
      title: '¿Eliminar este registro?',
      html: '<b>' + esc(fila.nombre) + '</b><br><small>Solo es posible si ningún otro registro lo utiliza. ' +
            'Si ya se usó, desactívelo (botón de encendido) para conservar el historial.</small>',
      icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626'
    }).then(r => {
      if (!r.isConfirmed) return;
      Api.post('api/catalogos/eliminar.php', { tipo: tipo, id: fila.id })
        .then(() => this.tablas[tipo].draw());
    });
  },
  _toggle(tipo, fila) {
    const activo = (+fila.activo === 1 || fila.estado === 'activo');
    const verbo  = activo ? 'desactivar' : 'activar';

    Swal.fire({
      title: '¿' + verbo.charAt(0).toUpperCase() + verbo.slice(1) + ' este registro?',
      html: 'El historial <b>no se pierde</b>; los inactivos dejan de aparecer en los formularios de nuevos registros.',
      icon: 'question', showCancelButton: true,
      confirmButtonText: 'Si, continuar', cancelButtonText: 'Cancelar', confirmButtonColor: '#2563eb'
    }).then(r => {
      if (!r.isConfirmed) return;
      Api.post('api/catalogos/toggle.php', { tipo: tipo, id: fila.id })
        .then(() => this.tablas[tipo].draw());
    });
  }
};

App.registrar('configuracion', Catalogos);