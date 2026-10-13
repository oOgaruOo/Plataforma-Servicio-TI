<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Rastreo de equipos con IP / MAC
//
// Un equipo con el agente instalado reporta cada pocos minutos (check-in):
// IP publica y local, MAC, nombre del equipo, Wi-Fi y, si el equipo lo permite,
// su posicion (GPS / Wi-Fi de Windows). El servidor deduce DONDE esta con este orden:
//   1. posicion que informa el propio equipo        (fuente "gps")
//   2. sede o red conocida (IP publica / red local)  (fuente "sede")
//   3. geolocalizacion de la IP publica, aproximada  (fuente "ip")
//   Si la posicion del equipo es muy imprecisa (Windows sin GPS la estima por Wi-Fi/IP con margenes de varios km), se trata
//   como aproximada: se usa la sede conocida si existe, y si no se marca fuente "aprox" (nunca como ubicacion exacta).
// Todo se crea solo la primera vez (columnas y tablas), como el resto de ayudantes.
// ============================================================

class Rastreo
{
    /** Minutos sin reportar para seguir considerandolo "en linea". */
    /** Margen maximo (m) para considerar fiable la posicion que informa el equipo. */
    public const PRECISION_UTIL_M = 1500;
    public const EN_LINEA_MIN = 15;
    /** Pasadas estas horas sin reportar se considera "sin senal". */
    public const SIN_SENAL_HORAS = 24;

    private static bool $ok = false;
    private static ?array $sedes = null;

    public static function asegurar(): void
    {
        if (self::$ok) return;

        try {
            Database::run('SELECT id FROM rastreo_sedes LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS rastreo_sedes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                ip_publica VARCHAR(45) NULL,
                prefijo_local VARCHAR(20) NULL,
                proyecto_id INT UNSIGNED NULL,
                direccion VARCHAR(200) NULL,
                latitud DECIMAL(10,7) NULL,
                longitud DECIMAL(10,7) NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sede_ip (ip_publica)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        try {
            Database::run('SELECT ip FROM ip_geocache LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS ip_geocache (
                ip VARCHAR(45) PRIMARY KEY,
                ciudad VARCHAR(100) NULL, region VARCHAR(100) NULL, pais VARCHAR(100) NULL,
                pais_codigo VARCHAR(5) NULL, isp VARCHAR(150) NULL,
                latitud DECIMAL(10,7) NULL, longitud DECIMAL(10,7) NULL,
                consultado TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        Esquema::columna('equipos_rastreo', 'fuente', 'VARCHAR(15) NULL');
        Esquema::columna('equipos_rastreo', 'precision_m', 'INT UNSIGNED NULL');
        Esquema::columna('equipos_rastreo', 'wifi', 'VARCHAR(120) NULL');
        Esquema::columna('equipos_rastreo', 'sede_id', 'INT UNSIGNED NULL');
        Esquema::columna('equipos_rastreo', 'mac_coincide', 'TINYINT NULL');

        Esquema::columna('equipos_rastreo_config', 'modo_perdido', 'TINYINT(1) NOT NULL DEFAULT 0');
        Esquema::columna('equipos_rastreo_config', 'perdido_desde', 'DATETIME NULL');
        Esquema::columna('equipos_rastreo_config', 'perdido_motivo', 'VARCHAR(10) NULL');
        Esquema::columna('equipos_rastreo_config', 'perdido_nota', 'VARCHAR(250) NULL');
        Esquema::columna('equipos_rastreo_config', 'perdido_por', 'INT UNSIGNED NULL');
        Esquema::columna('equipos_rastreo_config', 'ultima_alerta', 'DATETIME NULL');
        self::$ok = true;
    }

    /** "aa-bb-cc-dd-ee-ff" / "AABBCCDDEEFF" -> "AA:BB:CC:DD:EE:FF"; null si no es una MAC valida. */
    public static function normalizarMac(?string $mac): ?string
    {
        $h = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$mac));
        if (strlen($h) !== 12) return null;
        return implode(':', str_split($h, 2));
    }

    /** IP registrada en las especificaciones del equipo (campo "ip_asignada"). */
    public static function ipRegistrada(?string $especificaciones): ?string
    {
        if (!$especificaciones) return null;
        $d = json_decode($especificaciones, true);
        $ip = is_array($d) ? trim((string)($d['ip_asignada'] ?? '')) : '';
        return $ip !== '' ? $ip : null;
    }

    public static function esIpPublica(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) && !Geolocalizacion::esInterna($ip);
    }

    /** Latitud/longitud validas (rango y no 0,0 que es el "valor vacio" de muchos agentes). */
    public static function coordenadas($lat, $lon): ?array
    {
        if (!is_numeric($lat) || !is_numeric($lon)) return null;
        $lat = (float)$lat; $lon = (float)$lon;
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return null;
        if (abs($lat) < 0.0001 && abs($lon) < 0.0001) return null;
        return [round($lat, 7), round($lon, 7)];
    }

    /** Sede o red conocida a la que pertenece una conexion, o null. */
    public static function sedePara(?string $ipPublica, ?string $ipLocal): ?array
    {
        self::asegurar();
        if (self::$sedes === null) {
            self::$sedes = Database::get('SELECT * FROM rastreo_sedes WHERE activo = 1 ORDER BY id');
        }
        foreach (self::$sedes as $s) {
            if ($s['ip_publica'] && $ipPublica && $s['ip_publica'] === $ipPublica) return $s;
        }
        foreach (self::$sedes as $s) {
            $p = (string)$s['prefijo_local'];
            if ($p !== '' && $ipLocal && strpos($ipLocal, $p) === 0) return $s;
        }
        return null;
    }

    /** Fuerza a releer las sedes (despues de editarlas en la misma peticion). */
    public static function olvidarSedes(): void
    {
        self::$sedes = null;
    }

    /**
     * Estado de conexion segun el ultimo check-in.
     * Devuelve ['clave' => en_linea|reciente|sin_senal|sin_agente, 'minutos' => ?int, 'hace' => texto]
     */
    public static function conexion(bool $tieneAgente, ?string $ultimoCheckin, ?int $ahora = null): array
    {
        if (!$tieneAgente) return ['clave' => 'sin_agente', 'minutos' => null, 'hace' => 'Sin agente'];
        if (!$ultimoCheckin) return ['clave' => 'sin_senal', 'minutos' => null, 'hace' => 'Nunca reportó'];
        $t = strtotime($ultimoCheckin);
        if ($t === false) return ['clave' => 'sin_senal', 'minutos' => null, 'hace' => '—'];
        $min = max(0, (int)floor((($ahora ?? time()) - $t) / 60));
        if ($min <= self::EN_LINEA_MIN)              $clave = 'en_linea';
        elseif ($min <= self::SIN_SENAL_HORAS * 60)  $clave = 'reciente';
        else                                         $clave = 'sin_senal';
        return ['clave' => $clave, 'minutos' => $min, 'hace' => self::hace($min)];
    }

    public static function hace(int $min): string
    {
        if ($min < 1)    return 'ahora';
        if ($min < 60)   return "hace $min min";
        if ($min < 1440) return 'hace ' . (int)floor($min / 60) . ' h';
        return 'hace ' . (int)floor($min / 1440) . ' d';
    }
}
