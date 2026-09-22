<?php
require_once __DIR__ . '/../../core/guard_vista.php';

 $puede = Auth::can('usuarios', 'gestionar');
if (!$puede):
?>
  <div class="alert alert-warning mb-0">
    <i class="bi bi-shield-lock"></i> No tiene permisos para gestionar usuarios del sistema.
  </div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-person-gear me-1"></i>Cuentas con acceso al sistema</span>
    <button id="btn-nuevo-usuario" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg"></i> Nuevo usuario
    </button>
  </div>
  <div class="card-body">
    <table id="tb-usuarios" class="table table-bordered table-hover align-middle w-100">
      <thead>
        <tr>
          <th>Usuario</th><th>Nombre completo</th><th>Rol</th><th>Personal vinculado</th>
          <th>Correo</th><th>Ultimo acceso</th><th>Estado</th><th>&nbsp;</th>
        </tr>
      </thead>
    </table>
    <div class="text-muted small mt-2">
      <i class="bi bi-info-circle"></i>
      Los roles definen que modulos ve cada cuenta. Un usuario <b>bloqueado</b> no puede iniciar sesion.
      El campo «personal vinculado» conecta la cuenta con el registro del Modulo de Personal (Paso 5).
    </div>
  </div>
</div>
<?php endif; ?>