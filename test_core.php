<?php
// ============================================================
// SIGTI - Pruebas automáticas del núcleo (PASO 2)
// URL: http://localhost/sigti/test_core.php
// Correr cuando la base de datos ya esté importada.
// ============================================================
require_once __DIR__ . '/core/bootstrap.php';

 $pruebas = [];
function t(string $nombre, bool $ok, string $detalle = ''): void {
    global $pruebas;
    $pruebas[] = [$nombre, $ok, $detalle];
}

// ---- 1. Conexión a MySQL ----
 $conexionOk = false;
try {
    $version = Database::getValue('SELECT VERSION()');
    $conexionOk = true;
    t('Conexión a MySQL', true, 'Versión del servidor: ' . $version);
} catch (Throwable $e) {
    t('Conexión a MySQL', false, $e->getMessage() . ' — revise config/config.php y que MySQL esté iniciado');
}

if ($conexionOk) {
    // ---- 2. Base de datos y tablas ----
    try {
        $tablas = Database::get("SHOW TABLES FROM " . DB_NAME);
        $n = count($tablas);
        t('Base de datos "sigti" importada', $n >= 27, "$n tablas (se esperan 27)");

        if ($n >= 27) {
            // ---- 3. Seeders ----
            $esperados = [
                'roles' => 7, 'permisos' => 31, 'areas' => 8,
                'categorias' => 8, 'subcategorias' => 33, 'prioridades' => 4,
                'tipo_equipos' => 14, 'checklist_items' => 13, 'configuracion' => 6,
            ];
            foreach ($esperados as $tabla => $espera) {
                $real = (int) Database::getValue("SELECT COUNT(*) FROM $tabla");
                t("Seeder: $tabla", $real === $espera, "$real de $espera");
            }

            // ---- 4. Correlativos ----
            try {
                $c1 = Correlativo::generar('ticket');
                $c2 = Correlativo::generar('ticket');
                t('Correlativos (transacción segura)',
                  str_starts_with($c2, 'TK-') && $c1 !== $c2, "$c1 → $c2 (incrementa bien)");
            } catch (Throwable $e) {
                t('Correlativos', false, $e->getMessage());
            }

            // ---- 5. Encriptación bcrypt ----
            $hash = password_hash('Prueba123', HASH_ALGO);
            t('Encriptación bcrypt (password_verify)',
                password_verify('Prueba123', $hash) && !password_verify('Incorrecta', $hash));

            // ---- 6. Validator ----
            $v = Validator::make(
                ['email' => 'no-es-email', 'dni' => '123'],
                ['email' => 'required|email', 'dni' => 'required|dni']
            );
            t('Validator (reglas email + dni)', $v->fails() && count($v->errors()) === 2,
              'Detectó correctamente 2 errores de prueba');

            // ---- 7. Auditoría ----
            try {
                Auditoria::registrar('exportar', null, null, null, 'prueba desde test_core.php');
                $id = Database::getValue("SELECT id FROM auditoria_logs ORDER BY id DESC LIMIT 1");
                t('Auditoría', (int)$id > 0, "Log de prueba insertado (id $id)");
            } catch (Throwable $e) {
                t('Auditoría', false, $e->getMessage());
            }

            // ---- 8. Usuario admin existe ----
            $admin = Database::getOne("SELECT password_hash, estado FROM usuarios WHERE usuario = 'admin'");
            t('Usuario admin presente',
              $admin !== null,
              $admin['password_hash'] === 'PENDIENTE_ACTIVAR'
                  ? 'Pendiente: ejecute crear_admin.php'
                  : 'Contraseña ya activada');
        }
    } catch (Throwable $e) {
        t('Estructura de BD', false, $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>SIGTI - Test del núcleo (Paso 2)</title>
<style>
  body{font-family:Consolas,monospace;background:#f4f6f9;padding:30px;color:#222}
  h1{font-family:Segoe UI,Arial;font-size:22px}
  table{border-collapse:collapse;width:100%;max-width:760px;background:#fff;
        box-shadow:0 2px 8px rgba(0,0,0,.08);border-radius:8px;overflow:hidden}
  td{padding:10px 14px;border-bottom:1px solid #eee;font-size:14px}
  .ok{color:#166534;font-weight:bold}.err{color:#b91c1c;font-weight:bold}
  .det{color:#777;font-size:12px}
  .resumen{margin:16px 0;padding:12px;border-radius:8px;font-weight:bold;max-width:760px;box-sizing:border-box}
  .todo-ok{background:#dcfce7;color:#166534}.con-errores{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<h1>🧪 SIGTI — Pruebas del núcleo (Paso 2)</h1>
<?php
 $fallidas = count(array_filter($pruebas, fn($p) => !$p[1]));
?>
<div class="resumen <?= $fallidas ? 'con-errores' : 'todo-ok' ?>">
  <?= count($pruebas) - $fallidas ?>/<?= count($pruebas) ?> pruebas superadas
  <?= $fallidas ? ' — ⚠️ corrija lo marcado en rojo antes de continuar' : ' — ✔ núcleo listo para el Paso 3' ?>
</div>
<table>
<?php foreach ($pruebas as [$nombre, $ok, $detalle]): ?>
  <tr>
    <td class="<?= $ok ? 'ok' : 'err' ?>"><?= $ok ? '✔ PASS' : '✖ FAIL' ?></td>
    <td><?= htmlspecialchars($nombre) ?></td>
    <td class="det"><?= htmlspecialchars($detalle) ?></td>
  </tr>
<?php endforeach; ?>
</table>
</body>
</html>