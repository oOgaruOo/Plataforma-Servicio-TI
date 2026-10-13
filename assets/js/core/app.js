/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
const CSRF={token:CSRF_TOKEN};
const App={
usuario:null,
permisos:[],
modulos:{},
registrar(n,m){this.modulos[n]=m},
init(){
this._bl();
this._bo();
this._bt();
Loader.show();
 $.get(BASE_URL+'api/auth/perfil.php')
.then(function(r){
if(r.success){
App._en(r.data)
}else{
App._lg()
}
})
.fail(function(){App._lg()})
.always(function(){Loader.hide()});
},
_lg(){
 $('#app').addClass('hidden');
 $('#pantalla-login').removeClass('hidden');
setTimeout(function(){$('#login-usuario').focus()},100);
},
_en(d){
this.usuario=d.usuario;
this.permisos=d.permisos||[];
 $('#pantalla-login').addClass('hidden');
 $('#app').removeClass('hidden');
 $('#topbar-nombre').text(this.usuario.nombre);
 $('#topbar-rol').text('Rol: '+this.usuario.rol);
 $('#avatar-iniciales').text(this.usuario.nombre.trim().split(/\s+/).slice(0,2).map(function(p){return p[0].toUpperCase()}).join(''));
Sistema.aplicar(d.sistema);
Menu.construir();
Router.ir('dashboard/principal');
Notificaciones.iniciar();
this._avatarReset();
this.cargarAvatar();
},
// Avatar de la barra superior: foto del usuario, o animado mientras no tenga foto
cargarAvatar(){
 $.get(BASE_URL+'api/auth/perfil.php',{_:Date.now()},function(res){
if(!(res&&res.success&&res.data&&res.data.usuario&&App.usuario))return;
App.usuario.foto=res.data.usuario.foto||null;
App.usuario.foto_url=res.data.usuario.foto_url||null;
App._pintarAvatar();
}).fail(function(){});
},
_pintarAvatar(){
var u=this.usuario;
if(!u)return;
if(!u.foto_url){this._avatarAnimado();return}
var $img=$('#avatar-foto');
$img.off('load.av error.av')
.on('load.av',function(){
 $('#btn-avatar').removeClass('avatar-animado').attr('title',u.nombre);
 $('#avatar-iniciales').hide();
App._fotoLogin(this);
})
.on('error.av',function(){App._avatarAnimado()})
.attr('src',BASE_URL+u.foto_url);
},
_avatarReset(){
 $('#avatar-foto').off('load.av error.av').removeAttr('src');
 $('#avatar-iniciales').show();
 $('#btn-avatar').addClass('avatar-animado').attr('title','Sin foto: abra el menu y elija "Cambiar mi foto"');
},
_avatarAnimado(){
this._avatarReset();
try{localStorage.removeItem('sigti_foto')}catch(e){}
},
// copia pequena (96px) para mostrarla en la pantalla de login la proxima vez
_fotoLogin(img){
try{
var l=Math.min(img.naturalWidth,img.naturalHeight);
if(!l)return;
var c=document.createElement('canvas');c.width=c.height=96;
c.getContext('2d').drawImage(img,(img.naturalWidth-l)/2,(img.naturalHeight-l)/2,l,l,0,0,96,96);
localStorage.setItem('sigti_foto',c.toDataURL('image/jpeg',0.8));
}catch(e){}
},
sesionExpirada(){
this.usuario=null;
this.permisos=[];
Notificaciones.detener();
$('#vista-container').empty();
 $('#app').addClass('hidden');
 $('#pantalla-login').removeClass('hidden');
Toast.info('Sesion expirada.');
},
_bl(){
 $('#login-usuario').on('input',function(){App._loginBuscarFoto(this.value)});
 if($('#login-usuario').val())App._loginBuscarFoto($('#login-usuario').val(),true);
 $('#form-login').on('submit',function(e){
e.preventDefault();
var $b=$('#btn-login'),h=$b.html();
 $b.prop('disabled',true).html('Verificando...');
 $('#login-error').hide();
 $.ajax({
url:BASE_URL+'api/auth/login.php',
type:'POST',
dataType:'json',
headers:{'X-CSRF-Token':CSRF.token},
data:{usuario:$('#login-usuario').val().trim(),password:$('#login-password').val()},
success:function(r){
if(r.success){
try{localStorage.setItem('sigti_usuario',$('#login-usuario').val().trim())}catch(e){}
App._en(r.data);
 $('#form-login')[0].reset();
Toast.success(r.message)
}else{
 $('#login-error').text(r.message).show()
}
},
error:function(x){
var m='Error de conexion.';
try{m=JSON.parse(x.responseText).message||m}catch(e){}
 $('#login-error').text(m).show()
},
complete:function(){$b.prop('disabled',false).html(h)}
});
});
},
// Foto del ingreso: al escribir el usuario se muestra su foto; sin foto, un círculo con su inicial
_loginPintar(foto,usr){
var $b=$('#login-foto'),$i=$('#login-foto-img'),$s=$('#login-foto-ini');
 $('#login-logo-icona').hide();
if(foto){$i.attr('src',foto).removeClass('hidden');$s.text('')}
else{
 $i.addClass('hidden').removeAttr('src');
var ini=(usr||'').replace(/[^A-Za-z0-9\u00C0-\u017F]/g,'').slice(0,2).toUpperCase();
 $s.text(ini||'?')
}
 $b.toggleClass('sin-foto',!foto).removeClass('hidden');
},
_loginReset(){
 $('#login-foto').addClass('hidden').removeClass('sin-foto');
 $('#login-foto-img').addClass('hidden').removeAttr('src');
 $('#login-logo-icona').show();
},
_loginBuscarFoto(v,inmediato){
var s=this,u=String(v||'').trim();
clearTimeout(s._lt);
if(s._lx){try{s._lx.abort()}catch(e){}s._lx=null}
if(u.length<3){s._loginReset();return}
s._lt=setTimeout(function(){
var recordada=null;
try{if((localStorage.getItem('sigti_usuario')||'')===u)recordada=localStorage.getItem('sigti_foto')}catch(e){}
if(recordada)s._loginPintar(recordada,u);
s._lx= $.ajax({url:BASE_URL+'api/auth/foto-login.php',type:'GET',dataType:'json',data:{u:u},global:false})
.done(function(r){
if(String($('#login-usuario').val()||'').trim()!==u)return;     // ya escribió otra cosa
var f=r&&r.success&&r.data?r.data.foto:null;
if(f||!recordada)s._loginPintar(f,u);
})
.fail(function(){if(!recordada)s._loginPintar(null,u)});
},inmediato?0:350);
},
_bo(){
 $('#btn-logout').on('click',function(){
Swal.fire({title:'Cerrar sesion?',icon:'question',showCancelButton:true,
confirmButtonText:'Si',cancelButtonText:'Cancelar',confirmButtonColor:'#2563eb'
}).then(function(r){
if(!r.isConfirmed)return;
 $.post(BASE_URL+'api/auth/logout.php',{csrf_token:CSRF.token})
.always(function(){setTimeout(function(){location.reload()},500)});
});
});
},
_bt(){
 $('#btn-menu').on('click',function(){$('body').toggleClass('sidebar-oculto')});
 $('#btn-ver-pass').on('click',function(){
var i=$('#login-password');
var p=i.attr('type')==='password';
i.attr('type',p?'text':'password');
 $(this).find('i').attr('class',p?'bi bi-eye-slash':'bi bi-eye');
});
}
};

/* Bootstrap atrapa el foco dentro de la ventana abierta: cuando encima aparece un cuadro de SweetAlert con un campo
   (motivo, observacion...) el foco volvia a la ventana y NO se podia escribir. Se desactiva esa trampa de foco. */
try{bootstrap.Modal.Default.focus=false}catch(e){}
const Modal={
abrir(t,h,s){
 $('#modal-general-titulo').text(t);
 $('#modal-general-cuerpo').html(h);
 $('#modal-general .modal-dialog').removeClass('modal-sm modal-lg modal-xl').addClass(s||'modal-lg');
bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-general'),{focus:false}).show();
},
cerrar(){
bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-general')).hide();
}
};

function esc(s){
return String(s==null?'':s).replace(/[&<>"']/g,function(m){
return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
});
}

 $(document).ready(function(){App.init()});

// ---- MIS PERMISOS ----
 $(document).on('click','#btn-mis-permisos',function(){
 $.get(BASE_URL+'api/usuarios/permisos-efectivos.php',function(res){
if(!res.success){Toast.error(res.message||'Error');return}
var d=res.data;
var grupos={};
(d.permisos||[]).forEach(function(p){(grupos[p.modulo]=grupos[p.modulo]||[]).push(p)});
var h='<div class="alert alert-light border py-2 small"><b>'+esc(d.usuario.nombre)+'</b> — rol: <b>'+esc(d.usuario.rol)+'</b> — <b>'+d.efectivos+'</b> de '+d.total_permisos+' permisos.</div>';
Object.keys(grupos).forEach(function(mod){
h+='<div class="border rounded p-2 mb-2"><b class="small text-uppercase">'+esc(mod)+'</b>';
grupos[mod].forEach(function(p){
h+='<div class="d-flex justify-content-between py-1 border-bottom"><span class="small '+(p.activo?'':'text-muted')+'">'+(p.activo?'&#10004;':'&#10008;')+' '+esc(p.descripcion||p.permino)+'</span><span class="small text-muted">'+esc(p.origen)+'</span></div>';
});
h+='</div>';
});
h+='<div class="text-center mt-2"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>';
Modal.abrir('Mis permisos',h,'modal-lg');
}).fail(function(){Toast.error('Error al cargar')});
});