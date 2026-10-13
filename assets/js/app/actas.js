/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Actas de entrega, devolución y cambio de equipos
   Vista: views/actas/lista.php
   API:   api/actas/{listar,generar,pendientes,perfiles,firmar,archivo,formatos,encabezado}.php

   El contenido de cada acta depende del TIPO de equipo (computo, movil,
   impresion, red, energia, visual, periferico, telefonia, otro): lo define
   core/ActaPerfiles.php. Las actas se crean solas al entregar / recibir /
   cambiar un equipo en Asignaciones; «Generar acta» documenta los
   movimientos que quedaron sin acta.
   ============================================================ */

/* ---- perfiles por tipo de equipo (lo comparten Asignaciones y Actas) ---- */
var ActaPerf={
_p:null,_pr:null,_n:0,
cargar:function(){
var s=this;
if(s._p)return Promise.resolve(s._p);
if(!s._pr)s._pr=Api.get('api/actas/perfiles.php').then(function(r){s._p=r;return r}).catch(function(){s._pr=null;return null});
return s._pr;
},
olvidar:function(){this._p=null;this._pr=null},
nombre:function(f){
if(f==='mixto')return 'Varios tipos';
var p=this._p;
return p&&p.familias&&p.familias[f]?p.familias[f].nombre:(f||'');
},
checks:function(mov,fam){
var p=this._p;
if(!p||!p.familias)return{};
var f=p.familias[fam]||p.familias.otro;
return(f&&f[mov])||{};
},
/* casillas de verificacion de TI de un tipo de equipo; mov = 'ent' (entregar) | 'dev' (recibir) */
html:function(mov,fam,marcados){
var c=this.checks(mov,fam),h='',s=this;
marcados=marcados||[];
Object.keys(c).forEach(function(k){
var id='apchk'+(++s._n);
h+='<div class="form-check"><input class="form-check-input ap-chk" type="checkbox" id="'+id+'" value="'+esc(k)+'"'+(marcados.indexOf(k)>=0?' checked':'')+'>'+
'<label class="form-check-label small" for="'+id+'">'+esc(c[k])+'</label></div>';
});
return h;
},
leer:function(sel){return $(sel).find('input.ap-chk:checked').map(function(){return this.value}).get()}
};

var ACTA_MOV={
entrega:{t:'Entrega',c:'text-bg-primary',i:'bi-box-arrow-in-right'},
devolucion:{t:'Devolución',c:'text-bg-warning text-dark',i:'bi-box-arrow-in-left'},
cambio:{t:'Cambio',c:'text-bg-info',i:'bi-arrow-left-right'}
};
var ACTA_DEST={persona:'bi-person',area:'bi-people',proyecto:'bi-kanban',obra:'bi-cone-striped'};

function actaFecha(v){
if(!v)return '—';
var m=String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
return m?m[3]+'/'+m[2]+'/'+m[1]+(m[4]?' '+m[4]+':'+m[5]:''):esc(v);
}

var Actas={
tabla:null,

init:function(){
var s=this;
s.tabla=null;
ActaPerf.cargar().then(function(){ s._tabla() });

$('#btn-filtrar-actas').off('click.act').on('click.act',function(){ if(s.tabla)s.tabla.draw() });
$('#af-tipo,#af-familia,#af-firmado').off('change.act').on('change.act',function(){ if(s.tabla)s.tabla.draw() });
$('#btn-limpiar-actas').off('click.act').on('click.act',function(){
$('#af-tipo,#af-familia,#af-firmado').val('');
if(s.tabla){s.tabla.search('').draw()}
});
$('#btn-nueva-acta').off('click.act').on('click.act',function(){ s._generar() });
$('#btn-formatos-acta').off('click.act').on('click.act',function(){ s._formatos() });
$('#btn-branding-acta').off('click.act').on('click.act',function(){ s._encabezado() });
$('#tb-actas').off('click.act').on('click.act','.btn-firmar-acta',function(){
var f=s.tabla.row($(this).closest('tr')).data();
if(f)s._firmar(f);
}).on('click.act','.act-ver-arch',function(){
var f=s.tabla.row($(this).closest('tr')).data();
if(f)s._verArchivo(f);
}).on('click.act','.act-subir-arch',function(){
var f=s.tabla.row($(this).closest('tr')).data();
if(f)s._subirArchivo(f);
});
},

_tabla:function(){
var s=this;
s.tabla=DT.server('#tb-actas','api/actas/listar.php',
function(){return{f_tipo:$('#af-tipo').val()||'',f_familia:$('#af-familia').val()||'',f_firmado:$('#af-firmado').val()||''}},
[
{data:'codigo',className:'nowrap',render:function(v){return '<strong>'+esc(v)+'</strong>'}},
{data:'tipo',render:function(v,t,f){
var m=ACTA_MOV[v]||{t:'Otro formato',c:'text-bg-secondary',i:'bi-file-earmark'};
return '<span class="badge '+m.c+'"><i class="bi '+m.i+'"></i> '+m.t+'</span>'+
(f.familia?'<br><span class="small text-muted">'+esc(ActaPerf.nombre(f.familia))+'</span>':'');
}},
{data:'equipos_resumen',orderable:true,render:function(v){
if(!v)return '<span class="text-muted">—</span>';
return '<span class="small" title="'+esc(v)+'">'+esc(v.length>80?v.slice(0,80)+'…':v)+'</span>';
}},
{data:'destino',render:function(v,t,f){
var i=ACTA_DEST[f.destino_tipo]||'bi-person';
return v?'<i class="bi '+i+' text-muted"></i> '+esc(v)+(f.area&&f.destino_tipo==='persona'?'<br><span class="small text-muted">'+esc(f.area)+'</span>':''):'<span class="text-muted">—</span>';
}},
{data:'responsable',render:function(v){return v?esc(v):'—'}},
{data:'fecha',className:'nowrap',render:function(v){return '<span class="small">'+actaFecha(v)+'</span>'}},
{data:'firmado',className:'nowrap',render:function(v,t,f){
var clip=+f.tiene_archivo===1?'<br><a href="#" class="small act-ver-arch" title="Ver el acta firmada adjunta" onclick="return false"><i class="bi bi-paperclip"></i> Ver archivo</a>':'';
return (+v===1?'<span class="badge text-bg-success"><i class="bi bi-patch-check"></i> Firmada</span>'+(f.firmado_en?'<br><span class="small text-muted">'+actaFecha(f.firmado_en)+'</span>':''):
'<span class="badge text-bg-light border text-muted">Pendiente</span>')+clip;
}},
{data:null,orderable:false,className:'text-center text-nowrap',render:function(v,t,f){
var h='';
if(f.token)h+='<a class="btn btn-sm btn-outline-primary me-1" target="_blank" rel="noopener" title="Ver / imprimir" href="'+BASE_URL+'acta.php?token='+encodeURIComponent(f.token)+'"><i class="bi bi-printer"></i></a>';
if(+f.tiene_archivo===1)h+='<button class="btn btn-sm btn-outline-info me-1 act-ver-arch" title="Ver el acta firmada adjunta"><i class="bi bi-file-earmark-check"></i></button>';
h+='<button class="btn btn-sm btn-outline-dark me-1 act-subir-arch" title="'+(+f.tiene_archivo===1?'Reemplazar o quitar el archivo':'Subir el acta firmada (PDF o foto)')+'"><i class="bi bi-upload"></i></button>';
h+=+f.firmado===1
?'<button class="btn btn-sm btn-outline-secondary btn-firmar-acta" title="Volver a pendiente de firma"><i class="bi bi-arrow-counterclockwise"></i></button>'
:'<button class="btn btn-sm btn-outline-success btn-firmar-acta" title="Marcar como firmada"><i class="bi bi-patch-check"></i></button>';
return h;
}}
],{order:[[5,'desc']]});
},

/* ---------- archivo del acta firmada (PDF o imagen) ---------- */
_verArchivo:function(f){
Visor.abrir('api/actas/archivo.php?id='+encodeURIComponent(f.id),'Acta firmada · '+f.codigo,{pdf:+f.archivo_es_pdf===1,nombre:f.archivo_nombre});
},
_subirArchivo:function(f){
var s=this,tiene=+f.tiene_archivo===1;
var h='<form id="form-act-arch" autocomplete="off"><div class="mb-2"><b>'+esc(f.codigo)+'</b>'+(f.destino?' · '+esc(f.destino):'')+'</div>'+
(tiene?'<div class="alert alert-light border small py-2"><i class="bi bi-file-earmark-check text-success"></i> Archivo actual: <b>'+esc(f.archivo_nombre||'acta')+'</b>'+(f.archivo_kb?' ('+esc(f.archivo_kb)+' KB)':'')+'. Si elige otro, lo reemplaza.</div>':'')+
'<label class="form-label">'+(tiene?'Nuevo archivo':'Acta firmada (PDF o foto)')+'</label>'+
'<input type="file" class="form-control" id="aa-archivo" accept=".pdf,image/png,image/jpeg,image/webp"><div class="form-text">PDF, JPG, PNG o WEBP · máximo 10 MB.</div><div class="invalid-feedback d-block" id="aa-err"></div>'+
(+f.firmado===1?'':'<div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="aa-firmar" checked><label class="form-check-label" for="aa-firmar">Marcar el acta como <b>firmada</b></label></div>')+
'<div class="d-flex justify-content-between mt-3"><div>'+(tiene?'<button type="button" class="btn btn-outline-danger" id="aa-quitar"><i class="bi bi-trash3"></i> Quitar archivo</button>':'')+'</div>'+
'<div><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary" id="aa-ok"><i class="bi bi-upload"></i> '+(tiene?'Reemplazar':'Subir archivo')+'</button></div></div></form>';
Modal.abrir('Acta firmada · '+f.codigo,h,'modal-md');
$('#form-act-arch').off('submit').on('submit',function(e){
e.preventDefault();
var a=$('#aa-archivo')[0].files&&$('#aa-archivo')[0].files[0];
$('#aa-err').text('');
if(!a){$('#aa-err').text('Seleccione el archivo.');return}
if(a.size>10*1024*1024){$('#aa-err').text('El archivo supera los 10 MB.');return}
var fd=new FormData();fd.append('accion','subir');fd.append('id',f.id);fd.append('archivo',a);
fd.append('marcar_firmada',(+f.firmado===1||$('#aa-firmar').is(':checked'))?'1':'0');
$('#aa-ok').prop('disabled',true);
Api.postFiles('api/actas/archivo.php',fd).then(function(r){
Modal.cerrar();s.tabla.draw(false);Toast.success((r&&r.mensaje)||'Archivo guardado');
}).catch(function(er){
$('#aa-ok').prop('disabled',false);
var m=er&&er.cuerpo&&er.cuerpo.errors&&er.cuerpo.errors.archivo;if(m)$('#aa-err').text(m);
});
});
$('#aa-quitar').off('click').on('click',function(){
Swal.fire({title:'¿Quitar el archivo?',text:'El acta '+f.codigo+' seguirá igual, pero sin el archivo adjunto.',icon:'warning',showCancelButton:true,
confirmButtonText:'Sí, quitar',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then(function(x){
if(!x.isConfirmed)return;
Api.post('api/actas/archivo.php',{accion:'quitar',id:f.id}).then(function(r){Modal.cerrar();s.tabla.draw(false);Toast.success((r&&r.mensaje)||'Archivo retirado')}).catch(function(){});
});
});
},

/* ---------- firmada / pendiente ---------- */
_firmar:function(f){
var s=this,nuevo=+f.firmado===1?0:1;
var hacer=function(){
Api.post('api/actas/firmar.php',{id:f.id,firmado:nuevo}).then(function(r){
s.tabla.draw(false);Toast.success((r&&r.mensaje)||'Listo');
});
};
if(nuevo===1)hacer();
else Swal.fire({title:'¿Volver a pendiente de firma?',text:'El acta '+f.codigo+' quedará como no firmada.',showCancelButton:true,
confirmButtonText:'Sí, volver a pendiente',cancelButtonText:'Cancelar'}).then(function(x){if(x.isConfirmed)hacer()});
},

_aviso:function(r){
var msg=(r&&r.mensaje)||'Acta generada';
if(!r||!r.url){Toast.success(msg);return}
Swal.fire({icon:'success',title:'Listo',text:msg,showCancelButton:true,confirmButtonText:'Ver / imprimir acta',
cancelButtonText:'Cerrar',confirmButtonColor:'#2563eb'}).then(function(x){if(x.isConfirmed)window.open(r.url,'_blank')});
},

/* ============================================================
   GENERAR ACTA de un movimiento registrado que no tiene acta
   ============================================================ */
_generar:function(){
var s=this;
ActaPerf.cargar().then(function(P){
if(!P){Toast.error('No se pudieron cargar los tipos de equipo.');return}

var st={modo:'entrega',a:[],b:[],sel:{},nuevo:0,viejo:0,personal:null,
extra:{motivo:'',motivo_texto:'',responsable_id:'',observaciones:''}};
var AYUDA={
entrega:'Equipos asignados que no tienen acta de entrega. Puede marcar varios del mismo destino para una sola acta.',
devolucion:'Equipos ya recibidos (botón «Recibir» de Asignaciones) que no tienen acta de devolución. Puede marcar varios del mismo destino.',
cambio:'Elija el equipo nuevo que se entregó (izquierda) y el equipo que se retiró (derecha), ambos del mismo destino.'
};

var radio=function(v,ic,txt){
return '<input type="radio" class="btn-check" name="gn-modo" id="gn-m-'+v+'" value="'+v+'"'+(v==='entrega'?' checked':'')+'>'+
'<label class="btn btn-outline-primary btn-sm" for="gn-m-'+v+'"><i class="bi '+ic+'"></i> '+txt+'</label>';
};
var html='<form id="form-gn" autocomplete="off"><div class="btn-group w-100 mb-2" role="group">'+
radio('entrega','bi-box-arrow-in-right','Acta de entrega')+radio('devolucion','bi-box-arrow-in-left','Acta de devolución')+radio('cambio','bi-arrow-left-right','Acta de cambio')+'</div>'+
'<div class="small text-muted mb-2" id="gn-ayuda"></div>'+
'<input class="form-control form-control-sm mb-2" id="gn-q" placeholder="Buscar por equipo, marca, persona, área o proyecto...">'+
'<div class="row g-2" id="gn-listas"></div>'+
'<div id="gn-detalle" class="mt-3"></div>'+
'<div class="row g-3 mt-1" id="gn-extra"></div>'+
'<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button type="button" class="btn btn-primary" id="gn-ok" disabled><i class="bi bi-file-earmark-check"></i> Generar acta</button></div></form>';
Modal.abrir('Generar acta de un movimiento registrado',html,'modal-xl');

var filaHtml=function(r,tipo,name){
var fecha=r.estado==='devuelta'?(r.fecha_devolucion_real||r.fecha_entrega):r.fecha_entrega;
return '<label class="list-group-item list-group-item-action d-flex gap-2 align-items-start py-2 gn-fila" data-id="'+r.id+'" data-clave="'+esc(r.destino_clave)+'">'+
'<input class="form-check-input mt-1 gn-sel" type="'+tipo+'" name="'+name+'" value="'+r.id+'">'+
'<div class="flex-grow-1"><div><b>'+esc(r.codigo)+'</b> <span class="text-muted small">'+esc((r.equipo_tipo||'')+' '+(r.marca||'')+' '+(r.modelo||''))+'</span> '+
'<span class="badge text-bg-light border">'+esc(ActaPerf.nombre(r.familia))+'</span></div>'+
'<div class="small text-muted">'+esc(r.destino_texto)+(r.responsable?' · resp. '+esc(r.responsable):'')+' · '+actaFecha(fecha).slice(0,10)+'</div></div></label>';
};
var pintarLista=function(rows,sel,tipo,name,titulo){
var h='<div class="'+(st.modo==='cambio'?'col-md-6':'col-12')+'">'+(titulo?'<div class="fw-semibold small mb-1">'+titulo+'</div>':'')+
'<div class="list-group" id="'+sel+'" style="max-height:230px;overflow:auto">';
if(!rows.length)h+='<div class="list-group-item text-muted small">No hay movimientos sin acta'+($('#gn-q').val()?' con esa búsqueda':'')+'.</div>';
rows.forEach(function(r){h+=filaHtml(r,tipo,name)});
return h+'</div></div>';
};
var fila=function(id){
return(st.a.concat(st.b)).filter(function(r){return +r.id===+id})[0];
};

var cargar=function(){
var q=$('#gn-q').val()||'';
var pedir=function(modo){return Api.get('api/actas/pendientes.php',{modo:modo,q:q}).catch(function(){return[]})};
$('#gn-ayuda').text(AYUDA[st.modo]);
var fin=function(){
var h=st.modo==='cambio'
?pintarLista(st.a,'gn-lista-a','radio','gn-nuevo','Equipo nuevo entregado')+pintarLista(st.b,'gn-lista-b','radio','gn-viejo','Equipo que se retira (devuelto)')
:pintarLista(st.a,'gn-lista-a','checkbox','gn-chk','');
$('#gn-listas').html(h);
marcar();
};
if(st.modo==='cambio')Promise.all([pedir('entrega'),pedir('devolucion')]).then(function(R){st.a=R[0]||[];st.b=R[1]||[];fin()});
else pedir(st.modo).then(function(l){st.a=l||[];st.b=[];fin()});
};

/* devuelve las filas elegidas, en orden */
var elegidos=function(){
if(st.modo==='cambio')return[fila(st.nuevo),fila(st.viejo)].filter(Boolean);
return Object.keys(st.sel).filter(function(k){return st.sel[k]}).map(fila).filter(Boolean);
};

/* vuelve a marcar lo elegido y bloquea lo que sea de otro destino */
var marcar=function(){
var rows=elegidos(),clave=rows.length?rows[0].destino_clave:'';
if(st.modo==='cambio'){
$('#gn-lista-a input').each(function(){$(this).prop('checked',+this.value===+st.nuevo)});
$('#gn-lista-b input').each(function(){$(this).prop('checked',+this.value===+st.viejo)});
var k=(fila(st.nuevo)||fila(st.viejo)||{}).destino_clave||'';
$('#gn-listas .gn-fila').each(function(){$(this).toggleClass('d-none',!!k&&String($(this).data('clave'))!==String(k)&&!$(this).find('input').prop('checked'))});
}else{
$('#gn-lista-a .gn-fila').each(function(){
var $i=$(this).find('input'),on=!!st.sel[this.getAttribute('data-id')];
$i.prop('checked',on);
var otro=!!clave&&String($(this).data('clave'))!==String(clave);
$i.prop('disabled',otro&&!on);$(this).toggleClass('opacity-50',otro&&!on);
});
}
detalle();
};

/* tarjeta por equipo elegido: casillas de TI segun su tipo, condicion, etc. */
var detalle=function(){
var rows=elegidos(),ids={};
rows.forEach(function(r){ids[r.id]=1});
$('#gn-detalle .gn-card').each(function(){if(!ids[$(this).data('id')])$(this).remove()});
rows.forEach(function(r){
if($('#gn-detalle .gn-card[data-id="'+r.id+'"]').length)return;
var dev=(st.modo==='devolucion')||(st.modo==='cambio'&&r.estado==='devuelta');
var rol=dev?'dev':'ent';
var c='<div class="border rounded p-3 mb-2 gn-card" data-id="'+r.id+'" data-rol="'+rol+'">'+
'<div class="fw-semibold mb-2"><i class="bi '+(dev?'bi-box-arrow-in-left':'bi-box-arrow-in-right')+'"></i> '+
(st.modo==='cambio'?(dev?'Equipo que se retira: ':'Equipo nuevo: '):'')+esc(r.codigo)+' · '+esc((r.equipo_tipo||'')+' '+(r.marca||''))+
' <span class="badge text-bg-light border">'+esc(ActaPerf.nombre(r.familia))+'</span></div>';
if(dev){
var cond=r.condicion_devolucion||'bueno';
c+='<div class="row g-2 mb-2"><div class="col-md-4"><label class="form-label small mb-0">Condición al recibir</label><select class="form-select form-select-sm gn-cond">'+
['bueno','regular','danado','faltante'].map(function(k){return '<option value="'+k+'"'+(k===cond?' selected':'')+'>'+esc(P.condiciones[k]||k)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-4"><label class="form-label small mb-0">Accesorios faltantes</label><input class="form-control form-control-sm gn-falt" maxlength="200"></div>'+
'<div class="col-md-4"><label class="form-label small mb-0">Observaciones del equipo</label><input class="form-control form-control-sm gn-obs" maxlength="250" value="'+esc(r.obs_devolucion||'')+'"></div></div>';
}
c+='<div class="small text-muted mb-1">'+(dev?'Inspección y verificación de TI al recibir:':'Verificación de TI al entregar:')+'</div><div class="gn-checks">'+ActaPerf.html(rol,r.familia)+'</div></div>';
$('#gn-detalle').append(c);
});
extras();
$('#gn-ok').prop('disabled',!(st.modo==='cambio'?(rows.length===2):rows.length>0));
};

var extras=function(){
var rows=elegidos(),h='';
var x=st.extra;
if(st.modo!=='entrega'){
var mot=st.modo==='cambio'?P.motivos_cambio:P.motivos_devolucion;
var r0=rows[0]||{};
var def=st.modo==='cambio'?'renovacion':((r0.destino_tipo==='proyecto'||r0.destino_tipo==='obra')?'fin_proyecto':(r0.modalidad==='prestamo'?'fin_prestamo':'fin_uso'));
var cur=x.motivo||def;
h+='<div class="col-md-6"><label class="form-label">Motivo '+(st.modo==='cambio'?'del cambio':'de la devolución')+'</label><select class="form-select form-select-sm" id="gn-motivo">'+
Object.keys(mot).map(function(k){return '<option value="'+k+'"'+(k===cur?' selected':'')+'>'+esc(mot[k])+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-6"><label class="form-label">Detalle del motivo (opcional)</label><input class="form-control form-control-sm" id="gn-motivo-texto" maxlength="200" value="'+esc(x.motivo_texto)+'"></div>';
}
if(rows.some(function(r){return +r.sin_responsable===1})){
h+='<div class="col-md-6"><label class="form-label">Responsable que firma el acta *</label><select class="form-select form-select-sm" id="gn-resp"><option value="">— Seleccione —</option></select>'+
'<div class="form-text">Estas asignaciones antiguas no tienen responsable registrado.</div></div>';
}
h+='<div class="col-12"><label class="form-label">Observaciones del acta (opcional)</label><textarea class="form-control form-control-sm" id="gn-obs" rows="2" maxlength="400">'+esc(x.observaciones)+'</textarea></div>';
$('#gn-extra').html(h);
if($('#gn-resp').length){
var pintar=function(l){
$('#gn-resp').html('<option value="">— Seleccione —</option>'+(l||[]).map(function(p){return '<option value="'+p.id+'"'+(+x.responsable_id===+p.id?' selected':'')+'>'+esc(p.texto)+'</option>'}).join(''));
};
if(st.personal)pintar(st.personal);
else Api.get('api/catalogos/select.php',{tipo:'personal'}).then(function(l){st.personal=l||[];pintar(st.personal)}).catch(function(){});
}
};

/* eventos */
$('input[name=gn-modo]').on('change',function(){
st.modo=$('input[name=gn-modo]:checked').val();
st.sel={};st.nuevo=0;st.viejo=0;st.extra.motivo='';
$('#gn-detalle').empty();
cargar();
});
var t=null;
$('#gn-q').on('input',function(){clearTimeout(t);t=setTimeout(cargar,300)});
$('#gn-listas').on('change','.gn-sel',function(){
var id=+this.value;
if(st.modo==='cambio'){
if(this.name==='gn-nuevo')st.nuevo=id;else st.viejo=id;
st.extra.motivo='';
}else{
if(this.checked&&Object.keys(st.sel).filter(function(k){return st.sel[k]}).length>=10){this.checked=false;Toast.warning('Máximo 10 equipos por acta');return}
st.sel[id]=this.checked;
}
marcar();
});
$('#gn-extra').on('input change','#gn-motivo,#gn-motivo-texto,#gn-resp,#gn-obs',function(){
st.extra.motivo=$('#gn-motivo').val()||st.extra.motivo;
st.extra.motivo_texto=$('#gn-motivo-texto').val()||'';
st.extra.responsable_id=$('#gn-resp').val()||'';
st.extra.observaciones=$('#gn-obs').val()||'';
});
$('#gn-ok').on('click',function(){
var rows=elegidos();
var d={tipo:st.modo,observaciones:$('#gn-obs').val()||'',motivo:$('#gn-motivo').val()||'',motivo_texto:$('#gn-motivo-texto').val()||'',responsable_id:$('#gn-resp').val()||''};
if(rows.some(function(r){return +r.sin_responsable===1})&&!d.responsable_id){Toast.warning('Seleccione el responsable que firma el acta');return}
var det={};
$('#gn-detalle .gn-card').each(function(){
var $c=$(this),id=$c.data('id');
det[id]={checks:$c.data('rol')==='dev'&&$c.find('.gn-cond').val()==='faltante'?[]:ActaPerf.leer($c),
condicion:$c.find('.gn-cond').val()||'',obs:$c.find('.gn-obs').val()||'',faltantes:$c.find('.gn-falt').val()||''};
});
d.detalle=JSON.stringify(det);
if(st.modo==='cambio'){d.nuevo_id=st.nuevo;d.viejo_id=st.viejo}
else d.asignaciones=JSON.stringify(rows.map(function(r){return r.id}));
var $b=$(this).prop('disabled',true);
Api.post('api/actas/generar.php',d).then(function(r){
Modal.cerrar();s.tabla.draw(false);s._aviso(r);
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
cargar();
});
},

/* ============================================================
   CODIGOS DE FORMATO por tipo de equipo
   ============================================================ */
_formatos:function(){
ActaPerf.olvidar();
ActaPerf.cargar().then(function(P){
if(!P){Toast.error('No se pudieron cargar los formatos.');return}
var movs=Object.keys(P.movimientos),puede=!!P.puede_configurar;
var h='<div class="small text-muted mb-2">Código que lleva cada acta en su encabezado (por ejemplo <code>TI-FOR-003</code>). '+
'Deje vacío para usar el formato base de ese movimiento.</div><div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><thead><tr><th>Tipo de equipo</th>'+
movs.map(function(m){return '<th>'+esc(P.movimientos[m])+'</th>'}).join('')+'</tr></thead><tbody>';
Object.keys(P.familias).forEach(function(f){
h+='<tr><td class="small fw-semibold">'+esc(P.familias[f].nombre)+'</td>';
movs.forEach(function(m){
var fm=P.formatos[m][f];
h+='<td><input class="form-control form-control-sm gf" data-mov="'+m+'" data-fam="'+f+'" maxlength="30" value="'+esc(fm.propio)+'" placeholder="'+esc(fm.propio?'':fm.efectivo)+'"'+(puede?'':' disabled')+'></td>';
});
h+='</tr>';
});
h+='</tbody></table></div>'+(puede?'':'<div class="form-text mt-2">Solo quien administra la configuración puede cambiar los códigos.</div>')+
'<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>'+
(puede?' <button type="button" class="btn btn-primary" id="gf-ok">Guardar</button>':'')+'</div>';
Modal.abrir('Códigos de formato de las actas',h,'modal-xl');
$('#gf-ok').on('click',function(){
var d={};
$('.gf').each(function(){var m=$(this).data('mov'),f=$(this).data('fam');(d[m]=d[m]||{})[f]=$(this).val().trim()});
Api.post('api/actas/formatos.php',{formatos:JSON.stringify(d)}).then(function(r){
ActaPerf.olvidar();Modal.cerrar();Toast.success((r&&r.mensaje)||'Guardado');
}).catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
});
});
},

/* ============================================================
   LOGO Y ENCABEZADO de las actas
   ============================================================ */
_encabezado:function(){
Api.get('api/actas/encabezado.php').then(function(r){
var v=r.valores||{};
var campo=function(k,l,col,ph){return '<div class="'+col+'"><label class="form-label small mb-0">'+l+'</label><input class="form-control form-control-sm" data-k="'+k+'" value="'+esc(v[k]||'')+'"'+(ph?' placeholder="'+esc(ph)+'"':'')+'></div>'};
var h='<form id="form-enc" autocomplete="off"><div class="row g-3">'+
campo('empresa_nombre','Nombre de la empresa','col-md-8','Nombre de su empresa')+campo('empresa_ruc','RUC','col-md-4','')+
campo('empresa_telefonos','Teléfonos','col-md-6','')+campo('empresa_correo','Correo','col-md-6','')+
campo('empresa_direccion','Dirección de la oficina','col-md-12','')+
campo('empresa_web','Sitio web','col-md-6','')+campo('empresa_ciudad','Ciudad (fecha de firma)','col-md-3','Lima')+
'<div class="col-md-3"><label class="form-label small mb-0">Color de marca</label><input type="color" class="form-control form-control-sm form-control-color w-100" data-k="acta_color" value="'+esc(v.acta_color||'#1e2732')+'"></div>'+
'<div class="col-12"><div class="border rounded p-3"><div class="fw-semibold small mb-2"><i class="bi bi-image"></i> Logo (PNG o JPG, máximo 3 MB)</div>'+
'<div class="d-flex align-items-center gap-3 flex-wrap"><div id="enc-logo" style="min-width:90px;min-height:50px" class="border rounded p-1 bg-light text-center small text-muted">'+(r.logo?'':'Sin logo')+'</div>'+
'<input type="file" id="enc-archivo" class="form-control form-control-sm" accept="image/png,image/jpeg" style="max-width:260px">'+
'<button type="button" class="btn btn-sm btn-outline-primary" id="enc-subir">Subir logo</button>'+
'<button type="button" class="btn btn-sm btn-outline-danger" id="enc-quitar">Quitar</button></div></div></div>'+
'</div><div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> '+
'<button type="button" class="btn btn-primary" id="enc-ok">Guardar datos</button></div></form>';
Modal.abrir('Logo y encabezado de las actas',h,'modal-lg');
var verLogo=function(){$('#enc-logo').html('<img src="'+BASE_URL+'api/configuracion/logo.php?accion=ver&t='+Date.now()+'" alt="Logo" style="max-height:60px;max-width:180px" onerror="this.parentNode.textContent=\'Sin logo\'">')};
if(r.logo)verLogo();
$('#enc-subir').on('click',function(){
var f=$('#enc-archivo')[0].files[0];
if(!f){Toast.warning('Elija la imagen del logo');return}
var fd=new FormData();fd.append('accion','subir');fd.append('logo',f);
Api.postFiles('api/configuracion/logo.php',fd).then(function(){Toast.success('Logo actualizado');verLogo()}).catch(function(){});
});
$('#enc-quitar').on('click',function(){
Api.post('api/configuracion/logo.php',{accion:'quitar'}).then(function(){Toast.success('Logo eliminado');$('#enc-logo').text('Sin logo')}).catch(function(){});
});
$('#enc-ok').on('click',function(){
var d={};
$('#form-enc [data-k]').each(function(){d[$(this).data('k')]=$(this).val()});
Api.post('api/actas/encabezado.php',d).then(function(x){Modal.cerrar();Toast.success((x&&x.mensaje)||'Guardado')})
.catch(function(e){if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0])});
});
});
}
};
App.registrar('actas',Actas);
