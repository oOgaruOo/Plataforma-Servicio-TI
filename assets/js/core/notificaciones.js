// ============================================================
// SIGTI - Polling de notificaciones (badge del topbar)
// Se auto-desactiva si el endpoint aún no existe (Paso 12),
// por eso no llena la consola de errores durante el desarrollo.
// ============================================================
const Notificaciones = {
  _timer: null,
  _disponible: true,      // false si el endpoint devuelve 404

  iniciar() {
    if (this._timer || !App.usuario) return;
    this.consultar();
    this._timer = setInterval(() => this.consultar(), 30000);   // cada 30 seg
  },

  detener() {
    clearInterval(this._timer);
    this._timer = null;
    $('#badge-notif').addClass('hidden').text('');
  },

  consultar() {
    if (!this._disponible || !App.usuario) return;

    $.getJSON(BASE_URL + 'api/notificaciones/contar.php')
      .done(res => {
        if (res.success) {
          const total = (res.data && res.data.total) || 0;
          $('#badge-notif').text(total > 99 ? '99+' : total)
                           .toggleClass('hidden', total === 0);
        }
      })
      .fail(xhr => {
        if (xhr.status === 404) this._disponible = false;  // módulo del Paso 12 aún no existe
        if (xhr.status === 401) { this.detener(); App.sesionExpirada(); }
      });
  }
};