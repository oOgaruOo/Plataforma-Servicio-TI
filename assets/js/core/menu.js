/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Menú lateral dinámico
   - Se arma solo con los permisos de la persona.
   - Secciones plegables (se recuerda cuáles) y buscador (Ctrl+K o «/»).
   - Favoritos con estrella (por usuario) y modo compacto (solo iconos).
   - Insignias con los pendientes de cada módulo (api/menu/insignias.php), que se actualizan solas.
   ============================================================ */
var MENU=[
{seccion:'General'},
{ruta:'dashboard/principal',icono:'speedometer2',texto:'Dashboard',permiso:null},
{ruta:'guias/principal',icono:'journal-text',texto:'Guias',permiso:null},
{ruta:'chat/principal',icono:'chat-dots',texto:'Chat',permiso:null},
{seccion:'Operacion'},
{ruta:'tickets/lista',icono:'life-preserver',texto:'Tickets',permiso:['tickets.ver','tickets.ver_propios','tickets.ver_area']},
{ruta:'capacitaciones/lista',icono:'mortarboard',texto:'Capacitaciones',permiso:['capacitaciones.solicitar','capacitaciones.ver_area','capacitaciones.ver','capacitaciones.gestionar']},
{ruta:'mantenimiento/lista',icono:'tools',texto:'Mantenimiento',permiso:['mantenimiento.ver']},
{seccion:'Inventario'},
{ruta:'equipos/lista',icono:'pc-display',texto:'Equipos',permiso:['equipos.ver']},
{ruta:'equipos/seguimiento',icono:'geo-alt',texto:'Seguimiento',permiso:['equipos.ver']},
{ruta:'software/lista',icono:'app-indicator',texto:'Software',permiso:['software.ver']},
{ruta:'proyectos/lista',icono:'kanban',texto:'Proyectos y Obras',permiso:['asignaciones.ver']},
{ruta:'asignaciones/lista',icono:'box-seam',texto:'Asignaciones',permiso:['asignaciones.ver']},
{ruta:'actas/lista',icono:'file-earmark-text',texto:'Actas',permiso:['actas.generar']},
{ruta:'etiquetas/plantillas',icono:'tags',texto:'Diseñador de Etiquetas',permiso:['configuracion.gestionar']},
{seccion:'Personal'},
{ruta:'personal/lista',icono:'people',texto:'Personal',permiso:['personal.ver']},
{ruta:'carnets/lista',icono:'person-vcard',texto:'Carnets',permiso:['personal.ver']},
{seccion:'Analisis'},
{ruta:'reportes/principal',icono:'graph-up-arrow',texto:'Reportes',permiso:['reportes.ver','reportes.ver_area']},
{seccion:'Administracion'},
{ruta:'usuarios/lista',icono:'person-gear',texto:'Usuarios',permiso:['usuarios.gestionar']},
{ruta:'configuracion/principal',icono:'sliders',texto:'Configuracion',permiso:['configuracion.gestionar']},
{ruta:'permisos/lista',icono:'key',texto:'Permisos',permiso:['configuracion.gestionar']},
{ruta:'licencia/principal',icono:'patch-check',texto:'Licencia y rubro',permiso:['configuracion.gestionar']},
{ruta:'auditoria/lista',icono:'journal-text',texto:'Auditoria',permiso:['auditoria.ver']}
];
var Menu={
_ins:{},_rutaAct:null,_ti:null,_ult:0,_dom:false,
/* ---------- almacenamiento local (por navegador) ---------- */
_k:function(n){return 'sigti_menu_'+n+'_'+((typeof App!=='undefined'&&App.usuario&&App.usuario.id)||0)},
_lee:function(n,d){try{var v=localStorage.getItem(this._k(n));return v?JSON.parse(v):d}catch(e){return d}},
_guarda:function(n,v){try{localStorage.setItem(this._k(n),JSON.stringify(v))}catch(e){}},
_sin:function(s){return String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'')},
/* ---------- armado ---------- */
_items:function(){
var s=this,sec=null,out=[];
MENU.forEach(function(i){
if(i.seccion){sec=i.seccion;return}
if(!s._puede(i.permiso))return;
if(typeof Sistema!=='undefined'&&Sistema.apagada(i.ruta))return;
out.push($.extend({sec:sec},i,{texto:(typeof Terminos!=='undefined'?Terminos.t(i.texto):i.texto)}));
});
return out;
},
construir:function(){
var s=this,items=s._items(),cerradas=s._lee('cerradas',[]),favs=s._lee('favs',[]),h='';
s._lista=items;
h+='<div id="menu-favs"></div>';
var secs=[];
items.forEach(function(i){if(secs.indexOf(i.sec)<0)secs.push(i.sec)});
secs.forEach(function(sec){
var abierta=cerradas.indexOf(sec)<0;
h+='<div class="menu-grupo'+(abierta?'':' plegado')+'" data-sec="'+esc(sec)+'">'+
'<button type="button" class="menu-seccion" aria-expanded="'+(abierta?'true':'false')+'"><span>'+esc(sec)+'</span><i class="bi bi-chevron-down"></i></button>'+
'<div class="menu-grupo-items">';
items.filter(function(i){return i.sec===sec}).forEach(function(i){h+=s._htmlItem(i,favs)});
h+='</div></div>';
});
h+='<div id="menu-vacio" class="menu-vacio hidden">Nada coincide con su búsqueda.</div>';
 $('#menu-nav').html(h);
s._pintaFavs();
s._pintaInsignias();
s._eventos();
s._modoInicial();
s.actualizar();
if(s._ti)clearInterval(s._ti);
s._ti=setInterval(function(){if(!document.hidden&&App.usuario)s.actualizar()},90000);
if(s._rutaAct)s.activo(s._rutaAct);
},
_htmlItem:function(i,favs){
var esFav=favs.indexOf(i.ruta)>=0;
return '<a href="#" class="menu-item" data-ruta="'+i.ruta+'" title="'+esc(i.texto)+'"><i class="bi bi-'+i.icono+'"></i><span class="menu-txt">'+esc(i.texto)+'</span>'+
'<span class="menu-badge hidden" data-b="'+i.ruta+'"></span>'+
'<span class="menu-fav'+(esFav?' on':'')+'" data-fav="'+i.ruta+'" title="'+(esFav?'Quitar de favoritos':'Fijar en favoritos')+'"><i class="bi bi-star'+(esFav?'-fill':'')+'"></i></span></a>';
},
_eventos:function(){
var s=this;
 $('#menu-nav').off('click.mn')
.on('click.mn','.menu-fav',function(e){e.preventDefault();e.stopPropagation();s._alternarFav($(this).attr('data-fav'))})
.on('click.mn','.menu-item',function(e){
e.preventDefault();
Router.ir($(this).attr('data-ruta'));
if(window.innerWidth<992)$('body').addClass('sidebar-oculto');
})
.on('click.mn','.menu-seccion',function(){s._alternarSeccion($(this).closest('.menu-grupo'))});
if(s._dom)return;
s._dom=true;
 $(document).on('input.mn','#menu-buscar',function(){s._filtrar(this.value)})
.on('keydown.mn','#menu-buscar',function(e){
if(e.key==='Escape'){this.value='';s._filtrar('');this.blur()}
else if(e.key==='Enter'){var p=$('#menu-nav .menu-grupo:not(.menu-favs-g):not(.oculto) .menu-item:not(.oculto)').first();if(p.length)p.trigger('click')}
})
.on('keydown.mn',function(e){
if(!App.usuario)return;
var t=(e.target.tagName||'').toLowerCase(),escribiendo=t==='input'||t==='textarea'||t==='select'||e.target.isContentEditable;
if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();s._enfocarBuscar()}
else if(e.key==='/'&&!escribiendo&&!e.ctrlKey&&!e.altKey&&!e.metaKey){e.preventDefault();s._enfocarBuscar()}
})
.on('click.mn','.menu-buscar-wrap i',function(){if($('body').hasClass('sidebar-mini'))s._enfocarBuscar()})
.on('click.mn','#menu-compacto',function(){s.compacto(!$('body').hasClass('sidebar-mini'))})
.on('click.mn','#sidebar-fondo',function(){$('body').addClass('sidebar-oculto')});
},
_enfocarBuscar:function(){
if($('body').hasClass('sidebar-oculto'))$('body').removeClass('sidebar-oculto');
if($('body').hasClass('sidebar-mini')&&window.innerWidth>=992)this.compacto(false);
 $('#menu-buscar').trigger('focus').select();
},
/* ---------- secciones plegables ---------- */
_alternarSeccion:function($g,forzar){
var plegar=forzar===undefined?!$g.hasClass('plegado'):!!forzar;
$g.toggleClass('plegado',plegar).find('.menu-seccion').attr('aria-expanded',plegar?'false':'true');
var c=[];
 $('#menu-nav .menu-grupo.plegado').each(function(){c.push($(this).attr('data-sec'))});
this._guarda('cerradas',c);
this._pintaInsignias();
},
/* ---------- favoritos ---------- */
_alternarFav:function(ruta){
var f=this._lee('favs',[]),i=f.indexOf(ruta);
if(i>=0)f.splice(i,1);else f.push(ruta);
this._guarda('favs',f);
 $('#menu-nav .menu-fav[data-fav="'+ruta+'"]').each(function(){
var on=f.indexOf(ruta)>=0;
 $(this).toggleClass('on',on).attr('title',on?'Quitar de favoritos':'Fijar en favoritos').find('i').attr('class','bi bi-star'+(on?'-fill':''));
});
this._pintaFavs();
},
_pintaFavs:function(){
var s=this,f=s._lee('favs',[]),h='';
var l=(s._lista||[]).filter(function(i){return f.indexOf(i.ruta)>=0});
if(l.length){
h='<div class="menu-grupo menu-favs-g"><div class="menu-seccion fijo"><span><i class="bi bi-star-fill"></i> Favoritos</span></div><div class="menu-grupo-items">';
l.forEach(function(i){h+=s._htmlItem(i,f).replace('class="menu-item"','class="menu-item en-favs"')});
h+='</div></div>';
}
 $('#menu-favs').html(h);
s._pintaInsignias();
if(s._rutaAct)s.activo(s._rutaAct);
},
/* ---------- buscador ---------- */
_filtrar:function(q){
var s=this;q=s._sin(q).trim();
 $('#menu-nav').toggleClass('filtrando',q!=='');
 $('#menu-buscar').closest('.menu-buscar-wrap').toggleClass('con-texto',q!=='');
if(q===''){ $('#menu-nav .menu-item').removeClass('oculto');$('#menu-nav .menu-grupo').removeClass('oculto');$('#menu-vacio').addClass('hidden');return}
var hay=0;
 $('#menu-nav .menu-grupo').each(function(){
var $g=$(this),n=0;
 $g.find('.menu-item').each(function(){
var ok=s._sin($(this).find('.menu-txt').text()).indexOf(q)>=0||s._sin($(this).attr('data-ruta')).indexOf(q)>=0;
 $(this).toggleClass('oculto',!ok);if(ok){n++;hay++}
});
 $g.toggleClass('oculto',n===0);
});
 $('#menu-vacio').toggleClass('hidden',hay>0);
},
/* ---------- modo compacto / movil ---------- */
compacto:function(v){
v=!!v;
 $('body').toggleClass('sidebar-mini',v);
 $('#menu-compacto').attr('title',v?'Expandir el menú':'Contraer el menú (solo iconos)').find('i').attr('class','bi bi-'+(v?'chevron-double-right':'chevron-double-left'));
try{localStorage.setItem('sigti_menu_mini',v?'1':'0')}catch(e){}
},
_modoInicial:function(){
var mini=false;
try{mini=localStorage.getItem('sigti_menu_mini')==='1'}catch(e){}
if(window.innerWidth>=992)this.compacto(mini);
else $('body').addClass('sidebar-oculto');
if(!$('#sidebar-fondo').length) $('body').append('<div id="sidebar-fondo"></div>');
},
/* ---------- insignias ---------- */
actualizar:function(){
var s=this;
if(!App.usuario)return;
s._ult=Date.now();
 $.ajax({url:BASE_URL+'api/menu/insignias.php',type:'GET',dataType:'json',global:false,cache:false})
.done(function(r){
if(!r||!r.success||!r.data)return;
var nuevo=r.data.insignias||{},chat=s._ins['chat/principal'];
s._ins=nuevo;
if(chat)s._ins['chat/principal']=chat;
s._pintaInsignias();
});
},
/* una insignia puesta a mano (p. ej. mensajes sin leer del chat) */
poner:function(ruta,n,nivel,titulo){
if(n>0)this._ins[ruta]={n:n,nivel:nivel||'azul',titulo:titulo||''};else delete this._ins[ruta];
this._pintaInsignias();
},
_pintaInsignias:function(){
var s=this;
 $('#menu-nav .menu-badge').each(function(){
var r=$(this).attr('data-b'),x=s._ins[r];
if(x&&x.n>0){
 $(this).text(x.n>99?'99+':x.n).attr('class','menu-badge '+(x.nivel||'ambar')).attr('title',x.titulo||'');
}else $(this).attr('class','menu-badge hidden').text('');
});
 $('#menu-nav .menu-grupo').each(function(){
var $g=$(this),t=0,rojo=false;
 $g.find('.menu-grupo-items .menu-item').each(function(){var x=s._ins[$(this).attr('data-ruta')];if(x&&x.n>0){t+=x.n;if(x.nivel==='rojo')rojo=true}});
 $g.find('.menu-seccion .menu-sec-n').remove();
if(t>0&&$g.hasClass('plegado')&&!$g.hasClass('menu-favs-g'))$g.find('.menu-seccion span').first().after('<em class="menu-sec-n'+(rojo?' rojo':'')+'">'+(t>99?'99+':t)+'</em>');
});
},
_puede:function(p){
if(!p)return true;
if(App.usuario&&App.usuario.rol==='super_admin')return true;
var l=Array.isArray(p)?p:[p];
return l.some(function(x){return App.permisos.indexOf(x)>=0});
},
activo:function(r){
this._rutaAct=r;
var $a= $('.menu-item').removeClass('activo').filter('[data-ruta="'+r+'"]').addClass('activo');
// que se vea: abre la sección donde está y la lleva a la vista
$a.each(function(){var $g=$(this).closest('.menu-grupo');if($g.hasClass('plegado'))Menu._alternarSeccion($g,false)});
var $p=$a.not('.en-favs').first();
if($p.length&&$p[0].scrollIntoView&&!$('#menu-nav').hasClass('filtrando'))try{$p[0].scrollIntoView({block:'nearest'})}catch(e){}
if(Date.now()-this._ult>20000)this.actualizar();
}
};
