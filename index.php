<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/core/bootstrap.php';
 $csrfToken = Csrf::token();

// Cache-busting optimizado: version estatica (storage/.version)
// Actualizarla con bump-version.php tras cambiar JS/CSS
 $verFile = __DIR__ . '/storage/.version';
 $ver = is_file($verFile) ? (string) file_get_contents($verFile) : '1';
 $v = fn($ruta) => $ver;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="author" content="<?= htmlspecialchars(Autor::NOMBRE) ?>">
<meta name="generator" content="<?= htmlspecialchars(Autor::CODIGO) ?>">
<meta name="citation_author" content="<?= htmlspecialchars(Autor::NOMBRE) ?>">
<meta name="citation_author_orcid" content="https://orcid.org/<?= Autor::ORCID ?>">
<link rel="author" href="https://orcid.org/<?= Autor::ORCID ?>">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => Autor::SISTEMA,
    'applicationCategory' => 'BusinessApplication', 'copyrightYear' => Autor::ANIO,
    'copyrightHolder' => ['@type' => 'Person', 'name' => Autor::NOMBRE, '@id' => 'https://orcid.org/' . Autor::ORCID],
    'author' => ['@type' => 'Person', 'name' => Autor::NOMBRE, '@id' => 'https://orcid.org/' . Autor::ORCID, 'sameAs' => 'https://orcid.org/' . Autor::ORCID],
    'identifier' => Autor::CODIGO,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<!-- <?= Autor::SISTEMA ?> · Desarrollado por <?= Autor::NOMBRE ?> · ORCID <?= Autor::ORCID ?> · Código de autor <?= Autor::CODIGO ?> · Sello <?= Autor::sello() ?> -->
<title><?= APP_NAME ?> — Sistema Integral de Gestión TI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link href="<?= BASE_URL ?>assets/css/sigti.css?v=<?= $v('/assets/css/sigti.css') ?>" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/sigti-fx.css?v=<?= $ver ?>" rel="stylesheet">
</head>
<body>

<!-- ============ PANTALLA DE LOGIN ============ -->
<div id="pantalla-login" class="pantalla-login hidden">
  <div class="login-card">
    <div class="login-logo-chip" id="login-logo-empresa-wrap" style="display:none"><img id="login-logo-empresa" src="" alt="Logo"></div>
    <div class="login-foto hidden" id="login-foto">
      <img id="login-foto-img" alt="" class="hidden">
      <span id="login-foto-ini"></span>
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
    <div class="login-pie">v1.0 — Plataforma de Servicio TI
      <div class="login-dev">Desarrollado por <b><?= htmlspecialchars(Autor::NOMBRE) ?></b><br><span title="Código de autor"><?= htmlspecialchars(Autor::CODIGO) ?></span><br><a class="login-orcid" href="https://orcid.org/<?= Autor::ORCID ?>" target="_blank" rel="noopener author" title="Perfil ORCID del autor"><span class="orcid-ico">iD</span> orcid.org/<?= Autor::ORCID ?></a></div>
    </div>
  </div>
</div>

<!-- ============ SHELL DE LA APLICACIÓN ============ -->
<div id="app" class="hidden">
  <aside id="sidebar">
    <div class="sidebar-brand">
      <span class="sidebar-logo-chip" id="sidebar-logo-wrap" style="display:none"><img id="sidebar-logo" src="" alt=""></span>
      <span>🖥️ SIGTI</span>
    </div>
    <div class="menu-buscar-wrap">
      <i class="bi bi-search"></i>
      <input type="search" id="menu-buscar" placeholder="Buscar en el menú…" autocomplete="off" aria-label="Buscar en el menú">
      <kbd title="Atajos: / o Ctrl+K">/</kbd>
    </div>
    <nav id="menu-nav"></nav>
    <div class="sidebar-pie">
      <span class="pie-txt">Área de Sistemas</span>
      <button type="button" id="menu-compacto" title="Contraer el menú (solo iconos)"><i class="bi bi-chevron-double-left"></i></button>
    </div>
  </aside>

  <div id="contenido">
    <header id="topbar">
      <button id="btn-menu" title="Mostrar/ocultar menú"><i class="bi bi-list"></i></button>
      <h2 id="titulo-vista">Dashboard</h2>
      <div class="topbar-derecha">
        <div class="dropdown dropdown-notif">
          <button id="btn-notificaciones" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notificaciones">
            <i class="bi bi-bell" id="icono-campana"></i>
            <span id="badge-notif" class="badge-notif hidden"></span>
          </button>
          <div class="dropdown-menu dropdown-menu-end panel-notif">
            <div class="panel-notif-header">
              <span class="fw-semibold small">Notificaciones</span>
              <button class="btn btn-link btn-sm py-0 px-1" id="np-todas" title="Marcar todas como leídas">Marcar todas</button>
            </div>
            <div id="panel-notif-lista" class="panel-notif-lista"><div class="panel-notif-vacio text-muted small">Sin notificaciones.</div></div>
          </div>
        </div>
        <div class="dropdown">
          <button id="btn-avatar" data-bs-toggle="dropdown" data-bs-auto-close="outside" class="avatar-circulo-perfecto">
            <img id="avatar-foto" src="" alt="" style="display:none">
            <span id="avatar-iniciales">--</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li class="px-3 py-2">
              <div id="topbar-nombre" class="fw-semibold" style="font-size:14px"></div>
              <div id="topbar-rol" class="text-muted" style="font-size:12px"></div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <button class="dropdown-item" id="btn-mi-foto">
                <i class="bi bi-camera"></i> Cambiar mi foto
              </button>
            </li>
            <li>
              <button class="dropdown-item" id="btn-fx-toggle" title="Apague las animaciones si el equipo va lento">
                <i class="bi bi-magic" id="fx-toggle-ico"></i> <span id="fx-toggle-txt">Animaciones: activadas</span>
              </button>
            </li>
            <li>
              <button class="dropdown-item" id="btn-acerca">
                <i class="bi bi-info-circle"></i> Acerca de SIGTI
              </button>
            </li>
            <li>
              <button class="dropdown-item text-danger" id="btn-logout">
                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
              </button>
            </li>
          </ul>
        </div>
      </div>
    </header>
    <div id="licencia-banner" class="hidden" role="status"></div>
    <main id="vista-container"></main>
  </div>
</div>

<!-- ============ GLOBALES ============ -->
<div id="loader-global"><div class="spinner"></div></div>
<div id="toast-container"></div>

<!-- Modal genérico -->
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
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Núcleo del sistema -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/app.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/toast.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/loader.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/ajax.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/foto.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/documento.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/visor.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/autor.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/sistema.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/menu.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/router.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/fx.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/notificaciones.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/datatables.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/core/chat.js?v=<?= $ver ?>"></script>

<!-- Módulos de la aplicación -->
<script src="<?= BASE_URL ?>assets/js/app/catalogos.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/usuarios.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/roles.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/personal.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/equipos.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/equipos_import.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/software.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/capacitaciones.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/seguimiento.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/tickets.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/mantenimiento.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/asignaciones.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/proyectos.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/chat.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/actas.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/actas_branding.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/anuncios.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/reportes.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/guias.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/auditoria.js?v=<?= $ver ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/licencia.js?v=<?= $ver ?>"></script>

<script>
window.SIGTI_INFO = <?= json_encode(Autor::datos() + ['version' => trim($ver)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
// ---- ULTIMO USUARIO EN EL LOGIN (la foto se busca en app.js al escribir/cargar el usuario) ----
(function () {
  var usr = '';
  try { usr = localStorage.getItem('sigti_usuario') || ''; } catch (e) {}
  if (usr) {
    var inp = document.getElementById('login-usuario');
    if (inp) inp.value = usr;
  }
})();

// ---- LOGO DE EMPRESA (login + sidebar) ----
(function () {
  var img = new Image();
  img.onload = function () {
    var lgW = document.getElementById('login-logo-empresa-wrap');
        var lg = document.getElementById('login-logo-empresa');
    if (lg && lgW) { lg.src = img.src; lgW.style.display = 'flex'; }
    var sbW = document.getElementById('sidebar-logo-wrap');
        var sb = document.getElementById('sidebar-logo');
    if (sb && sbW) { sb.src = img.src; sbW.style.display = 'inline-flex'; }
  };
  img.src = '<?= BASE_URL ?>api/configuracion/logo-publico.php?t=' + Date.now();
})();
</script>
<script src="<?= BASE_URL ?>assets/js/app/permisos.js?v=<?= $v('/assets/js/app/permisos.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/etiquetas_diseno.js?v=<?= $v('/assets/js/app/etiquetas_diseno.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/app/carnets.js?v=<?= $v('/assets/js/app/carnets.js') ?>"></script>
</body>
</html>