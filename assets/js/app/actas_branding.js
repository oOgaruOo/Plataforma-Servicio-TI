// ============================================================
// SIGTI - Branding de actas: subir/ver/quitar logo de empresa
// Independiente del modulo Actas (usa Modal/Api/Toast globales)
// ============================================================

 $(document).on('click', '#btn-branding-acta', function () {

  const t = Date.now();
  const html = `
  <div class="text-center mb-3">
    <img id="bl-preview" src="${BASE_URL}api/configuracion/logo.php?accion=ver&t=${t}"
         style="max-height:110px;max-width:240px;border:1px solid #e5e9f0;border-radius:8px;padding:8px;background:#fff">
    <div id="bl-sin-logo" class="text-muted small mt-2" style="display:none">
      Sin logo configurado — las actas salen solo con el nombre de la empresa.
    </div>
  </div>

  <div class="alert alert-light border py-2 small">
    <i class="bi bi-info-circle text-primary"></i>
    El logo se incrusta en el <b>encabezado de todas las actas</b> y el documento queda autocontenido:
    al imprimir, guardar PDF o compartir por WhatsApp/correo el logo viaja dentro del archivo.
    Recomendado: <b>PNG horizontal con fondo blanco o transparente</b>.
    El RUC, color y textos del pie se configuran en la tabla <code>configuracion</code>.
  </div>

  <form id="form-logo">
    <div class="mb-3" data-campo="logo">
      <label class="form-label">Nueva imagen (PNG o JPG, máx. 3 MB)</label>
      <input type="file" class="form-control" id="bl-file" accept=".png,.jpg,.jpeg">
    </div>
    <div class="d-flex justify-content-between align-items-center">
      <button type="button" class="btn btn-outline-danger btn-sm" id="bl-quitar">
        <i class="bi bi-trash"></i> Quitar logo
      </button>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="bl-subir">
          <i class="bi bi-upload"></i> Guardar logo
        </button>
      </div>
    </div>
  </form>`;

  Modal.abrir('Logo y encabezado de las actas', html, 'modal-md');

  // si no hay logo, la imagen da 404 -> ocultarla
  $('#bl-preview').on('error', function () {
    $(this).hide();
    $('#bl-sin-logo').show();
  });

  $('#bl-subir').on('click', () => {
    const file = $('#bl-file')[0].files[0];
    if (!file) { Toast.warning('Seleccione la imagen del logo.'); return; }

    const fd = new FormData();
    fd.append('accion', 'subir');
    fd.append('logo', file);

    $.ajax({
      url: BASE_URL + 'api/configuracion/logo.php',
      type: 'POST', data: fd, contentType: false, processData: false,
      headers: { 'X-CSRF-Token': CSRF.token },
      beforeSend: () => Loader.show(), complete: () => Loader.hide(),
      success: res => {
        if (res.success) {
          Toast.success(res.message);
          // refrescar vista previa
          $('#bl-sin-logo').hide();
          $('#bl-preview').show().attr('src', BASE_URL + 'api/configuracion/logo.php?accion=ver&t=' + Date.now());
          $('#bl-file').val('');
        } else {
          Toast.error(res.message || 'Error al subir');
          if (res.errors && res.errors.logo) Toast.warning(res.errors.logo);
        }
      },
      error: () => Toast.error('Error de conexión al subir el logo.')
    });
  });

  $('#bl-quitar').on('click', () => {
    Swal.fire({
      title: '¿Quitar el logo de las actas?',
      text: 'Las actas volverán al encabezado solo-texto con el nombre de la empresa.',
      icon: 'question', showCancelButton: true,
      confirmButtonText: 'Sí, quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626'
    }).then(r => {
      if (!r.isConfirmed) return;
      Api.post('api/configuracion/logo.php', { accion: 'quitar' }).then(() => {
        $('#bl-preview').hide();
        $('#bl-sin-logo').show();
      });
    });
  });
});