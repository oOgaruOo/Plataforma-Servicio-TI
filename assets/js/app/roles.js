/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Roles (Usuarios -> boton «Roles»)
   Una sola ventana con tres vistas: lista de roles, formulario del rol y
   editor de permisos (por modulo, con busqueda, copiar de otro rol y avisos).
   API: api/usuarios/roles.php y api/usuarios/roles-permisos.php
   ============================================================ */
var Roles={
_roles:[],
_ed:null,

/* ---------- entrada ---------- */
abrir:function(){
Roles._lista();
},

/* ---------- vista 1: lista ---------- */
_lista:function(){
Api.get('api/usuarios/roles.php').then(function(roles){
Roles._roles=roles||[];
Roles._pintarLista('');
}).catch(function(){});
},

_pintarLista:function(q){
var R=Roles._roles,t=String(q||'').trim().toLowerCase();
var filas=R.filter(function(r){
return !t||[r.nombre,r.descripcion,r.area].join(' ').toLowerCase().indexOf(t)>=0;
});
var h='<div class="d-flex flex-wrap gap-2 align-items-center mb-3">'+
'<button type="button" class="btn btn-primary btn-sm" id="rl-nuevo"><i class="bi bi-plus-lg"></i> Nuevo rol</button>'+
'<input type="search" class="form-control form-control-sm" id="rl-buscar" placeholder="Buscar rol, descripción o área..." style="max-width:320px" value="'+esc(q||'')+'">'+
'<span class="text-muted small ms-auto">'+R.length+' rol(es)</span></div>';
h+='<div class="table-responsive"><table class="table table-hover table-bordered align-middle mb-0" id="rl-tabla"><thead class="table-light"><tr>'+
'<th>Rol</th><th>Área</th><th class="text-center">Usuarios</th><th style="min-width:150px">Permisos</th><th class="text-center text-nowrap">Acciones</th></tr></thead><tbody>';
if(!filas.length){h+='<tr><td colspan="5" class="text-center text-muted py-4">No hay roles que coincidan.</td></tr>'}
filas.forEach(function(r){
var pct=r.total_permisos?Math.round(r.n_permisos*100/r.total_permisos):0;
var sinPerm=(+r.n_permisos===0);
h+='<tr data-id="'+r.id+'">'+
'<td><strong>'+esc(r.nombre)+'</strong> '+
(+r.protegido?'<span class="badge text-bg-secondary" title="El sistema usa este rol por su nombre">Sistema</span>':'')+
'<div class="small text-muted">'+(r.descripcion?esc(r.descripcion):'<em>Sin descripción</em>')+'</div></td>'+
'<td>'+(r.area?esc(r.area):'<span class="text-muted">—</span>')+'</td>'+
'<td class="text-center">'+(+r.n_usuarios?'<span class="badge text-bg-primary">'+r.n_usuarios+'</span>':'<span class="text-muted">0</span>')+'</td>'+
'<td>'+(sinPerm
?'<span class="badge text-bg-danger" title="Un usuario con este rol no podrá hacer nada">Sin permisos</span>'
:'<div class="progress" style="height:16px" title="'+r.n_permisos+' de '+r.total_permisos+' permisos"><div class="progress-bar'+(pct===100?' bg-success':'')+'" style="width:'+Math.max(pct,12)+'%">'+r.n_permisos+'/'+r.total_permisos+'</div></div>')+'</td>'+
'<td class="text-center text-nowrap">'+
'<button type="button" class="btn btn-sm btn-outline-primary rl-perm" title="Permisos del rol"><i class="bi bi-shield-check"></i></button> '+
'<button type="button" class="btn btn-sm btn-outline-secondary rl-edit" title="Editar rol"><i class="bi bi-pencil"></i></button> '+
'<button type="button" class="btn btn-sm btn-outline-secondary rl-dup" title="Duplicar con sus permisos"><i class="bi bi-files"></i></button> '+
(+r.protegido
?'<span title="Rol del sistema: no se elimina"><button type="button" class="btn btn-sm btn-outline-danger" disabled><i class="bi bi-trash"></i></button></span>'
:'<button type="button" class="btn btn-sm btn-outline-danger rl-del" title="Eliminar rol"><i class="bi bi-trash"></i></button>')+
'</td></tr>';
});
h+='</tbody></table></div>'+
'<div class="text-muted small mt-3"><i class="bi bi-info-circle"></i> '+
'<b>🛡 Permisos</b>: qué puede hacer el rol, por módulo · <b>Duplicar</b>: crea un rol nuevo con los mismos permisos · '+
'Un rol con usuarios no se puede eliminar: primero cambie el rol de esos usuarios. '+
'Los cambios de permisos se aplican a los usuarios conectados en menos de un minuto.</div>';
Modal.abrir('Roles del sistema',h,'modal-xl');

$('#rl-nuevo').on('click',function(){Roles._form(null)});
$('#rl-buscar').on('input',function(){
var v=this.value;Roles._pintarLista(v);
var el=document.getElementById('rl-buscar');if(el){el.focus();el.setSelectionRange(v.length,v.length)}
});
$('#rl-tabla').on('click','.rl-perm,.rl-edit,.rl-dup,.rl-del',function(){
var id=+$(this).closest('tr').data('id');
var r=Roles._roles.filter(function(x){return +x.id===id})[0];
if(!r)return;
if($(this).hasClass('rl-perm'))Roles._editor(r.id);
else if($(this).hasClass('rl-edit'))Roles._form(r);
else if($(this).hasClass('rl-dup'))Roles._form(null,r);
else Roles._eliminar(r);
});
},

/* ---------- vista 2: formulario ---------- */
// fila = rol a editar; base = rol a duplicar (se copian sus permisos)
_form:function(fila,base){
var esNuevo=!fila;
Api.get('api/catalogos/select.php',{tipo:'areas'}).catch(function(){return[]}).then(function(areas){
areas=areas||[];
var v=function(c){return fila?(fila[c]==null?'':fila[c]):''};
var nombre=fila?fila.nombre:(base?base.nombre+'_copia':'');
var desc=fila?(fila.descripcion||''):(base?(base.descripcion||''):'');
var aid=fila?(+fila.area_id||0):(base?(+base.area_id||0):0);
var prot=!!(fila&&+fila.protegido);
var h='<form id="form-rol" onsubmit="return false">'+
'<div class="mb-3"><a href="#" id="rf-volver" class="small"><i class="bi bi-arrow-left"></i> Volver a la lista de roles</a></div>'+
'<div class="row">'+
'<div class="col-md-6 mb-3"><label class="form-label">Nombre del rol *</label>'+
'<input class="form-control" id="rf-nombre" maxlength="50" value="'+esc(nombre)+'"'+(prot?' disabled':'')+' placeholder="ej: jefe_logistica">'+
'<div class="form-text">'+(prot?'Este rol lo usa el sistema por su nombre: no se renombra.':'Sin espacios: letras, números, guion y guion bajo (3 a 50). Los roles nuevos se guardan en minúsculas.')+'</div></div>'+
'<div class="col-md-6 mb-3"><label class="form-label">Área (opcional)</label><select class="form-select" id="rf-area"'+(prot?' disabled':'')+'>'+
'<option value="">— Sin área —</option>'+areas.map(function(a){return '<option value="'+a.id+'"'+(+a.id===aid?' selected':'')+'>'+esc(a.texto)+'</option>'}).join('')+'</select></div>'+
'<div class="col-12 mb-3"><label class="form-label">Descripción</label><input class="form-control" id="rf-desc" maxlength="200" value="'+esc(desc)+'" placeholder="Para qué sirve este rol"></div>';
if(esNuevo){
h+='<div class="col-12 mb-3"><label class="form-label">Copiar los permisos de</label><select class="form-select" id="rf-copiar">'+
'<option value="">— Empezar sin permisos —</option>'+
Roles._roles.map(function(r){return '<option value="'+r.id+'"'+(base&&+base.id===+r.id?' selected':'')+'>'+esc(r.nombre)+' ('+r.n_permisos+' permisos)</option>'}).join('')+'</select>'+
'<div class="form-text">Después podrá ajustarlos uno por uno.</div></div>';
}
h+='</div><div class="text-end"><button type="button" class="btn btn-secondary" id="rf-cancel">Cancelar</button> '+
'<button type="button" class="btn btn-primary" id="rf-guardar">'+(esNuevo?'Crear rol':'Guardar cambios')+'</button></div></form>';
Modal.abrir(esNuevo?(base?'Duplicar rol «'+base.nombre+'»':'Nuevo rol'):'Editar rol — '+fila.nombre,h,'modal-lg');
$('#rf-volver,#rf-cancel').on('click',function(e){e.preventDefault();Roles._lista()});
$('#rf-guardar').on('click',function(){
var $b=$(this).prop('disabled',true);
var datos={accion:'guardar',id:fila?fila.id:0,nombre:$('#rf-nombre').val(),descripcion:$('#rf-desc').val(),area_id:$('#rf-area').val()};
if(esNuevo)datos.copiar_de=$('#rf-copiar').val()||'';
Api.post('api/usuarios/roles.php',datos).then(function(r){
if(esNuevo){
Toast.success(r&&r.copiados?('Rol creado con '+r.copiados+' permiso(s) copiados.'):'Rol creado. Defina sus permisos.');
Roles._editor(r.id);   // se pasa directo a elegir los permisos
}else{
Toast.success('Rol actualizado.');
Roles._lista();
}
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
});
},

/* ---------- eliminar ---------- */
_eliminar:function(r){
if(+r.n_usuarios>0){
Swal.fire({title:'No se puede eliminar',icon:'info',
html:'<b>'+esc(r.nombre)+'</b> tiene <b>'+r.n_usuarios+'</b> usuario(s).<br><small>Cambie el rol de esos usuarios (Usuarios → Editar) y vuelva a intentarlo.</small>'});
return;
}
Swal.fire({title:'¿Eliminar el rol «'+esc(r.nombre)+'»?',icon:'warning',showCancelButton:true,
html:'Se borran también sus <b>'+r.n_permisos+'</b> permiso(s) asignados. No se puede deshacer.',
confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',confirmButtonColor:'#dc3545'
}).then(function(x){
if(!x.isConfirmed)return;
Api.post('api/usuarios/roles.php',{accion:'eliminar',id:r.id}).then(function(){
Toast.success('Rol eliminado.');
Roles._lista();
}).catch(function(){});
});
},

/* ---------- vista 3: editor de permisos ---------- */
_editor:function(rolId){
Api.get('api/usuarios/roles-permisos.php',{rol:rolId}).then(function(d){
var sel={};
d.permisos.forEach(function(p){if(+p.en_rol)sel[p.id]=1});
Roles._ed={rol:d.rol,super:!!d.super,permisos:d.permisos,sel:sel,orig:$.extend({},sel)};
Roles._pintarEditor();
}).catch(function(){});
},

_modulos:function(){
var g={},orden=[];
Roles._ed.permisos.forEach(function(p){
if(!g[p.modulo]){g[p.modulo]={clave:p.modulo,nombre:p.modulo_nombre,items:[]};orden.push(p.modulo)}
g[p.modulo].items.push(p);
});
return orden.map(function(k){return g[k]});
},

_pintarEditor:function(){
var E=Roles._ed,ro=E.super;
var h='<div class="mb-2"><a href="#" id="re-volver" class="small"><i class="bi bi-arrow-left"></i> Volver a la lista de roles</a></div>'+
'<div class="d-flex flex-wrap align-items-center gap-2 mb-2">'+
'<h6 class="mb-0"><i class="bi bi-shield-check text-primary"></i> Permisos del rol «'+esc(E.rol.nombre)+'»</h6>'+
(+E.rol.n_usuarios?'<span class="badge text-bg-primary">'+E.rol.n_usuarios+' usuario(s)</span>':'<span class="badge text-bg-light border">Sin usuarios</span>')+
'<span class="ms-auto small" id="re-cuenta"></span></div>';
if(ro){
h+='<div class="alert alert-info py-2 small"><i class="bi bi-info-circle"></i> <b>super_admin</b> siempre tiene todos los permisos y no se edita.</div>';
}else{
h+='<div class="d-flex flex-wrap gap-2 align-items-center mb-3">'+
'<input type="search" class="form-control form-control-sm" id="re-buscar" placeholder="Buscar permiso o módulo..." style="max-width:260px">'+
'<div class="btn-group btn-group-sm" role="group">'+
'<button type="button" class="btn btn-outline-secondary" id="re-todo">Marcar todo</button>'+
'<button type="button" class="btn btn-outline-secondary" id="re-nada">Quitar todo</button>'+
'<button type="button" class="btn btn-outline-secondary" id="re-ver" title="Marca solo los permisos de consulta (ver)">Solo consulta</button></div>'+
'<div class="input-group input-group-sm" style="max-width:340px"><span class="input-group-text">Copiar de</span>'+
'<select class="form-select" id="re-copiar"><option value="">— elegir rol —</option>'+
Roles._roles.filter(function(r){return +r.id!==+E.rol.id}).map(function(r){return '<option value="'+r.id+'">'+esc(r.nombre)+'</option>'}).join('')+'</select>'+
'<button type="button" class="btn btn-outline-secondary" id="re-aplicar">Aplicar</button></div></div>';
}
h+='<div class="row g-2" id="re-grid">';
Roles._modulos().forEach(function(m){
h+='<div class="col-md-6 re-mod" data-mod="'+esc(m.clave)+'"><div class="border rounded h-100">'+
'<div class="d-flex align-items-center gap-2 px-2 py-1 bg-light border-bottom">'+
'<input type="checkbox" class="form-check-input mt-0 re-modchk"'+(ro?' disabled':'')+'> <b class="small text-uppercase text-primary">'+esc(m.nombre)+'</b>'+
'<span class="badge text-bg-light border ms-auto re-modcount"></span></div><div class="p-1">';
m.items.forEach(function(p){
h+='<label class="d-flex align-items-start gap-2 px-2 py-1 re-perm" data-id="'+p.id+'" data-txt="'+esc((m.nombre+' '+p.modulo+' '+p.accion+' '+(p.descripcion||'')).toLowerCase())+'" style="cursor:'+(ro?'default':'pointer')+'">'+
'<input type="checkbox" class="form-check-input mt-1 re-chk" value="'+p.id+'"'+(+p.en_rol?' checked':'')+(ro?' disabled':'')+'>'+
'<span class="small"><code>'+esc(p.accion)+'</code>'+(+p.sensible?' <span class="badge text-bg-warning text-dark" title="Da control amplio o permite borrar datos">sensible</span>':'')+
(p.descripcion?'<br><span class="text-muted">'+esc(p.descripcion)+'</span>':'')+'</span></label>';
});
h+='</div></div></div>';
});
h+='</div><div class="text-muted small text-center py-3 d-none" id="re-vacio">Ningún permiso coincide con la búsqueda.</div>'+
'<div class="text-end mt-3 pt-2 border-top"><button type="button" class="btn btn-secondary" id="re-cancel">'+(ro?'Cerrar':'Cancelar')+'</button> '+
(ro?'':'<button type="button" class="btn btn-primary" id="re-guardar" disabled><i class="bi bi-check2"></i> Guardar permisos</button>')+'</div>';
Modal.abrir('Roles del sistema',h,'modal-xl');
Roles._ligarEditor();
Roles._actualizarEditor();
},

_ligarEditor:function(){
var E=Roles._ed;
$('#re-volver,#re-cancel').on('click',function(e){e.preventDefault();Roles._salirEditor()});
if(E.super)return;
$('#re-grid').on('change','.re-chk',function(){
var id=+this.value;
if(this.checked)E.sel[id]=1;else delete E.sel[id];
Roles._actualizarEditor();
});
$('#re-grid').on('change','.re-modchk',function(){
var on=this.checked;
$(this).closest('.re-mod').find('.re-perm:not(.d-none) .re-chk').each(function(){
this.checked=on;if(on)E.sel[+this.value]=1;else delete E.sel[+this.value];
});
Roles._actualizarEditor();
});
$('#re-buscar').on('input',function(){Roles._filtrarEditor(this.value)});
$('#re-todo').on('click',function(){Roles._marcar(function(){return true})});
$('#re-nada').on('click',function(){Roles._marcar(function(){return false})});
$('#re-ver').on('click',function(){Roles._marcar(function(p){return p.accion==='ver'||p.accion==='ver_propios'||p.accion==='ver_area'})});
$('#re-aplicar').on('click',function(){
var rid=$('#re-copiar').val();
if(!rid){Toast.warning('Elija el rol del que desea copiar.');return}
Api.get('api/usuarios/roles-permisos.php',{rol:rid}).then(function(o){
var set={};o.permisos.forEach(function(p){if(+p.en_rol)set[p.id]=1});
Roles._marcar(function(p){return !!set[p.id]});
Toast.success('Permisos de «'+o.rol.nombre+'» aplicados. Revise y guarde.');
}).catch(function(){});
});
$('#re-guardar').on('click',function(){Roles._guardarEditor($(this))});
},

// aplica una regla (permiso -> marcado?) a todos los permisos
_marcar:function(fn){
var E=Roles._ed;
E.sel={};
E.permisos.forEach(function(p){if(fn(p))E.sel[p.id]=1});
$('#re-grid .re-chk').each(function(){this.checked=!!E.sel[+this.value]});
Roles._actualizarEditor();
},

_filtrarEditor:function(q){
var t=String(q||'').trim().toLowerCase(),visibles=0;
$('#re-grid .re-perm').each(function(){
var ok=!t||String($(this).data('txt')).indexOf(t)>=0;
$(this).toggleClass('d-none',!ok);if(ok)visibles++;
});
$('#re-grid .re-mod').each(function(){
$(this).toggleClass('d-none',$(this).find('.re-perm:not(.d-none)').length===0);
});
$('#re-vacio').toggleClass('d-none',visibles>0);
Roles._actualizarEditor();
},

// cuenta, casillas de modulo (con estado intermedio) y boton Guardar
_actualizarEditor:function(){
var E=Roles._ed,total=E.permisos.length,n=Object.keys(E.sel).length;
$('#re-grid .re-mod').each(function(){
var $m=$(this),$c=$m.find('.re-chk'),on=$c.filter(':checked').length,all=$c.length;
var chk=$m.find('.re-modchk')[0];
chk.checked=on===all&&all>0;chk.indeterminate=on>0&&on<all;
$m.find('.re-modcount').text(on+'/'+all);
});
var ca=Roles._cambios();
$('#re-cuenta').html('<b>'+n+'</b> de '+total+' permisos'+
(ca.add||ca.del?' · <span class="text-success">+'+ca.add+'</span> <span class="text-danger">−'+ca.del+'</span> sin guardar':''));
$('#re-guardar').prop('disabled',!(ca.add||ca.del));
},

_cambios:function(){
var E=Roles._ed,add=[],del=[];
E.permisos.forEach(function(p){
var a=!!E.sel[p.id],b=!!E.orig[p.id];
if(a&&!b)add.push(p);else if(!a&&b)del.push(p);
});
return{add:add.length,del:del.length,agregados:add,quitados:del};
},

_salirEditor:function(){
var E=Roles._ed;
var ca=E&&!E.super?Roles._cambios():{add:0,del:0};
if(!(ca.add||ca.del)){Roles._lista();return}
Swal.fire({title:'¿Descartar los cambios?',icon:'warning',showCancelButton:true,
text:'Hay permisos modificados sin guardar.',confirmButtonText:'Sí, descartar',cancelButtonText:'Seguir editando'
}).then(function(r){if(r.isConfirmed)Roles._lista()});
},

_guardarEditor:function($b){
var E=Roles._ed,ca=Roles._cambios();
var enviar=function(){
$b.prop('disabled',true);
Api.post('api/usuarios/roles-permisos.php',{accion:'guardar',rol_id:E.rol.id,permisos:JSON.stringify(Object.keys(E.sel).map(Number))}).then(function(r){
Toast.success('Permisos guardados ('+(r&&r.total!=null?r.total:Object.keys(E.sel).length)+').'+(r&&r.usuarios?' Se aplican en menos de un minuto.':''));
Roles._lista();
}).catch(function(){$b.prop('disabled',false)});
};
var sens=ca.agregados.filter(function(p){return +p.sensible});
if(!sens.length&&!(+E.rol.n_usuarios&&ca.del)){enviar();return}
// avisos antes de guardar: permisos sensibles nuevos o quitar permisos a usuarios que ya los usan
var msg='';
if(sens.length)msg+='<p class="mb-1">Va a conceder permisos <b>sensibles</b>:</p><ul class="text-start small">'+sens.map(function(p){return '<li><code>'+esc(p.modulo+'.'+p.accion)+'</code>'+(p.descripcion?' — '+esc(p.descripcion):'')+'</li>'}).join('')+'</ul>';
if(+E.rol.n_usuarios&&ca.del)msg+='<p class="mb-0 small">Se quitan <b>'+ca.del+'</b> permiso(s) a <b>'+E.rol.n_usuarios+'</b> usuario(s) que hoy los tienen.</p>';
Swal.fire({title:'Confirmar cambios',icon:'warning',html:msg,showCancelButton:true,confirmButtonText:'Sí, guardar',cancelButtonText:'Revisar'
}).then(function(x){if(x.isConfirmed)enviar()});
}
};
