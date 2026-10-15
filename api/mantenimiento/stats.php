<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Auth::requirePermission('mantenimiento', 'ver');
Programados::asegurar();

 $porEstado = Database::get(
    "SELECT estado, COUNT(*) AS n FROM mantenimientos GROUP BY estado ORDER BY n DESC");
 $porTipo = Database::get(
    "SELECT tipo, COUNT(*) AS n FROM mantenimientos GROUP BY tipo ORDER BY n DESC");

 $meses = []; $costos = []; $cantidades = [];
 for ($i = 5; $i >= 0; $i--) {
    $clave = date('Y-m', strtotime("-$i months"));
    $meses[] = $clave;
    $costos[$clave] = 0; $cantidades[$clave] = 0;
}
 $filas = Database::get(
    "SELECT DATE_FORMAT(fecha_inicio,'%Y-%m') AS mes,
            SUM(costo_total) AS costo, COUNT(*) AS n
     FROM mantenimientos
     WHERE fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
       AND estado NOT IN ('cancelado')
     GROUP BY mes");
 foreach ($filas as $f) {
    if (isset($costos[$f['mes']])) { $costos[$f['mes']] = (float)$f['costo']; $cantidades[$f['mes']] = (int)$f['n']; }
}

 $proximos = Database::get(
    "SELECT mp.proxima_fecha, mp.frecuencia, mp.tipo,
            e.codigo, e.marca,
            CONCAT(u.nombre_completo, '') AS responsable
     FROM mantenimientos_programados mp
     INNER JOIN equipos e ON e.id = mp.equipo_id
     LEFT JOIN usuarios u ON u.id = mp.responsable_id
     WHERE mp.activo = 1 AND mp.mantenimiento_actual_id IS NULL
       AND mp.proxima_fecha <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY mp.proxima_fecha ASC LIMIT 10");

 $slaVencidos = Database::get(
    "SELECT m.codigo, m.estado, m.sla_limite, e.codigo AS equipo
     FROM mantenimientos m
     INNER JOIN equipos e ON e.id = m.equipo_id
     WHERE m.sla_limite IS NOT NULL
       AND m.sla_limite < NOW()
       AND m.estado NOT IN ('cerrado','cancelado','entregado','devuelto_stock','no_reparable')
     ORDER BY m.sla_limite ASC LIMIT 10");

// ---- tiempo promedio de atencion (dias, fecha_inicio -> fecha_fin), general y por tipo ----
 $tiempoGeneral = (float) (Database::getValue(
    "SELECT AVG(DATEDIFF(fecha_fin, fecha_inicio)) FROM mantenimientos
     WHERE fecha_fin IS NOT NULL AND estado NOT IN ('cancelado')") ?? 0);
 $tiempoPorTipo = Database::get(
    "SELECT tipo, ROUND(AVG(DATEDIFF(fecha_fin, fecha_inicio)),1) AS dias, COUNT(*) AS n
     FROM mantenimientos
     WHERE fecha_fin IS NOT NULL AND estado NOT IN ('cancelado')
     GROUP BY tipo ORDER BY dias DESC");

// ---- top proveedores (cantidad atendida, costo y dias promedio) ----
 $porProveedor = Database::get(
    "SELECT pr.nombre, COUNT(*) AS n,
            ROUND(AVG(m.costo_total),2) AS costo_prom,
            ROUND(AVG(DATEDIFF(m.fecha_fin, m.fecha_inicio)),1) AS dias_prom
     FROM mantenimientos m
     INNER JOIN proveedores pr ON pr.id = m.proveedor_id
     WHERE m.proveedor_id IS NOT NULL
     GROUP BY pr.id ORDER BY n DESC LIMIT 8");

// ---- top tecnicos internos (cantidad atendida y dias promedio) ----
 $porTecnico = Database::get(
    "SELECT CONCAT(u.nombre_completo,'') AS tecnico, COUNT(*) AS n,
            ROUND(AVG(DATEDIFF(m.fecha_fin, m.fecha_inicio)),1) AS dias_prom
     FROM mantenimientos m
     INNER JOIN usuarios u ON u.id = m.tecnico_usuario_id
     WHERE m.tecnico_usuario_id IS NOT NULL
     GROUP BY u.id ORDER BY n DESC LIMIT 8");

// ---- equipos con mas mantenimientos en los ultimos 12 meses (posibles fallas recurrentes) ----
 $topEquipos = Database::get(
    "SELECT e.codigo, e.marca, e.modelo, t.nombre AS tipo, COUNT(*) AS n
     FROM mantenimientos m
     INNER JOIN equipos e ON e.id = m.equipo_id
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE m.fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
       AND m.estado NOT IN ('cancelado')
     GROUP BY e.id HAVING n >= 2
     ORDER BY n DESC LIMIT 8");

Response::ok([
    'activos'     => (int) Database::getValue(
        "SELECT COUNT(*) FROM mantenimientos
         WHERE estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')"),
    'con_proveedor' => (int) Database::getValue(
        "SELECT COUNT(*) FROM mantenimientos
         WHERE estado IN ('enviado_proveedor','cotizado','aprobado','en_reparacion')"),
    'atrasados'   => (int) Database::getValue(
        "SELECT COUNT(*) FROM mantenimientos
         WHERE fecha_retorno_estimada IS NOT NULL AND fecha_retorno_estimada < CURDATE()
           AND estado IN ('enviado_proveedor','cotizado','aprobado','en_reparacion')"),
    // trabajo terminado pero sin cerrar (Listo / Entregado / Devuelto a stock)
    'listos'      => (int) Database::getValue(
        "SELECT COUNT(*) FROM mantenimientos WHERE estado IN ('listo','entregado','devuelto_stock')"),
    'no_reparables' => (int) Database::getValue(
        "SELECT COUNT(*) FROM mantenimientos WHERE estado = 'no_reparable'"),
    'costo_mes'   => (float) (Database::getValue(
        "SELECT COALESCE(SUM(costo_total),0) FROM mantenimientos
         WHERE MONTH(fecha_inicio) = MONTH(CURDATE()) AND YEAR(fecha_inicio) = YEAR(CURDATE())
           AND estado NOT IN ('cancelado')") ?? 0),
    'programados_prox' => (int) Database::getValue(
        "SELECT COUNT(*) FROM mantenimientos_programados
         WHERE activo = 1 AND mantenimiento_actual_id IS NULL
           AND proxima_fecha <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
    // ---- datos del panel ----
    'por_estado'  => $porEstado,
    'por_tipo'    => $porTipo,
    'meses'       => $meses,
    'costos_mes'  => array_values($costos),
    'cantidades_mes' => array_values($cantidades),
    'proximos'    => $proximos,
    'sla_vencidos' => $slaVencidos,
    'costo_anio'  => (float) (Database::getValue(
        "SELECT COALESCE(SUM(costo_total),0) FROM mantenimientos
         WHERE YEAR(fecha_inicio) = YEAR(CURDATE()) AND estado NOT IN ('cancelado')") ?? 0),
    // ---- metricas adicionales del panel ----
    'tiempo_prom_general' => round($tiempoGeneral, 1),
    'tiempo_prom_tipo'    => $tiempoPorTipo,
    'por_proveedor'       => $porProveedor,
    'por_tecnico'         => $porTecnico,
    'top_equipos'         => $topEquipos,
]);
