/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var Usuarios={
tabla:null,
init:function(){
this.tabla=DT.server('#tb-usuarios','api/usuarios/listar.php',function(){return{}},
[
{data:null,orderable:false,className:'text-center',
render:function(v,t,f){return Foto.avatar({id:f.id,foto:f.foto,nombre:f.nombre_completo||f.usuario,size:36})}},
{data:'usuario',render:function(v){return '<strong>'+esc(v)+'</strong>'}},
{data:'nombre_completo'},
{data:'rol',render:function(v){return '<span class="badge text-bg-light border">'+esc(v)+'</span>'}},
{data:'personal',render:function(v,t,f){return v?'<i class="bi bi-link-45deg text-success"></i> '+esc(v)+((f.personal_cargo||f.personal_area)?'<div class="small text-muted">'+esc(f.personal_cargo||'')+(f.personal_cargo&&f.personal_area?' · ':'')+esc(f.personal_area||'')+'</div>':''):'<span class="text-muted">—</span>'}},
{data:'celular',render:function(v){return v?esc(v):'—'}},
{data:'correo',render:function(v){return v?esc(v):'—'}},
{data:'ultimo_acceso',render:function(v){return v?esc(v):'<span class="text-muted">nunca</span>'}},
{data:'estado',render:function(v){return v==='activo'?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-danger">'+esc(v)+'</span>'}},
{data:null,orderable:false,className:'text-center',
render:function(){return '<button class="btn btn-sm btn-outline-primary btn-editar"><i class="bi bi-pencil"></i></button>'}}
]);
 $('#btn-nuevo-usuario').on('click',function(){Usuarios._form(null)});
 // «Roles»: crear, editar, duplicar, eliminar roles y definir sus permisos
 $('#btn-roles').on('click',function(){
Roles.abrir();
// al cerrar la ventana, la tabla refleja nombres de rol que pudieron cambiar
var m=document.getElementById('modal-general');
if(m)m.addEventListener('hidden.bs.modal',function(){if(Usuarios.tabla)Usuarios.tabla.draw(false)},{once:true});
});
 $('#tb-usuarios').on('click','.btn-editar',function(){
var f=Usuarios.tabla.row($(this).closest('tr')).data();
if(f)Usuarios._form(f);
});
},
/* roles del area de la persona primero (jefe_/personal_ del area), luego el resto */
_opcionesRol:function(roles,areaId,sel){
var propios=areaId?roles.filter(function(r){return +r.area_id===+areaId}):[];
var otros=roles.filter(function(r){return propios.indexOf(r)<0});
var o=function(r){return '<option value="'+r.id+'"'+(+sel===+r.id?' selected':'')+'>'+esc(r.texto)+(r.area?' ('+esc(r.area)+')':'')+'</option>'};
if(!propios.length)return otros.map(o).join('');
return '<optgroup label="Roles de su área">'+propios.map(o).join('')+'</optgroup><optgroup label="Otros roles">'+otros.map(o).join('')+'</optgroup>';
},
/* jefe_<area> si el cargo es de mando; si no, personal_<area> */
_rolSugerido:function(roles,p){
if(!p||!p.area_id)return null;
var propios=roles.filter(function(r){return +r.area_id===+p.area_id});
var mando=/jefe|gerente|director|coordinador|supervisor|residente|l[ií]der|encargado|subgerente/i.test(p.cargo||'');
var pref=mando?'jefe_':'personal_';
return propios.filter(function(r){return String(r.texto).indexOf(pref)===0})[0]||null;
},
_form:function(fila){
var esNuevo=!fila;
Promise.all([
Api.get('api/catalogos/select.php',{tipo:'roles'}),
Api.get('api/catalogos/select.php',{tipo:'personal'})
]).then(function(listas){
var roles=listas[0],personal=listas[1]||[];
var val=function(c){return esNuevo?'':(fila[c]==null?'':fila[c])};
// si la persona vinculada ya no esta en la lista (p.ej. cesada), se conserva igual su vinculo
var pid=+val('personal_id');
if(pid&&!personal.some(function(p){return +p.id===pid})){personal=[{id:pid,texto:fila.personal||('Personal #'+pid)}].concat(personal)}
var est=esNuevo?'activo':fila.estado;
var html='<form id="form-usuario" data-id="'+(esNuevo?0:fila.id)+'" onsubmit="return false">'+
'<div class="mb-3">'+Foto.panel({id:esNuevo?0:fila.id,foto:esNuevo?null:fila.foto,nombre:esNuevo?'':(fila.nombre_completo||fila.usuario)})+'</div>'+
'<div class="row">'+
'<div class="col-md-6 mb-3"><label>Usuario *</label><input class="form-control" id="fu-usuario" value="'+esc(val('usuario'))+'"></div>'+
'<div class="col-md-6 mb-3"><label>Nombre *</label><input class="form-control" id="fu-nombre" value="'+esc(val('nombre_completo'))+'"></div>'+
'<div class="col-md-6 mb-3"><label>Rol *</label><select class="form-select" id="fu-rol">'+Usuarios._opcionesRol(roles,0,val('rol_id'))+'</select></div>'+
'<div class="col-md-6 mb-3"><label>Personal vinculado</label><select class="form-select" id="fu-personal">'+
'<option value="">— Sin vincular —</option>'+
personal.map(function(p){return '<option value="'+p.id+'"'+(+p.id===pid?' selected':'')+'>'+esc(p.texto)+(p.dni?' · '+esc(p.dni):'')+(p.cargo?' — '+esc(p.cargo):'')+'</option>'}).join('')+'</select>'+
'<div id="fu-pinfo" class="small mt-1"></div>'+
'<div class="form-text">La foto del usuario se muestra también en su ficha de personal.</div></div>'+
'<div class="col-md-6 mb-3"><label>Correo</label><input type="email" class="form-control" id="fu-correo" value="'+esc(val('correo'))+'"></div>'+
'<div class="col-md-6 mb-3"><label>Celular corporativo</label><input class="form-control" id="fu-celular" maxlength="20" value="'+esc(val('celular'))+'"></div>'+
'<div class="col-md-6 mb-3"><label>Estado</label><select class="form-select" id="fu-estado">'+
['activo','bloqueado','inactivo'].map(function(e){return '<option value="'+e+'"'+(est===e?' selected':'')+'>'+e.charAt(0).toUpperCase()+e.slice(1)+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-6 mb-3"><label>Contrasena'+(esNuevo?' *':' (dejar vacio)')+'</label><input type="password" class="form-control" id="fu-pass" autocomplete="new-password"></div>'+
'</div><div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button type="button" class="btn btn-primary" id="btn-gu">Guardar</button></div></form>';
Modal.abrir(esNuevo?'Nuevo usuario':'Editar — '+fila.usuario,html);
var ctl=Foto.activar({foto:esNuevo?null:fila.foto,nombre:esNuevo?'':(fila.nombre_completo||fila.usuario)});
// cargo / area de la persona elegida y rol sugerido para ambos
var rolTocado=!esNuevo;
 $('#fu-rol').on('change',function(){rolTocado=true});
var mostrarPersona=function(){
var p=personal.filter(function(x){return +x.id===+$('#fu-personal').val()})[0];
var actual=$('#fu-rol').val();
if(!p){ $('#fu-pinfo').html('');$('#fu-rol').html(Usuarios._opcionesRol(roles,0,actual));return}
var sug=Usuarios._rolSugerido(roles,p);
 $('#fu-rol').html(Usuarios._opcionesRol(roles,p.area_id,actual));
var h='<i class="bi bi-person-badge text-primary"></i> Cargo: <b>'+esc(p.cargo||'sin cargo')+'</b> · Área: <b>'+esc(p.area||'sin área')+'</b>';
if(sug)h+='<br><i class="bi bi-lightbulb text-warning"></i> Rol sugerido para su área y cargo: <b>'+esc(sug.texto)+'</b> <button type="button" class="btn btn-sm btn-outline-primary py-0" id="fu-usar-rol">Usar</button>';
else if(p.area_id)h+='<br><span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Su área no tiene roles propios: elija uno general o créelo en «Roles».</span>';
 $('#fu-pinfo').html(h);
 $('#fu-usar-rol').on('click',function(){ $('#fu-rol').val(sug.id);rolTocado=true});
if(esNuevo&&!rolTocado&&sug) $('#fu-rol').val(sug.id);
};
 $('#fu-personal').on('change',mostrarPersona);
if(pid)mostrarPersona();
 $('#btn-gu').on('click',function(){
var $b=$(this).prop('disabled',true);
var id=+$('#form-usuario').data('id');
Api.post('api/usuarios/guardar.php',{
id:id,usuario:$('#fu-usuario').val(),
nombre_completo:$('#fu-nombre').val(),rol_id:$('#fu-rol').val(),personal_id:$('#fu-personal').val(),
correo:$('#fu-correo').val(),celular:$('#fu-celular').val(),estado:$('#fu-estado').val(),password:$('#fu-pass').val()
}).then(function(r){
var uid=(r&&r.id)||id;
// la foto se sube cuando el usuario ya existe (en un usuario nuevo recien ahora hay id)
return Foto.aplicar(ctl,uid).then(function(){return uid},function(){
Toast.warning('El usuario se guardo, pero no se pudo actualizar la foto.');return uid});
}).then(function(uid){
Modal.cerrar();
Usuarios.tabla.draw(false);
Toast.success(esNuevo?'Usuario creado.':'Usuario actualizado.');
if(App.usuario&&+App.usuario.id===+uid&&(ctl.archivo||ctl.quitar))App.cargarAvatar();
}).catch(function(e){
$b.prop('disabled',false);
if(e&&e.cuerpo&&e.cuerpo.errors)Toast.warning(Object.values(e.cuerpo.errors)[0]);
});
});
});
}
};
App.registrar('usuarios',Usuarios);
