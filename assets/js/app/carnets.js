/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Carnets del personal
   Lista de todo el personal (no cesado) con el estado de su carnet:
   sin carnet -> pendiente -> impreso -> entregado   (vencido se calcula por la vigencia)
   Seleccion multiple para generar / imprimir / marcar; acciones por fila.
   ============================================================ */
var ESTADOS_CAR={
pendiente:{t:'Pendiente',c:'text-bg-warning'},
impreso:{t:'Impreso',c:'text-bg-primary'},
entregado:{t:'Entregado',c:'text-bg-success'}
};
var Carnets={
_datos:[],
_sel:{},
_puede:false,
tabla:null,
LIMITE_IMPRIMIR:300,

init:function(){
var s=this;
s._puede=!!$('#tb-carnets').data('puedeEditar');
s._sel={};
s._datos=[];
s.tabla=null;
s._eventos();
s._cargar();
},

/* ---------- datos ---------- */
_cargar:function(){
var s=this;
return Api.get('api/carnets/listar.php').then(function(filas){
s._datos=filas||[];
// quita de la seleccion a quien ya no esta en la lista
var ids={};s._datos.forEach(function(f){ids[f.id]=1});
Object.keys(s._sel).forEach(function(k){if(!ids[k])delete s._sel[k]});
s._llenarAreas();
s._stats();
s._pintar();
}).catch(function(){});
},

// clave de estado de una fila: sin | pendiente | impreso | entregado
_clave:function(f){return f.carnet_estado||'sin'},
_renovar:function(f){return !!f.carnet_estado&&(+f.vencido===1||+f.datos_cambiaron===1)},

_filtradas:function(){
var est=$('#caf-estado').val()||'',area=$('#caf-area').val()||'',q=($('#caf-buscar').val()||'').trim().toLowerCase(),s=this;
return s._datos.filter(function(f){
if(est==='renovar'){if(!s._renovar(f))return false}
else if(est&&s._clave(f)!==est)return false;
if(area&&String(f.area_id)!==area)return false;
if(q){
var t=[f.codigo_usuario,f.dni,f.nombres,f.apellidos,f.cargo,f.area,f.carnet_codigo].join(' ').toLowerCase();
if(t.indexOf(q)<0)return false;
}
return true;
});
},

_llenarAreas:function(){
var vistos={},ops='<option value="">Todas las áreas</option>',actual=$('#caf-area').val()||'';
this._datos.forEach(function(f){if(f.area_id&&!vistos[f.area_id]){vistos[f.area_id]=f.area||('Área #'+f.area_id)}});
Object.keys(vistos).sort(function(a,b){return vistos[a].localeCompare(vistos[b])}).forEach(function(k){
ops+='<option value="'+esc(k)+'">'+esc(vistos[k])+'</option>'});
$('#caf-area').html(ops).val(actual);
},

_stats:function(){
var n={sin_carnet:0,pendientes:0,impresos:0,entregados:0,renovar:0},s=this;
this._datos.forEach(function(f){
var k=s._clave(f);
if(k==='sin')n.sin_carnet++;else if(k==='pendiente')n.pendientes++;else if(k==='impreso')n.impresos++;else if(k==='entregado')n.entregados++;
if(s._renovar(f))n.renovar++;
});
$('#car-stats [data-k]').each(function(){$(this).text(n[$(this).data('k')])});
},

/* ---------- tabla ---------- */
_pintar:function(){
var s=this,filas=s._filtradas();
if(!s.tabla){
s.tabla=$('#tb-carnets').DataTable({
data:filas,language:DATATABLES_ES,pageLength:25,lengthMenu:[[10,25,50,100],[10,25,50,100]],searching:false,
order:[[3,'asc']],
columns:[
{data:null,orderable:false,className:'text-center',render:function(v,t,f){
return '<input type="checkbox" class="car-chk" data-id="'+f.id+'"'+(s._sel[f.id]?' checked':'')+'>'}},
{data:'codigo_usuario',render:function(v){return v?'<span class="badge bg-light text-dark border font-monospace">'+esc(v)+'</span>':'<span class="text-muted">—</span>'}},
{data:'dni',render:function(v){return esc(v)}},
{data:'apellidos',render:function(v,t,f){
if(t!=='display')return (f.apellidos||'')+' '+(f.nombres||'');
return '<div class="d-flex align-items-center gap-2">'+Foto.avatar({id:f.id,foto:f.foto,nombre:(f.nombres||'')+' '+(f.apellidos||''),size:30,endpoint:'api/personal/foto.php'})+
'<div class="lh-sm"><strong class="small">'+esc(f.apellidos)+'</strong><br><span class="small text-muted">'+esc(f.nombres)+'</span></div></div>'}},
{data:'area',render:function(v){return v?esc(v):'—'}},
{data:'cargo',render:function(v){return '<span class="small">'+(v?esc(v):'—')+'</span>'}},
{data:'carnet_codigo',render:function(v){return v?'<code>'+esc(v)+'</code>':'<span class="text-muted small">sin carnet</span>'}},
{data:'carnet_vigencia',render:function(v,t,f){
if(!v)return '—';
return +f.vencido===1?'<span class="text-danger fw-semibold">'+esc(v)+'</span>':esc(v)}},
{data:'carnet_estado',render:function(v,t,f){return s._badge(f)}},
{data:null,orderable:false,className:'text-center text-nowrap',render:function(v,t,f){return s._acciones(f)}}
]});
}else{
s.tabla.clear().rows.add(filas).draw(false);
}
s._botones();
},

_badge:function(f){
if(!f.carnet_estado)return '<span class="badge text-bg-secondary">Sin carnet</span>';
var e=ESTADOS_CAR[f.carnet_estado]||{t:f.carnet_estado,c:'text-bg-light'};
var h='<span class="badge '+e.c+'">'+e.t+'</span>';
if(+f.vencido===1)h+=' <span class="badge text-bg-danger" title="Pasó la fecha de vigencia">Vencido</span>';
if(+f.datos_cambiaron===1)h+=' <span class="badge text-bg-warning text-dark" title="Cambiaron datos que van impresos (nombre, cargo, área, código o foto)">Reimprimir</span>';
return h;
},

_acciones:function(f){
var h='',k=this._clave(f);
if(k==='sin'){
return this._puede?'<button class="btn btn-sm btn-outline-primary btn-car" data-a="generar" title="Generar carnet"><i class="bi bi-plus-lg"></i></button>':'';
}
h+='<button class="btn btn-sm btn-outline-dark btn-car" data-a="imprimir" title="Imprimir carnet"><i class="bi bi-printer"></i></button>';
if(!this._puede)return h;
if(k==='pendiente')h+=' <button class="btn btn-sm btn-outline-primary btn-car" data-a="impreso" title="Marcar como impreso"><i class="bi bi-check2"></i></button>';
if(k==='impreso')h+=' <button class="btn btn-sm btn-outline-success btn-car" data-a="entregado" title="Marcar como entregado"><i class="bi bi-hand-thumbs-up"></i></button>';
if(this._renovar(f))h+=' <button class="btn btn-sm btn-outline-warning btn-car" data-a="renovar" title="'+(+f.vencido===1?'Renovar vigencia':'Reimprimir con los datos nuevos')+'"><i class="bi bi-arrow-repeat"></i></button>';
if(k!=='pendiente')h+=' <button class="btn btn-sm btn-outline-secondary btn-car" data-a="revertir" title="Volver un estado atrás"><i class="bi bi-arrow-counterclockwise"></i></button>';
return h;
},

/* ---------- seleccion y botones ---------- */
_idsSel:function(){return Object.keys(this._sel).map(Number)},
_selFilas:function(){var s=this;return s._datos.filter(function(f){return s._sel[f.id]})},

// ids para imprimir: los marcados con carnet; si no hay marcados, los pendientes de lo filtrado
_paraImprimir:function(){
var s=this,m=s._selFilas().filter(function(f){return f.carnet_estado});
if(m.length)return{ids:m.map(function(f){return f.id}),pend:false};
return{ids:s._filtradas().filter(function(f){return f.carnet_estado==='pendiente'}).map(function(f){return f.id}),pend:true};
},

_botones:function(){
var s=this,sel=s._selFilas();
var gen=sel.filter(function(f){return !f.carnet_estado}).length;
var mi=sel.filter(function(f){return f.carnet_estado==='pendiente'}).length;
var me=sel.filter(function(f){return f.carnet_estado==='impreso'}).length;
var imp=s._paraImprimir();
$('#car-n-gen').text(gen);$('#btn-generar-carnets').prop('disabled',!gen);
$('#car-n-mi').text(mi);$('#btn-marcar-impreso').prop('disabled',!mi);
$('#car-n-me').text(me);$('#btn-marcar-entregado').prop('disabled',!me);
$('#car-n-imp').text(imp.ids.length);$('#car-lbl-imp').text(imp.pend?'Imprimir pendientes':'Imprimir seleccionados');
$('#btn-imprimir-carnets').prop('disabled',!imp.ids.length);
// casilla de "todos": marcada si todos los de la pagina visible estan marcados
var vis=s.tabla?s.tabla.rows({page:'current'}).data().toArray():[];
$('#car-todos').prop('checked',vis.length>0&&vis.every(function(f){return s._sel[f.id]}));
},

/* ---------- eventos ---------- */
_eventos:function(){
var s=this;
$('#caf-estado,#caf-area').off('change').on('change',function(){s._pintar()});
$('#caf-buscar').off('input').on('input',function(){s._pintar()});
$('#caf-limpiar').off('click').on('click',function(){$('#caf-estado,#caf-area,#caf-buscar').val('');s._pintar()});
$('#tb-carnets').off('change','.car-chk').on('change','.car-chk',function(){
var id=+$(this).data('id');
if(this.checked)s._sel[id]=1;else delete s._sel[id];
s._botones();
});
$('#car-todos').off('change').on('change',function(){
var on=this.checked;
s.tabla.rows({page:'current'}).data().toArray().forEach(function(f){if(on)s._sel[f.id]=1;else delete s._sel[f.id]});
$('#tb-carnets .car-chk').prop('checked',on);
s._botones();
});
$('#tb-carnets').off('draw.dt').on('draw.dt',function(){s._botones()});
$('#tb-carnets').off('click','.btn-car').on('click','.btn-car',function(){
var f=s.tabla.row($(this).closest('tr')).data();
if(f)s._accion($(this).data('a'),f);
});
$('#btn-generar-carnets').off('click').on('click',function(){
s._generar(s._selFilas().filter(function(f){return !f.carnet_estado}).map(function(f){return f.id}));
});
$('#btn-imprimir-carnets').off('click').on('click',function(){s._imprimir(s._paraImprimir().ids)});
$('#btn-marcar-impreso').off('click').on('click',function(){
s._marcar('impreso',s._selFilas().filter(function(f){return f.carnet_estado==='pendiente'}).map(function(f){return f.id}));
});
$('#btn-marcar-entregado').off('click').on('click',function(){
s._marcar('entregado',s._selFilas().filter(function(f){return f.carnet_estado==='impreso'}).map(function(f){return f.id}));
});
},

_accion:function(a,f){
var s=this,nombre=(f.nombres||'')+' '+(f.apellidos||'');
if(a==='generar')return s._generar([f.id]);
if(a==='imprimir')return s._imprimir([f.id]);
if(a==='impreso'||a==='entregado')return s._marcar(a,[f.id]);
if(a==='renovar'){
var vence=+f.vencido===1;
return Swal.fire({title:vence?'¿Renovar el carnet?':'¿Reimprimir el carnet?',
html:'<b>'+esc(nombre)+'</b><br><small>'+(vence
?'Se emite con fecha nueva y vigencia renovada, y vuelve a <b>Pendiente</b> para imprimirlo.'
:'Cambiaron datos impresos. El carnet vuelve a <b>Pendiente</b> (misma vigencia) para imprimirlo de nuevo.')+'</small>',
icon:'question',showCancelButton:true,confirmButtonText:'Sí, continuar',cancelButtonText:'Cancelar'
}).then(function(r){
if(!r.isConfirmed)return;
Api.post('api/carnets/actualizar.php',{id:f.id,renovar:vence?1:0}).then(function(){
Toast.success(vence?'Carnet renovado. Listo para imprimir.':'Carnet listo para reimprimir.');
s._cargar();
}).catch(function(){});
});
}
if(a==='revertir'){
var ant=f.carnet_estado==='entregado'?'Impreso':'Pendiente';
return Swal.fire({title:'¿Volver un estado atrás?',
html:'<b>'+esc(nombre)+'</b><br><small>El carnet pasará de <b>'+esc(ESTADOS_CAR[f.carnet_estado].t)+'</b> a <b>'+ant+'</b>.</small>',
icon:'warning',showCancelButton:true,confirmButtonText:'Sí, revertir',cancelButtonText:'Cancelar'
}).then(function(r){
if(!r.isConfirmed)return;
Api.post('api/carnets/revertir.php',{id:f.id}).then(function(){
Toast.success('Carnet revertido a '+ant+'.');
s._cargar();
}).catch(function(){});
});
}
},

_generar:function(ids){
var s=this;
if(!ids.length)return;
Api.post('api/carnets/generar.php',{personal_ids:JSON.stringify(ids)}).then(function(r){
var n=(r&&r.creados)||0,err=(r&&r.errores)||[];
if(n)Toast.success(n+' carnet(s) generado(s) en estado Pendiente.');
if(err.length)Toast.warning(err.slice(0,3).join(' ')+(err.length>3?' (+'+(err.length-3)+' más)':''));
ids.forEach(function(id){delete s._sel[id]});
s._cargar();
}).catch(function(){});
},

_imprimir:function(ids){
if(!ids.length)return;
if(ids.length>this.LIMITE_IMPRIMIR){Toast.warning('Imprima de a '+this.LIMITE_IMPRIMIR+' carnets como máximo. Seleccione menos personas.');return}
var w=window.open(BASE_URL+'views/carnets/imprimir.php?ids='+ids.join(','),'_blank');
if(!w)Toast.warning('El navegador bloqueó la ventana de impresión. Permita las ventanas emergentes para este sitio.');
},

// la ventana de impresion avisa al terminar: se ofrece marcar como impresos los que estaban pendientes
despuesDeImprimir:function(ids){
var s=this;
if(!s._puede)return;
var pend=s._datos.filter(function(f){return ids.indexOf(f.id)>=0&&f.carnet_estado==='pendiente'}).map(function(f){return f.id});
if(!pend.length)return;
Swal.fire({title:'¿Se imprimieron bien?',
html:'Marcar como <b>Impreso</b> '+pend.length+' carnet(s) que estaban Pendientes.',
icon:'question',showCancelButton:true,confirmButtonText:'Sí, marcar impresos',cancelButtonText:'Todavía no'
}).then(function(r){if(r.isConfirmed)s._marcar('impreso',pend)});
},

_marcar:function(estado,ids){
var s=this;
if(!ids.length)return;
Api.post('api/carnets/estado.php',{estado:estado,ids:JSON.stringify(ids)}).then(function(r){
var n=(r&&r.cambiados)||0,om=(r&&r.omitidos)||[];
if(n)Toast.success(n+' carnet(s) marcado(s) como '+estado+'.');
if(om.length)Toast.warning(om.slice(0,3).join(' ')+(om.length>3?' (+'+(om.length-3)+' más)':''));
ids.forEach(function(id){delete s._sel[id]});
s._cargar();
}).catch(function(){});
}
};
App.registrar('carnets',Carnets);
