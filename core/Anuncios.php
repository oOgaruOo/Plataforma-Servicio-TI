<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Anuncios del sistema
//   * asegura la tabla (sigti.sql antiguo no la traia) y las columnas nuevas
//   * vigencia (programado / vigente / vencido / oculto)
//   * destinatarios: todos, por rol o por area
//   * cierre por usuario (anuncio_cierres): lo que cierra una persona no vuelve a salirle
// ============================================================

class Anuncios
{
    public const TIPOS = ['info', 'aviso', 'urgente', 'mantenimiento'];
    public const DESTINOS = ['todos', 'roles', 'areas'];

    private static bool $ok = false;

    public static function asegurar(): void
    {
        if (self::$ok) return;
        Database::run("CREATE TABLE IF NOT EXISTS anuncios (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            titulo       VARCHAR(150) NOT NULL,
            mensaje      TEXT NOT NULL,
            tipo         VARCHAR(15) NOT NULL DEFAULT 'info',
            activo       TINYINT(1) NOT NULL DEFAULT 1,
            fecha_desde  DATE NULL,
            hora_desde   TIME NULL,
            fecha_hasta  DATE NULL,
            hora_hasta   TIME NULL,
            creado_por   INT UNSIGNED NULL,
            created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        Esquema::columna('anuncios', 'destino', "VARCHAR(10) NOT NULL DEFAULT 'todos'");
        Esquema::columna('anuncios', 'destino_ids', 'VARCHAR(255) NULL');
        Esquema::columna('anuncios', 'fijo', 'TINYINT(1) NOT NULL DEFAULT 0');
        Esquema::columna('anuncios', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL');
        Database::run("CREATE TABLE IF NOT EXISTS anuncio_cierres (
            anuncio_id  INT UNSIGNED NOT NULL,
            usuario_id  INT UNSIGNED NOT NULL,
            cerrado_en  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (anuncio_id, usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ok = true;
    }

    /** Ids guardados como "1,3,7" -> [1,3,7]. */
    public static function ids(?string $s): array
    {
        if ($s === null || trim($s) === '') return [];
        return array_values(array_unique(array_filter(array_map('intval', explode(',', $s)), fn($i) => $i > 0)));
    }

    /** "08:30:00" -> "08:30" (o null). */
    public static function hora(?string $h): ?string
    {
        if ($h === null || $h === '') return null;
        return substr($h, 0, 5);
    }

    /** programado | vigente | vencido | oculto, segun la fecha y hora actuales. */
    public static function estado(array $a, ?int $ahora = null): string
    {
        if ((int)($a['activo'] ?? 1) !== 1) return 'oculto';
        $ahora = $ahora ?? time();
        $hoy = date('Y-m-d', $ahora);
        $hm = date('H:i', $ahora);
        $fd = $a['fecha_desde'] ?? null;
        $fh = $a['fecha_hasta'] ?? null;
        $hd = self::hora($a['hora_desde'] ?? null);
        $hh = self::hora($a['hora_hasta'] ?? null);

        if ($fd !== null && $fd !== '') {
            if ($fd > $hoy) return 'programado';
            if ($fd === $hoy && $hd !== null && $hm < $hd) return 'programado';
        }
        if ($fh !== null && $fh !== '') {
            if ($fh < $hoy) return 'vencido';
            if ($fh === $hoy && $hh !== null && $hm > $hh) return 'vencido';
        }
        return 'vigente';
    }

    /** Rol y area del usuario (para decidir si un anuncio le llega). */
    public static function perfil(int $usuarioId): array
    {
        $r = Database::getOne(
            'SELECT u.rol_id, p.area_id FROM usuarios u LEFT JOIN personal p ON p.id = u.personal_id WHERE u.id = ?',
            [$usuarioId]);
        return ['rol_id' => (int)($r['rol_id'] ?? 0), 'area_id' => (int)($r['area_id'] ?? 0)];
    }

    public static function llegaA(array $a, array $perfil): bool
    {
        $dest = $a['destino'] ?? 'todos';
        if ($dest === 'roles') return in_array($perfil['rol_id'], self::ids($a['destino_ids'] ?? ''), true);
        if ($dest === 'areas') return $perfil['area_id'] > 0 && in_array($perfil['area_id'], self::ids($a['destino_ids'] ?? ''), true);
        return true;
    }

    /** Usuarios activos a los que llega el anuncio. */
    public static function destinatarios(array $a): array
    {
        $dest = $a['destino'] ?? 'todos';
        $ids = self::ids($a['destino_ids'] ?? '');
        if ($dest === 'roles' && $ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $f = Database::get("SELECT id FROM usuarios WHERE estado = 'activo' AND rol_id IN ($in)", $ids);
        } elseif ($dest === 'areas' && $ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $f = Database::get("SELECT u.id FROM usuarios u INNER JOIN personal p ON p.id = u.personal_id
                                WHERE u.estado = 'activo' AND p.area_id IN ($in)", $ids);
        } elseif ($dest === 'todos') {
            $f = Database::get("SELECT id FROM usuarios WHERE estado = 'activo'");
        } else {
            $f = [];
        }
        return array_map('intval', array_column($f, 'id'));
    }

    /**
     * Datos de contacto de una lista de usuarios, para correo/WhatsApp.
     * Correo: usuarios.correo > personal.correo_corporativo > personal.correo_personal.
     * Devuelve [usuario_id => ['nombre'=>.., 'correo'=>?string, 'telefono'=>?string]].
     */
    public static function contactos(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $filas = Database::get(
            "SELECT u.id, u.nombre_completo, u.correo AS correo_login,
                    p.correo_corporativo, p.correo_personal, p.telefono
             FROM usuarios u LEFT JOIN personal p ON p.id = u.personal_id
             WHERE u.id IN ($in)", $ids);
        $out = [];
        foreach ($filas as $f) {
            $correo = trim((string) ($f['correo_login'] ?? ''));
            if ($correo === '') $correo = trim((string) ($f['correo_corporativo'] ?? ''));
            if ($correo === '') $correo = trim((string) ($f['correo_personal'] ?? ''));
            $out[(int) $f['id']] = [
                'nombre'   => $f['nombre_completo'],
                'correo'   => $correo !== '' ? $correo : null,
                'telefono' => $f['telefono'] !== null && trim((string) $f['telefono']) !== '' ? trim((string) $f['telefono']) : null,
            ];
        }
        return $out;
    }

    /** Texto legible de los destinatarios: "Todos", "Roles: a, b", "Áreas: x". */
    public static function textoDestino(array $a): string
    {
        $dest = $a['destino'] ?? 'todos';
        $ids = self::ids($a['destino_ids'] ?? '');
        if ($dest === 'todos' || !$ids) return 'Todos los usuarios';
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($dest === 'roles') {
            $n = array_column(Database::get("SELECT nombre FROM roles WHERE id IN ($in) ORDER BY nombre", $ids), 'nombre');
            return 'Roles: ' . ($n ? implode(', ', $n) : '(eliminados)');
        }
        $n = array_column(Database::get("SELECT nombre FROM areas WHERE id IN ($in) ORDER BY nombre", $ids), 'nombre');
        return 'Áreas: ' . ($n ? implode(', ', $n) : '(eliminadas)');
    }
}
