<?php
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();

 $hoy = date('Y-m-d');

Response::ok([
    'activos'   => (int) Database::getValue("SELECT COUNT(*) FROM asignaciones WHERE estado = 'activa' AND tipo = 'permanente'"),
    'prestamos' => (int) Database::getValue("SELECT COUNT(*) FROM asignaciones WHERE estado IN ('activa','vencida') AND tipo = 'prestamo'"),
    'prestamos_vencidos' => (int) Database::getValue(
        "SELECT COUNT(*) FROM asignaciones
         WHERE tipo = 'prestamo' AND estado IN ('activa','vencida')
           AND fecha_devolucion_esperada IS NOT NULL AND fecha_devolucion_esperada < ?", [$hoy]),
    'solicitudes_pendientes' => (int) Database::getValue(
        "SELECT COUNT(*) FROM solicitudes_equipo WHERE estado = 'pendiente'"),
    'solicitudes_aprobadas' => (int) Database::getValue(
        "SELECT COUNT(*) FROM solicitudes_equipo WHERE estado = 'aprobada'"),
    'devueltos_mes' => (int) Database::getValue(
        "SELECT COUNT(*) FROM asignaciones WHERE estado = 'devuelta'
           AND MONTH(fecha_devolucion_real) = MONTH(CURDATE()) AND YEAR(fecha_devolucion_real) = YEAR(CURDATE())"),
]);