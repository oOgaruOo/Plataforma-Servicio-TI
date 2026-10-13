<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Documentos adjuntos (PDF o imagen) para actas firmadas, facturas, etc.
//   Documentos::guardar($_FILES['archivo'], 'subcarpeta')  -> ['archivo','nombre_original','tamano_kb','mime']
//   Documentos::servir($subcarpeta, $archivo, $nombreOriginal, $descargar)   (envia el archivo y termina)
//   Documentos::borrar($subcarpeta, $archivo)
// El formato se detecta por los BYTES del archivo (no por la extension) y solo se aceptan PDF, PNG, JPG y WEBP.
// Los archivos viven en storage/ (cerrado al navegador) y solo se entregan por un endpoint que valida sesion y permiso.
// ============================================================

class Documentos
{
    public const MIMES = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'];
    public const MAX_MB = 10;

    private static function detectar(string $ruta): ?string
    {
        $m = (string) @file_get_contents($ruta, false, null, 0, 12);
        if (strncmp($m, '%PDF-', 5) === 0) return 'pdf';
        if (strncmp($m, "\x89PNG", 4) === 0) return 'png';
        if (strncmp($m, "\xFF\xD8\xFF", 3) === 0) return 'jpg';
        if (strncmp($m, 'RIFF', 4) === 0 && substr($m, 8, 4) === 'WEBP') return 'webp';
        return null;
    }

    /** True si el navegador envio un archivo mas grande que post_max_size (PHP descarta todo el formulario). */
    public static function excedeLimitePost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && empty($_FILES)
            && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    public const MSG_GRANDE = 'El archivo es demasiado grande (máximo 10 MB).';

    private static function carpeta(string $sub): string
    {
        if (!preg_match('/^[a-z0-9\-]+$/', $sub)) throw new RuntimeException('Carpeta inválida.');
        return STORAGE_PATH . '/' . $sub;
    }

    /** @throws RuntimeException con un mensaje listo para mostrar */
    public static function guardar(array $file, string $sub): array
    {
        $cod = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($cod === UPLOAD_ERR_INI_SIZE || $cod === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('El archivo es demasiado grande (máximo ' . self::MAX_MB . ' MB).');
        }
        if ($cod !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el archivo. Intente de nuevo.');
        if (($file['size'] ?? 0) <= 0) throw new RuntimeException('El archivo está vacío.');
        if ($file['size'] > self::MAX_MB * 1024 * 1024) {
            throw new RuntimeException('El archivo supera el máximo de ' . self::MAX_MB . ' MB.');
        }
        $ext = self::detectar($file['tmp_name']);
        if (!$ext) throw new RuntimeException('Formato no permitido. Suba un PDF o una imagen (JPG, PNG o WEBP).');

        $dir = self::carpeta($sub);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new RuntimeException('No se pudo preparar la carpeta de archivos.');
        $nombre = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destino = $dir . '/' . $nombre;
        if (!move_uploaded_file($file['tmp_name'], $destino)) throw new RuntimeException('No se pudo guardar el archivo.');

        $orig = preg_replace('/[\x00-\x1F\x7F"\\\\\/]/', '', basename((string)($file['name'] ?? 'documento.' . $ext)));
        return [
            'archivo'         => $nombre,
            'nombre_original' => mb_substr($orig !== '' ? $orig : 'documento.' . $ext, 0, 200),
            'tamano_kb'       => (int) max(1, round($file['size'] / 1024)),
            'mime'            => self::MIMES[$ext],
        ];
    }

    public static function borrar(string $sub, ?string $archivo): void
    {
        if (!$archivo || !preg_match('/^[A-Za-z0-9_\-]+\.(pdf|png|jpg|webp)$/', $archivo)) return;
        $r = self::carpeta($sub) . '/' . $archivo;
        if (is_file($r)) @unlink($r);
    }

    /** Envia el archivo (inline para verlo en el visor, o como descarga) y termina la peticion. */
    public static function servir(string $sub, ?string $archivo, ?string $nombreOriginal, bool $descargar = false): void
    {
        if (!$archivo || !preg_match('/^[A-Za-z0-9_\-]+\.(pdf|png|jpg|webp)$/', $archivo)) {
            Response::error('El documento no existe.', null, 404);
        }
        $ruta = self::carpeta($sub) . '/' . $archivo;
        if (!is_file($ruta)) Response::error('El archivo ya no está en el servidor.', null, 404);
        $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        $nombre = $nombreOriginal ?: $archivo;
        if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== $ext) $nombre .= '.' . $ext;
        $nombre = preg_replace('/[^A-Za-z0-9._\- ()ñÑáéíóúÁÉÍÓÚ]/u', '_', $nombre);

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: ' . self::MIMES[$ext]);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($ruta);
        exit;
    }
}
