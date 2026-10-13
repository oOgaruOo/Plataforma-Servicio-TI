<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Fotos: validar la imagen subida y guardarla recortada en cuadrado
// (la usa la foto del personal para el carnet; la de usuarios tiene su propia copia)
// ============================================================

class FotoImagen
{
    public const LADO_PX   = 320;
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Revisa $_FILES['foto']. Devuelve [tipoImagen, null] si sirve, o [0, 'mensaje']. */
    public static function validar(array $f): array
    {
        if (empty($f['name'])) return [0, 'Seleccione una imagen.'];
        if (($f['error'] ?? 0) !== UPLOAD_ERR_OK) {
            if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return [0, 'La imagen es demasiado grande (maximo 5 MB).'];
            return [0, 'Error al subir (codigo ' . (int)$f['error'] . ').'];
        }
        if ($f['size'] > self::MAX_BYTES) return [0, 'Maximo 5 MB.'];
        // manda el contenido, no la extension: debe ser realmente PNG o JPG
        $info = @getimagesize($f['tmp_name']);
        $tipo = $info[2] ?? 0;
        if (!in_array($tipo, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) return [0, 'Solo imagenes PNG o JPG validas.'];
        return [$tipo, null];
    }

    /** Recorta al centro en cuadrado, reduce y guarda como JPG. false si no hay GD o no se pudo. */
    public static function procesar(string $origen, int $tipo, string $destino): bool
    {
        if (!function_exists('imagecreatetruecolor')) return false;
        $img = $tipo === IMAGETYPE_PNG ? @imagecreatefrompng($origen) : @imagecreatefromjpeg($origen);
        if (!$img) return false;

        if ($tipo === IMAGETYPE_JPEG && function_exists('exif_read_data') && function_exists('imagerotate')) {
            $exif = @exif_read_data($origen);
            $giro = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
            if ($giro !== 0) {
                $rot = imagerotate($img, $giro, 0);
                if ($rot) { imagedestroy($img); $img = $rot; }
            }
        }
        $w = imagesx($img); $h = imagesy($img);
        $lado = min($w, $h);
        $out = imagecreatetruecolor(self::LADO_PX, self::LADO_PX);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, (int)(($w - $lado) / 2), (int)(($h - $lado) / 2), self::LADO_PX, self::LADO_PX, $lado, $lado);
        $ok = imagejpeg($out, $destino, 85);
        imagedestroy($img); imagedestroy($out);
        return (bool)$ok;
    }
}
