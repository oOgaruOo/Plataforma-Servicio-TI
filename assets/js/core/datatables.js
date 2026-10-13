/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var DATATABLES_ES={
processing:'Procesando...',
search:'Buscar:',
lengthMenu:'Mostrar _MENU_',
info:'_START_ a _END_ de _TOTAL_',
zeroRecords:'Sin resultados',
paginate:{first:'Primero',last:'Ultimo',next:'Siguiente',previous:'Anterior'}
};
var DT={
server:function(sel,url,extra,cols,opts){
return $(sel).DataTable({
processing:true,
serverSide:true,
language:DATATABLES_ES,
pageLength:10,
lengthMenu:[[10,25,50,100],[10,25,50,100]],
ajax:{
url:BASE_URL+url,
type:'POST',
headers:{'X-CSRF-Token':CSRF.token},
data:function(d){
var e=typeof extra==='function'?extra():(extra||{});
Object.assign(d,e);
},
error:function(xhr){
if(xhr.status===401){App.sesionExpirada();return}
if(xhr.status===419){setTimeout(function(){location.reload()},1500);return}
Toast.error('Error ('+xhr.status+')');
}
},
columns:(cols||[]).map(function(c){
// seguridad: una columna que solo declara el campo (sin render propio) se muestra como TEXTO, nunca como HTML
if(c&&typeof c.data==='string'&&typeof c.render==='undefined'){
return $.extend({},c,{render:function(d,t){return(t==='display'&&typeof d==='string')?esc(d):d}});
}
return c;
}),
order:(opts&&opts.order)||[[0,'asc']],
opts:opts||{}
});
}
};