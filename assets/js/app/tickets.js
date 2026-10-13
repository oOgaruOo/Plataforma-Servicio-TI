/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var ESTADOS_TK={abierto:{t:'Abierto',c:'text-bg-secondary'},asignado:{t:'Asignado',c:'text-bg-info'},
en_atencion:{t:'En atencion',c:'text-bg-primary'},pendiente_usuario:{t:'Pend.usuario',c:'text-bg-warning text-dark'},
pendiente_proveedor:{t:'Pend.proveedor',c:'text-bg-warning text-dark'},resuelto:{t:'RESUELTO',c:'text-bg-success'},
cerrado:{t:'CERRADO',c:'text-bg-dark'},cancelado:{t:'Cancelado',c:'text-bg-danger'}};
var EV_TK={creado:{i:'bi-plus-circle',t:'Ticket creado'},actualizado:{i:'bi-pencil',t:'Ticket editado'},
asignado:{i:'bi-person-check',t:'Asignación'},cambio_estado:{i:'bi-arrow-repeat',t:'Cambio de estado'},
comentario:{i:'bi-chat-left-text',t:'Comentario'},evidencia:{i:'bi-camera',t:'Evidencia de la solución'},
finalizado:{i:'bi-flag-fill',t:'Ticket FINALIZADO'}};
function fmtMinTk(min){min=+min||0;if(min<60)return min+' min';if(min<1440)return Math.floor(min/60)+' h'+(min%60?' '+(min%60)+' min':'');var d=Math.floor(min/1440),h=Math.floor((min%1440)/60);return d+' d'+(h?' '+h+' h':'')}
function fmtFechaTk(v){if(!v)return '—';var p=String(v).split(/[- :]/);return p[2]+'/'+p[1]+'/'+p[0]+' '+String(p[3]||'').slice(0,5)}
var Tickets={
tabla:null,
init:function(){
this.tabla=DT.server('#tb-tickets','api/tickets/listar.php',
function(){return{f_estado:$('#tf-estado').val()||'',f_prioridad:$('#tf-prioridad').val()||0,f_categoria:$('#tf-categoria').val()||0,f_tecnico:$('#tf-tecnico').val()||-1,f_mios:$('#tf-mios').is(':checked')?1:0}},
[
{data:'codigo',render:function(v){return '<strong>'+esc(v)+'</strong>'}},
{data:'titulo',render:function(v,t,f){
var extra='';
if(+f.n_comentarios>0)extra+=' <span class="badge text-bg-light border" title="Comentarios"><i class="bi bi-chat-left-text"></i> '+f.n_comentarios+'</span>';
if(+f.n_adjuntos>0)extra+=' <span class="badge text-bg-light border" title="Adjuntos"><i class="bi bi-paperclip"></i> '+f.n_adjuntos+'</span>';
if(+f.reaperturas>0)extra+=' <span class="badge text-bg-warning text-dark" title="Reaperturas"><i class="bi bi-arrow-repeat"></i> '+f.reaperturas+'</span>';
return '<span class="small">'+esc(v)+'</span>'+extra}},
{data:'categoria',render:function(v){return '<span class="badge text-bg-light border">'+esc(v)+'</span>'}},
{data:'prioridad',render:function(v,t,f){return '<span class="badge" style="background:'+(f.prioridad_color||'#666')+';color:#fff">'+esc(v)+'</span>'}},
{data:'solicitante',render:function(v){return '<span class="small">'+esc(v)+'</span>'}},
{data:'area',render:function(v){return '<span class="small">'+esc(v)+'</span>'}},
{data:'tecnico',render:function(v,t,f){return v?'<span class="small" title="Asignado: '+esc(f.fecha_asignacion||'—')+'">'+esc(v)+'</span>':'<span class="text-muted small">sin asignar</span>'}},
{data:'estado',render:function(v){var e=ESTADOS_TK[v]||{t:v,c:'text-bg-light'};return '<span class="badge '+e.c+'">'+e.t+'</span>'}},
{data:'fecha_creacion',className:'nowrap',render:function(v){if(!v)return '—';var p=String(v).split(/[- :]/);return '<span class="small text-muted" title="Apertura del ticket">'+p[2]+'/'+p[1]+'/'+p[0]+' '+String(p[3]||'').slice(0,5)+'</span>'}},
{data:'sla_limite',className:'nowrap',render:function(v,t,f){
if((f.estado==='resuelto'||f.estado==='cerrado')&&f.fecha_resolucion){
var am=+f.fin_atraso_min||0;
return '<span class="small text-muted d-block" title="Fecha y hora en que se finalizó">'+fmtFechaTk(f.fecha_resolucion)+'</span>'+
(am>0?'<span class="badge text-bg-danger">Atrasado '+esc(fmtMinTk(am))+'</span>':'<span class="badge text-bg-success">A tiempo</span>')}
if(!v||['resuelto','cerrado','cancelado'].indexOf(f.estado)>=0)return '—';
var r=(new Date(v)-Date.now())/1000;
if(r<=0)return '<span class="badge text-bg-danger">VENCIDO</span>';
var h=Math.floor(r/3600),m=Math.floor((r%3600)/60);
return '<span class="badge '+(r<7200?'text-bg-warning text-dark':'text-bg-success')+'">'+(h>0?h+'h ':'')+m+'m</span>'}},
{data:null,orderable:false,className:'text-center nowrap',render:function(v,t,f){
var b='<div class="btn-group btn-group-sm" role="group">'+
'<button class="btn btn-outline-primary btn-ver" title="Ver detalle y seguimiento"><i class="bi bi-eye"></i></button>';
if(+f.puede_finalizar===1)b+='<button class="btn btn-outline-success btn-finalizar-tk" title="Finalizar ticket"><i class="bi bi-check2-circle"></i></button>';
if(f.puede_editar)b+='<button class="btn btn-outline-secondary btn-editar" title="Editar ticket"><i class="bi bi-pencil"></i></button>';
if(f.puede_asignar)b+='<button class="btn btn-outline-info btn-reasignar" title="Asignar / reasignar técnico"><i class="bi bi-person-check"></i></button>';
if(f.puede_eliminar)b+='<button class="btn btn-outline-danger btn-eliminar" title="Eliminar ticket"><i class="bi bi-trash"></i></button>';
return b+'</div>';
}}
]);
 $('#btn-filtrar-tickets').on('click',function(){Tickets.tabla.draw()});
 $('#btn-limpiar-tickets').on('click',function(){
 $('#tf-estado,#tf-prioridad,#tf-categoria').val(''); $('#tf-tecnico').val('-1'); $('#tf-mios').prop('checked',false); Tickets.tabla.draw();
});
 $('#tf-estado,#tf-prioridad,#tf-categoria,#tf-tecnico,#tf-mios').on('change',function(){Tickets.tabla.draw()});
 $('#btn-nuevo-ticket').on('click',function(){Tickets._form()});
 $('#tb-tickets').on('click','.btn-ver',function(){
var f=Tickets.tabla.row($(this).closest('tr')).data();
if(f)Tickets._ver(f.id);
});
 $('#tb-tickets').on('click','.btn-finalizar-tk',function(){
var f=Tickets.tabla.row($(this).closest('tr')).data();
if(f)Api.get('api/tickets/detalle.php',{id:f.id}).then(function(t){Tickets._t=t;Tickets._finalizar(t)});
});
 $('#tb-tickets').on('click','.btn-editar',function(){
var f=Tickets.tabla.row($(this).closest('tr')).data();
if(f)Tickets._form(f.id);
});
 $('#tb-tickets').on('click','.btn-reasignar',function(){
var f=Tickets.tabla.row($(this).closest('tr')).data();
if(f)Tickets._reasignar(f);
});
 $('#tb-tickets').on('click','.btn-eliminar',function(){
var f=Tickets.tabla.row($(this).closest('tr')).data();
if(f)Tickets._eliminar(f);
});
// auto-refresco silencioso cada 60 s (solo si la vista esta activa y la pestaña visible)
if(Tickets._refresh)clearInterval(Tickets._refresh);
Tickets._refresh=setInterval(function(){
if($('#tb-tickets').length&&!document.hidden){Tickets.tabla.draw(false);Tickets._stats()}
},60000);
Tickets._stats();
},
_stats:function(){
 $.get(BASE_URL+'api/tickets/stats.php',function(r){
if(!r.success)return;
var d=r.data||{};
 $('#tickets-stats .n').each(function(){
var k=$(this).data('k');
$(this).text(d[k]!==undefined?d[k]:'–');
});
});
},
_form:function(id){
id=id||0;
var esEdita=id>0;
var carga=esEdita?Api.get('api/tickets/detalle.php',{id:id}).catch(function(){return null}):Promise.resolve(null);
carga.then(function(T){
if(esEdita&&!T){Toast.error('No se pudo cargar el ticket');return}
Promise.all([
Api.get('api/catalogos/select.php',{tipo:'personal'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'areas'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'categorias'}).catch(function(){return[]})
]).then(function(R){
var html='<form id="form-ticket" data-id="'+id+'"><div class="row">'+
'<div class="col-md-6 mb-3"><label>Titulo *</label><input class="form-control" id="ft-titulo" maxlength="150" value="'+esc(T?T.titulo:'')+'"></div>'+
'<div class="col-md-3 mb-3"><label>Solicitante *</label><select class="form-select" id="ft-solicitante"'+(esEdita?' disabled':'')+'>'+
R[0].map(function(p){return '<option value="'+p.id+'">'+esc(p.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-3 mb-3"><label>Area *</label><select class="form-select" id="ft-area">'+
R[1].map(function(a){return '<option value="'+a.id+'">'+esc(a.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-3 mb-3"><label>Categoria *</label><select class="form-select" id="ft-categoria"'+(esEdita?' disabled':'')+'>'+
R[2].map(function(c){return '<option value="'+c.id+'">'+esc(c.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-3 mb-3"><label>Subcategoria</label><select class="form-select" id="ft-subcategoria"><option value="">— Opcional —</option></select></div>'+
'<div class="col-md-3 mb-3"><label>Prioridad *</label><select class="form-select" id="ft-prioridad"></select></div>'+
'<div class="col-md-3 mb-3"><label>Impacto</label><select class="form-select" id="ft-impacto">'+
'<option value="individual">Individual</option><option value="area">Area</option><option value="empresa">Empresa</option></select></div>'+
'<div class="col-12 mb-3"><label>Descripcion *</label><textarea class="form-control" id="ft-descripcion" rows="4" placeholder="Describe la incidencia con detalle...">'+esc(T?T.descripcion:'')+'</textarea></div>'+
'<div class="col-12 mb-3"><label>Adjuntos — imágenes (opcional'+(esEdita?', se suman a los existentes':'')+')</label>'+
'<input type="file" class="form-control" id="ft-adjuntos" multiple accept="image/*">'+
'<div class="form-text" style="font-size:11px">Fotos del problema, capturas de pantalla o documentos de soporte.</div></div>'+
'</div><div class="text-end"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button class="btn btn-primary" id="btn-gt"><i class="bi bi-check-lg"></i> '+(esEdita?'Guardar cambios':'Crear')+'</button></div></form>';
Modal.abrir(esEdita?'Editar ticket '+T.codigo:'Nuevo ticket',html,'modal-xl');
 $.get(BASE_URL+'api/catalogos/select.php?tipo=prioridades',function(r){
if(r.success){
 $('#ft-prioridad').html(r.data.map(function(p){return '<option value="'+p.id+'">'+esc(p.texto)+' ('+p.sla_horas+'h)</option>'}).join(''));
if(esEdita)$('#ft-prioridad').val(T.prioridad_id);
}
});
if(esEdita){
 $('#ft-area').val(T.area_afectada_id);
 $('#ft-categoria').val(T.categoria_id);
 $('#ft-impacto').val(T.impacto);
if(T.solicitante_personal_id)$('#ft-solicitante').val(T.solicitante_personal_id);
}
var cargarSub=function(cid,sel){
 $('#ft-subcategoria').html('<option value="">— Opcional —</option>');
if(!cid)return;
 $.get(BASE_URL+'api/tickets/subcategorias.php',{categoria:cid},function(r){
if(r.success&&r.data.length){
 $('#ft-subcategoria').append(r.data.map(function(s){return '<option value="'+s.id+'">'+esc(s.texto)+'</option>'}).join(''));
if(sel)$('#ft-subcategoria').val(sel);
}
});
};
if(esEdita){cargarSub(T.categoria_id,T.subcategoria_id)}
else{
 $('#ft-categoria').on('change',function(){cargarSub($(this).val(),null)});
}
 $('#btn-gt').on('click',function(){Tickets._guardar(id)});
});
});
},
_guardar:function(id){
id=id||0;
var d={id:id,titulo:$('#ft-titulo').val(),descripcion:$('#ft-descripcion').val(),
solicitante_id:$('#ft-solicitante').val()||'',area_id:$('#ft-area').val(),
categoria_id:$('#ft-categoria').val(),subcategoria_id:$('#ft-subcategoria').val()||'',
prioridad_id:$('#ft-prioridad').val(),
impacto:$('#ft-impacto').val(),equipo_id:''};
if(d.titulo.length<5){Toast.warning('Titulo min 5 chars');return}
if(d.descripcion.length<10){Toast.warning('Descripcion min 10 chars');return}
var fd=new FormData();
Object.keys(d).forEach(function(k){fd.append(k,d[k])});
var inp=document.getElementById('ft-adjuntos');
if(inp&&inp.files.length){
for(var i=0;i<inp.files.length;i++)fd.append('adjuntos[]',inp.files[i]);
}
Api.postFiles('api/tickets/guardar.php',fd).then(function(){
Modal.cerrar();Tickets.tabla.draw();Toast.success(id>0?'Ticket actualizado':'Ticket creado');
}).catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
},
/* Reasignacion rapida desde la lista */
_reasignar:function(f){
Api.get('api/catalogos/select.php',{tipo:'tecnicos'}).then(function(tec){
var html='<div class="mb-3"><label class="small text-muted">'+esc(f.codigo)+' — '+esc(f.titulo)+'</label>'+
'<label class="form-label mt-2">Técnico *</label><select class="form-select" id="tr-tecnico">'+
'<option value="">— Seleccione —</option>'+
tec.map(function(u){return '<option value="'+u.id+'"'+(+u.id===+f.tecnico_usuario_id?' selected':'')+'>'+esc(u.texto)+'</option>'}).join('')+
'</select><label class="form-label mt-2">Nivel de atención</label>'+
'<select class="form-select" id="tr-nivel">'+
'<option value="1">N1 — Mesa de ayuda</option><option value="2">N2 — Soporte técnico</option><option value="3">N3 — Especialista</option></select></div>'+
'<div class="text-end"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button class="btn btn-primary" id="btn-tr"><i class="bi bi-person-check"></i> Asignar</button></div>';
Modal.abrir((f.tecnico?'Reasignar':'Asignar')+' — '+f.codigo,html,'modal-md');
 $('#btn-tr').on('click',function(){
var t=$('#tr-tecnico').val();
if(!t){Toast.warning('Selecciona un técnico');return}
Api.post('api/tickets/asignar.php',{id:f.id,tecnico_id:t,nivel:$('#tr-nivel').val()})
.then(function(){Modal.cerrar();Tickets.tabla.draw();Toast.success('Técnico asignado')});
});
});
},
/* Eliminacion con confirmacion y motivo */
_eliminar:function(f){
Swal.fire({
title:'¿Eliminar '+f.codigo+'?',
html:'<b>'+esc(f.titulo)+'</b><br><span class="small text-muted">Se borrará junto con sus comentarios, adjuntos e historial. La acción quedará registrada en la auditoría.</span><br>'+
'<input class="form-control form-control-sm mt-2" id="sw-motivo" placeholder="Motivo (opcional)">',
icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',
confirmButtonColor:'#dc2626',focusCancel:true,
didOpen:function(){$('#sw-motivo').val('')}
}).then(function(r){
if(!r.isConfirmed)return;
Api.post('api/tickets/eliminar.php',{id:f.id,motivo:$('#sw-motivo').val()||''})
.then(function(m){Tickets.tabla.draw();Toast.success(m||'Ticket eliminado')});
});
},
/* ============================================================
   DETALLE INTERACTIVO: timeline completo + seguimiento + acciones
   ============================================================ */
_ver:function(id){
Api.get('api/tickets/detalle.php',{id:id}).then(function(t){
var est=ESTADOS_TK[t.estado]||{t:t.estado,c:'text-bg-light'};
var pri='<span class="badge" style="background:'+(t.prioridad_color||'#666')+';color:#fff">'+esc(t.prioridad)+'</span>';

// ---- cabecera ----
var html='<div class="ticket-det-head mb-3">'+
'<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">'+
'<div><h5 class="mb-1">'+esc(t.codigo)+' — '+esc(t.titulo)+'</h5>'+
'<div class="text-muted small">'+esc(t.solicitante)+' · '+esc(t.area)+' · '+esc(t.categoria)+
(t.subcategoria?' / '+esc(t.subcategoria):'')+'</div></div>'+
'<div class="text-end"><span class="badge '+est.c+' fs-6">'+est.t+'</span> '+pri+
'<div class="small text-muted mt-1">Nivel: '+esc(t.nivel_txt)+(t.tecnico?' · '+esc(t.tecnico):'')+'</div></div></div></div>';

// ---- barra de acciones: terminar / cerrar / reabrir ----
if(t.puede&&(t.puede.finalizar||t.puede.cerrar||t.puede.reabrir)){
html+='<div class="d-flex flex-wrap gap-2 align-items-center mb-3 p-2 border rounded" id="td-barra" style="background:#f0fdf4">';
if(t.puede.finalizar)html+='<button class="btn btn-success" id="td-btn-fin"><i class="bi bi-check2-circle"></i> Finalizar ticket</button>'+
'<span class="small text-muted">Marca el ticket como terminado y deja constancia de la solución, la fecha, quién lo hizo y el tiempo.</span>';
if(t.puede.cerrar)html+='<button class="btn btn-dark" id="td-btn-cerrar"><i class="bi bi-lock"></i> Cerrar definitivamente</button>'+
'<span class="small text-muted">El ticket ya está resuelto; el cierre lo da por conforme.</span>';
if(t.puede.reabrir)html+='<button class="btn btn-outline-warning ms-auto" id="td-btn-reabrir"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button>';
html+='</div>';
}
if(t.sla_vencido_ahora)html+='<div class="alert alert-danger py-1 small mb-2"><i class="bi bi-exclamation-triangle"></i> El plazo (SLA) venció hace <b>'+esc(t.sla_atraso_txt||'')+'</b>. Al finalizar deberá indicar el motivo del retraso.</div>';
// ---- cuando y como se finalizo ----
if(t.finalizacion){
var fz=t.finalizacion;
html+='<div class="border rounded p-2 mb-2" id="td-card-fin" style="background:'+(fz.a_tiempo?'#f0fdf4':'#fef2f2')+'">'+
'<b><i class="bi bi-flag-fill"></i> Finalización</b>'+
'<div class="row g-1 small mt-1">'+
'<div class="col-md-4"><span class="text-muted">Finalizado el:</span> <b>'+esc(fmtFechaTk(fz.fecha))+'</b></div>'+
'<div class="col-md-4"><span class="text-muted">Por:</span> <b>'+esc(fz.por||'—')+'</b></div>'+
'<div class="col-md-4"><span class="text-muted">Cómo se resolvió:</span> <b>'+esc(fz.tipo_txt||'—')+'</b></div>'+
'<div class="col-md-4"><span class="text-muted">Tiempo de trabajo:</span> <b>'+esc(fz.tiempo_txt||'no indicado')+'</b></div>'+
'<div class="col-md-4"><span class="text-muted">Desde la apertura:</span> <b>'+esc(fz.total_txt||'—')+'</b></div>'+
'<div class="col-md-4">'+(fz.a_tiempo?'<span class="badge text-bg-success">Dentro del SLA</span>':'<span class="badge text-bg-danger">Con retraso de '+esc(fz.atraso_txt||'')+'</span>')+'</div>'+
(fz.fecha_cierre?'<div class="col-md-8"><span class="text-muted">Cerrado el:</span> <b>'+esc(fmtFechaTk(fz.fecha_cierre))+'</b> <span class="text-muted">por</span> <b>'+esc(fz.cerrado_por||'—')+'</b></div>':'')+
'</div>'+
(fz.motivo_retraso?'<div class="small mt-1"><span class="text-muted">Motivo del retraso:</span> '+esc(fz.motivo_retraso)+'</div>':'')+
'</div>';
}
// ---- descripcion y solucion ----
html+='<div class="border rounded p-2 mb-2 bg-light"><b>Descripción</b><br><span class="small">'+esc(t.descripcion)+'</span></div>';
if(t.solucion)html+='<div class="border rounded p-2 mb-2" style="background:#f0fdf4"><b><i class="bi bi-check-circle"></i> Solución</b><br><span class="small">'+esc(t.solucion)+'</span></div>';
if(t.motivo_cancelacion)html+='<div class="border rounded p-2 mb-2" style="background:#fef2f2"><b>Cancelado:</b> <span class="small">'+esc(t.motivo_cancelacion)+'</span></div>';

// ---- timeline: historial + comentarios + adjuntos (nada se pierde) ----
var evs=[];
(t.historial||[]).forEach(function(h){evs.push({f:String(h.evento_fecha||''),tipo:'ev',ev:h.evento,det:h.detalle,usr:h.usuario})});
(t.comentarios||[]).forEach(function(c){evs.push({f:String(c.comentario_fecha||''),tipo:'com',msg:c.mensaje,usr:c.autor,int:+c.interno===1})});
evs.sort(function(a,b){return a.f.localeCompare(b.f)});
Tickets._evs=evs;
var usuarios=[];
evs.forEach(function(e){if(e.usr&&usuarios.indexOf(e.usr)<0)usuarios.push(e.usr)});
html+='<div class="row g-2 mb-3"><div class="col-md-7">'+
'<div class="d-flex justify-content-between align-items-center flex-wrap gap-1">'+
'<b class="small"><i class="bi bi-clock-history"></i> Historial completo</b>'+
'<div class="d-flex gap-1">'+
'<select id="tl-f-tipo" class="form-select form-select-sm" style="width:auto;font-size:12px">'+
'<option value="">Todo</option><option value="com">Solo comentarios</option><option value="ev">Solo movimientos</option></select>'+
'<select id="tl-f-usr" class="form-select form-select-sm" style="width:auto;font-size:12px">'+
'<option value="">Todos los usuarios</option>'+
usuarios.map(function(u){return '<option value="'+esc(u)+'">'+esc(u)+'</option>'}).join('')+
'</select></div></div>'+
'<div class="ticket-timeline mt-1" id="tl-lista"></div></div><div class="col-md-5">'+
'<b class="small"><i class="bi bi-paperclip"></i> Adjuntos ('+((t.adjuntos||[]).length)+')</b><div class="mt-1">';
if((t.adjuntos||[]).length){
t.adjuntos.forEach(function(a){
html+='<a class="d-block small border rounded p-1 mb-1 text-decoration-none" href="'+BASE_URL+'api/tickets/descargar.php?id='+a.id+'" target="_blank">'+
'<i class="bi bi-file-earmark"></i> '+esc(a.nombre_original)+' <span class="text-muted">('+ (a.tamano_kb||0) +' KB)</span></a>';
});
}else html+='<div class="text-muted small">Sin adjuntos.</div>';
// fechas clave
html+='<div class="small text-muted mt-2 border-top pt-2">'+
'Creado: '+esc(t.fecha_creacion||'—')+'<br>'+
'1ra respuesta: '+esc(t.fecha_primera_respuesta||'—')+'<br>'+
'Resuelto: '+esc(t.fecha_resolucion||'—')+'<br>'+
'Cierre: '+esc(t.fecha_cierre||'—')+
(t.sla_limite?'<br><span class="'+(new Date(t.sla_limite)<new Date()?'text-danger fw-bold':'')+'">SLA límite: '+esc(t.sla_limite)+'</span>':'')+
'</div></div></div>';

// ---- panel de acciones (hasta 3 columnas) ----
var bloques=[];
// seguimiento
bloques.push('<div class="border rounded p-2"><b class="small"><i class="bi bi-chat-left-text"></i> Agregar seguimiento</b>'+
'<textarea class="form-control form-control-sm mt-1" id="td-comentario" rows="3" maxlength="3000" placeholder="Escribe una observación, avance o respuesta..."></textarea>'+
(t.puede&&t.puede.comentario_interno?'<div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="td-interno"><label class="form-check-label small" for="td-interno">Nota interna (no la ve el solicitante)</label></div>':'')+
'<button class="btn btn-sm btn-primary mt-1 w-100" id="td-btn-com"><i class="bi bi-send"></i> Comentar</button></div>');
// cambio de estado
var transUi=(t.transiciones||[]).filter(function(x){return ['asignado','resuelto','cerrado'].indexOf(x)<0&&!(x==='en_atencion'&&['resuelto','cerrado'].indexOf(t.estado)>=0)});
if(t.puede&&t.puede.cambiar_estado&&transUi.length){
bloques.push('<div class="border rounded p-2"><b class="small"><i class="bi bi-arrow-repeat"></i> Cambiar estado</b><div class="mt-1" id="td-trans">'+
transUi.map(function(x){
var e=ESTADOS_TK[x]||{t:x};return '<button class="btn btn-sm btn-outline-secondary m-0 mb-1 td-btn-est" data-est="'+x+'">'+e.t+'</button>'}).join(' ')+
'</div><div class="form-text" style="font-size:11px">Se abre un formulario con el motivo.</div></div>');
}
// asignacion
if(t.puede&&t.puede.asignar&&['cerrado','cancelado'].indexOf(t.estado)<0){
bloques.push('<div class="border rounded p-2"><b class="small"><i class="bi bi-person-check"></i> '+(t.tecnico?'Reasignar':'Asignar')+' técnico</b>'+
'<select class="form-select form-select-sm mt-1" id="td-tecnico"><option value="">Cargando...</option></select>'+
'<select class="form-select form-select-sm mt-1" id="td-nivel">'+
'<option value="1">N1 — Mesa de ayuda</option><option value="2">N2 — Soporte técnico</option><option value="3">N3 — Especialista</option></select>'+
'<button class="btn btn-sm btn-outline-primary mt-1 w-100" id="td-btn-asig"><i class="bi bi-person-check"></i> Asignar</button></div>');
}
if(bloques.length){
var cols={'1':'12','2':'6','3':'4'}[String(bloques.length)]||'4';
html+='<div class="row g-2 mb-3">'+bloques.map(function(b){return '<div class="col-12 col-md-'+cols+'">'+b+'</div>'}).join('')+'</div>';
}

Modal.abrir('Ticket '+t.codigo,html,'modal-xl');
Tickets._t=t;
Tickets._renderTimeline();
Tickets._conectarAcciones();
});
},
_renderTimeline:function(){
var evs=Tickets._evs||[];
var ft=$('#tl-f-tipo').val()||'';
var fu=$('#tl-f-usr').val()||'';
var vis=evs.filter(function(e){
if(ft&&e.tipo!==ft)return false;
if(fu&&e.usr!==fu)return false;
return true;
});
var h='';
if(!vis.length)h='<div class="text-muted small">Nada que coincida con el filtro.</div>';
vis.forEach(function(e){
if(e.tipo==='ev'){
var m=EV_TK[e.ev]||{i:'bi-dot',t:e.ev};
h+='<div class="tl-item"><span class="tl-ico"><i class="bi '+m.i+'"></i></span>'+
'<div><span class="small fw-semibold">'+m.t+'</span>'+
(e.det?'<span class="small text-muted"> — '+esc(e.det)+'</span>':'')+
'<div class="tl-fecha">'+esc(e.f)+(e.usr?' · '+esc(e.usr):'')+'</div></div></div>';
}else{
h+='<div class="tl-item tl-com'+(e.int?' tl-interno':'')+'"><span class="tl-ico"><i class="bi bi-chat-left-text"></i></span>'+
'<div><span class="small fw-semibold">'+esc(e.usr||'—')+'</span>'+(e.int?' <span class="badge text-bg-dark" style="font-size:10px">INTERNO</span>':'')+
'<div class="small">'+esc(e.msg)+'</div>'+
'<div class="tl-fecha">'+esc(e.f)+'</div></div></div>';
}
});
 $('#tl-lista').html(h);
},
/* ============ FINALIZAR (boton Terminar / Culminar) ============ */
_finalizar:function(t){
var tipos=t.tipos_solucion||{};
var venc=!!t.sla_vencido_ahora;
var html='<form id="form-tkfin" onsubmit="return false">'+
'<div class="alert alert-success py-2 small mb-3"><i class="bi bi-info-circle"></i> Al confirmar, el ticket <b>'+esc(t.codigo)+'</b> queda <b>RESUELTO</b> y se guarda '+
'<b>cuándo</b> terminó, <b>quién</b> lo hizo y <b>cómo</b> se resolvió.'+(t.tecnico?'':' Como no tiene técnico, quedará a su nombre.')+'</div>'+
((t.mantenimientos_abiertos||[]).length?'<div class="alert alert-warning py-2 small mb-3" id="tf-aviso-mt"><i class="bi bi-wrench-adjustable"></i> Este ticket tiene el mantenimiento <b>'+
esc(t.mantenimientos_abiertos[0].codigo)+'</b> abierto: el equipo <b>'+esc(t.mantenimientos_abiertos[0].equipo_codigo)+'</b> seguirá fuera de servicio hasta que se finalice. '+
'Al terminar este ticket se le ofrecerá finalizarlo.</div>':'')+
'<div class="mb-3"><label class="form-label">Solución aplicada *</label>'+
'<textarea id="tf-sol" class="form-control" rows="4" maxlength="2000" placeholder="Ej: Se reinstaló el driver de la impresora y se probó la impresión."></textarea></div>'+
'<div class="row g-2"><div class="col-md-7 mb-3"><label class="form-label">¿Cómo se resolvió? *</label><select id="tf-tipo" class="form-select">'+
'<option value="">— Seleccione —</option>'+Object.keys(tipos).map(function(k){return '<option value="'+k+'">'+esc(tipos[k])+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-5 mb-3"><label class="form-label">Tiempo de trabajo</label><div class="input-group">'+
'<input id="tf-h" type="number" min="0" max="999" class="form-control" placeholder="0"><span class="input-group-text">h</span>'+
'<input id="tf-m" type="number" min="0" max="59" class="form-control" placeholder="0"><span class="input-group-text">min</span></div>'+
'<div class="form-text">Opcional.</div></div></div>'+
'<div class="mb-3"><label class="form-label">Evidencias — imágenes (opcional)</label><input type="file" class="form-control" id="tf-evid" multiple accept="image/*">'+
'<div class="form-text">Fotos o capturas que demuestren que quedó operativo.</div></div>';
if(venc)html+='<div class="mb-3 p-2 border border-danger rounded" style="background:#fef2f2"><div class="small text-danger fw-bold mb-1"><i class="bi bi-exclamation-triangle"></i> SLA vencido hace '+esc(t.sla_atraso_txt||'')+' — indique el motivo del retraso</div>'+
'<textarea id="tf-ret" class="form-control" rows="2" maxlength="250" placeholder="Ej: Se esperó la llegada del repuesto."></textarea></div>';
html+='<div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="tf-cerrar"><label class="form-check-label" for="tf-cerrar">Cerrar definitivamente de una vez (no queda en espera de conformidad)</label></div>'+
'<div class="text-end"><button type="button" class="btn btn-secondary" id="tf-no">Cancelar</button> '+
'<button type="button" class="btn btn-success" id="tf-ok"><i class="bi bi-check2-circle"></i> Finalizar ticket</button></div></form>';
Modal.abrir('Finalizar '+t.codigo+' — '+t.titulo,html,'modal-lg');
 $('#tf-no').on('click',function(){Modal.cerrar()});
 $('#tf-ok').on('click',function(){
var sol=($('#tf-sol').val()||'').trim(),tipo=$('#tf-tipo').val()||'',ret=($('#tf-ret').val()||'').trim();
if(!sol){Toast.warning('Describa la solución aplicada');$('#tf-sol').trigger('focus');return}
if(!tipo){Toast.warning('Indique cómo se resolvió');$('#tf-tipo').trigger('focus');return}
if(venc&&!ret){Toast.warning('Indique el motivo del retraso');$('#tf-ret').trigger('focus');return}
var h=parseInt($('#tf-h').val(),10)||0,mi=parseInt($('#tf-m').val(),10)||0;
var fd=new FormData();
fd.append('id',t.id);fd.append('solucion',sol);fd.append('tipo_solucion',tipo);
fd.append('tiempo_min',(h||mi)?String(h*60+mi):'');
fd.append('motivo_retraso',ret);fd.append('cerrar',$('#tf-cerrar').is(':checked')?'1':'0');
var inp=document.getElementById('tf-evid');
if(inp&&inp.files.length){for(var i=0;i<inp.files.length;i++)fd.append('evidencias[]',inp.files[i])}
var $b=$('#tf-ok').prop('disabled',true);
Api.postFiles('api/tickets/finalizar.php',fd).then(function(r){
Toast.success((r&&r.mensaje)||'Ticket finalizado');
if(Tickets.tabla)Tickets.tabla.draw(false);
Tickets._stats();
Tickets._ver(t.id);
Tickets._avisoMt(r);
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
},
/* ============ el ticket quedo atendido pero su mantenimiento sigue abierto (equipo aun fuera de servicio) ============ */
_avisoMt:function(r){
var mts=(r&&r.mantenimientos_abiertos)||[];
if(!mts.length)return;
var m=mts[0],puede=!!(r&&r.puede_finalizar_mt);
var TXT={en_reparacion_externa:'Reparación externa',en_mantenimiento:'En mantenimiento',en_revision:'En revisión'};
Swal.fire({
title:'El equipo sigue fuera de servicio',
html:'El ticket quedó atendido, pero el mantenimiento <b>'+esc(m.codigo)+'</b> sigue abierto y el equipo <b>'+esc(m.equipo_codigo)+'</b> figura como «'+esc(TXT[m.equipo_estado]||m.equipo_estado)+'».'+
(puede?'<br><br>¿Desea finalizar el mantenimiento ahora para dejar el equipo operativo?':'<br><br>Pida a un técnico con permiso que finalice ese mantenimiento.'),
icon:'warning',showCancelButton:puede,confirmButtonText:puede?'Finalizar mantenimiento':'Entendido',cancelButtonText:'Más tarde',confirmButtonColor:'#16a34a'
}).then(function(s){
if(!s.isConfirmed||!puede)return;
Api.get('api/mantenimiento/detalle.php',{id:m.id}).then(function(mt){Mantenimiento._finalizar(mt)});
});
},
/* ============ CAMBIAR ESTADO: formulario propio, a todo el ancho ============ */
_formEstado:function(t,dest){
var e=ESTADOS_TK[dest]||{t:dest},act=ESTADOS_TK[t.estado]||{t:t.estado};
var reabre=(dest==='en_atencion'&&['resuelto','cerrado'].indexOf(t.estado)>=0);
var obligatorio=(dest==='cancelado'||dest==='pendiente_usuario'||dest==='pendiente_proveedor'||reabre);
var titulo=reabre?'Reabrir':(dest==='cerrado'?'Cerrar definitivamente':'Cambiar estado');
var etiqueta=reabre?'Motivo de la reapertura *':(dest==='cancelado'?'Motivo de la cancelación *':(obligatorio?'Motivo de la espera *':'Observación (opcional)'));
var html='<form id="form-tkest" onsubmit="return false"><div class="mb-3 d-flex align-items-center gap-2 flex-wrap">'+
'<span class="badge '+(act.c||'text-bg-light')+'">'+esc(act.t)+'</span><i class="bi bi-arrow-right"></i><span class="badge '+(e.c||'text-bg-light')+'">'+esc(e.t)+'</span></div>'+
'<div class="mb-3"><label class="form-label" for="tk-motivo">'+etiqueta+'</label>'+
'<textarea id="tk-motivo" class="form-control" rows="3" maxlength="250" placeholder="Escriba aquí el motivo..."></textarea></div>'+
'<div class="text-end"><button type="button" class="btn btn-secondary" id="tk-est-no">Cancelar</button> '+
'<button type="button" class="btn btn-primary" id="tk-est-ok"><i class="bi bi-check-lg"></i> Confirmar</button></div></form>';
Modal.abrir(titulo+' — '+t.codigo,html,'modal-md');
 $('#tk-motivo').trigger('focus');
 $('#tk-est-no').on('click',function(){Tickets._ver(t.id)});
 $('#tk-est-ok').on('click',function(){
var motivo=($('#tk-motivo').val()||'').trim();
if(obligatorio&&motivo===''){Toast.warning('Indique el motivo');$('#tk-motivo').trigger('focus');return}
var $b=$('#tk-est-ok').prop('disabled',true);
Api.post('api/tickets/estado.php',{id:t.id,estado:dest,motivo:motivo,solucion:''})
.then(function(r){
Toast.success(dest==='cerrado'?'Ticket cerrado':(reabre?'Ticket reabierto':'Estado actualizado'));
if(Tickets.tabla)Tickets.tabla.draw(false);
Tickets._stats();
Tickets._ver(t.id);
Tickets._avisoMt(r);
}).catch(function(er){
$b.prop('disabled',false);
if(er&&er.cuerpo&&er.cuerpo.errors)Toast.warning(Object.values(er.cuerpo.errors)[0]);
});
});
},
_conectarAcciones:function(){
var t=Tickets._t,id=t.id;
// filtros del historial
 $('#tl-f-tipo,#tl-f-usr').on('change',function(){Tickets._renderTimeline()});
// cargar tecnicos
if($('#td-tecnico').length){
 $.get(BASE_URL+'api/catalogos/select.php?tipo=tecnicos',function(r){
if(r.success)$('#td-tecnico').html(r.data.map(function(u){return '<option value="'+u.id+'"'+(+u.id===+t.tecnico_usuario_id?' selected':'')+'>'+esc(u.texto)+'</option>'}).join(''));
});
}
// comentar
 $('#td-btn-com').on('click',function(){
var m=$('#td-comentario').val().trim();
if(m.length<2){Toast.warning('Escribe el comentario');return}
Api.post('api/tickets/comentario.php',{id:id,mensaje:m,interno:$('#td-interno').is(':checked')?1:0})
.then(function(){Toast.success('Comentario agregado');Tickets._ver(id);Tickets.tabla.draw(false)});
});
// terminar / cerrar / reabrir y cambio de estado: formularios a todo el ancho (motivo siempre visible)
 $('#td-btn-fin').on('click',function(){Tickets._finalizar(t)});
 $('#td-btn-cerrar').on('click',function(){Tickets._formEstado(t,'cerrado')});
 $('#td-btn-reabrir').on('click',function(){Tickets._formEstado(t,'en_atencion')});
 $('.td-btn-est').on('click',function(){Tickets._formEstado(t,$(this).data('est'))});
// asignar
 $('#td-btn-asig').on('click',function(){
var tec=$('#td-tecnico').val();
if(!tec){Toast.warning('Selecciona un técnico');return}
Api.post('api/tickets/asignar.php',{id:id,tecnico_id:tec,nivel:$('#td-nivel').val()})
.then(function(){Toast.success('Ticket asignado');Tickets._ver(id);Tickets.tabla.draw(false)});
});
}
};
App.registrar('tickets',Tickets);
