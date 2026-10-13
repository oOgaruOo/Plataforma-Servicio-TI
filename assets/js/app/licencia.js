/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Licencia y rubro
   Vista: views/licencia/principal.php
   API:   api/licencia/{estado,activar,rubro,modulos}.php
   ============================================================ */
var Licencia={
d:null,sel:null,
init:function(){
var s=this;
if(!$('#lic-raiz').length)return;
s.cargar();
$('#lic-copiar').on('click',function(){s._copiar()});
$('#lic-activar').on('click',function(){s.activar()});
$('#lic-rubros').on('click','.lic-rubro',function(){s.sel=$(this).attr('data-k');s._pintaRubros()});
$('#lic-rubro-ok').on('click',function(){s.aplicarRubro()});
$('#lic-mod-ok').on('click',function(){s.guardarModulos()});
},
cargar:function(){
var s=this;
Api.get('api/licencia/estado.php').then(function(r){
s.d=r.detalle;
if(!s.d)return;
s.sel=null;
s._pintaEstado();s._pintaRubros();s._pintaModulos();
Sistema.aplicar(r);
});
},
_fecha:function(f){if(!f)return '—';var p=String(f).split('-');return p[2]+'/'+p[1]+'/'+p[0]},
_pintaEstado:function(){
var d=this.d,nombres={activa:'Licencia activa',por_vencer:'Por vencer',gracia:'Vencida — período de gracia',vencida:'Vencida — solo lectura',prueba:'Período de prueba',sin_licencia:'Sin licencia — solo lectura',invalida:'Licencia no válida — solo lectura'};
var h='<div class="d-flex flex-wrap justify-content-between gap-2"><div><div class="fs-5 fw-semibold">'+esc(nombres[d.estado]||d.estado)+'</div><div>'+esc(d.mensaje||'')+'</div></div>';
h+='<div class="small text-md-end">';
if(d.empresa)h+='<div><b>'+esc(d.empresa)+'</b></div><div>RUC '+esc(d.ruc||'')+'</div>';
if(d.licencia_id)h+='<div>Licencia '+esc(d.licencia_id)+'</div>';
h+='<div>Vence: <b>'+(d.permanente?'Permanente':this._fecha(d.vence||d.prueba_hasta))+'</b></div></div></div>';
if(d.estado==='gracia'||d.estado==='vencida'||d.estado==='sin_licencia'||d.estado==='invalida')
h+='<div class="small mt-2">En <b>solo lectura</b> se puede entrar, consultar, buscar y exportar; no se puede registrar ni modificar. Sus datos están a salvo. Active la licencia para volver a trabajar con normalidad.</div>';
$('#lic-estado').attr('class','lic-estado mb-3 '+(d.nivel||'ok')).html(h);
$('#lic-inst').text(d.instalacion);
},
_copiar:function(){
var t=this.d&&this.d.instalacion;if(!t)return;
if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(function(){Toast.success('Código copiado.')},function(){Toast.info(t)})}
else Toast.info(t);
},
activar:function(){
var s=this,t=$.trim($('#lic-texto').val());
if(!t){Toast.warning('Pegue el texto de la licencia.');$('#lic-texto').trigger('focus');return}
var $b=$('#lic-activar').prop('disabled',true);
Api.post('api/licencia/activar.php',{licencia:t}).then(function(r){
Toast.success(r.mensaje||'Licencia activada.');
$('#lic-texto').val('');
Sistema.aplicar(r.sistema);
Menu.construir();
s.cargar();
}).catch(function(){}).then(function(){$b.prop('disabled',false)});
},
_pintaRubros:function(){
var d=this.d,act=(Sistema.datos&&Sistema.datos.rubro)||'',s=this,h='';
$('#lic-rubro-act').text(act?'Rubro actual: '+(d.rubros.filter(function(x){return x.clave===act})[0]||{}).nombre:'Aún no ha elegido un rubro');
d.rubros.forEach(function(x){
var det=[];
if(x.categorias.length)det.push('Tickets: '+x.categorias.join(', '));
if(x.tipos.length)det.push('Equipos: '+x.tipos.join(', '));
if(x.areas.length)det.push('Áreas: '+x.areas.join(', '));
h+='<div class="col-md-6 col-xl-4"><div class="lic-rubro'+(s.sel===x.clave?' sel':'')+'" data-k="'+x.clave+'"><div class="d-flex gap-2"><i class="bi '+x.icono+'"></i><div><div class="fw-semibold">'+esc(x.nombre)+(act===x.clave?' <span class="badge text-bg-success">actual</span>':'')+'</div>'+
'<div class="small text-muted">'+esc(x.descripcion)+'</div><div class="small mt-1">Lo que antes era «obra» se llamará <b>'+esc(x.termino)+'</b>.</div>'+
(det.length?'<div class="small text-muted mt-1">'+det.map(esc).join('<br>')+'</div>':'')+'</div></div></div></div>';
});
$('#lic-rubros').html(h);
$('#lic-rubro-ok').prop('disabled',!s.sel);
},
aplicarRubro:function(){
var s=this;
if(!s.sel)return;
var x=this.d.rubros.filter(function(r){return r.clave===s.sel})[0];
Swal.fire({title:'¿Aplicar el rubro «'+x.nombre+'»?',text:'Se agregarán sus categorías, tipos de equipo y áreas. No se borra nada de lo que ya tiene.',icon:'question',showCancelButton:true,confirmButtonText:'Aplicar',cancelButtonText:'Cancelar'}).then(function(res){
if(!res.isConfirmed)return;
Api.post('api/licencia/rubro.php',{rubro:s.sel,modulos:$('#lic-rubro-mod').is(':checked')?1:0}).then(function(r){
Toast.success(r.mensaje);
setTimeout(function(){Sistema.recargar()},900);
});
});
},
_pintaModulos:function(){
var h='';
this.d.modulos.forEach(function(m){
h+='<div class="col-md-6 col-xl-3"><div class="form-check form-switch"><input class="form-check-input lic-mod" type="checkbox" role="switch" id="lm-'+m.clave+'" value="'+m.clave+'"'+(m.activo?' checked':'')+'><label class="form-check-label" for="lm-'+m.clave+'">'+esc(m.nombre)+'</label></div></div>';
});
$('#lic-modulos').html(h);
},
guardarModulos:function(){
var off=[];
$('.lic-mod').each(function(){if(!this.checked)off.push(this.value)});
Api.post('api/licencia/modulos.php',{off:off.length?off:''}).then(function(r){
Toast.success(r.mensaje);
Sistema.aplicar(r.sistema);
setTimeout(function(){Sistema.recargar()},700);
});
}
};
App.registrar('licencia',Licencia);
