/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Inventario de equipos de TI
   Vista: views/equipos/lista.php
   API:   api/equipos/{listar,stats,detalle,guardar,baja,exportar,importar,select}.php

   "Asignado a" muestra la PERSONA, el AREA, el PROYECTO o la OBRA donde
   esta el equipo (se asigna desde el modulo Asignaciones).
   ============================================================ */

/* ---- campos por familia de equipo ---- */
var EQ_FAMILIAS={
computo:{nombre:'Equipos de Computo',campos:[
{k:'cpu',l:'Procesador',ph:'Core i5-1235U'},
{k:'ram',l:'Memoria RAM',ph:'16 GB DDR4'},
{k:'disco',l:'Disco / Almacenamiento',ph:'SSD 512 GB NVMe'},
{k:'so',l:'Sistema Operativo',ph:'Windows 11 Pro'},
{k:'grafica',l:'Tarjeta grafica',ph:'Integrada / RTX 2050'}]},
movil:{nombre:'Equipos Moviles',campos:[
{k:'so',l:'Sistema Operativo',ph:'Android 14'},
{k:'almacenamiento',l:'Almacenamiento',ph:'128 GB'},
{k:'pantalla',l:'Pantalla',ph:'6.5 AMOLED'},
{k:'linea',l:'Linea telefonica',ph:'987 654 321'},
{k:'plan',l:'Plan de datos',ph:'Postpago 20 GB'}]},
impresion:{nombre:'Impresion e Imagen',campos:[
{k:'tecnologia',l:'Tecnologia',tipo:'select',opts:['Laser','Inyeccion de tinta','Matricial','Termica','LED']},
{k:'color',l:'Impresion a color',tipo:'select',opts:['No (B/N)','Si']},
{k:'consumible',l:'Consumible / repuesto',ph:'Toner HP 106A'},
{k:'rendimiento',l:'Rendimiento mensual',ph:'1500 paginas'},
{k:'duplex',l:'Impresion duplex',tipo:'select',opts:['No','Si']}]},
red:{nombre:'Equipos de Red',campos:[
{k:'puertos',l:'Puertos',ph:'8 puertos RJ45'},
{k:'velocidad',l:'Velocidad',ph:'Gigabit / WiFi 6'},
{k:'banda',l:'Banda WiFi',ph:'2.4 / 5 GHz'},
{k:'rack',l:'Montable en rack',tipo:'select',opts:['No','Si']}]},
energia:{nombre:'Energia Electrica',campos:[
{k:'capacidad_va',l:'Capacidad (VA)',ph:'750 VA'},
{k:'potencia_w',l:'Potencia (W)',ph:'500 W'},
{k:'baterias',l:'Baterias',ph:'1 bateria 12V 9Ah'},
{k:'tomas',l:'Tomacorrientes',ph:'6 tomas'}]},
visual:{nombre:'Visualizacion',campos:[
{k:'pulgadas',l:'Pulgadas',ph:'24 pulgadas'},
{k:'resolucion',l:'Resolucion',ph:'1920x1080 Full HD'},
{k:'panel',l:'Panel / Tecnologia',tipo:'select',opts:['IPS','VA','TN','OLED','DLP','LCD','LED']},
{k:'video',l:'Conexion de video',ph:'HDMI + VGA'}]},
periferico:{nombre:'Perifericos',campos:[
{k:'conexion_p',l:'Conexion',tipo:'select',opts:['USB','Inalambrico','Bluetooth','PS/2','Jack 3.5 mm']}]},
telefonia:{nombre:'Telefonia',campos:[
{k:'extension',l:'Extension / linea',ph:'101'},
{k:'canales',l:'Canales / lineas',ph:'2'}]},
otro:{nombre:'Otros equipos TI',campos:[
{k:'descripcion_tecnica',l:'Descripcion tecnica',ph:'Ej: Camara IP 4MP dome POE'}]}
};
/* configuracion de red: si (dispositivo de red) · opcional · no */
var EQ_FAM_IP={impresion:'si',red:'si',telefonia:'si',visual:'opcional',otro:'opcional',computo:'opcional',movil:'no',energia:'no',periferico:'no'};
var EQ_RED=[
{k:'ip_asignada',l:'Direccion IP asignada',ph:'192.168.1.50'},
{k:'mascara',l:'Mascara de subred',ph:'255.255.255.0'},
{k:'gateway',l:'Gateway',ph:'192.168.1.1'},
{k:'vlan',l:'VLAN',ph:'VLAN 10'},
{k:'dns',l:'DNS asignado',ph:'8.8.8.8'},
{k:'obs_red',l:'Observaciones de red',ph:'Puerto 24 del switch'}];
var EQ_ESTADO={en_stock:['En stock','text-bg-success'],asignado:['Asignado','text-bg-primary'],en_prestamo:['En préstamo','text-bg-info'],
en_revision:['En revisión','text-bg-warning text-dark'],en_mantenimiento:['En mantenimiento','text-bg-warning text-dark'],
en_reparacion_externa:['Reparación externa','text-bg-warning text-dark'],obsoleto:['Obsoleto','text-bg-secondary'],dado_de_baja:['De baja','text-bg-dark']};
var EQ_COND={nuevo:'success',bueno:'success',regular:'warning text-dark',danado:'danger',irreparable:'dark'};
var EQ_DEST={persona:['Persona','bi-person','text-bg-primary'],area:['Área','bi-people','text-bg-info'],
proyecto:['Proyecto','bi-kanban','text-bg-success'],obra:['Obra','bi-cone-striped','text-bg-warning text-dark']};
var EQ_IP_RE=/^((25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(25[0-5]|2[0-4]\d|[01]?\d\d?)$/;

function eqEstadoBadge(v){var e=EQ_ESTADO[v]||[String(v||'').replace(/_/g,' '),'text-bg-light'];return '<span class="badge '+e[1]+' nowrap">'+esc(e[0])+'</span>'}
function eqCondBadge(v){return '<span class="badge text-bg-'+(EQ_COND[v]||'light')+'">'+esc(v||'')+'</span>'}
function eqDestBadge(t){var d=EQ_DEST[t];return d?'<span class="badge '+d[2]+'"><i class="bi '+d[1]+'"></i> '+d[0]+'</span>':''}
function eqEtiquetaSpec(k){
for(var f in EQ_FAMILIAS){var c=EQ_FAMILIAS[f].campos.filter(function(x){return x.k===k})[0];if(c)return c.l}
var r=EQ_RED.filter(function(x){return x.k===k})[0];
return r?r.l:String(k).replace(/_/g,' ');
}

var Equipos={
tabla:null,
sel:{},
perm:{crear:false,editar:false,baja:false,asignar:false,software:false,swVer:false},
_specsOrig:{},_famOrig:'',_accs:[],

init:function(){
var s=this,$p=$('#equipos-permisos');
s.perm={crear:+$p.data('crear')===1,editar:+$p.data('editar')===1,baja:+$p.data('baja')===1,asignar:+$p.data('asignar')===1,software:+$p.data('software')===1,swVer:+$p.data('sw-ver')===1};
s.sel={};

s.tabla=DT.server('#tb-equipos','api/equipos/listar.php',s._filtros,[
{data:null,orderable:false,className:'text-center',render:function(v,t,f){
return '<input type="checkbox" class="form-check-input chk-etq" data-id="'+f.id+'"'+(s.sel[f.id]?' checked':'')+'>'}},
{data:'codigo',render:function(v){return '<strong class="nowrap">'+esc(v)+'</strong>'}},
{data:'tipo'},
{data:null,render:function(v,t,f){return esc(f.marca)+(f.modelo?' '+esc(f.modelo):'')}},
{data:'nro_serie',render:function(v){return v?'<span class="small">'+esc(v)+'</span>':'<span class="text-muted">&mdash;</span>'}},
{data:'estado',render:function(v,t,f){
var h=eqEstadoBadge(v);
if(f&&f.mt_codigo){
h+='<div class="small text-muted nowrap" title="Mantenimiento que retiene al equipo"><i class="bi bi-wrench-adjustable"></i> '+esc(f.mt_codigo)+'</div>';
if(+f.mt_ticket_atendido===1)h+='<div class="small text-danger fw-semibold eq-mt-pend" title="El ticket ya fue atendido, pero el mantenimiento sigue abierto: finalícelo en Operación › Mantenimiento para dejar el equipo operativo"><i class="bi bi-exclamation-triangle"></i> Ticket '+esc(f.mt_ticket_codigo||'')+' ya atendido: falta finalizar el mantenimiento</div>';
}
return h}},
{data:'condicion',render:function(v){return eqCondBadge(v)}},
{data:'asignado_a',render:function(v,t,f){
if(!v)return '<span class="text-muted">&mdash;</span>';
var h=eqDestBadge(f.destino_tipo)+' <b>'+esc(v)+'</b>';
if(f.destino_tipo==='proyecto'||f.destino_tipo==='obra'){
h+='<br><span class="small text-muted">'+(f.destino_codigo?esc(f.destino_codigo):'')+(f.responsable?' · Resp.: '+esc(f.responsable):'')+'</span>';
}else if(f.destino_tipo==='area'&&f.responsable){
h+='<br><span class="small text-muted">Resp.: '+esc(f.responsable)+'</span>';
}
return h}},
{data:'garantia_hasta',className:'nowrap',render:function(v){
if(!v)return '<span class="text-muted">&mdash;</span>';
var d=Math.round((new Date(v)-new Date())/86400000);
if(d<0)return '<span class="badge text-bg-danger">Vencida</span>';
if(d<=90)return '<span class="badge text-bg-warning text-dark">'+d+'d</span>';
return esc(v)}},
{data:null,orderable:false,className:'text-nowrap text-center',render:function(v,t,f){
var baja=f.estado==='dado_de_baja',h='<button class="btn btn-sm btn-outline-primary btn-ver me-1" title="Ver ficha"><i class="bi bi-eye"></i></button>';
if(!baja){
if(s.perm.editar)h+='<button class="btn btn-sm btn-outline-secondary btn-editar me-1" title="Editar"><i class="bi bi-pencil"></i></button>';
if(s.perm.asignar&&f.estado==='en_stock'&&['danado','irreparable'].indexOf(f.condicion)<0)h+='<button class="btn btn-sm btn-outline-success btn-asignar me-1" title="Asignar a persona / área / proyecto / obra"><i class="bi bi-box-arrow-right"></i></button>';
if(s.perm.asignar&&(f.estado==='asignado'||f.estado==='en_prestamo'))h+='<button class="btn btn-sm btn-outline-info btn-trasladar me-1" title="Trasladar a otro destino"><i class="bi bi-arrow-left-right"></i></button>';
if(s.perm.baja)h+='<button class="btn btn-sm btn-outline-danger btn-baja me-1" title="Dar de baja"><i class="bi bi-trash3"></i></button>';
}
h+='<button class="btn btn-sm btn-outline-dark btn-etiqueta" title="Etiqueta"><i class="bi bi-upc-scan"></i></button>';
return h}}
],{order:[[1,'asc']]});
s.tabla.on('draw',function(){s._syncChk()});

s._cargarProyectos();
s._stats();
s._bind();
},

_filtros:function(){
return{
f_estado:$('#ef-estado').val()||'',
f_tipo:$('#ef-tipo').val()||0,
f_garantia:$('#ef-garantia').val()||0,
f_destino:$('#ef-destino').val()||'',
f_proyecto:$('#ef-proyecto').val()||''
};
},

_cargarProyectos:function(){
Api.get('api/catalogos/select.php',{tipo:'proyectos'}).then(function(l){
var g={proyecto:'',obra:''};
(l||[]).forEach(function(p){g[p.tipo]+='<option value="'+p.id+'">'+esc(p.texto)+'</option>'});
$('#ef-proyecto').append((g.proyecto?'<optgroup label="Proyectos">'+g.proyecto+'</optgroup>':'')+(g.obra?'<optgroup label="Obras">'+g.obra+'</optgroup>':''));
}).catch(function(){});
},

_stats:function(){
Api.get('api/equipos/stats.php',{tipo:$('#ef-tipo').val()||0}).then(function(r){
$('#equipos-stats [data-k]').each(function(){
var k=$(this).data('k');
$(this).text(k==='valorizado'?'S/ '+Number(r[k]||0).toLocaleString():(r[k]||0));
});
});
},

_recargar:function(){this.tabla.draw(false);this._stats()},

_bind:function(){
var s=this;
$('#btn-filtrar-equipos').off('click.eq').on('click.eq',function(){s.tabla.draw();s._stats()});
$('#ef-estado,#ef-tipo,#ef-garantia,#ef-destino,#ef-proyecto').off('change.eq').on('change.eq',function(){s.tabla.draw();s._stats()});
$('#btn-limpiar-equipos').off('click.eq').on('click.eq',function(){
$('#ef-estado,#ef-tipo,#ef-garantia,#ef-destino,#ef-proyecto').val('');
s.tabla.search('').draw();s._stats();
});
$('#btn-nuevo-equipo').off('click.eq').on('click.eq',function(){s._form(null)});
$('#btn-importar-equipos').off('click.eq').on('click.eq',function(){EquiposImport.abrir(function(){s._recargar()})});
$('#btn-exportar-csv').off('click.eq').on('click.eq',function(e){e.preventDefault();s._exportar('csv')});
$('#btn-exportar-xls').off('click.eq').on('click.eq',function(e){e.preventDefault();s._exportar('excel')});

/* etiquetas en lote: casillas por fila + "seleccionar todos" */
$('#tb-equipos').off('change.eq').on('change.eq','.chk-etq',function(){
var id=$(this).data('id');
if(this.checked)s.sel[id]=1;else delete s.sel[id];
s._syncChk();
});
$('#tb-equipos').on('change.eq','.chk-todos',function(){
var on=this.checked;
$('#tb-equipos .chk-etq').each(function(){
var id=$(this).data('id');this.checked=on;
if(on)s.sel[id]=1;else delete s.sel[id];
});
s._syncChk();
});
$('#btn-etiquetas-lote').off('click.eq').on('click.eq',function(){
var ids=Object.keys(s.sel);
if(!ids.length)return;
if(ids.length>200){Toast.warning('Máximo 200 etiquetas por lote');return}
window.open(BASE_URL+'views/equipos/etiquetas.php?ids='+ids.join(','),'_blank');
});

$('#tb-equipos').off('click.eqa').on('click.eqa','.btn-ver,.btn-editar,.btn-baja,.btn-etiqueta,.btn-asignar,.btn-trasladar',function(){
var f=s.tabla.row($(this).closest('tr')).data();
if(!f)return;
var $b=$(this);
if($b.hasClass('btn-ver'))s._ver(f.id);
else if($b.hasClass('btn-editar'))s._form(f);
else if($b.hasClass('btn-baja'))s._baja(f);
else if($b.hasClass('btn-asignar'))s._irAsignar({equipo_id:f.id});
else if($b.hasClass('btn-trasladar'))s._irAsignar({operacion:'traslado',equipo_id:f.id});
else window.open(BASE_URL+'views/equipos/etiquetas.php?ids='+f.id,'_blank');
});
},

/* sincroniza casilla "todos" y el contador del boton de etiquetas */
_syncChk:function(){
var n=Object.keys(this.sel).length;
$('#btn-etiquetas-lote').prop('disabled',n===0).html('<i class="bi bi-upc-scan"></i> Etiquetas ('+n+')');
var $c=$('#tb-equipos .chk-etq'),marc=$c.filter(':checked').length;
$('#tb-equipos .chk-todos').prop('checked',$c.length>0&&marc===$c.length);
},

/* abre Asignaciones con el formulario de entrega ya preparado */
_irAsignar:function(o){
if(typeof Asignaciones==='undefined'){Toast.error('El módulo de asignaciones no está disponible');return}
Modal.cerrar();
Asignaciones.pendiente=o;
Router.ir('asignaciones/lista');
},

/* descarga el inventario con los filtros aplicados (CSV o Excel) */
_exportar:function(formato){
var q=$.param($.extend({formato:formato,csrf_token:CSRF.token},this._filtros()));
fetch(BASE_URL+'api/equipos/exportar.php?'+q,{credentials:'same-origin'}).then(function(r){
if(!r.ok)return r.json().then(function(j){throw j},function(){throw{message:'Error ('+r.status+')'}});
var m=/filename="?([^";]+)"?/.exec(r.headers.get('Content-Disposition')||'');
var nombre=m?m[1]:('inventario_equipos.'+(formato==='csv'?'csv':'xls'));
return r.blob().then(function(b){
var a=document.createElement('a');
a.href=URL.createObjectURL(b);a.download=nombre;
document.body.appendChild(a);a.click();document.body.removeChild(a);
setTimeout(function(){URL.revokeObjectURL(a.href)},2000);
Toast.success('Inventario exportado');
});
}).catch(function(e){Toast.error((e&&e.message)||'No se pudo exportar')});
},

/* ============================================================
   FORMULARIO (registrar / editar). Al editar se lee la ficha completa
   para precargar tipo, especificaciones y accesorios.
   ============================================================ */
_form:function(fila){
var s=this,esNuevo=!fila;
Promise.all([
Api.get('api/catalogos/select.php',{tipo:'tipos'}),
esNuevo?Promise.resolve(null):Api.get('api/equipos/detalle.php',{id:fila.id})
]).then(function(R){s._formHtml(R[0]||[],R[1])});
},

_formHtml:function(tipos,e){
var s=this,esNuevo=!e;
var v=function(c){return esNuevo||e[c]==null?'':e[c]};
var bloqueado=!esNuevo&&['asignado','en_prestamo','en_mantenimiento','en_reparacion_externa'].indexOf(e.estado)>=0;

if(!esNuevo&&!tipos.some(function(t){return +t.id===+e.tipo_equipo_id}))
tipos=tipos.concat([{id:e.tipo_equipo_id,texto:e.tipo+' (inactivo)',familia:e.familia}]);

s._specsOrig={};
if(!esNuevo&&e.especificaciones){try{s._specsOrig=JSON.parse(e.especificaciones)||{}}catch(x){s._specsOrig={}}}
s._famOrig=esNuevo?'':(e.familia||'otro');
s._accs=esNuevo?[]:(e.accesorios||[]).map(function(a){return{nombre:a.nombre,entregado:+a.entregado===1}});

var cond=['nuevo','bueno','regular','danado','irreparable'],condT={nuevo:'Nuevo',bueno:'Bueno',regular:'Regular',danado:'Dañado',irreparable:'Irreparable'};
var cur=esNuevo?'nuevo':e.condicion;

var h='<form id="form-equipo" autocomplete="off" data-id="'+(esNuevo?0:e.id)+'"><div class="row">';
h+='<div class="col-md-4 mb-3"><label class="form-label">Tipo <span class="text-danger">*</span></label><select class="form-select" id="fe-tipo">';
tipos.forEach(function(t){
h+='<option value="'+t.id+'" data-familia="'+esc(t.familia||'otro')+'"'+(!esNuevo&&+t.id===+e.tipo_equipo_id?' selected':'')+'>'+esc(t.texto)+'</option>';
});
h+='</select><div class="form-text">Los campos técnicos cambian según el tipo</div></div>';
h+='<div class="col-md-4 mb-3"><label class="form-label">Marca <span class="text-danger">*</span></label><input class="form-control" id="fe-marca" value="'+esc(v('marca'))+'" maxlength="60"></div>';
h+='<div class="col-md-4 mb-3"><label class="form-label">Modelo</label><input class="form-control" id="fe-modelo" value="'+esc(v('modelo'))+'" maxlength="80"></div>';
h+='<div class="col-md-3 mb-3"><label class="form-label">N° de serie</label><input class="form-control" id="fe-serie" value="'+esc(v('nro_serie'))+'" maxlength="80"></div>';
h+='<div class="col-md-3 mb-3"><label class="form-label">Activo fijo</label><input class="form-control" id="fe-af" value="'+esc(v('activo_fijo'))+'" maxlength="40"></div>';
h+='<div class="col-md-3 mb-3"><label class="form-label">IMEI</label><input class="form-control" id="fe-imei" value="'+esc(v('imei'))+'" maxlength="20"></div>';
h+='<div class="col-md-3 mb-3"><label class="form-label">MAC</label><input class="form-control" id="fe-mac" value="'+esc(v('mac'))+'" maxlength="20"></div>';
h+='<div class="col-md-3 mb-3"><label class="form-label">Condición <span class="text-danger">*</span></label><select class="form-select" id="fe-condicion">';
cond.forEach(function(c){h+='<option value="'+c+'"'+(cur===c?' selected':'')+'>'+condT[c]+'</option>'});
h+='</select></div>';
h+='<div class="col-md-3 mb-3"><label class="form-label">Fecha de compra</label><input type="date" class="form-control" id="fe-fcompra" value="'+esc(v('fecha_compra'))+'"></div>';
h+='<div class="col-md-3 mb-3"><label class="form-label">Garantía hasta</label><input type="date" class="form-control" id="fe-garantia" value="'+esc(v('garantia_hasta'))+'"></div>';
h+='<div class="col-md-3 mb-3"><label class="form-label">Costo (S/)</label><input type="number" class="form-control" id="fe-costo" min="0" step="0.01" value="'+esc(v('costo'))+'"></div>';
h+='<div class="col-md-4 mb-3"><label class="form-label">Proveedor</label><input class="form-control" id="fe-proveedor" value="'+esc(v('proveedor_compra'))+'" maxlength="100"></div>';
h+='<div class="col-md-4 mb-3"><label class="form-label">Ubicación</label><input class="form-control" id="fe-ubicacion" value="'+esc(v('ubicacion'))+'" maxlength="100" placeholder="Almacén TI"></div>';

h+='<div class="col-md-4 mb-3"><label class="form-label">Estado</label>';
if(esNuevo){
h+='<input class="form-control" value="En stock (automático)" disabled>';
}else if(bloqueado){
var a=e.asignacion,dest=a?(EQ_DEST[a.destino_tipo]||['',''])[0]+': '+(a.destino_nombre||''):'';
h+='<input class="form-control" disabled value="'+esc((EQ_ESTADO[e.estado]||[e.estado])[0]+(dest?' — '+dest:''))+'">';
h+='<div class="form-text">Cambia solo con la entrega, devolución o mantenimiento.</div>';
}else{
h+='<select class="form-select" id="fe-estado">';
[['en_stock','En stock'],['en_revision','En revisión'],['obsoleto','Obsoleto']].forEach(function(o){
h+='<option value="'+o[0]+'"'+(e.estado===o[0]?' selected':'')+'>'+o[1]+'</option>';
});
h+='</select>';
}
h+='</div></div>';

h+='<div class="acc-seccion mb-3"><div class="form-label mb-2">Especificaciones <span class="badge text-bg-primary ms-1" id="fe-fam-nombre"></span></div><div class="row" id="fe-specs"></div></div>';
h+='<div class="acc-seccion mb-3" id="fe-zona-red" style="display:none"><div class="form-label mb-2"><i class="bi bi-ethernet me-1 text-primary"></i>Configuración de red / IP <span class="badge ms-1" id="fe-red-badge"></span></div><div class="row" id="fe-red"></div></div>';

h+='<div class="mb-3"><label class="form-label">Accesorios que se entregan con el equipo</label>'+
'<div class="d-flex flex-wrap gap-1 mb-2" id="fe-acc"></div>'+
'<div class="input-group input-group-sm" style="max-width:420px"><input class="form-control" id="fe-acc-in" list="fe-acc-dl" maxlength="80" placeholder="Cargador, maletín, mouse...">'+
'<datalist id="fe-acc-dl"></datalist><button type="button" class="btn btn-outline-primary" id="fe-acc-add"><i class="bi bi-plus-lg"></i> Agregar</button></div></div>';

if(esNuevo&&s.perm.software&&typeof SoftwarePicker!=='undefined'){
h+='<div class="acc-seccion mb-3" id="fe-zona-sw"><div class="form-label mb-1"><i class="bi bi-app-indicator me-1 text-primary"></i>Software instalado <span class="badge ms-1" id="fe-sw-badge"></span></div>'+
'<div class="form-text mb-2" id="fe-sw-ayuda"></div><div id="fe-sw"></div></div>';
}
h+='<div class="mb-3"><label class="form-label">Observaciones</label><textarea class="form-control" id="fe-obs" rows="2" maxlength="500">'+esc(v('observaciones'))+'</textarea></div>';
h+='<div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="button" class="btn btn-primary" id="btn-ge"><i class="bi bi-check-lg"></i> '+(esNuevo?'Registrar':'Guardar')+'</button></div></form>';

Modal.abrir(esNuevo?'Registrar equipo':'Editar — '+e.codigo,h,'modal-xl');

var fam=function(){return $('#fe-tipo option:selected').data('familia')||'otro'};
s._renderFam(fam(),esNuevo||fam()!==s._famOrig?{}:s._specsOrig);
s._renderAcc();
s._sugerirAcc(fam());
s._swOk=false;
if($('#fe-sw').length){
s._swAyuda(fam());
SoftwarePicker.montar('#fe-sw',{familia:fam()});
}
$('#fe-tipo').on('change',function(){
var f=fam();
s._renderFam(f,f===s._famOrig?s._specsOrig:{});
s._sugerirAcc(f);
if($('#fe-sw').length){s._swAyuda(f);SoftwarePicker.cambiarFamilia(f)}
});
$('#fe-acc-add').on('click',function(){s._addAcc()});
$('#fe-acc-in').on('keydown',function(ev){if(ev.key==='Enter'){ev.preventDefault();s._addAcc()}});
$('#fe-acc').on('click','.fe-acc-del',function(){s._accs.splice(+$(this).data('i'),1);s._renderAcc()});
$('#btn-ge').on('click',function(){s._guardar(!esNuevo&&!bloqueado)});
},

/* texto del bloque «Software instalado» segun el tipo de equipo */
_swAyuda:function(f){
var comp=f==='computo';
$('#fe-sw-badge').text(comp?'Recomendado':'Opcional').attr('class','badge ms-1 '+(comp?'text-bg-primary':'text-bg-secondary'));
$('#fe-sw-ayuda').text(comp?'Indique qué programas tiene instalados (sistema operativo, ofimática, antivirus…). Queda para el control interno y las auditorías; cada licencia consume un puesto.':'Si el equipo tiene programas (aplicaciones del celular, firmware con licencia…), indíquelos aquí. Puede dejarlo vacío.');
},

/* ---- accesorios (chips) ---- */
_renderAcc:function(){
var h='';
this._accs.forEach(function(a,i){
h+='<span class="badge text-bg-light border text-dark fw-normal py-2">'+esc(a.nombre)+
(a.entregado?' <i class="bi bi-lock-fill text-muted" title="Entregado con el equipo: se retira al devolverlo"></i>':
' <a href="#" class="fe-acc-del text-danger ms-1" data-i="'+i+'" onclick="return false" title="Quitar"><i class="bi bi-x-lg"></i></a>')+'</span>';
});
$('#fe-acc').html(h||'<span class="text-muted small">Sin accesorios</span>');
},
_addAcc:function(){
var n=($('#fe-acc-in').val()||'').trim();
if(!n)return;
if(this._accs.some(function(a){return a.nombre.toLowerCase()===n.toLowerCase()})){Toast.info('Ya está en la lista');return}
this._accs.push({nombre:n.slice(0,80),entregado:false});
$('#fe-acc-in').val('').focus();
this._renderAcc();
},
_sugerirAcc:function(f){
Api.get('api/equipos/accesorios-tipos.php',{familia:f}).then(function(l){
$('#fe-acc-dl').html((l||[]).map(function(a){return '<option value="'+esc(a.texto)+'">'}).join(''));
}).catch(function(){});
},

/* ---- campos tecnicos segun la familia (specs = valores a precargar) ---- */
_renderFam:function(fam,specs){
specs=specs||{};
var f=EQ_FAMILIAS[fam]||EQ_FAMILIAS.otro;
$('#fe-fam-nombre').text(f.nombre);

var h='';
f.campos.forEach(function(c){
h+='<div class="col-md-4 mb-3"><label class="form-label">'+esc(c.l)+'</label>';
if(c.tipo==='select'){
h+='<select class="form-select" data-k="'+c.k+'"><option value="">&mdash;</option>';
(c.opts||[]).forEach(function(o){h+='<option'+(specs[c.k]===o?' selected':'')+'>'+esc(o)+'</option>'});
if(specs[c.k]&&(c.opts||[]).indexOf(specs[c.k])<0)h+='<option selected>'+esc(specs[c.k])+'</option>';
h+='</select></div>';
}else{
h+='<input class="form-control" data-k="'+c.k+'" value="'+esc(specs[c.k]||'')+'" placeholder="'+esc(c.ph||'')+'"></div>';
}
});
if(fam==='impresion'){
var cx=String(specs.conexion||'');
h+='<div class="col-md-4 mb-3"><label class="form-label">Conexiones disponibles</label><div>';
['USB','Ethernet (Red)','WiFi'].forEach(function(o){
h+='<label class="me-3 mb-1"><input type="checkbox" class="form-check-input" data-k="conexion" value="'+esc(o)+'"'+(cx.indexOf(o)>=0?' checked':'')+'> <span>'+esc(o)+'</span></label>';
});
h+='</div></div>';
}
$('#fe-specs').html(h);

var ipMode=EQ_FAM_IP[fam]||'no',zona=$('#fe-zona-red');
if(ipMode==='no'){zona.hide();$('#fe-red').html('');return}
zona.show();
$('#fe-red-badge').text(ipMode==='si'?'Dispositivo de red':'Opcional').attr('class','badge ms-1 '+(ipMode==='si'?'text-bg-danger':'text-bg-secondary'));
var rh='';
EQ_RED.forEach(function(c){
rh+='<div class="col-md-4 mb-2"><label class="form-label">'+esc(c.l)+'</label>'+
'<input type="text" class="form-control '+(c.k==='ip_asignada'||c.k==='mascara'||c.k==='gateway'||c.k==='dns'?'ip-input':'')+'" data-k="'+c.k+'" value="'+esc(specs[c.k]||'')+'" placeholder="'+esc(c.ph)+'"></div>';
});
$('#fe-red').html(rh);
var chk=function($i){
var x=$i.val();
$i.css('border-color',x===''?'':(EQ_IP_RE.test(x)?'#16a34a':'#dc2626'));
};
$('#fe-red .ip-input').each(function(){chk($(this))}).on('input',function(){chk($(this))});
},

_guardar:function(esEdicionLibre){
var s=this,esNuevo=!(+$('#form-equipo').data('id'));
var famActual=$('#fe-tipo option:selected').data('familia')||'otro';

var inputsK={},specs={};
$('#fe-specs [data-k], #fe-red [data-k]').each(function(){inputsK[$(this).data('k')]=1});
/* al editar, los datos tecnicos que este formulario no muestra se conservan */
if(!esNuevo&&famActual===s._famOrig){
Object.keys(s._specsOrig).forEach(function(k){if(!inputsK[k])specs[k]=s._specsOrig[k]});
}
$('#fe-specs input[data-k]:not([type=checkbox]), #fe-specs select[data-k], #fe-red [data-k]').each(function(){
var x=$.trim($(this).val()||'');
if(x)specs[$(this).data('k')]=x;
});
$('#fe-specs input[type=checkbox][data-k]:checked').each(function(){
var k=$(this).data('k');
specs[k]=(specs[k]?specs[k]+', ':'')+$(this).val();
});

var d={
id:+$('#form-equipo').data('id')||0,
tipo_equipo_id:$('#fe-tipo').val()||'',
marca:($('#fe-marca').val()||'').trim(),
modelo:$('#fe-modelo').val()||'',
nro_serie:($('#fe-serie').val()||'').trim(),
activo_fijo:$('#fe-af').val()||'',
imei:($('#fe-imei').val()||'').trim(),
mac:$('#fe-mac').val()||'',
condicion:$('#fe-condicion').val()||'',
estado:esNuevo?'en_stock':($('#fe-estado').length?$('#fe-estado').val():''),
fecha_compra:$('#fe-fcompra').val()||'',
garantia_hasta:$('#fe-garantia').val()||'',
proveedor_compra:$('#fe-proveedor').val()||'',
costo:$('#fe-costo').val()||'',
ubicacion:$('#fe-ubicacion').val()||'',
observaciones:$('#fe-obs').val()||'',
especificaciones:JSON.stringify(specs),
accesorios:JSON.stringify(s._accs.map(function(a){return{nombre:a.nombre}}))
};
if(esNuevo&&$('#fe-sw').length)d.software=SoftwarePicker.valor();

if(!d.tipo_equipo_id){Toast.warning('Seleccione el tipo');return}
if(!d.marca){Toast.warning('Indique la marca');return}
if(d.fecha_compra&&d.garantia_hasta&&d.garantia_hasta<d.fecha_compra){Toast.warning('La garantía no puede vencer antes de la fecha de compra');return}
var malas=[];
$('#fe-red .ip-input').each(function(){var x=$.trim($(this).val());if(x&&!EQ_IP_RE.test(x))malas.push(eqEtiquetaSpec($(this).data('k')))});
if(malas.length){Toast.warning('Dirección no válida en: '+malas.join(', '));return}

if(esNuevo&&$('#fe-sw').length&&famActual==='computo'&&SoftwarePicker.cantidad()===0&&!s._swOk){
Swal.fire({title:'Sin software registrado',text:'Este equipo de cómputo no tiene programas registrados. Para el control interno y las auditorías conviene indicar qué tiene instalado.',icon:'question',showCancelButton:true,
confirmButtonText:'Registrar sin software',cancelButtonText:'Agregar software',confirmButtonColor:'#2563eb'}).then(function(r){
if(r.isConfirmed){s._swOk=true;s._guardar(esEdicionLibre)}else{$('#fe-sw select').first().focus()}
});
return;
}

$('#btn-ge').prop('disabled',true);
Api.post('api/equipos/guardar.php',d).then(function(r){
Modal.cerrar();s._recargar();
Toast.success((r&&(r.mensaje||r.message))||'Equipo guardado');
}).catch(function(e){
$('#btn-ge').prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
},

/* ============================================================
   FICHA DEL EQUIPO
   ============================================================ */
_ver:function(id){
var s=this;
Api.get('api/equipos/detalle.php',{id:id}).then(function(e){
var cel=function(t,x){return '<div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="text-muted small">'+t+'</div><strong class="small">'+(x===''||x==null?'&mdash;':esc(x))+'</strong></div></div>'};
var h='<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2"><div>'+
'<h5 class="mb-0">'+esc(e.codigo)+' &middot; '+esc(e.tipo)+'</h5>'+
'<div class="text-muted">'+esc(e.marca||'')+' '+esc(e.modelo||'')+'</div></div>'+
'<div>'+eqEstadoBadge(e.estado)+' '+eqCondBadge(e.condicion)+'</div></div>'+
(e.mantenimiento_abierto?'<div class="alert '+(['resuelto','cerrado'].indexOf(e.mantenimiento_abierto.ticket_estado)>=0?'alert-danger':'alert-warning')+' py-2 small mb-2" id="fi-mt-abierto"><i class="bi bi-wrench-adjustable"></i> '+
'Retenido por el mantenimiento <b>'+esc(e.mantenimiento_abierto.codigo)+'</b>.'+
(['resuelto','cerrado'].indexOf(e.mantenimiento_abierto.ticket_estado)>=0?' El ticket <b>'+esc(e.mantenimiento_abierto.ticket_codigo||'')+'</b> ya fue atendido: <b>finalice ese mantenimiento</b> (Operación › Mantenimiento) para que el equipo quede operativo.':'')+'</div>':'');

/* donde esta / a quien se asigno */
var a=e.asignacion;
if(a){
var det=(EQ_DEST[a.destino_tipo]||EQ_DEST.persona);
h+='<div class="alert alert-light border mb-3 py-2"><div class="d-flex flex-wrap gap-2 align-items-center">'+eqDestBadge(a.destino_tipo)+' <b>'+esc(a.destino_nombre||'')+'</b>'+
(a.tipo==='prestamo'?' <span class="badge text-bg-info">Préstamo</span>':'')+'</div><div class="small text-muted mt-1">'+
(a.destino_tipo==='persona'&&a.p_dni?'DNI '+esc(a.p_dni)+' · ':'')+
(a.destino_codigo?esc(a.destino_codigo)+' · ':'')+(a.destino_cliente?'Cliente: '+esc(a.destino_cliente)+' · ':'')+
(a.destino_ubicacion?'Ubicación: '+esc(a.destino_ubicacion)+' · ':'')+
(a.destino_tipo!=='persona'&&a.responsable?'Responsable: '+esc(a.responsable)+' · ':'')+
(a.destino_detalle?esc(a.destino_detalle)+' · ':'')+
'Desde '+esc(String(a.fecha_entrega||'').slice(0,10))+
(a.fecha_devolucion_esperada?' · Retorno esperado '+esc(a.fecha_devolucion_esperada):'')+'</div></div>';
}else if(e.ubicacion){
h+='<div class="alert alert-light border mb-3 py-2"><i class="bi bi-geo-alt"></i> Ubicación: <b>'+esc(e.ubicacion)+'</b></div>';
}

h+='<div class="row g-2 mb-3">'+
cel('N° de serie',e.nro_serie)+cel('Activo fijo',e.activo_fijo)+cel('IMEI',e.imei)+cel('MAC',e.mac)+
cel('Fecha de compra',e.fecha_compra)+cel('Proveedor',e.proveedor_compra)+
cel('Costo',e.costo?'S/ '+Number(e.costo).toLocaleString():'')+cel('Garantía hasta',e.garantia_hasta)+
cel('Mantenimientos',e.vecesMant||0)+cel('Costo de mantenimiento','S/ '+Number(e.costo_mant_total||0).toLocaleString())+
cel('Ubicación',e.ubicacion)+cel('Verificado',String(e.verificado_ubicacion||'').slice(0,10))+'</div>';

var specs={};
try{if(e.especificaciones)specs=JSON.parse(e.especificaciones)}catch(x){}
var ks=Object.keys(specs);
if(ks.length){
h+='<h6 class="mt-3">Especificaciones</h6><div class="row g-2 mb-3">';
ks.forEach(function(k){h+=cel(esc(eqEtiquetaSpec(k)),specs[k])});
h+='</div>';
}
if((e.accesorios||[]).length){
h+='<h6>Accesorios</h6><div class="mb-3">'+e.accesorios.map(function(x){
return '<span class="badge text-bg-light border text-dark fw-normal me-1">'+esc(x.nombre)+(+x.entregado===1?' <i class="bi bi-box-arrow-right text-primary" title="Entregado con el equipo"></i>':'')+'</span>'}).join('')+'</div>';
}
if(+e.software_ver===1){
h+='<h6>Software instalado <span class="badge text-bg-primary">'+(e.software||[]).length+'</span></h6>';
if((e.software||[]).length){
h+='<div class="mb-3">'+e.software.map(function(x){
var mal=(x.alertas||[]).length;
return '<span class="badge '+(mal?'text-bg-danger':'text-bg-light border text-dark fw-normal')+' me-1 mb-1" title="'+esc((x.alertas||[]).join(', '))+'">'+esc(x.nombre)+(x.version?' '+esc(x.version):'')+(mal?' <i class="bi bi-exclamation-triangle"></i>':'')+'</span>'}).join('')+'</div>';
}else{
h+='<div class="text-muted small mb-3">Todavía no tiene software registrado.</div>';
}
}
if((e.asignaciones||[]).length){
h+='<h6>Asignaciones</h6><div style="max-height:200px;overflow:auto" class="mb-3"><table class="table table-sm table-bordered tabla-mini mb-0"><thead><tr><th>Destino</th><th>Entrega</th><th>Modalidad</th><th>Estado</th><th>Devuelto</th></tr></thead><tbody>'+
e.asignaciones.map(function(x){return '<tr><td>'+eqDestBadge(x.destino_tipo)+' '+esc(x.destino_nombre||'')+(x.destino_tipo!=='persona'&&x.responsable?'<div class="small text-muted">Resp.: '+esc(x.responsable)+'</div>':'')+'</td>'+
'<td class="small">'+esc(String(x.fecha_entrega||'').slice(0,10))+'</td><td>'+esc(x.tipo)+'</td><td>'+esc(x.estado)+'</td>'+
'<td class="small">'+(x.fecha_devolucion_real?esc(String(x.fecha_devolucion_real).slice(0,10))+(x.condicion_devolucion?' ('+esc(x.condicion_devolucion)+')':''):'&mdash;')+'</td></tr>'}).join('')+'</tbody></table></div>';
}
if((e.historial||[]).length){
h+='<h6>Historial</h6><div style="max-height:220px;overflow:auto" class="small border rounded p-2 mb-3">'+
e.historial.map(function(x){return '<div class="mb-1"><span class="text-muted">'+esc(String(x.created_at||'').slice(0,16))+'</span> <b>'+esc(String(x.evento||'').replace(/_/g,' '))+'</b>'+
(x.detalle?' — '+esc(x.detalle):'')+(x.usuario?' <span class="text-muted">('+esc(x.usuario)+')</span>':'')+'</div>'}).join('')+'</div>';
}

h+='<div class="text-end">';
if(e.estado!=='dado_de_baja'){
if(s.perm.asignar&&e.estado==='en_stock')h+='<button class="btn btn-success btn-sm me-1" id="fi-asignar"><i class="bi bi-box-arrow-right"></i> Asignar</button>';
if(s.perm.asignar&&(e.estado==='asignado'||e.estado==='en_prestamo'))h+='<button class="btn btn-info btn-sm me-1" id="fi-trasladar"><i class="bi bi-arrow-left-right"></i> Trasladar</button>';
if(s.perm.editar)h+='<button class="btn btn-outline-secondary btn-sm me-1" id="fi-editar"><i class="bi bi-pencil"></i> Editar</button>';
}
if(+e.software_ver===1&&typeof Software!=='undefined')h+='<button class="btn btn-outline-primary btn-sm me-1" id="fi-software"><i class="bi bi-app-indicator"></i> Software</button>';
if(e.estado!=='dado_de_baja')h+='<button class="btn btn-outline-danger btn-sm me-1" id="fi-rastreo"><i class="bi bi-crosshair"></i> Rastreo</button>';
h+='<a class="btn btn-outline-dark btn-sm" target="_blank" href="'+BASE_URL+'views/equipos/etiquetas.php?ids='+e.id+'"><i class="bi bi-upc-scan"></i> Etiqueta</a></div>';

Modal.abrir('Ficha — '+e.codigo,h,'modal-xl');
$('#fi-asignar').on('click',function(){s._irAsignar({equipo_id:e.id})});
$('#fi-trasladar').on('click',function(){s._irAsignar({operacion:'traslado',equipo_id:e.id})});
$('#fi-editar').on('click',function(){Modal.cerrar();setTimeout(function(){s._form({id:e.id})},350)});
$('#fi-software').on('click',function(){Modal.cerrar();setTimeout(function(){Software.abrirEquipo(e.id)},350)});
$('#fi-rastreo').on('click',function(){Modal.cerrar();setTimeout(function(){Router.ir('equipos/seguimiento',{tab:'rastreo',equipo_id:e.id})},350)});
});
},

/* ============================================================
   BAJA (definitiva, queda como historico)
   ============================================================ */
_baja:function(f){
var hoy=new Date().toISOString().slice(0,10);
var enUso=f.estado==='asignado'||f.estado==='en_prestamo';
var h='<div class="alert alert-danger py-2">Dar de baja a <b>'+esc(f.codigo)+'</b> ('+esc(f.tipo)+' '+esc(f.marca||'')+'). El registro queda como histórico; no se puede deshacer.</div>';
if(enUso)h+='<div class="alert alert-warning py-2">Este equipo está <b>asignado'+(f.asignado_a?' a '+esc(f.asignado_a):'')+'</b>. Registre primero su devolución en <i>Asignaciones</i>.</div>';
h+='<div class="row g-3"><div class="col-md-6"><label class="form-label">Motivo *</label><select class="form-select" id="fb-motivo"><option value="">&mdash; Seleccione &mdash;</option>'+
'<option value="obsolescencia">Obsolescencia</option><option value="danado_irreparable">Dañado irreparable</option><option value="robo">Robo</option>'+
'<option value="perdida">Pérdida</option><option value="venta">Venta</option><option value="otros">Otros</option></select></div>'+
'<div class="col-md-6"><label class="form-label">Fecha de baja *</label><input type="date" class="form-control" id="fb-fecha" value="'+hoy+'" max="'+hoy+'"></div>'+
'<div class="col-12"><label class="form-label">Destino final (opcional)</label><input class="form-control" id="fb-destino" maxlength="100" placeholder="Ej: Chatarra autorizada, comprador, proveedor de reciclaje"></div>'+
'<div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" id="fb-obs" rows="2" maxlength="300"></textarea></div></div>'+
'<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button class="btn btn-danger" id="fb-ok"'+(enUso?' disabled':'')+'>Confirmar baja</button></div>';
Modal.abrir('Baja — '+f.codigo,h,'modal-md');
$('#fb-ok').on('click',function(){
var m=$('#fb-motivo').val(),fe=$('#fb-fecha').val();
if(!m){Toast.warning('Seleccione el motivo');return}
if(!fe){Toast.warning('Indique la fecha de baja');return}
Api.post('api/equipos/baja.php',{id:f.id,motivo:m,fecha:fe,destino:$('#fb-destino').val()||'',observaciones:$('#fb-obs').val()||''})
.then(function(r){Modal.cerrar();delete Equipos.sel[f.id];Equipos._recargar();Toast.success((r&&r.message)||'Equipo dado de baja')})
.catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
});
}
};

App.registrar('equipos',Equipos);
