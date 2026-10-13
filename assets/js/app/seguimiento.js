/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var Seguimiento={
tabla:null,
_timer:null,
init:function(params){
this._params=params||{};
this._c();
this._rtIniciar();
 $('#sg-filtro').on('change',function(){Seguimiento._c()});
if(this._params.tab==='verif'){var tv=document.getElementById('sg-tab-verif');if(tv&&window.bootstrap)bootstrap.Tab.getOrCreateInstance(tv).show()}
clearInterval(this._timer);
var s=this;
this._timer=setInterval(function(){
if(document.getElementById('tb-seg')&&!document.querySelector('.modal.show')){s._c(true);s._rt(true)}
},30000);
},
/* ============================================================
   RASTREO EN VIVO
   ============================================================ */
_rtDatos:[],
_rtStats:null,
_rtPuedeEditar:false,
_mapa:null,
_capa:null,
_rastro:null,
_marcas:{},
_rtPrimera:true,
_rtIniciar:function(){
var s=this;
s._rtPrimera=true;
 $('#rt-buscar').on('input',function(){if(s._rtTabla)s._rtTabla.search($(this).val()).draw()});
 $('#rt-filtro').on('change',function(){s._rtRender();s._rtMapa(true)});
 $('#btn-rt-refrescar').on('click',function(){s._rt()});
 $('#btn-rt-sedes').on('click',function(){s._rtSedes()});
 $('#btn-rt-guia').on('click',function(){s._rtGuia()});
 $('#rt-ver-todos').on('click',function(e){e.preventDefault();s._rastro&&s._rastro.clearLayers();s._rtMapa(true)});
 $('#seg-tabs button[data-bs-toggle="tab"]').on('shown.bs.tab',function(e){
if(e.target.id==='sg-tab-rastreo'&&s._mapa)s._mapa.invalidateSize();
if(e.target.id==='sg-tab-verif'&&$.fn.DataTable.isDataTable('#tb-seg'))$('#tb-seg').DataTable().columns.adjust();
});
s._rt();
},
_rtFiltrados:function(){
var f=$('#rt-filtro').val()||'todos';
return (this._rtDatos||[]).filter(function(e){
if(f==='todos')return true;
if(f==='perdidos')return e.perdido===1;
if(f==='con_mapa')return e.lat!=null&&e.lon!=null;
return e.conexion===f;
});
},
_rt:function(silencioso){
var s=this;
return Api.get('api/rastreo/panel.php').then(function(d){
s._rtDatos=d.equipos||[];
s._rtStats=d.stats||{};
s._rtPuedeEditar=!!d.puede_editar;
 $('#rt-stats [data-k]').each(function(){ $(this).text((d.stats||{})[$(this).data('k')]||0)});
 $('#rt-ultima').text('actualizado '+new Date().toLocaleTimeString());
var np=(d.stats||{}).perdidos||0;
 $('#rt-badge-perdidos').text(np).toggleClass('d-none',!np);
s._rtAlertaPerdidos();
s._rtRender();
s._rtMapa(s._rtPrimera);
if(s._rtPrimera){
s._rtPrimera=false;
var id=parseInt((s._params||{}).equipo_id,10);
if(id){s._params.equipo_id=null;s._rtEnfocar(id,true)}
}
}).catch(function(){});
},
_rtAlertaPerdidos:function(){
var l=(this._rtDatos||[]).filter(function(e){return e.perdido===1});
var $a= $('#rt-alerta-perdidos');
if(!l.length){$a.empty();return}
$a.html('<div class="alert alert-danger py-2"><b><i class="bi bi-exclamation-octagon"></i> Equipos '+(l.length===1?'marcado':'marcados')+' como perdido o robado</b>'+
l.map(function(e){
var donde=e.lat!=null?(e.ubic_rastreo||'ubicación en el mapa'):'sin ubicación todavía';
return '<div class="small mt-1"><b>'+esc(e.codigo)+'</b> ('+esc(e.perdido_motivo==='robo'?'ROBADO':'PERDIDO')+') &mdash; último reporte: '+esc(e.ultimo_reporte?e.hace:'nunca')+' &mdash; '+esc(donde)+
' <button class="btn btn-sm btn-outline-danger py-0 ms-1 btn-rt-mapa" data-id="'+e.id+'">Ubicar</button></div>'}).join('')+'</div>');
},
_rtConQuien:function(e){
if(e.destino_tipo==='proyecto'||e.destino_tipo==='obra'){
return '<span class="badge '+(e.destino_tipo==='obra'?'text-bg-warning text-dark':'text-bg-success')+'">'+(e.destino_tipo==='obra'?'OBRA':'PROYECTO')+'</span> <b class="small">'+esc(e.destino_nombre||'')+'</b>'+
(e.destino_responsable?'<br><span class="text-muted small">Resp.: '+esc(e.destino_responsable)+'</span>':'');
}
if(e.destino_tipo==='area')return '<span class="badge text-bg-info">ÁREA</span> <b class="small">'+esc(e.destino_nombre||'')+'</b>';
if(e.destino_tipo==='persona'||e.destino_nombre)return '<b class="small">'+esc(e.destino_nombre||'')+'</b>';
return '<span class="text-muted small">En stock</span>';
},
_rtBadgeConexion:function(e){
var col=e.conexion==='en_linea'?'success':e.conexion==='reciente'?'primary':e.conexion==='sin_senal'?'warning text-dark':'secondary';
var t={en_linea:'En línea',reciente:'Visto en 24 h',sin_senal:'Sin señal',sin_agente:'Sin agente'}[e.conexion]||e.conexion;
return '<span class="badge text-bg-'+col+'">'+t+'</span>';
},
_rtFuenteEf:function(f,p){return f==='gps'&&p&&+p>1500?'aprox':f},
_rtFuente:function(f,p){
f=this._rtFuenteEf(f,p);
var m={gps:['GPS / Wi-Fi','success'],aprox:['Aproximada','warning text-dark'],sede:['Sede conocida','primary'],ip:['IP aproximada','warning text-dark'],red:['Solo red','secondary']}[f];
return m?'<span class="badge text-bg-'+m[1]+'">'+m[0]+'</span>':'';
},
_rtRender:function(){
var s=this;
var filas=this._rtFiltrados().map(function(e){
var ipRep=e.ip_local||'',ipReg=e.ip_registrada||'';
var red='<div class="small">IP: <b>'+esc(ipRep||ipReg||'—')+'</b>'+
(ipRep&&ipReg&&ipRep!==ipReg?' <span class="badge text-bg-warning text-dark" title="En el inventario figura '+esc(ipReg)+'">≠ inventario</span>':'')+'</div>'+
'<div class="small text-muted">MAC '+esc(e.mac_address||e.mac||'—')+
(e.mac_coincide!=null&&+e.mac_coincide===0?' <span class="badge text-bg-danger" title="La MAC que reporta el equipo no es la registrada ('+esc(e.mac||'')+')">MAC distinta</span>':'')+'</div>'+
(e.hostname?'<div class="small text-muted">'+esc(e.hostname)+'</div>':'');
var est=s._rtBadgeConexion(e)+(e.perdido?' <span class="badge text-bg-danger">'+(e.perdido_motivo==='robo'?'ROBADO':'PERDIDO')+'</span>':'')+
'<div class="small text-muted">'+esc(e.hace||'')+'</div>';
var ub=e.ultimo_reporte?
'<div class="small">'+esc(e.ubic_rastreo||'Sin ubicación')+'</div>'+
'<div>'+s._rtFuente(e.fuente,e.precision_m)+(e.precision_m?' <span class="small text-muted">±'+esc(e.precision_m)+' m</span>':'')+'</div>'+
(e.sede?'<div class="small text-muted">Sede: '+esc(e.sede)+'</div>':''):
'<span class="text-muted small">Nunca reportó</span>';
var acc='<button class="btn btn-sm btn-outline-danger btn-rt-mapa me-1" data-id="'+e.id+'" title="Ubicar en el mapa"><i class="bi bi-crosshair"></i></button>'+
'<button class="btn btn-sm btn-outline-secondary btn-rt-det" data-id="'+e.id+'" title="Detalle y acciones"><i class="bi bi-sliders"></i></button>';
return [
'<strong>'+esc(e.codigo)+'</strong><br><span class="text-muted small">'+esc(e.tipo||'')+' '+esc(e.marca||'')+'</span>',
red,est,ub,s._rtConQuien(e),acc];
});
var pag=0,q=$('#rt-buscar').val()||'';
if($.fn.DataTable.isDataTable('#tb-rt')){pag=$('#tb-rt').DataTable().page();$('#tb-rt').DataTable().clear().destroy()}
 $('#tb-rt tbody').empty();
s._rtTabla=$('#tb-rt').DataTable({data:filas,language:DATATABLES_ES,pageLength:15,order:[],
columns:[{title:'Equipo'},{title:'Red (IP / MAC)'},{title:'Estado'},{title:'Última ubicación'},{title:'Con quien'},{title:'',orderable:false,className:'text-center'}]});
if(q)s._rtTabla.search(q).draw();
if(pag&&pag<s._rtTabla.page.info().pages)s._rtTabla.page(pag).draw('page');
},
_rtMapa:function(centrar){
var el=document.getElementById('rt-mapa');
if(!el)return;
if(typeof L==='undefined'){
el.innerHTML='<div class="p-4 text-center text-muted"><i class="bi bi-wifi-off fs-3"></i><br>No se pudo cargar el mapa (se necesita conexión a internet en este navegador). La tabla sigue mostrando la última ubicación de cada equipo.</div>';
return;
}
if(this._mapa&&this._mapa.getContainer()!==el){try{this._mapa.remove()}catch(x){}this._mapa=null}
if(!this._mapa){
this._mapa=L.map(el).setView([-9.19,-75.01],5);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(this._mapa);
this._capa=L.layerGroup().addTo(this._mapa);
this._rastro=L.layerGroup().addTo(this._mapa);
centrar=true;
}
this._capa.clearLayers();
this._marcas={};
var pts=[],s=this;
this._rtFiltrados().forEach(function(e){
if(e.lat==null||e.lon==null)return;
var col=e.perdido?'#dc2626':e.conexion==='en_linea'?'#16a34a':e.conexion==='reciente'?'#2563eb':'#d97706';
var ll=[e.lat,e.lon];
var fe=s._rtFuenteEf(e.fuente,e.precision_m);
if(fe==='aprox'){L.circle(ll,{radius:Math.min(+e.precision_m,30000),color:'#d97706',weight:1,dashArray:'4',fillOpacity:.06,interactive:false}).addTo(s._capa)}
else if(fe==='ip'){L.circle(ll,{radius:5000,color:'#94a3b8',weight:1,dashArray:'4',fillOpacity:.05,interactive:false}).addTo(s._capa)}
else if(e.precision_m&&+e.precision_m<=2000){L.circle(ll,{radius:+e.precision_m,color:col,weight:1,fillOpacity:.1,interactive:false}).addTo(s._capa)}
var m=L.circleMarker(ll,{radius:e.perdido?11:8,color:'#fff',weight:2,fillColor:col,fillOpacity:.95}).addTo(s._capa);
m.bindPopup('<b>'+esc(e.codigo)+'</b>'+(e.perdido?' <span class="badge text-bg-danger">'+(e.perdido_motivo==='robo'?'ROBADO':'PERDIDO')+'</span>':'')+
'<br>'+esc(e.ubic_rastreo||'')+'<br><span class="text-muted">'+esc(e.hace||'')+'</span>'+
(fe==='ip'?'<br><i>Ubicación aproximada por IP</i>':'')+(fe==='aprox'?'<br><i class="text-warning">Ubicación aproximada (±'+esc(e.precision_m>=1000?(e.precision_m/1000).toFixed(1)+' km':e.precision_m+' m')+'): no es la dirección exacta</i>':'')+
'<br><button class="btn btn-sm btn-outline-secondary mt-1 btn-rt-det" data-id="'+e.id+'">Detalle</button>');
s._marcas[e.id]=m;
pts.push(ll);
});
if(centrar&&pts.length)this._mapa.fitBounds(pts,{padding:[30,30],maxZoom:16});
setTimeout(function(){if(s._mapa)s._mapa.invalidateSize()},50);
},
_rtEnfocar:function(id,abrirDetalle){
var s=this;
var e=(this._rtDatos||[]).filter(function(x){return +x.id===+id})[0];
if(!e){Toast.warning('El equipo no tiene IP/MAC registrada ni rastreo activado');return}
if(e.lat==null||e.lon==null){
Toast.info('Este equipo todavía no reportó coordenadas'+(e.ip_local?' (última IP '+e.ip_local+')':''));
if(abrirDetalle)s._rtDetalle(id);
return;
}
if(this._mapa){
this._mapa.setView([e.lat,e.lon],Math.max(this._mapa.getZoom(),15));
if(s._marcas[e.id])s._marcas[e.id].openPopup();
var cont=document.getElementById('rt-mapa');
if(cont&&cont.scrollIntoView)cont.scrollIntoView({block:'nearest'});
}
s._rtRecorrido(id,true);
},
_rtRecorrido:function(id,sinAjustar){
var s=this;
return Api.get('api/rastreo/gestionar.php',{accion:'estado',equipo_id:id}).then(function(g){
if(!s._rastro||typeof L==='undefined')return;
s._rastro.clearLayers();
var pts=(g.rastreos||[]).filter(function(r){return r.latitud!=null&&r.longitud!=null})
.map(function(r){return {ll:[+r.latitud,+r.longitud],f:r.created_at,u:r.ubicacion}}).reverse();
if(pts.length<2){if(!sinAjustar)Toast.info('Solo hay un punto registrado para este equipo');return}
L.polyline(pts.map(function(p){return p.ll}),{color:'#dc2626',weight:3,opacity:.7,dashArray:'6 6'}).addTo(s._rastro);
pts.forEach(function(p){L.circleMarker(p.ll,{radius:4,color:'#dc2626',weight:1,fillColor:'#fff',fillOpacity:1}).bindTooltip(esc(p.f)+(p.u?' · '+esc(p.u):'')).addTo(s._rastro)});
if(!sinAjustar&&s._mapa)s._mapa.fitBounds(pts.map(function(p){return p.ll}),{padding:[30,30],maxZoom:17});
}).catch(function(){});
},
_rtDetalle:function(id){
var s=this;
Api.get('api/rastreo/gestionar.php',{accion:'estado',equipo_id:id}).then(function(g){
var q=g.equipo||{},c=g.config,con=g.conexion||{},r0=(g.rastreos||[])[0]||null,ed=!!g.puede_editar;
var cel=function(t,x){return '<div class="col-6 col-md-4"><div class="border rounded p-2 h-100"><div class="text-muted small">'+t+'</div><strong class="small">'+(x===''||x==null?'&mdash;':esc(x))+'</strong></div></div>'};
var perdido=c&&+c.modo_perdido===1;
var h='<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2"><div><h5 class="mb-0">'+esc(q.codigo)+' &middot; '+esc(q.tipo||'')+'</h5>'+
'<div class="text-muted">'+esc(q.marca||'')+' '+esc(q.modelo||'')+'</div></div><div>'+
s._rtBadgeConexion({conexion:con.clave})+' <span class="text-muted small">'+esc(con.hace||'')+'</span></div></div>';
if(perdido)h+='<div class="alert alert-danger py-2"><b><i class="bi bi-exclamation-octagon"></i> '+(c.perdido_motivo==='robo'?'ROBADO':'PERDIDO')+'</b> desde '+esc(String(c.perdido_desde||'').slice(0,16))+
(c.perdido_nota?' &mdash; '+esc(c.perdido_nota):'')+'<div class="small">Cada vez que el equipo se conecte se avisará a los usuarios con permiso de edición de equipos.</div></div>';
if(!c)h+='<div class="alert alert-secondary py-2">Este equipo no tiene el <b>agente de rastreo</b> activado: no reporta su ubicación.</div>';
h+='<div class="row g-2 mb-3">'+
cel('IP en inventario',q.ip_registrada)+cel('IP local reportada',r0&&r0.ip_local)+cel('IP pública vista',r0&&r0.ip_publica)+
cel('MAC en inventario',q.mac)+cel('MAC reportada',r0&&r0.mac_address)+cel('Nombre del equipo',r0&&r0.hostname)+
cel('Wi-Fi',r0&&r0.wifi)+cel('Última ubicación',r0&&r0.ubicacion)+cel('Origen del dato',r0&&({gps:'GPS / Wi-Fi de Windows',aprox:'Aproximada (Windows sin GPS)',sede:'Sede conocida',ip:'IP pública (aproximada)',red:'Solo red'}[s._rtFuenteEf(r0.fuente,r0.precision_m)]||r0.fuente))+'</div>';
if(r0&&s._rtFuenteEf(r0.fuente,r0.precision_m)==='aprox')h+='<div class="alert alert-warning py-2 small"><i class="bi bi-geo-alt"></i> <b>Ubicación aproximada.</b> Este equipo no tiene GPS: Windows estimó la posición por Wi-Fi o IP con un margen de <b>±'+esc(+r0.precision_m>=1000?(r0.precision_m/1000).toFixed(1)+' km':r0.precision_m+' m')+'</b>, por eso el mapa puede mostrar otra dirección. Para ubicarlo bien, registre su red en <b>Sedes y redes</b>'+(r0.ip_publica?' (IP pública <code>'+esc(r0.ip_publica)+'</code>'+(r0.ip_local?' o red local <code>'+esc(String(r0.ip_local).replace(/\d+$/,''))+'</code>':'')+')':'')+' con la dirección y las coordenadas de la oficina.</div>';
if(r0&&+r0.mac_coincide===0)h+='<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> La MAC que reporta el equipo <b>no coincide</b> con la registrada en el inventario. Puede ser un cambio de tarjeta de red o que el agente esté instalado en otro equipo.</div>';

h+='<div class="d-flex flex-wrap gap-2 mb-3">';
if(r0&&r0.latitud!=null)h+='<button class="btn btn-sm btn-outline-danger" id="rd-mapa"><i class="bi bi-map"></i> Ver recorrido en el mapa</button>';
if(ed){
if(!c||+c.activo!==1)h+='<button class="btn btn-sm btn-primary" id="rd-activar"><i class="bi bi-power"></i> '+(c?'Reactivar':'Activar')+' rastreo</button>';
if(c&&+c.activo===1){
h+='<button class="btn btn-sm btn-primary" id="rd-agente"><i class="bi bi-download"></i> Descargar agente (.ps1)</button>'+
'<button class="btn btn-sm btn-outline-secondary" id="rd-token"><i class="bi bi-key"></i> Ver token</button>'+
'<button class="btn btn-sm btn-outline-secondary" id="rd-regen"><i class="bi bi-arrow-repeat"></i> Regenerar token</button>';
if(!perdido)h+='<button class="btn btn-sm btn-outline-secondary" id="rd-desact"><i class="bi bi-power"></i> Desactivar</button>';
}
if(r0)h+='<button class="btn btn-sm btn-success" id="rd-verificar"><i class="bi bi-check-lg"></i> Confirmar que está en esa ubicación</button>';
if(!perdido)h+='<button class="btn btn-sm btn-danger ms-auto" id="rd-perdido"><i class="bi bi-exclamation-octagon"></i> Marcar perdido / robado</button>';
else h+='<button class="btn btn-sm btn-success ms-auto" id="rd-encontrado"><i class="bi bi-check2-circle"></i> Equipo encontrado</button>';
}
h+='</div>';

var rs=g.rastreos||[];
h+='<h6>Últimos reportes <span class="text-muted small">('+rs.length+')</span></h6>';
if(!rs.length)h+='<div class="text-muted small">Aún no hay reportes de este equipo.</div>';
else h+='<div style="max-height:260px;overflow:auto"><table class="table table-sm table-bordered tabla-mini mb-0"><thead><tr><th>Fecha</th><th>IP local</th><th>IP pública</th><th>MAC</th><th>Ubicación</th><th>Origen</th></tr></thead><tbody>'+
rs.map(function(r){return '<tr><td class="small">'+esc(String(r.created_at||'').slice(0,16))+'</td><td class="small">'+esc(r.ip_local||'')+'</td><td class="small">'+esc(r.ip_publica||'')+'</td>'+
'<td class="small">'+(r.mac_coincide==null?'<span class="text-muted">—</span>':+r.mac_coincide===1?'<span class="text-success">✓</span>':'<span class="text-danger">distinta</span>')+'</td>'+
'<td class="small">'+esc(r.ubicacion||'')+(r.sede?' <span class="text-muted">('+esc(r.sede)+')</span>':'')+(r.wifi?'<div class="text-muted">'+esc(r.wifi)+'</div>':'')+'</td>'+
'<td class="small">'+s._rtFuente(r.fuente,r.precision_m)+(r.precision_m?' ±'+esc(r.precision_m)+' m':'')+'</td></tr>'}).join('')+'</tbody></table></div>';
h+='<div class="text-muted small mt-2">Los datos los informa el equipo mismo (declarados, sin verificar). El rastreo no cambia el inventario.</div>';

Modal.abrir('Rastreo — '+q.codigo,h,'modal-xl');
var reabrir=function(){Modal.cerrar();setTimeout(function(){s._rt();s._rtDetalle(id)},400)};
var post=function(datos,fn){datos.equipo_id=id;return Api.post('api/rastreo/gestionar.php',datos).then(fn||reabrir).catch(function(){})};
 $('#rd-mapa').on('click',function(){Modal.cerrar();setTimeout(function(){
var e=(s._rtDatos||[]).filter(function(x){return +x.id===+id})[0];
if(e&&e.lat!=null&&s._mapa)s._mapa.setView([e.lat,e.lon],15);
s._rtRecorrido(id,false);
var cont=document.getElementById('rt-mapa');if(cont&&cont.scrollIntoView)cont.scrollIntoView({block:'nearest'});
},350)});
 $('#rd-activar').on('click',function(){post({accion:'activar'},function(){Toast.success('Rastreo activado. Descargue el agente e instálelo en el equipo.');reabrir()})});
 $('#rd-desact').on('click',function(){
Swal.fire({title:'¿Desactivar el rastreo?',text:'El equipo dejará de reportar su ubicación.',icon:'warning',showCancelButton:true,confirmButtonText:'Desactivar'})
.then(function(x){if(x.isConfirmed)post({accion:'desactivar'},function(){Toast.success('Rastreo desactivado');reabrir()})});
});
 $('#rd-regen').on('click',function(){
Swal.fire({title:'¿Regenerar el token?',text:'El agente instalado dejará de funcionar hasta que descargue e instale el nuevo.',icon:'warning',showCancelButton:true,confirmButtonText:'Regenerar'})
.then(function(x){if(x.isConfirmed)post({accion:'regenerar'},function(){Toast.success('Token regenerado. Descargue el agente nuevamente.');reabrir()})});
});
 $('#rd-token').on('click',function(){
post({accion:'ver_token'},function(t){
var url=location.origin+BASE_URL.replace(/\/$/,'')+'/api/rastreo/checkin.php';
Swal.fire({title:'Token del equipo',width:640,confirmButtonText:'Cerrar',
html:'<div class="text-start small"><label>Token (no lo comparta):</label><input class="form-control form-control-sm mb-2" readonly value="'+esc(t.token)+'" onclick="this.select()">'+
'<label>Dirección de reporte (POST con <code>token</code>, <code>ip_local</code>, <code>mac</code>...):</label><input class="form-control form-control-sm" readonly value="'+esc(url)+'" onclick="this.select()"></div>'});
});
});
 $('#rd-agente').on('click',function(){s._rtDescargarAgente(id,q.codigo)});
 $('#rd-perdido').on('click',function(){
Swal.fire({title:'Marcar '+esc(q.codigo),confirmButtonText:'Marcar',showCancelButton:true,confirmButtonColor:'#dc2626',
html:'<div class="text-start small"><label>Situación</label><select id="sw-motivo" class="form-select mb-2"><option value="perdido">Perdido / extraviado</option><option value="robo">Robado</option></select>'+
'<label>Nota (dónde se vio por última vez, n.º de denuncia...)</label><textarea id="sw-nota" class="form-control" rows="2" maxlength="250"></textarea>'+
'<div class="mt-2 text-muted">El equipo reportará cada minuto cuando se conecte y se avisará a los usuarios con permiso de edición.</div></div>',
preConfirm:function(){return {motivo:document.getElementById('sw-motivo').value,nota:document.getElementById('sw-nota').value}}})
.then(function(x){if(x.isConfirmed)post({accion:'perdido',motivo:x.value.motivo,nota:x.value.nota},function(){Toast.warning('Equipo marcado. Seguimiento intensivo activado.');reabrir()})});
});
 $('#rd-encontrado').on('click',function(){
Swal.fire({title:'¿El equipo fue encontrado?',icon:'question',showCancelButton:true,confirmButtonText:'Sí, encontrado'})
.then(function(x){if(x.isConfirmed)post({accion:'encontrado'},function(){Toast.success('Equipo marcado como encontrado');reabrir()})});
});
 $('#rd-verificar').on('click',function(){
Swal.fire({title:'Confirmar ubicación',text:'Se registra en el historial que el equipo está en:',input:'text',inputValue:(r0&&r0.ubicacion)||'',showCancelButton:true,confirmButtonText:'Confirmar',
inputValidator:function(v){if(!v||!v.trim())return 'Indique la ubicación'}})
.then(function(x){if(!x.isConfirmed)return;
Api.post('api/equipos/seguimiento.php',{accion:'verificar',equipo_id:id,ubicacion:x.value.trim()}).then(function(){Toast.success('Ubicación confirmada');s._c(true)}).catch(function(){})});
});
});
},
_rtDescargarAgente:function(id,codigo){
var def=location.origin+BASE_URL.replace(/\/$/,'');
var local=/^(localhost|127\.|\[?::1)/.test(location.hostname);
Swal.fire({title:'Dirección del servidor SIGTI',input:'text',inputValue:def,showCancelButton:true,confirmButtonText:'Descargar',width:620,
html:'<div class="text-start small">Es la dirección con la que <b>el equipo a rastrear</b> llegará a este sistema.'+
(local?'<div class="alert alert-warning py-1 mt-2 mb-0"><b>Atención:</b> está entrando por «'+esc(location.hostname)+'». Cambie por la IP o el dominio del servidor (ej. <code>http://192.168.1.100/Plataforma-Servicio-TI</code>) o el equipo no podrá reportar.</div>':'')+'</div>',
inputValidator:function(v){if(!/^https?:\/\/[A-Za-z0-9.\-:\[\]]+(\/[A-Za-z0-9._\-\/]*)?$/.test((v||'').trim()))return 'Use una dirección como http://192.168.1.100/Plataforma-Servicio-TI'}})
.then(function(x){
if(!x.isConfirmed)return;
var url=BASE_URL+'api/rastreo/agente.php?equipo_id='+encodeURIComponent(id)+'&servidor='+encodeURIComponent(x.value.trim());
fetch(url,{credentials:'same-origin'}).then(function(r){
if(r.ok&&(r.headers.get('Content-Disposition')||'').indexOf('attachment')>=0){
return r.blob().then(function(b){
var a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='sigti-rastreo-'+String(codigo).replace(/[^A-Za-z0-9_-]/g,'')+'.ps1';
document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove()},500);
Swal.fire({icon:'success',title:'Agente descargado',width:620,confirmButtonText:'Entendido',
html:'<div class="text-start small">En el equipo a rastrear, abra <b>PowerShell como administrador</b> en la carpeta donde guardó el archivo y ejecute:<br><code>powershell -ExecutionPolicy Bypass -File .\\sigti-rastreo-'+esc(codigo)+'.ps1 -Instalar</code><br><br>Para que la ubicación sea precisa, en Windows active <i>Configuración › Privacidad › Ubicación</i>. El archivo contiene el token del equipo: no lo comparta.</div>'});
});
}
return r.json().then(function(j){Toast.warning((j&&j.message)||'No se pudo generar el agente')});
}).catch(function(){Toast.error('No se pudo descargar el agente')});
});
},
_rtGuia:function(){
Modal.abrir('Cómo funciona el rastreo',
'<ol class="small">'+
'<li>En la ficha del equipo (o en <b>Detalle</b>) pulse <b>Activar rastreo</b> y <b>Descargar agente</b>.</li>'+
'<li>Instale el archivo en el equipo (una sola vez, PowerShell como administrador). Desde ahí informa cada 5 minutos su IP, MAC, red Wi-Fi y, si Windows tiene la ubicación activada, su posición.</li>'+
'<li>Aquí verá el estado (<b>en línea / visto en 24 h / sin señal</b>) y su última ubicación en el mapa. Si se pierde, pulse <b>Marcar perdido / robado</b>: reporta cada minuto y se avisa en cuanto se conecte.</li></ol>'+
'<div class="alert alert-warning small mb-2"><b>Alcances reales</b><ul class="mb-0">'+
'<li>La posición precisa solo llega si Windows tiene <b>Ubicación activada</b>. Las laptops sin GPS dan una posición con margen de varios km: se marca como <b>Aproximada</b> y, si su red está en «Sedes y redes», se usa la <b>sede</b>. Si no hay posición, se usa la <b>sede</b> (si registró su red en «Sedes y redes») o la <b>IP pública</b> (aproximada, a nivel de ciudad).</li>'+
'<li>El equipo debe estar <b>encendido y con internet</b>, y poder llegar a este servidor. Si SIGTI solo es accesible dentro de la oficina, solo reportará mientras esté en esa red (o por VPN). Para recuperar equipos fuera de la oficina, SIGTI debe publicarse con un dominio o IP pública.</li>'+
'<li>Si alguien formatea el equipo o desinstala el agente, deja de reportar (verá «Sin señal»).</li>'+
'<li>Celulares y tablets (IMEI) no se rastrean con este agente.</li></ul></div>'+
'<div class="small text-muted">Los datos son declarados por el equipo y no modifican el inventario. Confirme la ubicación físicamente antes de actuar.</div>','modal-lg');
},
_rtSedes:function(){
var s=this;
Promise.all([Api.get('api/rastreo/sedes.php'),Api.get('api/catalogos/select.php',{tipo:'proyectos'}).catch(function(){return[]})]).then(function(r){
var sedes=r[0]||[],proy=r[1]||[];
var h='<div class="alert alert-info small py-2">Registre las <b>oficinas, obras o proyectos</b> con su red. Cuando un equipo reporte desde esa red, se mostrará en el mapa en la sede (aunque no tenga GPS). '+
'Para conocer la IP pública de una oficina, abra <b>https://api.ipify.org</b> desde un equipo de esa oficina. Las coordenadas se copian de Google Maps (clic derecho › primer número).</div>'+
'<form id="form-sede" class="row g-2 mb-3" autocomplete="off"><input type="hidden" id="sd-id">'+
'<div class="col-md-4"><label class="form-label small mb-0">Nombre *</label><input class="form-control form-control-sm" id="sd-nombre" maxlength="120" placeholder="Oficina Lima"></div>'+
'<div class="col-md-4"><label class="form-label small mb-0">IP pública de la sede</label><input class="form-control form-control-sm" id="sd-ip" maxlength="45" placeholder="200.10.20.30"></div>'+
'<div class="col-md-4"><label class="form-label small mb-0">Red local (inicio)</label><input class="form-control form-control-sm" id="sd-pref" maxlength="20" placeholder="192.168.1."></div>'+
'<div class="col-md-3"><label class="form-label small mb-0">Latitud</label><input class="form-control form-control-sm" id="sd-lat" placeholder="-12.046374"></div>'+
'<div class="col-md-3"><label class="form-label small mb-0">Longitud</label><input class="form-control form-control-sm" id="sd-lon" placeholder="-77.042793"></div>'+
'<div class="col-md-3"><label class="form-label small mb-0">Proyecto / obra</label><select class="form-select form-select-sm" id="sd-proy"><option value="">— ninguno —</option>'+
proy.map(function(p){return '<option value="'+esc(p.id)+'">'+esc(p.texto||p.nombre||'')+'</option>'}).join('')+'</select></div>'+
'<div class="col-md-3"><label class="form-label small mb-0">Dirección</label><input class="form-control form-control-sm" id="sd-dir" maxlength="200"></div>'+
'<div class="col-12 text-end"><button type="button" class="btn btn-sm btn-secondary d-none" id="sd-cancel">Cancelar edición</button> <button type="submit" class="btn btn-sm btn-primary" id="sd-ok"><i class="bi bi-save"></i> Guardar sede</button></div></form>';
h+=sedes.length?'<table class="table table-sm table-bordered tabla-mini"><thead><tr><th>Sede</th><th>IP pública</th><th>Red local</th><th>Coordenadas</th><th>Reportes</th><th>Estado</th><th></th></tr></thead><tbody>'+
sedes.map(function(x){return '<tr><td><b>'+esc(x.nombre)+'</b>'+(x.proyecto?'<div class="small text-muted">'+esc(x.proyecto)+'</div>':'')+(x.direccion?'<div class="small text-muted">'+esc(x.direccion)+'</div>':'')+'</td>'+
'<td class="small">'+esc(x.ip_publica||'—')+'</td><td class="small">'+esc(x.prefijo_local||'—')+'</td>'+
'<td class="small">'+(x.latitud!=null?esc(x.latitud)+', '+esc(x.longitud):'<span class="text-warning">sin coordenadas</span>')+'</td><td class="small">'+esc(x.reportes||0)+'</td>'+
'<td>'+(+x.activo===1?'<span class="badge text-bg-success">Activa</span>':'<span class="badge text-bg-secondary">Inactiva</span>')+'</td>'+
'<td class="text-nowrap"><button class="btn btn-sm btn-outline-primary sd-edit" data-id="'+x.id+'" title="Editar"><i class="bi bi-pencil"></i></button> '+
'<button class="btn btn-sm btn-outline-secondary sd-toggle" data-id="'+x.id+'" title="Activar / desactivar"><i class="bi bi-power"></i></button> '+
'<button class="btn btn-sm btn-outline-danger sd-del" data-id="'+x.id+'" data-nombre="'+esc(x.nombre)+'" title="Eliminar"><i class="bi bi-trash"></i></button></td></tr>'}).join('')+'</tbody></table>':
'<div class="text-muted small">Aún no hay sedes registradas.</div>';
Modal.abrir('Sedes y redes conocidas',h,'modal-xl');
var reabrir=function(){Modal.cerrar();setTimeout(function(){s._rt();s._rtSedes()},400)};
 $('#form-sede').on('submit',function(ev){
ev.preventDefault();
Api.post('api/rastreo/sedes.php',{accion:'guardar',id:$('#sd-id').val(),nombre:$('#sd-nombre').val(),ip_publica:$('#sd-ip').val(),prefijo_local:$('#sd-pref').val(),
latitud:$('#sd-lat').val(),longitud:$('#sd-lon').val(),proyecto_id:$('#sd-proy').val(),direccion:$('#sd-dir').val()}).then(function(){Toast.success('Sede guardada');reabrir()}).catch(function(){});
});
 $('#sd-cancel').on('click',function(){reabrir()});
 $('.sd-edit').on('click',function(){
var id=+$(this).data('id'),x=sedes.filter(function(z){return +z.id===id})[0];
if(!x)return;
 $('#sd-id').val(x.id);$('#sd-nombre').val(x.nombre);$('#sd-ip').val(x.ip_publica||'');$('#sd-pref').val(x.prefijo_local||'');
 $('#sd-lat').val(x.latitud!=null?x.latitud:'');$('#sd-lon').val(x.longitud!=null?x.longitud:'');$('#sd-proy').val(x.proyecto_id||'');$('#sd-dir').val(x.direccion||'');
 $('#sd-cancel').removeClass('d-none');$('#sd-ok').html('<i class="bi bi-save"></i> Actualizar sede');
 $('#sd-nombre').trigger('focus');
});
 $('.sd-toggle').on('click',function(){Api.post('api/rastreo/sedes.php',{accion:'toggle',id:$(this).data('id')}).then(function(){reabrir()}).catch(function(){})});
 $('.sd-del').on('click',function(){
var id=$(this).data('id'),n=$(this).data('nombre');
Swal.fire({title:'¿Eliminar la sede «'+esc(n)+'»?',text:'Los reportes antiguos conservan su texto de ubicación.',icon:'warning',showCancelButton:true,confirmButtonText:'Eliminar'})
.then(function(x){if(x.isConfirmed)Api.post('api/rastreo/sedes.php',{accion:'eliminar',id:id}).then(function(){Toast.success('Sede eliminada');reabrir()}).catch(function(){})});
});
});
},
_c:function(silencioso){
Api.get('api/equipos/seguimiento.php',{accion:'panel',f_verif:$('#sg-filtro').val()||'todos'}).then(function(d){
 $('#seg-stats [data-k]').each(function(){$(this).text(d.stats[$(this).data('k')]||0)});
 $('#seg-ultima').text('actualizado '+new Date().toLocaleTimeString());
var filas=(d.equipos||[]).map(function(e){
var conQ='';
if(e.destino_tipo==='proyecto'||e.destino_tipo==='obra'){
conQ='<span class="badge '+(e.destino_tipo==='obra'?'text-bg-warning text-dark':'text-bg-success')+'">'+(e.destino_tipo==='obra'?'OBRA':'PROYECTO')+'</span><br><b>'+esc(e.destino_nombre||'')+'</b>'+
'<br><span class="text-muted small">'+(e.destino_responsable?'Resp.: '+esc(e.destino_responsable):'')+(e.destino_detalle?' · '+esc(e.destino_detalle):'')+'</span>';
}else if(e.asignado){
conQ='<b>'+esc(e.asignado)+'</b><br><span class="text-muted small">'+esc(e.asignado_cargo||'')+'</span>';
}else if(e.area_directa){
conQ='<span class="badge text-bg-info">AREA</span><br><b>'+esc(e.area_directa)+'</b>';
}else{
conQ='<span class="text-muted small">En stock</span>';
}
var acc='';
if(App.permisos.indexOf('equipos.editar')>=0){
acc='<button class="btn btn-sm btn-success btn-verificar me-1" data-id="'+e.id+'" data-ub="'+esc(e.ubicacion||'')+'" title="Esta aqui"><i class="bi bi-check-lg"></i></button>'+
'<button class="btn btn-sm btn-outline-primary btn-trasladar me-1" data-id="'+e.id+'" data-cod="'+esc(e.codigo)+'" title="Trasladar"><i class="bi bi-arrow-left-right"></i></button>';
}
acc+='<button class="btn btn-sm btn-outline-secondary btn-historial" data-id="'+e.id+'" data-cod="'+esc(e.codigo)+'" title="Historial"><i class="bi bi-clock-history"></i></button>';
return [
'<strong>'+esc(e.codigo)+'</strong><br><span class="text-muted small">'+esc(e.marca||'')+'</span>',
e.ubicacion?esc(e.ubicacion):'<span class="text-muted">sin verificar</span>',
conQ,
'<span class="small">'+esc(e.estado)+'</span>',
'<span class="badge '+(e.sem==='rojo'?'text-bg-danger':e.sem==='ambar'?'text-bg-warning text-dark':'text-bg-success')+'">'+esc(e.sem_txt||'')+'</span>',
acc];
});
if($.fn.DataTable.isDataTable('#tb-seg'))$('#tb-seg').DataTable().clear().destroy();
 $('#tb-seg tbody').empty();
 $('#tb-seg').DataTable({data:filas,language:DATATABLES_ES,pageLength:25,
columns:[{title:'Equipo'},{title:'Ubicacion'},{title:'Con quien'},{title:'Estado'},{title:'Verificacion'},{title:'',orderable:false,className:'text-center'}]});
if(silencioso&&Seguimiento._lastTotal!==undefined&&Seguimiento._lastTotal!==d.stats.total){
Toast.info('Inventario actualizado: '+d.stats.total+' equipo(s)');
}
Seguimiento._lastTotal=d.stats.total;
}).catch(function(){});
}
};
 $(document).on('click','.btn-verificar',function(){
var d=$(this).data();
Api.post('api/equipos/seguimiento.php',{accion:'verificar',equipo_id:d.id,ubicacion:d.ub})
.then(function(){Seguimiento._c()});
});
 $(document).on('click','.btn-trasladar',function(){
var d=$(this).data();
Swal.fire({title:'Trasladar '+esc(d.cod),input:'text',inputPlaceholder:'Nueva ubicacion...',
showCancelButton:true,confirmButtonText:'Trasladar'}).then(function(r){
if(!r.isConfirmed||!r.value)return;
Api.post('api/equipos/seguimiento.php',{accion:'trasladar',equipo_id:d.id,ubicacion:r.value,observaciones:''})
.then(function(){Seguimiento._c()});
});
});
 $(document).on('click','.btn-historial',function(){
var d=$(this).data();
Api.get('api/equipos/seguimiento.php',{accion:'historial',equipo_id:d.id}).then(function(h){
var html='<div class="timeline">';
(h||[]).forEach(function(ev){
html+='<div class="tl-item"><div class="tl-titulo">'+esc(ev.tipo)+' — '+esc(ev.ubicacion)+'</div>'+
(ev.con_quien?'<div class="tl-detalle">Con: '+esc(ev.con_quien)+'</div>':'')+
'<div class="tl-fecha">'+esc(ev.created_at)+'</div></div>';
});
html+='</div>';
Modal.abrir('Historial — '+d.cod,html,'modal-lg');
});
});
 $(document).on('click','.btn-rt-mapa',function(){
if( $(this).closest('.leaflet-popup').length)return;
Seguimiento._rtEnfocar($(this).data('id'),true);
});
 $(document).on('click','.btn-rt-det',function(){
Seguimiento._rtDetalle($(this).data('id'));
});
App.registrar('seguimiento',Seguimiento);