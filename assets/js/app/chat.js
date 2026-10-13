/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   CHAT INTERNO — personas, áreas, entre áreas, grupos y reuniones
   ============================================================ */
var Chat={
yo:null,conv:[],sup:[],usuarios:[],areas:[],esSis:false,
tab:'todos',q:'',sid:0,ultimo:0,act:0,sala:null,acceso:'escribir',tl:null,tm:null,mm:{},resp:null,edit:0,adj:null,menc:{},mi:0,
EMO_RAPIDOS:['👍','❤️','😂','😮','😢','🙏'],
EMO:{'Caritas':'😀 😃 😄 😁 😆 😅 😂 🤣 🙂 😉 😊 😇 🥰 😍 😘 😋 😛 😜 🤪 😎 🤓 🥳 😏 😒 😞 😔 😟 😕 🙁 😣 😖 😫 😩 🥺 😢 😭 😤 😠 😡 🤬 🤯 😳 🥵 🥶 😱 😨 😰 😥 😓 🤗 🤔 🤭 🤫 😶 😐 😑 😬 🙄 😯 😮 😲 🥱 😴 🤤 😪 😵 🤐 🥴 🤢 🤮 🤧 😷 🤒 🤕',
'Gestos':'👍 👎 👌 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 🖖 👋 🤝 🙏 💪 👏 🙌 👐 🤲 ✍️ 🫡 👀 🙈 🙉 🙊',
'Trabajo':'💻 🖥️ ⌨️ 🖱️ 🖨️ 📱 ☎️ 📞 📧 📎 📌 📅 ✅ ❌ ⚠️ 🔧 🔨 🛠️ ⚙️ 🔒 🔑 💡 🔋 🔌 📶 📁 📄 📊 📈 📉 🗂️ 🧾 💼 🎯 🚀 ⏰ ⌛ 🔔 📢',
'Ánimo':'❤️ 🧡 💛 💚 💙 💜 🖤 💔 ✨ 🌟 ⭐ 🔥 💯 🎉 🎊 🎁 🏆 ☕ 🍕 🎂 🍻 ✔️ ❓ ❗ ➕ ➖ ➡️ ⬅️ ⬆️ ⬇️ 🇵🇪'},
TIPO_ICO:{general:'people-fill',directo:'person-fill',grupo:'people',area:'building',entre_areas:'arrow-left-right',reunion:'camera-video',supervision:'eye'},
EST_REU:{programada:['Programada','text-bg-info'],en_curso:['En curso','text-bg-success'],finalizada:['Finalizada','text-bg-secondary'],cancelada:['Cancelada','text-bg-danger']},

init:function(){
var s=this;
s._parar();
s.sid=0;s.ultimo=0;s.act=0;s.sala=null;s.tab='todos';s.q='';s.mm={};s._limpiarCompositor();
 $('#ch-lista').off('click.ch').on('click.ch','.chat-item',function(){s._abrir(+$(this).data('id'))});
 $('#ch-tabs').off('click.ch').on('click.ch','button',function(){
s.tab=$(this).data('tab');
 $('#ch-tabs button').removeClass('activo');$(this).addClass('activo');
s._lista();
});
 $('#ch-buscar').off('input.ch').on('input.ch',function(){s.q=$.trim($(this).val()).toLowerCase();s._lista()});
 $('#chat-app').off('click.chn').on('click.chn','[data-nuevo]',function(e){e.preventDefault();s._nuevo($(this).data('nuevo'))});
 $('#ch-enviar').off('click.ch').on('click.ch',function(){s._enviar()});
 $('#ch-campo').off('keydown.ch').on('keydown.ch',function(e){
if(!$('#ch-menciones').hasClass('d-none')){
if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();s._moverMencion(e.key==='ArrowDown'?1:-1);return}
if(e.key==='Enter'||e.key==='Tab'){e.preventDefault();var $x=$('#ch-menciones .m').eq(s.mi);if($x.length)s._elegirMencion(+$x.data('id'));return}
if(e.key==='Escape'){e.preventDefault();$('#ch-menciones').addClass('d-none');return}
}
if(e.key==='Escape'&&(s.resp||s.edit||s.adj)){s._cancelarBarra();return}
if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();s._enviar()}
}).off('input.ch').on('input.ch',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,110)+'px'});
 $('#ch-info-btn').off('click.ch').on('click.ch',function(){$('#ch-info').toggleClass('d-none')});
 $('#ch-atras').off('click.ch').on('click.ch',function(){s.sid=0;$('#chat-app').removeClass('con-conv');s._lista()});
 $('#ch-info').off('click.ch').on('click.ch','[data-acc]',function(){s._accion($(this).data('acc'),$(this).data('id'),$(this).data('val'))});
 $('#ch-emoji-btn').off('click.ch').on('click.ch',function(e){e.stopPropagation();s._panelEmojis()});
 $('#ch-emojis').off('click.ch').on('click.ch','.e',function(){s._insertar($(this).data('e'))}).on('click.ch','.tab',function(){s._panelEmojis($(this).data('c'))});
 $('#ch-adj-btn').off('click.ch').on('click.ch',function(){$('#ch-adj').trigger('click')});
 $('#ch-adj').off('change.ch').on('change.ch',function(){if(this.files&&this.files[0])s._adjuntar(this.files[0]);this.value=''});
 $('#ch-campo').off('paste.ch').on('paste.ch',function(e){
var it=((e.originalEvent||e).clipboardData||{}).items||[];
for(var i=0;i<it.length;i++)if(it[i].kind==='file'){var f=it[i].getAsFile();if(f){s._adjuntar(f);e.preventDefault();return}}
});
 $('#ch-campo').off('keyup.chm click.chm').on('keyup.chm click.chm',function(e){if(['ArrowUp','ArrowDown','Enter','Tab','Escape'].indexOf(e.key)<0)s._menciones()});
 $('#ch-barra').off('click.ch').on('click.ch','[data-x]',function(){s._cancelarBarra()});
 $('#ch-menciones').off('mousedown.ch').on('mousedown.ch','.m',function(e){e.preventDefault();s._elegirMencion(+$(this).data('id'))});
 $('#ch-msgs').off('click.ch').on('click.ch','[data-a]',function(e){
var $m=$(this).closest('[data-id]'),id=+$m.data('id'),a=$(this).data('a');
if(a==='reaccion'){$m.toggleClass('reac-abierta');return}
if(a==='reaccionar'){s._reaccionar(id,$(this).data('e'));return}
if(a==='responder'){s._responder(id);return}
if(a==='editar'){s._editar(id);return}
if(a==='eliminar'){s._eliminar(id);return}
}).on('click.ch','[data-ir]',function(){s._ir(+$(this).data('ir'))});
 $('#ch-sonido').off('click.ch').on('click.ch',function(){
if(!window.ChatBtn)return;
ChatBtn.sonido(!ChatBtn.sonido());s._iconoSonido();
if(ChatBtn.sonido())ChatBtn.sonar();
});
s._iconoSonido();
 $(document).off('click.chemo').on('click.chemo',function(e){if(!$(e.target).closest('#ch-emojis,#ch-emoji-btn').length)$('#ch-emojis').addClass('d-none')});
s._cargar();
s.tl=setInterval(function(){if(!$('#ch-lista').length){s._parar();return}s._cargar(true)},8000);
s.tm=setInterval(function(){if(!$('#ch-msgs').length){s._parar();return}s._msgs()},4000);
},
_parar:function(){
if(this.tl){clearInterval(this.tl);this.tl=null}
if(this.tm){clearInterval(this.tm);this.tm=null}
},

/* ---------- lista de conversaciones ---------- */
_cargar:function(silencio,luego){
var s=this;
 $.getJSON(BASE_URL+'api/chat/mensajes.php',{listar:1}).done(function(r){
if(!r||!r.success)return;
var d=r.data;
s.yo=d.yo;s.conv=d.conversaciones||[];s.sup=d.supervision||[];s.usuarios=d.usuarios||[];s.areas=d.areas||[];s.esSis=!!d.es_sistemas;
if(s.sid){var c=s._conv(s.sid);if(c)c.no_leidos=0}
s._lista();
if(window.ChatBtn)ChatBtn.poner(d.no_leidos||0);
if(luego)luego();
}).fail(function(){if(!silencio)Toast.error('No se pudo cargar el chat')});
},
_conv:function(id){
var l=this.conv.concat(this.sup);
for(var i=0;i<l.length;i++)if(l[i].sala_id===id)return l[i];
return null;
},
_iniciales:function(n){
var p=String(n||'?').replace(/[↔]/g,' ').split(/\s+/).filter(function(x){return x});
return ((p[0]||'?').charAt(0)+(p.length>1?p[1].charAt(0):'')).toUpperCase();
},
_hora:function(t){
if(!t)return '';
var loc=function(d){return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2)};
if(String(t).substring(0,10)===loc(new Date()))return String(t).substring(11,16);
return String(t).substring(8,10)+'/'+String(t).substring(5,7);
},
_cuando:function(t){
if(!t)return '';
return String(t).substring(8,10)+'/'+String(t).substring(5,7)+'/'+String(t).substring(0,4)+' '+String(t).substring(11,16);
},
_avatar:function(c){
var ico=this.TIPO_ICO[c.tipo]||'chat',h;
if(c.tipo==='directo'){
h='<div class="chat-av">'+esc(this._iniciales(c.nombre))+(c.en_linea?'<span class="chat-on"></span>':'')+'</div>';
}else{
h='<div class="chat-av tipo-'+esc(c.tipo)+'"><i class="bi bi-'+ico+'"></i></div>';
}
return h;
},
_lista:function(){
var s=this,l=s.conv.slice(),q=s.q;
var tipos={personas:['directo'],areas:['area','entre_areas'],grupos:['grupo','personal','soporte'],reuniones:['reunion']};
if(s.tab!=='todos')l=l.filter(function(c){return tipos[s.tab].indexOf(c.tipo)>=0});
if(q)l=l.filter(function(c){return String(c.nombre||'').toLowerCase().indexOf(q)>=0});
if(s.tab==='reuniones'){
l.sort(function(a,b){
var pa=a.reunion&&(a.reunion.estado==='programada'||a.reunion.estado==='en_curso'),pb=b.reunion&&(b.reunion.estado==='programada'||b.reunion.estado==='en_curso');
if(pa!==pb)return pa?-1:1;
var ia=a.reunion?a.reunion.inicio:'',ib=b.reunion?b.reunion.inicio:'';
return pa?(ia<ib?-1:1):(ia<ib?1:-1);
});
}else{
l.sort(function(a,b){return (b.ultimo_id||0)-(a.ultimo_id||0)});
// La sala General siempre arriba cuando no hay búsqueda
var g=l.filter(function(c){return c.tipo==='general'}),o=l.filter(function(c){return c.tipo!=='general'});
l=g.concat(o);
}
var h='';
l.forEach(function(c){h+=s._item(c)});
if(s.esSis&&(s.tab==='todos'||s.tab==='personas')&&s.sup.length){
var sp=s.sup.filter(function(c){return !q||String(c.nombre||'').toLowerCase().indexOf(q)>=0});
if(sp.length){
h+='<div class="chat-sep"><i class="bi bi-eye"></i> Supervisión de Sistemas (solo lectura)</div>';
sp.forEach(function(c){h+=s._item(c)});
}
}
if(!h)h='<div class="text-muted small p-3 text-center">'+(q?'Sin resultados.':'Aún no tiene conversaciones en esta pestaña.<br>Use <b>Nuevo</b> para empezar una.')+'</div>';
 $('#ch-lista').html(h);
},
_item:function(c){
var sub='';
if(c.tipo==='reunion'&&c.reunion){
var e=this.EST_REU[c.reunion.estado]||['',''];
sub='<span class="badge '+e[1]+'">'+e[0]+'</span> '+esc(this._cuando(c.reunion.inicio));
}else if(c.ultimo_texto){
sub=(c.ultimo_autor?'<b>'+esc(c.ultimo_autor)+':</b> ':'')+esc(c.ultimo_texto);
}else if(c.subtitulo){
sub=esc(c.subtitulo);
}else{
sub='<i>Sin mensajes</i>';
}
return '<div class="chat-item'+(c.sala_id===this.sid?' activa':'')+'" data-id="'+c.sala_id+'">'+this._avatar(c)+
'<div class="chat-item-txt"><div class="n">'+esc(c.nombre)+'</div><div class="u">'+sub+'</div></div>'+
'<div class="chat-item-der"><span class="h">'+esc(this._hora(c.ultima_hora))+'</span>'+
(c.no_leidos?'<span class="chat-no-leidos">'+(c.no_leidos>99?'99+':c.no_leidos)+'</span>':'')+'</div></div>';
},

/* ---------- abrir una conversación ---------- */
_abrir:function(id){
var s=this;
s.sid=id;s.ultimo=0;s.act=0;s.sala=null;s.mm={};s._limpiarCompositor();
 $('#chat-app').addClass('con-conv');
 $('#ch-vacio').addClass('d-none');$('#ch-conv').removeClass('d-none');
 $('#ch-msgs').empty();$('#ch-aviso').addClass('d-none');$('#ch-unirse').addClass('d-none');
 $('#ch-titulo').text('Cargando…');$('#ch-sub').text('');
var c=s._conv(id);if(c)c.no_leidos=0;
s._lista();
s._msgs(true);
 $('#ch-campo').focus();
},
_msgs:function(primera){
var s=this,sid=s.sid;
if(!sid)return;
 $.getJSON(BASE_URL+'api/chat/mensajes.php',{sala_id:sid,desde_id:s.ultimo,desde_act:s.act}).done(function(r){
if(sid!==s.sid)return;                   // cambió de conversación mientras cargaba
if(!r||!r.success){return}
var d=r.data;
s.acceso=d.acceso;
if(d.sala){s.sala=d.sala;s._cabecera();s._panel()}
var ca=$('#ch-msgs')[0],cerca=primera||ca.scrollHeight-ca.scrollTop-ca.offsetHeight<90;
// mensajes editados / borrados / con reacciones nuevas
(d.cambios||[]).forEach(function(m){
s.mm[m.id]=m;
 $('#m-'+m.id).replaceWith(s._msgHtml(m));
});
var ms=d.mensajes||[];
if(ms.length||primera){
var h='';
if(primera){s._ultDia=''}
var ajenos=0;
ms.forEach(function(m){
s.mm[m.id]=m;
var f=String(m.created_at||'').substring(0,10);
if(f&&f!==s._ultDia){s._ultDia=f;h+='<div class="chat-dia"><span>'+esc(s._diaTxt(f))+'</span></div>'}
if(!primera&&s.yo&&+m.usuario_id!==s.yo.id&&+m.sistema!==1)ajenos++;
h+=s._msgHtml(m);
});
if(primera&&!ms.length)h='<div class="text-muted small text-center p-4">Aún no hay mensajes. ¡Escriba el primero!</div>';
if(primera)$('#ch-msgs').html(h);else $('#ch-msgs').append(h);
s.ultimo=d.ultimo_id||s.ultimo;
if(ajenos&&document.hidden&&window.ChatBtn)ChatBtn.sonar();
}
s.act=d.ultimo_act||s.act;
if(cerca&&(ms.length||primera))ca.scrollTop=ca.scrollHeight;
 $('#ch-escribir').toggleClass('d-none',s.acceso!=='escribir');
}).fail(function(x){
if(x.status===403){Toast.error('No tiene acceso a esa conversación');s.sid=0;$('#ch-conv').addClass('d-none');$('#ch-vacio').removeClass('d-none');$('#chat-app').removeClass('con-conv')}
});
},

/* ---------- un mensaje ---------- */
_tam:function(b){return b>=1048576?(b/1048576).toFixed(1)+' MB':Math.max(1,Math.round(b/1024))+' KB'},
_texto:function(m){
var h=esc(m.mensaje),s=this;
(m.menciones||[]).forEach(function(id){
var n=s._nombreDe(id);
if(!n)return;
var e=esc('@'+n);
h=h.split(e).join('<span class="chat-menc">'+e+'</span>');
});
h=h.replace(/(https?:\/\/[^\s<]+)/g,function(u){return '<a href="'+u+'" target="_blank" rel="noopener noreferrer">'+u+'</a>'});
return h;
},
_nombreDe:function(id){
if(this.yo&&this.yo.id===id)return this.yo.nombre;
for(var i=0;i<this.usuarios.length;i++)if(this.usuarios[i].id===id)return this.usuarios[i].nombre;
return '';
},
_msgHtml:function(m){
var s=this,yoId=s.yo?s.yo.id:0;
if(+m.sistema===1)return '<div class="chat-sis" id="m-'+m.id+'"><span>'+esc(m.mensaje)+'</span></div>';
var p=m.usuario_id===yoId,mostrarAutor=!p&&s.sala&&s.sala.tipo!=='directo';
var h='<div class="chat-msg'+(p?' propio':'')+'" id="m-'+m.id+'" data-id="'+m.id+'">';
if(mostrarAutor)h+='<div class="chat-autor">'+esc(m.autor||'')+'</div>';
h+='<div class="chat-fila">';
if(m.eliminado){
h+='<div class="chat-burbuja eliminado"><i class="bi bi-slash-circle"></i> Mensaje eliminado</div></div></div>';
return h;
}
h+='<div class="chat-burbuja">';
if(m.resp)h+='<div class="chat-cita" data-ir="'+m.resp.id+'"><b>'+esc(m.resp.autor||'')+'</b><span>'+esc(m.resp.texto)+'</span></div>';
if(m.archivo){
var u=BASE_URL+'api/chat/archivo.php?id='+m.id;
if(m.archivo.imagen)h+='<a href="'+u+'" target="_blank" rel="noopener noreferrer"><img class="chat-img" src="'+u+'" alt="'+esc(m.archivo.nombre)+'" loading="lazy"></a>';
else h+='<a class="chat-arch" href="'+u+'&dl=1"><i class="bi bi-file-earmark-arrow-down"></i><span><b>'+esc(m.archivo.nombre)+'</b><small>'+s._tam(m.archivo.size)+' · descargar</small></span></a>';
}
if(m.mensaje)h+='<div class="chat-txt">'+s._texto(m)+'</div>';
h+='</div>';
if(s.acceso==='escribir'){
var admin=s.sala&&s.sala.soy_admin&&(s.sala.tipo==='grupo'||s.sala.tipo==='reunion');
h+='<div class="chat-acc"><button type="button" data-a="reaccion" title="Reaccionar">😊</button><button type="button" data-a="responder" title="Responder"><i class="bi bi-reply"></i></button>';
if(p&&m.mensaje)h+='<button type="button" data-a="editar" title="Editar"><i class="bi bi-pencil"></i></button>';
if(p||admin)h+='<button type="button" data-a="eliminar" title="Eliminar"><i class="bi bi-trash"></i></button>';
h+='</div>';
}
h+='</div>';
if(s.acceso==='escribir'){
h+='<div class="chat-reac-rapidas">';
s.EMO_RAPIDOS.forEach(function(e){h+='<button type="button" data-a="reaccionar" data-e="'+e+'">'+e+'</button>'});
h+='</div>';
}
if((m.reacciones||[]).length){
h+='<div class="chat-reacs">';
m.reacciones.forEach(function(r){h+='<button type="button"'+(s.acceso==='escribir'?' data-a="reaccionar"':'')+' data-e="'+r.e+'" class="'+(r.yo?'mia':'')+'">'+r.e+' <small>'+r.n+'</small></button>'});
h+='</div>';
}
h+='<div class="chat-hora">'+esc(String(m.created_at||'').substring(11,16))+(m.editado?' · editado':'')+'</div></div>';
return h;
},
_ir:function(id){
var $e=$('#m-'+id);
if(!$e.length){Toast.info('Ese mensaje está más arriba en la conversación');return}
if($e[0].scrollIntoView)$e[0].scrollIntoView({block:'center'});
$e.addClass('resaltado');setTimeout(function(){$e.removeClass('resaltado')},1500);
},

/* ---------- acciones sobre un mensaje ---------- */
_reaccionar:function(id,e){
var s=this;
Api.post('api/chat/mensajes.php',{accion:'reaccionar',mensaje_id:id,emoji:e}).then(function(){s._msgs()});
},
_responder:function(id){
var m=this.mm[id];if(!m)return;
if(this.edit)this._cancelarBarra();
this.resp={id:id,autor:m.autor,texto:m.mensaje||(m.archivo?'📎 '+m.archivo.nombre:'')};
this._barra();$('#ch-campo').focus();
},
_editar:function(id){
var m=this.mm[id];if(!m||!m.mensaje)return;
this._limpiarCompositor();
this.edit=id;
 $('#ch-campo').val(m.mensaje).trigger('input').focus();
this._barra();
},
_eliminar:function(id){
var s=this;
Swal.fire({title:'¿Eliminar este mensaje?',text:'Desaparecerá para todos en la conversación.',icon:'warning',showCancelButton:true,confirmButtonText:'Eliminar',cancelButtonText:'No'}).then(function(x){
if(x.isConfirmed)Api.post('api/chat/mensajes.php',{accion:'eliminar',mensaje_id:id}).then(function(r){Toast.success(r.mensaje);s._msgs();s._cargar(true)});
});
},

/* ---------- compositor: respuesta, edición, adjunto, emojis, menciones ---------- */
_limpiarCompositor:function(foco,conservar){
this.resp=null;this.edit=0;this.menc={};
if(!conservar){this.adj=null}
if(foco){this.adj=null; $('#ch-campo').val('').css('height','auto').focus()}
if(!conservar&&!foco)$('#ch-campo').val('').css('height','auto');
 $('#ch-emojis,#ch-menciones').addClass('d-none');
this._barra();
},
/* ✕ de la barra: quita respuesta / adjunto / edición sin perder lo que se está escribiendo (salvo al editar) */
_cancelarBarra:function(){
var ed=!!this.edit;
this.resp=null;this.edit=0;this.adj=null;
if(ed){this.menc={}; $('#ch-campo').val('').css('height','auto')}
this._barra();$('#ch-campo').focus();
},
_barra:function(){
var b=$('#ch-barra'),h='';
if(this.edit)h+='<div class="b"><i class="bi bi-pencil"></i><span>Editando mensaje</span><button type="button" data-x="1" title="Cancelar">✕</button></div>';
if(this.resp)h+='<div class="b"><i class="bi bi-reply"></i><span>Respondiendo a <b>'+esc(this.resp.autor||'')+'</b>: '+esc(String(this.resp.texto).substring(0,80))+'</span><button type="button" data-x="1" title="Cancelar">✕</button></div>';
if(this.adj)h+='<div class="b"><i class="bi bi-paperclip"></i><span><b>'+esc(this.adj.name||'imagen')+'</b> · '+this._tam(this.adj.size)+'</span><button type="button" data-x="1" title="Quitar">✕</button></div>';
b.html(h).toggleClass('d-none',!h);
},
_adjuntar:function(f){
if(f.size>10*1024*1024){Toast.warning('El archivo supera los 10 MB');return}
if(this.edit){Toast.warning('Termine de editar antes de adjuntar');return}
if(!f.name)f=new File([f],'captura.png',{type:f.type||'image/png'});
this.adj=f;this._barra();$('#ch-campo').focus();
},
_panelEmojis:function(cat){
var s=this,$p=$('#ch-emojis');
if(!cat&&!$p.hasClass('d-none')){$p.addClass('d-none');return}
cat=cat||'Caritas';
var h='<div class="tabs">';
Object.keys(s.EMO).forEach(function(c){h+='<button type="button" class="tab'+(c===cat?' activo':'')+'" data-c="'+c+'">'+c+'</button>'});
h+='</div><div class="grid">';
s.EMO[cat].split(' ').forEach(function(e){h+='<button type="button" class="e" data-e="'+e+'">'+e+'</button>'});
$p.html(h+'</div>').removeClass('d-none');
},
_insertar:function(t){
var ta=$('#ch-campo')[0],a=ta.selectionStart==null?ta.value.length:ta.selectionStart,b=ta.selectionEnd==null?a:ta.selectionEnd;
ta.value=ta.value.slice(0,a)+t+ta.value.slice(b);
ta.focus();ta.selectionStart=ta.selectionEnd=a+t.length;
 $(ta).trigger('input');
},
_candidatos:function(q){
var s=this,yoId=s.yo?s.yo.id:0,n=function(x){return String(x||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'')};
if(!s.sala||s.acceso!=='escribir')return [];
q=n(q);
return (s.sala.miembros||[]).filter(function(m){return m.id!==yoId&&n(m.nombre).indexOf(q)>=0}).slice(0,6);
},
_menciones:function(){
var ta=$('#ch-campo')[0],ant=ta.value.slice(0,ta.selectionStart),mt=/(^|\s)@([^\s@]{0,25})$/.exec(ant),$d=$('#ch-menciones');
if(!mt){$d.addClass('d-none');return}
var c=this._candidatos(mt[2]);
if(!c.length){$d.addClass('d-none');return}
this.mi=0;
var h='';
c.forEach(function(m,i){h+='<div class="m'+(i===0?' on':'')+'" data-id="'+m.id+'"><b>'+esc(m.nombre)+'</b><small>'+esc(m.area||'')+'</small></div>'});
$d.html(h).removeClass('d-none');
},
_moverMencion:function(d){
var $m=$('#ch-menciones .m');if(!$m.length)return;
this.mi=(this.mi+d+$m.length)%$m.length;
$m.removeClass('on').eq(this.mi).addClass('on');
},
_elegirMencion:function(id){
var m=null,l=(this.sala&&this.sala.miembros)||[];
for(var i=0;i<l.length;i++)if(l[i].id===id)m=l[i];
if(!m)return;
var ta=$('#ch-campo')[0],pos=ta.selectionStart,ant=ta.value.slice(0,pos),post=ta.value.slice(pos);
ant=ant.replace(/(^|\s)@[^\s@]{0,25}$/,function(_,sp){return sp+'@'+m.nombre+' '});
ta.value=ant+post;ta.selectionStart=ta.selectionEnd=ant.length;
this.menc[id]=m.nombre;
 $('#ch-menciones').addClass('d-none');ta.focus();
},
_iconoSonido:function(){
var on=!window.ChatBtn||ChatBtn.sonido();
 $('#ch-sonido').html('<i class="bi bi-bell'+(on?'-fill':'-slash')+'"></i>').attr('title',on?'Sonido de avisos: activado':'Sonido de avisos: silenciado');
},
_diaTxt:function(f){
var d=new Date(),loc=function(x){return x.getFullYear()+'-'+('0'+(x.getMonth()+1)).slice(-2)+'-'+('0'+x.getDate()).slice(-2)};
if(f===loc(d))return 'Hoy';
d.setDate(d.getDate()-1);
if(f===loc(d))return 'Ayer';
return f.substring(8,10)+'/'+f.substring(5,7)+'/'+f.substring(0,4);
},
_cabecera:function(){
var s=this.sala,sub='',c=this._conv(s.id);
 $('#ch-titulo').text(c&&c.tipo==='directo'?c.nombre:s.nombre);
var n=(s.miembros||[]).length;
if(s.tipo==='directo'){
var o=(s.miembros||[]).filter(function(m){return Chat.yo&&m.id!==Chat.yo.id})[0];
sub=this.acceso==='leer'?'Supervisión de Sistemas · solo lectura':(o?((o.area||'')+(o.cargo?' · '+o.cargo:'')+(o.en_linea?' · en línea':'')):'');
}else if(s.tipo==='general'){sub='Todo el personal · '+n+' personas';}
else if(s.tipo==='area'){sub='Canal del área · '+n+' personas';}
else if(s.tipo==='entre_areas'){sub='Comunicación entre áreas · '+n+' personas';}
else if(s.tipo==='reunion'&&s.reunion){sub='Reunión · '+this._cuando(s.reunion.inicio)+' · '+s.reunion.duracion_min+' min';}
else{sub='Grupo · '+n+' personas';}
 $('#ch-sub').text(sub);
var av=$('#ch-aviso');
if(this.acceso==='leer'){
av.html('<i class="bi bi-eye"></i> Está viendo esta conversación como <b>Sistemas</b> (supervisión). No puede escribir en ella.').removeClass('d-none');
}else if(s.tipo==='directo'){
av.html('<i class="bi bi-shield-lock"></i> Conversación privada entre ustedes dos. Por seguridad, el área de Sistemas puede supervisar los chats directos.').removeClass('d-none');
}else{av.addClass('d-none')}
var u=$('#ch-unirse');
if(s.reunion&&s.reunion.estado!=='cancelada'&&s.reunion.estado!=='finalizada'&&/^https:\/\//i.test(s.reunion.enlace||'')){
u.attr('href',s.reunion.enlace).removeClass('d-none');
}else{u.addClass('d-none')}
},

/* ---------- panel derecho: datos y participantes ---------- */
_panel:function(){
var s=this.sala,h='',yoId=this.yo?this.yo.id:0,self=this;
h+='<div class="chat-der-cab"><b>Datos</b><button type="button" class="btn-close btn-close-sm" data-acc="cerrar-info"></button></div>';
if(s.descripcion&&s.tipo!=='reunion')h+='<p class="small text-muted px-3 mb-2">'+esc(s.descripcion)+'</p>';
if(s.tipo==='reunion'&&s.reunion){
var r=s.reunion,e=this.EST_REU[r.estado]||['',''];
h+='<div class="px-3 pb-2 small"><div class="mb-1"><span class="badge '+e[1]+'">'+e[0]+'</span></div>'+
'<div><b>'+esc(r.titulo)+'</b></div>'+(r.descripcion?'<div class="text-muted">'+esc(r.descripcion)+'</div>':'')+
'<div class="mt-1"><i class="bi bi-calendar-event"></i> '+esc(this._cuando(r.inicio))+' · '+r.duracion_min+' min</div>'+
'<div><i class="bi bi-person-badge"></i> Organiza: '+esc(r.organizador||'')+'</div>';
if(/^https:\/\//i.test(r.enlace||'')&&r.estado!=='cancelada'){
h+='<div class="mt-2"><a class="btn btn-success btn-sm w-100" href="'+esc(r.enlace)+'" target="_blank" rel="noopener noreferrer"><i class="bi bi-camera-video"></i> Unirse a la videollamada</a>'+
'<div class="text-muted mt-1" style="word-break:break-all">'+esc(r.enlace)+'</div></div>';
}
if((r.organizador_id===yoId||this.esSis)&&r.estado!=='cancelada'&&r.estado!=='finalizada'){
h+='<div class="d-flex gap-1 mt-2"><button class="btn btn-outline-secondary btn-sm flex-fill" data-acc="reprogramar" data-id="'+r.id+'"><i class="bi bi-calendar-event"></i> Reprogramar</button>'+
'<button class="btn btn-outline-danger btn-sm flex-fill" data-acc="cancelar-reunion" data-id="'+r.id+'"><i class="bi bi-x-circle"></i> Cancelar</button></div>';
}
h+='</div>';
}
var editable=(s.tipo==='grupo'||s.tipo==='reunion')&&s.soy_admin&&this.acceso==='escribir';
h+='<div class="px-3 d-flex justify-content-between align-items-center"><b class="small">Participantes ('+(s.miembros||[]).length+')</b>';
if(editable)h+='<button class="btn btn-outline-primary btn-sm py-0" data-acc="agregar"><i class="bi bi-person-plus"></i> Agregar</button>';
h+='</div><div class="chat-miembros">';
(s.miembros||[]).forEach(function(m){
h+='<div class="chat-miembro"><div class="chat-av chat-av-s">'+esc(self._iniciales(m.nombre))+(m.en_linea?'<span class="chat-on"></span>':'')+'</div>'+
'<div class="flex-grow-1" style="min-width:0"><div class="n text-truncate">'+esc(m.nombre)+(m.id===yoId?' (usted)':'')+(m.admin?' <span class="badge text-bg-warning text-dark">admin</span>':'')+'</div>'+
'<div class="u text-truncate">'+esc((m.area||'')+(m.cargo?' · '+m.cargo:''))+'</div></div>';
if(editable&&m.id!==yoId&&s.tipo==='grupo'){
h+='<button class="btn btn-sm btn-link p-0 me-1" title="'+(m.admin?'Quitar administración':'Hacer administrador')+'" data-acc="admin" data-id="'+m.id+'" data-val="'+(m.admin?0:1)+'"><i class="bi bi-star'+(m.admin?'-fill':'')+'"></i></button>';
}
if(editable&&m.id!==yoId)h+='<button class="btn btn-sm btn-link text-danger p-0" title="Quitar del grupo" data-acc="quitar" data-id="'+m.id+'"><i class="bi bi-x-circle"></i></button>';
if(m.id!==yoId&&s.tipo!=='directo'&&this.acceso==='escribir')h+='<button class="btn btn-sm btn-link p-0 ms-1" title="Escribirle en privado" data-acc="privado" data-id="'+m.id+'"><i class="bi bi-chat-left-text"></i></button>';
h+='</div>';
},this);
h+='</div>';
if((s.tipo==='grupo'||s.tipo==='reunion')&&this.acceso==='escribir'){
h+='<div class="p-3 d-grid gap-1">';
if(s.tipo==='grupo'&&s.soy_admin)h+='<button class="btn btn-outline-secondary btn-sm" data-acc="renombrar"><i class="bi bi-pencil"></i> Editar nombre del grupo</button>';
if(s.tipo==='grupo')h+='<button class="btn btn-outline-warning btn-sm" data-acc="salir"><i class="bi bi-box-arrow-left"></i> Salir del grupo</button>';
if(s.tipo==='grupo'&&s.soy_admin)h+='<button class="btn btn-outline-danger btn-sm" data-acc="archivar"><i class="bi bi-trash"></i> Cerrar el grupo</button>';
h+='</div>';
}
 $('#ch-info').html(h);
},
_accion:function(a,id,val){
var s=this,sid=s.sid;
if(a==='cerrar-info'){$('#ch-info').addClass('d-none');return}
if(a==='privado'){s._directo(id);return}
if(a==='agregar'){s._formAgregar();return}
if(a==='admin'){
Api.post('api/chat/salas.php',{accion:'admin',sala_id:sid,usuario_id:id,valor:+val}).then(function(r){Toast.success(r.mensaje);s._recargarSala()});
return}
if(a==='quitar'){
Swal.fire({title:'¿Quitar del grupo?',icon:'question',showCancelButton:true,confirmButtonText:'Quitar',cancelButtonText:'No'}).then(function(x){
if(x.isConfirmed)Api.post('api/chat/salas.php',{accion:'quitar',sala_id:sid,usuario_id:id}).then(function(r){Toast.success(r.mensaje);s._recargarSala()});
});return}
if(a==='renombrar'){s._formNombre();return}
if(a==='salir'){
Swal.fire({title:'¿Salir del grupo?',text:'Dejará de recibir sus mensajes.',icon:'warning',showCancelButton:true,confirmButtonText:'Salir',cancelButtonText:'No'}).then(function(x){
if(x.isConfirmed)Api.post('api/chat/salas.php',{accion:'salir',sala_id:sid}).then(function(r){Toast.success(r.mensaje);s._cerrarSala()});
});return}
if(a==='archivar'){
Swal.fire({title:'¿Cerrar el grupo?',text:'Desaparecerá para todos sus participantes.',icon:'warning',showCancelButton:true,confirmButtonText:'Cerrar grupo',cancelButtonText:'No'}).then(function(x){
if(x.isConfirmed)Api.post('api/chat/salas.php',{accion:'archivar',sala_id:sid}).then(function(r){Toast.success(r.mensaje);s._cerrarSala()});
});return}
if(a==='cancelar-reunion'){
Swal.fire({title:'¿Cancelar la reunión?',text:'Se avisará a todos los invitados.',icon:'warning',showCancelButton:true,confirmButtonText:'Cancelar reunión',cancelButtonText:'No'}).then(function(x){
if(x.isConfirmed)Api.post('api/chat/reuniones.php',{accion:'cancelar',id:id}).then(function(r){Toast.success(r.mensaje);s._recargarSala();s._cargar(true)});
});return}
if(a==='reprogramar'){s._formReprogramar(id);return}
},
_recargarSala:function(){this.ultimo=0;this._msgs(true);this._cargar(true)},
_cerrarSala:function(){
this.sid=0;this.sala=null;
 $('#ch-conv').addClass('d-none');$('#ch-vacio').removeClass('d-none');$('#ch-info').addClass('d-none');$('#chat-app').removeClass('con-conv');
this._cargar(true);
},

/* ---------- enviar ---------- */
_enviar:function(){
var s=this;
if(!s.sid||s._env)return;
var m=$.trim($('#ch-campo').val()||'');
if(s.edit){
if(!m){Toast.warning('Escriba el mensaje');return}
s._env=true;
Api.post('api/chat/mensajes.php',{accion:'editar',mensaje_id:s.edit,mensaje:m}).then(function(){
s._env=false;s._limpiarCompositor(true);s._msgs();
},function(){s._env=false});
return;
}
if(!m&&!s.adj)return;
var ids=Object.keys(s.menc).filter(function(i){return m.indexOf('@'+s.menc[i])>=0});
var fin=function(){s._env=false;s._limpiarCompositor(true);s._msgs();s._cargar(true)};
s._env=true;
if(s.adj){
var fd=new FormData();
fd.append('accion','enviar');fd.append('sala_id',s.sid);fd.append('mensaje',m);fd.append('archivo',s.adj,s.adj.name||'captura.png');
if(s.resp)fd.append('responde_a',s.resp.id);
ids.forEach(function(i){fd.append('menciones[]',i)});
Api.postFiles('api/chat/mensajes.php',fd).then(fin,function(){s._env=false});
}else{
var d={accion:'enviar',sala_id:s.sid,mensaje:m,menciones:ids};
if(s.resp)d.responde_a=s.resp.id;
Api.post('api/chat/mensajes.php',d).then(fin,function(){s._env=false});
}
},

/* ---------- nuevo ---------- */
_nuevo:function(t){
if(t==='directo')this._formDirecto();
else if(t==='area')this._formArea();
else if(t==='grupo')this._formGrupo();
else if(t==='reunion')this._formReunion();
},
_directo:function(uid){
var s=this;
Api.post('api/chat/mensajes.php',{accion:'iniciar_directo',usuario_id:uid}).then(function(r){
Modal.cerrar();
s._cargar(true,function(){s._abrir(r.sala_id)});
});
},
_formDirecto:function(){
var s=this,h='<input type="search" id="nd-q" class="form-control form-control-sm mb-2" placeholder="Buscar por nombre, área o cargo…" autocomplete="off"><div class="chat-pick" id="nd-lista">';
s.usuarios.forEach(function(u){
h+='<div class="chat-pick-item" data-u="'+u.id+'" data-t="'+esc((u.nombre+' '+(u.area||'')+' '+(u.cargo||'')).toLowerCase())+'">'+
'<div class="chat-av chat-av-s">'+esc(s._iniciales(u.nombre))+(u.en_linea?'<span class="chat-on"></span>':'')+'</div>'+
'<div><div class="n">'+esc(u.nombre)+'</div><div class="u">'+esc((u.area||'Sin área')+(u.cargo?' · '+u.cargo:''))+'</div></div></div>';
});
if(!s.usuarios.length)h+='<div class="text-muted small p-2">No hay otros usuarios activos.</div>';
h+='</div>';
Modal.abrir('Chat con una persona',h,'modal-md');
 $('#nd-q').on('input',function(){var q=$.trim(this.value).toLowerCase();$('#nd-lista .chat-pick-item').each(function(){$(this).toggle(String($(this).data('t')).indexOf(q)>=0)})}).focus();
 $('#nd-lista').on('click','.chat-pick-item',function(){s._directo(+$(this).data('u'))});
},
_formArea:function(){
var s=this,h='<p class="small text-muted">Se abre el canal con <b>todo el personal</b> del área. Si elige otra distinta a la suya, se crea un canal entre ambas áreas.</p><div class="chat-pick" id="na-lista">';
s.areas.forEach(function(a){
h+='<div class="chat-pick-item" data-a="'+a.id+'"><div class="chat-av chat-av-s tipo-area"><i class="bi bi-building"></i></div>'+
'<div><div class="n">'+esc(a.nombre)+(a.mia?' <span class="badge text-bg-primary">Mi área</span>':'')+'</div>'+
'<div class="u">'+(a.mia?'Canal de su área':(s.yo&&s.yo.area_id?'Canal '+esc((s._nomArea(s.yo.area_id)||'mi área'))+' ↔ '+esc(a.nombre):'Canal del área'))+'</div></div></div>';
});
h+='</div>';
Modal.abrir('Hablar con un área',h,'modal-md');
 $('#na-lista').on('click','.chat-pick-item',function(){
var id=+$(this).data('a');
Api.post('api/chat/mensajes.php',{accion:'iniciar_area',area_id:id}).then(function(r){
Modal.cerrar();
s._cargar(true,function(){s._abrir(r.sala_id)});
});
});
},
_nomArea:function(id){
for(var i=0;i<this.areas.length;i++)if(this.areas[i].id===id)return this.areas[i].nombre;
return '';
},

/* selector reutilizable de personas / áreas completas */
_picker:function(excluir){
var s=this,ex=excluir||[],h='<input type="search" id="pk-q" class="form-control form-control-sm mb-2" placeholder="Buscar persona o área…" autocomplete="off">'+
'<div class="chat-pick chat-pick-chk" id="pk-lista">'+
'<div class="chat-pick-tit">Áreas completas</div>';
s.areas.forEach(function(a){
h+='<label class="chat-pick-item" data-t="'+esc(a.nombre.toLowerCase())+'"><input type="checkbox" class="form-check-input pk-a" value="'+a.id+'">'+
'<div class="chat-av chat-av-s tipo-area"><i class="bi bi-building"></i></div><div><div class="n">'+esc(a.nombre)+'</div><div class="u">Todo el personal del área</div></div></label>';
});
h+='<div class="chat-pick-tit">Personas</div>';
s.usuarios.forEach(function(u){
if(ex.indexOf(u.id)>=0)return;
h+='<label class="chat-pick-item" data-t="'+esc((u.nombre+' '+(u.area||'')+' '+(u.cargo||'')).toLowerCase())+'"><input type="checkbox" class="form-check-input pk-u" value="'+u.id+'">'+
'<div class="chat-av chat-av-s">'+esc(s._iniciales(u.nombre))+(u.en_linea?'<span class="chat-on"></span>':'')+'</div>'+
'<div><div class="n">'+esc(u.nombre)+'</div><div class="u">'+esc((u.area||'Sin área')+(u.cargo?' · '+u.cargo:''))+'</div></div></label>';
});
h+='</div><div class="small text-muted mt-1" id="pk-cuenta">Nadie elegido</div>';
return h;
},
_pickerEventos:function(){
var cuenta=function(){
var u=$('#pk-lista .pk-u:checked').length,a=$('#pk-lista .pk-a:checked').length;
 $('#pk-cuenta').text(!u&&!a?'Nadie elegido':(u?u+' persona(s)':'')+(u&&a?' + ':'')+(a?a+' área(s) completa(s)':''));
};
 $('#pk-q').on('input',function(){var q=$.trim(this.value).toLowerCase();$('#pk-lista .chat-pick-item').each(function(){$(this).toggle(String($(this).data('t')).indexOf(q)>=0)})});
 $('#pk-lista').on('change','input',cuenta);
},
_picked:function(){
return{
usuarios:$('#pk-lista .pk-u:checked').map(function(){return this.value}).get(),
areas:$('#pk-lista .pk-a:checked').map(function(){return this.value}).get()
};
},
_formGrupo:function(){
var s=this,h='<div class="mb-2"><label class="form-label small mb-1">Nombre del grupo *</label><input id="ng-nombre" class="form-control form-control-sm" maxlength="120"></div>'+
'<div class="mb-2"><label class="form-label small mb-1">Descripción</label><input id="ng-desc" class="form-control form-control-sm" maxlength="255"></div>'+
'<label class="form-label small mb-1">Participantes *</label>'+s._picker()+
'<div class="text-end mt-3"><button class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button> <button class="btn btn-primary btn-sm" id="ng-ok"><i class="bi bi-people"></i> Crear grupo</button></div>';
Modal.abrir('Crear un grupo',h,'modal-lg');
s._pickerEventos();
 $('#ng-ok').on('click',function(){
var p=s._picked();
Api.post('api/chat/salas.php',{accion:'crear',nombre:$('#ng-nombre').val(),descripcion:$('#ng-desc').val(),usuarios:p.usuarios,areas:p.areas}).then(function(r){
Modal.cerrar();Toast.success(r.mensaje);
s._cargar(true,function(){s._abrir(r.sala_id)});
},function(e){s._errores(e)});
});
},
_formAgregar:function(){
var s=this,ya=(s.sala.miembros||[]).map(function(m){return m.id});
var h=s._picker(ya)+'<div class="text-end mt-3"><button class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button> <button class="btn btn-primary btn-sm" id="ag-ok"><i class="bi bi-person-plus"></i> Agregar</button></div>';
Modal.abrir('Agregar personas a «'+s.sala.nombre+'»',h,'modal-lg');
s._pickerEventos();
 $('#ag-ok').on('click',function(){
var p=s._picked();
Api.post('api/chat/salas.php',{accion:'invitar',sala_id:s.sid,usuarios:p.usuarios,areas:p.areas}).then(function(r){
Modal.cerrar();Toast.success(r.mensaje);s._recargarSala();
});
});
},
_formNombre:function(){
var s=this,h='<div class="mb-2"><label class="form-label small mb-1">Nombre *</label><input id="en-nombre" class="form-control form-control-sm" maxlength="120" value="'+esc(s.sala.nombre)+'"></div>'+
'<div class="mb-2"><label class="form-label small mb-1">Descripción</label><input id="en-desc" class="form-control form-control-sm" maxlength="255" value="'+esc(s.sala.descripcion||'')+'"></div>'+
'<div class="text-end"><button class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button> <button class="btn btn-primary btn-sm" id="en-ok">Guardar</button></div>';
Modal.abrir('Editar grupo',h,'modal-md');
 $('#en-ok').on('click',function(){
Api.post('api/chat/salas.php',{accion:'editar',sala_id:s.sid,nombre:$('#en-nombre').val(),descripcion:$('#en-desc').val()}).then(function(r){
Modal.cerrar();Toast.success(r.mensaje);s._recargarSala();
},function(e){s._errores(e)});
});
},
_hoy:function(){
var d=new Date();
return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2);
},
_formReunion:function(){
var s=this,d=new Date(Date.now()+3600000),hh=('0'+d.getHours()).slice(-2)+':00';
var h='<div class="row g-2"><div class="col-12"><label class="form-label small mb-1">Motivo de la reunión *</label><input id="nr-titulo" class="form-control form-control-sm" maxlength="120"></div>'+
'<div class="col-12"><label class="form-label small mb-1">Detalle / agenda</label><input id="nr-desc" class="form-control form-control-sm" maxlength="255"></div>'+
'<div class="col-6 col-md-4"><label class="form-label small mb-1">Fecha *</label><input type="date" id="nr-fecha" class="form-control form-control-sm" min="'+s._hoy()+'" value="'+s._hoy()+'"></div>'+
'<div class="col-6 col-md-3"><label class="form-label small mb-1">Hora *</label><input type="time" id="nr-hora" class="form-control form-control-sm" value="'+hh+'"></div>'+
'<div class="col-6 col-md-2"><label class="form-label small mb-1">Duración</label><select id="nr-dur" class="form-select form-select-sm"><option value="15">15 min</option><option value="30">30 min</option><option value="45">45 min</option><option value="60" selected>1 h</option><option value="90">1 h 30</option><option value="120">2 h</option><option value="180">3 h</option></select></div>'+
'<div class="col-6 col-md-3"><label class="form-label small mb-1">Enlace (opcional)</label><input id="nr-enlace" class="form-control form-control-sm" placeholder="https://… (si lo deja vacío se crea uno)"></div></div>'+
'<label class="form-label small mb-1 mt-2">Invitados *</label>'+s._picker()+
'<div class="text-end mt-3"><button class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button> <button class="btn btn-primary btn-sm" id="nr-ok"><i class="bi bi-camera-video"></i> Programar y avisar</button></div>';
Modal.abrir('Programar una reunión',h,'modal-lg');
s._pickerEventos();
 $('#nr-ok').on('click',function(){
var p=s._picked();
Api.post('api/chat/reuniones.php',{accion:'crear',titulo:$('#nr-titulo').val(),descripcion:$('#nr-desc').val(),fecha:$('#nr-fecha').val(),hora:$('#nr-hora').val(),
duracion_min:$('#nr-dur').val(),enlace:$('#nr-enlace').val(),usuarios:p.usuarios,areas:p.areas}).then(function(r){
Modal.cerrar();Toast.success(r.mensaje);
s.tab='reuniones'; $('#ch-tabs button').removeClass('activo').filter('[data-tab=reuniones]').addClass('activo');
s._cargar(true,function(){s._abrir(r.sala_id)});
},function(e){s._errores(e)});
});
},
_formReprogramar:function(rid){
var s=this,r=s.sala.reunion,f=String(r.inicio).substring(0,10),hh=String(r.inicio).substring(11,16);
var h='<div class="row g-2"><div class="col-6"><label class="form-label small mb-1">Fecha *</label><input type="date" id="rp-fecha" class="form-control form-control-sm" min="'+s._hoy()+'" value="'+esc(f)+'"></div>'+
'<div class="col-6"><label class="form-label small mb-1">Hora *</label><input type="time" id="rp-hora" class="form-control form-control-sm" value="'+esc(hh)+'"></div>'+
'<div class="col-6"><label class="form-label small mb-1">Duración (min)</label><input type="number" id="rp-dur" class="form-control form-control-sm" min="5" max="480" value="'+r.duracion_min+'"></div></div>'+
'<div class="text-end mt-3"><button class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button> <button class="btn btn-primary btn-sm" id="rp-ok">Reprogramar y avisar</button></div>';
Modal.abrir('Reprogramar reunión',h,'modal-md');
 $('#rp-ok').on('click',function(){
Api.post('api/chat/reuniones.php',{accion:'reprogramar',id:rid,fecha:$('#rp-fecha').val(),hora:$('#rp-hora').val(),duracion_min:$('#rp-dur').val()}).then(function(x){
Modal.cerrar();Toast.success(x.mensaje);s._recargarSala();
},function(e){s._errores(e)});
});
},
_errores:function(e){
var er=e&&e.cuerpo&&e.cuerpo.errors;
if(er){var m=[];Object.keys(er).forEach(function(k){m.push(er[k])});if(m.length)Toast.warning(m.join(' · '))}
}
};
App.registrar('chat',Chat);
