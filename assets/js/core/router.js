/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var Router={
/* Rutas cuyo modulo JS NO es el primer segmento (ej. equipos/seguimiento -> Seguimiento) */
MODULO_DE:{'equipos/seguimiento':'seguimiento','configuracion/principal':'catalogos'},
TITULOS:{
'dashboard/principal':'Dashboard',
'guias/principal':'Guias del Sistema',
'chat/principal':'Chat interno',
'tickets/lista':'Mesa de Ayuda — Tickets',
'capacitaciones/lista':'Capacitaciones',
'mantenimiento/lista':'Mantenimiento',
'equipos/lista':'Inventario de Equipos',
'equipos/seguimiento':'Seguimiento de Ubicacion',
'software/lista':'Software instalado',
'proyectos/lista':'Proyectos y Obras',
'asignaciones/lista':'Asignaciones',
'actas/lista':'Actas',
'etiquetas/plantillas':'Diseñador de Etiquetas',
'personal/lista':'Personal',
'carnets/lista':'Carnets',
'reportes/principal':'Reportes',
'usuarios/lista':'Usuarios',
'configuracion/principal':'Configuracion',
'permisos/lista':'Permisos del Sistema',
'licencia/principal':'Licencia y rubro',
'auditoria/lista':'Auditoria'
},
ir:function(ruta,params){
if(!App.usuario)return;
var mod=ruta.split('/')[0];
Loader.show();
 $.ajax({
url:BASE_URL+'views/'+ruta+'.php',
type:'GET',
data:params||{},
dataType:'html',
headers:{'X-CSRF-Token':CSRF.token},
success:function(html){
 $('#vista-container').html(html);
 $('#titulo-vista').text(Router.TITULOS[ruta]||ruta);
Menu.activo(ruta);
if(('#'+ruta)!==location.hash)history.replaceState(null,'','#'+ruta);
var m=App.modulos[Router.MODULO_DE[ruta]||mod];
if(m&&typeof m.init==='function')m.init(params||{});
},
error:function(xhr){
if(xhr.status===401){App.sesionExpirada();return}
if(xhr.status===404){
 $('#vista-container').html('<div class="modulo-construccion"><i class="bi bi-cone-striped"></i><h3>Vista no encontrada</h3><p>'+ruta+'</p></div>');
}else{
 $('#vista-container').html('<div class="alert alert-danger">Error ('+xhr.status+')</div>');
}
},
complete:function(){Loader.hide()}
});
}
};