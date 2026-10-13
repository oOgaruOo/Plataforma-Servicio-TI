/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var ESTADOS_P={pre_ingreso:{t:'Pre-ingreso',c:'text-bg-secondary'},activo:{t:'Activo',c:'text-bg-success'},
cese_programado:{t:'Cese prog.',c:'text-bg-warning'},en_proceso_cese:{t:'En proc.cese',c:'text-bg-warning text-dark'},cesado:{t:'Cesado',c:'text-bg-dark'}};
var Personal={
tabla:null,
_perm:{editar:false,eliminar:false},
init:function(){
var $t=$('#tb-personal');
this._perm={editar:!!$t.data('puedeEditar'),eliminar:!!$t.data('puedeEliminar')};
this.tabla=DT.server('#tb-personal','api/personal/listar.php',
function(){return{f_estado:$('#pf-estado').val()||'',f_area:$('#pf-area').val()||0,f_campo:$('#pf-campo').val()||'nombres',f_texto:$('#pf-texto').val()||''}},
[
{data:null,orderable:false,className:'text-center',render:function(v,t,f){
return '<input type="checkbox" class="form-check-input chk-per" data-id="'+f.id+'"'+(Personal.sel[f.id]?' checked':'')+'>'}},
{data:'codigo_usuario',render:function(v){return v?'<span class="badge bg-light text-dark border font-monospace">'+esc(v)+'</span>':'<span class="text-muted">—</span>'}},
{data:'dni'},
{data:'apellidos'},
{data:'nombres',render:function(v,t,f){return esc(v)+(+f.cumple_hoy?' <span title="Hoy es su cumpleaños">🎂</span>':'')}},
{data:'area',render:function(v){return v?esc(v):'—'}},
{data:'cargo'},
{data:'fecha_ingreso',render:function(v){return v?esc(v):'—'}},
{data:'tipo_personal',render:function(v){return v?'<span class="badge text-bg-light border">'+esc(v.charAt(0).toUpperCase()+v.slice(1))+'</span>':'—'}},
{data:'estado',render:function(v){var e=ESTADOS_P[v]||{t:v,c:'text-bg-light'};return '<span class="badge '+e.c+'">'+e.t+'</span>'}},
{data:'check_ok',render:function(v,t,f){var tot=parseInt(f.check_total,10)||0,ok=parseInt(v,10)||0;if(!tot)return '<span class="text-muted">—</span>';var pct=Math.round(ok*100/tot);return '<div class="progress" style="height:16px;min-width:80px"><div class="progress-bar'+(pct===100?' bg-success':'')+'" style="width:'+pct+'%">'+ok+'/'+tot+'</div></div>'}},
{data:null,orderable:false,className:'text-center text-nowrap',
render:function(v,t,f){return Personal._acciones(f)}}
],{order:[[1,'asc']]});
this.sel={};
this.tabla.on('draw',function(){Personal._syncSel()});
// casillas por fila + «marcar todos» de la página; la selección sobrevive al paginar y al filtrar
 $('#tb-personal').on('change','.chk-per',function(){
var id=+$(this).data('id'),f=Personal.tabla.row($(this).closest('tr')).data();
if(this.checked&&f)Personal.sel[id]={n:(f.apellidos||'')+' '+(f.nombres||''),cod:f.codigo_usuario||'',dni:f.dni||'',area:f.area||'',cargo:f.cargo||'',correo:f.correo_corporativo||f.correo_personal||'',locker:f.locker||''};
else delete Personal.sel[id];
Personal._syncSel();
});
 $('#tb-personal').on('change','.chk-todos',function(){
var on=this.checked;
 $('#tb-personal .chk-per').each(function(){ $(this).prop('checked',on).trigger('change')});
});
 $('#btn-sel-acciones').closest('.btn-group').on('click','[data-sel]',function(e){e.preventDefault();Personal._accionSel($(this).data('sel'))});
 $('#btn-filtrar-personal').on('click',function(){Personal.tabla.draw()});
 $('#btn-limpiar-personal').on('click',function(){
 $('#pf-estado,#pf-area,#pf-texto').val('');$('#pf-campo').val('nombres');Personal.tabla.draw()});
 $('#pf-estado,#pf-area,#pf-campo').on('change',function(){Personal.tabla.draw()});
 $('#pf-texto').on('keyup',function(e){if(e.key==='Enter')Personal.tabla.draw()});
 $('#btn-nuevo-personal').on('click',function(){Personal._form(null)});
 $('#btn-cumples').on('click',function(){Personal._cumples(30)});
 $('#btn-etiquetas-personal').on('click',function(){Personal._etiquetas()});
 $('#tb-personal').on('click','.btn-ver,.btn-editar,.btn-eliminar,.btn-etq',function(){
var f=Personal.tabla.row($(this).closest('tr')).data();
if(!f)return;
if($(this).hasClass('btn-etq')){window.open(BASE_URL+'views/personal/etiquetas.php?ids='+f.id+'&objeto='+$(this).data('obj'),'_blank');return}
if($(this).hasClass('btn-ver'))Personal._ver(f.id);
else if($(this).hasClass('btn-editar'))Personal._form(f);
else Personal._eliminar(f);
});
},
/* contador del boton «Seleccionados» y casilla de «todos» de la pagina */
_syncSel:function(){
var n=Object.keys(this.sel).length;
 $('#btn-sel-acciones').prop('disabled',n===0).html('<i class="bi bi-check2-square"></i> Seleccionados ('+n+')');
var $c=$('#tb-personal .chk-per'),marc=$c.filter(':checked').length;
 $('#tb-personal .chk-todos').prop('checked',$c.length>0&&marc===$c.length);
},
/* acciones sobre las personas marcadas */
_accionSel:function(a){
var ids=Object.keys(this.sel),lista=ids.map(function(i){return Personal.sel[i]});
if(!ids.length){Toast.warning('Marque al menos una persona');return}
if(a==='limpiar'){this.sel={}; $('#tb-personal .chk-per,#tb-personal .chk-todos').prop('checked',false);this._syncSel();return}
if(a==='etq-personal'||a==='etq-locker'){
if(ids.length>300){Toast.warning('Máximo 300 etiquetas por lote: marque menos personas');return}
var obj=a==='etq-locker'?'locker':'personal';
if(obj==='locker'&&!lista.some(function(p){return p.locker})){Toast.warning('Ninguna de las personas marcadas tiene locker asignado');return}
if(!window.open(BASE_URL+'views/personal/etiquetas.php?ids='+ids.join(',')+'&objeto='+obj,'_blank'))Toast.warning('El navegador bloqueó la ventana: permita las ventanas emergentes para este sitio');
return}
if(a==='correos'){
var cs=lista.map(function(p){return p.correo}).filter(function(c){return c});
if(!cs.length){Toast.warning('Las personas marcadas no tienen correo registrado');return}
var txt=cs.join('; ');
var fin=function(){Toast.success(cs.length+' correo(s) copiado(s)'+(cs.length<lista.length?' ('+(lista.length-cs.length)+' sin correo)':''))};
if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(txt).then(fin,function(){Personal._copiarTxt(txt);fin()});
else{this._copiarTxt(txt);fin()}
return}
if(a==='csv'){
var q=function(v){return '"'+String(v==null?'':v).replace(/"/g,'""')+'"'};
var f=['Codigo;DNI;Apellidos y nombres;Area;Cargo;Correo;Locker'].concat(lista.map(function(p){return [p.cod,p.dni,p.n,p.area,p.cargo,p.correo,p.locker].map(q).join(';')}));
var b=new Blob(['\ufeff'+f.join('\r\n')],{type:'text/csv;charset=utf-8'});
var u=URL.createObjectURL(b),l=document.createElement('a');
l.href=u;l.download='personal-seleccion.csv';document.body.appendChild(l);l.click();document.body.removeChild(l);setTimeout(function(){URL.revokeObjectURL(u)},1000);
}
},
_copiarTxt:function(t){
var x=document.createElement('textarea');x.value=t;x.style.position='fixed';x.style.opacity='0';document.body.appendChild(x);x.select();
try{document.execCommand('copy')}catch(e){}
document.body.removeChild(x);
},
// Botones de cada fila: Ver siempre; Editar y Eliminar segun permiso y estado
_acciones:function(f){
var P=this._perm;
var h='<button class="btn btn-sm btn-outline-primary btn-ver" title="Ver ficha"><i class="bi bi-eye"></i></button>';
h+=' <button class="btn btn-sm btn-outline-dark btn-etq" data-obj="personal" title="Etiqueta del personal"><i class="bi bi-upc-scan"></i></button>';
if(f.locker)h+=' <button class="btn btn-sm btn-outline-dark btn-etq" data-obj="locker" title="Etiqueta del locker '+esc(f.locker)+'"><i class="bi bi-lock"></i></button>';
if(P.editar){
h+=f.estado==='cesado'
?' <span title="Registro histórico: una persona cesada no se edita"><button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-pencil"></i></button></span>'
:' <button class="btn btn-sm btn-outline-secondary btn-editar" title="Editar"><i class="bi bi-pencil"></i></button>';
}
if(P.eliminar){
h+=f.estado==='pre_ingreso'
?' <button class="btn btn-sm btn-outline-danger btn-eliminar" title="Eliminar (solo pre-ingreso)"><i class="bi bi-trash"></i></button>'
:' <span title="Solo se elimina personal en pre-ingreso. El resto pasa a cesado (histórico)"><button class="btn btn-sm btn-outline-danger" disabled><i class="bi bi-trash"></i></button></span>';
}
return h;
},
// Imprimir etiquetas de varias personas (por area) o de sus lockers
_etiquetas:function(){
Api.get('api/catalogos/select.php',{tipo:'areas'}).catch(function(){return[]}).then(function(areas){
areas=areas||[];
var h='<form id="form-etq-per" onsubmit="return false"><div class="row g-3">'+
'<div class="col-md-6"><label class="form-label" for="etq-obj">Qué imprimir</label><select class="form-select" id="etq-obj">'+
'<option value="personal">Etiquetas del personal (con su código)</option><option value="locker">Etiquetas de lockers</option></select></div>'+
'<div class="col-md-6"><label class="form-label" for="etq-area">Área</label><select class="form-select" id="etq-area"><option value="">Todas las áreas</option>'+
areas.map(function(a){return '<option value="'+a.id+'">'+esc(a.texto)+(a.codigo?' ('+esc(a.codigo)+')':'')+'</option>'}).join('')+'</select></div>'+
'<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="etq-act" checked><label class="form-check-label" for="etq-act">Solo personal vigente (sin cesados)</label></div>'+
'<div class="form-text">Para una sola persona use el botón de etiqueta de su fila. Las plantillas se diseñan en Inventario › Diseñador de etiquetas.</div></div>'+
'</div><div class="text-end mt-4"><button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>'+
'<button type="button" class="btn btn-primary" id="etq-abrir"><i class="bi bi-printer"></i> Abrir para imprimir</button></div></form>';
Modal.abrir('Imprimir etiquetas',h,'modal-md');
$('#etq-abrir').on('click',function(){
var q='objeto='+encodeURIComponent($('#etq-obj').val())+'&area='+encodeURIComponent($('#etq-area').val()||0)+'&activos='+($('#etq-act').is(':checked')?1:0);
window.open(BASE_URL+'views/personal/etiquetas.php?'+q,'_blank');Modal.cerrar();
});
});
},
_hoyISO:function(){
var d=new Date();
return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2);
},
// Alta y edicion. En edicion se carga la ficha completa (la lista no trae todos los campos)
_form:function(fila){
var esNuevo=!fila;
Promise.all([
Api.get('api/catalogos/select.php',{tipo:'areas'}).catch(function(){return[]}),
esNuevo?Promise.resolve(null):Api.get('api/personal/detalle.php',{id:fila.id})
]).then(function(R){
var areas=R[0]||[],p=R[1];
var val=function(c){return esNuevo?'':(p[c]==null?'':p[c])};
var aid=+val('area_id');
// si el area de la persona ya no esta activa, se conserva igual en la lista
if(aid&&!areas.some(function(a){return +a.id===aid})){areas=[{id:aid,texto:p.area||('Área #'+aid)}].concat(areas)}
var tipo=esNuevo?'empleado':(p.tipo_personal||'empleado');
var cta0=esNuevo?null:((p.cuentas||[])[0]||null);
var html='<form id="form-personal" data-id="'+(esNuevo?0:p.id)+'" data-area0="'+(esNuevo?0:aid)+'" onsubmit="return false"><div class="row">'+
'<div class="col-12 mb-3"><div class="alert alert-light border py-2 mb-0 small" id="fp-codigo-info"><i class="bi bi-upc-scan text-primary"></i> <span id="fp-codigo-txt"></span></div></div>'+
'<div class="col-12 mb-3">'+Foto.panel({id:esNuevo?0:p.id,foto:esNuevo?null:(p.foto||null),nombre:esNuevo?'':(p.nombres+' '+p.apellidos),endpoint:'api/personal/foto.php',titulo:'Foto del carnet',
nota:(!esNuevo&&!p.foto&&cta0&&cta0.foto)?'Usa la foto de su cuenta de usuario. Si sube una aquí, el carnet usará esta. PNG o JPG, máx. 5 MB.':'PNG o JPG, máx. 5 MB. Se recorta en cuadrado. Sale en el carnet.'})+'</div>'+
'<div class="col-md-3 mb-3"><label>DNI *</label>'+Documento.campoHtml('fp-dni',val('dni'),8)+'</div>'+
'<div class="col-md-3 mb-3"><label>Nombres *</label><input class="form-control" id="fp-nombres" maxlength="80" value="'+esc(val('nombres'))+'"></div>'+
'<div class="col-md-3 mb-3"><label>Apellido paterno *</label><input class="form-control" id="fp-ap-pat" maxlength="39" value="'+esc(val('apellido_paterno'))+'"></div>'+
'<div class="col-md-3 mb-3"><label>Apellido materno</label><input class="form-control" id="fp-ap-mat" maxlength="39" value="'+esc(val('apellido_materno'))+'"></div>'+
'<div class="col-md-6 mb-3"><label>Correo personal</label><input type="email" class="form-control" id="fp-correo-p" value="'+esc(val('correo_personal'))+'"></div>'+
'<div class="col-md-6 mb-3"><label>Correo corporativo</label><input type="email" class="form-control" id="fp-correo-c" value="'+esc(val('correo_corporativo'))+'"></div>'+
'<div class="col-md-4 mb-3"><label>Telefono</label><input class="form-control" id="fp-telefono" value="'+esc(val('telefono'))+'" maxlength="20"></div>'+
'<div class="col-md-4 mb-3"><label>Area *</label><select class="form-select" id="fp-area">'+
(esNuevo?'<option value="">— Seleccione —</option>':'')+
areas.map(function(a){return '<option value="'+a.id+'"'+(+aid===+a.id?' selected':'')+'>'+esc(a.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-4 mb-3"><label>Cargo *</label><input class="form-control" id="fp-cargo" value="'+esc(val('cargo'))+'"></div>'+
'<div class="col-md-4 mb-3"><label>Fecha ingreso</label><input type="date" class="form-control" id="fp-fingreso" value="'+esc(val('fecha_ingreso'))+'"></div>'+
'<div class="col-md-4 mb-3"><label><i class="bi bi-cake2"></i> Fecha de cumpleaños (nacimiento)'+(esNuevo?' *':'')+'</label>'+
'<input type="date" class="form-control" id="fp-fnac" max="'+Personal._hoyISO()+'" value="'+esc(val('fecha_nacimiento'))+'">'+
'<div class="form-text">El sistema avisa el día de su cumpleaños.</div></div>'+
'<div class="col-md-4 mb-3"><label>Genero</label><select class="form-select" id="fp-genero">'+
'<option value="">—</option><option value="masculino"'+(val('genero')==='masculino'?' selected':'')+'>Masculino</option>'+
'<option value="femenino"'+(val('genero')==='femenino'?' selected':'')+'>Femenino</option></select></div>'+
'<div class="col-md-4 mb-3"><label>Tipo</label><select class="form-select" id="fp-tipo">'+
[['empleado','Empleado'],['contratista','Contratista'],['practicante','Practicante'],['tercero','Tercero']].map(function(o){
return '<option value="'+o[0]+'"'+(tipo===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-4 mb-3"><label>Locker</label><input class="form-control" id="fp-locker" maxlength="20" placeholder="Ej: L-015" value="'+esc(val('locker'))+'"><div class="form-text">Opcional. Sale en la etiqueta del locker.</div></div>'+
'<div class="col-12 mb-3"><label>Observaciones</label><textarea class="form-control" id="fp-obs" rows="2" maxlength="500">'+esc(val('observaciones'))+'</textarea></div>'+
'</div><div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button type="button" class="btn btn-primary" id="btn-gp">Guardar</button></div></form>';
Modal.abrir(esNuevo?'Registrar ingreso (alta)':'Editar — '+p.apellidos+' '+p.nombres,html,'modal-lg');
var fotoCtl=Foto.activar({foto:esNuevo?null:(p.foto||null),nombre:esNuevo?'':(p.nombres+' '+p.apellidos)});
// codigo de usuario: se arma con el prefijo del area elegida
var pref={};areas.forEach(function(a){pref[a.id]=a.codigo||''});
var pintaCodigo=function(){
var a=+$('#fp-area').val(),pre=pref[a]||'',txt;
if(esNuevo)txt=a&&pre?('Código de usuario: se asignará automáticamente según el área, será <b>'+esc(pre)+'-####</b> (el número lo da el sistema).'):'Código de usuario: se asignará automáticamente según el área que elija.';
else if(a&&a!==+$('#form-personal').data('area0')&&pre)txt='Código actual: <b>'+esc(p.codigo_usuario||'—')+'</b> · <span class="text-warning-emphasis">al cambiar de área se le asignará un código nuevo <b>'+esc(pre)+'-####</b>.</span>';
else txt='Código de usuario: <b>'+esc(p.codigo_usuario||'—')+'</b> <span class="text-muted">(lo asigna el sistema según el área)</span>';
$('#fp-codigo-txt').html(txt);
};
$('#fp-area').on('change',pintaCodigo);pintaCodigo();
// al escribir un DNI valido se validan los 8 digitos y se traen nombres y apellidos (RENIEC)
Documento.activar({id:'fp-dni',tipo:'dni',contexto:'personal',alDatos:function(r){
if(r.nombres)$('#fp-nombres').val(r.nombres);
if(r.apellido_paterno||r.apellido_materno){$('#fp-ap-pat').val(r.apellido_paterno||'');$('#fp-ap-mat').val(r.apellido_materno||'')}
}});
 $('#btn-gp').on('click',function(){
var $b=$(this).prop('disabled',true);
Api.post('api/personal/guardar.php',{
id:$('#form-personal').data('id'),dni:$('#fp-dni').val(),nombres:$('#fp-nombres').val(),
apellido_paterno:$('#fp-ap-pat').val(),apellido_materno:$('#fp-ap-mat').val(),correo_personal:$('#fp-correo-p').val(),correo_corporativo:$('#fp-correo-c').val(),
telefono:$('#fp-telefono').val(),area_id:$('#fp-area').val(),cargo:$('#fp-cargo').val(),
// estas 3 no se editan aqui: en edicion se conservan los valores actuales
jefe_id:val('jefe_id'),fecha_cese:val('fecha_cese'),estado:esNuevo?'pre_ingreso':p.estado,
fecha_ingreso:$('#fp-fingreso').val(),fecha_nacimiento:$('#fp-fnac').val(),genero:$('#fp-genero').val(),
tipo_personal:$('#fp-tipo').val(),observaciones:$('#fp-obs').val(),locker:$('#fp-locker').val()
}).then(function(r){
// la foto se sube aparte (en el alta, con el id recien creado)
var idP=(r&&r.id)||(esNuevo?0:p.id);
var pf=(idP&&(fotoCtl.archivo||fotoCtl.quitar))?Foto.aplicar(fotoCtl,idP,'api/personal/foto.php').catch(function(){Toast.warning('Los datos se guardaron, pero la foto no se pudo subir. Intente de nuevo desde Editar.')}):Promise.resolve(null);
return pf.then(function(){return r});
}).then(function(r){
Modal.cerrar();Personal.tabla.draw(false);
Toast.success(esNuevo?('Persona registrada. Código de usuario: '+((r&&r.codigo_usuario)||'—')+'.'+(r&&r.checklist?' Checklist de ingreso: '+r.checklist+' ítems.':'')):(r&&r.codigo_cambio?('Datos actualizados. Nuevo código por cambio de área: '+r.codigo_usuario+'.'):'Datos actualizados.'));
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
});
},
// Ficha completa de la persona
_ver:function(id){
Api.get('api/personal/detalle.php',{id:id}).then(function(p){
var e=ESTADOS_P[p.estado]||{t:p.estado,c:'text-bg-light'};
var nombre=(p.nombres||'')+' '+(p.apellidos||'');
var cta=(p.cuentas||[])[0]||null;
var dato=function(t,v){return '<div class="col-md-6"><span class="text-muted small d-block">'+t+'</span>'+(v?esc(v):'—')+'</div>'};
var cumple='—';
if(p.fecha_nacimiento){
cumple=esc(p.fecha_nacimiento)+(p.edad!=null?' · '+p.edad+' años':'');
if(p.dias_cumple===0)cumple+=' <span class="badge text-bg-warning">🎂 ¡Hoy es su cumpleaños!</span>';
else if(p.dias_cumple!=null)cumple+=' <span class="text-muted small">(próximo en '+p.dias_cumple+' día'+(p.dias_cumple===1?'':'s')+')</span>';
}
var h='<div class="d-flex align-items-center gap-3 mb-3">'+
Foto.avatar({id:p.id,foto:p.foto||(cta?cta.foto:null),nombre:nombre,size:64,endpoint:'api/personal/foto.php'})+
'<div><div class="fs-5 fw-semibold">'+esc(nombre)+'</div>'+
'<span class="badge '+e.c+'">'+e.t+'</span> <span class="text-muted small">'+esc(p.cargo||'')+(p.area?' · '+esc(p.area):'')+'</span></div></div>'+
'<div class="row g-2">'+
dato('Código de usuario',p.codigo_usuario)+dato('Locker',p.locker)+dato('DNI',p.dni)+dato('Apellido paterno',p.apellido_paterno)+dato('Apellido materno',p.apellido_materno)+dato('Tipo',p.tipo_personal?p.tipo_personal.charAt(0).toUpperCase()+p.tipo_personal.slice(1):'')+
dato('Correo corporativo',p.correo_corporativo)+dato('Correo personal',p.correo_personal)+
dato('Teléfono',p.telefono)+dato('Jefe inmediato',p.jefe)+
dato('Fecha de ingreso',p.fecha_ingreso)+dato('Fecha de cese',p.fecha_cese)+
'<div class="col-md-6"><span class="text-muted small d-block">Cumpleaños</span>'+cumple+'</div>'+
dato('Género',p.genero?p.genero.charAt(0).toUpperCase()+p.genero.slice(1):'')+
(p.observaciones?'<div class="col-12"><span class="text-muted small d-block">Observaciones</span>'+esc(p.observaciones)+'</div>':'')+
'</div>';
// cuentas del sistema
h+='<hr class="my-3"><div class="fw-semibold mb-1"><i class="bi bi-person-gear"></i> Cuentas del sistema</div>';
h+=(p.cuentas&&p.cuentas.length)?p.cuentas.map(function(c){
return '<div class="small">'+esc(c.usuario)+' · <span class="badge text-bg-light border">'+esc(c.rol)+'</span> · '+esc(c.estado)+'</div>'}).join('')
:'<div class="text-muted small">Sin cuenta de acceso.</div>';
// equipos asignados
h+='<div class="fw-semibold mt-3 mb-1"><i class="bi bi-laptop"></i> Equipos asignados ('+(p.equipos?p.equipos.length:0)+')</div>';
h+=(p.equipos&&p.equipos.length)?'<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Código</th><th>Tipo</th><th>Equipo</th><th>Serie</th><th>Entrega</th><th>Estado</th></tr></thead><tbody>'+
p.equipos.map(function(q){return '<tr><td>'+esc(q.codigo)+'</td><td>'+esc(q.tipo_eq)+'</td><td>'+esc((q.marca||'')+' '+(q.modelo||''))+'</td><td>'+esc(q.nro_serie||'—')+'</td><td>'+esc(q.fecha_entrega||'—')+'</td><td>'+esc(q.asig_estado)+'</td></tr>'}).join('')+
'</tbody></table></div>':'<div class="text-muted small">Sin equipos asignados.</div>';
// checklist
if(p.check_total){
var pct=Math.round(p.check_ok*100/p.check_total);
h+='<div class="fw-semibold mt-3 mb-1"><i class="bi bi-list-check"></i> Checklist</div><div class="progress" style="height:18px"><div class="progress-bar'+(pct===100?' bg-success':'')+'" style="width:'+pct+'%">'+p.check_ok+'/'+p.check_total+'</div></div>';
}
h+='<div class="text-end mt-3">'+
(Personal._perm.editar&&p.estado!=='cesado'?'<button type="button" class="btn btn-outline-secondary" id="btn-ficha-editar"><i class="bi bi-pencil"></i> Editar</button> ':'')+
'<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>';
Modal.abrir('Ficha — '+nombre,h,'modal-lg');
 $('#btn-ficha-editar').on('click',function(){Personal._form(p)});
});
},
// Eliminar: solo pre-ingreso sin actividad (el servidor lo vuelve a comprobar)
_eliminar:function(f){
var nombre=(f.nombres||'')+' '+(f.apellidos||'');
var id=f.id;
Swal.fire({title:'¿Eliminar este registro?',
html:'<b>'+esc(nombre)+'</b> (DNI '+esc(f.dni)+')<br><small>Se borrará también su checklist de ingreso. Esta acción no se puede deshacer.</small>',
icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',confirmButtonColor:'#dc3545'
}).then(function(r){
if(!r.isConfirmed)return;
Api.post('api/personal/eliminar.php',{id:id}).then(function(){
Personal.tabla.draw(false);Toast.success('Registro eliminado.');
}).catch(function(){});
});
},
// Proximos cumpleanos
_cumples:function(dias){
Api.get('api/personal/cumpleanos.php',{dias:dias}).then(function(d){
var fmt=function(iso){var x=new Date(iso+'T00:00:00');return x.toLocaleDateString('es-PE',{day:'numeric',month:'long'})};
var cuando=function(n){return n===0?'<span class="badge text-bg-warning">🎂 Hoy</span>':(n===1?'Mañana':'En '+n+' días')};
var h='<div class="d-flex align-items-center gap-2 mb-2"><label class="small mb-0">Mostrar los próximos</label>'+
'<select id="cu-dias" class="form-select form-select-sm" style="width:auto">'+
[7,30,60,90].map(function(n){return '<option value="'+n+'"'+(+d.dias===n?' selected':'')+'>'+n+' días</option>'}).join('')+'</select></div>';
h+=d.lista.length?'<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Persona</th><th>Área</th><th>Fecha</th><th>Cuándo</th><th class="text-end">Cumple</th></tr></thead><tbody>'+
d.lista.map(function(c){return '<tr><td>'+esc(c.nombre)+'<div class="text-muted small">'+esc(c.cargo||'')+'</div></td><td>'+esc(c.area||'—')+'</td><td>'+esc(fmt(c.proximo))+'</td><td>'+cuando(c.dias)+'</td><td class="text-end">'+c.cumple+' años</td></tr>'}).join('')+
'</tbody></table></div>':'<div class="text-muted text-center py-3">No hay cumpleaños en los próximos '+d.dias+' días.</div>';
h+='<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>';
Modal.abrir('🎂 Próximos cumpleaños',h,'modal-lg');
 $('#cu-dias').on('change',function(){Personal._cumples(+$(this).val())});
}).catch(function(){});
}
};
App.registrar('personal',Personal);
