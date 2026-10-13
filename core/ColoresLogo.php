<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Colores del logo de la empresa (por defecto, la paleta neutra de SIGTI)
// v2: análisis optimizado para logos geométricos bicolor
// ============================================================

class ColoresLogo
{
    private const BASE_AZUL     = '#1B4F9C';   // azul base
    private const BASE_AZUL_OSC = '#0D2E5C';   // azul profundo para degradado
    private const BASE_ACENTO = '#F5C518';   // acento

    public static function obtener(): array
    {
        $logoPath = null;
        foreach (['png', 'jpg', 'jpeg'] as $ext) {
            $f = STORAGE_PATH . '/branding/logo.' . $ext;
            if (is_file($f)) { $logoPath = $f; break; }
        }

        if (!$logoPath) {
            // sin logo: identidad neutra por defecto
            return self::base();
        }

        // cache: si ya extrajimos y el logo no cambió
        $cacheFile = STORAGE_PATH . '/branding/.colores_logo.json';
        if (is_file($cacheFile) && filemtime($cacheFile) > filemtime($logoPath)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if ($cached && isset($cached['primario'])) return $cached;
        }

        // ---- analizar ----
        $colores = self::analizar($logoPath);
        @file_put_contents($cacheFile, json_encode($colores));
        return $colores;
    }

    private static function base(): array
    {
        return [
            'primario'    => self::BASE_AZUL_OSC,    // fondo del carnet
            'primario_claro' => self::BASE_AZUL,     // degradado inferior
            'secundario'  => self::BASE_ACENTO,     // franja superior
            'acento'      => self::BASE_ACENTO,     // badges, bordes
            'texto_claro' => true,                     // texto blanco sobre azul
            'texto_acento' => '#1e293b',               // texto oscuro sobre amarillo
        ];
    }

    private static function analizar(string $ruta): array
    {
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $img = $ext === 'png' ? @imagecreatefrompng($ruta)
              : @imagecreatefromjpeg($ruta);
        if (!$img) return self::base();

        $w = imagesx($img);
        $h = imagesy($img);

        // muestrear TODO el logo (no aleatorio: grid sistemático)
        $azul = ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
        $amarillo = ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
        $otros = ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];

        $step = max(1, (int)(sqrt($w * $h) / 100));
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $lum = ($r + $g + $b) / 3;
                // saltar blanco y negro puro (fondos y texto del logo)
                if ($lum > 235 || $lum < 30) continue;

                // clasificar: ¿azul? ¿amarillo? ¿otro?
                if ($b > $r + 30 && $b > 60 && $g < $b + 30) {
                    // azul: b dominante
                    $azul['count']++; $azul['r'] += $r; $azul['g'] += $g; $azul['b'] += $b;
                } elseif ($r > 150 && $g > 120 && $b < 100 && $r > $b + 50) {
                    // amarillo/naranja: r+g altos, b bajo
                    $amarillo['count']++; $amarillo['r'] += $r; $amarillo['g'] += $g; $amarillo['b'] += $b;
                } else {
                    $otros['count']++; $otros['r'] += $r; $otros['g'] += $g; $otros['b'] += $b;
                }
            }
        }

        imagedestroy($img);

        // determinar primario y secundario
        $primario = null;
        $secundario = null;

        if ($azul['count'] > 0) {
            $primario = sprintf('#%02x%02x%02x',
                (int)($azul['r'] / $azul['count']),
                (int)($azul['g'] / $azul['count']),
                (int)($azul['b'] / $azul['count']));
        }
        if ($amarillo['count'] > 0) {
            $secundario = sprintf('#%02x%02x%02x',
                (int)($amarillo['r'] / $amarillo['count']),
                (int)($amarillo['g'] / $amarillo['count']),
                (int)($amarillo['b'] / $amarillo['count']));
        }

        // fallback: si no encontramos azul/amarillo, usar los más frecuentes
        if (!$primario) {
            if ($otros['count'] > 0) {
                $primario = sprintf('#%02x%02x%02x',
                    (int)($otros['r'] / $otros['count']),
                    (int)($otros['g'] / $otros['count']),
                    (int)($otros['b'] / $otros['count']));
            } else {
                return self::base();   // nada encontré: paleta base
            }
        }
        if (!$secundario) {
            $secundario = self::BASE_ACENTO;   // default amarillo
        }

        // generar versión clara del primario para el degradado
        $primarioClaro = self::aclarar($primario, 0.25);

        // el texto sobre el primario: blanco si oscuro
        $textoClaro = self::esOscuro($primario);

        // el texto sobre el secundario (amarillo): siempre oscuro
        $textoAcento = '#1e293b';

        return [
            'primario'       => $primario,
            'primario_claro' => $primarioClaro,
            'secundario'     => $secundario,
            'acento'         => $secundario,   // amarillo como acento
            'texto_claro'    => $textoClaro,
            'texto_acento'   => $textoAcento,
        ];
    }

    private static function esOscuro(string $hex): bool
    {
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        return ($r * 0.299 + $g * 0.587 + $b * 0.114) / 255 < 0.5;
    }

    private static function aclarar(string $hex, float $factor): string
    {
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        $r = (int)min(255, $r + (255 - $r) * $factor);
        $g = (int)min(255, $g + (255 - $g) * $factor);
        $b = (int)min(255, $b + (255 - $b) * $factor);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}