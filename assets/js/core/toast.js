/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
const Toast={
_icons:{success:'bi-check-circle-fill',error:'bi-x-circle-fill',info:'bi-info-circle-fill',warning:'bi-exclamation-triangle-fill'},
success(m){this._show(m,'success')},
error(m){this._show(m,'error',6000)},
info(m){this._show(m,'info')},
warning(m){this._show(m,'warning',6000)},
_show(msg,tipo,dur){
dur=dur||4000;
var $t=$('<div class="toast-sigti toast-'+tipo+'"><i class="bi '+this._icons[tipo]+'"></i><span></span></div>');
 $t.find('span').text(msg);
 $('#toast-container').append($t);
 $t.on('click',function(){$(this).addClass('salir')});
setTimeout(function(){$t.addClass('salir')},dur);
setTimeout(function(){$t.remove()},dur+350);
}
};