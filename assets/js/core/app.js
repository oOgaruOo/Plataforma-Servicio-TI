// ============================================================
// SIGTI - Arranque de la aplicación y control de sesión
// ============================================================
const CSRF = { token: CSRF_TOKEN };

const App = {
  usuario: null,
  permisos: [],
  modulos: {},          // registro de módulos JS: App.registrar('tickets', Tickets)

  registrar(nombre, modulo) { this.modulos[nombre] = modulo; },

  // ---------- Ciclo de arranque ----------
  init() {
    this.bindLogin();
    this.bindLogout();
    this.bindTopbar();
    if (window.innerWidth < 992) $('body').addClass('sidebar-oculto');

    Loader.show();
    Api.get('api/auth/perfil.php')
      .then(data => this.entrar(data))
      .catch(() => this.mostrarLogin())
      .finally(() => Loader.hide());
  },

  mostrarLogin() {
    this.usuario = null;
    this.permisos = [];
    $('#app').addClass('hidden');
    $('#pantalla-login').removeClass('hidden');
    setTimeout(() => $('#login-usuario').trigger('focus'), 150);
  },

  entrar(data) {
    this.usuario  = data.usuario;
    this.permisos = data.permisos || [];

    $('#pantalla-login').addClass('hidden');
    $('#app').removeClass('hidden');
    $('#topbar-nombre').text(this.usuario.nombre);
    $('#topbar-rol').text('Rol: ' + this.usuario.rol);
    $('#avatar-iniciales').text(this.iniciales(this.usuario.nombre));

    Menu.construir();
    Notificaciones.iniciar();

    // si el usuario navegó con hash (ej: #tickets/lista), lo respeta
    const destino = (location.hash || '#dashboard/principal').slice(1);
    Router.ir(destino);
  },

  iniciales(nombre) {
    return nombre.trim().split(/\s+/).slice(0, 2).map(p => p.charAt(0).toUpperCase()).join('');
  },

  // sesión muerta a mitad de operación → volver al login sin recargar
  sesionExpirada() {
    if (!this.usuario && !$('#pantalla-login').hasClass('hidden')) return;
    Notificaciones.detener();
    this.usuario = null;
    this.permisos = [];
    $('#vista-container').empty();
    $('#app').addClass('hidden');
    $('#pantalla-login').removeClass('hidden');
    Toast.info('Su sesión ha expirado. Ingrese nuevamente.');
  },

  // ---------- Login (AJAX con errores inline, sin toast) ----------
  bindLogin() {
    $('#form-login').on('submit', e => {
      e.preventDefault();
      const $btn = $('#btn-login');
      const htmlOriginal = $btn.html();
      $btn.prop('disabled', true)
          .html('<span class="spinner-border spinner-border-sm me-1"></span>Verificando...');
      $('#login-error').hide();

      $.ajax({
        url: BASE_URL + 'api/auth/login.php',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-Token': CSRF.token },
        data: {
          usuario:  $('#login-usuario').val().trim(),
          password: $('#login-password').val()
        },
        success: res => {
          if (res.success) {
            this.entrar(res.data);
            $('#form-login')[0].reset();
            Toast.success(res.message);
          } else {
            $('#login-error').text(res.message).show();
          }
        },
        error: xhr => {
          let msg = 'Error de conexión con el servidor.';
          try { msg = JSON.parse(xhr.responseText).message || msg; } catch (_) {}
          if (xhr.status === 419) {
            msg = 'Token de seguridad expirado. Recargando la página...';
            setTimeout(() => location.reload(), 1600);
          }
          $('#login-error').text(msg).show();
        },
        complete: () => $btn.prop('disabled', false).html(htmlOriginal)
      });
    });
  },

  // ---------- Logout ----------
  bindLogout() {
    $('#btn-logout').on('click', () => {
      Swal.fire({
        title: '¿Cerrar sesión?',
        text: 'Se terminará su sesión actual.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar sesión',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#2563eb'
      }).then(r => {
        if (!r.isConfirmed) return;
        Api.post('api/auth/logout.php')
          .then(() => setTimeout(() => location.reload(), 900))  // recarga = sesión y token nuevos
          .catch(() => {});                                        // 401/419 ya se manejan solos
      });
    });
  },

  // ---------- Topbar ----------
  bindTopbar() {
    $('#btn-menu').on('click', () => $('body').toggleClass('sidebar-oculto'));

    $('#btn-notificaciones').on('click', () =>
      Toast.info('El centro de notificaciones se activa en el Paso 12.'));

    $('#btn-ver-pass').on('click', function () {
      const $inp = $('#login-password');
      const esPass = $inp.attr('type') === 'password';
      $inp.attr('type', esPass ? 'text' : 'password');
      $(this).find('i').attr('class', esPass ? 'bi bi-eye-slash' : 'bi bi-eye');
    });
  }
};

// ---------- Modal genérico (lo usan TODOS los módulos) ----------
const Modal = {
  abrir(titulo, html, tamano = 'modal-lg') {
    $('#modal-general-titulo').text(titulo);
    $('#modal-general-cuerpo').html(html);
    $('#modal-general .modal-dialog')
      .removeClass('modal-sm modal-lg modal-xl').addClass(tamano);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-general')).show();
  },
  cerrar() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-general')).hide();
  }
};

 $(document).ready(() => App.init());