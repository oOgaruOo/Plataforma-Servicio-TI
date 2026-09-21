// ============================================================
// SIGTI - Wrapper central de peticiones AJAX
// TODOS los módulos usan Api.post / Api.get / Api.put / Api.delete
// Maneja automáticamente: loader, toasts, 401 (sesión), 419 (CSRF)
// ============================================================
const Api = {

  get(url, data)          { return this._send('GET',  url, data); },
  post(url, data = {})    { return this._send('POST', url, data); },
  put(url, data = {})     { return this._send('POST', url, { ...data, _method: 'PUT' }); },
  delete(url, data = {})  { return this._send('POST', url, { ...data, _method: 'DELETE' }); },

  _send(metodo, url, data = {}) {
    return new Promise((resolve, reject) => {
      $.ajax({
        url: BASE_URL + url,
        type: metodo,
        data: data,
        dataType: 'json',
        headers: { 'X-CSRF-Token': CSRF.token },
        beforeSend: () => Loader.show(),
        complete:   () => Loader.hide(),

        success(res) {
          if (res && res.success) {
            resolve(res.data);
          } else {
            Toast.error((res && res.message) || 'Error en la operación');
            reject({ cuerpo: res });
          }
        },

        error(xhr) {
          let cuerpo = null;
          try { cuerpo = JSON.parse(xhr.responseText); } catch (_) {}
          const status = xhr.status;
          const msg = (cuerpo && cuerpo.message) || ('Error de conexión con el servidor (' + status + ')');

          if (status === 401) {                       // sesión muerta
            if (App.usuario) App.sesionExpirada();
            reject({ status, cuerpo });
            return;
          }
          if (status === 419) {                       // token CSRF vencido
            Toast.error('Sesión de seguridad expirada. Recargando la página...');
            setTimeout(() => location.reload(), 1600);
            reject({ status, cuerpo });
            return;
          }
          if (status === 403) {
            Toast.error(msg);                         // sin permiso
          } else if (status === 422) {
            Toast.warning(msg);                       // validación: módulo pinta los campos
          } else {
            Toast.error(msg);
          }
          reject({ status, cuerpo });
        }
      });
    });
  }
};