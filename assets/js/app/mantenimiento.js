/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var EST_MT={solicitado:'Solicitado',en_evaluacion:'En evaluación',en_proceso:'En proceso',
enviado_proveedor:'Enviado a proveedor',cotizado:'Cotizado',aprobado:'Aprobado',rechazado:'Rechazado',
en_reparacion:'En reparación',recibido_reparado:'Recibido reparado',listo:'Listo',entregado:'Entregado',
devuelto_stock:'Devuelto a stock',cerrado:'Cerrado',cancelado:'Cancelado',no_reparable:'No reparable'};
/* Utilidades/trabajos sugeridos por tipo de equipo */
var UTIL_MT={
'Laptop':['Limpieza de RAM','Cambio de pasta térmica','Limpieza física interna','Limpieza de software','Actualización de drivers'],
'PC de escritorio':['Limpieza de RAM','Cambio de pasta térmica','Limpieza física interna','Limpieza de software','Limpieza de fuente'],
'Impresora':['Cambio de tóner','Limpieza de cabezas','Cambio de rodillo','Limpieza general'],
'Celular':['Limpieza de software','Limpieza de puerto de carga','Cambio de mica'],
'Tablet':['Limpieza de software','Limpieza de puerto de carga'],
'Monitor':['Limpieza general','Ajuste de conectores'],
'Servidor':['Limpieza física','Verificación de discos','Limpieza de ventilación'],
'Scáner':['Limpieza de cristal','Limpieza interna'],
'Escáner':['Limpieza de cristal','Limpieza interna'],
'Router / Switch':['Limpieza de ventilación','Actualización de firmware'],
'Access Point':['Limpieza de ventilación','Actualización de firmware'],
'UPS':['Revisión de baterías','Limpieza interna'],
'Proyector':['Limpieza de filtro','Limpieza de lente'],
'Periférico':['Limpieza general'],
'Otro':['Limpieza general']
};
var Mantenimiento={
tabla:null,
init:function(){
this.tabla=DT.server('#tb-mt','api/mantenimiento/listar.php',
function(){return{f_estado:$('#mtf-estado').val()||'',f_tipo:$('#mtf-tipo').val()||'',f_proveedor:$('#mtf-proveedor').val()||0,f_atrasados:$('#mtf-atrasados').is(':checked')?1:0}},
[
{data:'codigo',render:function(v){return '<strong>'+esc(v)+'</strong>'}},
{data:null,render:function(v,t,f){return '<span class="small"><b>'+esc(f.equipo_codigo||'')+'</b><br><span class="text-muted">'+esc((f.equipo_tipo||'')+' '+f.equipo_marca||'')+'</span></span>'}},
{data:'tipo',render:function(v){return '<span class="badge text-bg-light border">'+esc(v)+'</span>'}},
{data:'estado',render:function(v){
var c={solicitado:'text-bg-secondary',en_evaluacion:'text-bg-info',en_proceso:'text-bg-primary',enviado_proveedor:'text-bg-warning text-dark',cotizado:'text-bg-warning text-dark',aprobado:'text-bg-success',en_reparacion:'text-bg-primary',listo:'text-bg-success',entregado:'text-bg-dark',devuelto_stock:'text-bg-dark',cerrado:'text-bg-dark',cancelado:'text-bg-danger',no_reparable:'text-bg-danger'}[v]||'text-bg-light';
return '<span class="badge '+c+'">'+esc(EST_MT[v]||v)+'</span>'}},
{data:'proveedor',render:function(v){return v?esc(v):'<span class="text-muted small">interno</span>'}},
{data:'tecnico',render:function(v){return v?esc(v):'<span class="text-muted small">—</span>'}},
{data:'fecha_retorno_estimada',render:function(v,t,f){
if(!v)return '—';
var atras=+f.atrasado===1;
return '<span class="small '+(atras?'text-danger fw-bold':'')+'">'+esc(v.slice(0,10))+(atras?' ⚠':'')+'</span>'}},
{data:'costo_total',render:function(v){return v>0?'S/ '+Number(v).toLocaleString():'—'}},
{data:'fecha_inicio',render:function(v){return v?'<span class="small text-muted">'+esc(v.slice(2,16))+'</span>':'—'}},
{data:'sla_limite',className:'nowrap',render:function(v,t,f){
if(f.estado==='cerrado'){
var am=+f.atraso_min||0;
var pc=+f.n_percances>0?' <i class="bi bi-exclamation-triangle text-warning" title="'+f.n_percances+' percance(s) registrado(s)"></i>':'';
if(am>0)return '<span class="badge text-bg-danger" title="'+esc(f.motivo_atraso||'')+'">Atrasado '+esc(Mantenimiento._txtAtraso(am))+'</span>'+pc;
return '<span class="badge text-bg-success">A tiempo</span>'+pc}
if(!v||['cancelado','entregado','devuelto_stock','no_reparable'].indexOf(f.estado)>=0)return '—';
var r=(new Date(String(v).replace(' ','T'))-Date.now())/1000;
if(r<=0)return '<span class="badge text-bg-danger">VENCIDO</span>';
var d=Math.floor(r/86400),h=Math.floor((r%86400)/3600);
return '<span class="badge '+(r<86400?'text-bg-warning text-dark':'text-bg-success')+'">'+(d>0?d+'d ':'')+h+'h</span>'}},
{data:null,orderable:false,className:'text-center nowrap',render:function(v,t,f){
var b='<div class="btn-group btn-group-sm" role="group">'+
'<button class="btn btn-outline-primary btn-ver-mt" title="Ver detalle y seguimiento"><i class="bi bi-eye"></i></button>';
if(+f.puede_finalizar===1)b+='<button class="btn btn-outline-success btn-finalizar-mt" title="Finalizar mantenimiento"><i class="bi bi-check2-circle"></i></button>';
if(+f.puede_estado===1)b+='<button class="btn btn-outline-info btn-estado-mt" title="Cambiar estado"><i class="bi bi-arrow-repeat"></i></button>';
if(+f.puede_editar===1)b+='<button class="btn btn-outline-secondary btn-editar-mt" title="Editar"><i class="bi bi-pencil"></i></button>';
if(+f.puede_eliminar===1)b+='<button class="btn btn-outline-danger btn-eliminar-mt" title="Eliminar"><i class="bi bi-trash"></i></button>';
return b+'</div>';
}}
],{order:[[8,'desc']]});
 $('#btn-filtrar-mt').on('click',function(){Mantenimiento.tabla.draw()});
 $('#mtf-estado,#mtf-tipo,#mtf-proveedor,#mtf-atrasados').on('change',function(){Mantenimiento.tabla.draw()});
 $('#btn-limpiar-mt').on('click',function(){
 $('#mtf-estado,#mtf-tipo,#mtf-proveedor').val('');
 $('#mtf-atrasados').prop('checked',false);
Mantenimiento.tabla.search('').draw();
});
 $('#btn-nuevo-mt').on('click',function(){Mantenimiento._form()});
// (el boton "ver" lo atiende un manejador delegado al final del archivo)
Mantenimiento._motorEstado();
Mantenimiento._stats();
Mantenimiento._panel();
Mantenimiento._abrirTabPendiente();
// recargar panel al mostrar la pestaña
 $(document).off('shown.mtpanel').on('shown.mtpanel','button[data-bs-target="#tab-panel"]',function(){Mantenimiento._panel()});
},
/* ============ PANEL DEL MODULO ============ */
_charts:{},
_panel:function(){
if(!$('#tab-panel').length)return;
Api.get('api/mantenimiento/stats.php').then(function(d){
var ESTC={solicitado:'#64748b',en_evaluacion:'#0891b2',en_proceso:'#2563eb',enviado_proveedor:'#d97706',
cotizado:'#d97706',aprobado:'#16a34a',rechazado:'#b91c1c',en_reparacion:'#2563eb',recibido_reparado:'#0891b2',
listo:'#16a34a',entregado:'#1e293b',devuelto_stock:'#1e293b',cerrado:'#1e293b',cancelado:'#dc2626',no_reparable:'#dc2626'};
var dibujar=function(id,cfg){
var el=document.getElementById(id);
if(!el||typeof Chart==='undefined')return;
try{Chart.getChart(el)?.destroy();new Chart(el,cfg)}catch(e){}
};
dibujar('mt-p-estado',{type:'doughnut',data:{labels:(d.por_estado||[]).map(function(e){return EST_MT[e.estado]||e.estado}),
datasets:[{data:(d.por_estado||[]).map(function(e){return +e.n}),backgroundColor:(d.por_estado||[]).map(function(e){return ESTC[e.estado]||'#94a3b8'})}]},
options:{responsive:true,plugins:{legend:{position:'right',labels:{boxWidth:12,font:{size:10}}},title:{display:true,text:'Por estado'}}}});
dibujar('mt-p-tipo',{type:'bar',data:{labels:(d.por_tipo||[]).map(function(e){return e.tipo}),
datasets:[{label:'Mantenimientos',data:(d.por_tipo||[]).map(function(e){return +e.n}),backgroundColor:'#2563eb'}]},
options:{responsive:true,plugins:{legend:{display:false},title:{display:true,text:'Por tipo'}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
dibujar('mt-p-costos',{type:'line',data:{labels:d.meses||[],datasets:[
{label:'Costo S/',data:d.costos_mes||[],borderColor:'#16a34a',fill:true,tension:.3},
{label:'Cantidad',data:d.cantidades_mes||[],borderColor:'#2563eb',tension:.3,yAxisID:'y2'}]},
options:{responsive:true,plugins:{title:{display:true,text:'Mantenimientos y costos — 6 meses'}},
scales:{y:{beginAtZero:true},y2:{position:'right',beginAtZero:true,grid:{drawOnChartArea:false}}}}});
 $('#mt-p-costomes').text('S/ '+Number(d.costo_mes||0).toLocaleString());
 $('#mt-p-costoyear').text('S/ '+Number(d.costo_anio||0).toLocaleString());
var prox=d.proximos||[];
 $('#mt-p-proximos').html(prox.length?
'<table class="table table-sm table-bordered tabla-mini mb-0" style="font-size:11px"><thead><tr><th>Equipo</th><th>Tipo</th><th>Próxima</th><th>Resp.</th></tr></thead><tbody>'+
prox.map(function(p){var ven=p.proxima_fecha<=new Date().toISOString().slice(0,10);
return '<tr'+(ven?' class="table-danger"':'')+'><td><b>'+esc(p.codigo)+'</b> '+esc(p.marca||'')+'</td><td>'+esc(p.tipo)+'</td>'+
'<td><span class="badge '+(ven?'text-bg-danger':'text-bg-primary')+'">'+esc(p.proxima_fecha)+'</span></td><td>'+esc(p.responsable||'—')+'</td></tr>'}).join('')+'</tbody></table>'
:'<span class="text-muted">Sin programaciones en los próximos 30 días.</span>');
var sla=d.sla_vencidos||[];
 $('#mt-p-sla').html(sla.length?
'<table class="table table-sm table-bordered tabla-mini mb-0" style="font-size:11px"><thead><tr><th>Código</th><th>Equipo</th><th>Estado</th><th>SLA venció</th></tr></thead><tbody>'+
sla.map(function(s){return '<tr class="table-danger"><td><b>'+esc(s.codigo)+'</b></td><td>'+esc(s.equipo)+'</td><td>'+esc(EST_MT[s.estado]||s.estado)+'</td><td>'+esc((s.sla_limite||'').slice(0,16))+'</td></tr>'}).join('')+'</tbody></table>'
:'<span class="text-muted">Nada vencido. ✔</span>');
}).catch(function(){});
},
/* ============ NUEVO PROVEEDOR INLINE ============ */
_formProveedor:function(alVolver){
ProveedorForm.abrir(null,{
alGuardar:function(id){if(alVolver)alVolver(id)},
alCancelar:function(){if(alVolver)alVolver(null);else Modal.cerrar()}
});
},
_stats:function(){
 $.get(BASE_URL+'api/mantenimiento/stats.php',function(r){
if(!r.success)return;
var d=r.data||{};
 $('.mini-stat .n[data-k]').each(function(){
var k=$(this).data('k');
if(d[k]!==undefined)$(this).text(k==='costo_mes'&&+d[k]>0?('S/ '+Number(d[k]).toLocaleString()):d[k]);
});
});
},
/* ============ NUEVO MANTENIMIENTO: personal O proveedor ============ */
_form:function(prefill){
prefill=prefill||{};
Promise.all([
Api.get('api/equipos/select.php',{modo:'activos'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'tecnicos'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'proveedores'}).catch(function(){return[]}),
Api.get('api/mantenimiento/tickets-disponibles.php').catch(function(){return[]})
]).then(function(R){
var TK=R[3]||[];
var html='<form id="form-mt"><div class="row">'+
'<div class="col-md-6 mb-3"><label>Equipo *</label><select class="form-select" id="fm-equipo"><option value="">—</option>'+
R[0].map(function(e){return '<option value="'+e.id+'">'+esc(e.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-3 mb-3"><label>Tipo *</label><select class="form-select" id="fm-tipo">'+
'<option value="correctivo">Correctivo (SLA 5d)</option><option value="preventivo">Preventivo (SLA 10d)</option>'+
'<option value="limpieza">Limpieza (SLA 5d)</option><option value="actualizacion">Actualización (SLA 30d)</option></select></div>'+
'<div class="col-md-3 mb-3"><label>Asignación *</label><select class="form-select" id="fm-destino-tipo">'+
'<option value="interno">Personal interno</option><option value="externo">Proveedor externo</option>'+
'<option value="sin">Sin asignar aún</option></select></div>'+
'<div class="col-md-6 mb-3" id="fm-box-interno" style="display:none"><label>Técnico interno *</label><select class="form-select" id="fm-tecnico">'+
'<option value="">— Seleccione —</option>'+
R[1].map(function(u){return '<option value="'+u.id+'">'+esc(u.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-6 mb-3" id="fm-box-externo" style="display:none"><label>Proveedor *</label><div class="d-flex gap-1">'+
'<select class="form-select" id="fm-proveedor"><option value="">— Seleccione —</option>'+
R[2].map(function(p){return '<option value="'+p.id+'">'+esc(p.texto)+'</option>'}).join('')+'</select>'+
'<button class="btn btn-outline-primary text-nowrap" id="btn-nuevo-prov" title="Registrar proveedor nuevo"><i class="bi bi-plus-lg"></i> Nuevo</button></div></div>'+
'<div class="col-md-6 mb-3" id="fm-box-destino" style="display:none"><label>Destino de envío</label><input class="form-control" id="fm-destino" maxlength="150" placeholder="Distrito / departamento (opcional)"></div>'+
'<div class="col-md-6 mb-3" id="fm-box-retorno" style="display:none"><label>Retorno estimado</label><input type="date" class="form-control" id="fm-retorno"></div>'+
'<div class="col-12 mb-3"><label><i class="bi bi-headset"></i> Ticket de soporte relacionado <span class="text-muted small">(opcional)</span></label>'+
'<select class="form-select" id="fm-ticket"><option value="">— Ninguno —</option></select>'+
'<div class="form-text">Si lo vincula, al finalizar el mantenimiento podrá resolver el ticket de una vez.</div></div>'+
'<div class="col-12 mb-3"><label>Diagnóstico preliminar</label><textarea class="form-control" id="fm-diag" rows="3"></textarea></div>'+
'</div><div class="text-end"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button class="btn btn-primary" id="btn-gmt"><i class="bi bi-check-lg"></i> Registrar</button></div></form>';
Modal.abrir('Registrar mantenimiento',html,'modal-lg');
// restaurar valores si venimos de registrar un proveedor
if(prefill.equipo_id)$('#fm-equipo').val(prefill.equipo_id);
if(prefill.tipo)$('#fm-tipo').val(prefill.tipo);
if(prefill.tecnico_id)$('#fm-tecnico').val(prefill.tecnico_id);
if(prefill.destino)$('#fm-destino').val(prefill.destino);
if(prefill.retorno)$('#fm-retorno').val(prefill.retorno);
if(prefill.diag)$('#fm-diag').val(prefill.diag);
// tickets: solo los del equipo elegido; al elegir un ticket se elige su equipo
var pintaTk=function(){
var eq=+$('#fm-equipo').val()||0,sel=$('#fm-ticket').val()||prefill.ticket_id||'';
var lista=TK.filter(function(x){return !eq||+x.equipo_id===eq});
 $('#fm-ticket').html('<option value="">— Ninguno —</option>'+lista.map(function(x){
return '<option value="'+x.id+'">'+esc(x.codigo)+' — '+esc(x.titulo||'')+' ('+esc(x.equipo_codigo||'')+')</option>'}).join('')).val(sel);
};
pintaTk();
 $('#fm-equipo').on('change',pintaTk);
 $('#fm-ticket').on('change',function(){
var x=TK.filter(function(k){return String(k.id)===String($('#fm-ticket').val())})[0];
if(x&&x.equipo_id){ $('#fm-equipo').val(x.equipo_id)}
});
var cambia=function(){
var v=$('#fm-destino-tipo').val();
 $('#fm-box-interno').toggle(v==='interno');
 $('#fm-box-externo').toggle(v==='externo');
 $('#fm-box-destino').toggle(v==='externo');
 $('#fm-box-retorno').toggle(v==='externo');
};
if(prefill.destino_tipo)$('#fm-destino-tipo').val(prefill.destino_tipo);
cambia();
if(prefill.proveedor_id)$('#fm-proveedor').val(prefill.proveedor_id);
 $('#fm-destino-tipo').on('change',cambia);
// registrar proveedor sin perder el formulario
 $('#btn-nuevo-prov').on('click',function(){
var captura={equipo_id:$('#fm-equipo').val(),tipo:$('#fm-tipo').val(),destino_tipo:$('#fm-destino-tipo').val(),
tecnico_id:$('#fm-tecnico').val(),proveedor_id:$('#fm-proveedor').val(),destino:$('#fm-destino').val(),diag:$('#fm-diag').val(),
ticket_id:$('#fm-ticket').val(),retorno:$('#fm-retorno').val()};
Mantenimiento._formProveedor(function(nuevoId){
captura.proveedor_id=nuevoId;
Mantenimiento._form(captura);
});
});
 $('#btn-gmt').on('click',function(){
var d={equipo_id:$('#fm-equipo').val(),tipo:$('#fm-tipo').val(),
ticket_id:$('#fm-ticket').val()||'',destino_envio:$('#fm-destino').val()||'',diagnostico:$('#fm-diag').val()};
var v=$('#fm-destino-tipo').val();
if(v==='interno')d.tecnico_id=$('#fm-tecnico').val()||'';
if(v==='externo'){d.proveedor_id=$('#fm-proveedor').val()||'';d.fecha_retorno=$('#fm-retorno').val()||''}
if(!d.equipo_id){Toast.warning('Selecciona el equipo');return}
if(v==='interno'&&!d.tecnico_id){Toast.warning('Selecciona el técnico interno');return}
if(v==='externo'&&!d.proveedor_id){Toast.warning('Selecciona el proveedor');return}
Api.post('api/mantenimiento/guardar.php',d)
.then(function(r){Modal.cerrar();Mantenimiento.tabla.draw();Toast.success(r.message||'Registrado')})
.catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
});
});
},
/* ============ DETALLE COMPLETO: seguimiento + utilidades + evidencias ============ */
_ver:function(id){
Api.get('api/mantenimiento/detalle.php',{id:id}).then(function(m){
Mantenimiento._t=m;
var html='<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">'+
'<div><h5 class="mb-1">'+esc(m.codigo)+' — '+esc(m.equipo_codigo||'')+'</h5>'+
'<div class="text-muted small">'+esc(m.equipo_tipo||'')+' '+esc(m.equipo_marca||'')+' '+esc(m.equipo_modelo||'')+
(m.ticket_codigo?' · <i class="bi bi-headset"></i> ticket '+esc(m.ticket_codigo)+(m.ticket_estado?' ('+esc(m.ticket_estado.replace(/_/g,' '))+')':''):'')+'</div></div>'+
'<div class="text-end"><span class="badge text-bg-primary">'+esc(EST_MT[m.estado]||m.estado)+'</span>'+
(m.plazo&&!m.terminal&&!['entregado','devuelto_stock'].includes(m.estado)?
'<div class="small mt-1 '+(m.plazo.vencido_ahora?'text-danger fw-bold':'text-muted')+'">'+(m.plazo.origen==='retorno'?'Retorno prometido: ':'SLA: ')+esc(m.plazo.limite.slice(0,16))+
(m.plazo.vencido_ahora?' · atrasado '+esc(m.plazo.atraso_txt||''):'')+'</div>':'')+
'</div></div>';
// ---- preventivo programado: que pasa al terminar ----
if(m.programado&&!m.terminal){
html+='<div class="alert alert-info py-2 small mb-2" id="mt-aviso-prog"><i class="bi bi-calendar-check"></i> '+
(m.programado.vinculado?'Este mantenimiento es un <b>preventivo programado</b> ('+esc(m.programado.frecuencia)+').'
:'Coincide con la programación <b>'+esc(m.programado.frecuencia)+'</b> de este equipo.')+
' Al finalizarlo, la <b>próxima vez se programará sola</b> para el <b>'+esc(m.programado.proxima_si_finaliza||'')+'</b>.</div>';
}
// ---- camino para finalizar + acciones ----
var RUTA_EXT=['solicitado','en_evaluacion','enviado_proveedor','cotizado','aprobado','en_reparacion','recibido_reparado','listo','entregado','cerrado'];
var RUTA_INT=['solicitado','en_evaluacion','en_proceso','listo','entregado','cerrado'];
var ext=!!m.proveedor_id||['enviado_proveedor','cotizado','aprobado','en_reparacion','recibido_reparado'].indexOf(m.estado)>=0;
var ruta=ext?RUTA_EXT:RUTA_INT;
if(!m.terminal){
var idx=ruta.indexOf(m.estado);
if(m.estado==='devuelto_stock')idx=ruta.indexOf('entregado');
if(idx<0)idx=0;
var fh='<div class="border rounded p-2 mb-2" style="background:#f8fafc">'+
'<span class="small fw-semibold"><i class="bi bi-signpost-2"></i> Camino hasta finalizar:</span> ';
ruta.forEach(function(p,i){
var hecho=i<idx,actual=i===idx;
fh+='<span class="small '+(hecho?'text-success':(actual?'':'text-muted'))+'">'+(i>0?' <span class="text-muted">→</span> ':'')+
(hecho?'<i class="bi bi-check-lg"></i> ':'')+
(actual?'<span class="badge text-bg-primary">'+esc(EST_MT[p]||p)+'</span>':esc(EST_MT[p]||p))+'</span>';
});
fh+='</div>';
html+=fh;
if(m.ticket_atendido_pendiente){
var EQ_TXT={en_reparacion_externa:'Reparación externa',en_mantenimiento:'En mantenimiento',en_revision:'En revisión'};
html+='<div class="alert alert-danger py-2 small mb-2" id="mt-aviso-tk"><i class="bi bi-exclamation-triangle"></i> El ticket <b>'+esc(m.ticket_codigo||'')+'</b> ya fue '+
(m.ticket_estado==='cerrado'?'cerrado':'resuelto')+', pero este mantenimiento sigue abierto y el equipo <b>'+esc(m.equipo_codigo||'')+'</b> figura como «'+esc(EQ_TXT[m.equipo_estado]||m.equipo_estado||'')+'».'+
(m.finalizable?' Finalícelo para dejar el equipo operativo.':' Cambie su estado o cancélelo si ya no corresponde.')+'</div>';
}
if(m.puede&&m.puede.cambiar_estado){
var cierre=(m.estado==='entregado'||m.estado==='devuelto_stock');
html+='<div class="d-flex flex-wrap gap-2 align-items-center mb-2 p-2 border rounded" style="background:#f0fdf4">';
if(m.finalizable){
html+='<button class="btn btn-success" id="mt-btn-fin"><i class="bi bi-check2-circle"></i> '+(cierre?'Cerrar mantenimiento':'Finalizar mantenimiento')+'</button>'+
'<span class="small text-muted">'+(cierre?'El equipo ya salió del taller; se cierra el registro.':'Termina el trabajo y devuelve el equipo a <b>'+esc(m.equipo_vuelve_a||'stock')+'</b>.')+
(m.sin_cotizar?' <b>Se finaliza sin completar la cotización</b> porque el ticket ya está atendido.':'')+'</span>';
}else{
html+='<span class="small text-muted"><i class="bi bi-info-circle"></i> Este paso depende de la cotización/aprobación del costo: use «Cambiar estado». Podrá finalizar cuando el trabajo esté en reparación'+(Mantenimiento._conProveedor(m)?', o anule el envío si lo atendió Sistemas':'')+'.</span>'+
(Mantenimiento._conProveedor(m)?'<button class="btn btn-success btn-sm" id="mt-btn-sistemas"><i class="bi bi-arrow-counterclockwise"></i> Atendido por Sistemas</button>':'');
}
html+='<button class="btn btn-outline-secondary btn-sm ms-auto" id="mt-btn-est"><i class="bi bi-arrow-repeat"></i> Cambiar estado</button></div>';
}
}
// asignacion
var asig=m.proveedor?('<i class="bi bi-building"></i> Externo: <b>'+esc(m.proveedor)+'</b>'+(m.destino_envio?' · destino: '+esc(m.destino_envio):''))
:(m.tecnico?('<i class="bi bi-person"></i> Interno: <b>'+esc(m.tecnico)+'</b>'):'<span class="text-muted">Sin asignar</span>');
html+='<div class="border rounded p-2 mb-2 bg-light small">'+asig+' · Iniciado: '+esc((m.fecha_inicio||'').slice(0,16))+'</div>';
// diagnostico y solucion
if(m.diagnostico)html+='<div class="border rounded p-2 mb-2"><b>Diagnóstico</b><br><span class="small">'+esc(m.diagnostico)+'</span></div>';
if(m.solucion)html+='<div class="border rounded p-2 mb-2" style="background:#f0fdf4"><b><i class="bi bi-check-circle"></i> Solución</b><br><span class="small">'+esc(m.solucion)+'</span></div>';
// ---- como y cuando se finalizo ----
if(m.finalizacion){
var fz=m.finalizacion;
html+='<div class="border rounded p-2 mb-2" id="mt-card-fin" style="background:'+(fz.a_tiempo?'#f0fdf4':'#fef2f2')+'">'+
'<b><i class="bi bi-flag-fill"></i> Finalización</b>'+
'<div class="row g-1 small mt-1">'+
'<div class="col-md-4"><span class="text-muted">Finalizado:</span> <b>'+esc((fz.fecha||'').slice(0,16))+'</b></div>'+
'<div class="col-md-4"><span class="text-muted">Por:</span> <b>'+esc(fz.por||'—')+'</b></div>'+
'<div class="col-md-4"><span class="text-muted">Resultado:</span> <b>'+esc(fz.resultado_txt||'—')+'</b></div>'+
'<div class="col-md-4"><span class="text-muted">Plazo ('+(fz.plazo_origen==='retorno'?'retorno del proveedor':'SLA')+'):</span> '+esc((fz.plazo_limite||'sin plazo').slice(0,16))+'</div>'+
'<div class="col-md-4">'+(fz.a_tiempo?'<span class="badge text-bg-success">Terminó a tiempo</span>':'<span class="badge text-bg-danger">Terminó con atraso de '+esc(fz.atraso_txt||'')+'</span>')+'</div>'+
'<div class="col-md-4"><span class="text-muted">Percances:</span> <b>'+(+fz.n_percances||0)+'</b></div>'+
'</div>'+
(fz.motivo_atraso?'<div class="small mt-1"><span class="text-muted">Motivo del atraso:</span> '+esc(fz.motivo_atraso)+'</div>':'')+
(m.programado?'<div class="small mt-1" id="mt-prox-prog"><i class="bi bi-calendar-event"></i> <span class="text-muted">Próximo mantenimiento programado ('+esc(m.programado.frecuencia)+'):</span> <b>'+
(m.programado.activo?esc(m.programado.proxima_fecha):'pausado')+'</b></div>':'')+
'</div>';
}
// ---- percances ----
html+='<div class="border rounded p-2 mb-2" id="mt-card-percances"><div class="d-flex justify-content-between align-items-center">'+
'<b class="small"><i class="bi bi-exclamation-triangle"></i> Percances / incidencias</b>'+
(m.puede&&m.puede.percance?'<button class="btn btn-sm btn-outline-warning" id="mt-btn-percance"><i class="bi bi-plus-lg"></i> Registrar percance</button>':'')+'</div>';
if((m.percances||[]).length){
m.percances.forEach(function(p){
html+='<div class="small border-top pt-1 mt-1"><span class="badge text-bg-warning text-dark">'+esc(p.tipo_txt)+'</span> '+esc(p.detalle)+
' <span class="text-muted">· '+esc((p.created_at||'').slice(0,16))+(p.usuario?' · '+esc(p.usuario):'')+'</span></div>';
});
}else html+='<div class="text-muted small mt-1">Sin percances registrados.</div>';
html+='</div>';
// utilidades / repuestos
html+='<div class="row g-2 mb-3"><div class="col-md-6"><div class="border rounded p-2">'+
'<b class="small"><i class="bi bi-tools"></i> Utilidades y repuestos utilizados</b><div id="mt-rep-lista" class="mt-1">';
var tot=0;
(m.repuestos||[]).forEach(function(r){
tot+=+r.subtotal;
html+='<div class="d-flex justify-content-between border-bottom py-1 small"><span>'+esc(r.descripcion)+' × '+r.cantidad+
(r.proveedor?' <span class="text-muted">('+esc(r.proveedor)+')</span>':'')+'</span>'+
'<span class="text-nowrap">S/ '+Number(r.subtotal).toLocaleString()+
(m.puede&&m.puede.repuestos?' <button class="btn btn-sm btn-link text-danger p-0 btn-mt-delrep" data-id="'+r.id+'" title="Quitar"><i class="bi bi-x"></i></button>':'')+
'</span></div>';
});
if(!(m.repuestos||[]).length)html+='<div class="text-muted small">Sin utilidades registradas.</div>';
html+='<div class="d-flex justify-content-between small fw-semibold border-top pt-1 mt-1"><span>Total repuestos/utilidades</span><span>S/ '+tot.toLocaleString()+'</span></div>';
if(m.puede&&m.puede.repuestos){
// sugerencias por tipo de equipo
var sugs=UTIL_MT[m.equipo_tipo]||UTIL_MT['Otro'];
html+='<div class="mt-2"><label class="small">Agregar utilidad / repuesto</label>'+
'<div class="mb-1">'+sugs.map(function(s){return '<button class="btn btn-sm btn-outline-secondary me-1 mb-1 btn-mt-util">'+esc(s)+'</button>'}).join('')+'</div>'+
'<div class="d-flex gap-1"><input class="form-control form-control-sm" id="mt-rep-desc" placeholder="Descripción" maxlength="150">'+
'<input class="form-control form-control-sm" id="mt-rep-cant" type="number" min="0.5" step="0.5" value="1" style="max-width:80px">'+
'<input class="form-control form-control-sm" id="mt-rep-costo" type="number" min="0" step="0.1" value="0" style="max-width:110px" placeholder="S/">'+
'<button class="btn btn-sm btn-primary" id="mt-rep-add"><i class="bi bi-plus"></i></button></div></div>';
}
html+='</div></div>';
// evidencias
html+='<div class="col-md-6"><div class="border rounded p-2">'+
'<b class="small"><i class="bi bi-camera"></i> Evidencias (antes / después)</b><div class="mt-1" id="mt-ev-lista">';
(m.evidencias||[]).forEach(function(e){
html+='<a class="d-block small border rounded p-1 mb-1 text-decoration-none" href="'+BASE_URL+'api/mantenimiento/ver-archivo.php?id='+e.id+'" target="_blank">'+
'<i class="bi bi-file-image"></i> ['+esc(e.tipo)+'] '+esc(e.descripcion||e.archivo)+'</a>';
});
if(!(m.evidencias||[]).length)html+='<div class="text-muted small">Sin evidencias.</div>';
if(m.puede&&m.puede.repuestos){
html+='<div class="mt-2 d-flex gap-1 align-items-end"><div><label class="small">Tipo</label>'+
'<select class="form-select form-select-sm" id="mt-ev-tipo"><option value="antes">Antes</option><option value="despues">Después</option><option value="otro">Otro</option></select></div>'+
'<div class="flex-grow-1"><label class="small">Descripción</label><input class="form-control form-control-sm" id="mt-ev-desc" maxlength="200"></div>'+
'<input type="file" class="form-control form-control-sm" id="mt-ev-file" accept="image/*" style="max-width:210px">'+
'<button class="btn btn-sm btn-primary" id="mt-ev-add"><i class="bi bi-upload"></i></button></div>';
}
html+='</div></div></div>';
// timeline + transiciones
html+='<div class="row g-2"><div class="col-md-7"><b class="small"><i class="bi bi-clock-history"></i> Seguimiento</b><div class="ticket-timeline mt-1" id="mt-timeline">';
var evs=[];
(m.seguimiento||[]).forEach(function(h){
evs.push({f:String(h.created_at||''),txt:esc(String(h.detalle||''))});
});
if(!evs.length)(m.historial||[]).forEach(function(h){
evs.push({f:String(h.created_at||''),txt:(h.datos_nuevos?esc(String(h.datos_nuevos).slice(0,120)):'Registro de auditoría')});
});
if(!(evs.length))html+='<div class="text-muted small">Sin movimientos.</div>';
evs.forEach(function(e){
html+='<div class="tl-item"><span class="tl-ico"><i class="bi bi-dot"></i></span><div><span class="small">'+e.txt+'</span><div class="tl-fecha">'+esc(e.f)+'</div></div></div>';
});
html+='</div></div><div class="col-md-5"><b class="small">Costos</b><div class="small mt-1">'+
'Mano de obra: S/ '+Number(m.costo_mano_obra||0).toLocaleString()+'<br>Repuestos: S/ '+Number(m.costo_repuestos||0).toLocaleString()+
'<br><b>Total: S/ '+Number(m.costo_total||0).toLocaleString()+'</b></div>';
if(m.puede&&m.puede.editar&&!['cerrado','cancelado','no_reparable'].includes(m.estado)){
html+='<button class="btn btn-sm btn-outline-secondary mt-2 w-100" id="mt-btn-edit"><i class="bi bi-pencil"></i> Editar diagnóstico/solución</button>';
}
html+='</div></div>';

Modal.abrir('MT '+m.codigo,html,'modal-xl');
Mantenimiento._conectar();
});
},
_conectar:function(){
var m=Mantenimiento._t,id=m.id;
// utilidades rapidas precargan descripcion
 $(document).off('click','.btn-mt-util').on('click','.btn-mt-util',function(){
 $('#mt-rep-desc').val($(this).text());
 $('#mt-rep-cant').focus();
});
 $('#mt-rep-add').on('click',function(){
var desc=$('#mt-rep-desc').val().trim();
if(!desc){Toast.warning('Escribe la utilidad o repuesto');return}
Api.post('api/mantenimiento/repuesto.php',{accion:'agregar',mantenimiento_id:id,
descripcion:desc,cantidad:$('#mt-rep-cant').val()||1,costo_unitario:$('#mt-rep-costo').val()||0})
.then(function(){Toast.success('Agregado');Mantenimiento._ver(id);Mantenimiento.tabla.draw(false)});
});
 $(document).off('click','.btn-mt-delrep').on('click','.btn-mt-delrep',function(){
Api.post('api/mantenimiento/repuesto.php',{accion:'quitar',mantenimiento_id:id,repuesto_id:$(this).data('id')})
.then(function(){Toast.success('Quitado');Mantenimiento._ver(id)});
});
 // evidencias (FormData)
 $('#mt-ev-add').on('click',function(){
var f=document.getElementById('mt-ev-file');
if(!f||!f.files.length){Toast.warning('Selecciona la imagen');return}
var fd=new FormData();
fd.append('mantenimiento_id',id);fd.append('tipo',$('#mt-ev-tipo').val());
fd.append('descripcion',$('#mt-ev-desc').val()||'');
fd.append('archivo',f.files[0]);
Api.postFiles('api/mantenimiento/evidencia.php',fd)
.then(function(){Toast.success('Evidencia guardada');Mantenimiento._ver(id)});
});
 // editar
 $('#mt-btn-edit').on('click',function(){Mantenimiento._formEdit(m)});
 $('#mt-btn-fin').on('click',function(){Mantenimiento._finalizar(m)});
 $('#mt-btn-sistemas').on('click',function(){Mantenimiento._formEstado(m.id,'en_proceso',{})});
 $('#mt-btn-percance').on('click',function(){Mantenimiento._percance(m)});
 $('#mt-btn-est').on('click',function(){Mantenimiento._estado(m.id,m.estado)});
},
_txtAtraso:function(min){
min=+min||0;
if(min<60)return min+' min';
if(min<1440)return Math.floor(min/60)+' h'+(min%60?' '+(min%60)+' min':'');
var d=Math.floor(min/1440),h=Math.floor((min%1440)/60);return d+' d'+(h?' '+h+' h':'');
},
/* ============ REGISTRAR PERCANCE ============ */
_percance:function(m){
var tipos=m.percance_tipos||{};
var html='<form id="form-mtpc"><div class="mb-2"><label class="form-label">¿Qué ocurrió? *</label><select id="pc-tipo" class="form-select">'+
'<option value="">— Seleccione —</option>'+Object.keys(tipos).map(function(k){return '<option value="'+k+'">'+esc(tipos[k])+'</option>'}).join('')+'</select></div>'+
'<div class="mb-3"><label class="form-label">Detalle *</label><textarea id="pc-det" class="form-control" rows="3" maxlength="250" placeholder="Ej: El repuesto llegó dañado; se pidió otro al proveedor."></textarea></div>'+
'<div class="text-end"><button type="button" class="btn btn-secondary" id="pc-no">Volver</button> '+
'<button type="button" class="btn btn-warning" id="pc-ok"><i class="bi bi-save"></i> Guardar percance</button></div></form>';
Modal.abrir('Percance — '+m.codigo,html,'modal-md');
 $('#pc-no').on('click',function(){Mantenimiento._ver(m.id)});
 $('#pc-ok').on('click',function(){
var tp=$('#pc-tipo').val(),dt=($('#pc-det').val()||'').trim();
if(!tp){Toast.warning('Seleccione el tipo de percance');return}
if(!dt){Toast.warning('Describa brevemente el percance');$('#pc-det').trigger('focus');return}
var $b=$('#pc-ok').prop('disabled',true);
Api.post('api/mantenimiento/percance.php',{id:m.id,tipo:tp,detalle:dt}).then(function(r){
Toast.success((r&&r.mensaje)||'Percance registrado');
Mantenimiento._ver(m.id);
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
},
/* ============ FINALIZAR (un solo paso) ============ */
_finalizar:function(m){
var cierre=(m.estado==='entregado'||m.estado==='devuelto_stock');
var tkOk=m.ticket_id&&m.puede&&m.puede.resolver_ticket&&['en_atencion','pendiente_usuario','pendiente_proveedor'].indexOf(m.ticket_estado)>=0;
var rep=+m.costo_repuestos||0;
var html='<form id="form-mtfin">'+
'<div class="alert alert-success py-2 small mb-3"><i class="bi bi-info-circle"></i> Al confirmar, el mantenimiento queda <b>CERRADO</b> y el equipo <b>'+esc(m.equipo_codigo||'')+'</b> '+
(cierre?'ya salió del taller: solo se cierra el registro.':'vuelve a <b>'+esc(m.equipo_vuelve_a||'stock')+'</b>.')+'</div>'+
(m.sin_cotizar?'<div class="alert alert-warning py-2 small mb-3" id="mf-aviso-sincoti"><i class="bi bi-exclamation-triangle"></i> El ticket <b>'+esc(m.ticket_codigo||'')+'</b> ya está atendido: se finaliza <b>sin completar la cotización ni la aprobación del costo</b>. Registre el costo real abajo si lo conoce.</div>':'')+
(m.programado?'<div class="alert alert-info py-2 small mb-3" id="mf-aviso-prog"><i class="bi bi-calendar-check"></i> Es un preventivo <b>'+esc(m.programado.frecuencia)+'</b>: al confirmar, el próximo se programa solo para el <b>'+esc(m.programado.proxima_si_finaliza||'')+'</b>.</div>':'')+
'<div class="mb-3"><label class="form-label d-block">¿Cómo terminó? *</label>'+
'<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="mf-res" id="mf-res1" value="completado" checked><label class="form-check-label" for="mf-res1">Completado</label></div>'+
'<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="mf-res" id="mf-res2" value="parcial"><label class="form-check-label" for="mf-res2">Completado con observaciones</label></div></div>'+
'<div class="mb-3"><label class="form-label">Trabajo realizado / solución *</label>'+
'<textarea id="mf-sol" class="form-control" rows="4" maxlength="2000" placeholder="Ej: Se limpió el ventilador, se cambió la pasta térmica y se reinstaló el sistema.">'+esc(m.solucion||'')+'</textarea></div>'+
'<div class="row g-2"><div class="col-md-5 mb-3"><label class="form-label">Mano de obra (S/)</label>'+
'<input id="mf-mano" type="number" min="0" step="0.1" class="form-control" value="'+(+m.costo_mano_obra||0)+'">'+
'<div class="form-text">Repuestos ya registrados: S/ '+rep.toLocaleString()+'</div></div>'+
'<div class="col-md-7 mb-3"><label class="form-label">Observaciones (opcional)</label><input id="mf-obs" class="form-control" maxlength="500"></div></div>';
// ---- percance y atraso ----
var tiposP=m.percance_tipos||{},pcs=m.percances||[],venc=!!(m.plazo&&m.plazo.vencido_ahora);
html+='<div class="border rounded p-2 mb-3" id="mf-box-pc"><div class="form-check"><input class="form-check-input" type="checkbox" id="mf-pc"><label class="form-check-label" for="mf-pc">¿Hubo un percance durante el trabajo?'+
(pcs.length?' <span class="text-muted">(ya hay '+pcs.length+' registrado'+(pcs.length>1?'s':'')+')</span>':'')+'</label></div>'+
'<div id="mf-pc-form" class="mt-2" style="display:none"><div class="row g-2"><div class="col-md-5"><select id="mf-pc-tipo" class="form-select form-select-sm"><option value="">— Tipo de percance —</option>'+
Object.keys(tiposP).map(function(k){return '<option value="'+k+'">'+esc(tiposP[k])+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-7"><input id="mf-pc-det" class="form-control form-control-sm" maxlength="250" placeholder="Qué pasó"></div></div></div></div>';
if(m.plazo){
html+='<div class="mb-3 p-2 border rounded '+(venc?'border-danger':'')+'" id="mf-box-atraso" style="'+(venc?'background:#fef2f2':'')+'">'+
'<div class="small '+(venc?'text-danger fw-bold':'text-muted')+'"><i class="bi bi-hourglass-split"></i> '+
(m.plazo.origen==='retorno'?'Retorno prometido por el proveedor: ':'Plazo (SLA): ')+esc(m.plazo.limite.slice(0,16))+
(venc?' — ATRASADO '+esc(m.plazo.atraso_txt||'')+'. Indique el motivo.':' — va a tiempo.')+'</div>'+
(venc?'<label class="form-label mt-1 mb-1">Motivo del atraso *</label>'+
'<textarea id="mf-atraso" class="form-control" rows="2" maxlength="250" placeholder="Ej: El proveedor demoró en entregar el repuesto.">'+
esc(pcs.map(function(p){return p.tipo_txt+': '+p.detalle}).join(' | ').slice(0,250))+'</textarea>':'')+'</div>';
}
if(tkOk){
html+='<div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="mf-tk" checked>'+
'<label class="form-check-label" for="mf-tk">Marcar como <b>RESUELTO</b> el ticket de soporte <b>'+esc(m.ticket_codigo||'')+'</b></label></div>';
}else if(m.ticket_id){
html+='<div class="small text-muted mb-3"><i class="bi bi-headset"></i> Ticket '+esc(m.ticket_codigo||'')+' ('+esc(String(m.ticket_estado||'').replace(/_/g,' '))+'): '+
(m.puede&&m.puede.resolver_ticket?'se resuelve desde Tickets.':'no tiene permiso para resolver tickets.')+'</div>';
}
html+='<div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button type="button" class="btn btn-success" id="mf-ok"><i class="bi bi-check2-circle"></i> '+(cierre?'Cerrar':'Finalizar')+'</button></div></form>';
Modal.abrir((cierre?'Cerrar ':'Finalizar ')+m.codigo,html,'modal-lg');
 $('#mf-pc').on('change',function(){$('#mf-pc-form').toggle(this.checked)});
 $('#mf-ok').on('click',function(){
var sol=($('#mf-sol').val()||'').trim();
if(!sol){Toast.warning('Describa el trabajo realizado');$('#mf-sol').trigger('focus');return}
var pcOn=$('#mf-pc').is(':checked'),pcT=$('#mf-pc-tipo').val()||'',pcD=($('#mf-pc-det').val()||'').trim();
if(pcOn&&(!pcT||!pcD)){Toast.warning('Indique el tipo y el detalle del percance');return}
var atr=($('#mf-atraso').val()||'').trim();
if(venc&&!atr&&pcOn)atr=(tiposP[pcT]||'Percance')+': '+pcD;
if(venc&&!atr){Toast.warning('Indique el motivo del atraso');$('#mf-atraso').trigger('focus');return}
var $b=$('#mf-ok').prop('disabled',true);
var dat={id:m.id,solucion:sol,costo_mano_obra:$('#mf-mano').val()||'0',
observaciones:$('#mf-obs').val()||'',resolver_ticket:$('#mf-tk').is(':checked')?'1':'0',
resultado:$('input[name=mf-res]:checked').val()||'completado',motivo_atraso:atr};
if(pcOn){dat.percance_tipo=pcT;dat.percance_detalle=pcD}
Api.post('api/mantenimiento/finalizar.php',dat)
.then(function(r){
Modal.cerrar();
Toast.success((r&&r.mensaje)||'Mantenimiento finalizado');
if(Mantenimiento.tabla)Mantenimiento.tabla.draw(false);
Mantenimiento._stats();Mantenimiento._panel();
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
},
/* ============ CAMBIO DE ESTADO ============ */
 _estado:function(id,estadoActual){
Api.get('api/mantenimiento/detalle.php',{id:id}).then(function(m){
Mantenimiento._m=m;
var trans=m.transiciones||[];
var html='<div class="mb-2"><span class="badge text-bg-primary">'+esc(EST_MT[m.estado]||m.estado)+'</span> <span class="small text-muted">'+esc(m.codigo)+'</span></div>';
if(!trans.length)html+='<div class="text-muted small">Estado final, sin transiciones.</div>';
else{
if(m.finalizable&&m.puede&&m.puede.cambiar_estado)html+='<div class="alert alert-success py-2 small d-flex align-items-center gap-2 flex-wrap"><span>¿Ya terminó el trabajo?</span><button type="button" class="btn btn-sm btn-success" id="mt-est-fin"><i class="bi bi-check2-circle"></i> Finalizar ahora</button></div>';
html+=trans.map(function(x){return Mantenimiento._btnEstado(m,x,'')}).join('');
html+='<div id="mt-est-form" class="mt-2"></div>';
}
Modal.abrir('Cambiar estado — '+m.codigo,html,'modal-md');
 $('#mt-est-fin').on('click',function(){Mantenimiento._finalizar(m)});
 $('.btn-mt-est').on('click',function(){
if($(this).data('est')==='cerrado'&&m.finalizable){Mantenimiento._finalizar(m);return}
Mantenimiento._formEstado(m.id,$(this).data('est'),{});
});
});
},
/* boton de un estado destino; si el equipo esta con el proveedor, volver a Sistemas se ofrece como «anular envio» */
_conProveedor:function(m){return ['enviado_proveedor','cotizado','aprobado','rechazado','en_reparacion'].indexOf(m.estado)>=0},
_btnEstado:function(m,x,dest){
var rev=Mantenimiento._conProveedor(m)&&(x==='en_evaluacion'||x==='en_proceso');
var txt=rev?(x==='en_proceso'?'Atendido por Sistemas (anular envío)':'Volver a evaluación (anular envío)'):(EST_MT[x]||x);
return '<button class="btn btn-sm '+(rev?'btn-outline-success':'btn-outline-secondary')+' me-1 mb-1 btn-mt-est'+(x===dest?' active':'')+'" data-est="'+x+'">'+(rev?'<i class="bi bi-arrow-counterclockwise"></i> ':'')+esc(txt)+'</button>'},
/* Formulario de una transicion concreta (reutilizable tras registrar proveedor) */
_formEstado:function(id,dest,prefill){
prefill=prefill||{};
var yo=Mantenimiento;
Api.get('api/mantenimiento/detalle.php',{id:id}).then(function(m){
// re-renderizar SIEMPRE el modal de estado (el de proveedor pudo haberlo reemplazado)
var trans=m.transiciones||[];
var html='<div class="mb-2"><span class="badge text-bg-primary">'+esc(EST_MT[m.estado]||m.estado)+'</span> <span class="small text-muted">'+esc(m.codigo)+'</span></div>';
if(!trans.length){html+='<div class="text-muted small">Estado final, sin transiciones.</div>';}
else{
if(m.finalizable&&m.puede&&m.puede.cambiar_estado)html+='<div class="alert alert-success py-2 small d-flex align-items-center gap-2 flex-wrap"><span>¿Ya terminó el trabajo?</span><button type="button" class="btn btn-sm btn-success" id="mt-est-fin"><i class="bi bi-check2-circle"></i> Finalizar ahora</button></div>';
html+=trans.map(function(x){return Mantenimiento._btnEstado(m,x,dest)}).join('');
html+='<div id="mt-est-form" class="mt-2"></div>';
}
Modal.abrir('Cambiar estado — '+m.codigo,html,'modal-md');
 $('#mt-est-fin').on('click',function(){Mantenimiento._finalizar(m)});
 $('.btn-mt-est').on('click',function(){
if($(this).data('est')==='cerrado'&&m.finalizable){Mantenimiento._finalizar(m);return}
Mantenimiento._formEstado(m.id,$(this).data('est'),{});
});
if(!trans.length)return;
var necesitaProv=(dest==='enviado_proveedor');
var necesitaCoti=(dest==='cotizado');
var f='<div class="border-top pt-2">';
if(necesitaProv)f+='<label class="small">Proveedor *</label><div class="d-flex gap-1 mb-2">'+
'<select class="form-select form-select-sm" id="me-prov"><option value="">Cargando…</option></select>'+
'<button class="btn btn-sm btn-outline-primary text-nowrap" id="me-prov-nuevo" title="Registrar proveedor nuevo"><i class="bi bi-plus-lg"></i></button></div>'+
'<label class="small">Fecha estimada de retorno *</label><input type="date" class="form-control form-control-sm" id="me-retorno" value="'+esc(prefill.retorno||'')+'">';
if(necesitaCoti)f+='<label class="small">Monto cotizado (S/) *</label><input type="number" class="form-control form-control-sm mb-2" id="me-coti" min="0.1" step="0.1">'+
'<label class="small">Archivo de cotización (PDF/imagen) *</label><input type="file" class="form-control form-control-sm" id="me-archivo" accept="image/*,.pdf">';
if(dest==='en_reparacion')f+='<label class="small">Costo aprobado (S/)</label><input type="number" class="form-control form-control-sm" id="me-costo" min="0" step="0.1">';
var revierte=yo._conProveedor(m)&&(dest==='en_evaluacion'||dest==='en_proceso');
var pideMotivo=(dest==='cancelado'||dest==='rechazado'||dest==='no_reparable'||revierte);
if(revierte)f+='<div class="alert alert-success py-2 small mb-2" id="me-aviso-rev"><i class="bi bi-arrow-counterclockwise"></i> Se <b>anula el envío al proveedor</b>'+(m.proveedor?' («'+esc(m.proveedor)+'»)':'')+
(dest==='en_proceso'?' y el equipo lo atiende el <b>área de Sistemas</b> (queda <b>En proceso</b>, en mantenimiento interno).':' y el mantenimiento vuelve a <b>evaluación</b> interna.')+' Luego podrá finalizarlo normalmente.</div>';
if(pideMotivo)f+='<label class="small" for="me-motivo">'+(revierte?'¿Por qué se anula el envío al proveedor? *':(dest==='cancelado'?'Motivo de la cancelación *':(dest==='rechazado'?'Motivo del rechazo *':'¿Por qué no es reparable? *')))+'</label>'+
'<textarea class="form-control form-control-sm mb-2" id="me-motivo" rows="3" maxlength="250" placeholder="Escriba aquí el motivo..."></textarea>';
if(dest==='listo')f+='<label class="small" for="me-sol">Trabajo realizado *</label>'+
'<textarea class="form-control form-control-sm mb-2" id="me-sol" rows="3" maxlength="2000" placeholder="Ej: Se cambió la fuente de poder y se probó el equipo.">'+esc(m.solucion||'')+'</textarea>';
f+='<div class="d-flex gap-1 mt-2"><button class="btn btn-sm btn-primary flex-fill" id="me-ok"><i class="bi bi-check-lg"></i> Confirmar</button>'+
'<button class="btn btn-sm btn-outline-secondary" id="me-no">Cancelar</button></div></div>';
 $('#mt-est-form').html(f);
 $('#me-no').on('click',function(){$('#mt-est-form').empty()});
if(necesitaProv){
 $.get(BASE_URL+'api/catalogos/select.php?tipo=proveedores',function(r){
if(r.success){
$('#me-prov').html('<option value="">— Seleccione proveedor —</option>'+r.data.map(function(p){return '<option value="'+p.id+'">'+esc(p.texto)+'</option>'}).join(''));
if(prefill.proveedor_id)$('#me-prov').val(prefill.proveedor_id);
}
});
// registrar proveedor sin perder el estado del panel
 $('#me-prov-nuevo').on('click',function(){
var retorno=$('#me-retorno').val();
yo._formProveedor(function(nuevoId){
yo._formEstado(id,dest,{retorno:retorno,proveedor_id:nuevoId});
});
});
}
 $('#me-ok').on('click',function(){
var d={id:id,estado:dest};
if(pideMotivo){
d.motivo=($('#me-motivo').val()||'').trim();
if(!d.motivo){Toast.warning(revierte?'Indique por qué se anula el envío al proveedor':(dest==='cancelado'?'Indique el motivo de la cancelación':(dest==='rechazado'?'Indique el motivo del rechazo':'Indique por qué no es reparable')));$('#me-motivo').trigger('focus');return}
}
if(dest==='listo'){
d.solucion=($('#me-sol').val()||'').trim();
if(!d.solucion){Toast.warning('Describa el trabajo realizado');$('#me-sol').trigger('focus');return}
}
var enviar=function(){
Api.post('api/mantenimiento/estado.php',d)
.then(function(r){Modal.cerrar();Mantenimiento._ver(id);if(Mantenimiento.tabla)Mantenimiento.tabla.draw(false);Toast.success((r&&r.mensaje)||'Estado actualizado')})
.catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
};
if(necesitaProv){
d.proveedor_id=$('#me-prov').val();
d.fecha_retorno=$('#me-retorno').val();
if(!d.proveedor_id){Toast.warning('Selecciona proveedor');return}
if(!d.fecha_retorno){Toast.warning('Indica la fecha estimada de retorno');return}
}
if(necesitaCoti){
var arch=document.getElementById('me-archivo');
if(!$('#me-coti').val()||+$('#me-coti').val()<=0){Toast.warning('Indica el monto cotizado');return}
if(!arch||!arch.files.length){Toast.warning('Adjunta el archivo de la cotización');return}
var fd=new FormData();
Object.keys(d).forEach(function(k){fd.append(k,d[k])});
fd.append('cotizacion_archivo',arch.files[0]);
Api.postFiles('api/mantenimiento/estado.php',fd)
.then(function(r){Modal.cerrar();Mantenimiento._ver(id);if(Mantenimiento.tabla)Mantenimiento.tabla.draw(false);Toast.success((r&&r.mensaje)||'Estado actualizado')})
.catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
return;
}
enviar();
});
});
},
/* ============ EDICION ============ */
_formEdit:function(m){
var html='<form id="form-mted"><div class="row">'+
'<div class="col-12 mb-3"><label>Diagnóstico</label><textarea class="form-control" id="fe-diag" rows="3">'+esc(m.diagnostico||'')+'</textarea></div>'+
'<div class="col-12 mb-3"><label>Solución / trabajos realizados</label><textarea class="form-control" id="fe-sol" rows="3">'+esc(m.solucion||'')+'</textarea></div>'+
'<div class="col-md-4 mb-3"><label>Costo mano de obra (S/)</label><input type="number" class="form-control" id="fe-mano" min="0" step="0.1" value="'+(m.costo_mano_obra||0)+'"></div>'+
'<div class="col-md-4 mb-3"><label>Técnico interno</label><select class="form-select" id="fe-tec"><option value="">— Sin cambio —</option></select></div>'+
'<div class="col-md-4 mb-3"><label>Observaciones</label><input class="form-control" id="fe-obs" maxlength="1000" value="'+esc(m.observaciones||'')+'"></div>'+
'</div><div class="text-end"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button class="btn btn-primary" id="fe-ok"><i class="bi bi-check-lg"></i> Guardar</button></div></form>';
Modal.abrir('Editar '+m.codigo,html,'modal-lg');
 $.get(BASE_URL+'api/catalogos/select.php?tipo=tecnicos',function(r){
if(r.success)$('#fe-tec').html('<option value="">— Sin cambio —</option>'+r.data.map(function(u){return '<option value="'+u.id+'"'+(+u.id===+m.tecnico_usuario_id?' selected':'')+'>'+esc(u.texto)+'</option>'}).join(''));
});
 $('#fe-ok').on('click',function(){
var d={id:m.id,diagnostico:$('#fe-diag').val(),solucion:$('#fe-sol').val(),
costo_mano_obra:$('#fe-mano').val(),tecnico_id:$('#fe-tec').val()||String(m.tecnico_usuario_id||''),observaciones:$('#fe-obs').val()};
Api.post('api/mantenimiento/actualizar.php',d)
.then(function(){Modal.cerrar();Mantenimiento._ver(m.id);Mantenimiento.tabla.draw(false);Toast.success('Actualizado')})
.catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
});
},
/* ============ ELIMINAR ============ */
_eliminar:function(f){
Swal.fire({
title:'¿Eliminar '+f.codigo+'?',
html:'<b>'+esc(f.equipo_codigo||'')+'</b><br><span class="small text-muted">Se borra con sus repuestos y evidencias. La auditoría conserva el registro. Si el equipo estaba bloqueado, se libera.</span><br>'+
'<input class="form-control form-control-sm mt-2" id="sw-motivo-mt" placeholder="Motivo (opcional)">',
icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',
confirmButtonColor:'#dc2626',focusCancel:true,
didOpen:function(){$('#sw-motivo-mt').val('')}
}).then(function(r){
if(!r.isConfirmed)return;
Api.post('api/mantenimiento/eliminar.php',{id:f.id,motivo:$('#sw-motivo-mt').val()||''})
.then(function(m){Mantenimiento.tabla.draw();Toast.success(m||'Eliminado')});
});
},
/* ============ volver a la lista SIN perder la pestaña «Preventivos programados» ============ */
_tabPrev:false,
_irPreventivos:function(){Mantenimiento._tabPrev=true;Router.ir('mantenimiento/lista')},
_abrirTabPendiente:function(){
if(!Mantenimiento._tabPrev)return;
Mantenimiento._tabPrev=false;
var b=document.getElementById('btn-tab-prev');
if(b){try{bootstrap.Tab.getOrCreateInstance(b).show()}catch(e){ $(b).trigger('click') }}
},
/* ============ MOTOR AUTONOMO: estado en el banner ============ */
_motorEstado:function(){
if(!$('#motor-banner').length)return;
Api.get('api/preventivos/motor.php',{accion:'estado'}).then(function(d){
var parts=[];
if(d.ultima_ejecucion)parts.push('Última ejecución: '+d.ultima_ejecucion);
else parts.push('Primera ejecución al cargar el sistema');
if(+d.generaria>0)parts.push('Pendientes hoy: '+d.generaria+' ('+
[].concat(
(d.por_calendario||[]).length?('calendario '+d.por_calendario.length):null,
(d.por_vida||[]).length?('tiempo de uso '+d.por_vida.length):null,
(d.por_fallas||[]).length?('fallas '+d.por_fallas.length):null
).filter(Boolean).join(', ')+')');
 $('#motor-ultima').text(parts.join(' · '));
}).catch(function(){ $('#motor-ultima').text('No se pudo consultar el estado del motor.') });
},
/* ============ PREVENTIVOS PROGRAMADOS (CRUD) ============ */
_formProg:function(id,datos){
id=id||0;datos=datos||{};
Promise.all([
Api.get('api/equipos/select.php',{modo:'activos'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'tecnicos'}).catch(function(){return[]})
]).then(function(R){
var html='<form id="form-prog"><div class="row">'+
'<div class="col-12 mb-3"><label>Equipo *</label><select class="form-select" id="fp-equipo">'+
'<option value="">— Seleccione —</option>'+
R[0].map(function(e){return '<option value="'+e.id+'">'+esc(e.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-6 mb-3"><label>Tipo *</label><select class="form-select" id="fp-tipo">'+
'<option value="preventivo">Preventivo</option><option value="limpieza">Limpieza</option>'+
'<option value="actualizacion">Actualización</option></select></div>'+
'<div class="col-md-6 mb-3"><label>Frecuencia *</label><select class="form-select" id="fp-frecuencia">'+
'<option value="mensual">Mensual</option><option value="trimestral">Trimestral</option>'+
'<option value="semestral">Semestral</option><option value="anual">Anual</option></select></div>'+
'<div class="col-md-6 mb-3"><label>Próxima fecha *</label><input type="date" class="form-control" id="fp-fecha" value="'+esc(datos.proxima_fecha||'')+'"></div>'+
'<div class="col-md-6 mb-3"><label>Responsable</label><select class="form-select" id="fp-resp"><option value="">— Opcional —</option>'+
R[1].map(function(u){return '<option value="'+u.id+'">'+esc(u.texto)+'</option>'}).join('')+'</select></div>'+
'</div><div class="text-end"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button class="btn btn-primary" id="btn-fp"><i class="bi bi-check-lg"></i> '+(id>0?'Guardar':'Programar')+'</button></div></form>';
Modal.abrir(id>0?'Editar programación':'Programar mantenimiento preventivo',html,'modal-lg');
if(id>0){
 $('#fp-equipo').val(datos.equipo_id);
 $('#fp-tipo').val(datos.tipo);
 $('#fp-frecuencia').val(datos.frecuencia);
if(datos.responsable_id)$('#fp-resp').val(datos.responsable_id);
}
 $('#btn-fp').on('click',function(){
var d={accion:'guardar',id:id,equipo_id:$('#fp-equipo').val(),tipo:$('#fp-tipo').val(),
frecuencia:$('#fp-frecuencia').val(),proxima_fecha:$('#fp-fecha').val(),responsable_id:$('#fp-resp').val()||''};
if(!d.equipo_id){Toast.warning('Selecciona el equipo');return}
if(!d.proxima_fecha){Toast.warning('Indica la próxima fecha');return}
Api.post('api/mantenimiento/programados.php',d).then(function(){
Modal.cerrar();Mantenimiento._irPreventivos();Toast.success('Programación guardada');
}).catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
});
});
},
_datoProg:function(id,luego){
Api.get('api/mantenimiento/programados.php').then(function(lista){
var p=(lista||[]).find(function(x){return +x.id===+id});
luego(p||{});
});
},
/* ============ ESTADO (maquina) — helper usado por _estado ============ */
_estadosTxt:function(v){return EST_MT[v]||v}
};
App.registrar('mantenimiento',Mantenimiento);

/* ============ Manejadores delegados ============ */
// fila: ver / estado / editar / eliminar
 $(document).on('click','#tb-mt .btn-ver-mt',function(){
var f=Mantenimiento.tabla.row($(this).closest('tr')).data();
if(f)Mantenimiento._ver(f.id);
});
 $(document).on('click','#tb-mt .btn-finalizar-mt',function(){
var f=Mantenimiento.tabla.row($(this).closest('tr')).data();
if(!f)return;
Api.get('api/mantenimiento/detalle.php',{id:f.id}).then(function(m){Mantenimiento._finalizar(m)});
});
 $(document).on('click','#tb-mt .btn-estado-mt',function(){
var f=Mantenimiento.tabla.row($(this).closest('tr')).data();
if(f)Mantenimiento._estado(f.id,f.estado);
});
 $(document).on('click','#tb-mt .btn-editar-mt',function(){
var f=Mantenimiento.tabla.row($(this).closest('tr')).data();
if(!f)return;
Api.get('api/mantenimiento/detalle.php',{id:f.id}).then(function(m){Mantenimiento._formEdit(m)});
});
 $(document).on('click','#tb-mt .btn-eliminar-mt',function(){
var f=Mantenimiento.tabla.row($(this).closest('tr')).data();
if(f)Mantenimiento._eliminar(f);
});
// preventivos programados
 $(document).on('click','.btn-ver-prog-mt',function(e){e.preventDefault();Mantenimiento._ver($(this).data('id'))});
 $(document).on('click','#btn-nuevo-prog',function(){Mantenimiento._formProg(0)});
 $(document).on('click','.btn-editar-prog',function(){
var id=$(this).data('id');
Mantenimiento._datoProg(id,function(p){Mantenimiento._formProg(id,p)});
});
 $(document).on('click','.btn-toggle-prog',function(){
Api.post('api/mantenimiento/programados.php',{accion:'toggle',id:$(this).data('id')})
.then(function(m){Mantenimiento._irPreventivos();Toast.success(m||'Actualizado')});
});
 $(document).on('click','.btn-ejecutar-prog',function(){
var id=$(this).data('id'),eq=$(this).data('equipo');
Swal.fire({title:'Ejecutar programación?',text:'Generará el mantenimiento de '+eq+' ahora. Al finalizarlo, la próxima fecha se calcula sola.',icon:'question',
showCancelButton:true,confirmButtonText:'Ejecutar',cancelButtonText:'Cancelar',confirmButtonColor:'#2563eb'})
.then(function(r){
if(!r.isConfirmed)return;
Api.post('api/mantenimiento/programados.php',{accion:'ejecutar',id:id})
.then(function(d){Mantenimiento._irPreventivos();Toast.success((d&&d.mensaje)||'Mantenimiento generado')})
.catch(function(){});
});
});
 $(document).on('click','#btn-motor-ejecutar',function(){
Swal.fire({title:'Forzar el motor ahora?',text:'Generará todos los preventivos pendientes por calendario, tiempo de uso y fallas recurrentes.',icon:'question',
showCancelButton:true,confirmButtonText:'Ejecutar',cancelButtonText:'Cancelar',confirmButtonColor:'#16a34a'})
.then(function(r){
if(!r.isConfirmed)return;
Api.post('api/preventivos/motor.php',{accion:'ejecutar'}).then(function(d){
var gen=(d&&d.generados)||[],n=gen.length;
Mantenimiento._irPreventivos();
Toast.success(n?('Motor ejecutado: '+n+' mantenimiento(s) generado(s): '+gen.slice(0,5).map(function(g){return g.codigo+' ('+g.equipo+')'}).join(', ')+(n>5?'…':'')):'Nada pendiente por generar: no hay preventivos con la fecha cumplida.');
});
});
});
