<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('usuarios', 'gestionar');

 $base = "SELECT u.id, u.usuario, u.nombre_completo, u.correo, u.rol_id, u.personal_id,
                u.estado, u.ultimo_acceso, r.nombre AS rol,
                CONCAT(p.nombres, ' ', p.apellidos) AS personal
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         LEFT  JOIN personal p ON p.id = u.personal_id";

 $alias = ['usuario', 'nombre_completo', 'rol', 'personal', 'correo', 'ultimo_acceso', 'estado'];
 $busca = ['usuario', 'nombre_completo', 'rol', 'personal', 'correo'];

 $draw   = (int)($_POST['draw'] ?? 0);
 $start  = max(0, (int)($_POST['start'] ?? 0));
 $length = (int)($_POST['length'] ?? 10);
if ($length < 1)   $length = 10;
if ($length > 200) $length = 200;

 $busqueda = trim((string)($_POST['search']['value'] ?? ''));

 $colIdx = (int)($_POST['order'][0]['column'] ?? 0);
 $dir    = (strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC';
 $colOrd = $alias[$colIdx] ?? $alias[0];

 $total = (int) Database::getValue("SELECT COUNT(*) FROM ($base) x");

 $cond   = '';
 $params = [];
if ($busqueda !== '') {
    $partes = [];
    foreach ($busca as $col) {
        $partes[] = "$col LIKE ?";
        $params[] = '%' . $busqueda . '%';
    }
    $cond = ' WHERE (' . implode(' OR ', $partes) . ')';
}

 $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($base) x$cond", $params);
 $filas = Database::get(
    "SELECT * FROM ($base) x$cond ORDER BY $colOrd $dir LIMIT $length OFFSET $start",
    $params
);

Response::datatable($draw, $total, $filtrados, $filas);