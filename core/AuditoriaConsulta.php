<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Auditoría: filtros y etiquetas compartidos por
// listar.php (tabla), exportar.php (CSV) y resumen.php (tarjetas).
// ============================================================

class AuditoriaConsulta
{
    /** Etiquetas legibles de la columna accion. */
    public const ACCIONES = [
        'crear' => 'Creó', 'actualizar' => 'Modificó', 'eliminar' => 'Eliminó',
        'login' => 'Inició sesión', 'logout' => 'Cerró sesión', 'login_fallido' => 'Inicio de sesión fallido',
        'exportar' => 'Exportó', 'aprobar' => 'Aprobó', 'cambiar_estado' => 'Cambió de estado',
    ];

    /**
     * Arma el WHERE segun los filtros f_* recibidos.
     * @return array{0:string,1:array} [' WHERE ...' o '', parametros]
     */
    public static function filtros(array $in): array
    {
        $g = fn(string $k): string => trim((string)($in[$k] ?? ''));
        $where = [];
        $p = [];

        if ((int)($in['f_usuario'] ?? 0) > 0) { $where[] = 'a.usuario_id = ?'; $p[] = (int)$in['f_usuario']; }
        if ($g('f_tabla') !== '')     { $where[] = 'a.tabla = ?';     $p[] = $g('f_tabla'); }
        if ((int)($in['f_registro'] ?? 0) > 0) { $where[] = 'a.registro_id = ?'; $p[] = (int)$in['f_registro']; }
        if ($g('f_accion') !== '')    { $where[] = 'a.accion = ?';    $p[] = $g('f_accion'); }
        if ($g('f_modulo') !== '')    { $where[] = 'a.modulo = ?';    $p[] = $g('f_modulo'); }
        if ($g('f_resultado') !== '') { $where[] = 'a.resultado = ?'; $p[] = $g('f_resultado'); }
        if ($g('f_ubicacion') !== '') { $where[] = 'a.ubicacion LIKE ?'; $p[] = '%' . $g('f_ubicacion') . '%'; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $g('f_desde'))) { $where[] = 'a.created_at >= ?'; $p[] = $g('f_desde') . ' 00:00:00'; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $g('f_hasta'))) { $where[] = 'a.created_at <= ?'; $p[] = $g('f_hasta') . ' 23:59:59'; }
        if ($g('f_seguridad') === '1') {
            $where[] = "(a.accion = 'login_fallido' OR a.resultado IN ('denegado','rechazado') OR a.modulo = 'seguridad')";
        }
        return [$where ? (' WHERE ' . implode(' AND ', $where)) : '', $p];
    }

    /** Columnas donde busca el cuadro de busqueda. */
    public const BUSCA = [
        'a.usuario', 'a.usuario_nombre', 'a.rol_snapshot', 'a.modulo', 'a.submodulo',
        'a.accion_detalle', 'a.accion', 'a.tabla', 'a.resultado', 'a.ip', 'a.ip_real', 'a.ubicacion',
        'a.motivo', 'CAST(a.registro_id AS CHAR)',
    ];

    /** Consulta base (misma para tabla y CSV). */
    public static function base(string $cond): string
    {
        return "SELECT a.id, a.usuario_id, a.usuario, a.usuario_nombre,
                       a.rol_snapshot, a.actor_tipo, a.modulo, a.submodulo,
                       a.accion, a.accion_detalle, a.tabla, a.registro_id,
                       a.equipo_id, a.resultado, a.motivo,
                       a.datos_anteriores, a.datos_nuevos, a.ip, a.ip_real,
                       a.fuente_ip, a.user_agent, a.session_ref,
                       a.ubicacion, a.latitud, a.longitud, a.precision_geo,
                       a.fuente_geolocalizacion, a.created_at,
                       COALESCE(a.usuario_nombre, u.nombre_completo) AS usuario_mostrar
                FROM auditoria_logs a
                LEFT JOIN usuarios u ON u.id = a.usuario_id" . $cond;
    }

    /** Agrega el texto del cuadro de busqueda al WHERE. @return array{0:string,1:array} */
    public static function conBusqueda(string $cond, array $params, string $busqueda): array
    {
        if ($busqueda === '') return [$cond, $params];
        $partes = [];
        foreach (self::BUSCA as $col) {
            $partes[] = "$col LIKE ?";
            $params[] = '%' . $busqueda . '%';
        }
        // tambien por nombre del usuario cuando el log no guardo el nombre
        $partes[] = 'u.nombre_completo LIKE ?';
        $params[] = '%' . $busqueda . '%';
        return [$cond . ($cond ? ' AND (' : ' WHERE (') . implode(' OR ', $partes) . ')', $params];
    }
}
