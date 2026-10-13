<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Notificaciones automaticas (helper estatico)
// Uso:  Notificador::avisar($usuarioDestino, $tipo, $titulo,
//                            $mensaje, $url);
// Nunca rompe la operacion principal (try/catch interno).
// ============================================================

class Notificador
{
    public static function avisar(?int $usuarioDestino, string $tipo,
                                  string $titulo, string $mensaje = '', ?string $url = null): void
    {
        if ($usuarioDestino === null || $usuarioDestino <= 0) return;
        try {
            Database::insert('notificaciones', [
                'usuario_destino_id' => $usuarioDestino,
                'tipo'    => $tipo,
                'titulo'  => mb_substr($titulo, 0, 120),
                'mensaje' => mb_substr($mensaje, 0, 300),
                'url'     => $url ? mb_substr($url, 0, 200) : null,
            ]);
        } catch (Throwable $e) {
            error_log('[SIGTI] Notificacion fallida: ' . $e->getMessage());
        }
    }

    /** Notificar a TODOS los usuarios activos (ej: publicacion de anuncios) */
    public static function avisarTodos(string $tipo, string $titulo,
                                       string $mensaje = '', ?string $url = null): void
    {
        try {
            $usuarios = Database::get("SELECT id FROM usuarios WHERE estado = 'activo'");
            foreach ($usuarios as $u) {
                self::avisar((int)$u['id'], $tipo, $titulo, $mensaje, $url);
            }
        } catch (Throwable $e) {
            error_log('[SIGTI] avisarTodos: ' . $e->getMessage());
        }
    }

    /** Notificar a todos los usuarios con un permiso (ej: supervisores) */
    public static function avisarPermiso(string $modulo, string $accion, string $tipo,
                                         string $titulo, string $mensaje = '', ?string $url = null): void
    {
        try {
            // super_admin siempre
            $sa = Database::get("SELECT id FROM usuarios WHERE rol_id = 1 AND estado = 'activo'");
            foreach ($sa as $u) {
                if ((int)$u['id'] !== Auth::userId()) {
                    self::avisar((int)$u['id'], $tipo, $titulo, $mensaje, $url);
                }
            }
            // con el permiso por rol
            $conPermiso = Database::get(
                "SELECT u.id FROM usuarios u
                 INNER JOIN rol_permisos rp ON rp.rol_id = u.rol_id
                 INNER JOIN permisos p ON p.id = rp.permiso_id
                 WHERE p.modulo = ? AND p.accion = ? AND u.estado = 'activo'",
                [$modulo, $accion]);
            foreach ($conPermiso as $u) {
                if ((int)$u['id'] !== Auth::userId()) {
                    self::avisar((int)$u['id'], $tipo, $titulo, $mensaje, $url);
                }
            }
        } catch (Throwable $e) {
            error_log('[SIGTI] avisarPermiso: ' . $e->getMessage());
        }
    }
}