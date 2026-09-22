// ============================================================
// SIGTI - Modulo Usuarios del Sistema (vista: usuarios/lista)
// ============================================================

const COL_ACCIONES_USUARIOS = {
  data: null, orderable: false, className: 'text-nowrap text-center',
  render: (v, type, fila) => {
    const propio = App.usuario && fila.id === App.usuario.id;
    let h =
      '<button class="btn btn-sm btn-outline-primary btn-editar me-1" title="Editar"><i class="bi bi-pencil"></i></button>' +
      '<button class="btn btn-sm btn-outline-warning btn-pass me-1" title="Restablecer contrasena"><i class="bi bi-key"></i></button>';
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

  init() {
    this.tabla = DT.server('#tb-usuarios', 'api/usuarios/listar.php', () => ({}), [
      { data: 'usuario', render: v => '<strong>' + esc(v) + '</strong>' },
      { data: 'nombre_completo' },
      { data: 'rol', render: v => '<span class="badge text-bg-light border">' + esc(v) + '</span>' },
      { data: 'personal', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
      { data: 'correo', render: v => v ? esc(v) : '<span class="text-muted">—</span>' },
      { data: 'ultimo_acceso', render: v => v ? esc(v) : '<span class="text-muted">nunca</span>' },
      { data: 'estado', render: v => Badges.usuario(v) },
      COL_ACCIONES_USUARIOS
    ]);

    $('#btn-nuevo-usuario').on('click', () => this._abrirForm(null));

    $('#tb-usuarios').on('click', '.btn-editar, .btn-pass, .btn-bloquear, .btn-activar', function () {
      const fila = Usuarios.tabla.row($(this).closest('tr')).data();
      if (!fila) return;
      if      ($(this).hasClass('btn-editar'))   Usuarios._abrirForm(fila);
      else if ($(this).hasClass('btn-pass'))     Usuarios._abrirPass(fila);
      else if ($(this).hasClass('btn-bloquear')) Usuarios._cambiarEstado(fila, 'bloqueado', 'bloquear');
      else                                       Usuarios._cambiarEstado(fila, 'activo', 'activar');
    });
  },

  _abrirForm(fila) {
    const esNuevo = !fila;
    const propio  = !esNuevo && App.usuario && fila.id === App.usuario.id;

    Promise.all([
      Api.get('api/catalogos/select.php', { tipo: 'roles' }).catch(() => []),
      Api.get('api/catalogos/select.php', { tipo: 'personal' }).catch(() => [])
    ]).then(([roles, personal]) => {

      const val = c => esNuevo ? '' : (fila[c] ?? '');

      const html = `
      <form id="form-usuario" autocomplete="off" data-id="${esNuevo ? 0 : fila.id}">
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

      $('#btn-guardar-usuario').on('click', () => this._guardar());
      $('#form-usuario').on('submit', e => { e.preventDefault(); this._guardar(); });
    });
  },

  _guardar() {
    const datos = {
      id:              $('#form-usuario').data('id') || 0,
      usuario:         $.trim($('#fu-usuario').val() || ''),
      nombre_completo: $.trim($('#fu-nombre').val() || ''),
      rol_id:          $('#fu-rol').val() || '',
      personal_id:     $('#fu-personal').val() || '',
      correo:          $.trim($('#fu-correo').val() || ''),
      estado:          $('#fu-estado').val() || 'activo',
      password:        $('#fu-password').val() || ''
    };

    Api.post('api/usuarios/guardar.php', datos)
      .then(() => { Modal.cerrar(); this.tabla.draw(); })
      .catch(err => {
        if (err && err.cuerpo && err.cuerpo.errors) pintarErroresForm('#form-usuario', err.cuerpo.errors);
      });
  },

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