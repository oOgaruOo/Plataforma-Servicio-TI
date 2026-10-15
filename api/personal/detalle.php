<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Ficha completa (ampliada: equipos, cuentas, checklist cese)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('personal', 'ver');
Esquema::columna('personal', 'fecha_nacimiento', 'DATE NULL');
Esquema::columna('personal', 'foto', 'VARCHAR(80) NULL');
Apellidos::asegurar();
CodigoUsuario::asegurar();

 $id = (int)($_GET['id'] ?? 0);
if ($id === 0) Response::error('Persona no especificada.');

 $p = Database::getOne(
    "SELECT p.*, a.nombre AS area,
            CONCAT(j.nombres, ' ', j.apellidos) AS jefe,
            (SELECT COUNT(*) FROM usuarios u WHERE u.personal_id = p.id AND u.estado <> 'inactivo') AS cuentas_sistema
     FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id
     LEFT JOIN personal j ON j.id = p.jefe_id
     WHERE p.id = ?", [$id]);
if (!$p) Response::error('La persona no existe.');

 $p['equipos'] = Database::get(
    "SELECT asig.id AS asig_id, asig.tipo, asig.estado AS asig_estado,
            asig.fecha_entrega, asig.fecha_devolucion_esperada,
            eq.codigo, eq.marca, eq.modelo, eq.nro_serie,
            t.nombre AS tipo_eq
     FROM asignaciones asig
     INNER JOIN equipos eq ON eq.id = asig.equipo_id
     INNER JOIN tipo_equipos t ON t.id = eq.tipo_equipo_id
     WHERE asig.personal_id = ? AND asig.estado IN ('activa','vencida')
     ORDER BY t.nombre", [$id]);

 $p['cuentas'] = Database::get(
    "SELECT u.id, u.usuario, u.estado, u.ultimo_acceso, u.foto, r.nombre AS rol
     FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE u.personal_id = ?", [$id]);

 $p['checklist'] = Database::get(
    "SELECT pc.id, pc.completado, pc.fecha_completado, pc.observacion,
            ci.item, ci.responsable AS responsable_definido, ci.obligatorio,
            CONCAT(u.nombre_completo, '') AS responsable_nombre
     FROM personal_checklist pc
     INNER JOIN checklist_items ci ON ci.id = pc.item_id
     LEFT JOIN usuarios u ON u.id = pc.responsable_usuario_id
     WHERE pc.personal_id = ?
     ORDER BY ci.plantilla_id, ci.orden", [$id]);

// cumpleanos: edad, proximo cumpleanos y dias que faltan
 $p['edad'] = $p['proximo_cumple'] = $p['dias_cumple'] = null;
if (!empty($p['fecha_nacimiento']) && Cumpleanos::fecha($p['fecha_nacimiento'])) {
    $p['edad']           = Cumpleanos::edad($p['fecha_nacimiento']);
    $p['proximo_cumple'] = Cumpleanos::proximo($p['fecha_nacimiento'])->format('Y-m-d');
    $p['dias_cumple']    = Cumpleanos::dias($p['fecha_nacimiento']);
}

 $p['check_ok']    = count(array_filter($p['checklist'], fn($c) => (int)$c['completado'] === 1));
 $p['check_total'] = count($p['checklist']);

// separar checklist de ingreso vs cese
 $p['checklist_ingreso'] = array_values(array_filter($p['checklist'], fn($c) =>
    mb_stripos($c['item'], 'bloquear') === false && mb_stripos($c['item'], 'desactivar') === false &&
    mb_stripos($c['item'], 'revocar') === false && mb_stripos($c['item'], 'recuperar') === false &&
    mb_stripos($c['item'], 'retiro') === false && mb_stripos($c['item'], 'cierre') === false));
 $p['checklist_cese'] = array_values(array_filter($p['checklist'], fn($c) =>
    mb_stripos($c['item'], 'bloquear') !== false || mb_stripos($c['item'], 'desactivar') !== false ||
    mb_stripos($c['item'], 'revocar') !== false || mb_stripos($c['item'], 'recuperar') !== false ||
    mb_stripos($c['item'], 'retiro') !== false || mb_stripos($c['item'], 'cierre') !== false));

Response::ok($p);