/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   Botón flotante del chat: abre la pantalla de Chat, muestra los mensajes
   sin leer y avisa de las reuniones que están por empezar.
   ============================================================ */
var ChatBtn={
_ac:false,_t:null,_av:{},_n:null,_ctx:null,
init:function(){
var s=this;
s._v();
setInterval(function(){s._v()},4000);
},
_v:function(){
var l=!!(typeof App!=='undefined'&&App.usuario&&(typeof Sistema==='undefined'||Sistema.activo('chat')));
 if(l&&this._ac){var pleno=$('#chat-app').length>0;$('#chat-btn-flotante').toggleClass('hidden',pleno);if(pleno)ChatMini.cerrar()}
if(l&&!this._ac){this._ac=true;this._m();this._sondear();this._t=setInterval(function(){ChatBtn._sondear()},15000)}
else if(!l&&this._ac){this._d()}
},
_m:function(){
if(document.getElementById('chat-btn-flotante'))return;
 $('body').append('<button id="chat-btn-flotante" title="Chat"><i class="bi bi-chat-dots"></i><span id="chat-badge" class="hidden"></span></button>');
 $('#chat-btn-flotante').on('click',function(){ChatMini.alternar()});
},
_d:function(){
this._ac=false;this._av={};this._n=null;
if(this._t){clearInterval(this._t);this._t=null}
ChatMini.destruir();
 $('#chat-btn-flotante').remove();
},
/* número de mensajes sin leer en el icono (0 lo oculta) */
poner:function(n){
var b=$('#chat-badge'),antes=this._n;
this._n=n;
if(typeof Menu!=='undefined'&&Menu.poner)Menu.poner('chat/principal',n,'azul',n+' mensaje(s) sin leer');
if(!b.length)return;
if(n>0)b.text(n>99?'99+':n).removeClass('hidden');else b.addClass('hidden').text('');
if(antes!==null&&n>antes)this.sonar();          // llegó algo nuevo desde la última vez
},
/* sonido de aviso: si la persona lo silencia, se recuerda en este navegador */
sonido:function(v){
try{
if(v===undefined)return localStorage.getItem('sigti_chat_sonido')!=='0';
localStorage.setItem('sigti_chat_sonido',v?'1':'0');
}catch(e){}
return v===undefined?true:!!v;
},
sonar:function(){
if(!this.sonido())return;
try{
var A=window.AudioContext||window.webkitAudioContext;
if(!A)return;
if(!this._ctx)this._ctx=new A();
var c=this._ctx;
if(c.state==='suspended'&&c.resume)c.resume();
var t=c.currentTime;
[[880,0],[1175,0.13]].forEach(function(f){
var o=c.createOscillator(),g=c.createGain();
o.type='sine';o.frequency.value=f[0];
g.gain.setValueAtTime(0.0001,t+f[1]);g.gain.exponentialRampToValueAtTime(0.18,t+f[1]+0.02);g.gain.exponentialRampToValueAtTime(0.0001,t+f[1]+0.22);
o.connect(g);g.connect(c.destination);o.start(t+f[1]);o.stop(t+f[1]+0.25);
});
}catch(e){}
},
_sondear:function(){
var s=this;
if(typeof App==='undefined'||!App.usuario)return;
 $.getJSON(BASE_URL+'api/chat/resumen.php').done(function(r){
if(!r||!r.success)return;
s.poner(r.data.no_leidos||0);
(r.data.reuniones||[]).forEach(function(m){
var k=m.id+'|'+m.estado;
if(s._av[k])return;
s._av[k]=1;
var hora=String(m.inicio).substring(11,16);
if(typeof Toast!=='undefined')Toast.info(m.estado==='en_curso'?'Reunión en curso: «'+m.titulo+'». Abra el chat para unirse.':'Reunión «'+m.titulo+'» a las '+hora+'. Abra el Chat para unirse.');
});
});
}
};
/* ============================================================
   Chat emergente: se abre sobre cualquier pantalla (sin ir al módulo Chat).
   Lista de conversaciones, mensajes en vivo, enviar texto o adjuntar un archivo, y empezar chats nuevos.
   Para lo demás (grupos, reuniones, responder, reaccionar…) tiene «Abrir el chat completo».
   Usa la misma API que la pantalla de Chat: api/chat/mensajes.php
   ============================================================ */
var ChatMini={
_ab:false,_dom:false,_sala:null,_conv:[],_dir:[],_areas:[],_msgs:{},_ult:0,_act:0,_tl:null,_tm:null,_env:false,_yo:0,_nuevoTab:'personas',
ICONOS:{directo:'person-fill',area:'building',entre_areas:'diagram-2',grupo:'people-fill',reunion:'camera-video-fill',general:'megaphone-fill'},
_h:function(s){return typeof esc==='function'?esc(s):String(s==null?'':s).replace(/[&<>"']/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]})},
_hora:function(f){f=String(f||'');return f.length>=16?f.substring(11,16):''},
_dia:function(f){f=String(f||'').substring(0,10);var h=new Date(),m=h.getMonth()+1,d=h.getDate(),hoy=h.getFullYear()+'-'+(m<10?'0':'')+m+'-'+(d<10?'0':'')+d;return f===hoy?'':f.substring(8,10)+'/'+f.substring(5,7)},
_get:function(d){return $.ajax({url:BASE_URL+'api/chat/mensajes.php',type:'GET',data:d,dataType:'json'})},
_post:function(d,fd){
var o={url:BASE_URL+'api/chat/mensajes.php',type:'POST',dataType:'json',headers:{'X-CSRF-Token':(typeof CSRF!=='undefined'?CSRF.token:'')}};
if(fd){o.data=fd;o.processData=false;o.contentType=false}else o.data=d;
return $.ajax(o);
},
_err:function(x){var m='No se pudo completar la acción.';try{var c=x&&x.responseJSON;if(c){m=c.message||m;if(c.errors){var k=Object.keys(c.errors)[0];if(k)m=c.errors[k]}}}catch(e){}if(typeof Toast!=='undefined')Toast.warning(m)},

/* ---------- abrir / cerrar ---------- */
alternar:function(){if(this._ab)this.cerrar();else this.abrir()},
abrir:function(){
var s=this;
if(!s._dom)s._crear();
s._ab=true;$('#chat-mini').removeClass('hidden');
$('#chat-btn-flotante').addClass('cm-abierto');
if(s._sala)s._ver('conv');else{s._ver('lista');s._cargarLista()}
s._timers();
},
cerrar:function(){
if(!this._ab&&!this._dom)return;
this._ab=false;$('#chat-mini').addClass('hidden');$('#chat-btn-flotante').removeClass('cm-abierto');
this._paraTimers();
},
destruir:function(){this._paraTimers();this._ab=false;this._dom=false;this._sala=null;this._conv=[];this._msgs={};$('#chat-mini').remove()},
_paraTimers:function(){if(this._tl){clearInterval(this._tl);this._tl=null}if(this._tm){clearInterval(this._tm);this._tm=null}},
_timers:function(){
var s=this;s._paraTimers();
s._tl=setInterval(function(){if(s._ab&&!s._sala&&!$('#cm-lista-v').hasClass('hidden'))s._cargarLista(true)},8000);
s._tm=setInterval(function(){if(s._ab&&s._sala&&!$('#cm-conv-v').hasClass('hidden'))s._cargarMensajes(true)},3000);
},

/* ---------- estructura ---------- */
_crear:function(){
var s=this;
$('body').append('<div id="chat-mini" class="hidden" role="dialog" aria-label="Chat">'+
'<div class="cm-cab"><button type="button" id="cm-atras" class="hidden" title="Volver a la lista"><i class="bi bi-arrow-left"></i></button>'+
'<div class="cm-tit" id="cm-tit">Chat</div>'+
'<button type="button" id="cm-full" title="Abrir el chat completo"><i class="bi bi-arrows-angle-expand"></i></button>'+
'<button type="button" id="cm-cerrar" title="Cerrar"><i class="bi bi-x-lg"></i></button></div>'+
'<div class="cm-vista" id="cm-lista-v"><div class="cm-barra"><input type="search" id="cm-buscar" class="form-control form-control-sm" placeholder="Buscar conversación…" autocomplete="off">'+
'<button type="button" class="btn btn-primary btn-sm" id="cm-nuevo"><i class="bi bi-plus-lg"></i> Nuevo</button></div><div class="cm-lista" id="cm-lista"><div class="cm-vacio">Cargando…</div></div></div>'+
'<div class="cm-vista hidden" id="cm-nuevo-v"><div class="cm-pestanas"><button type="button" class="activo" data-t="personas">Personas</button><button type="button" data-t="areas">Áreas</button></div>'+
'<div class="cm-barra"><input type="search" id="cm-pbuscar" class="form-control form-control-sm" placeholder="Buscar…" autocomplete="off"></div><div class="cm-lista" id="cm-personas"></div></div>'+
'<div class="cm-vista hidden" id="cm-conv-v"><div class="cm-msgs" id="cm-msgs"></div><div class="cm-solo hidden" id="cm-solo"><i class="bi bi-eye"></i> Solo lectura</div>'+
'<div class="cm-comp" id="cm-comp"><button type="button" id="cm-adj" title="Adjuntar archivo o imagen"><i class="bi bi-paperclip"></i></button>'+
'<input type="file" id="cm-file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">'+
'<textarea id="cm-campo" rows="1" maxlength="1000" placeholder="Escriba un mensaje…"></textarea>'+
'<button type="button" id="cm-env" title="Enviar"><i class="bi bi-send-fill"></i></button></div></div></div>');
s._dom=true;
$('#cm-cerrar').on('click',function(){s.cerrar()});
$('#cm-full').on('click',function(){
s.cerrar();
if(typeof Router!=='undefined')Router.ir('chat/principal');
});
$('#cm-atras').on('click',function(){
s._sala=null;s._ver('lista');s._cargarLista();
});
$('#cm-buscar').on('input',function(){s._pintaLista()});
$('#cm-lista').on('click','.cm-it',function(){var id=+$(this).attr('data-id');var c=s._conv.filter(function(x){return +x.sala_id===id})[0];if(c)s._abrirSala(c)});
$('#cm-nuevo').on('click',function(){s._nuevo()});
$('#cm-nuevo-v .cm-pestanas').on('click','button',function(){s._nuevoTab=$(this).attr('data-t');$('#cm-nuevo-v .cm-pestanas button').removeClass('activo');$(this).addClass('activo');s._pintaNuevo()});
$('#cm-pbuscar').on('input',function(){s._pintaNuevo()});
$('#cm-personas').on('click','.cm-it',function(){s._iniciar($(this).attr('data-k'),+$(this).attr('data-id'))});
$('#cm-env').on('click',function(){s._enviar()});
$('#cm-campo').on('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();s._enviar()}})
 .on('input',function(){this.style.height='auto';this.style.height=Math.min(96,this.scrollHeight)+'px'});
$('#cm-adj').on('click',function(){$('#cm-file').trigger('click')});
$('#cm-file').on('change',function(){var f=this.files&&this.files[0];if(f)s._enviar(f);this.value=''});
$(document).on('keydown.cmini',function(e){if(e.key==='Escape'&&s._ab&&!$('.modal.show').length)s.cerrar()});
},
_ver:function(v){
$('#cm-lista-v,#cm-nuevo-v,#cm-conv-v').addClass('hidden');
$('#cm-'+v+'-v').removeClass('hidden');
$('#cm-atras').toggleClass('hidden',v==='lista');
if(v==='lista')$('#cm-tit').text('Chat');
else if(v==='nuevo')$('#cm-tit').text('Nuevo chat');
},

/* ---------- lista de conversaciones ---------- */
_cargarLista:function(silencioso){
var s=this;
return s._get({listar:1}).done(function(r){
if(!r||!r.success)return;
var d=r.data||{};
s._yo=d.yo&&d.yo.id;s._conv=(d.conversaciones||[]).slice().sort(function(a,b){return (b.ultimo_id||0)-(a.ultimo_id||0)});
s._dir=d.usuarios||[];s._areas=d.areas||[];
if(typeof ChatBtn!=='undefined')ChatBtn.poner(d.no_leidos||0);
s._pintaLista();
}).fail(function(){if(!silencioso)$('#cm-lista').html('<div class="cm-vacio">No se pudo cargar. Intente de nuevo.</div>')});
},
_pintaLista:function(){
var s=this,q=($('#cm-buscar').val()||'').toLowerCase().trim(),h='';
s._conv.filter(function(c){return !q||String(c.nombre||'').toLowerCase().indexOf(q)>=0}).forEach(function(c){
var ic=s.ICONOS[c.tipo]||'chat-dots-fill';
var pre=c.ultimo_texto?((c.ultimo_autor?s._h(c.ultimo_autor)+': ':'')+s._h(c.ultimo_texto)):'<i>Sin mensajes</i>';
h+='<div class="cm-it'+(c.no_leidos>0?' nuevo':'')+'" data-id="'+c.sala_id+'"><div class="cm-av"><i class="bi bi-'+ic+'"></i>'+(c.tipo==='directo'&&+c.en_linea?'<span class="cm-on"></span>':'')+'</div>'+
'<div class="cm-cu"><div class="cm-n"><span class="cm-nn">'+s._h(c.nombre)+'</span><span class="cm-hr">'+(c.ultima_hora?s._h(s._dia(c.ultima_hora)||s._hora(c.ultima_hora)):'')+'</span></div>'+
'<div class="cm-p">'+pre+'</div></div>'+(c.no_leidos>0?'<span class="cm-bd">'+(c.no_leidos>99?'99+':c.no_leidos)+'</span>':'')+'</div>';
});
$('#cm-lista').html(h||'<div class="cm-vacio">'+(q?'Ninguna conversación coincide.':'Todavía no tiene conversaciones.<br>Use <b>Nuevo</b> para escribirle a alguien.')+'</div>');
},

/* ---------- chat nuevo (persona o area) ---------- */
_nuevo:function(){
this._ver('nuevo');$('#cm-pbuscar').val('');this._pintaNuevo();
setTimeout(function(){$('#cm-pbuscar').trigger('focus')},50);
},
_pintaNuevo:function(){
var s=this,q=($('#cm-pbuscar').val()||'').toLowerCase().trim(),h='';
if(s._nuevoTab==='areas'){
s._areas.filter(function(a){return !q||String(a.nombre).toLowerCase().indexOf(q)>=0}).forEach(function(a){
h+='<div class="cm-it" data-k="area" data-id="'+a.id+'"><div class="cm-av"><i class="bi bi-building"></i></div><div class="cm-cu"><div class="cm-n"><span class="cm-nn">'+s._h(a.nombre)+'</span></div><div class="cm-p">'+(a.mia?'Su área':'Hablar con el área')+'</div></div></div>';
});
}else{
s._dir.filter(function(u){return !q||(String(u.nombre)+' '+String(u.area||'')).toLowerCase().indexOf(q)>=0}).forEach(function(u){
h+='<div class="cm-it" data-k="persona" data-id="'+u.id+'"><div class="cm-av"><i class="bi bi-person-fill"></i>'+(+u.en_linea?'<span class="cm-on"></span>':'')+'</div><div class="cm-cu"><div class="cm-n"><span class="cm-nn">'+s._h(u.nombre)+'</span></div><div class="cm-p">'+s._h(((u.area||'')+(u.cargo?' · '+u.cargo:''))||u.rol||'')+'</div></div></div>';
});
}
$('#cm-personas').html(h||'<div class="cm-vacio">Sin resultados.</div>');
},
_iniciar:function(k,id){
var s=this;
s._post(k==='area'?{accion:'iniciar_area',area_id:id}:{accion:'iniciar_directo',usuario_id:id}).done(function(r){
if(!r||!r.success||!r.data)return;
var sid=+r.data.sala_id;
s._cargarLista(true).always(function(){
var c=s._conv.filter(function(x){return +x.sala_id===sid})[0];
if(c)s._abrirSala(c);
});
}).fail(function(x){s._err(x)});
},

/* ---------- conversacion ---------- */
_abrirSala:function(c){
var s=this;
s._sala=c;s._ult=0;s._act=0;s._msgs={};
$('#cm-msgs').empty();$('#cm-tit').text(c.nombre||'Chat');
$('#cm-campo').val('').css('height','auto');
s._ver('conv');$('#cm-tit').text(c.nombre||'Chat');
s._cargarMensajes();
setTimeout(function(){$('#cm-campo').trigger('focus')},60);
},
_cargarMensajes:function(silencioso){
var s=this,sala=s._sala;
if(!sala)return;
var d={sala_id:sala.sala_id};
if(s._ult>0){d.desde_id=s._ult;d.desde_act=s._act}
return s._get(d).done(function(r){
if(!s._sala||s._sala.sala_id!==sala.sala_id)return;      // cambio de conversacion mientras cargaba
if(!r||!r.success)return;
var x=r.data,primera=s._ult===0,abajo=primera||s._abajo();
(x.cambios||[]).forEach(function(m){s._msgs[m.id]=m;$('#cm-m-'+m.id).replaceWith(s._burbuja(m))});
var nuevos=x.mensajes||[];
nuevos.forEach(function(m){if(!s._msgs[m.id]){s._msgs[m.id]=m;$('#cm-msgs').append(s._burbuja(m))}});
s._ult=Math.max(s._ult,x.ultimo_id||0);s._act=Math.max(s._act,x.ultimo_act||0);
var lec=x.acceso!=='escribir';$('#cm-solo').toggleClass('hidden',!lec);$('#cm-comp').toggleClass('hidden',lec);
if(primera&&!nuevos.length)$('#cm-msgs').html('<div class="cm-vacio">Aún no hay mensajes. ¡Escriba el primero!</div>');
else if(nuevos.length)$('#cm-msgs .cm-vacio').remove();
if(abajo&&(nuevos.length||primera))s._bajar();
if(nuevos.length&&!primera&&typeof ChatBtn!=='undefined'&&nuevos.some(function(m){return +m.usuario_id!==+s._yo&&!m.sistema}))ChatBtn.sonar();
}).fail(function(){if(!silencioso)$('#cm-msgs').html('<div class="cm-vacio">No se pudo cargar la conversación.</div>')});
},
_abajo:function(){var e=document.getElementById('cm-msgs');return !e||e.scrollHeight-e.scrollTop-e.clientHeight<60},
_bajar:function(){var e=document.getElementById('cm-msgs');if(e)e.scrollTop=e.scrollHeight},
_burbuja:function(m){
var s=this,propio=+m.usuario_id===+s._yo;
if(+m.sistema===1)return '<div class="cm-sis" id="cm-m-'+m.id+'">'+s._h(m.mensaje)+'</div>';
var cuerpo;
if(m.eliminado)cuerpo='<i class="text-muted">Mensaje eliminado</i>';
else{
cuerpo=m.mensaje?s._h(m.mensaje):'';
if(m.archivo){
var u=BASE_URL+'api/chat/archivo.php?id='+m.id;
cuerpo+=(cuerpo?'<br>':'')+(m.archivo.imagen?'<a href="'+u+'" target="_blank" rel="noopener"><img class="cm-img" src="'+u+'" alt="'+s._h(m.archivo.nombre)+'"></a>':
'<a href="'+u+'&dl=1" class="cm-arch"><i class="bi bi-paperclip"></i> '+s._h(m.archivo.nombre)+'</a>');
}
}
var dia=s._dia(m.created_at);
return '<div class="cm-m'+(propio?' yo':'')+'" id="cm-m-'+m.id+'">'+(!propio&&s._sala&&s._sala.tipo!=='directo'?'<div class="cm-au">'+s._h(m.autor||'')+'</div>':'')+
(m.resp?'<div class="cm-rs">↩ '+s._h(m.resp.autor||'')+': '+s._h(m.resp.texto||'')+'</div>':'')+
'<div class="cm-bu">'+cuerpo+'</div><div class="cm-ho">'+(dia?s._h(dia)+' ':'')+s._h(s._hora(m.created_at))+(m.editado?' · editado':'')+'</div></div>';
},
_enviar:function(archivo){
var s=this;
if(s._env||!s._sala)return;
var txt=$.trim($('#cm-campo').val()||'');
if(!txt&&!archivo)return;
if(archivo&&archivo.size>10*1024*1024){if(typeof Toast!=='undefined')Toast.warning('El archivo supera los 10 MB');return}
var sala=s._sala.sala_id;
s._env=true;$('#cm-env').prop('disabled',true);
var req;
if(archivo){var fd=new FormData();fd.append('accion','enviar');fd.append('sala_id',sala);fd.append('mensaje',txt);fd.append('archivo',archivo);req=s._post(null,fd)}
else req=s._post({accion:'enviar',sala_id:sala,mensaje:txt});
req.done(function(r){
if(r&&r.success){$('#cm-campo').val('').css('height','auto');s._cargarMensajes(true).always(function(){s._bajar()})}
}).fail(function(x){s._err(x)}).always(function(){s._env=false;$('#cm-env').prop('disabled',false);$('#cm-campo').trigger('focus')});
}
};

 $(document).ready(function(){setTimeout(function(){ChatBtn.init()},1500)});
