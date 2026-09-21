// ============================================================
// SIGTI - Loader global con contador
// (varias peticiones simultáneas no lo hacen parpadear)
// ============================================================
const Loader = {
  _contador: 0,

  show() {
    this._contador++;
    $('#loader-global').addClass('visible');
  },

  hide() {
    this._contador = Math.max(0, this._contador - 1);
    if (this._contador === 0) $('#loader-global').removeClass('visible');
  }
};