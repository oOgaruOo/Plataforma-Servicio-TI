<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Upload multi-formato: PNG, JPG, JPEG, WEBP, GIF, BMP
// Detecta el formato real por bytes mágicos (no por extensión)
// ============================================================

class Upload
{
    // formatos soportados con su función GD
    private const FORMATOS = [
        'png'  => ['mime' => 'image/png',  'gd' => 'imagecreatefrompng'],
        'jpg'  => ['mime' => 'image/jpeg', 'gd' => 'imagecreatefromjpeg'],
        'jpeg' => ['mime' => 'image/jpeg', 'gd' => 'imagecreatefromjpeg'],
        'webp' => ['mime' => 'image/webp', 'gd' => 'imagecreatefromwebp'],
        'gif'  => ['mime' => 'image/gif',  'gd' => 'imagecreatefromgif'],
        'bmp'  => ['mime' => 'image/bmp',  'gd' => 'imagecreatefrombmp'],
    ];

    public static function guardar(array $file, string $subcarpeta): array
    {
        $codigo = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($codigo !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir archivo (código ' . $codigo . ').');
        }

        $maxMb = (int)(self::config('max_upload_mb') ?: 10);
        if ($file['size'] > $maxMb * 1024 * 1024) {
            throw new RuntimeException("El archivo supera el máximo de {$maxMb} MB.");
        }

        // ---- detectar formato REAL por bytes mágicos ----
        $ext = self::detectarFormato($file['tmp_name']);

        if (!$ext) {
            throw new RuntimeException('Formato de imagen no soportado. Use PNG, JPG, WEBP, GIF o BMP.');
        }

        // ---- mover a storage con nombre único ----
        $dir = STORAGE_PATH . '/' . $subcarpeta;
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $nombre = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destino = $dir . '/' . $nombre;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        // ---- si es WEBP/GIF/BMP y GD lo soporta: convertir a PNG para compatibilidad ----
        if (in_array($ext, ['webp', 'gif', 'bmp'])) {
            $convertido = self::convertirAPng($destino, $ext);
            if ($convertido) {
                @unlink($destino);
                $nombre = str_replace('.' . $ext, '.png', $nombre);
                $destino = $dir . '/' . $nombre;
            }
        }

        return [
            'archivo'         => $nombre,
            'nombre_original' => mb_substr(basename($file['name']), 0, 255),
            'tamano_kb'       => (int) round($file['size'] / 1024),
            'formato'         => $ext,
        ];
    }

    // ---- detectar formato por contenido ----
    private static function detectarFormato(string $ruta): ?string
    {
        if (!is_readable($ruta)) return null;
        $magic = (string) file_get_contents($ruta, false, null, 0, 12);

        // PNG: \x89PNG
        if (substr($magic, 0, 4) === "\x89PNG") return 'png';
        // JPEG: \xFF\xD8\xFF
        if (substr($magic, 0, 3) === "\xFF\xD8\xFF") return 'jpg';
        // GIF: GIF8
        if (substr($magic, 0, 3) === 'GIF') return 'gif';
        // WEBP: RIFF....WEBP
        if (substr($magic, 0, 4) === 'RIFF' && substr($magic, 8, 4) === 'WEBP') return 'webp';
        // BMP: BM
        if (substr($magic, 0, 2) === 'BM') return 'bmp';

        return null;
    }

    // ---- convertir a PNG si GD soporta ----
    private static function convertirAPng(string $ruta, string $ext): bool
    {
        try {
            $fn = self::FORMATOS[$ext]['gd'] ?? null;
            if (!$fn || !function_exists($fn)) return false;

            $img = @$fn($ruta);
            if (!$img) return false;

            $ok = @imagepng($img, str_replace('.' . $ext, '_tmp.png', $ruta));
            imagedestroy($img);
            if (!$ok) return false;

            @rename(str_replace('.' . $ext, '_tmp.png', $ruta), $ruta);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function config(string $clave): ?string
    {
        static $cache = [];
        if (!isset($cache[$clave])) {
            $row = Database::getOne("SELECT valor FROM configuracion WHERE clave = ?", [$clave]);
            $cache[$clave] = $row['valor'] ?? null;
        }
        return $cache[$clave];
    }
}