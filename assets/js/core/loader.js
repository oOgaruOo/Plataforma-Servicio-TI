/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
const Loader={
_c:0,
show(){this._c++;$('#loader-global').addClass('visible')},
hide(){this._c=Math.max(0,this._c-1);if(this._c===0)$('#loader-global').removeClass('visible')}
};