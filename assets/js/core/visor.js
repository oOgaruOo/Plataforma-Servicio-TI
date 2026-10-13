/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Visor de documentos (PDF o imagen) en una ventana emergente
   Visor.abrir(url, titulo, {pdf:true|false, nombre:'archivo.pdf'})
   El archivo lo entrega un endpoint que valida sesion y permiso (nunca se abre desde storage/ directamente).
   ============================================================ */
var Visor = {
  _u: function (url, extra) { return url + (url.indexOf('?') >= 0 ? '&' : '?') + extra; },
  abrir: function (url, titulo, op) {
    op = op || {};
    var pdf = !!op.pdf;
    var h = '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">' +
      '<div class="text-muted small text-truncate" style="max-width:60%">' + esc(op.nombre || '') + '</div>' +
      '<div><a class="btn btn-sm btn-outline-primary me-1" id="visor-nueva" target="_blank" rel="noopener" href="' + esc(BASE_URL + url) + '"><i class="bi bi-box-arrow-up-right"></i> Abrir en otra pestaña</a>' +
      '<a class="btn btn-sm btn-outline-secondary" id="visor-desc" href="' + esc(BASE_URL + this._u(url, 'descargar=1')) + '"><i class="bi bi-download"></i> Descargar</a></div></div>' +
      (pdf
        ? '<iframe id="visor-doc" title="Documento" src="' + esc(BASE_URL + url) + '" style="width:100%;height:72vh;border:1px solid #e5e7eb;border-radius:.4rem;background:#f8fafc"></iframe>'
        : '<div class="text-center" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:.4rem;padding:.5rem"><img id="visor-doc" alt="Documento" src="' + esc(BASE_URL + url) + '" style="max-width:100%;max-height:72vh"></div>');
    Modal.abrir(titulo || 'Documento', h, 'modal-xl');
  }
};
