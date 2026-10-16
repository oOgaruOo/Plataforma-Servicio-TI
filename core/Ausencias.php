<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH.: Permisos, dias libres y compensaciones del personal (gestion interna; sin validez tributaria/legal)
// El area (o RR.HH. en su nombre) solicita, RR.HH. aprueba/rechaza/cancela — mismo flujo que Vacaciones.
// Una solicitud APROBADA se refleja sola en Asistencia ese dia (automatico), igual que ya pasa con Vacaciones.
// Las tablas se crean solas la primera vez que se abre el modulo.
// ============================================================

class Ausencias
{
    public const TIPOS = [
        'permiso'      => 'Permiso',
        'dia_libre'    => 'Día libre',
        'compensacion' => 'Compensación',
    ];

    public const ESTADOS = [
        'pendiente' => ['Pendiente', 'ambar'], 'aprobada' => ['Aprobada', 'verde'],
        'rechazada' => ['Rechazada', 'rojo'],  'cancelada' => ['Cancelada', 'gris'],
    ];

    private static bool $ok = false;

    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT id FROM rrhh_ausencias LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_ausencias (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                personal_id     INT UNSIGNED NOT NULL,
                tipo            VARCHAR(20) NOT NULL DEFAULT 'permiso',
                fecha_inicio    DATE NOT NULL,
                fecha_fin       DATE NOT NULL,
                dias            SMALLINT UNSIGNED NOT NULL,
                motivo          VARCHAR(200) NULL,
                estado          VARCHAR(12) NOT NULL DEFAULT 'pendiente',
                motivo_rechazo  VARCHAR(200) NULL,
                observaciones   VARCHAR(200) NULL,
                aprobado_por    INT UNSIGNED NULL,
                aprobado_en     DATETIME NULL,
                creado_en       DATETIME NOT NULL,
                creado_por      INT UNSIGNED NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        self::$ok = true;
    }

    public static function dias(string $ini, string $fin): int
    {
        return (int) floor((strtotime($fin) - strtotime($ini)) / 86400) + 1;
    }

    /** ¿Esta persona ya tiene una solicitud pendiente o aprobada que se cruza con esas fechas (cualquier tipo)? */
    public static function traslape(int $personalId, string $ini, string $fin, int $excluirId = 0): bool
    {
        $r = Database::getValue(
            "SELECT COUNT(*) FROM rrhh_ausencias WHERE personal_id = ? AND estado IN ('pendiente','aprobada') AND id <> ? AND fecha_inicio <= ? AND fecha_fin >= ?",
            [$personalId, $excluirId, $fin, $ini]
        );
        return (int) $r > 0;
    }

    /** ¿Esta persona ya tiene vacaciones pendientes o aprobadas que se cruzan con esas fechas? (aviso, no bloquea). */
    public static function traslapaConVacaciones(int $personalId, string $ini, string $fin): bool
    {
        if (!class_exists('Vacaciones')) return false;
        $r = Database::getValue(
            "SELECT COUNT(*) FROM rrhh_vacaciones WHERE personal_id = ? AND estado IN ('pendiente','aprobada') AND fecha_inicio <= ? AND fecha_fin >= ?",
            [$personalId, $fin, $ini]
        );
        return (int) $r > 0;
    }

    public static function crear(int $personalId, string $tipo, string $ini, string $fin, ?string $motivo, ?string $obs, int $uid): array
    {
        if (!isset(self::TIPOS[$tipo])) return ['ok' => false, 'error' => 'Tipo de permiso no válido.'];
        if ($fin < $ini) return ['ok' => false, 'error' => 'La fecha de fin no puede ser anterior a la de inicio.'];
        if (self::traslape($personalId, $ini, $fin)) return ['ok' => false, 'error' => 'Esta persona ya tiene una solicitud pendiente o aprobada (permiso/día libre/compensación) que se cruza con esas fechas.'];
        $d = self::dias($ini, $fin);
        $id = Database::insert('rrhh_ausencias', [
            'personal_id' => $personalId, 'tipo' => $tipo, 'fecha_inicio' => $ini, 'fecha_fin' => $fin, 'dias' => $d,
            'motivo' => $motivo ?: null, 'estado' => 'pendiente',
            'observaciones' => $obs ?: null, 'creado_en' => date('Y-m-d H:i:s'), 'creado_por' => $uid,
        ]);
        $r = ['ok' => true, 'id' => $id];
        if (self::traslapaConVacaciones($personalId, $ini, $fin)) {
            $r['aviso'] = 'Ojo: esta persona también tiene una solicitud de vacaciones pendiente o aprobada que se cruza con estas fechas.';
        }
        return $r;
    }

    public static function aprobar(int $id, int $uid): bool
    {
        return (bool) Database::update('rrhh_ausencias', ['estado' => 'aprobada', 'aprobado_por' => $uid, 'aprobado_en' => date('Y-m-d H:i:s')], "id = ? AND estado = 'pendiente'", [$id]);
    }

    public static function rechazar(int $id, string $motivo, int $uid): bool
    {
        return (bool) Database::update('rrhh_ausencias', ['estado' => 'rechazada', 'motivo_rechazo' => $motivo, 'aprobado_por' => $uid, 'aprobado_en' => date('Y-m-d H:i:s')], "id = ? AND estado = 'pendiente'", [$id]);
    }

    public static function cancelar(int $id): bool
    {
        return (bool) Database::update('rrhh_ausencias', ['estado' => 'cancelada'], "id = ? AND estado IN ('pendiente','aprobada')", [$id]);
    }

    public static function resumen(): array
    {
        return [
            'pendientes'  => (int) Database::getValue("SELECT COUNT(*) FROM rrhh_ausencias WHERE estado = 'pendiente'"),
            'proximas_7d' => (int) Database::getValue(
                "SELECT COUNT(*) FROM rrhh_ausencias WHERE estado = 'aprobada' AND fecha_inicio BETWEEN ? AND ?",
                [date('Y-m-d'), date('Y-m-d', strtotime('+7 days'))]
            ),
        ];
    }
}
