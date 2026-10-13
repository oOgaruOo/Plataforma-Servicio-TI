/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Autoría: ventana «Acerca de SIGTI» y firma en la consola del navegador.
   Los datos vienen de core/Autor.php a través de window.SIGTI_INFO (index.php).
   ============================================================ */
var Acerca = {
  info: function () { return window.SIGTI_INFO || {}; },
  _version: function (v) {
    v = String(v || '');
    if (/^\d{14,}/.test(v)) return v.substring(6, 8) + '/' + v.substring(4, 6) + '/' + v.substring(0, 4) + ' ' + v.substring(8, 10) + ':' + v.substring(10, 12);
    return v || '—';
  },
  abrir: function () {
    var i = this.info();
    var h = '<div class="text-center py-2">' +
      '<div style="font-size:42px">🖥️</div>' +
      '<h4 class="mb-0" style="letter-spacing:2px">SIGTI</h4>' +
      '<div class="text-muted small mb-3">' + esc(i.sistema || 'Plataforma de Servicio TI') + '</div>' +
      '<div class="acerca-caja">' +
        '<div class="small text-muted">' + esc(i.rol || 'Desarrollador del sistema') + '</div>' +
        '<div class="fw-semibold fs-6" id="acerca-nombre">' + esc(i.nombre || '') + '</div>' +
        '<div class="mt-2"><span class="badge text-bg-dark" id="acerca-codigo" title="Código de autor">' + esc(i.codigo || '') + '</span></div>' +
        (i.orcid ? '<div class="mt-2"><a id="acerca-orcid" class="acerca-orcid" href="' + esc(i.orcid_url || ('https://orcid.org/' + i.orcid)) + '" target="_blank" rel="noopener author"><span class="orcid-ico">iD</span> ' + esc(i.orcid_url || ('https://orcid.org/' + i.orcid)) + '</a></div>' : '') +
      '</div>' +
      '<table class="table table-sm small text-start mt-3 mb-1"><tbody>' +
        '<tr><th class="text-muted fw-normal" style="width:42%">Versión del sistema</th><td id="acerca-version">' + esc(this._version(i.version)) + '</td></tr>' +
        '<tr><th class="text-muted fw-normal">Sello de autoría</th><td><code id="acerca-sello">' + esc(i.sello || '') + '</code></td></tr>' +
        '<tr><th class="text-muted fw-normal">Año</th><td>' + esc(i.anio || '') + '</td></tr>' +
      '</tbody></table>' +
      '<div class="text-muted" style="font-size:11px">© ' + esc(i.anio || '') + ' ' + esc(i.nombre || '') + '. Todos los derechos reservados.</div>' +
      '</div>' +
      '<div class="text-center mt-2"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>';
    Modal.abrir('Acerca de SIGTI', h, 'modal-md');
  }
};
$(document).on('click', '#btn-acerca', function () { Acerca.abrir(); });
(function () {
  try {
    var i = window.SIGTI_INFO || {};
    if (i.nombre && window.console && console.log) {
      console.log('%cSIGTI%c  ' + (i.sistema || '') + '\n%cDesarrollado por ' + i.nombre + ' · ' + i.codigo + ' · ORCID ' + (i.orcid || '—') + ' · sello ' + i.sello,
        'background:#2563eb;color:#fff;padding:2px 8px;border-radius:4px;font-weight:700', 'color:#2563eb;font-weight:600', 'color:#64748b');
    }
  } catch (e) {}
})();
