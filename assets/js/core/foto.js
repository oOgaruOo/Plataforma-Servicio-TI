/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Fotos de usuario
   - Foto.avatar(o)   : <img> con la foto, o avatar ANIMADO si no tiene
   - Foto.panel/activar/aplicar : selector de foto para formularios
   - Foto.miFoto()    : cada usuario cambia su propia foto (menu del avatar)
   La foto vive en usuarios.foto: es del usuario; el personal vinculado la hereda.
   ============================================================ */
var Foto={
LIMITE:5*1024*1024,

iniciales:function(n){
var p=String(n||'').trim().split(/\s+/).filter(Boolean).slice(0,2);
return p.length?p.map(function(x){return x.charAt(0).toUpperCase()}).join(''):'--';
},

url:function(id,v,ep){
return BASE_URL+(ep||'api/usuarios/foto.php')+'?id='+encodeURIComponent(id)+'&v='+encodeURIComponent(v||'');
},

/* o = {id, foto (nombre de archivo o null), nombre, size (px), endpoint (opcional: p.ej. api/personal/foto.php)} */
avatar:function(o){
var s=o.size||36;
if(o.foto){
return '<span class="av" style="--s:'+s+'px"><img src="'+esc(Foto.url(o.id,o.foto,o.endpoint))+'" alt="" loading="lazy" data-ini="'+esc(Foto.iniciales(o.nombre))+'"></span>';
}
return Foto._anim(o.nombre,s);
},

_anim:function(nombre,s){
return '<span class="av av-anim" style="--s:'+s+'px" title="Sin foto"><b>'+esc(Foto.iniciales(nombre))+'</b></span>';
},

/* HTML del selector de foto (insertarlo dentro del formulario del modal) */
panel:function(o){
return '<div class="foto-panel d-flex align-items-center gap-3 p-2 border rounded bg-light">'+
'<div id="fp-prev">'+Foto.avatar({id:o.id,foto:o.foto,nombre:o.nombre,size:84,endpoint:o.endpoint})+'</div>'+
'<div class="flex-grow-1"><div class="fw-semibold small mb-1">'+esc(o.titulo||'Foto del usuario')+'</div>'+
'<input type="file" id="fp-file" accept="image/png,image/jpeg" class="d-none">'+
'<button type="button" class="btn btn-sm btn-outline-primary" id="fp-elegir"><i class="bi bi-camera"></i> Elegir foto</button> '+
'<button type="button" class="btn btn-sm btn-outline-danger'+(o.foto?'':' d-none')+'" id="fp-quitar"><i class="bi bi-trash"></i> Quitar</button>'+
'<div class="form-text mb-0" id="fp-msg">'+esc(o.nota||'PNG o JPG, máx. 5 MB. Se recorta en cuadrado.')+'</div></div></div>';
},

/* Activa el selector (llamar justo despues de Modal.abrir). Devuelve el controlador {archivo,quitar,foto} */
activar:function(o){
var c={archivo:null,quitar:false,foto:o.foto||null};
$('#fp-elegir').on('click',function(){$('#fp-file').trigger('click')});
$('#fp-file').on('change',function(){
var f=this.files&&this.files[0];
if(!f)return;
if(!/^image\/(png|jpeg)$/.test(f.type)){Toast.warning('Solo imágenes PNG o JPG.');this.value='';return}
if(f.size>Foto.LIMITE){Toast.warning('La imagen supera 5 MB.');this.value='';return}
c.archivo=f;c.quitar=false;
var rd=new FileReader();
rd.onload=function(){$('#fp-prev').html('<span class="av" style="--s:84px"><img src="'+rd.result+'" alt=""></span>')};
rd.readAsDataURL(f);
$('#fp-quitar').removeClass('d-none');
$('#fp-msg').text('Nueva foto lista: se guardará al confirmar.');
});
$('#fp-quitar').on('click',function(){
c.archivo=null;c.quitar=!!c.foto;
$('#fp-file').val('');
$('#fp-prev').html(Foto._anim(o.nombre,84));
$(this).addClass('d-none');
$('#fp-msg').text(c.quitar?'La foto se eliminará al confirmar.':'Sin foto.');
});
return c;
},

/* Sube o quita la foto segun el controlador. Devuelve una promesa (null si no hubo cambios) */
aplicar:function(c,usuarioId,endpoint){
var ep=endpoint||'api/usuarios/foto.php';
if(c.archivo){
var fd=new FormData();
fd.append('id',usuarioId);fd.append('accion','subir');fd.append('foto',c.archivo);
return Api.postFiles(ep,fd);
}
if(c.quitar){return Api.post(ep,{id:usuarioId,accion:'quitar'})}
return Promise.resolve(null);
},

/* Cada usuario cambia su propia foto */
miFoto:function(){
var u=App.usuario;
if(!u)return;
var h='<div id="form-mi-foto">'+Foto.panel({id:u.id,foto:u.foto,nombre:u.nombre})+
'<div class="text-end mt-3"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> '+
'<button class="btn btn-primary" id="btn-mf-guardar">Guardar foto</button></div></div>';
Modal.abrir('Mi foto',h,'modal-md');
var c=Foto.activar({foto:u.foto,nombre:u.nombre});
$('#btn-mf-guardar').on('click',function(){
if(!c.archivo&&!c.quitar){Modal.cerrar();return}
Foto.aplicar(c,u.id).then(function(){
Modal.cerrar();
Toast.success(c.quitar?'Foto eliminada.':'Foto actualizada.');
App.cargarAvatar();
}).catch(function(){});
});
}
};

/* Si una foto no carga (archivo borrado, error de red), se muestra el avatar animado en su lugar */
document.addEventListener('error',function(e){
var t=e.target;
if(t&&t.tagName==='IMG'&&t.parentNode&&t.parentNode.classList&&t.parentNode.classList.contains('av')){
var p=t.parentNode;
p.classList.add('av-anim');
p.innerHTML='<b>'+esc(t.getAttribute('data-ini')||'--')+'</b>';
}
},true);

/* Menu del avatar: "Cambiar mi foto" */
$(document).on('click','#btn-mi-foto',function(){
try{bootstrap.Dropdown.getOrCreateInstance(document.getElementById('btn-avatar')).hide()}catch(e){}
Foto.miFoto();
});
