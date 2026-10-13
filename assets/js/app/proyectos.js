/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   PROYECTOS Y OBRAS — registro con plazo + equipos enviados y su devolucion estipulada
   ============================================================ */
var SIT_PY={
en_curso:{t:'En curso',c:'text-bg-primary'},
por_vencer:{t:'Por vencer',c:'text-bg-warning text-dark'},
vencido:{t:'Plazo vencido',c:'text-bg-danger'},
por_iniciar:{t:'Por iniciar',c:'text-bg-info'},
finalizado:{t:'Finalizado',c:'text-bg-dark'}
};

var Proyectos={
tabla:null,
datos:[],
perm:{crear:false},

init:function(){
var s=this;
s.perm={crear:+$('#py-permisos').data('crear')===1};
 $('#btn-nuevo-proy').off('click.py').on('click.py',function(){s._form(null)});
 $('#pyf-tipo,#pyf-est,#pyf-problemas').off('change.py').on('change.py',function(){s._pintar()});
 $('#tb-proy').off('click.py').on('click.py','.btn-ver-py,.btn-asignar-py,.btn-editar-py,.btn-estado-py',function(){
var id=+$(this).data('id');
if($(this).hasClass('btn-ver-py'))s._ver(id);
else if($(this).hasClass('btn-asignar-py'))s._asignar(id);
else if($(this).hasClass('btn-editar-py'))s._editar(id);
else s._cambiarEstado(id);
});
s._cargar();
},

_cargar:function(luego){
var s=this;
Api.get('api/proyectos/listar.php').then(function(r){
s.datos=(r&&r.lista)||[];
var st=(r&&r.stats)||{};
 $('#py-stats [data-k]').each(function(){ $(this).text(st[$(this).data('k')]||0) });
s._pintar();
if(luego)luego();
}).catch(function(){});
},

_fecha:function(v){
if(!v)return '—';
var p=String(v).slice(0,10).split('-');
return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:esc(v);
},

_txtDias:function(d,vence){
if(d===null||d===undefined)return '';
if(d<0)return (vence?'venció':'vencido')+' hace '+Math.abs(d)+' d';
if(d===0)return (vence?'vence':'vence')+' hoy';
return 'faltan '+d+' d';
},

_pintar:function(){
var s=this,tipo=$('#pyf-tipo').val()||'',est=$('#pyf-est').val(),prob=$('#pyf-problemas').is(':checked');
var l=(s.datos||[]).filter(function(p){
if(tipo&&p.tipo!==tipo)return false;
if(est==='abiertos'&&p.estado!=='activo')return false;
if(est==='finalizados'&&p.estado==='activo')return false;
if(prob&&!(+p.n_vencidos>0||p.situacion==='vencido'))return false;
return true;
});
if(s.tabla){s.tabla.destroy();s.tabla=null}
 $('#tb-proy tbody').empty();
s.tabla=$('#tb-proy').DataTable({
data:l,
language:DATATABLES_ES,
pageLength:10,
order:[[3,'asc']],
columns:[
{data:'codigo',render:function(v){return '<strong>'+esc(v)+'</strong>'}},
{data:'nombre',render:function(v,t,p){
var b=p.tipo==='obra'?'<span class="badge text-bg-warning text-dark"><i class="bi bi-cone-striped"></i> Obra</span>':'<span class="badge text-bg-success"><i class="bi bi-kanban"></i> Proyecto</span>';
return '<div>'+esc(v)+' '+b+'</div><div class="small text-muted">'+esc([p.cliente,p.ubicacion].filter(Boolean).join(' · ')||'')+'</div>'}},
{data:'responsable',render:function(v){return v?esc(v):'<span class="text-muted">—</span>'}},
{data:'fecha_fin',render:function(v,t,p){
if(t!=='display')return v||'9999-12-31';
var h='<div class="small">'+Proyectos._fecha(p.fecha_inicio)+' → <b>'+Proyectos._fecha(p.fecha_fin)+'</b></div>';
if(p.estado==='activo'&&p.dias_fin!==null)h+='<div class="small '+(p.dias_fin<0?'text-danger fw-semibold':'text-muted')+'">'+Proyectos._txtDias(p.dias_fin,true)+'</div>';
if(p.estado!=='activo'&&p.fecha_cierre)h+='<div class="small text-muted">cerrado el '+Proyectos._fecha(p.fecha_cierre)+'</div>';
return h}},
{data:'situacion',render:function(v){var e=SIT_PY[v]||{t:v,c:'text-bg-light'};return '<span class="badge '+e.c+'">'+e.t+'</span>'}},
{data:'n_equipos',render:function(v,t,p){
if(t!=='display')return v;
var h='<span class="badge text-bg-secondary">'+(+v)+'</span>';
if(+p.n_vencidos>0)h+=' <span class="badge text-bg-danger" title="Equipos cuya devolución estipulada ya venció">'+p.n_vencidos+' vencida(s)</span>';
return h}},
{data:'prox_devolucion',render:function(v,t,p){
if(t!=='display')return v||'9999-12-31';
if(!v||+p.n_equipos===0)return '<span class="text-muted">—</span>';
var d=Math.round((new Date(String(v).slice(0,10)+'T00:00:00')-new Date(new Date().toISOString().slice(0,10)+'T00:00:00'))/86400000);
return '<div class="small"><b>'+Proyectos._fecha(v)+'</b></div><div class="small '+(d<0?'text-danger fw-semibold':'text-muted')+'">'+Proyectos._txtDias(d,false)+'</div>'}},
{data:null,orderable:false,className:'text-center text-nowrap',render:function(v,t,p){
var h='<button class="btn btn-sm btn-outline-primary btn-ver-py me-1" data-id="'+p.id+'" title="Ver ficha y equipos"><i class="bi bi-eye"></i></button>';
if(Proyectos.perm.crear){
if(p.estado==='activo')h+='<button class="btn btn-sm btn-outline-success btn-asignar-py me-1" data-id="'+p.id+'" title="Enviar un equipo"><i class="bi bi-box-arrow-right"></i></button>';
h+='<button class="btn btn-sm btn-outline-secondary btn-editar-py me-1" data-id="'+p.id+'" title="Editar"><i class="bi bi-pencil"></i></button>';
h+='<button class="btn btn-sm btn-outline-'+(p.estado==='activo'?'dark':'warning')+' btn-estado-py" data-id="'+p.id+'" title="'+(p.estado==='activo'?'Finalizar':'Reabrir')+'"><i class="bi bi-'+(p.estado==='activo'?'flag':'arrow-counterclockwise')+'"></i></button>';
}
return h}}
]
});
},

_por:function(id){return (this.datos||[]).filter(function(p){return +p.id===+id})[0]},

/* ---------- alta / edicion ---------- */
_editar:function(id){this._form(this._por(id)||null)},
_form:function(p){
var s=this,nuevo=!p,hoy=new Date().toISOString().slice(0,10);
Api.get('api/catalogos/select.php',{tipo:'personal'}).catch(function(){return[]}).then(function(per){
per=per||[];
var tipo=p?p.tipo:'proyecto';
var html='<form id="form-py" autocomplete="off"><div class="row g-3">'+
'<div class="col-md-5"><label class="form-label">Tipo *</label><div class="btn-group w-100">'+
'<input type="radio" class="btn-check" name="py-tipo" id="pyt-proyecto" value="proyecto"'+(tipo==='proyecto'?' checked':'')+'><label class="btn btn-outline-success btn-sm" for="pyt-proyecto"><i class="bi bi-kanban"></i> Proyecto</label>'+
'<input type="radio" class="btn-check" name="py-tipo" id="pyt-obra" value="obra"'+(tipo==='obra'?' checked':'')+'><label class="btn btn-outline-warning btn-sm" for="pyt-obra"><i class="bi bi-cone-striped"></i> Obra</label></div></div>'+
'<div class="col-md-3"><label class="form-label">Código</label><input class="form-control" id="py-codigo" maxlength="20" placeholder="Automático" value="'+esc(p?p.codigo:'')+'"></div>'+
'<div class="col-md-12"><label class="form-label">Nombre *</label><input class="form-control" id="py-nombre" maxlength="120" value="'+esc(p?p.nombre:'')+'" placeholder="Ej: Edificio Torre Norte"></div>'+
'<div class="col-md-6"><label class="form-label">Cliente</label><input class="form-control" id="py-cliente" maxlength="120" value="'+esc(p&&p.cliente||'')+'"></div>'+
'<div class="col-md-6"><label class="form-label">Ubicación</label><input class="form-control" id="py-ubic" maxlength="150" value="'+esc(p&&p.ubicacion||'')+'" placeholder="Distrito / dirección"></div>'+
'<div class="col-md-12"><label class="form-label">Responsable (jefe de proyecto / residente)</label><select class="form-select" id="py-resp"><option value="">— Sin responsable —</option>'+
per.map(function(x){return '<option value="'+x.id+'"'+(p&&+p.responsable_id===+x.id?' selected':'')+'>'+esc(x.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-6"><label class="form-label">Fecha de inicio *</label><input type="date" class="form-control" id="py-ini" value="'+esc(p&&p.fecha_inicio?String(p.fecha_inicio).slice(0,10):hoy)+'"></div>'+
'<div class="col-md-6"><label class="form-label">Fecha de fin (plazo) *</label><input type="date" class="form-control" id="py-fin" value="'+esc(p&&p.fecha_fin?String(p.fecha_fin).slice(0,10):'')+'"></div>'+
'<div class="col-12 form-text mt-0">El plazo limita la <b>devolución estipulada</b> de cada equipo que se envíe: ninguno puede quedar más allá del fin.</div>'+
'<div class="col-12"><label class="form-label">Descripción / alcance</label><textarea class="form-control" id="py-desc" rows="2" maxlength="250">'+esc(p&&p.descripcion||'')+'</textarea></div>'+
'</div><div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button type="button" class="btn btn-primary" id="py-ok"><i class="bi bi-check-lg"></i> '+(nuevo?'Registrar':'Guardar')+'</button></div></form>';
Modal.abrir(nuevo?'Nuevo proyecto / obra':'Editar '+p.codigo,html,'modal-lg');
 $('#py-ok').on('click',function(){
var d={id:p?p.id:'',clase:$('input[name=py-tipo]:checked').val()||'proyecto',codigo:($('#py-codigo').val()||'').trim(),
nombre:($('#py-nombre').val()||'').trim(),cliente:($('#py-cliente').val()||'').trim(),ubicacion:($('#py-ubic').val()||'').trim(),
responsable_id:$('#py-resp').val()||'',fecha_inicio:$('#py-ini').val()||'',fecha_fin:$('#py-fin').val()||'',descripcion:($('#py-desc').val()||'').trim()};
if(!d.nombre){Toast.warning('Indique el nombre');$('#py-nombre').trigger('focus');return}
if(!d.fecha_inicio){Toast.warning('Indique la fecha de inicio');return}
if(!d.fecha_fin){Toast.warning('Indique la fecha de fin (plazo)');$('#py-fin').trigger('focus');return}
if(d.fecha_fin<d.fecha_inicio){Toast.warning('El fin no puede ser anterior al inicio');return}
var $b=$('#py-ok').prop('disabled',true);
Api.post('api/proyectos/guardar.php',d).then(function(r){
Modal.cerrar();
Toast.success((r&&r.mensaje)||'Guardado');
s._cargar();
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
});
},

/* ---------- enviar un equipo: abre Asignaciones ya preparado ---------- */
_asignar:function(id){
var p=this._por(id);
if(!p)return;
Modal.cerrar();
Asignaciones.pendiente={operacion:'entrega',destino:p.tipo,proyecto_id:p.id};
Router.ir('asignaciones/lista');
},

/* ---------- finalizar / reabrir ---------- */
_cambiarEstado:function(id){
var s=this,p=s._por(id);
if(!p)return;
var que=p.tipo==='obra'?'la obra':'el proyecto';
if(p.estado==='activo'){
Swal.fire({title:'¿Finalizar '+esc(p.codigo)+'?',html:'Se cierra '+que+' <b>'+esc(p.nombre)+'</b>. Solo es posible si ya no tiene equipos sin devolver.',
icon:'question',showCancelButton:true,confirmButtonText:'Sí, finalizar',cancelButtonText:'Cancelar',confirmButtonColor:'#16a34a'})
.then(function(r){
if(!r.isConfirmed)return;
Api.post('api/proyectos/estado.php',{id:id,accion:'finalizar'}).then(function(d){
Toast.success((d&&d.mensaje)||'Finalizado');s._cargar();
}).catch(function(){});
});
return;
}
var vencido=!p.fecha_fin||String(p.fecha_fin).slice(0,10)<new Date().toISOString().slice(0,10);
Swal.fire({title:'¿Reabrir '+esc(p.codigo)+'?',html:vencido?'El plazo ya pasó: indique la <b>nueva fecha de fin</b>.':'Volverá a recibir equipos.',
icon:'question',input:vencido?'date':undefined,inputLabel:vencido?'Nueva fecha de fin':undefined,
inputValidator:vencido?function(v){if(!v)return 'Indique la nueva fecha de fin'}:undefined,
showCancelButton:true,confirmButtonText:'Sí, reabrir',cancelButtonText:'Cancelar'})
.then(function(r){
if(!r.isConfirmed)return;
Api.post('api/proyectos/estado.php',{id:id,accion:'reabrir',fecha_fin:vencido?(r.value||''):''}).then(function(d){
Toast.success((d&&d.mensaje)||'Reabierto');s._cargar();
}).catch(function(){});
});
},

/* ---------- ficha ---------- */
_ver:function(id){
var s=this;
Api.get('api/proyectos/detalle.php',{id:id}).then(function(p){
var e=SIT_PY[p.situacion]||{t:p.situacion,c:'text-bg-light'};
var tb=p.tipo==='obra'?'<span class="badge text-bg-warning text-dark"><i class="bi bi-cone-striped"></i> Obra</span>':'<span class="badge text-bg-success"><i class="bi bi-kanban"></i> Proyecto</span>';
var cel=function(t,x){return '<div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="text-muted small">'+t+'</div><strong class="small">'+(x===''||x==null?'&mdash;':x)+'</strong></div></div>'};
var pct=null;
if(p.dias_total){pct=Math.max(0,Math.min(100,Math.round(100*(+p.dias_usados||0)/p.dias_total)))}
var html='<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2"><div><h5 class="mb-0">'+esc(p.codigo)+' · '+esc(p.nombre)+'</h5>'+
'<div class="text-muted small">'+tb+' '+(p.cliente?'Cliente: '+esc(p.cliente):'')+'</div></div><span class="badge '+e.c+' fs-6" id="py-sit">'+e.t+'</span></div>'+
(pct!==null&&p.estado==='activo'?'<div class="mb-3" id="py-barra"><div class="d-flex justify-content-between small text-muted"><span>Plazo transcurrido</span><span>'+pct+'% · '+(p.dias_fin<0?'<b class="text-danger">'+Proyectos._txtDias(p.dias_fin,true)+'</b>':Proyectos._txtDias(p.dias_fin,true))+'</span></div>'+
'<div class="progress" style="height:8px"><div class="progress-bar '+(p.dias_fin<0?'bg-danger':(p.situacion==='por_vencer'?'bg-warning':'bg-primary'))+'" style="width:'+pct+'%"></div></div></div>':'')+
'<div class="row g-2 mb-3">'+cel('Responsable',esc(p.responsable||''))+cel('Ubicación',esc(p.ubicacion||''))+
cel('Inicio',Proyectos._fecha(p.fecha_inicio))+cel(p.estado==='activo'?'Fin (plazo)':'Cerrado el',p.estado==='activo'?Proyectos._fecha(p.fecha_fin):Proyectos._fecha(p.fecha_cierre))+'</div>'+
(p.descripcion?'<div class="small text-muted mb-3">'+esc(p.descripcion)+'</div>':'');

html+='<h6 class="mt-2"><i class="bi bi-box-seam"></i> Equipos enviados <span class="badge text-bg-secondary">'+p.enviados.length+'</span>'+
(+p.n_vencidos>0?' <span class="badge text-bg-danger">'+p.n_vencidos+' devolución(es) vencida(s)</span>':'')+'</h6>';
if(!p.enviados.length)html+='<div class="text-muted small mb-3" id="py-sin-eq">No hay equipos enviados a este '+(p.tipo==='obra'?'obra':'proyecto')+' en este momento.</div>';
else{
html+='<div class="table-responsive mb-3"><table class="table table-sm table-bordered align-middle mb-0" id="py-tb-enviados"><thead><tr><th>Equipo</th><th>Enviado el</th><th>Devolución estipulada</th><th>Recibe</th><th>Frente / zona</th><th></th></tr></thead><tbody>';
p.enviados.forEach(function(x){
var dv=x.dias_devolucion;
html+='<tr'+(x.vencida?' class="table-danger"':'')+'><td><b>'+esc(x.equipo_codigo)+'</b><div class="small text-muted">'+esc((x.equipo_tipo||'')+' '+(x.marca||'')+' '+(x.modelo||''))+'</div></td>'+
'<td class="small">'+Proyectos._fecha(x.fecha_entrega)+'</td>'+
'<td class="small"><b>'+Proyectos._fecha(x.fecha_devolucion_esperada)+'</b>'+(dv!==null?'<div class="'+(dv<0?'text-danger fw-semibold':'text-muted')+'">'+Proyectos._txtDias(dv,false)+'</div>':'')+
(x.fuera_de_plazo?'<div class="text-danger">posterior al fin del plazo</div>':'')+'</td>'+
'<td class="small">'+esc(x.responsable||'—')+'</td><td class="small">'+esc(x.destino_detalle||'—')+'</td>'+
'<td class="text-center">'+(x.acta_token?'<a class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener" title="Ver acta" href="'+BASE_URL+'acta.php?token='+encodeURIComponent(x.acta_token)+'"><i class="bi bi-file-earmark-text"></i></a>':'')+'</td></tr>';
});
html+='</tbody></table></div>';
}
if(p.devueltos.length){
html+='<h6 class="mt-3"><i class="bi bi-arrow-return-left"></i> Ya devueltos <span class="badge text-bg-secondary">'+p.devueltos.length+'</span></h6>'+
'<div class="table-responsive mb-2"><table class="table table-sm table-bordered align-middle mb-0" id="py-tb-devueltos"><thead><tr><th>Equipo</th><th>Enviado</th><th>Estipulada</th><th>Devuelto</th><th>Resultado</th></tr></thead><tbody>';
p.devueltos.forEach(function(x){
html+='<tr><td><b>'+esc(x.equipo_codigo)+'</b> <span class="small text-muted">'+esc(x.equipo_tipo||'')+'</span></td><td class="small">'+Proyectos._fecha(x.fecha_entrega)+'</td>'+
'<td class="small">'+Proyectos._fecha(x.fecha_devolucion_esperada)+'</td><td class="small">'+Proyectos._fecha(x.fecha_devolucion_real)+'</td>'+
'<td class="small">'+(x.atraso_dias===null?'—':(x.atraso_dias>0?'<span class="badge text-bg-danger">'+x.atraso_dias+' d tarde</span>':'<span class="badge text-bg-success">A tiempo</span>'))+'</td></tr>';
});
html+='</tbody></table></div>';
}
html+='<div class="text-end mt-3">'+
(p.puede.asignar?'<button class="btn btn-success btn-sm" id="py-btn-asignar"><i class="bi bi-box-arrow-right"></i> Enviar un equipo</button> ':'')+
(p.puede.editar?'<button class="btn btn-outline-secondary btn-sm" id="py-btn-editar"><i class="bi bi-pencil"></i> Editar</button> ':'')+
'<button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button></div>';
Modal.abrir('Proyecto / obra — '+p.codigo,html,'modal-xl');
 $('#py-btn-asignar').on('click',function(){s._asignar(p.id)});
 $('#py-btn-editar').on('click',function(){Modal.cerrar();s._form(s._por(p.id)||p)});
}).catch(function(){});
}
};
App.registrar('proyectos',Proyectos);
