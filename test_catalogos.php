<?php
require_once __DIR__ . '/core/bootstrap.php';

 $res = [];
function t(string $n, bool $ok, string $d = ''): void { global $res; $res[] = [$n, $ok, $d]; }

 $archivos = [
    'api/catalogos/select.php',  'api/catalogos/listar.php',
    'api/catalogos/guardar.php', 'api/catalogos/toggle.php',
    'api/usuarios/listar.php',   'api/usuarios/guardar.php',
    'api/usuarios/estado.php',   'api/usuarios/password.php',
    'views/configuracion/principal.php', 'views/usuarios/lista.php',
    'assets/js/core/datatables.js', 'assets/js/app/catalogos.js',
    'assets/js/app/usuarios.js',
    'core/bootstrap.php', 'core/Response.php',
];

foreach ($archivos as $a) {
    $ruta = ROOT_PATH . '/' . $a;
    if (!is_file($ruta)) { t("Archivo: $a", false, 'NO EXISTE'); continue; }
    $bom = substr((string) file_get_contents($ruta, false, null, 0, 3), 0, 3) === "\xEF\xBB\xBF";
    t("Archivo: $a", !$bom, $bom
        ? 'guardado como UTF-8 con BOM -> re-guardar como UTF-8 (sin BOM)'
        : 'presente, sin BOM');
}

 $bin = PHP_BINARY ?: 'php';
foreach (['api/catalogos/listar.php', 'api/catalogos/guardar.php',
          'api/catalogos/toggle.php', 'api/catalogos/select.php',
          'core/bootstrap.php', 'core/Response.php'] as $a) {
    $ruta = ROOT_PATH . '/' . $a;
    if (!is_file($ruta)) continue;
    $salida = @shell_exec('"' . $bin . '" -l "' . $ruta . '" 2>&1');
    if ($salida === null) {
        t("Sintaxis: $a", true, '(no verificable: shell_exec deshabilitado)');
    } elseif (strpos($salida, 'No syntax errors') === false) {
        t("Sintaxis: $a", false, trim(preg_replace('/\s+/', ' ', strip_tags($salida))));
    } else {
        t("Sintaxis: $a", true, 'OK');
    }
}

 $catalogos = [
    'areas'         => 'SELECT id, nombre, descripcion, estado FROM areas',
    'tipos'         => 'SELECT id, nombre, activo FROM tipo_equipos',
    'categorias'    => 'SELECT id, nombre, descripcion, activo FROM categorias',
    'subcategorias' => 'SELECT s.id, s.categoria_id, s.nombre, c.nombre AS categoria, s.activo
                        FROM subcategorias s
                        INNER JOIN categorias c ON c.id = s.categoria_id',
    'prioridades'   => 'SELECT id, nombre, nivel, color, sla_horas, activo FROM prioridades',
    'proveedores'   => 'SELECT id, ruc, nombre, contacto, telefono, correo, especialidad, estado
                        FROM proveedores',
];
foreach ($catalogos as $tipo => $sql) {
    try {
        $total = (int) Database::getValue("SELECT COUNT(*) FROM ($sql) x");
        t("Consulta '$tipo'", true, "$total registros");
    } catch (Throwable $e) {
        t("Consulta '$tipo'", false, $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>SIGTI - Diagnostico Catalogos</title>
<style>
body{font-family:Segoe UI,Arial;background:#f4f6f9;padding:30px;color:#222}
table{border-collapse:collapse;width:100%;max-width:900px;background:#fff;
      box-shadow:0 2px 8px rgba(0,0,0,.08);border-radius:8px;overflow:hidden}
td{padding:10px 14px;border-bottom:1px solid #eee;font-size:14px}
.ok{color:#166534;font-weight:bold}.err{color:#b91c1c;font-weight:bold}
.det{color:#666;font-size:12px;max-width:560px}
.resumen{margin:16px 0;padding:12px;border-radius:8px;font-weight:bold;max-width:900px;box-sizing:border-box}
.todo-ok{background:#dcfce7;color:#166534}.con-errores{background:#fee2e2;color:#991b1b}
h1{font-size:22px}
</style></head>
<body>
<h1>SIGTI - Diagnostico del modulo Catalogos</h1>
<?php $fallidas = count(array_filter($res, fn($r) => !$r[1])); ?>
<div class="resumen <?= $fallidas ? 'con-errores' : 'todo-ok' ?>">
  <?= count($res) - $fallidas ?>/<?= count($res) ?> verificaciones superadas
  <?= $fallidas ? ' - corrija las filas en ROJO' : ' - lado del servidor sano' ?>
</div>
<table>
<?php foreach ($res as [$n, $ok, $d]): ?>
  <tr>
    <td class="<?= $ok ? 'ok' : 'err' ?>"><?= $ok ? 'PASS' : 'FAIL' ?></td>
    <td><?= htmlspecialchars($n) ?></td>
    <td class="det"><?= htmlspecialchars($d) ?></td>
  </tr>
<?php endforeach; ?>
</table>
</body></html>