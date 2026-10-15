<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/guard_vista.php';
?>
<div class="chat-app" id="chat-app">

  <!-- Lista de conversaciones -->
  <aside class="chat-lado" id="ch-lado">
    <div class="chat-lado-cab">
      <div class="d-flex gap-2 mb-2">
        <div class="dropdown flex-grow-1">
          <button class="btn btn-primary btn-sm w-100 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-plus-lg"></i> Nuevo
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" data-nuevo="directo"><i class="bi bi-person"></i> Chat con una persona</a></li>
            <li><a class="dropdown-item" href="#" data-nuevo="area"><i class="bi bi-building"></i> Hablar con un área</a></li>
            <li><a class="dropdown-item" href="#" data-nuevo="grupo"><i class="bi bi-people"></i> Crear un grupo</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-nuevo="reunion"><i class="bi bi-camera-video"></i> Programar una reunión</a></li>
          </ul>
        </div>
        <button type="button" id="ch-sonido" class="btn btn-light btn-sm" title="Sonido de avisos"><i class="bi bi-bell-fill"></i></button>
      </div>
      <input type="search" id="ch-buscar" class="form-control form-control-sm" placeholder="Buscar conversación…">
      <div class="chat-tabs" id="ch-tabs">
        <button type="button" class="activo" data-tab="todos">Todos</button>
        <button type="button" data-tab="personas">Personas</button>
        <button type="button" data-tab="areas">Áreas</button>
        <button type="button" data-tab="grupos">Grupos</button>
        <button type="button" data-tab="reuniones">Reuniones</button>
      </div>
    </div>
    <div class="chat-lista" id="ch-lista"><div class="text-muted small p-3">Cargando…</div></div>
  </aside>

  <!-- Conversación -->
  <section class="chat-centro" id="ch-centro">
    <div class="chat-vacio" id="ch-vacio">
      <i class="bi bi-chat-dots"></i>
      <h5>Chat interno</h5>
      <p>Elija una conversación de la lista o use <b>Nuevo</b> para hablar con una persona o un área,
         crear un grupo o programar una reunión.</p>
    </div>
    <div class="chat-conv d-none" id="ch-conv">
      <div class="chat-cab">
        <button type="button" class="btn btn-sm btn-light d-lg-none" id="ch-atras" title="Volver"><i class="bi bi-arrow-left"></i></button>
        <div class="chat-cab-tit">
          <div class="t" id="ch-titulo"></div>
          <div class="s" id="ch-sub"></div>
        </div>
        <div class="d-flex gap-1">
          <a id="ch-unirse" class="btn btn-sm btn-success d-none" target="_blank" rel="noopener noreferrer"><i class="bi bi-camera-video"></i> Unirse</a>
          <button type="button" class="btn btn-sm btn-light" id="ch-info-btn" title="Datos y participantes"><i class="bi bi-info-circle"></i></button>
        </div>
      </div>
      <div class="chat-aviso d-none" id="ch-aviso"></div>
      <div class="chat-msgs" id="ch-msgs"></div>
      <div class="chat-compositor" id="ch-escribir">
        <div class="chat-barra d-none" id="ch-barra"></div>
        <div class="chat-menciones d-none" id="ch-menciones"></div>
        <div class="chat-emojis d-none" id="ch-emojis"></div>
        <div class="chat-stickers d-none" id="ch-stickers"></div>
        <div class="chat-grabando d-none" id="ch-grabando">
          <span class="punto"></span><span id="ch-grab-tiempo">0:00</span>
          <button type="button" id="ch-grab-cancelar" title="Cancelar"><i class="bi bi-trash"></i></button>
          <button type="button" id="ch-grab-enviar" title="Enviar"><i class="bi bi-send-fill"></i></button>
        </div>
        <div class="chat-escribir">
          <button type="button" id="ch-emoji-btn" class="btn btn-light" title="Emojis">😊</button>
          <button type="button" id="ch-sticker-btn" class="btn btn-light" title="Stickers"><i class="bi bi-images"></i></button>
          <button type="button" id="ch-mic-btn" class="btn btn-light" title="Grabar nota de voz"><i class="bi bi-mic-fill"></i></button>
          <button type="button" id="ch-adj-btn" class="btn btn-light" title="Adjuntar archivo o imagen (también puede pegar una captura)"><i class="bi bi-paperclip"></i></button>
          <input type="file" id="ch-adj" class="d-none" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
          <textarea id="ch-campo" rows="1" maxlength="1000" placeholder="Escriba un mensaje… (@ para mencionar · Enter envía · Mayús+Enter salto de línea)"></textarea>
          <button type="button" id="ch-enviar" class="btn btn-primary" title="Enviar"><i class="bi bi-send-fill"></i></button>
        </div>
      </div>
    </div>
  </section>

  <!-- Datos / participantes -->
  <aside class="chat-der d-none" id="ch-info"></aside>
</div>
