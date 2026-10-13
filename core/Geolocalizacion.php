<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Deteccion de IP real + Geolocalizacion
// - Detecta IP real (proxy/CDN/local)
// - Determina si es interna o externa
// - Geolocaliza IPs externas via ip-api.com (gratis)
// - Cachea resultados para no consultar repetido
// ============================================================

class Geolocalizacion
{
    /**
     * Devuelve el peer TCP observado por PHP. Los headers de proxy no se
     * consideran confiables mientras no exista una lista de proxies autorizados.
     */
    public static function ipReal(): string
    {
        $peer = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($peer, FILTER_VALIDATE_IP) ? $peer : '0.0.0.0';
    }

    /**
     * ¿Es una IP de red interna? (no se geolocaliza)
     */
    public static function esInterna(string $ip): bool
    {
        if ($ip === '' || $ip === '0.0.0.0' || $ip === '::1') return true;
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return true;
        // privadas (10/8, 172.16/12, 192.168/16, fc00::/7), reservadas, loopback y link-local (169.254, fe80::)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return true;
        // CGNAT del operador (100.64.0.0/10): no se puede geolocalizar
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $n = ip2long($ip);
            if ($n !== false && $n >= ip2long('100.64.0.0') && $n <= ip2long('100.127.255.255')) return true;
        }
        if (strpos(strtolower($ip), 'fe80:') === 0) return true;
        return false;
    }

    /**
     * Geolocaliza una IP (solo externas). Devuelve string legible:
     * "Lima, Perú (Telefonica)" o "Red Interna"
     * Usa cache para no consultar repetido.
     */
    public static function ubicacion(string $ip): string
    {
        if (self::esInterna($ip)) {
            return 'Red Interna';
        }
        $d = self::datosCompletos($ip);
        return $d ? self::formatear($d) : 'No geolocalizada';
    }

    /**
     * Formatea los datos de geo en string legible.
     * Acepta tanto la forma de la cache (ciudad/region/pais) como la de ip-api (city/regionName/country).
     */
    private static function formatear(array $d): string
    {
        $ciudad = $d['ciudad'] ?? $d['city'] ?? '';
        $region = $d['region'] ?? $d['regionName'] ?? '';
        $pais   = $d['pais']   ?? $d['country']    ?? '';
        $partes = [];
        if ($ciudad !== '') $partes[] = $ciudad;
        if ($region !== '' && $region !== $ciudad) $partes[] = $region;
        if ($pais !== '') $partes[] = $pais;
        $ubicacion = implode(', ', $partes);
        if (!empty($d['isp'])) {
            $ubicacion .= ($ubicacion !== '' ? ' ' : '') . '(' . $d['isp'] . ')';
        }
        return $ubicacion ?: 'No geolocalizada';
    }

    /**
     * Datos de geo (con latitud/longitud) de una IP publica: ciudad, region, pais, isp, latitud, longitud.
     * Primero la cache (ip_geocache); si no esta, consulta ip-api.com y la guarda.
     * Antes solo leia la cache y nunca la llenaba, asi que el rastreo jamas obtenia la ubicacion.
     */
    public static function datosCompletos(string $ip): ?array
    {
        if (self::esInterna($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) return null;

        try {
            $cache = Database::getOne(
                'SELECT ciudad, region, pais, isp, latitud, longitud FROM ip_geocache WHERE ip = ?', [$ip]);
            if ($cache) return $cache;
        } catch (Throwable $e) {
            // la tabla cache puede no existir aun
        }

        try {
            $contexto = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,countryCode,regionName,city,isp,lat,lon';
            $raw = @file_get_contents($url, false, $contexto);
            if ($raw === false) return null;
            $d = json_decode($raw, true);
            if (!is_array($d) || ($d['status'] ?? '') !== 'success') return null;

            $fila = [
                'ciudad'  => $d['city'] ?? null,
                'region'  => $d['regionName'] ?? null,
                'pais'    => $d['country'] ?? null,
                'isp'     => $d['isp'] ?? null,
                'latitud' => $d['lat'] ?? null,
                'longitud'=> $d['lon'] ?? null,
            ];
            try {
                Database::insert('ip_geocache', array_merge(['ip' => $ip, 'pais_codigo' => $d['countryCode'] ?? null], $fila));
            } catch (Throwable $e) {
                // si la cache falla no se rompe nada
            }
            return $fila;
        } catch (Throwable $e) {
            return null;
        }
    }
}
