<?php
require_once __DIR__ . '/core/bootstrap.php';
 $csrfToken = Csrf::token();   // el token viaja embebido al JS (misma sesión)

// Cache-busting: usa la fecha de última modificación de cada archivo,
// así el navegador SIEMPRE pide la versión fresca tras cualquier cambio.
 $v = fn($ruta) => filemtime(ROOT_PATH . $ruta);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APP_NAME ?> — Sistema Integral de Gestión TI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/sigti.css?v=<?= $v('/assets/css/sigti.css') ?>" rel="stylesheet">
</head>
<body>

<!-- ============ PANTALLA DE LOGIN ============ -->
<div id="pantalla-login" class="pantalla-login hidden">
  <div class="login-card">
    <div class="login-foto hidden" id="login-foto">
          <img id="login-foto-img" alt="">
        </div>
        <div class="login-logo" id="login-logo-icona">🖥️</div>
    <h1>SIGTI</h1>
    <p class="login-sub">Sistema Integral de Gestión TI — Área de Sistemas</p>

    <form id="form-login" autocomplete="off">
      <label for="login-usuario">Usuario</label>
      <input type="text" id="login-usuario" maxlength="50" required autofocus>

      <label for="login-password">Contraseña</label>
      <div class="campo-pass">
        <input type="password" id="login-password" maxlength="100" required>
        <button type="button" id="btn-ver-pass" tabindex="-1" title="Mostrar/ocultar">
          <i class="bi bi-eye"></i>
        </button>
      </div>

      <div id="login-error" class="login-error"></div>
      <button type="submit" id="btn-login">Ingresar</button>
    </form>
    <div class="login-pie">v1.0 — Desarrollo por pasos</div>
  </div>
</div>

<!-- ============ SHELL DE LA APLICACIÓN ============ -->
<div id="app" class="hidden">
  <aside id="sidebar">
    <div class="sidebar-brand">🖥️ SIGTI</div>
    <nav id="menu-nav"></nav>
    <div class="sidebar-pie">Área de Sistemas</div>
  </aside>

  <div id="contenido">
    <header id="topbar">
      <button id="btn-menu" title="Mostrar/ocultar menú"><i class="bi bi-list"></i></button>
      <h2 id="titulo-vista">Dashboard</h2>
      <div class="topbar-derecha">
        <button id="btn-notificaciones" title="Notificaciones">
          <i class="bi bi-bell"></i>
          <span id="badge-notif" class="badge-notif hidden"></span>
        </button>
        <div class="dropdown">
          <button id="btn-avatar" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <span id="avatar-iniciales">--</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li class="px-3 py-2">
              <div id="topbar-nombre" class="fw-semibold" style="font-size:14px"></div>
              <div id="topbar-rol" class="text-muted" style="font-size:12px"></div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <button class="dropdown-item text-danger" id="btn-logout">
                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
              </button>
            </li>
          </ul>
        </div>
      </div>
    </header>
    <main id="vista-container"></main>
  </div>
</div>

<!-- ============ GLOBALES ============ -->
<div id="loader-global"><div class="spinner"></div></div>
<div id="toast-container"></div>

<!-- Modal genérico: TODOS los módulos lo reutilizan -->
<div class="modal fade" id="modal-general" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-general-titulo"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modal-general-cuerpo"></div>
    </div>
  </div>
</div>

<noscript><div style="padding:30px;text-align:center">Este sistema requiere JavaScript activado.</div></noscript>

<script>
  const BASE_URL   = '<?= BASE_URL ?>';
  const CSRF_TOKEN = '<?= $csrfToken ?>';
</script>

<!-- Librerías CDN -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- Núcleo del sistema (con cache-busting automático) -->
<script src="<?= BASE_URL ?>assets/js/core/app.js?v=<?= $v('/assets/js/core/app.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/toast.js?v=<?= $v('/assets/js/core/toast.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/loader.js?v=<?= $v('/assets/js/core/loader.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/ajax.js?v=<?= $v('/assets/js/core/ajax.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/menu.js?v=<?= $v('/assets/js/core/menu.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/router.js?v=<?= $v('/assets/js/core/router.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/notificaciones.js?v=<?= $v('/assets/js/core/notificaciones.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/datatables.js?v=<?= $v('/assets/js/core/datatables.js') ?>"></script>

<!-- Módulos de la aplicación (con cache-busting automático) -->
<script src="<?= BASE_URL ?>assets/js/app/catalogos.js?v=<?= $v('/assets/js/app/catalogos.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/usuarios.js?v=<?= $v('/assets/js/app/usuarios.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/personal.js?v=<?= $v('/assets/js/app/personal.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/equipos.js?v=<?= $v('/assets/js/app/equipos.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/tickets.js?v=<?= $v('/assets/js/app/tickets.js') ?>"></script>

<script src="<?= BASE_URL ?>assets/js/app/mantenimiento.js?v=<?= $v('/assets/js/app/mantenimiento.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/actas.js?v=<?= $v('/assets/js/app/actas.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/actas_branding.js?v=<?= $v('/assets/js/app/actas_branding.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/asignaciones.js?v=<?= $v('/assets/js/app/asignaciones.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app/equipos_import.js?v=<?= $v('/assets/js/app/equipos_import.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
  // foto del ultimo usuario (login) y avatar del topbar
  (function () {
    var fotoGuardada = localStorage.getItem('sigti_foto');
    var nomGuardado  = localStorage.getItem('sigti_nombre') || '';
    var usrGuardado  = localStorage.getItem('sigti_usuario') || '';
    if (fotoGuardada) {
      var img = document.getElementById('login-foto-img');
      var box = document.getElementById('login-foto');
      if (img && box) { img.src = fotoGuardada; box.classList.remove('hidden'); }
      var ic = document.getElementById('login-logo-icona');
      if (ic) ic.style.display = 'none';
    }
    if (usrGuardado) {
      var inp = document.getElementById('login-usuario');
      if (inp) inp.value = usrGuardado;
    }
  })();
  function guardarFotoLogin(fotoB64, nombre, usuario) {
    try {
      if (fotoB64) localStorage.setItem('sigti_foto', fotoB64);
      else localStorage.removeItem('sigti_foto');
      localStorage.setItem('sigti_nombre', nombre || '');
      localStorage.setItem('sigti_usuario', usuario || '');
    } catch (e) {}
  }
</script>
<script src="<?= BASE_URL ?>assets/js/app/seguimiento.js?v=<?= $v('/assets/js/app/seguimiento.js') ?>"></script>
</body>
</html>