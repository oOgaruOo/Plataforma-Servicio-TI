<?php
// ============================================================
// SIGTI - Registro de auditoria
// Archivo: Auditoria.php  (SIN tilde)  Clase: class Auditoria
// ============================================================

class Auditoria
{
    public static function registrar(
        string $accion,
        ?string $tabla = null,
        ?int    $registroId = null,
        $datosAnteriores = null,
        $datosNuevos = null
    ): void {
        try {
            Database::insert('auditoria_logs', [
                'usuario_id'       => Auth::userId(),
                'accion'           => $accion,
                'tabla'            => $tabla,
                'registro_id'      => $registroId,
                'datos_anteriores' => self::aTexto($datosAnteriores),
                'datos_nuevos'     => self::aTexto($datosNuevos),
                'ip'               => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'       => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
            ]);
        } catch (Throwable $e) {
            error_log('[SIGTI] Fallo al auditar: ' . $e->getMessage());
        }
    }

    private static function aTexto($datos): ?string
    {
        if ($datos === null) return null;
        if (is_array($datos)) return json_encode($datos, JSON_UNESCAPED_UNICODE);
        return (string) $datos;
    }
}