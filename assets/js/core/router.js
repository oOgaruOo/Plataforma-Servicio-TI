// ============================================================
// SIGTI - Router de vistas (carga sin recargar la página)
// Las vistas viven en /views/<modulo>/<vista>.php
// ============================================================
const Router = {

  vistaActual: null,

  TITULOS: {
    'dashboard/principal':   'Dashboard',
    'tickets/lista':         'Mesa de Ayuda — Tickets',
    'mantenimiento/lista':   'Mantenimiento de Equipos',
    'equipos/lista':         'Inventario de Equipos',
    'asignaciones/lista':    'Asignaciones y Actas',
    'personal/lista':        'Personal — Altas y Ceses',
    'reportes/principal':    'Reportes',
    'usuarios/lista':        'Usuarios del Sistema',
    'configuracion/principal': 'Configuración y Catálogos',
    'auditoria/lista':       'Auditoría del Sistema'
  },

  // vistas que aún no existen → panel "en construcción" con su paso
   EN_CONSTRUCCION: {
    'equipos/lista': 6,
    'tickets/lista': 7, 'mantenimiento/lista': 8,
    'asignaciones/lista': 9, 'reportes/principal': 11, 'auditoria/lista': 12
  },

  ir(ruta, params = {}) {
    if (!App.usuario) return;              // sin sesión no se navega
    this.vistaActual = ruta;
    const modulo = ruta.split('/')[0];

    Loader.show();
    $.ajax({
      url: BASE_URL + 'views/' + ruta + '.php',
      type: 'GET',
      data: params,
      dataType: 'html',
      headers: { 'X-CSRF-Token': CSRF.token },

      success: html => {
        $('#vista-container').html(html);
        this._despuesDeCargar(ruta, modulo, params);
      },

      error: xhr => {
        if (xhr.status === 401) { App.sesionExpirada(); return; }

        if (xhr.status === 404) {
          const paso = this.EN_CONSTRUCCION[ruta] || '—';
          $('#vista-container').html(`
            <div class="modulo-construccion">
              <i class="bi bi-cone-striped"></i>
              <h3 class="mt-3">Módulo en construcción</h3>
              <p class="mb-1"><strong>${this.TITULOS[ruta] || ruta}</strong></p>
              <p>Se implementará en el <strong>Paso ${paso}</strong> del desarrollo lineal.</p>
              <span class="text-muted" style="font-size:13px">
                El shell, la seguridad y la navegación ya están operativos.
              </span>
            </div>`);
          this._despuesDeCargar(ruta, modulo, params);
        } else {
          $('#vista-container').html(
            '<div class="alert alert-danger">Error al cargar la vista (código ' + xhr.status + ').</div>');
        }
      },

      complete: () => Loader.hide()
    });
  },

  _despuesDeCargar(ruta, modulo, params) {
    $('#titulo-vista').text(this.TITULOS[ruta] || ruta);
    Menu.activo(ruta);
    if (('#' + ruta) !== location.hash) {
      history.replaceState(null, '', '#' + ruta);   // URL sincronizada sin recargar
    }
    // reinicializa el JS del módulo si se registró (patrón de pasos siguientes)
    const mod = App.modulos[modulo];
    if (mod && typeof mod.init === 'function') mod.init(params);
  }
};