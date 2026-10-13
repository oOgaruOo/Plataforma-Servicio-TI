/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
const Api={
get(u,d){return this._s('GET',u,d)},
post(u,d){return this._s('POST',u,d)},
put(u,d){d=d||{};d._method='PUT';return this._s('POST',u,d)},
/* POST con FormData (archivos): fd debe ser un FormData listo */
postFiles(u,fd){
return new Promise(function(res,rej){
 $.ajax({
url:BASE_URL+u,
type:'POST',
data:fd,
processData:false,
contentType:false,
dataType:'json',
headers:{'X-CSRF-Token':CSRF.token},
beforeSend:function(){Loader.show()},
complete:function(){Loader.hide()},
success:function(r){
if(r&&r.success){res(r.data)}
else{Toast.error((r&&r.message)||'Error');rej({cuerpo:r})}
},
error:function(xhr){
var c=null;
try{c=JSON.parse(xhr.responseText)}catch(e){}
var s=xhr.status;
var msg=(c&&c.message)||('Error ('+s+')');
if(s===401){if(App.usuario)App.sesionExpirada();rej({status:s});return}
if(s===419){Toast.error('Sesion expirada. Recargando...');setTimeout(function(){location.reload()},1500);rej({status:s});return}
if(s===403){Toast.error(msg)}
else if(s===422){Toast.warning(msg)}
else{Toast.error(msg)}
rej({status:s,cuerpo:c})
}
});
})
},
_s(m,u,d){
return new Promise(function(res,rej){
 $.ajax({
url:BASE_URL+u,
type:m,
data:d||{},
dataType:'json',
headers:{'X-CSRF-Token':CSRF.token},
beforeSend:function(){Loader.show()},
complete:function(){Loader.hide()},
success:function(r){
if(r&&r.success){
res(r.data)
}else{
Toast.error((r&&r.message)||'Error');
rej({cuerpo:r})
}
},
error:function(xhr){
var c=null;
try{c=JSON.parse(xhr.responseText)}catch(e){}
var s=xhr.status;
var msg=(c&&c.message)||('Error ('+s+')');
if(s===401){
if(App.usuario)App.sesionExpirada();
rej({status:s});
return
}
if(s===419){
Toast.error('Sesion expirada. Recargando...');
setTimeout(function(){location.reload()},1500);
rej({status:s});
return
}
if(s===403){Toast.error(msg)}
else if(s===422){Toast.warning(msg)}
else{Toast.error(msg)}
rej({status:s,cuerpo:c})
}
})
})
}
};