/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Importacion masiva de equipos desde Excel / CSV
   Boton "Importar Excel" del Inventario.
   API: api/equipos/importar.php (valida cada fila; lo correcto se inserta
   y lo incorrecto se devuelve con el numero de fila y el motivo).
   ============================================================ */
var EquiposImport={
init:function(){},

/* columnas de la plantilla (clave del API, titulo, ejemplo) */
COLS:[
['tipo','Tipo *','Laptop'],['marca','Marca *','Lenovo'],['modelo','Modelo','ThinkPad E14'],
['serie','N° de serie','PF3ABC12'],['activo_fijo','Activo fijo','AF-0001'],['imei','IMEI',''],['mac','MAC',''],
['condicion','Condición','nuevo'],['fecha_compra','Fecha de compra','2026-03-15'],['proveedor','Proveedor','Coolbox'],
['costo','Costo (S/)','3200.00'],['garantia','Garantía hasta','2029-03-15'],['ubicacion','Ubicación','Almacén TI'],
['cpu','Procesador','Core i5-1235U'],['ram','Memoria RAM','16 GB'],['disco','Disco','SSD 512 GB'],
['so','Sistema operativo','Windows 11 Pro'],['accesorios','Accesorios (separados por coma)','Cargador, Mouse']
],
ALIAS:{
tipo:'tipo',tipo_de_equipo:'tipo',tipo_equipo:'tipo',
marca:'marca',modelo:'modelo',
serie:'serie',n_de_serie:'serie',n_serie:'serie',nro_serie:'serie',nro_de_serie:'serie',numero_de_serie:'serie',numero_serie:'serie',
activo_fijo:'activo_fijo',codigo_activo_fijo:'activo_fijo',af:'activo_fijo',
imei:'imei',mac:'mac',
condicion:'condicion',estado_fisico:'condicion',
fecha_compra:'fecha_compra',fecha_de_compra:'fecha_compra',
proveedor:'proveedor',costo:'costo',costo_s:'costo',precio:'costo',
garantia:'garantia',garantia_hasta:'garantia',
ubicacion:'ubicacion',
cpu:'cpu',procesador:'cpu',ram:'ram',memoria:'ram',memoria_ram:'ram',
disco:'disco',almacenamiento:'disco',so:'so',sistema_operativo:'so',accesorios:'accesorios'
},
filas:[],

_norm:function(t){
return String(t||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
},

abrir:function(alFinal){
var s=this;
s.filas=[];s.alFinal=alFinal||function(){};
var h='<div class="alert alert-light border small py-2 mb-3"><i class="bi bi-info-circle text-primary"></i> '+
'Suba un archivo <b>Excel (.xlsx)</b> o <b>CSV</b> con una fila por equipo. Solo <b>Tipo</b> y <b>Marca</b> son obligatorios. '+
'El tipo debe existir en el catálogo; los equipos entran <b>en stock</b>. Las filas con error se informan y <b>no</b> detienen las demás.</div>'+
'<div class="d-flex flex-wrap gap-2 align-items-center mb-3">'+
'<button type="button" class="btn btn-outline-success btn-sm" id="imp-plantilla"><i class="bi bi-file-earmark-excel"></i> Descargar plantilla</button>'+
'<input type="file" class="form-control form-control-sm" id="imp-archivo" accept=".xlsx,.xls,.csv" style="max-width:360px"></div>'+
'<div id="imp-previa"></div>'+
'<div class="text-end mt-3"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button> '+
'<button type="button" class="btn btn-primary" id="imp-ok" disabled><i class="bi bi-upload"></i> Importar</button></div>';
Modal.abrir('Importar equipos desde Excel',h,'modal-lg');

$('#imp-plantilla').on('click',function(){s._plantilla()});
$('#imp-archivo').on('change',function(){if(this.files&&this.files[0])s._leer(this.files[0])});
$('#imp-ok').on('click',function(){s._enviar()});
},

_plantilla:function(){
if(typeof XLSX==='undefined'){Toast.error('La librería de Excel no está disponible (revise la conexión)');return}
Api.get('api/catalogos/select.php',{tipo:'tipos'}).then(function(tipos){
var s=EquiposImport;
var wb=XLSX.utils.book_new();
var aoa=[s.COLS.map(function(c){return c[1]}),s.COLS.map(function(c){return c[2]})];
var ws=XLSX.utils.aoa_to_sheet(aoa);
ws['!cols']=s.COLS.map(function(c){return{wch:Math.max(14,c[1].length+2)}});
XLSX.utils.book_append_sheet(wb,ws,'Equipos');
var ws2=XLSX.utils.aoa_to_sheet([['Tipos válidos (use el nombre tal cual)']].concat((tipos||[]).map(function(t){return[t.texto]}))
.concat([[''],['Condición: nuevo, bueno, regular, dañado, irreparable'],['Fechas: AAAA-MM-DD o DD/MM/AAAA']]));
ws2['!cols']=[{wch:60}];
XLSX.utils.book_append_sheet(wb,ws2,'Ayuda');
XLSX.writeFile(wb,'plantilla_equipos.xlsx');
});
},

_leer:function(archivo){
var s=this;
if(typeof XLSX==='undefined'){Toast.error('La librería de Excel no está disponible (revise la conexión)');return}
var rd=new FileReader();
rd.onload=function(ev){
try{
var wb=XLSX.read(new Uint8Array(ev.target.result),{type:'array',cellDates:true});
var ws=wb.Sheets[wb.SheetNames[0]];
var datos=XLSX.utils.sheet_to_json(ws,{defval:'',raw:false,dateNF:'yyyy-mm-dd'});
s._procesar(datos);
}catch(x){
$('#imp-previa').html('<div class="alert alert-danger">No se pudo leer el archivo: '+esc(x.message||x)+'</div>');
$('#imp-ok').prop('disabled',true);
}
};
rd.readAsArrayBuffer(archivo);
},

_procesar:function(datos){
var s=this,mapa={},desconocidas=[];
if(datos.length){
Object.keys(datos[0]).forEach(function(c){
var k=s.ALIAS[s._norm(c)];
if(k)mapa[c]=k;else desconocidas.push(c);
});
}
var claves=Object.keys(mapa).map(function(c){return mapa[c]});
var faltan=['tipo','marca'].filter(function(k){return claves.indexOf(k)<0});

s.filas=[];
datos.forEach(function(r){
var f={},vacia=true;
Object.keys(mapa).forEach(function(c){
var v=String(r[c]==null?'':r[c]).trim();
if(v!=='')vacia=false;
f[mapa[c]]=v;
});
if(!vacia)s.filas.push(f);
});

var h='';
if(faltan.length)h+='<div class="alert alert-danger py-2">Faltan columnas obligatorias: <b>'+faltan.join(', ')+'</b>. Use la plantilla.</div>';
if(desconocidas.length)h+='<div class="alert alert-warning py-2 small">Columnas ignoradas (no reconocidas): '+desconocidas.map(esc).join(', ')+'</div>';
if(!s.filas.length&&!faltan.length)h+='<div class="alert alert-warning py-2">El archivo no tiene filas con datos.</div>';
if(s.filas.length&&!faltan.length){
h+='<div class="alert alert-success py-2"><b>'+s.filas.length+'</b> fila(s) lista(s) para importar. Vista previa de las primeras:</div>'+
'<div style="overflow:auto;max-height:240px"><table class="table table-sm table-bordered tabla-mini mb-0" style="font-size:12px"><thead><tr><th>#</th><th>Tipo</th><th>Marca</th><th>Modelo</th><th>Serie</th><th>Condición</th><th>Costo</th></tr></thead><tbody>'+
s.filas.slice(0,8).map(function(f,i){return '<tr><td>'+(i+2)+'</td><td>'+esc(f.tipo)+'</td><td>'+esc(f.marca)+'</td><td>'+esc(f.modelo||'')+'</td><td>'+esc(f.serie||'')+'</td><td>'+esc(f.condicion||'')+'</td><td>'+esc(f.costo||'')+'</td></tr>'}).join('')+
'</tbody></table></div>';
}
$('#imp-previa').html(h);
$('#imp-ok').prop('disabled',!(s.filas.length&&!faltan.length));
},

_enviar:function(){
var s=this,lote=500,pos=0,insertados=0,errores=[];
$('#imp-ok').prop('disabled',true);
var post=function(trozo){
return new Promise(function(res){
Loader.show();
$.ajax({url:BASE_URL+'api/equipos/importar.php',type:'POST',contentType:'application/json',dataType:'json',
data:JSON.stringify({filas:trozo}),headers:{'X-CSRF-Token':CSRF.token},
success:function(r){res({ok:true,r:r})},
error:function(xhr){var c=null;try{c=JSON.parse(xhr.responseText)}catch(x){}res({ok:false,c:c,status:xhr.status})},
complete:function(){Loader.hide()}
});
});
};
var paso=function(){
if(pos>=s.filas.length)return fin();
var desde=pos,trozo=s.filas.slice(pos,pos+lote);
pos+=lote;
post(trozo).then(function(x){
if(x.ok&&x.r&&x.r.success){
insertados+=(x.r.data&&x.r.data.insertados)||0;
((x.r.data&&x.r.data.errores)||[]).forEach(function(e){errores.push({fila:e.fila+desde,error:e.error})});
}else{
var c=x.c||{};
if(x.status===419){Toast.error('Sesión expirada. Recargando...');setTimeout(function(){location.reload()},1500);return}
if(x.status===401){App.sesionExpirada();return}
if(Array.isArray(c.errors)){c.errors.forEach(function(e){errores.push({fila:e.fila+desde,error:e.error})})}
else errores.push({fila:'—',error:c.message||('Error ('+x.status+')')});
}
paso();
});
};
var fin=function(){
var h='<div class="alert '+(insertados?'alert-success':'alert-warning')+'"><b>'+insertados+'</b> equipo(s) importado(s)'+(errores.length?' · <b>'+errores.length+'</b> fila(s) con error':'')+'.</div>';
if(errores.length){
h+='<div style="overflow:auto;max-height:260px"><table class="table table-sm table-bordered tabla-mini mb-0"><thead><tr><th style="width:70px">Fila</th><th>Motivo</th></tr></thead><tbody>'+
errores.map(function(e){return '<tr><td>'+esc(e.fila)+'</td><td>'+esc(e.error)+'</td></tr>'}).join('')+'</tbody></table></div>'+
'<div class="small text-muted mt-1">Corrija esas filas en el archivo y vuelva a importarlo: las que ya entraron se rechazarán como series repetidas.</div>';
}
$('#imp-previa').html(h);
$('#imp-archivo').val('');
s.filas=[];
if(insertados){Toast.success(insertados+' equipo(s) importado(s)');s.alFinal()}
};
paso();
}
};
App.registrar('equiposImport',EquiposImport);
