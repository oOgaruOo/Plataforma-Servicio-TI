/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Sistema: licencia, módulos de la empresa y vocabulario del rubro
   - Sistema.aplicar(d)  recibe «sistema» de api/auth/login.php y perfil.php
   - Banner de licencia (prueba / por vencer / gracia / solo lectura)
   - Módulos apagados: desaparecen del menú y del chat flotante
   - Terminos: cambia «obra» por la palabra del rubro (sede, tienda, planta...) en los textos fijos de la pantalla
   ============================================================ */
var Sistema={
datos:null,
/* ruta del menu -> modulo que se puede apagar (igual que Licencia::MODULOS en PHP) */
RUTAS:{'proyectos/lista':'proyectos','capacitaciones/lista':'capacitaciones','mantenimiento/lista':'mantenimiento','software/lista':'software','equipos/seguimiento':'rastreo','carnets/lista':'carnets','etiquetas/plantillas':'etiquetas','chat/principal':'chat'},
aplicar:function(d){
this.datos=d||null;
d=d||{};
this._banner(d.licencia);
$('body').toggleClass('sigti-solo-lectura',!!(d.licencia&&d.licencia.solo_lectura));
Terminos.activar(d.terminos||null);
},
soloLectura:function(){return !!(this.datos&&this.datos.licencia&&this.datos.licencia.solo_lectura)},
activo:function(m){return !(this.datos&&this.datos.modulos_off&&this.datos.modulos_off.indexOf(m)>=0)},
apagada:function(ruta){var m=this.RUTAS[ruta];return !!m&&!this.activo(m)},
puedeGestionar:function(){return !!(App.usuario&&(App.usuario.rol==='super_admin'||App.permisos.indexOf('configuracion.gestionar')>=0))},
_banner:function(l){
var $b=$('#licencia-banner');
if(!$b.length)return;
if(!l||l.nivel==='ok'){$b.addClass('hidden').empty();return}
var ic=l.nivel==='critico'?'bi-exclamation-octagon-fill':(l.nivel==='aviso'?'bi-exclamation-triangle-fill':'bi-info-circle-fill');
var h='<i class="bi '+ic+'"></i><span class="lb-msg"></span>';
if(this.puedeGestionar())h+='<button type="button" class="btn btn-sm btn-light lb-ir">Activar / renovar licencia</button>';
else h+='<span class="small">Avise al administrador del sistema.</span>';
$b.removeClass('hidden lb-info lb-aviso lb-critico').addClass('lb-'+l.nivel).html(h);
$b.find('.lb-msg').text(l.mensaje||'');
},
/* tras activar / cambiar de rubro o de modulos: se vuelve a leer todo */
recargar:function(){location.reload()}
};
$(document).on('click','#licencia-banner .lb-ir',function(){Router.ir('licencia/principal')});

/* ---------- vocabulario del rubro ---------- */
var Terminos={
_t:null,_obs:null,
OMITIR:'script,style,textarea,input,code,pre,[data-noterm],.dataTables_empty',
t:function(s){return this._t?this._cambia(String(s)):s},
activar:function(t){
this._t=(t&&t.singular)?t:null;
if(this._obs){this._obs.disconnect();this._obs=null}
if(!this._t||typeof MutationObserver==='undefined')return;
var s=this;
this._recorre(document.body);
this._obs=new MutationObserver(function(ms){
ms.forEach(function(m){
if(m.type==='characterData'){s._nodo(m.target)}
else m.addedNodes.forEach(function(n){s._recorre(n)});
});
});
this._obs.observe(document.body,{childList:true,subtree:true,characterData:true});
},
_cambia:function(str){
var T=this._t;
var r=str.replace(/\bu(\s+)(obras?)\b/gi,function(m,sp,w){return 'o'+sp+w})
.replace(/\beste(\s+)(obra)\b/gi,function(m,sp,w){return 'esta'+sp+w});
return r.replace(/(mano de\s+)?\b(obras?)\b/gi,function(m,mano,w,off,all){
if(mano)return m;
var rest=all.slice(off+m.length);
if(w.charAt(0)!==w.charAt(0).toLowerCase()&&/^\s+[A-ZÁÉÍÓÚÑ0-9]/.test(rest))return m;   /* «Obra Trujillo»: es un nombre propio */
var base=/s$/i.test(w)?T.plural:T.singular;
if(w.length>1&&w===w.toUpperCase())return base.toUpperCase();
if(w.charAt(0)!==w.charAt(0).toLowerCase())return base.charAt(0).toUpperCase()+base.slice(1);
return base;
});
},
_nodo:function(n){
var p=n.parentElement;
if(!p)return;
var tag=p.tagName;
if(tag==='TD'||tag==='TH'||p.closest(this.OMITIR))return;
if(tag==='OPTION'&&!/^(Asignado a obras|A obras|Solo obras|Obras?|Proyectos?\s*(y|\/)\s*obras?|Cualquier proyecto \/ obra)$/i.test(n.nodeValue.trim())&&!/^Obra(?= · )/.test(n.nodeValue.trim()))return;
var v=n.nodeValue;
if(!v||!/obra/i.test(v))return;
var nv=this._cambia(v);
if(nv!==v)n.nodeValue=nv;
},
_recorre:function(raiz){
var s=this;
if(raiz.nodeType===3){s._nodo(raiz);return}
if(raiz.nodeType!==1)return;
if(raiz.matches&&raiz.matches(this.OMITIR))return;
var w=document.createTreeWalker(raiz,NodeFilter.SHOW_TEXT,null),n,lista=[];
while((n=w.nextNode()))lista.push(n);
lista.forEach(function(x){s._nodo(x)});
var els=raiz.querySelectorAll?raiz.querySelectorAll('[title],[placeholder]'):[];
Array.prototype.forEach.call(els,function(e){
['title','placeholder'].forEach(function(a){
var v=e.getAttribute(a);
if(v&&/obra/i.test(v)){var nv=s._cambia(v);if(nv!==v)e.setAttribute(a,nv)}
});
});
}
};
