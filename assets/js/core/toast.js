// ============================================================
// SIGTI - Notificaciones flotantes
// ============================================================
const Toast = {
  _iconos: {
    success: 'bi-check-circle-fill',
    error:   'bi-x-circle-fill',
    info:    'bi-info-circle-fill',
    warning: 'bi-exclamation-triangle-fill'
  },

  success(msg) { this._show(msg, 'success'); },
  error(msg)   { this._show(msg, 'error', 6000); },
  info(msg)    { this._show(msg, 'info'); },
  warning(msg) { this._show(msg, 'warning', 6000); },

  _show(mensaje, tipo, duracion = 4000) {
    const $t = $(
      `<div class="toast-sigti toast-${tipo}">
         <i class="bi ${this._iconos[tipo]}"></i><span></span>
       </div>`);
    $t.find('span').text(mensaje);
    $('#toast-container').append($t);

    const cerrar = () => $t.addClass('salir');
    $t.on('click', cerrar);                 // clic = cerrar antes de tiempo
    setTimeout(cerrar, duracion);
    setTimeout(() => $t.remove(), duracion + 350);

    // máximo 5 simultáneos (descarta el más viejo)
    const $todos = $('#toast-container .toast-sigti');
    if ($todos.length > 5) $todos.first().remove();
  }
};