<?php
// ============================================================
// SIGTI - Activacion de la cuenta admin (EJECUTAR UNA SOLA VEZ)
// URL: http://localhost/Plataforma-Servicio-TI/crear_admin.php
// BORRAR este archivo despues de usarlo.
// ============================================================
require_once __DIR__ . '/core/bootstrap.php';

 $mensaje = '';
 $exito   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass) < 8) {
        $mensaje = 'La contrasena debe tener al menos 8 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $mensaje = 'La contrasena debe combinar letras y numeros.';
    } elseif ($pass !== $pass2) {
        $mensaje = 'Las contrasenas no coinciden.';
    } else {
        try {
            Database::update(
                'usuarios',
                ['password_hash' => password_hash($pass, HASH_ALGO),
                 'estado'        => 'activo',
                 'intentos_fallidos' => 0],
                'usuario = ?', ['admin']
            );
            Auditoria::registrar('actualizar', 'usuarios', null, null, 'Activacion de cuenta admin');
            $exito   = true;
            $mensaje = 'Contrasena del usuario <b>admin</b> establecida. Ya puede iniciar sesion.';
        } catch (Throwable $e) {
            $mensaje = 'Error de base de datos: ' . $e->getMessage()
                     . '<br><small>Ya importo db/sigti.sql en MySQL?</small>';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>SIGTI - Activar administrador</title>
<style>
  body{font-family:Segoe UI,Arial;background:#1e2732;display:flex;align-items:center;
       justify-content:center;min-height:100vh;margin:0;color:#eee}
  .card{background:#fff;color:#333;border-radius:10px;padding:32px;width:380px;box-shadow:0 10px 40px rgba(0,0,0,.4)}
  h1{font-size:20px;margin:0 0 6px;color:#1e2732}
  p.sub{color:#888;font-size:13px;margin:0 0 20px}
  label{display:block;font-size:13px;margin:12px 0 4px;font-weight:600}
  input{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box}
  button{width:100%;margin-top:20px;padding:12px;background:#2563eb;color:#fff;border:0;
         border-radius:6px;font-size:15px;cursor:pointer}
  .msg{padding:10px;border-radius:6px;font-size:14px;margin-top:14px}
  .ok{background:#dcfce7;color:#166534}.err{background:#fee2e2;color:#991b1b}
  .warn{margin-top:16px;font-size:12px;color:#b45309;background:#fef3c7;padding:8px;border-radius:6px}
</style>
</head>
<body>
  <div class="card">
    <h1>SIGTI - Activacion</h1>
    <p class="sub">Define la contrasena del usuario <b>admin</b> (Super Administrador)</p>

    <?php if ($mensaje): ?>
      <div class="msg <?= $exito ? 'ok' : 'err' ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if (!$exito): ?>
      <form method="post">
        <label>Nueva contrasena</label>
        <input type="password" name="password" required minlength="8">
        <label>Repetir contrasena</label>
        <input type="password" name="password2" required minlength="8">
        <button type="submit">Activar cuenta admin</button>
      </form>
    <?php else: ?>
      <div class="warn">Por seguridad, <b>borre este archivo</b> (crear_admin.php) del servidor ahora.</div>
    <?php endif; ?>
  </div>
</body>
</html>