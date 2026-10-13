/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Asignaciones de equipos de TI
   Vista: views/asignaciones/lista.php
   API:   api/asignaciones/{listar,entregar,devolver,editar,eliminar,stats,solicitudes}.php

   Cada equipo puede ir a una PERSONA, un AREA, un PROYECTO o una OBRA.
   En area / proyecto / obra se indica ademas el RESPONSABLE (persona que
   recibe, firma el acta y custodia los equipos).
   ============================================================ */
var ESTADOS_AS={activa:{t:'Activa',c:'text-bg-success'},vencida:{t:'Vencida',c:'text-bg-danger'},devuelta:{t:'Devuelta',c:'text-bg-secondary'},cancelada:{t:'Cancelada',c:'text-bg-dark'}};
var DESTINOS_AS={
persona:{t:'Persona',i:'bi-person',c:'text-bg-primary'},
area:{t:'Área',i:'bi-people',c:'text-bg-info'},
proyecto:{t:'Proyecto',i:'bi-kanban',c:'text-bg-success'},
obra:{t:'Obra',i:'bi-cone-striped',c:'text-bg-warning text-dark'}
};
function badgeDestino(tipo){
var d=DESTINOS_AS[tipo]||DESTINOS_AS.persona;
return '<span class="badge '+d.c+'"><i class="bi '+d.i+'"></i> '+d.t+'</span>';
}

var Asignaciones={
tabla:null,
perm:{crear:false,devolver:false,solicitar:false},

init:function(){
var s=this,$p=$('#asig-permisos');
s.perm={crear:+$p.data('crear')===1,devolver:+$p.data('devolver')===1,solicitar:+$p.data('solicitar')===1};

s.tabla=DT.server('#tb-asig','api/asignaciones/listar.php',
function(){return{
f_estado:$('#asf-estado').val()||'',
f_tipo:$('#asf-tipo').val()||'',
f_destino:$('#asf-destino').val()||'',
f_proyecto:$('#asf-proyecto').val()||''
}},
[
{data:'equipo_codigo',render:function(v,t,f){return '<strong class="nowrap">'+esc(v)+'</strong><br><span class="text-muted small">'+esc((f.equipo_tipo||'')+' '+(f.marca||''))+'</span>'}},
{data:'equipo_tipo',visible:false},
{data:'destino_nombre',render:function(v,t,f){
var h=badgeDestino(f.destino_tipo)+' <b>'+esc(v||'—')+'</b>';
if(f.destino_tipo==='persona'&&f.dni)h+='<br><span class="text-muted small">DNI '+esc(f.dni)+'</span>';
if(f.destino_codigo)h+='<br><span class="text-muted small">'+esc(f.destino_codigo)+(f.destino_ubicacion?' · '+esc(f.destino_ubicacion):'')+'</span>';
if(f.destino_detalle)h+='<br><span class="small"><i class="bi bi-geo-alt"></i> '+esc(f.destino_detalle)+'</span>';
return h}},
{data:'responsable',render:function(v,t,f){
if(f.destino_tipo==='persona')return f.area?esc(f.area):'<span class="text-muted">&mdash;</span>';
return v?'<span class="small text-muted">Resp.</span> '+esc(v):'<span class="text-muted">&mdash;</span>'}},
{data:'tipo',render:function(v){return v==='prestamo'?'<span class="badge text-bg-info">Prestamo</span>':'<span class="badge text-bg-primary">Permanente</span>'}},
{data:'fecha_entrega',className:'nowrap',render:function(v){return '<span class="small">'+esc(v)+'</span>'}},
{data:'fecha_devolucion_esperada',className:'nowrap',render:function(v,t,f){
if(!v)return '&mdash;';
return +f.vencido===1?'<span class="badge text-bg-danger">'+esc(v)+' VENCIDO</span>':'<span class="small">'+esc(v)+'</span>'}},
{data:'estado',render:function(v,t,f){
if(+f.vencido===1)return '<span class="badge text-bg-danger">VENCIDO</span>';
var e=ESTADOS_AS[v]||{t:v,c:'text-bg-light'};
return '<span class="badge '+e.c+'">'+e.t+'</span>'}},
{data:null,orderable:false,className:'text-nowrap text-center',render:function(v,t,f){
var act=['activa','vencida'].indexOf(f.estado)>=0,h='';
if(act&&s.perm.devolver)h+='<button class="btn btn-sm btn-outline-warning btn-devolver me-1" title="Recibir / devolver"><i class="bi bi-box-arrow-in-left"></i></button>';
if(act&&s.perm.crear){
h+='<button class="btn btn-sm btn-outline-primary btn-trasladar me-1" title="Trasladar a otro destino"><i class="bi bi-arrow-left-right"></i></button>';
h+='<button class="btn btn-sm btn-outline-secondary btn-editar-asig me-1" title="Editar modalidad / retorno"><i class="bi bi-pencil"></i></button>';
h+='<button class="btn btn-sm btn-outline-danger btn-cancelar-asig me-1" title="Cancelar (registro erroneo)"><i class="bi bi-x-circle"></i></button>';
}
if(f.acta_token)h+='<a class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener" title="Ver acta" href="'+BASE_URL+'acta.php?token='+encodeURIComponent(f.acta_token)+'"><i class="bi bi-file-earmark-text"></i></a>';
return h||'<span class="text-muted">&mdash;</span>'}}
],{order:[[5,'desc']]});

s._cargarFiltroProyectos();
s._stats();

$('#btn-filtrar-asig').off('click.asig').on('click.asig',function(){s.tabla.draw()});
$('#asf-estado,#asf-tipo,#asf-destino,#asf-proyecto').off('change.asig').on('change.asig',function(){s.tabla.draw()});
$('#btn-limpiar-asig').off('click.asig').on('click.asig',function(){
$('#asf-estado,#asf-tipo,#asf-destino,#asf-proyecto').val('');
s.tabla.search('').draw();
});
$('#btn-nueva-asignacion').off('click.asig').on('click.asig',function(){s._entrega()});

$('#tb-asig').off('click.asig').on('click.asig','.btn-devolver,.btn-trasladar,.btn-editar-asig,.btn-cancelar-asig',function(){
var f=s.tabla.row($(this).closest('tr')).data();
if(!f)return;
if($(this).hasClass('btn-devolver'))s._devolver(f);
else if($(this).hasClass('btn-trasladar'))s._entrega({operacion:'traslado',equipo_id:f.equipo_id});
else if($(this).hasClass('btn-editar-asig'))s._editar(f);
else s._cancelar(f);
});

/* ---- pestaña Solicitudes (la tabla viene armada desde el servidor) ---- */
var n=$('#tab-sol .btn-aprobar-sol').length;
$('#badge-sol-pend').text(n);
$('#btn-nueva-solicitud').off('click.asig').on('click.asig',function(){s._solicitar()});
$('#tab-sol').off('click.asig').on('click.asig','.btn-aprobar-sol,.btn-rechazar-sol,.btn-cancelar-sol,.btn-entregar-sol',function(){
var $b=$(this),id=$b.data('id'),cod=$b.data('codigo');
if($b.hasClass('btn-entregar-sol')){
var pt=$b.data('proyecto-tipo'),pid=+$b.data('proyecto')||0;
s._entrega({solicitud_id:id,tipo_equipo_id:+$b.data('tipo')||0,
destino:pid?pt:'persona',proyecto_id:pid,personal_id:+$b.data('solicitante')||0,codigo:cod});
}else if($b.hasClass('btn-cancelar-sol'))s._accionSol('cancelar',id,cod,false);
else if($b.hasClass('btn-aprobar-sol'))s._accionSol('aprobar',id,cod,true);
else s._accionSol('rechazar',id,cod,true);
});

/* viene desde Inventario (boton Asignar / Trasladar): abre el formulario ya preparado */
if(s.pendiente){var pd=s.pendiente;s.pendiente=null;setTimeout(function(){s._entrega(pd)},150)}
},

_stats:function(){
Api.get('api/asignaciones/stats.php').then(function(r){
$('#asig-stats [data-k]').each(function(){$(this).text(r[$(this).data('k')]||0)});
}).catch(function(){});
},

_cargarFiltroProyectos:function(){
Api.get('api/catalogos/select.php',{tipo:'proyectos'}).then(function(l){
var g={proyecto:'',obra:''};
(l||[]).forEach(function(p){g[p.tipo]+='<option value="'+p.id+'">'+esc(p.texto)+'</option>'});
$('#asf-proyecto').append((g.proyecto?'<optgroup label="Proyectos">'+g.proyecto+'</optgroup>':'')+(g.obra?'<optgroup label="Obras">'+g.obra+'</optgroup>':''));
}).catch(function(){});
},

/* ---------- utilidades de formulario ---------- */
_opciones:function(lista,sel,vacio){
var h='<option value="">'+(vacio||'— Seleccione —')+'</option>';
(lista||[]).forEach(function(o){h+='<option value="'+esc(o.id)+'"'+(sel&&+sel===+o.id?' selected':'')+'>'+esc(o.texto)+'</option>'});
return h;
},
/* caja de busqueda que filtra las opciones de un <select> largo */
_filtrar:function(inputSel,selectSel,lista,vacio){
var s=this;
$(inputSel).off('input.f').on('input.f',function(){
var q=$(this).val().toLowerCase(),act=$(selectSel).val();
var sub=(lista||[]).filter(function(o){return !q||String(o.texto).toLowerCase().indexOf(q)>=0||String(o.dni||'').indexOf(q)>=0||+o.id===+act});
$(selectSel).html(s._opciones(sub,act,vacio)).val(act);
});
},

/* ============================================================
   REGISTRAR ENTREGA / PRESTAMO / CAMBIO / TRASLADO
   o = {operacion, equipo_id, destino, personal_id, proyecto_id,
        tipo_equipo_id, solicitud_id}
   ============================================================ */
_entrega:function(o){
o=o||{};
var s=this;
Promise.all([
Api.get('api/catalogos/select.php',{tipo:'personal'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'areas'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'proyectos'}).catch(function(){return[]})
]).then(function(R){
var personal=R[0]||[],areas=R[1]||[],proy=R[2]||[];
var proyectos=proy.filter(function(p){return p.tipo==='proyecto'}),obras=proy.filter(function(p){return p.tipo==='obra'});
var dest0=o.destino||'persona',op0=o.operacion||(o.solicitud_id?'entrega':'entrega');

var radio=function(v){var d=DESTINOS_AS[v];
return '<input type="radio" class="btn-check" name="destino" id="dst-'+v+'" value="'+v+'"'+(dest0===v?' checked':'')+'>'+
'<label class="btn btn-outline-primary btn-sm" for="dst-'+v+'"><i class="bi '+d.i+'"></i> '+d.t+'</label>'};

var html='<form id="form-entrega" autocomplete="off">'+
(o.codigo?'<div class="alert alert-info py-2 small mb-3"><i class="bi bi-inbox"></i> Atendiendo la solicitud <b>'+esc(o.codigo)+'</b>.</div>':'')+
'<div class="row g-3">'+
'<div class="col-md-4"><label class="form-label">Operación</label><select class="form-select" id="ae-operacion">'+
'<option value="entrega">Entrega permanente</option><option value="prestamo">Préstamo temporal</option>'+
'<option value="cambio">Cambio de equipo</option><option value="traslado">Traslado (equipo ya asignado)</option></select></div>'+
'<div class="col-md-8"><label class="form-label">Asignar a</label><div class="btn-group w-100" role="group">'+
radio('persona')+radio('area')+radio('proyecto')+radio('obra')+'</div></div>'+

/* destino: persona */
'<div class="col-md-6 ae-z ae-z-persona"><label class="form-label">Colaborador *</label>'+
'<input class="form-control form-control-sm mb-1" id="ae-personal-q" placeholder="Buscar por nombre o DNI...">'+
'<select class="form-select" id="ae-personal">'+s._opciones(personal,o.personal_id)+'</select></div>'+
/* destino: area */
'<div class="col-md-6 ae-z ae-z-area"><label class="form-label">Área *</label>'+
'<select class="form-select" id="ae-area">'+s._opciones(areas,null)+'</select></div>'+
/* destino: proyecto */
'<div class="col-md-6 ae-z ae-z-proyecto"><label class="form-label">Proyecto *</label>'+
'<select class="form-select" id="ae-proyecto">'+s._opciones(proyectos,dest0==='proyecto'?o.proyecto_id:null,'— Seleccione el proyecto —')+'</select>'+
(proyectos.length?'':'<div class="form-text text-danger">No hay proyectos activos: cree uno en Inventario › Proyectos y Obras.</div>')+'</div>'+
/* destino: obra */
'<div class="col-md-6 ae-z ae-z-obra"><label class="form-label">Obra *</label>'+
'<select class="form-select" id="ae-obra">'+s._opciones(obras,dest0==='obra'?o.proyecto_id:null,'— Seleccione la obra —')+'</select>'+
(obras.length?'':'<div class="form-text text-danger">No hay obras activas: cree una en Inventario › Proyectos y Obras.</div>')+'</div>'+

/* responsable (area / proyecto / obra) */
'<div class="col-md-6 ae-z ae-z-resp"><label class="form-label">Responsable que recibe *</label>'+
'<select class="form-select" id="ae-resp">'+s._opciones(personal,null)+'</select>'+
'<div class="form-check mt-1 ae-z-area"><input class="form-check-input" type="checkbox" id="ae-resp-todos"><label class="form-check-label small" for="ae-resp-todos">Mostrar todo el personal (no solo el del área)</label></div>'+
'<div class="form-text">Firma el acta y custodia los equipos.</div></div>'+
'<div class="col-md-12 ae-z ae-z-info small text-muted" id="ae-info"></div>'+
'<div class="col-md-6 ae-z ae-z-resp"><label class="form-label">Frente / zona / detalle (opcional)</label>'+
'<input class="form-control" id="ae-detalle" maxlength="150" placeholder="Ej: Frente 2, Oficina de campo, Torre B"></div>'+

/* equipo */
'<div class="col-md-8"><label class="form-label" id="ae-lbl-equipo">Equipo a entregar *</label>'+
'<select class="form-select" id="ae-equipo"><option value="">Cargando...</option></select></div>'+
'<div class="col-md-4 ae-z-retorno"><label class="form-label" id="ae-lbl-retorno">Retorno esperado *</label>'+
'<input type="date" class="form-control" id="ae-retorno"></div>'+

/* cambio de equipo */
'<div class="col-12 ae-z-cambio"><div class="border rounded p-3 bg-light"><div class="row g-3">'+
'<div class="col-md-12"><label class="form-label">Equipo que se retira (de este destino) *</label><select class="form-select" id="ae-viejo"><option value="">— Elija primero el destino —</option></select></div>'+
'<div class="col-md-4"><label class="form-label">Condición al retirarlo</label><select class="form-select" id="ae-cond-dev"><option value="bueno">Bueno</option><option value="regular">Regular</option><option value="danado">Dañado</option><option value="faltante">Faltante</option></select></div>'+
'<div class="col-md-4"><label class="form-label">Motivo del cambio</label><select class="form-select" id="ae-motivo"><option value="renovacion">Renovación</option><option value="falla">Falla del equipo</option><option value="robo_perdida">Robo / pérdida</option><option value="otro">Otro</option></select></div>'+
'<div class="col-md-4"><label class="form-label">Detalle del motivo</label><input class="form-control" id="ae-motivo-texto" maxlength="200"></div>'+
'<div class="col-12"><label class="form-label">Observaciones del equipo retirado</label><input class="form-control" id="ae-obs-dev" maxlength="250"></div>'+
'</div></div></div>'+

/* verificacion de TI segun el tipo de equipo (queda marcada en el acta) */
'<div class="col-12 d-none" id="ae-verif"><div class="border rounded p-3">'+
'<div class="fw-semibold mb-1"><i class="bi bi-clipboard-check"></i> Verificación de TI al entregar <span class="badge text-bg-light border ms-1" id="ae-fam-nombre"></span></div>'+
'<div class="small text-muted mb-2">Marque lo que se verificó junto con el receptor: saldrá marcado en el acta.</div>'+
'<div id="ae-checks"></div></div></div>'+
'<div class="col-12 ae-z-cambio"><div class="border rounded p-3">'+
'<div class="fw-semibold mb-1"><i class="bi bi-clipboard-check"></i> Inspección del equipo que se retira <span class="badge text-bg-light border ms-1" id="ae-fam-nombre-dev"></span></div>'+
'<div id="ae-checks-dev"><span class="text-muted small">Elija el equipo que se retira.</span></div>'+
'<label class="form-label mt-2 mb-0 small">Accesorios faltantes del equipo que se retira (opcional)</label>'+
'<input class="form-control form-control-sm" id="ae-acc-falt-dev" maxlength="200" placeholder="Ej: cargador, mouse"></div></div>'+

'<div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" id="ae-obs" rows="2" maxlength="300"></textarea></div>'+
'</div><div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button type="button" class="btn btn-primary" id="btn-ae"><i class="bi bi-check-lg"></i> Registrar</button></div></form>';
Modal.abrir('Registrar entrega de equipo',html,'modal-xl');

var listaEq=[],listaViejos=[];
var famDe=function(lista,id){var e=(lista||[]).filter(function(x){return +x.id===+id})[0];return e?(e.familia||'otro'):''};
var pintarChecks=function(){
ActaPerf.cargar().then(function(){
var f=famDe(listaEq,$('#ae-equipo').val());
$('#ae-verif').toggleClass('d-none',!f);
$('#ae-checks').html(f?ActaPerf.html('ent',f):'');
$('#ae-fam-nombre').text(f?ActaPerf.nombre(f):'');
var fv=famDe(listaViejos,$('#ae-viejo').val());
$('#ae-checks-dev').html(fv?ActaPerf.html('dev',fv):'<span class="text-muted small">Elija el equipo que se retira.</span>');
$('#ae-fam-nombre-dev').text(fv?ActaPerf.nombre(fv):'');
});
};
var dest=function(){return $('input[name=destino]:checked').val()||'persona'};
var oper=function(){return $('#ae-operacion').val()};
var refDestino=function(){
var d=dest();
return d==='persona'?$('#ae-personal').val():d==='area'?$('#ae-area').val():d==='proyecto'?$('#ae-proyecto').val():$('#ae-obra').val();
};

/* muestra solo las zonas que corresponden al destino y a la operacion */
var zonas=function(){
var d=dest(),op=oper();
$('.ae-z').addClass('d-none');
$('.ae-z-'+d).removeClass('d-none');
if(d!=='persona')$('.ae-z-resp').removeClass('d-none');
if(d==='proyecto'||d==='obra')$('#ae-info').removeClass('d-none');
$('#ae-resp-todos').closest('.form-check').toggleClass('d-none',d!=='area');
var esPy=(d==='proyecto'||d==='obra');
$('.ae-z-retorno').toggleClass('d-none',!(op==='prestamo'||op==='traslado'||esPy));
$('.ae-z-cambio').toggleClass('d-none',op!=='cambio');
$('#ae-lbl-retorno').text(esPy?'Devolución estipulada *':(op==='traslado'?'Retorno (si es temporal)':'Retorno esperado *'));
$('#ae-lbl-equipo').text(op==='traslado'?'Equipo a trasladar *':op==='cambio'?'Equipo nuevo a entregar *':'Equipo a entregar *');
};

/* responsables: en un area se ofrecen primero los de esa area */
var cargarResp=function(sel){
var d=dest(),lista=personal;
if(d==='area'&&!$('#ae-resp-todos').is(':checked')&&$('#ae-area').val()){
var a=+$('#ae-area').val();
lista=personal.filter(function(p){return +p.area_id===a});
if(!lista.length)lista=personal;
}
$('#ae-resp').html(s._opciones(lista,sel||$('#ae-resp').val())).val(sel||$('#ae-resp').val());
};

var cargarEquipos=function(){
var op=oper(),par={};
if(o.tipo_equipo_id)par.tipo_equipo_id=o.tipo_equipo_id;
par.modo=op==='traslado'?'asignados':'disponibles';
Api.get('api/equipos/select.php',par).then(function(l){
var pre=o.equipo_id;
listaEq=l||[];
$('#ae-equipo').html(s._opciones(l,pre,l.length?'— Seleccione el equipo —':'— Sin equipos disponibles —'));
pintarChecks();
}).catch(function(){$('#ae-equipo').html('<option value="">— Error al cargar —</option>')});
};
var cargarViejos=function(){
if(oper()!=='cambio')return;
var ref=refDestino();
if(!ref){$('#ae-viejo').html('<option value="">— Elija primero el destino —</option>');return}
Api.get('api/equipos/select.php',{modo:'asignados',destino:dest(),ref:ref}).then(function(l){
listaViejos=l||[];
$('#ae-viejo').html(s._opciones(l,null,l.length?'— Seleccione el equipo a retirar —':'— Este destino no tiene equipos —'));
pintarChecks();
}).catch(function(){});
};

/* datos del proyecto / obra elegido: responsable por defecto y ubicacion */
var alElegirProyecto=function(){
var d=dest(),id=d==='proyecto'?$('#ae-proyecto').val():$('#ae-obra').val();
var p=proy.filter(function(x){return +x.id===+id})[0];
if(!p){$('#ae-info').html('');$('#ae-retorno').removeAttr('min max');cargarResp(null);return}
var fi=p.fecha_inicio?String(p.fecha_inicio).slice(0,10):'',ff=p.fecha_fin?String(p.fecha_fin).slice(0,10):'';
var hoy=new Date().toISOString().slice(0,10);
$('#ae-info').html('<i class="bi bi-info-circle"></i> '+(p.cliente?'Cliente: <b>'+esc(p.cliente)+'</b> · ':'')+(p.ubicacion?'Ubicación: <b>'+esc(p.ubicacion)+'</b> · ':'')+
'Plazo: <b>'+(fi?fi.split('-').reverse().join('/'):'—')+' → '+(ff?ff.split('-').reverse().join('/'):'—')+'</b>. El equipo se registra como <b>préstamo</b> y debe volver en la fecha estipulada (no después del fin).');
$('#ae-retorno').attr('min',hoy);
if(ff){$('#ae-retorno').attr('max',ff);var cur=$('#ae-retorno').val();if(!cur||cur>ff||cur<hoy)$('#ae-retorno').val(ff<hoy?hoy:ff)}
cargarResp(p.responsable_id||null);
};

$('input[name=destino]').on('change',function(){zonas();$('#ae-retorno').removeAttr('min max');var d=dest();if(d==='proyecto'||d==='obra')alElegirProyecto();else $('#ae-info').html('');if(d==='area')cargarResp(null);cargarViejos()});
$('#ae-operacion').on('change',function(){zonas();cargarEquipos();cargarViejos()});
$('#ae-personal,#ae-area').on('change',function(){if(dest()==='area')cargarResp(null);cargarViejos()});
$('#ae-resp-todos').on('change',function(){cargarResp(null)});
$('#ae-proyecto,#ae-obra').on('change',function(){alElegirProyecto();cargarViejos()});
s._filtrar('#ae-personal-q','#ae-personal',personal);
$('#ae-equipo,#ae-viejo').on('change',pintarChecks);

$('#ae-operacion').val(op0);
zonas();
if(dest0==='proyecto'||dest0==='obra')alElegirProyecto();else cargarResp(null);
cargarEquipos();
cargarViejos();

$('#btn-ae').on('click',function(){
var d=dest(),op=oper();
var x={
operacion:op,destino:d,
personal_id:d==='persona'?$('#ae-personal').val():'',
area_id:d==='area'?$('#ae-area').val():'',
proyecto_id:d==='proyecto'?$('#ae-proyecto').val():(d==='obra'?$('#ae-obra').val():''),
responsable_id:d==='persona'?'':$('#ae-resp').val(),
destino_detalle:d==='persona'?'':$('#ae-detalle').val(),
equipo_id:$('#ae-equipo').val()||'',
fecha_retorno:(op==='prestamo'||op==='traslado'||d==='proyecto'||d==='obra')?($('#ae-retorno').val()||''):'',
observaciones:$('#ae-obs').val()||'',
solicitud_id:o.solicitud_id||''
};
if(op==='cambio'){
x.equipo_viejo_id=$('#ae-viejo').val()||'';x.cond_dev=$('#ae-cond-dev').val();
x.obs_dev=$('#ae-obs-dev').val()||'';x.motivo=$('#ae-motivo').val();x.motivo_texto=$('#ae-motivo-texto').val()||'';
x.checks_dev=JSON.stringify(ActaPerf.leer('#ae-checks-dev'));x.acc_faltantes_dev=$('#ae-acc-falt-dev').val()||'';
}
x.checks=JSON.stringify(ActaPerf.leer('#ae-checks'));
if(d==='persona'&&!x.personal_id){Toast.warning('Seleccione el colaborador');return}
if(d==='area'&&!x.area_id){Toast.warning('Seleccione el área');return}
if((d==='proyecto'||d==='obra')&&!x.proyecto_id){Toast.warning('Seleccione '+(d==='obra'?'la obra':'el proyecto'));return}
if(d!=='persona'&&!x.responsable_id){Toast.warning('Indique el responsable que recibe los equipos');return}
if(!x.equipo_id){Toast.warning('Seleccione el equipo');return}
if((d==='proyecto'||d==='obra')&&!x.fecha_retorno){Toast.warning('Indique la devolución estipulada del equipo');$('#ae-retorno').trigger('focus');return}
if(op==='prestamo'&&!x.fecha_retorno){Toast.warning('Indique la fecha de retorno del préstamo');return}
if(op==='cambio'&&!x.equipo_viejo_id){Toast.warning('Seleccione el equipo que se retira');return}

$('#btn-ae').prop('disabled',true);
Api.post('api/asignaciones/entregar.php',x).then(function(r){
Modal.cerrar();
if(o.solicitud_id)Router.ir('asignaciones/lista');
else{s.tabla.draw(false);s._stats()}
s._avisoActa(r,'Equipo entregado');
}).catch(function(e){
$('#btn-ae').prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
});
},

/* ---------- aviso con el acta recien generada ---------- */
_avisoActa:function(r,textoBase){
var msg=(r&&r.mensaje)||textoBase;
if(!r||!r.acta_url){Toast.success(msg);return}
Swal.fire({icon:'success',title:'Listo',text:msg,showCancelButton:true,
confirmButtonText:'Ver / imprimir acta',cancelButtonText:'Cerrar',confirmButtonColor:'#2563eb'
}).then(function(x){if(x.isConfirmed)window.open(r.acta_url,'_blank')});
},

/* ---------- devolucion (recibe el equipo y genera el acta segun su tipo) ---------- */
_devolver:function(f){
var s=this,quien=(DESTINOS_AS[f.destino_tipo]||{t:''}).t+': '+(f.destino_nombre||'');
var fam=f.equipo_familia||'otro';
var sinResp=!(+f.responsable_id||+f.personal_id);
ActaPerf.cargar().then(function(P){
var motivos=(P&&P.motivos_devolucion)||{};
var mot0=(f.destino_tipo==='proyecto'||f.destino_tipo==='obra')?'fin_proyecto':(f.tipo==='prestamo'?'fin_prestamo':'fin_uso');
var optMot='';Object.keys(motivos).forEach(function(k){optMot+='<option value="'+k+'"'+(k===mot0?' selected':'')+'>'+esc(motivos[k])+'</option>'});
var html='<form id="form-dev" autocomplete="off"><div class="alert alert-light border py-2 small mb-3"><b>'+esc(f.equipo_codigo)+'</b> · '+esc((f.equipo_tipo||'')+' '+(f.marca||''))+
' <span class="badge text-bg-light border ms-1">'+esc(ActaPerf.nombre(fam))+'</span><br>Devuelve: <b>'+esc(quien)+'</b></div>'+
'<div class="row g-3">'+
'<div class="col-md-6"><label class="form-label">Condición del equipo devuelto *</label><select class="form-select" id="dv-cond">'+
'<option value="bueno">Devuelto — Bueno</option><option value="regular">Devuelto — Regular</option>'+
'<option value="danado">Devuelto — Dañado</option><option value="faltante">NO ENTREGADO (faltante)</option></select></div>'+
'<div class="col-md-6"><label class="form-label">Motivo de la devolución</label><select class="form-select" id="dv-motivo">'+optMot+'</select></div>'+
'<div class="col-12" id="dv-zona-checks"><div class="border rounded p-3"><div class="fw-semibold mb-1"><i class="bi bi-clipboard-check"></i> Inspección y verificación de TI al recibir</div>'+
'<div class="small text-muted mb-2">Marque lo que se verificó: saldrá marcado en el acta de devolución.</div>'+ActaPerf.html('dev',fam)+
'<label class="form-label mt-2 mb-0 small">Accesorios faltantes (opcional)</label><input class="form-control form-control-sm" id="dv-falt" maxlength="200" placeholder="Ej: cargador, mouse"></div></div>'+
'<div class="col-12"><label class="form-label">Observaciones (opcional)</label><textarea class="form-control" id="dv-obs" rows="2" maxlength="250" placeholder="Daños, detalles de la entrega, etc."></textarea></div>'+
(sinResp?'<div class="col-12"><div class="alert alert-warning py-2 small mb-0"><i class="bi bi-exclamation-triangle"></i> Esta asignación no tiene responsable: se recibirá el equipo pero <b>no se generará el acta</b> (podrá generarla después desde Actas).</div></div>':'')+
'</div><div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button type="button" class="btn btn-warning" id="dv-ok"><i class="bi bi-box-arrow-in-left"></i> Recibir equipo</button></div></form>';
Modal.abrir('Recibir equipo '+f.equipo_codigo,html,'modal-lg');
$('#dv-cond').on('change',function(){$('#dv-zona-checks').toggleClass('d-none',$(this).val()==='faltante')});
$('#dv-ok').on('click',function(){
var $b=$(this).prop('disabled',true);
Api.post('api/asignaciones/devolver.php',{
asignacion_id:f.id,condicion:$('#dv-cond').val(),observaciones:$('#dv-obs').val()||'',
motivo:$('#dv-motivo').val(),acc_faltantes:$('#dv-falt').val()||'',
checks:JSON.stringify($('#dv-cond').val()==='faltante'?[]:ActaPerf.leer('#dv-zona-checks'))
}).then(function(res){
Modal.cerrar();s.tabla.draw(false);s._stats();s._avisoActa(res,'Equipo recibido');
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
});
},

/* ---------- editar modalidad / retorno / detalle ---------- */
_editar:function(f){
var s=this,esPy=(f.destino_tipo==='proyecto'||f.destino_tipo==='obra'),esPrest=f.tipo==='prestamo'||esPy;
var html='<form id="form-asig-ed"><div class="alert alert-light border py-2 small mb-3"><b>'+esc(f.equipo_codigo)+'</b> · '+esc((DESTINOS_AS[f.destino_tipo]||{t:''}).t)+': '+esc(f.destino_nombre||'')+'</div>'+
'<div class="row g-3"><div class="col-md-6"><label class="form-label">Modalidad</label><select class="form-select" id="aed-tipo">'+
(esPy?'':'<option value="permanente"'+(esPrest?'':' selected')+'>Permanente</option>')+'<option value="prestamo"'+(esPrest?' selected':'')+'>Préstamo</option></select></div>'+
'<div class="col-md-6" id="aed-zr"><label class="form-label">'+(esPy?'Devolución estipulada *':'Retorno esperado')+'</label><input type="date" class="form-control" id="aed-retorno" value="'+esc(f.fecha_devolucion_esperada||'')+'"></div>'+
(f.destino_tipo!=='persona'?'<div class="col-12"><label class="form-label">Frente / zona / detalle</label><input class="form-control" id="aed-detalle" maxlength="150" value="'+esc(f.destino_detalle||'')+'"></div>':'')+
'</div><div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="button" class="btn btn-primary" id="aed-ok">Guardar</button></div></form>';
Modal.abrir('Editar asignación',html,'modal-md');
var z=function(){$('#aed-zr').toggleClass('d-none',$('#aed-tipo').val()!=='prestamo')};
$('#aed-tipo').on('change',z);z();
$('#aed-ok').on('click',function(){
var d={id:f.id,tipo:$('#aed-tipo').val(),fecha_retorno:$('#aed-retorno').val()||''};
if($('#aed-detalle').length)d.destino_detalle=$('#aed-detalle').val();
Api.post('api/asignaciones/editar.php',d).then(function(r){
Modal.cerrar();s.tabla.draw(false);s._stats();Toast.success((r&&r.message)||'Asignación actualizada');
}).catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
});
},

/* ---------- cancelar (registro erroneo) ---------- */
_cancelar:function(f){
var s=this;
Swal.fire({
title:'Cancelar la asignación de '+f.equipo_codigo+'?',
html:'<span class="small text-muted">Úsese solo si la entrega se registró por error: el equipo vuelve a stock. Para una devolución real use el botón «Recibir».</span>',
input:'text',inputLabel:'Motivo de la cancelación *',inputAttributes:{maxlength:250},
showCancelButton:true,confirmButtonText:'Cancelar asignación',cancelButtonText:'Volver',confirmButtonColor:'#dc2626',
inputValidator:function(v){if(!v||!v.trim())return 'Indique el motivo'}
}).then(function(r){
if(!r.isConfirmed)return;
Api.post('api/asignaciones/eliminar.php',{id:f.id,motivo:r.value}).then(function(res){
s.tabla.draw(false);s._stats();Toast.success((res&&res.message)||'Asignación cancelada');
});
});
},

/* ---------- solicitudes ---------- */
_solicitar:function(){
var s=this;
Promise.all([
Api.get('api/catalogos/select.php',{tipo:'personal'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'tipos'}).catch(function(){return[]}),
Api.get('api/catalogos/select.php',{tipo:'proyectos'}).catch(function(){return[]})
]).then(function(R){
var personal=R[0]||[],tipos=R[1]||[],proy=R[2]||[];
var html='<form id="form-sol" autocomplete="off"><div class="row g-3">'+
'<div class="col-md-6"><label class="form-label">Solicitante *</label><input class="form-control form-control-sm mb-1" id="so-q" placeholder="Buscar...">'+
'<select class="form-select" id="so-sol">'+s._opciones(personal,null)+'</select></div>'+
'<div class="col-md-6"><label class="form-label">Equipo que necesita *</label><select class="form-select" id="so-tipo">'+s._opciones(tipos,null)+'</select>'+
'<label class="form-label mt-2">Cantidad</label><input type="number" class="form-control" id="so-cant" min="1" max="10" value="1"></div>'+
'<div class="col-12"><label class="form-label">¿Para un proyecto u obra? (opcional)</label><select class="form-select" id="so-proy"><option value="">No, es para uso propio del solicitante</option>'+
proy.map(function(p){return '<option value="'+p.id+'">'+(p.tipo==='obra'?'Obra':'Proyecto')+' · '+esc(p.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-12"><label class="form-label">Justificación * <span class="text-muted small">(mínimo 10 caracteres)</span></label><textarea class="form-control" id="so-just" rows="3" maxlength="300"></textarea></div>'+
'</div><div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="button" class="btn btn-primary" id="so-ok">Enviar solicitud</button></div></form>';
Modal.abrir('Solicitar equipo',html,'modal-lg');
s._filtrar('#so-q','#so-sol',personal);
$('#so-ok').on('click',function(){
var d={accion:'crear',solicitante_id:$('#so-sol').val(),tipo_equipo_id:$('#so-tipo').val(),cantidad:$('#so-cant').val()||1,
proyecto_id:$('#so-proy').val()||'',justificacion:($('#so-just').val()||'').trim()};
if(!d.solicitante_id){Toast.warning('Seleccione el solicitante');return}
if(!d.tipo_equipo_id){Toast.warning('Seleccione el tipo de equipo');return}
if(d.justificacion.length<10){Toast.warning('La justificación debe tener al menos 10 caracteres');return}
Api.post('api/asignaciones/solicitudes.php',d).then(function(r){
Modal.cerrar();Toast.success((r&&r.message)||'Solicitud registrada');Router.ir('asignaciones/lista');
}).catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
});
});
},

_accionSol:function(accion,id,codigo,pideObs){
var txt={aprobar:'Aprobar',rechazar:'Rechazar',cancelar:'Cancelar'}[accion];
var cfg={title:txt+' la solicitud '+codigo+'?',showCancelButton:true,confirmButtonText:txt,cancelButtonText:'Volver',
confirmButtonColor:accion==='aprobar'?'#16a34a':'#dc2626'};
if(pideObs){cfg.input='text';cfg.inputLabel='Observaciones (opcional)';cfg.inputAttributes={maxlength:250}}
Swal.fire(cfg).then(function(r){
if(!r.isConfirmed)return;
Api.post('api/asignaciones/solicitudes.php',{accion:accion,id:id,observaciones:r.value||''}).then(function(res){
Toast.success((res&&res.message)||'Listo');Router.ir('asignaciones/lista');
});
});
}
};
App.registrar('asignaciones',Asignaciones);
