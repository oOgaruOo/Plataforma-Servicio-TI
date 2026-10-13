/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
var EtiquetasDiseno = {
_plantillas: [],
_filtro: 'todos',
_OBJ: {equipo: 'Equipos', personal: 'Personal', locker: 'Lockers'},
_OBJ_CLASE: {equipo: 'text-bg-secondary', personal: 'text-bg-primary', locker: 'text-bg-warning'},
_VARS: {
equipo: ['{codigo}', '{serie}', '{tipo}', '{marca}', '{modelo}', '{activo_fijo}', '{ubicacion}', '{url_consulta}'],
personal: ['{codigo_usuario}', '{nombre_completo}', '{nombres}', '{apellidos}', '{apellido_paterno}', '{apellido_materno}', '{dni}', '{area}', '{area_codigo}', '{cargo}', '{locker}', '{tipo_personal}'],
locker: ['{codigo_usuario}', '{nombre_completo}', '{nombres}', '{apellidos}', '{apellido_paterno}', '{apellido_materno}', '{dni}', '{area}', '{area_codigo}', '{cargo}', '{locker}', '{tipo_personal}']
},
_EJEMPLO: {
equipo: {
'{codigo}': 'EQ-00001', '{codigo_compacto}': 'EQ-00001', '{serie}': 'SN123456789',
'{tipo}': 'Laptop', '{marca}': 'HP', '{modelo}': 'ProBook 450', '{activo_fijo}': 'AF-000123',
'{ubicacion}': 'Oficina de Sistemas', '{area}': 'Sistemas', '{piso}': '2',
'{url_consulta}': BASE_URL + 'acta.php?eq=EQ-00001'
},
persona: {
'{codigo_usuario}': 'SIS-0001', '{nombre_completo}': 'Juan Carlos Pérez Gómez', '{nombres}': 'Juan Carlos',
'{apellidos}': 'Pérez Gómez', '{apellido_paterno}': 'Pérez', '{apellido_materno}': 'Gómez', '{dni}': '44332211',
'{area}': 'Sistemas', '{area_codigo}': 'SIS', '{cargo}': 'Analista de soporte', '{locker}': 'L-015', '{tipo_personal}': 'Empleado'
}
},

init: function() {
this._cargar();
var self = this;

 $(document).off('click.etq', '#btn-nueva-plantilla').on('click.etq', '#btn-nueva-plantilla', function() {
self._abrirEditor(null);
});

 $(document).off('click.etq', '.btn-editar-plantilla').on('click.etq', '.btn-editar-plantilla', function() {
self._abrirEditor(parseInt($(this).data('id')));
});

 $(document).off('click.etq', '.btn-eliminar-plantilla').on('click.etq', '.btn-eliminar-plantilla', function() {
var idPlantilla = parseInt($(this).data('id'));
Swal.fire({
title: '¿Eliminar plantilla?',
icon: 'warning', showCancelButton: true,
confirmButtonText: 'Sí', confirmButtonColor: '#dc3545'
}).then(function(r) {
if (!r.isConfirmed) return;
Api.post('api/etiquetas/plantillas.php', {accion: 'eliminar', id: idPlantilla})
.then(function() { EtiquetasDiseno._cargar() });
});
});

 $(document).off('click.etq', '.btn-duplicar-plantilla').on('click.etq', '.btn-duplicar-plantilla', function() {
Api.post('api/etiquetas/plantillas.php', {accion: 'duplicar', id: parseInt($(this).data('id'))})
.then(function() { EtiquetasDiseno._cargar() });
});

 $(document).off('click.etq', '.etq-filtro').on('click.etq', '.etq-filtro', function() {
self._filtro = String($(this).data('f'));
self._render();
});

 $(document).off('click.etq', '.etq-var').on('click.etq', '.etq-var', function() {
var $c = $('#ep-contenido-nuevo');
$c.val(($c.val() || '') + $(this).data('v')).trigger('focus');
});

 $(document).off('click.etq', '.btn-ver-plantilla').on('click.etq', '.btn-ver-plantilla', function() {
self._ver(parseInt($(this).data('id')));
});
},

_cargar: function() {
var self = this;
 $.get(BASE_URL + 'api/etiquetas/plantillas.php', function(res) {
if (!res.success) { Toast.error('Error: ' + (res.message || '')); return; }
self._plantillas = res.data;
self._render();
}).fail(function() { Toast.error('Error de conexión'); });
},

_render: function() {
var h = '', self = this;
var lista = this._plantillas.filter(function(p) { return self._filtro === 'todos' || (p.objeto || 'equipo') === self._filtro; });
// botones de filtro: Todas / Equipos / Personal / Lockers
var f = '';
[['todos', 'Todas']].concat(Object.keys(this._OBJ).map(function(k) { return [k, self._OBJ[k]]; })).forEach(function(o) {
var n = o[0] === 'todos' ? self._plantillas.length : self._plantillas.filter(function(p) { return (p.objeto || 'equipo') === o[0]; }).length;
f += '<button type="button" class="btn btn-sm ' + (self._filtro === o[0] ? 'btn-dark' : 'btn-outline-dark') + ' etq-filtro" data-f="' + o[0] + '">' + o[1] + ' <span class="badge text-bg-light">' + n + '</span></button> ';
});
 $('#etq-filtros').html(f);
if (!lista.length) {
h = '<div class="text-muted small p-3 text-center">Sin plantillas de este tipo. Cree una nueva.</div>';
} else {
lista.forEach(function(p) {
h += '<div class="col-6 col-md-3"><div class="border rounded p-2 text-center h-100">';
h += '<div style="border:2px dashed #cbd5e1;border-radius:6px;width:' + (p.ancho_mm * 3) + 'px;height:' + (p.alto_mm * 3) + 'px;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#94a3b8">' + p.ancho_mm + '×' + p.alto_mm + 'mm</div>';
h += '<span class="badge ' + (self._OBJ_CLASE[p.objeto || 'equipo'] || 'text-bg-secondary') + ' mb-1">' + esc(self._OBJ[p.objeto || 'equipo'] || 'Equipos') + '</span>';
h += '<b class="small d-block mb-1">' + esc(p.nombre) + '</b>';
h += '<span class="text-muted" style="font-size:11px">' + (p.elementos || []).length + ' elemento(s)</span>';
h += '<div class="mt-2 d-flex gap-1 justify-content-center">';
h += '<button class="btn btn-sm btn-outline-success btn-ver-plantilla" data-id="' + p.id + '" title="Ver etiqueta"><i class="bi bi-eye"></i></button>';
h += '<button class="btn btn-sm btn-outline-primary btn-editar-plantilla" data-id="' + p.id + '" title="Editar"><i class="bi bi-pencil"></i></button>';
h += '<button class="btn btn-sm btn-outline-secondary btn-duplicar-plantilla" data-id="' + p.id + '" title="Duplicar"><i class="bi bi-copy"></i></button>';
h += '<button class="btn btn-sm btn-outline-danger btn-eliminar-plantilla" data-id="' + p.id + '" title="Eliminar"><i class="bi bi-trash"></i></button>';
h += '</div></div></div>';
});
}
 $('#lista-plantillas').html(h);
},

/* Vista previa de la etiqueta (mismas reglas que la impresion: medidas en mm,
   texto en pt, codigo de barras CODE128 y QR como recuadro). Usa datos de ejemplo. */
_ver: function(id) {
var self = this, p = null;
this._plantillas.forEach(function(x) { if (parseInt(x.id) === id) p = x; });
if (!p) { Toast.warning('La plantilla no existe.'); return; }

var ancho = parseInt(p.ancho_mm) || 40, alto = parseInt(p.alto_mm) || 30;
var K = Math.max(3, Math.min(8, Math.floor(440 / ancho)));   // px por mm
var ej = (p.objeto === 'personal' || p.objeto === 'locker') ? this._EJEMPLO.persona : this._EJEMPLO.equipo;
function resolver(t) {
t = String(t == null ? '' : t);
Object.keys(ej).forEach(function(k) { t = t.split(k).join(ej[k]); });
return t;
}
function num(v, def) { v = parseFloat(v); return isNaN(v) ? def : v; }

var h = '<div class="text-center">';
h += '<div class="small text-muted mb-2"><b>' + esc(p.nombre) + '</b> · ' + ancho + ' × ' + alto + ' mm · datos de ejemplo</div>';
h += '<div class="p-3 rounded" style="background:#e9edf2;overflow:auto">';
h += '<div id="etq-prev" style="position:relative;display:inline-block;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.25);outline:1px dashed #94a3b8;outline-offset:2px;overflow:hidden;width:' + (ancho * K) + 'px;height:' + (alto * K) + 'px">';
(p.elementos || []).forEach(function(el) {
var c = resolver(el.contenido);
var pos = 'position:absolute;left:' + (num(el.x, 2) * K) + 'px;top:' + (num(el.y, 2) * K) + 'px;width:' + (num(el.w, 36) * K) + 'px;height:' + (num(el.h, 5) * K) + 'px;';
if (el.tipo === 'texto') {
var fs = num(el.fontSize, 8) * 0.3528 * K;                       // pt -> mm -> px
h += '<div style="' + pos + 'font-size:' + fs + 'px;font-weight:' + (el.negrita ? '700' : '400') + ';text-align:' + esc(el.align || 'center') + ';color:#000;line-height:1.1;overflow:hidden;white-space:nowrap">' + esc(c) + '</div>';
} else if (el.tipo === 'barcode') {
h += '<div style="' + pos + 'display:flex;align-items:center;justify-content:center"><svg class="etq-bc" style="width:100%;height:100%" data-cod="' + esc(c.replace(/ /g, '')) + '"></svg></div>';
} else if (el.tipo === 'qr') {
h += '<div style="' + pos + 'display:flex;align-items:center;justify-content:center;text-align:center;border:1px solid #000;font-size:' + (8 * 0.3528 * K) + 'px;color:#000;overflow:hidden">QR:' + esc(c.substring(0, 20)) + '</div>';
}
});
h += '</div></div>';
h += '<div class="text-muted small mt-2">Así se verá al imprimir. El tamaño en pantalla es proporcional al real.</div>';
h += '<div class="mt-3"><button type="button" class="btn btn-outline-primary btn-sm me-2" id="etq-prev-editar"><i class="bi bi-pencil"></i> Editar plantilla</button>';
h += '<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button></div>';
h += '</div>';

Modal.abrir('Vista de la etiqueta', h, 'modal-md');

$('#etq-prev svg.etq-bc').each(function() {
var cod = this.getAttribute('data-cod');
if (!cod) return;
try {
JsBarcode(this, cod, {format: 'CODE128', width: 2, height: 60, displayValue: false, margin: 0, flat: true});
} catch (e) { $(this).replaceWith('<span class="small text-danger">Código inválido</span>'); }
});
$('#etq-prev-editar').on('click', function() { self._abrirEditor(id); });
},

_abrirEditor: function(id) {
var p = null;
this._plantillas.forEach(function(x) { if (x.id === id) p = x; });
var elementos = p ? JSON.parse(JSON.stringify(p.elementos)) : [];

var html = '<form id="form-plantilla">';
var objActual = p ? (p.objeto || 'equipo') : (this._filtro !== 'todos' ? this._filtro : 'equipo');
html += '<div class="row mb-3">';
html += '<div class="col-md-4"><label>Nombre *</label><input class="form-control" id="ep-nombre" value="' + (p ? esc(p.nombre) : '') + '" maxlength="100"></div>';
html += '<div class="col-md-2"><label>Para</label><select class="form-select" id="ep-objeto">';
Object.keys(this._OBJ).forEach(function(k) { html += '<option value="' + k + '"' + (objActual === k ? ' selected' : '') + '>' + EtiquetasDiseno._OBJ[k] + '</option>'; });
html += '</select></div>';
html += '<div class="col-md-3"><label>Ancho (mm)</label><input type="number" class="form-control" id="ep-ancho" value="' + (p ? p.ancho_mm : 40) + '" min="20" max="100"></div>';
html += '<div class="col-md-3"><label>Alto (mm)</label><input type="number" class="form-control" id="ep-alto" value="' + (p ? p.alto_mm : 30) + '" min="15" max="100"></div>';
html += '</div>';

html += '<div class="border rounded p-2 mb-3">';
html += '<div class="form-label mb-2"><b>Elementos</b> <span class="text-muted small">(texto, barcode, QR)</span></div>';
html += '<div class="small text-muted mb-2">Variables (clic para agregar): <span id="ep-vars"></span></div>';
html += '<div id="ep-elementos"></div>';
html += '<div class="row g-2 mt-2">';
html += '<div class="col-md-2"><select class="form-select form-select-sm" id="ep-tipo-nuevo">';
html += '<option value="texto">Texto</option>';
html += '<option value="barcode">Barcode</option>';
html += '<option value="qr">QR</option></select></div>';
html += '<div class="col-md-4"><input class="form-control form-control-sm" id="ep-contenido-nuevo" placeholder="Contenido o {variable}"></div>';
html += '<div class="col-md-2"><input type="number" class="form-control form-control-sm" id="ep-fontsize-nuevo" value="8" min="4" max="20" placeholder="pt"></div>';
html += '<div class="col-md-2"><input type="number" class="form-control form-control-sm" id="ep-y-nuevo" value="' + (elementos.length * 6 + 1) + '" placeholder="Y (mm)"></div>';
html += '<div class="col-md-2"><button type="button" class="btn btn-sm btn-primary w-100" id="ep-agregar"><i class="bi bi-plus-lg"></i></button></div>';
html += '</div></div>';

html += '<div class="text-end">';
html += '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> ';
html += '<button type="button" class="btn btn-primary" id="ep-guardar">Guardar plantilla</button></div>';
html += '</form>';

Modal.abrir(p ? 'Editar plantilla' : 'Nueva plantilla', html, 'modal-lg');

function pintarVars() {
var o = $('#ep-objeto').val(), v = '';
(EtiquetasDiseno._VARS[o] || []).forEach(function(x) { v += '<code class="etq-var" style="cursor:pointer" data-v="' + x + '">' + x + '</code> '; });
 $('#ep-vars').html(v);
}
 $('#ep-objeto').on('change', pintarVars);
pintarVars();

function pintar() {
var h = '';
elementos.forEach(function(el, i) {
h += '<div class="d-flex gap-1 align-items-center border-bottom py-1">';
h += '<span class="badge text-bg-light border">' + el.tipo + '</span>';
h += '<input type="text" class="form-control form-control-sm flex-fill ep-el-cont" data-i="' + i + '" value="' + esc(el.contenido) + '">';
h += '<input type="number" class="form-control form-control-sm ep-el-fs" data-i="' + i + '" value="' + (el.fontSize || 8) + '" style="width:60px" title="pt">';
h += '<input type="number" class="form-control form-control-sm ep-el-y" data-i="' + i + '" value="' + (el.y || 1) + '" style="width:60px" title="Y mm">';
h += '<button type="button" class="btn btn-sm btn-outline-danger ep-el-quitar" data-i="' + i + '"><i class="bi bi-x-lg"></i></button>';
h += '</div>';
});
 $('#ep-elementos').html(h || '<div class="text-muted small">Sin elementos. Agregue abajo.</div>');

 $('.ep-el-quitar').off('click').on('click', function() {
elementos.splice(parseInt($(this).data('i')), 1);
pintar();
});
 $('.ep-el-cont').off('input').on('input', function() {
elementos[parseInt($(this).data('i'))].contenido = $(this).val();
});
 $('.ep-el-fs').off('input').on('input', function() {
elementos[parseInt($(this).data('i'))].fontSize = parseInt($(this).val()) || 8;
});
 $('.ep-el-y').off('input').on('input', function() {
elementos[parseInt($(this).data('i'))].y = parseInt($(this).val()) || 1;
});
}
pintar();

 $('#ep-agregar').on('click', function() {
var contenido = $.trim($('#ep-contenido-nuevo').val() || '');
if (!contenido) { Toast.warning('Escriba el contenido.'); return; }
elementos.push({
tipo: $('#ep-tipo-nuevo').val(),
contenido: contenido,
fontSize: parseInt($('#ep-fontsize-nuevo').val()) || 8,
x: 2, y: parseInt($('#ep-y-nuevo').val()) || (elementos.length * 6 + 1),
w: 36, h: 5, negrita: true
});
 $('#ep-contenido-nuevo').val('');
pintar();
});

 $('#ep-guardar').on('click', function() {
var nombre = $.trim($('#ep-nombre').val() || '');
if (!nombre) { Toast.warning('Escriba el nombre.'); return; }
if (!elementos.length) { Toast.warning('Agregue elementos.'); return; }
Api.post('api/etiquetas/plantillas.php', {
accion: 'guardar',
id: p ? p.id : 0,
nombre: nombre,
objeto: $('#ep-objeto').val(),
ancho_mm: parseInt($('#ep-ancho').val()) || 40,
alto_mm: parseInt($('#ep-alto').val()) || 30,
elementos: JSON.stringify(elementos)
}).then(function() {
Modal.cerrar();
EtiquetasDiseno._cargar();
});
});
}
};

App.registrar('etiquetas', EtiquetasDiseno);