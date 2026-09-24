// ============================================================
// SIGTI - Modulo Usuarios del Sistema
// v3: CRUD + celular corporativo + FOTO + permisos por modulo
// ============================================================

const COL_ACCIONES_USUARIOS = {
  data: null, orderable: false, className: 'text-nowrap text-center',
  render: (v, type, fila) => {
    const propio = App.usuario && fila.id === App.usuario.id;
    let h =
      '<button class="btn btn-sm btn-outline-primary btn-editar me-1" title="Editar"><i class="bi bi-pencil"></i></button>' +
      '<button class="btn btn-sm btn-outline-warning btn-pass me-1" title="Restablecer contrasena"><i class="bi bi-key"></i></button>' +
      '<button class="btn btn-sm btn-outline-info btn-permisos me-1" title="Permisos por modulo"><i class="bi bi-shield-lock"></i></button>';
    if (!propio) {
      h += fila.estado === 'activo'
        ? '<button class="btn btn-sm btn-outline-danger btn-bloquear" title="Bloquear"><i class="bi bi-lock"></i></button>'
        : '<button class="btn btn-sm btn-outline-success btn-activar" title="Activar"><i class="bi bi-unlock"></i></button>';
    }
    return h;
  }
};

const Usuarios = {

  tabla: null,
  _fotoPendiente: null,

  init() {
    this.tabla = DT.server('#tb-usuarios', 'api/usuarios/listar.php', () => ({}), [
      { data: 'usuario', render: v => '<strong>' + esc(v) + '</strong>' },
      { data: 'nombre_completo' },
      { data: 'rol', render: v => '<span class="badge text-bg-light border">' + esc(v) + '</span>' },
      { data: 'personal', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
      { data: 'celular', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
      { data: 'correo', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
      { data: 'ultimo_acceso', render: v => v ? esc(v) : '<span class="text-muted">nunca</span>' },
      { data: 'estado', render: v => Badges.usuario(v) },
      COL_ACCIONES_USUARIOS
    ]);

    $('#btn-nuevo-usuario').on('click', () => this._abrirForm(null));

    $('#tb-usuarios').on('click', '.btn-editar, .btn-pass, .btn-permisos, .btn-bloquear, .btn-activar', function () {
      const fila = Usuarios.tabla.row($(this).closest('tr')).data();
      if (!fila) return;
      if      ($(this).hasClass('btn-editar'))   Usuarios._abrirForm(fila);
      else if ($(this).hasClass('btn-pass'))     Usuarios._abrirPass(fila);
      else if ($(this).hasClass('btn-permisos')) Usuarios._abrirPermisos(fila);
      else if ($(this).hasClass('btn-bloquear')) Usuarios._cambiarEstado(fila, 'bloqueado', 'bloquear');
      else                                       Usuarios._cambiarEstado(fila, 'activo', 'activar');
    });
  },

  // ---------------- Subida de foto (helper) ----------------
  _subirFoto(uid, file, luego) {
    const fd = new FormData();
    fd.append('accion', 'subir');
    fd.append('id', uid);
    fd.append('foto', file);
    $.ajax({
      url: BASE_URL + 'api/usuarios/foto.php',
      type: 'POST', data: fd, contentType: false, processData: false,
      headers: { 'X-CSRF-Token': CSRF.token },
      beforeSend: () => Loader.show(), complete: () => Loader.hide(),
      success: res => {
        if (res.success) { Toast.success(res.message); if (luego) luego(); }
        else Toast.error(res.message || 'Error al subir la foto.');
      },
      error: () => Toast.error('Error de conexion al subir la foto.')
    });
  },

  // ================= FORMULARIO =================
  _abrirForm(fila) {
    const esNuevo = !fila;
    const propio  = !esNuevo && App.usuario && fila.id === App.usuario.id;
    this._fotoPendiente = null;

    Promise.all([
      Api.get('api/catalogos/select.php', { tipo: 'roles' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'personal' }).catch(() => [])
    ]).then(([roles, personal]) => {

      const val = c => esNuevo ? '' : (fila[c] ?? '');
      const uid = esNuevo ? 0 : fila.id;

      const html = `
      <div class="d-flex align-items-center gap-3 mb-3">
        <img id="fu-foto" src="${BASE_URL}api/usuarios/foto.php?id=${uid}&t=${Date.now()}"
             style="width:74px;height:74px;border-radius:50%;object-fit:cover;border:2px solid #e5e9f0;background:#f1f5f9">
        <div class="flex-fill">
          <div class="small text-muted mb-1">
            Foto del usuario (PNG/JPG, max. 2 MB) — se muestra al iniciar sesion y en el menu superior.
            ${esNuevo ? 'Al crear el usuario la foto se sube automaticamente al guardar.' : ''}
          </div>
          <div class="d-flex gap-2">
            <label class="btn btn-outline-primary btn-sm mb-0">
              <i class="bi bi-camera"></i> ${esNuevo ? 'Elegir foto' : 'Cambiar foto'}
              <input type="file" id="fu-foto-file" accept=".png,.jpg,.jpeg" hidden>
            </label>
            ${!esNuevo ? '<button type="button" class="btn btn-outline-danger btn-sm" id="fu-foto-quitar"><i class="bi bi-trash"></i> Quitar</button>' : ''}
          </div>
        </div>
      </div>

      <form id="form-usuario" autocomplete="off" data-id="${uid}">
        <div class="row">
          <div class="col-md-6 mb-3" data-campo="usuario">
            <label class="form-label">Usuario <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="fu-usuario" value="${esc(val('usuario'))}"
                   maxlength="50" placeholder="jperez" ${propio ? 'disabled' : ''}>
          </div>
          <div class="col-md-6 mb-3" data-campo="nombre_completo">
            <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="fu-nombre" value="${esc(val('nombre_completo'))}" maxlength="120">
          </div>
          <div class="col-md-6 mb-3" data-campo="rol_id">
            <label class="form-label">Rol <span class="text-danger">*</span></label>
            <select class="form-select" id="fu-rol" ${propio ? 'disabled' : ''}>
              ${roles.map(r =>
                `<option value="${r.id}" ${+val('rol_id') === +r.id ? 'selected' : ''}>${esc(r.texto)}</option>`).join('')}
            </select>
          </div>
          <div class="col-md-6 mb-3" data-campo="personal_id">
            <label class="form-label">Personal vinculado</label>
            <select class="form-select" id="fu-personal">
              <option value="">— Sin vinculo —</option>
              ${personal.map(p =>
                `<option value="${p.id}" ${+val('personal_id') === +p.id ? 'selected' : ''}>${esc(p.texto)} (${esc(p.dni)})</option>`).join('')}
            </select>
          </div>
          <div class="col-md-6 mb-3" data-campo="correo">
            <label class="form-label">Correo</label>
            <input type="email" class="form-control" id="fu-correo" value="${esc(val('correo'))}" maxlength="120">
          </div>
          <div class="col-md-6 mb-3" data-campo="celular">
            <label class="form-label">Celular corporativo</label>
            <input type="text" class="form-control" id="fu-celular" value="${esc(val('celular'))}"
                   maxlength="20" placeholder="Ej: 997 520 698">
          </div>
          <div class="col-md-6 mb-3" data-campo="estado">
            <label class="form-label">Estado <span class="text-danger">*</span></label>
            <select class="form-select" id="fu-estado" ${propio ? 'disabled' : ''}>
              <option value="activo"    ${val('estado') === 'activo'    || esNuevo ? 'selected' : ''}>Activo</option>
              <option value="bloqueado" ${val('estado') === 'bloqueado' ? 'selected' : ''}>Bloqueado</option>
              <option value="inactivo"  ${val('estado') === 'inactivo'  ? 'selected' : ''}>Inactivo</option>
            </select>
          </div>
          <div class="col-md-6 mb-3" data-campo="password">
            <label class="form-label">Contrasena ${esNuevo ? '<span class="text-danger">*</span>' : ''}</label>
            <input type="password" class="form-control" id="fu-password" maxlength="100"
                   placeholder="${esNuevo ? 'Minimo 8 caracteres' : 'Dejar vacio para mantener la actual'}">
          </div>
        </div>

        ${propio ? '<div class="alert alert-info py-2 small mb-0">No puede cambiar su propio usuario, rol ni estado.</div>' : ''}
        <div class="d-flex justify-content-end gap-2 mt-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-guardar-usuario"><i class="bi bi-check-lg"></i> Guardar</button>
        </div>
      </form>`;

      Modal.abrir(esNuevo ? 'Nuevo usuario' : 'Editar usuario — ' + fila.usuario, html);

      // ---- foto ----
      $('#fu-foto-file').on('change', function () {
        if (!this.files[0]) return;
        if (uid > 0) {
          Usuarios._subirFoto(uid, this.files[0], () => {
            $('#fu-foto').attr('src', BASE_URL + 'api/usuarios/foto.php?id=' + uid + '&t=' + Date.now());
          });
          $(this).val('');
        } else {
          // usuario nuevo: vista previa local y se sube al guardar
          Usuarios._fotoPendiente = this.files[0];
          const r = new FileReader();
          r.onload = e => $('#fu-foto').attr('src', e.target.result);
          r.readAsDataURL(this.files[0]);
        }
      });
      if (!esNuevo) {
        $('#fu-foto-quitar').on('click', () => {
          Api.post('api/usuarios/foto.php', { accion: 'quitar', id: uid }).then(() => {
            $('#fu-foto').attr('src', BASE_URL + 'api/usuarios/foto.php?id=' + uid + '&t=' + Date.now());
          });
        });
      }

      $('#btn-guardar-usuario').on('click', () => this._guardar());
      $('#form-usuario').on('submit', e => { e.preventDefault(); this._guardar(); });
    });
  },

  _guardar() {
    const esNuevo = +($('#form-usuario').data('id') || 0) === 0;
    const uid = +($('#form-usuario').data('id') || 0);
    const fotoPend = this._fotoPendiente;

    const datos = {
      id:              uid,
      usuario:         $.trim($('#fu-usuario').val() || ''),
      nombre_completo: $.trim($('#fu-nombre').val() || ''),
      rol_id:          $('#fu-rol').val() || '',
      personal_id:     $('#fu-personal').val() || '',
      correo:          $.trim($('#fu-correo').val() || ''),
      celular:         $.trim($('#fu-celular').val() || ''),
      estado:          $('#fu-estado').val() || 'activo',
      password:        $('#fu-password').val() || ''
    };

    Api.post('api/usuarios/guardar.php', datos)
      .then(data => {
        Modal.cerrar();
        this.tabla.draw();
        // si era nuevo y eligio foto: subirla ya con su id
        if (esNuevo && fotoPend && data && data.id) {
          this._fotoPendiente = null;
          Usuarios._subirFoto(data.id, fotoPend, () => Usuarios.tabla.draw());
        }
      })
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-usuario', err.cuerpo.errors);
      });
  },

  // ================= PERMISOS POR MODULO =================
  _abrirPermisos(fila) {
    $.get(BASE_URL + 'api/usuarios/permisos.php?id=' + fila.id, res => {
      if (!res.success) { Toast.error(res.message || 'Error'); return; }

      const grupos = {};
      res.data.permisos.forEach(p => { (grupos[p.modulo] = grupos[p.modulo] || []).push(p); });

      let html = `
      <div class="alert alert-light border py-2 small">
        <i class="bi bi-shield-lock text-primary"></i>
        Permisos de <b>${esc(fila.usuario)}</b> — rol base: <b>${esc(res.data.rol)}</b><br>
        <b>Heredado</b> = lo que da el rol · <b>Concedido</b> = se otorga aunque el rol no lo tenga ·
        <b>Revocado</b> = se quita aunque el rol lo tenga.
        <span class="text-muted">Aplica cuando el usuario cierre sesion y reingrese.</span>
      </div>`;

      Object.keys(grupos).forEach(mod => {
        html += `<div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <b class="text-uppercase small">${esc(mod)}</b>
            <span class="btn-group btn-group-sm">
              <button type="button" class="btn btn-outline-success pm-todo" data-mod="${esc(mod)}" data-est="concedido">Todo</button>
              <button type="button" class="btn btn-outline-secondary pm-todo" data-mod="${esc(mod)}" data-est="heredado">Reset</button>
              <button type="button" class="btn btn-outline-danger pm-todo" data-mod="${esc(mod)}" data-est="revocado">Nada</button>
            </span>
          </div>`;
        grupos[mod].forEach(p => {
          const ov = p.override === null ? 'heredado' : (+p.override === 1 ? 'concedido' : 'revocado');
          const rolTxt = +p.en_rol === 1 ? 'si' : 'no';
          html += `<div class="d-flex align-items-center justify-content-between py-1">
            <span class="small">${esc(p.descripcion || (p.modulo + '.' + p.accion))}
              <code class="text-muted" style="font-size:10px">${esc(p.modulo + '.' + p.accion)}</code></span>
            <select class="form-select form-select-sm pm-estado" data-pid="${p.id}" data-mod="${esc(mod)}" style="width:185px">
              <option value="heredado"  ${ov === 'heredado'  ? 'selected' : ''}>Heredado (rol: ${rolTxt})</option>
              <option value="concedido" ${ov === 'concedido' ? 'selected' : ''}>Concedido</option>
              <option value="revocado"  ${ov === 'revocado'  ? 'selected' : ''}>Revocado</option>
            </select>
          </div>`;
        });
        html += '</div>';
      });

      html += `
      <div class="d-flex justify-content-end gap-2 mt-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="upm-guardar"><i class="bi bi-shield-check"></i> Guardar permisos</button>
      </div>`;

      Modal.abrir('Permisos por modulo — ' + fila.usuario, html, 'modal-lg');

      $('#modal-general-cuerpo').off('click', '.pm-todo').on('click', '.pm-todo', function () {
        $('.pm-estado[data-mod="' + $(this).data('mod') + '"]').val($(this).data('est'));
      });

      $('#upm-guardar').on('click', () => {
        const lista = $('.pm-estado').map((i, el) => ({
          permiso_id: $(el).data('pid'),
          estado: $(el).val()
        })).get();
        Api.post('api/usuarios/permisos.php', {
          accion: 'guardar', id: fila.id, permisos: JSON.stringify(lista)
        }).then(() => { Modal.cerrar(); });
      });
    }).fail(() => Toast.error('Error al cargar los permisos.'));
  },

  // ================= CONTRASENA =================
  _abrirPass(fila) {
    const html = `
      <form id="form-pass" autocomplete="off" data-id="${fila.id}">
        <div class="alert alert-secondary py-2 small">
          Restablecer la contrasena de <strong>${esc(fila.usuario)}</strong>.
          Se reinician los intentos fallidos automaticamente.
        </div>
        <div class="mb-3" data-campo="password">
          <label class="form-label">Nueva contrasena <span class="text-danger">*</span></label>
          <input type="password" class="form-control" id="fp-password" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label">Repetir contrasena <span class="text-danger">*</span></label>
          <input type="password" class="form-control" id="fp-password2" maxlength="100">
        </div>
        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-guardar-pass"><i class="bi bi-key"></i> Guardar contrasena</button>
        </div>
      </form>`;

    Modal.abrir('Cambiar contrasena — ' + fila.usuario, html);

    $('#btn-guardar-pass').on('click', () => {
      const p1 = $('#fp-password').val(), p2 = $('#fp-password2').val();
      if (p1.length < 8) { Toast.error('La contrasena debe tener al menos 8 caracteres.'); return; }
      if (p1 !== p2)     { Toast.error('Las contrasenas no coinciden.'); return; }
      Api.post('api/usuarios/password.php', { id: fila.id, password: p1 })
        .then(() => Modal.cerrar());
    });
  },

  // ================= BLOQUEAR / ACTIVAR =================
  _cambiarEstado(fila, estado, verbo) {
    Swal.fire({
      title: '¿' + verbo.charAt(0).toUpperCase() + verbo.slice(1) + ' a ' + esc(fila.usuario) + '?',
      text: estado === 'bloqueado'
        ? 'El usuario no podra iniciar sesion hasta que se active nuevamente.'
        : 'El usuario podra iniciar sesion normalmente.',
      icon: 'question', showCancelButton: true,
      confirmButtonText: 'Si, continuar', cancelButtonText: 'Cancelar', confirmButtonColor: '#2563eb'
    }).then(r => {
      if (!r.isConfirmed) return;
      Api.post('api/usuarios/estado.php', { id: fila.id, estado: estado })
        .then(() => this.tabla.draw());
    });
  }
};

App.registrar('usuarios', Usuarios);