<?php
// ============================================================
// SIGTI - Subida segura de archivos
// ============================================================

class Upload
{
    public static function guardar(array $file, string $subcarpeta): array
    {
        $codigo = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($codigo !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir archivo (codigo ' . $codigo . ').');
        }

        $maxMb = (int)(self::config('max_upload_mb') ?: 10);
        if ($file['size'] > $maxMb * 1024 * 1024) {
            throw new RuntimeException("El archivo supera el maximo de {$maxMb} MB.");
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $permitidas = array_map('trim', explode(',', (string) self::config('extensiones_permitidas')));
        if (!in_array($ext, $permitidas, true)) {
            throw new RuntimeException("La extension .$ext no esta permitida (" . implode(', ', $permitidas) . ").");
        }

        $dir = STORAGE_PATH . '/' . $subcarpeta;
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $nombre = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destino = $dir . '/' . $nombre;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        return [
            'archivo'         => $nombre,
            'nombre_original' => mb_substr(basename($file['name']), 0, 255),
            'tamano_kb'       => (int) round($file['size'] / 1024),
        ];
    }

    private static function config(string $clave): ?string
    {
        $row = Database::getOne("SELECT valor FROM configuracion WHERE clave = ?", [$clave]);
        return $row['valor'] ?? null;
    }
}