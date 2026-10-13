<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Registro transversal de auditoría
// ============================================================

class Auditoria
{
    /**
     * Registra una operación sin propagar detalles internos al usuario.
     * Los cinco primeros parámetros conservan la firma usada por los módulos.
     * El contexto adicional es opcional para mantener compatibilidad.
     */
    public static function registrar(
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        $datosAnteriores = null,
        $datosNuevos = null,
        array $contexto = []
    ): bool {
        try {
            $actor = Auth::info();
            $ipObservada = self::ipValida($_SERVER['REMOTE_ADDR'] ?? null);
            $hayHeadersProxy = self::hayHeadersDeProxy();

            // Sin una lista de proxies confiables, los headers no se consideran
            // fuente autorizada de la IP cliente. REMOTE_ADDR es el peer observado.
            $ipReal = $hayHeadersProxy ? null : $ipObservada;
            $fuenteIp = $hayHeadersProxy ? 'remote_addr_proxy' : 'remote_addr';

            $ubicacion = null;
            $latitud = null;
            $longitud = null;
            $fuenteGeo = null;
            $geoPrecision = null;
            $accionesGeo = ['login', 'logout', 'login_fallido'];

            // Solo geolocalizar IP cuando la dirección no depende de headers no
            // confiables. La geolocalización IP es estimada y nunca exacta.
            if (in_array($accion, $accionesGeo, true) && $ipReal !== null
                && !Geolocalizacion::esInterna($ipReal)) {
                $ubicacion = Geolocalizacion::ubicacion($ipReal);
                $geo = Geolocalizacion::datosCompletos($ipReal);
                if ($geo) {
                    $latitud = $geo['latitud'] ?? null;
                    $longitud = $geo['longitud'] ?? null;
                    $fuenteGeo = 'ip_aproximada';
                }
            } elseif (in_array($accion, $accionesGeo, true) && $ipReal !== null) {
                $ubicacion = 'Red interna';
                $fuenteGeo = 'ip_red_interna';
            }

            $resultado = $contexto['resultado'] ?? self::resultadoPorDefecto($accion);
            $sesionRef = null;
            if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
                $sesionRef = hash('sha256', session_id());
            }

            Database::insert('auditoria_logs', [
                'usuario_id' => Auth::userId(),
                'usuario' => $contexto['usuario'] ?? ($actor['usuario'] ?? null),
                'usuario_nombre' => $contexto['usuario_nombre'] ?? ($actor['nombre'] ?? null),
                'rol_snapshot' => $contexto['rol'] ?? ($actor['rol'] ?? null),
                'actor_tipo' => $contexto['actor_tipo'] ?? (Auth::userId() ? 'usuario' : 'sistema'),
                'modulo' => $contexto['modulo'] ?? self::moduloDeTabla($tabla),
                'submodulo' => $contexto['submodulo'] ?? null,
                'accion' => $accion,
                'accion_detalle' => $contexto['accion_detalle'] ?? $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'equipo_id' => isset($contexto['equipo_id']) ? (int)$contexto['equipo_id'] : null,
                'resultado' => $resultado,
                'motivo' => isset($contexto['motivo'])
                    ? mb_substr((string)$contexto['motivo'], 0, 500) : null,
                'datos_anteriores' => self::aTexto($datosAnteriores),
                'datos_nuevos' => self::aTexto($datosNuevos),
                'ip' => $ipObservada,
                'ip_real' => $ipReal,
                'fuente_ip' => $fuenteIp,
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                'session_ref' => $sesionRef,
                'precision_geo' => self::precisionValida($contexto['precision_geo'] ?? $geoPrecision),
                'fuente_geolocalizacion' => self::fuenteGeoValida(
                    $contexto['fuente_geolocalizacion'] ?? $fuenteGeo
                ),
                'ubicacion' => $contexto['ubicacion'] ?? $ubicacion,
                'latitud' => self::coordenadaValida($contexto['latitud'] ?? $latitud, -90, 90),
                'longitud' => self::coordenadaValida($contexto['longitud'] ?? $longitud, -180, 180),
            ]);

            return true;
        } catch (Throwable $e) {
            $sqlState = $e instanceof PDOException
                ? (string)($e->errorInfo[0] ?? $e->getCode())
                : (string)$e->getCode();
            error_log('[SIGTI][AUDITORIA] Persistencia fallida; accion='
                . self::logSeguro($accion) . '; tabla=' . self::logSeguro($tabla ?? '')
                . '; registro_id=' . ($registroId ?? 'null') . '; codigo=' . self::logSeguro($sqlState));
            return false;
        }
    }

    public static function registrarDenegacion(string $modulo, string $permiso): bool
    {
        return self::registrar('actualizar', 'permisos', null, null, null, [
            'modulo' => mb_substr($modulo, 0, 40),
            'submodulo' => mb_substr($permiso, 0, 60),
            'accion_detalle' => 'permiso_denegado',
            'resultado' => 'denegado',
            'motivo' => 'Permiso no concedido',
        ]);
    }

    private static function ipValida($ip): ?string
    {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) return null;
        return $ip;
    }

    private static function hayHeadersDeProxy(): bool
    {
        foreach ([
            'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP',
            'HTTP_X_CLIENT_IP', 'HTTP_CLIENT_IP',
        ] as $header) {
            if (!empty($_SERVER[$header])) return true;
        }
        return false;
    }

    private static function resultadoPorDefecto(string $accion): string
    {
        return $accion === 'login_fallido' ? 'rechazado' : 'exito';
    }

    private static function moduloDeTabla(?string $tabla): ?string
    {
        if ($tabla === null || $tabla === '') return null;
        $nombre = strtolower($tabla);

        if (in_array($nombre, ['tickets', 'ticket_historial', 'ticket_comentarios', 'ticket_adjuntos', 'encuestas'], true)
            || strpos($nombre, 'ticket_') === 0) return 'tickets';
        if (in_array($nombre, ['software_catalogo', 'software_licencias', 'equipo_software'], true)) return 'software';
        if (in_array($nombre, ['equipos', 'equipo_historial', 'equipo_ubicaciones', 'equipo_accesorios', 'equipo_licencias', 'equipos_rastreo', 'equipos_rastreo_config', 'rastreo_sedes', 'ip_geocache', 'bajas_equipos'], true)
            || strpos($nombre, 'equipo_') === 0) return 'equipos';
        if (in_array($nombre, ['asignaciones', 'solicitudes_equipo'], true)) return 'asignaciones';
        if ($nombre === 'actas' || strpos($nombre, 'acta') === 0) return 'actas';
        if ($nombre === 'mantenimientos' || strpos($nombre, 'mantenimiento_') === 0) return 'mantenimiento';
        if ($nombre === 'personal' || strpos($nombre, 'personal_') === 0) return 'personal';
        if (in_array($nombre, ['usuarios', 'roles', 'permisos', 'rol_permisos', 'usuario_permisos'], true)) return 'seguridad';
        if ($nombre === 'configuracion' || $nombre === 'proyectos') return 'configuracion';
        if ($nombre === 'anuncios') return 'anuncios';
        if (in_array($nombre, ['chat_mensajes', 'chat_salas', 'chat_reuniones'], true)) return 'chat';
        if ($nombre === 'carnets') return 'carnets';
        if (in_array($nombre, ['capacitaciones', 'capacitacion_temas', 'capacitacion_historial'], true)) return 'capacitaciones';
        return null;
    }

    private static function logSeguro(string $valor): string
    {
        return preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $valor);
    }

    private static function aTexto($datos): ?string
    {
        if ($datos === null) return null;
        if (is_array($datos)) {
            $datos = self::redactar($datos);
            $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            return $json === false ? null : $json;
        }
        return (string)$datos;
    }

    private static function redactar(array $datos): array
    {
        foreach ($datos as $clave => $valor) {
            if (preg_match('/password|passwd|token|secret|clave|credential|authorization|csrf/i', (string)$clave)) {
                $datos[$clave] = '[REDACTADO]';
            } elseif (is_array($valor)) {
                $datos[$clave] = self::redactar($valor);
            }
        }
        return $datos;
    }

    private static function coordenadaValida($valor, float $minimo, float $maximo): ?float
    {
        if (!is_numeric($valor)) return null;
        $numero = (float)$valor;
        return is_finite($numero) && $numero >= $minimo && $numero <= $maximo ? $numero : null;
    }

    private static function precisionValida($valor): ?float
    {
        if (!is_numeric($valor)) return null;
        $numero = (float)$valor;
        return is_finite($numero) && $numero >= 0 ? $numero : null;
    }

    private static function fuenteGeoValida($fuente): ?string
    {
        $permitidas = ['ip_aproximada', 'ip_red_interna', 'navegador', 'agente'];
        return is_string($fuente) && in_array($fuente, $permitidas, true) ? $fuente : null;
    }
}
