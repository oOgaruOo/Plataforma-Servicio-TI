<?php
require_once __DIR__ . '/core/bootstrap.php';
 $csrfToken = Csrf::token();   // el token viaja embebido al JS (misma sesión)
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APP_NAME ?> — Sistema Integral de Gestión TI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/sigti.css" rel="stylesheet">
</head>
<body>

<!-- ============ PANTALLA DE LOGIN ============ -->
<div id="pantalla-login" class="pantalla-login hidden">
  <div class="login-card">
    <div class="login-logo">🖥️</div>
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
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= BASE_URL ?>assets/js/core/app.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/toast.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/loader.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/ajax.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/menu.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/router.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/notificaciones.js"></script>
</body>
</html>