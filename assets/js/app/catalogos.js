/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Modulo Configuracion (catalogos)
   Vista:    views/configuracion/principal.php
   API:      api/catalogos/{listar,guardar,toggle,eliminar,select}.php

   IMPORTANTE: el numero y orden de 'cols' de cada catalogo debe
   coincidir con los <th> de la vista Y con 'alias' de listar.php
   (la ultima columna de acciones no cuenta en 'alias').
   ============================================================ */
var Catalogos = {

  tablas: {},

  /* ---------- helpers de columnas ---------- */
  _txt: function (campo) {
    return { data: campo };
  },
  _txtVacio: function (campo) {
    return { data: campo, render: function (v) { return v ? esc(v) : '<span class="text-muted">—</span>'; } };
  },
  _estado: function (campo) {
    // 'estado' = ENUM('activo','inactivo') | 'activo' = TINYINT(1)
    return {
      data: campo,
      render: function (v) {
        var on = (campo === 'estado') ? (v === 'activo') : (parseInt(v, 10) === 1);
        return '<span class="badge bg-' + (on ? 'success' : 'secondary') + '">' + (on ? 'Activo' : 'Inactivo') + '</span>';
      }
    };
  },
  _acciones: function (campo) {
    return {
      data: null, orderable: false, searchable: false, className: 'text-center text-nowrap',
      render: function (v, t, fila) {
        var on = (campo === 'estado') ? (fila.estado === 'activo') : (parseInt(fila.activo, 10) === 1);
        return '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-editar" title="Editar"><i class="bi bi-pencil"></i></button>' +
               '<button type="button" class="btn btn-sm btn-outline-' + (on ? 'success' : 'secondary') + ' me-1 btn-toggle" title="' + (on ? 'Desactivar' : 'Activar') + '"><i class="bi bi-power"></i></button>' +
               '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar" title="Eliminar"><i class="bi bi-trash"></i></button>';
      }
    };
  },

  /* ---------- definicion de cada catalogo ---------- */
  _def: function (tipo) {
    var s = this;
    var D = {
      areas: {
        titulo: 'Area', est: 'estado',
        cols: [
          {
            data: 'nombre',
            render: function (v, t, f) {
              return esc(v) + (f.codigo ? ' <span class="badge bg-light text-dark border ms-1" title="Prefijo del código de usuario del personal">' + esc(f.codigo) + '</span>' : '');
            }
          },
          s._txtVacio('descripcion'), s._estado('estado'), s._acciones('estado')
        ],
        campos: [
          { n: 'nombre',      l: 'Nombre',      t: 'text', max: 80,  req: true, w: 8 },
          { n: 'codigo',      l: 'Código (prefijo)', t: 'text', max: 6, w: 4,
            ayuda: 'Prefijo del código del personal de esta área (SIS-0001). Si cambia el nombre del área el prefijo se actualiza solo (y el personal toma el código nuevo); vacío = se arma del nombre. PRY y OBR están reservados.' },
          { n: 'descripcion', l: 'Descripcion', t: 'text', max: 200, w: 12 },
          { n: 'estado',      l: 'Estado',      t: 'estado', w: 6 }
        ]
      },
      tipos: {
        titulo: 'Tipo de equipo', est: 'activo',
        cols: [s._txt('nombre'), s._estado('activo'), s._acciones('activo')],
        campos: [
          { n: 'nombre', l: 'Nombre', t: 'text', max: 60, req: true, w: 12 },
          { n: 'activo', l: 'Estado', t: 'activo', w: 6 }
        ]
      },
      categorias: {
        titulo: 'Categoria', est: 'activo',
        cols: [s._txt('nombre'), s._txtVacio('descripcion'), s._estado('activo'), s._acciones('activo')],
        campos: [
          { n: 'nombre',      l: 'Nombre',      t: 'text', max: 60,  req: true, w: 12 },
          { n: 'descripcion', l: 'Descripcion', t: 'text', max: 200, w: 12 },
          { n: 'activo',      l: 'Estado',      t: 'activo', w: 6 }
        ]
      },
      subcategorias: {
        titulo: 'Subcategoria', est: 'activo',
        cols: [s._txt('nombre'), s._txt('categoria'), s._estado('activo'), s._acciones('activo')],
        campos: [
          { n: 'categoria_id', l: 'Categoria', t: 'categoria', req: true, w: 12 },
          { n: 'nombre',       l: 'Nombre',    t: 'text', max: 80, req: true, w: 12 },
          { n: 'activo',       l: 'Estado',    t: 'activo', w: 6 }
        ]
      },
      prioridades: {
        titulo: 'Prioridad', est: 'activo',
        cols: [
          s._txt('nombre'),
          s._txt('nivel'),
          {
            data: 'color',
            render: function (v) {
              var ok = /^#[0-9a-fA-F]{3,8}$/.test(v || '');
              return (ok ? '<span class="d-inline-block rounded border me-1 align-middle" style="width:16px;height:16px;background:' + v + '"></span>' : '') + esc(v);
            }
          },
          { data: 'sla_horas', render: function (v) { return esc(v) + ' h'; } },
          s._estado('activo'),
          s._acciones('activo')
        ],
        campos: [
          { n: 'nombre',    l: 'Nombre',      t: 'text',   max: 20, req: true, w: 6 },
          { n: 'nivel',     l: 'Nivel (1 = mas urgente)', t: 'number', min: 1, max: 99, req: true, w: 6 },
          { n: 'color',     l: 'Color',       t: 'color',  def: '#6c757d', req: true, w: 6 },
          { n: 'sla_horas', l: 'SLA (horas)', t: 'number', min: 1, max: 2000, req: true, w: 6 },
          { n: 'activo',    l: 'Estado',      t: 'activo', w: 6 }
        ]
      },
      proyectos: {
        titulo: 'Proyecto / Obra', est: 'estado', ancho: 'modal-lg',
        cols: [
          { data: 'codigo', render: function (v) { return '<strong class="text-nowrap">' + esc(v) + '</strong>'; } },
          {
            data: 'nombre',
            render: function (v, t, f) {
              return esc(v) + (f.cliente ? '<div class="small text-muted">Cliente: ' + esc(f.cliente) + '</div>' : '');
            }
          },
          {
            data: 'tipo',
            render: function (v) {
              return v === 'obra' ? '<span class="badge text-bg-warning text-dark"><i class="bi bi-cone-striped"></i> Obra</span>'
                                  : '<span class="badge text-bg-info"><i class="bi bi-kanban"></i> Proyecto</span>';
            }
          },
          s._txtVacio('ubicacion'), s._txtVacio('responsable'),
          { data: 'equipos', className: 'text-center', render: function (v) { return +v > 0 ? '<span class="badge text-bg-primary">' + (+v) + '</span>' : '<span class="text-muted">0</span>'; } },
          s._estado('estado'), s._acciones('estado')
        ],
        campos: [
          { n: 'clase',          l: 'Tipo',            t: 'opciones', req: true, w: 4,
            opciones: [['proyecto', 'Proyecto'], ['obra', 'Obra']] },
          { n: 'codigo',         l: 'Código',          t: 'text', max: 20, w: 4,
            ayuda: 'Vacío = se genera solo (PRY-001 / OBR-001).' },
          { n: 'estado',         l: 'Estado',          t: 'estado', w: 4 },
          { n: 'nombre',         l: 'Nombre',          t: 'text', max: 120, req: true, w: 12 },
          { n: 'cliente',        l: 'Cliente / contratante', t: 'text', max: 120, w: 6 },
          { n: 'ubicacion',      l: 'Ubicación',       t: 'text', max: 150, w: 6 },
          { n: 'responsable_id', l: 'Responsable (jefe de proyecto / residente)', t: 'personal', w: 12,
            ayuda: 'Es quien recibe y custodia los equipos por defecto; se puede cambiar en cada entrega.' },
          { n: 'fecha_inicio',   l: 'Fecha de inicio', t: 'date', w: 6 },
          { n: 'fecha_fin',      l: 'Fecha de fin',    t: 'date', w: 6 }
        ]
      },
      proveedores: {
        titulo: 'Proveedor', est: 'estado', ancho: 'modal-lg',
        cols: [
          {
            data: 'ruc',
            render: function (v, t, f) {
              return v ? '<span class="badge bg-light text-dark border me-1">' + esc((f.tipo_documento || 'ruc').toUpperCase()) + '</span>' + esc(v)
                       : '<span class="text-muted">—</span>';
            }
          },
          {
            data: 'nombre',
            render: function (v, t, f) {
              return esc(v) + (f.direccion ? '<div class="small text-muted">' + esc(f.direccion) + '</div>' : '');
            }
          },
          s._txtVacio('contacto'), s._txtVacio('telefono'),
          s._txtVacio('correo'), s._txtVacio('especialidad'), s._estado('estado'), s._acciones('estado')
        ],
        campos: [
          { n: 'nombre',       l: 'Razon social / nombre', t: 'text', max: 120, req: true, w: 8 },
          { n: 'ruc',          l: 'RUC',          t: 'text', max: 15,  w: 4 },
          { n: 'contacto',     l: 'Contacto',     t: 'text', max: 100, w: 6 },
          { n: 'telefono',     l: 'Telefono',     t: 'text', max: 30,  w: 6 },
          { n: 'correo',       l: 'Correo',       t: 'text', max: 120, w: 6 },
          { n: 'especialidad', l: 'Especialidad', t: 'text', max: 120, w: 6 },
          { n: 'estado',       l: 'Estado',       t: 'estado', w: 6 }
        ]
      }
    };
    return D[tipo] || null;
  },

  /* ---------- arranque ---------- */
  init: function () {
    var s = this;
    // La vista se vuelve a cargar cada vez que se entra al modulo:
    // las tablas anteriores ya no existen en el DOM.
    s.tablas = {};

    var activa = document.querySelector('.nav-tabs .nav-link.active[data-tipo]');
    if (activa) s._crearTabla(activa.getAttribute('data-tipo'));

    document.querySelectorAll('.nav-tabs .nav-link[data-tipo]').forEach(function (t) {
      t.addEventListener('shown.bs.tab', function () {
        var tipo = this.getAttribute('data-tipo');
        if (!s.tablas[tipo]) s._crearTabla(tipo);
        else s.tablas[tipo].columns.adjust();
      });
    });

    $('.btn-config-doc').off('click.cat').on('click.cat', function () { Documento.configurar(); });

    $('.btn-nuevo').off('click.cat').on('click.cat', function () {
      s._formulario($(this).data('tipo'), null);
    });

    ['areas', 'tipos', 'categorias', 'subcategorias', 'prioridades', 'proyectos', 'proveedores'].forEach(function (tipo) {
      $('#tb-' + tipo).off('click.cat').on('click.cat', '.btn-editar, .btn-toggle, .btn-eliminar', function () {
        var dt = s.tablas[tipo];
        if (!dt) return;
        var fila = dt.row($(this).closest('tr')).data();
        if (!fila) return;
        if ($(this).hasClass('btn-editar'))        s._formulario(tipo, fila);
        else if ($(this).hasClass('btn-eliminar')) s._eliminar(tipo, fila);
        else                                       s._toggle(tipo, fila);
      });
    });
  },

  _crearTabla: function (tipo) {
    var def = this._def(tipo);
    if (!def) return;
    this.tablas[tipo] = DT.server('#tb-' + tipo, 'api/catalogos/listar.php',
      function () { return { tipo: tipo }; },
      def.cols
    );
  },

  _refrescar: function (tipo) {
    if (this.tablas[tipo]) this.tablas[tipo].draw(false);
  },

  /* ---------- formulario crear / editar ---------- */
  _formulario: function (tipo, fila) {
    var s = this, def = s._def(tipo);
    if (!def) return;

    // Proveedores: formulario propio (valida RUC/DNI y trae los datos de SUNAT/RENIEC)
    if (tipo === 'proveedores') {
      ProveedorForm.abrir(fila, { alGuardar: function () { Modal.cerrar(); s._refrescar('proveedores'); } });
      return;
    }

    // Proyectos y obras: el responsable se elige de la lista de personal
    if (def.campos.some(function (c) { return c.t === 'personal'; })) {
      Api.get('api/catalogos/select.php', { tipo: 'personal' }).then(function (lista) {
        s._abrirFormulario(tipo, def, fila, [], lista || []);
      });
      return;
    }

    var necesitaCategorias = def.campos.some(function (c) { return c.t === 'categoria'; });
    if (!necesitaCategorias) { s._abrirFormulario(tipo, def, fila, []); return; }

    Api.get('api/catalogos/select.php', { tipo: 'categorias' }).then(function (lista) {
      lista = lista || [];
      // al editar, conservar la categoria actual aunque este desactivada
      if (fila && fila.categoria_id && !lista.some(function (c) { return +c.id === +fila.categoria_id; })) {
        lista.push({ id: fila.categoria_id, texto: fila.categoria + ' (inactiva)' });
      }
      s._abrirFormulario(tipo, def, fila, lista);
    });
  },

  _abrirFormulario: function (tipo, def, fila, categorias, personal) {
    var s = this, esNuevo = !fila;

    var html = '<form id="form-catalogo" novalidate><div class="row g-3">';
    def.campos.forEach(function (c) {
      var val = fila ? fila[c.n] : null;
      var id = 'fc-' + c.n;
      var ctl = '';

      if (c.t === 'estado') {
        var v = val || 'activo';
        ctl = '<select class="form-select" id="' + id + '">' +
              '<option value="activo"' + (v === 'activo' ? ' selected' : '') + '>Activo</option>' +
              '<option value="inactivo"' + (v === 'inactivo' ? ' selected' : '') + '>Inactivo</option></select>';
      } else if (c.t === 'activo') {
        var on = esNuevo ? true : (parseInt(val, 10) === 1);
        ctl = '<select class="form-select" id="' + id + '">' +
              '<option value="1"' + (on ? ' selected' : '') + '>Activo</option>' +
              '<option value="0"' + (!on ? ' selected' : '') + '>Inactivo</option></select>';
      } else if (c.t === 'categoria') {
        ctl = '<select class="form-select" id="' + id + '"><option value="">Seleccione...</option>' +
              categorias.map(function (o) {
                return '<option value="' + esc(o.id) + '"' + (fila && +o.id === +val ? ' selected' : '') + '>' + esc(o.texto) + '</option>';
              }).join('') + '</select>';
      } else if (c.t === 'opciones') {
        var vo = val || c.opciones[0][0];
        ctl = '<select class="form-select" id="' + id + '">' + c.opciones.map(function (o) {
          return '<option value="' + esc(o[0]) + '"' + (vo === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
        }).join('') + '</select>';
      } else if (c.t === 'personal') {
        ctl = '<select class="form-select" id="' + id + '"><option value="">— Sin responsable —</option>' +
              (personal || []).map(function (o) {
                return '<option value="' + esc(o.id) + '"' + (fila && +o.id === +val ? ' selected' : '') + '>' + esc(o.texto) + '</option>';
              }).join('') + '</select>';
      } else if (c.t === 'date') {
        ctl = '<input type="date" class="form-control" id="' + id + '" value="' + esc(val == null ? '' : val) + '">';
      } else if (c.t === 'color') {
        ctl = '<input type="color" class="form-control form-control-color w-100" id="' + id + '" value="' + esc(val || c.def || '#6c757d') + '">';
      } else if (c.t === 'number') {
        ctl = '<input type="number" class="form-control" id="' + id + '" min="' + c.min + '" max="' + c.max + '" value="' + esc(val == null ? '' : val) + '">';
      } else {
        ctl = '<input type="text" class="form-control" id="' + id + '" maxlength="' + (c.max || 200) + '" value="' + esc(val == null ? '' : val) + '">';
      }

      html += '<div class="col-md-' + (c.w || 12) + '">' +
              '<label class="form-label" for="' + id + '">' + esc(c.l) + (c.req ? ' *' : '') + '</label>' +
              ctl + '<div class="invalid-feedback"></div>' + (c.ayuda ? '<div class="form-text">' + esc(c.ayuda) + '</div>' : '') + '</div>';
    });
    html += '</div><div class="text-end mt-4">' +
            '<button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>' +
            '<button type="submit" class="btn btn-primary" id="fc-guardar">Guardar</button></div></form>';

    Modal.abrir((esNuevo ? 'Nuevo ' : 'Editar ') + def.titulo.toLowerCase(), html, def.ancho || 'modal-md');
    setTimeout(function () { $('#form-catalogo input:first, #form-catalogo select:first').first().trigger('focus'); }, 300);

    $('#form-catalogo').off('submit').on('submit', function (e) {
      e.preventDefault();
      var datos = { tipo: tipo, id: fila ? fila.id : 0 };
      def.campos.forEach(function (c) { datos[c.n] = $('#fc-' + c.n).val(); });

      var $btn = $('#fc-guardar').prop('disabled', true);
      $('#form-catalogo .is-invalid').removeClass('is-invalid');

      Api.post('api/catalogos/guardar.php', datos).then(function (r) {
        Modal.cerrar();
        Toast.success((r && r.mensaje) || (esNuevo ? 'Registro creado correctamente.' : 'Registro actualizado correctamente.'));
        s._refrescar(tipo);
      }).catch(function (err) {
        $btn.prop('disabled', false);
        var errores = err && err.cuerpo && err.cuerpo.errors;
        if (errores) {
          Object.keys(errores).forEach(function (campo) {
            var $c = $('#fc-' + campo).addClass('is-invalid');
            $c.siblings('.invalid-feedback').text(errores[campo]);
          });
        }
      });
    });
  },

  /* ---------- activar / desactivar ---------- */
  _toggle: function (tipo, fila) {
    var s = this;
    Api.post('api/catalogos/toggle.php', { tipo: tipo, id: fila.id }).then(function () {
      Toast.success('Estado actualizado.');
      s._refrescar(tipo);
    });
  },

  /* ---------- eliminar (el servidor bloquea si esta en uso) ---------- */
  _eliminar: function (tipo, fila) {
    var s = this;
    Swal.fire({
      title: 'Eliminar este registro?',
      html: '<b>' + esc(fila.nombre) + '</b><br><small class="text-muted">Si esta en uso, el sistema no lo eliminara: use el boton de encendido para desactivarlo.</small>',
      icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Si, eliminar', cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626'
    }).then(function (r) {
      if (!r.isConfirmed) return;
      Api.post('api/catalogos/eliminar.php', { tipo: tipo, id: fila.id }).then(function () {
        Toast.success('Registro eliminado.');
        s._refrescar(tipo);
      });
    });
  }
};

App.registrar('catalogos', Catalogos);
