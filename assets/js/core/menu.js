// ============================================================
// SIGTI - Menú lateral construido según PERMISOS del usuario
// ============================================================
const MENU = [
  { seccion: 'Operación' },
  { ruta: 'dashboard/principal',   icono: 'speedometer2',   texto: 'Dashboard',                  permiso: null },
  { ruta: 'tickets/lista',         icono: 'life-preserver', texto: 'Tickets',                    permiso: ['tickets.ver','tickets.ver_propios','tickets.ver_area'] },
  { ruta: 'mantenimiento/lista',   icono: 'tools',          texto: 'Mantenimiento',              permiso: ['mantenimiento.ver'] },

  { seccion: 'Inventario' },
  { ruta: 'equipos/lista',         icono: 'pc-display',     texto: 'Equipos',                    permiso: ['equipos.ver'] },
  { ruta: 'asignaciones/lista',    icono: 'box-seam',       texto: 'Asignaciones y Actas',       permiso: ['asignaciones.ver'] },

  { seccion: 'Personal' },
  { ruta: 'personal/lista',        icono: 'people',         texto: 'Personal (Altas / Ceses)',   permiso: ['personal.ver'] },

  { seccion: 'Análisis' },
  { ruta: 'reportes/principal',    icono: 'graph-up-arrow', texto: 'Reportes',                   permiso: ['reportes.ver','reportes.ver_area'] },

  { seccion: 'Administración' },
  { ruta: 'usuarios/lista',        icono: 'person-gear',    texto: 'Usuarios del Sistema',       permiso: ['usuarios.gestionar'] },
  { ruta: 'configuracion/principal', icono: 'sliders',      texto: 'Configuración y Catálogos',  permiso: ['configuracion.gestionar'] },
  { ruta: 'auditoria/lista',       icono: 'journal-text',   texto: 'Auditoría',                  permiso: ['auditoria.ver'] }
];

const Menu = {

  construir() {
    let html = '';
    MENU.forEach(item => {
      if (item.seccion) { html += `<div class="menu-seccion">${item.seccion}</div>`; return; }
      if (!this.puedeVer(item.permiso)) return;   // sin permiso → ni se muestra
      html += `<a href="#" class="menu-item" data-ruta="${item.ruta}">
                 <i class="bi bi-${item.icono}"></i><span>${item.texto}</span>
               </a>`;
    });
    const $nav = $('#menu-nav');
    $nav.html(html);

    $nav.off('click', '.menu-item').on('click', '.menu-item', function (e) {
      e.preventDefault();
      Router.ir($(this).data('ruta'));
      if (window.innerWidth < 992) $('body').addClass('sidebar-oculto');
    });
  },

  puedeVer(permiso) {
    if (!permiso) return true;                                   // todos los logueados
    if (App.usuario && App.usuario.rol === 'super_admin') return true;
    const lista = Array.isArray(permiso) ? permiso : [permiso];
    return lista.some(p => App.permisos.includes(p));
  },

  activo(ruta) {
    $('.menu-item').removeClass('activo')
      .filter(`[data-ruta="${ruta}"]`).addClass('activo');
  }
};