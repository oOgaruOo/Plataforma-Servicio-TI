<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('usuarios', 'gestionar');

    $base = "SELECT u.id, u.usuario, u.nombre_completo, u.correo, u.celular, u.rol_id, u.personal_id,
                    u.estado, u.ultimo_acceso, u.foto, r.nombre AS rol,
                    CONCAT(p.nombres, ' ', p.apellidos) AS personal,
                    p.cargo AS personal_cargo, pa.nombre AS personal_area
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             LEFT  JOIN personal p ON p.id = u.personal_id
             LEFT  JOIN areas pa ON pa.id = p.area_id";

    // la columna 0 de la tabla es la foto (no ordenable): se ordena por usuario
    $alias = ['usuario', 'usuario', 'nombre_completo', 'rol', 'personal', 'celular', 'correo', 'ultimo_acceso', 'estado'];
    $busca = ['u.usuario', 'u.nombre_completo', 'r.nombre', 'p.nombres', 'p.apellidos', 'p.cargo', 'pa.nombre', 'u.correo', 'u.celular'];

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

    $searchExtra = '';
    $paramsBusq  = [];
    if ($busqueda !== '') {
        $partes = [];
        foreach ($busca as $col) {
            $partes[] = "$col LIKE ?";
            $paramsBusq[] = '%' . $busqueda . '%';
        }
        $searchExtra = ' WHERE (' . implode(' OR ', $partes) . ')';
    }
    $baseFull = $base . $searchExtra;

    $paramsCon = $paramsBusq;
    $filtrados = (int) Database::getValue("SELECT COUNT(*) FROM ($baseFull) x", $paramsCon);

    $filas = Database::get(
        "SELECT * FROM ($baseFull) x ORDER BY $colOrd $dir LIMIT $length OFFSET $start",
        $paramsCon
    );

    Response::datatable($draw, $total, $filtrados, $filas);

} catch (Throwable $e) {
    Response::error('usuarios/listar: ' . $e->getMessage()
        . ' | ' . basename($e->getFile()) . ':' . $e->getLine());
}