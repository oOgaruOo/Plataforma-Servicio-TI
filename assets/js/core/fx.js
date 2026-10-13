/* @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026 */
/* ============================================================
   SIGTI - Movimiento de la interfaz (acompana a assets/css/sigti-fx.css)
   - Cada pantalla que se abre sube en cascada (un solo momento orquestado).
   - Las cifras de las tarjetas cuentan hasta su valor.
   - Los botones responden con una onda al pulsarlos.
   - El menu de la foto permite apagar las animaciones (equipos lentos).
   No cambia nada del funcionamiento: si falla, la pantalla se ve igual que antes.
   ============================================================ */
var FX = (function () {
  var KEY = 'sigti_fx';
  var root = document.documentElement;
  var reducido = false;
  try { reducido = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); } catch (e) { }
  var guardado = null;
  try { guardado = window.localStorage.getItem(KEY); } catch (e) { }
  var activo = guardado ? guardado === 'on' : !reducido;

  var visto = {};   // cifras ya animadas en esta pantalla (para no repetir cada vez que se refresca el panel)
  var SEL_NUM = '.mini-stat .n, .dash-card .num, .rp-kpi .fs-4, .fx-num';

  function aplicar() {
    root.classList.toggle('fx-off', !activo);
    root.classList.toggle('fx-on', activo);
    var $t = document.getElementById('fx-toggle-txt');
    if ($t) $t.textContent = activo ? 'Animaciones: activadas' : 'Animaciones: apagadas';
    var $i = document.getElementById('fx-toggle-ico');
    if ($i) $i.className = 'bi ' + (activo ? 'bi-magic' : 'bi-slash-circle');
    try {
      if (window.Chart && window.Chart.defaults) window.Chart.defaults.animation = activo ? { duration: 900, easing: 'easeOutQuart' } : false;
    } catch (e) { }
  }
  function poner(v) {
    activo = !!v;
    try { window.localStorage.setItem(KEY, activo ? 'on' : 'off'); } catch (e) { }
    aplicar();
  }

  /* ---------- cifras que cuentan ---------- */
  function leer(t) {
    var m = /^([^\d\-]*?)(-?\d[\d.,]*)(\s*%?)$/.exec(String(t).trim());
    if (!m) return null;
    var num = m[2], punto = num.lastIndexOf('.');
    var dec = punto >= 0 && punto > num.lastIndexOf(',') ? num.length - punto - 1 : 0;
    var v = parseFloat(num.replace(/,/g, ''));
    if (!isFinite(v)) return null;
    return { pre: m[1], suf: m[3], v: v, dec: dec, miles: num.indexOf(',') >= 0 };
  }
  function formato(p, v) {
    var s = Math.abs(v).toFixed(p.dec);
    if (p.miles) { var q = s.split('.'); q[0] = q[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); s = q.join('.'); }
    return p.pre + (v < 0 ? '-' : '') + s + p.suf;
  }
  function contar(el) {
    if (!activo || el.getAttribute('data-fx-busy')) return;
    var texto = el.textContent;
    if (texto === el.getAttribute('data-fx-val')) return;
    var tarjeta = el.closest('.dash-card,.mini-stat,.rp-kpi') || el.parentElement;
    var et = tarjeta ? tarjeta.querySelector('.et,.t,.small') : null;
    var clave = (et ? et.textContent : '') + '|' + (el.getAttribute('data-k') || '');
    if (clave !== '|' && visto[clave] === texto) { el.setAttribute('data-fx-val', texto); return; }
    visto[clave] = texto;
    var p = leer(texto);
    el.setAttribute('data-fx-val', texto);
    if (!p || p.v === 0 || Math.abs(p.v) > 1e9) return;
    el.setAttribute('data-fx-busy', '1');
    var t0 = null, dur = Math.min(1100, 450 + Math.log10(Math.abs(p.v) + 1) * 170);
    (window.requestAnimationFrame || function (f) { return setTimeout(f, 16); })(function paso(ts) {
      if (t0 === null) t0 = ts || Date.now();
      var k = Math.min(1, ((ts || Date.now()) - t0) / dur), e = 1 - Math.pow(1 - k, 4);
      if (k < 1) {
        el.textContent = formato(p, p.v * e);
        (window.requestAnimationFrame || function (f) { return setTimeout(f, 16); })(paso);
      } else {
        el.textContent = texto;
        el.removeAttribute('data-fx-busy');
      }
    });
  }
  function candidatos(nodo, lista) {
    var el = nodo && nodo.nodeType === 1 ? nodo : (nodo && nodo.parentElement);
    if (!el) return;
    var c = el.closest ? el.closest(SEL_NUM) : null;
    if (c) lista.push(c);
    else if (el.querySelectorAll && nodo.nodeType === 1) {
      var h = el.querySelectorAll(SEL_NUM);
      for (var i = 0; i < h.length && i < 80; i++) lista.push(h[i]);
    }
  }

  /* ---------- entrada de pantalla ---------- */
  function entrar(cont) {
    visto = {};
    if (!activo) return;
    var lista = [], hijos = cont.children;
    for (var i = 0; i < hijos.length; i++) {
      var k = hijos[i], tag = k.tagName;
      if (tag === 'STYLE' || tag === 'SCRIPT' || tag === 'LINK') continue;
      if (k.classList.contains('row') && k.children.length > 1) {
        for (var j = 0; j < k.children.length; j++) lista.push(k.children[j]);
      } else lista.push(k);
    }
    lista.slice(0, 16).forEach(function (el, n) {
      el.style.setProperty('--fx-i', n);
      el.classList.remove('fx-rise');
      void el.offsetWidth;
      el.classList.add('fx-rise');
      var fin = function () { el.classList.remove('fx-rise'); el.removeEventListener('animationend', fin); };
      el.addEventListener('animationend', fin);
    });
  }

  function iniciar() {
    aplicar();
    if (!window.MutationObserver) return;
    var vista = document.getElementById('vista-container');
    if (vista) {
      new MutationObserver(function (ms) {
        var nuevo = false;
        for (var i = 0; i < ms.length; i++) if (ms[i].target === vista && ms[i].type === 'childList' && ms[i].addedNodes.length) nuevo = true;
        if (nuevo) entrar(vista);
      }).observe(vista, { childList: true });
      var pend = null, lista = [];
      new MutationObserver(function (ms) {
        if (!activo) return;
        for (var i = 0; i < ms.length; i++) {
          var t = ms[i].target;
          if (t.nodeType === 1 && t.getAttribute && t.getAttribute('data-fx-busy')) continue;
          if (t.parentElement && t.parentElement.getAttribute && t.parentElement.getAttribute('data-fx-busy')) continue;
          if (ms[i].type === 'childList') for (var j = 0; j < ms[i].addedNodes.length; j++) candidatos(ms[i].addedNodes[j], lista);
          candidatos(t, lista);
        }
        if (lista.length && !pend) {
          pend = setTimeout(function () {
            var l = lista; lista = []; pend = null;
            var visto = [];
            l.forEach(function (el) { if (visto.indexOf(el) < 0) { visto.push(el); contar(el); } });
          }, 30);
        }
      }).observe(vista, { childList: true, subtree: true, characterData: true });
    }
    var menu = document.getElementById('menu-nav');
    if (menu) new MutationObserver(function () {
      var it = menu.querySelectorAll('.menu-item');
      for (var i = 0; i < it.length; i++) it[i].style.setProperty('--fx-i', i);
    }).observe(menu, { childList: true });
    var tit = document.getElementById('titulo-vista');
    if (tit) new MutationObserver(function () {
      if (!activo) return;
      tit.classList.remove('fx-title'); void tit.offsetWidth; tit.classList.add('fx-title');
    }).observe(tit, { childList: true, characterData: true, subtree: true });

    // onda en los botones
    document.addEventListener('pointerdown', function (e) {
      if (!activo || !e.target.closest) return;
      var b = e.target.closest('.btn');
      if (!b || b.disabled || b.classList.contains('disabled') || b.classList.contains('btn-close')) return;
      var r = b.getBoundingClientRect(), d = Math.max(r.width, r.height) * 2;
      var s = document.createElement('span');
      s.className = 'fx-ripple';
      s.style.cssText = 'width:' + d + 'px;height:' + d + 'px;left:' + (e.clientX - r.left - d / 2) + 'px;top:' + (e.clientY - r.top - d / 2) + 'px';
      b.appendChild(s);
      setTimeout(function () { if (s.parentNode) s.parentNode.removeChild(s); }, 650);
    }, true);

    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target.closest('#btn-fx-toggle') : null;
      if (t) { poner(!activo); }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
  return { activo: function () { return activo; }, poner: poner, _leer: leer, _formato: formato };
})();
