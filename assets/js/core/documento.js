/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - DNI / RUC: validar, traer datos y formulario de proveedor
   Servidor: api/catalogos/consulta.php  (SUNAT / RENIEC con token)
             api/configuracion/consulta-documentos.php (token)
   Usado por: Configuracion > Proveedores, Mantenimiento (proveedor nuevo),
              Personal (alta).
   ============================================================ */
const Documento = {

  _n: 0,

  /* ---------- validacion (misma regla que core/Documento.php) ---------- */
  limpiar: function (v) { return String(v == null ? '' : v).replace(/\D+/g, ''); },
  largo: function (tipo) { return tipo === 'dni' ? 8 : 11; },
  errorDni: function (n) { return /^\d{8}$/.test(n) ? null : 'El DNI debe tener 8 dígitos.'; },
  errorRuc: function (n) {
    if (!/^\d{11}$/.test(n)) return 'El RUC debe tener 11 dígitos.';
    if (['10', '15', '16', '17', '20'].indexOf(n.substr(0, 2)) < 0) return 'El RUC debe empezar con 10, 15, 16, 17 o 20.';
    var p = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2], s = 0, i;
    for (i = 0; i < 10; i++) s += (+n.charAt(i)) * p[i];
    var dv = 11 - (s % 11);
    if (dv === 10) dv = 0; else if (dv === 11) dv = 1;
    return dv === +n.charAt(10) ? null : 'RUC no válido: el dígito verificador no coincide. Revise el número.';
  },
  error: function (tipo, n) { return tipo === 'dni' ? this.errorDni(n) : this.errorRuc(n); },

  /* ---------- consulta al servidor (sin cargador global ni avisos emergentes) ---------- */
  consultar: function (tipo, numero, extra) {
    return new Promise(function (res, rej) {
      $.ajax({
        url: BASE_URL + 'api/catalogos/consulta.php', type: 'GET', dataType: 'json',
        data: $.extend({ tipo: tipo, numero: numero }, extra || {}),
        headers: { 'X-CSRF-Token': CSRF.token },
        success: function (r) {
          if (r && r.success) res(r.data); else rej({ mensaje: (r && r.message) || 'Error' });
        },
        error: function (xhr) {
          var c = null;
          try { c = JSON.parse(xhr.responseText); } catch (e) {}
          if (xhr.status === 401) { if (App.usuario) App.sesionExpirada(); rej({ status: 401, mensaje: 'Sesión expirada.' }); return; }
          rej({
            status: xhr.status,
            mensaje: (c && c.errors && c.errors.numero) || (c && c.message) || ('Error (' + xhr.status + ')')
          });
        }
      });
    });
  },

  /* ---------- campo con lupa ----------
     HTML del campo; despues de insertarlo en el DOM llamar a Documento.activar(...) */
  campoHtml: function (id, valor, largo) {
    return '<div class="input-group">' +
      '<input type="text" inputmode="numeric" autocomplete="off" class="form-control" id="' + id + '" maxlength="' + (largo || 11) + '" value="' + esc(valor == null ? '' : valor) + '">' +
      '<button type="button" class="btn btn-outline-primary" id="' + id + '-buscar" title="Validar y buscar los datos"><i class="bi bi-search"></i></button>' +
      '</div><div class="form-text" id="' + id + '-msg"></div>';
  },

  /* o = { id, tipo:'dni'|'ruc'|fn, contexto:'proveedor'|'personal', excluir:fn, alDatos:fn(remoto, respuesta) } */
  activar: function (o) {
    var s = this, $i = $('#' + o.id), $b = $('#' + o.id + '-buscar'), $m = $('#' + o.id + '-msg');
    var seq = 0, ult = '';
    var tipo = function () { return typeof o.tipo === 'function' ? o.tipo() : o.tipo; };
    var msg = function (cls, html) {
      $m.removeClass('text-danger text-success text-warning text-muted').addClass(cls).html(html);
    };
    var ocupado = function (si) {
      $b.prop('disabled', si).find('i').attr('class', si ? 'spinner-border spinner-border-sm' : 'bi bi-search');
    };

    function mostrar(t, d, manual) {
      var partes = [], cls = 'text-success', r = d.remoto;
      if (o.contexto === 'personal') {
        if (d.personal) {
          cls = 'text-warning';
          partes.push('<i class="bi bi-exclamation-triangle"></i> Ya está registrado en Personal: <b>' +
            esc((d.personal.nombres || '') + ' ' + (d.personal.apellidos || '')) + '</b>');
        }
      } else if (d.proveedor) {
        cls = 'text-warning';
        partes.push('<i class="bi bi-exclamation-triangle"></i> Ya existe el proveedor <b>' + esc(d.proveedor.nombre) + '</b> con este ' + t.toUpperCase() + '.');
      }
      if (r) {
        if (o.alDatos) o.alDatos(r, d);
        partes.unshift('<i class="bi bi-cloud-check"></i> Datos de ' + (t === 'ruc' ? 'SUNAT' : 'RENIEC') + ' cargados' + (r.desde_cache ? ' (consulta guardada)' : '') + '.');
        if (t === 'ruc' && r.estado && !r.activo_habido) {
          cls = 'text-warning';
          partes.push('SUNAT indica: <b>' + esc(r.estado) + ' / ' + esc(r.condicion) + '</b>. Verifique antes de contratar.');
        }
      } else if (d.aviso) {
        if (d.aviso.codigo === 'no_configurado') {
          if (cls === 'text-success') cls = 'text-muted';
          partes.unshift('Número válido. Complete los datos a mano' +
            (d.puede_configurar ? ' (o configure el servicio en Configuración › Proveedores › «Servicio de consulta DNI/RUC» para traerlos solos).' : '.'));
        } else {
          if (cls === 'text-success') cls = 'text-warning';
          partes.unshift('Número válido, pero no se pudieron traer los datos: ' + esc(d.aviso.mensaje));
        }
      }
      msg(cls, partes.join('<br>'));

      // Al pulsar la lupa sin servicio activo: explicarlo claro (con salida directa a configurarlo)
      if (manual && !r && d.aviso && (d.aviso.codigo === 'no_configurado' || d.aviso.codigo === 'token_invalido') && typeof Swal !== 'undefined') {
        var puede = !!d.puede_configurar;
        Swal.fire({
          icon: 'info', title: 'Falta activar la búsqueda automática',
          html: 'El número es válido, pero para <b>traer los datos de ' + (t === 'ruc' ? 'SUNAT' : 'RENIEC') + '</b> hace falta el token del servicio de consulta.<br>' +
                (puede ? '<small class="text-muted">Se configura una sola vez. Si sigue, se cerrará este formulario.</small>'
                       : '<small class="text-muted">Pida a un administrador que lo configure (Configuración › Proveedores).</small>'),
          showCancelButton: puede, confirmButtonText: puede ? 'Configurar ahora' : 'Entendido', cancelButtonText: 'Llenar a mano'
        }).then(function (x) {
          if (puede && x.isConfirmed) {
            Modal.cerrar(); Router.ir('configuracion/principal');
            setTimeout(function () { Documento.configurar(); }, 1200);
          }
        });
      }
    }

    function consultar(t, n, manual) {
      ult = n;
      var ticket = ++seq;
      ocupado(true);
      msg('text-muted', 'Consultando datos…');
      s.consultar(t, n, { excluir: o.excluir ? o.excluir() : 0 }).then(function (d) {
        if (ticket !== seq) return;               // llego tarde: el usuario ya cambio el numero
        ocupado(false); mostrar(t, d, manual);
      }).catch(function (e) {
        if (ticket !== seq) return;
        ocupado(false); $i.removeClass('is-valid').addClass('is-invalid');
        msg('text-danger', esc(e.mensaje));
      });
    }

    function validar(forzar) {
      var t = tipo(), n = s.limpiar($i.val());
      if ($i.val() !== n) $i.val(n);
      $i.removeClass('is-valid is-invalid');
      seq++;                                      // descarta respuestas pendientes
      ocupado(false);
      if (!n) { ult = ''; msg('text-muted', ''); return; }
      var e = s.error(t, n);
      if (e) {
        ult = '';
        if (forzar || n.length >= s.largo(t)) { $i.addClass('is-invalid'); msg('text-danger', esc(e)); }
        else msg('text-muted', n.length + ' / ' + s.largo(t) + ' dígitos');
        return;
      }
      $i.addClass('is-valid');
      msg('text-success', '<i class="bi bi-check-circle"></i> ' + t.toUpperCase() + ' válido');
      if (!forzar && n === ult) return;
      consultar(t, n, forzar);
    }

    $i.off('.doc').on('input.doc', function () { validar(false); })
      .on('keydown.doc', function (e) { if (e.key === 'Enter') { e.preventDefault(); validar(true); } });
    $b.off('.doc').on('click.doc', function () { validar(true); });
    return { revisar: function () { validar(false); }, buscar: function () { validar(true); } };
  },

  /* ---------- configuracion del servicio (token) ---------- */
  configurar: function () {
    Api.get('api/configuracion/consulta-documentos.php').then(function (c) {
      var opts = '<option value="">— Sin servicio (solo validar el número) —</option>' +
        Object.keys(c.servicios).map(function (k) {
          return '<option value="' + esc(k) + '"' + (c.servicio === k ? ' selected' : '') + '>' + esc(c.servicios[k]) + '</option>';
        }).join('');
      var html = '<form id="form-cdoc" onsubmit="return false" novalidate>' +
        '<div class="alert alert-light border small mb-3"><i class="bi bi-info-circle text-primary"></i> ' +
        'Para traer automáticamente los datos desde <b>SUNAT</b> (RUC) y <b>RENIEC</b> (DNI) se usa un servicio autorizado. ' +
        'Cree una cuenta gratuita en <a href="https://apiperu.dev" target="_blank" rel="noopener">apiperu.dev</a> o ' +
        '<a href="https://decolecta.com" target="_blank" rel="noopener">decolecta.com</a>, copie su <b>token</b> y péguelo aquí. ' +
        'Los planes gratuitos tienen un límite de consultas; las ya hechas se guardan 7 días.</div>' +
        '<div class="row g-3">' +
        '<div class="col-md-5"><label class="form-label" for="cd-servicio">Servicio</label><select class="form-select" id="cd-servicio">' + opts + '</select>' +
        '<div class="invalid-feedback"></div></div>' +
        '<div class="col-md-7"><label class="form-label" for="cd-token">Token</label>' +
        '<input type="password" class="form-control" id="cd-token" autocomplete="new-password" placeholder="' +
        (c.tiene_token ? 'Guardado (' + esc(c.token_mask) + ') — vacío para conservarlo' : 'Pegue aquí su token') + '">' +
        '<div class="invalid-feedback"></div>' +
        (c.tiene_token ? '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="cd-quitar"><label class="form-check-label small" for="cd-quitar">Borrar el token guardado</label></div>' : '') +
        '</div>' +
        '<div class="col-12"><label class="form-label" for="cd-cacert">Ruta de cacert.pem <span class="text-muted">(opcional)</span></label>' +
        '<input type="text" class="form-control" id="cd-cacert" maxlength="250" value="' + esc(c.cacert) + '" placeholder="Ej: C:\\xampp\\php\\extras\\ssl\\cacert.pem">' +
        '<div class="form-text">Solo si al probar aparece un error de certificado HTTPS (común en XAMPP).</div><div class="invalid-feedback"></div></div>' +
        '</div>' +
        '<hr><div class="row g-2 align-items-end">' +
        '<div class="col-md-3"><label class="form-label small" for="cd-ptipo">Probar con</label><select class="form-select form-select-sm" id="cd-ptipo"><option value="ruc">RUC</option><option value="dni">DNI</option></select></div>' +
        '<div class="col-md-5"><label class="form-label small" for="cd-pnum">Número</label><input class="form-control form-control-sm" id="cd-pnum" inputmode="numeric" maxlength="11"></div>' +
        '<div class="col-md-4"><button type="button" class="btn btn-outline-secondary btn-sm w-100" id="cd-probar"><i class="bi bi-plug"></i> Probar conexión</button></div>' +
        '<div class="col-12 small" id="cd-resultado"><span class="text-muted">Guarde primero y luego pruebe con un número real.</span></div></div>' +
        '<div class="text-end mt-4"><button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cerrar</button>' +
        '<button type="button" class="btn btn-primary" id="cd-guardar">Guardar</button></div></form>';
      Modal.abrir('Servicio de consulta DNI / RUC', html, 'modal-lg');

      $('#cd-ptipo').on('change', function () { $('#cd-pnum').attr('maxlength', Documento.largo($(this).val())); });

      $('#cd-guardar').on('click', function () {
        var $b = $(this).prop('disabled', true);
        $('#form-cdoc .is-invalid').removeClass('is-invalid');
        Api.post('api/configuracion/consulta-documentos.php', {
          accion: 'guardar', servicio: $('#cd-servicio').val(), token: $('#cd-token').val(),
          cacert: $('#cd-cacert').val(), quitar_token: $('#cd-quitar').is(':checked') ? '1' : '0'
        }).then(function () {
          Toast.success('Configuración guardada.');
          Modal.cerrar();
        }).catch(function (err) {
          $b.prop('disabled', false);
          var er = err && err.cuerpo && err.cuerpo.errors;
          if (er) Object.keys(er).forEach(function (k) {
            var $c = $('#cd-' + k).addClass('is-invalid');
            $c.siblings('.invalid-feedback').text(er[k]);
          });
        });
      });

      $('#cd-probar').on('click', function () {
        var t = $('#cd-ptipo').val(), n = Documento.limpiar($('#cd-pnum').val()), e = Documento.error(t, n);
        if (e) { $('#cd-resultado').html('<span class="text-danger">' + esc(e) + '</span>'); return; }
        var $b = $(this).prop('disabled', true);
        $('#cd-resultado').html('<span class="text-muted">Probando…</span>');
        Api.post('api/configuracion/consulta-documentos.php', { accion: 'probar', tipo: t, numero: n }).then(function (r) {
          var d = r.remoto;
          $('#cd-resultado').html('<span class="text-success"><i class="bi bi-check-circle"></i> Funciona.</span> ' +
            esc(d.nombre) + (d.direccion ? ' — ' + esc(d.direccion) : ''));
        }).catch(function (err) {
          $('#cd-resultado').html('<span class="text-danger">' + esc((err && err.cuerpo && err.cuerpo.message) || 'No se pudo probar.') + '</span>');
        }).then(function () { $b.prop('disabled', false); });
      });
    });
  }
};

/* ============================================================
   Formulario de proveedor (el mismo en Configuracion y en Mantenimiento)
   ProveedorForm.abrir(fila|null, { alGuardar:fn(id), alCancelar:fn })
   No cierra la ventana: lo decide quien lo llama.
   ============================================================ */
const ProveedorForm = {
  abrir: function (fila, o) {
    o = o || {};
    var esNuevo = !fila, f = fila || {};
    var v = function (k) { return f[k] == null ? '' : f[k]; };
    var tipo = (f.tipo_documento === 'dni') ? 'dni' : 'ruc';
    var docOriginal = Documento.limpiar(f.ruc) + '|' + tipo;

    var html = '<form id="form-prov" onsubmit="return false" novalidate><div class="row g-3">' +
      '<div class="col-md-3"><label class="form-label" for="pv-tipo">Documento</label>' +
      '<select class="form-select" id="pv-tipo"><option value="ruc"' + (tipo === 'ruc' ? ' selected' : '') + '>RUC</option>' +
      '<option value="dni"' + (tipo === 'dni' ? ' selected' : '') + '>DNI</option></select></div>' +
      '<div class="col-md-' + (esNuevo ? 9 : 5) + '"><label class="form-label" for="pv-doc" id="pv-doc-lbl">Número de ' + tipo.toUpperCase() + '</label>' +
      Documento.campoHtml('pv-doc', v('ruc'), Documento.largo(tipo)) + '</div>' +
      (esNuevo ? '' :
        '<div class="col-md-4"><label class="form-label" for="pv-estado">Estado</label><select class="form-select" id="pv-estado">' +
        '<option value="activo"' + (f.estado !== 'inactivo' ? ' selected' : '') + '>Activo</option>' +
        '<option value="inactivo"' + (f.estado === 'inactivo' ? ' selected' : '') + '>Inactivo</option></select></div>') +
      '<div class="col-12"><label class="form-label" for="pv-nombre">Razón social / nombre *</label>' +
      '<input class="form-control" id="pv-nombre" maxlength="120" value="' + esc(v('nombre')) + '"><div class="invalid-feedback"></div></div>' +
      '<div class="col-12"><label class="form-label" for="pv-direccion">Dirección</label>' +
      '<input class="form-control" id="pv-direccion" maxlength="200" value="' + esc(v('direccion')) + '"><div class="invalid-feedback"></div></div>' +
      '<div class="col-md-4"><label class="form-label" for="pv-contacto">Contacto</label>' +
      '<input class="form-control" id="pv-contacto" maxlength="100" value="' + esc(v('contacto')) + '"><div class="invalid-feedback"></div></div>' +
      '<div class="col-md-4"><label class="form-label" for="pv-telefono">Teléfono</label>' +
      '<input class="form-control" id="pv-telefono" maxlength="30" value="' + esc(v('telefono')) + '"><div class="invalid-feedback"></div></div>' +
      '<div class="col-md-4"><label class="form-label" for="pv-correo">Correo</label>' +
      '<input type="email" class="form-control" id="pv-correo" maxlength="120" value="' + esc(v('correo')) + '"><div class="invalid-feedback"></div></div>' +
      '<div class="col-12"><label class="form-label" for="pv-especialidad">Especialidad</label>' +
      '<input class="form-control" id="pv-especialidad" maxlength="120" placeholder="Ej: Reparación de impresoras" value="' + esc(v('especialidad')) + '"><div class="invalid-feedback"></div></div>' +
      '</div><div class="text-end mt-4"><button type="button" class="btn btn-secondary me-2" id="pv-cancelar">Cancelar</button>' +
      '<button type="button" class="btn btn-primary" id="pv-guardar"><i class="bi bi-check-lg"></i> Guardar proveedor</button></div></form>';

    Modal.abrir((esNuevo ? 'Nuevo' : 'Editar') + ' proveedor', html, 'modal-lg');

    var marcar = function (campo, texto) {
      var $c = $('#' + campo).addClass('is-invalid');
      var $fb = $c.siblings('.invalid-feedback');
      if ($fb.length) $fb.text(texto); else $('#' + campo + '-msg').removeClass('text-muted text-success').addClass('text-danger').text(texto);
    };

    // lo que se rellenó solo desde SUNAT/RENIEC (para no dejar datos de otro documento al cambiar)
    var auto = { nombre: null, direccion: null };
    var limpiarAuto = function () {
      if (auto.nombre !== null && $('#pv-nombre').val() === auto.nombre) $('#pv-nombre').val('');
      if (auto.direccion !== null && $('#pv-direccion').val() === auto.direccion) $('#pv-direccion').val('');
      auto = { nombre: null, direccion: null };
    };

    var ctl = Documento.activar({
      id: 'pv-doc', contexto: 'proveedor',
      tipo: function () { return $('#pv-tipo').val(); },
      excluir: function () { return f.id || 0; },
      alDatos: function (r) {
        limpiarAuto();
        if (r.nombre) { $('#pv-nombre').val(r.nombre).removeClass('is-invalid'); auto.nombre = r.nombre; }
        if (r.direccion) { $('#pv-direccion').val(r.direccion); auto.direccion = r.direccion; }
      }
    });

    $('#pv-tipo').on('change', function () {
      var t = $(this).val();
      limpiarAuto();
      $('#pv-doc').attr('maxlength', Documento.largo(t));
      $('#pv-doc-lbl').text('Número de ' + t.toUpperCase());
      ctl.revisar();
    });

    setTimeout(function () { $('#pv-doc').trigger('focus'); }, 300);

    $('#pv-cancelar').on('click', function () { if (o.alCancelar) o.alCancelar(); else Modal.cerrar(); });

    $('#pv-guardar').on('click', function () {
      $('#form-prov .is-invalid').removeClass('is-invalid');
      var t = $('#pv-tipo').val(), num = Documento.limpiar($('#pv-doc').val());
      var d = {
        tipo: 'proveedores', id: esNuevo ? 0 : f.id, tipo_documento: t, ruc: num,
        nombre: $.trim($('#pv-nombre').val()), direccion: $.trim($('#pv-direccion').val()),
        contacto: $.trim($('#pv-contacto').val()), telefono: $.trim($('#pv-telefono').val()),
        correo: $.trim($('#pv-correo').val()), especialidad: $.trim($('#pv-especialidad').val()),
        estado: esNuevo ? 'activo' : $('#pv-estado').val()
      };
      if (d.nombre.length < 3) { marcar('pv-nombre', 'Ingrese la razón social o nombre (mínimo 3 letras).'); return; }
      // un documento antiguo sin cambios no se re-valida (el servidor aplica la misma regla)
      if (num && (num + '|' + t) !== docOriginal) {
        var e = Documento.error(t, num);
        if (e) { marcar('pv-doc', e); return; }
      }
      var $b = $(this).prop('disabled', true);
      Api.post('api/catalogos/guardar.php', d).then(function (r) {
        Toast.success(esNuevo ? 'Proveedor registrado.' : 'Proveedor actualizado.');
        if (o.alGuardar) o.alGuardar(r && r.id ? r.id : (esNuevo ? null : f.id));
      }).catch(function (err) {
        $b.prop('disabled', false);
        var er = err && err.cuerpo && err.cuerpo.errors;
        if (er) {
          var mapa = { ruc: 'pv-doc', nombre: 'pv-nombre', direccion: 'pv-direccion', contacto: 'pv-contacto',
                       telefono: 'pv-telefono', correo: 'pv-correo', especialidad: 'pv-especialidad', estado: 'pv-estado' };
          Object.keys(er).forEach(function (k) { if (mapa[k]) marcar(mapa[k], er[k]); });
        }
      });
    });
  }
};
