/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var Notificaciones={
_t:null,
_ok:true,
_previo:-1,
iniciar:function(){
if(this._t||!App.usuario)return;
this._previo=-1;
this.refrescar();
var s=this;
this._t=setInterval(function(){s.refrescar()},15000);
},
detener:function(){
clearInterval(this._t);
this._t=null;
$('#badge-notif').addClass('hidden').text('');
$('#panel-notif-lista').html('<div class="panel-notif-vacio text-muted small">Sin notificaciones.</div>');
},
refrescar:function(){
if(!this._ok||!App.usuario)return;
var s=this;
$.getJSON(BASE_URL+'api/notificaciones/contar.php')
.done(function(r){
if(r.success){
var t=(r.data&&r.data.total)||0;
$('#badge-notif').text(t>99?'99+':t).toggleClass('hidden',t===0);
if(s._previo>=0&&t>s._previo){s._animar()}
s._previo=t;
}
})
.fail(function(x){
if(x.status===404)s._ok=false;
if(x.status===401){s.detener();App.sesionExpirada()}
});
},
_animar:function(){
var c=document.getElementById('icono-campana');
if(!c)return;
c.classList.remove('campana-anim');
void c.offsetWidth;
c.classList.add('campana-anim');
},
_cargarPanel:function(){
var s=this;
$.getJSON(BASE_URL+'api/notificaciones/listar.php')
.done(function(res){
if(!res.success){return}
var lista=(res.data&&res.data.notificaciones)||[];
var h='';
if(!lista.length){
h='<div class="panel-notif-vacio text-muted small">Sin notificaciones.</div>';
}else{
lista.forEach(function(n){
h+='<div class="panel-notif-item '+(n.leido==0?'no-leida':'')+'" data-id="'+n.id+'" data-url="'+esc(n.url||'')+'">'
+'<i class="bi '+(n.leido==0?'bi-record-circle-fill texto-punto':'bi-check-circle text-muted')+'"></i>'
+'<div class="flex-grow-1">'
+'<div class="small '+(n.leido==0?'fw-semibold':'')+'">'+esc(n.titulo)+'</div>'
+(n.mensaje?'<div class="small text-muted text-truncate">'+esc(n.mensaje)+'</div>':'')
+'<div class="panel-notif-fecha">'+esc(n.created_at)+'</div>'
+'</div></div>';
});
}
$('#panel-notif-lista').html(h);
s.refrescar();
})
.fail(function(){});
}
};

// Abrir panel -> cargar lista fresca
$(document).on('show.bs.dropdown','.dropdown-notif',function(){
Notificaciones._cargarPanel();
});

// Click en una notificacion -> marcar leida + ir a la url si tiene
$(document).on('click','.panel-notif-item',function(){
var $el=$(this),id=$el.data('id');
var url=String($el.data('url')||'').replace(/^[#\/]+/,'');
Api.post('api/notificaciones/marcar.php',{accion:'leida',id:id}).catch(function(){}).then(function(){
$el.removeClass('no-leida').find('.fw-semibold').removeClass('fw-semibold');
$el.find('.bi-record-circle-fill').removeClass('bi-record-circle-fill texto-punto').addClass('bi-check-circle text-muted');
Notificaciones.refrescar();
if(url){Router.ir(url)}
});
});

// Marcar todas
$(document).on('click','#np-todas',function(e){
e.stopPropagation();
Api.post('api/notificaciones/marcar.php',{accion:'todas'}).then(function(){
Toast.success('Todas marcadas como leídas');
Notificaciones._cargarPanel();
});
});
