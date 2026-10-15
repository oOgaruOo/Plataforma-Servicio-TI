<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH.: Vacaciones del personal (gestion interna; sin validez tributaria/legal)
// Saldo estimado: 2.5 dias ganados por mes trabajado desde la fecha de ingreso (30 dias / 12 meses).
// Las tablas se crean solas la primera vez que se abre el modulo.
// ============================================================

class Vacaciones
{
    public const ESTADOS = [
        'pendiente' => ['Pendiente', 'ambar'], 'aprobada' => ['Aprobada', 'verde'],
        'rechazada' => ['Rechazada', 'rojo'],  'cancelada' => ['Cancelada', 'gris'],
    ];

    /** Tipos de contrato (ver Contratos::TIPOS) que NO acumulan vacaciones. */
    public const TIPOS_SIN_ACUMULACION = ['practicante', 'locacion'];

    /** Dias disponibles a partir de los cuales se sugiere que la persona ya salga de vacaciones (1 año acumulado). */
    public const UMBRAL_SUGERENCIA = 30;

    private static bool $ok = false;

    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT id FROM rrhh_vacaciones LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_vacaciones (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                personal_id     INT UNSIGNED NOT NULL,
                fecha_inicio    DATE NOT NULL,
                fecha_fin       DATE NOT NULL,
                dias            SMALLINT UNSIGNED NOT NULL,
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

    private static function mesesDesdeIngreso(string $ingreso): int
    {
        return max(0, (int) floor((strtotime(date('Y-m-d')) - strtotime($ingreso)) / 86400 / 30.44));
    }

    /** Tiempo trabajado desde la fecha de ingreso (ficha de Personal). Null si no tiene fecha de ingreso registrada. */
    public static function antiguedad(int $personalId): ?array
    {
        $ing = Database::getValue('SELECT fecha_ingreso FROM personal WHERE id = ?', [$personalId]);
        if (!$ing) return null;
        $dias = max(0, (int) floor((strtotime(date('Y-m-d')) - strtotime((string) $ing)) / 86400));
        $anios = intdiv($dias, 365);
        $meses = intdiv($dias % 365, 30);
        $texto = trim(
            ($anios > 0 ? $anios . ' año' . ($anios != 1 ? 's' : '') : '') .
            ($anios > 0 && $meses > 0 ? ' y ' : '') .
            ($meses > 0 || $anios == 0 ? $meses . ' mes' . ($meses != 1 ? 'es' : '') : '')
        );
        return ['fecha_ingreso' => (string) $ing, 'dias' => $dias, 'anios' => $anios, 'meses' => $meses, 'texto' => $texto];
    }

    /**
     * Saldo estimado de la persona: ganados (desde su ingreso), usados, reservados (pendientes/aprobadas futuras) y disponible.
     * Solo acumula si su contrato VIGENTE no es de un tipo sin acumulacion (ver TIPOS_SIN_ACUMULACION); si no tiene
     * contrato vigente registrado, se asume que si acumula (no se le puede quitar algo que nunca se definio).
     */
    public static function saldo(int $personalId): array
    {
        $ing = Database::getValue('SELECT fecha_ingreso FROM personal WHERE id = ?', [$personalId]);
        if (!$ing) return ['ganados' => 0, 'usados' => 0, 'reservados' => 0, 'disponible' => 0, 'acumula' => true, 'tipo_contrato' => null, 'tipo_contrato_txt' => null, 'sugerido' => false];

        $tipo = null;
        if (class_exists('Contratos')) {
            $vig = Contratos::vigenteDe($personalId);
            $tipo = $vig['tipo'] ?? null;
        }
        $acumula = !($tipo && in_array($tipo, self::TIPOS_SIN_ACUMULACION, true));

        $meses = self::mesesDesdeIngreso((string) $ing);
        $ganados = $acumula ? (int) floor($meses * 2.5) : 0;
        $usados = (int) (Database::getValue(
            "SELECT COALESCE(SUM(dias),0) FROM rrhh_vacaciones WHERE personal_id = ? AND estado = 'aprobada' AND fecha_fin < ?",
            [$personalId, date('Y-m-d')]
        ) ?? 0);
        $reservados = (int) (Database::getValue(
            "SELECT COALESCE(SUM(dias),0) FROM rrhh_vacaciones WHERE personal_id = ? AND estado IN ('pendiente','aprobada') AND fecha_fin >= ?",
            [$personalId, date('Y-m-d')]
        ) ?? 0);
        $disponible = max(0, $ganados - $usados - $reservados);
        return [
            'ganados' => $ganados, 'usados' => $usados, 'reservados' => $reservados,
            'disponible' => $disponible,
            'acumula' => $acumula, 'tipo_contrato' => $tipo,
            'tipo_contrato_txt' => $tipo ? (Contratos::TIPOS[$tipo] ?? $tipo) : null,
            'sugerido' => $disponible >= self::UMBRAL_SUGERENCIA,
        ];
    }

    /**
     * Personal activo cuyo saldo disponible ya llega (o supera) el umbral de sugerencia: candidatos a
     * que RR.HH. les programe la salida de vacaciones. Ordenado de mayor a menor saldo disponible.
     */
    public static function sugeridos(): array
    {
        $personas = Database::get("SELECT id, dni, nombres, apellidos FROM personal WHERE estado = 'activo'");
        $out = [];
        foreach ($personas as $p) {
            $s = self::saldo((int) $p['id']);
            if (!$s['sugerido']) continue;
            $out[] = [
                'personal_id' => (int) $p['id'], 'dni' => $p['dni'], 'nombres' => $p['nombres'], 'apellidos' => $p['apellidos'],
                'disponible' => $s['disponible'], 'ganados' => $s['ganados'],
            ];
        }
        usort($out, function ($a, $b) { return $b['disponible'] <=> $a['disponible']; });
        return $out;
    }

    public static function traslape(int $personalId, string $ini, string $fin, int $excluirId = 0): bool
    {
        $r = Database::getValue(
            "SELECT COUNT(*) FROM rrhh_vacaciones WHERE personal_id = ? AND estado IN ('pendiente','aprobada') AND id <> ? AND fecha_inicio <= ? AND fecha_fin >= ?",
            [$personalId, $excluirId, $fin, $ini]
        );
        return (int) $r > 0;
    }

    public static function crear(int $personalId, string $ini, string $fin, ?string $obs, int $uid, bool $forzar = false): array
    {
        if ($fin < $ini) return ['ok' => false, 'error' => 'La fecha de fin no puede ser anterior a la de inicio.'];
        if (self::traslape($personalId, $ini, $fin)) return ['ok' => false, 'error' => 'Esta persona ya tiene una solicitud pendiente o aprobada que se cruza con esas fechas.'];
        $d = self::dias($ini, $fin);
        if (!$forzar) {
            $s = self::saldo($personalId);
            if ($d > $s['disponible']) return ['ok' => false, 'error' => 'Solo tiene ' . $s['disponible'] . ' día(s) disponible(s) (solicita ' . $d . ').'];
        }
        $id = Database::insert('rrhh_vacaciones', [
            'personal_id' => $personalId, 'fecha_inicio' => $ini, 'fecha_fin' => $fin, 'dias' => $d, 'estado' => 'pendiente',
            'observaciones' => $obs ?: null, 'creado_en' => date('Y-m-d H:i:s'), 'creado_por' => $uid,
        ]);
        return ['ok' => true, 'id' => $id];
    }

    public static function aprobar(int $id, int $uid): bool
    {
        return (bool) Database::update('rrhh_vacaciones', ['estado' => 'aprobada', 'aprobado_por' => $uid, 'aprobado_en' => date('Y-m-d H:i:s')], "id = ? AND estado = 'pendiente'", [$id]);
    }

    public static function rechazar(int $id, string $motivo, int $uid): bool
    {
        return (bool) Database::update('rrhh_vacaciones', ['estado' => 'rechazada', 'motivo_rechazo' => $motivo, 'aprobado_por' => $uid, 'aprobado_en' => date('Y-m-d H:i:s')], "id = ? AND estado = 'pendiente'", [$id]);
    }

    public static function cancelar(int $id): bool
    {
        return (bool) Database::update('rrhh_vacaciones', ['estado' => 'cancelada'], "id = ? AND estado IN ('pendiente','aprobada')", [$id]);
    }

    public static function resumen(): array
    {
        return [
            'pendientes'  => (int) Database::getValue("SELECT COUNT(*) FROM rrhh_vacaciones WHERE estado = 'pendiente'"),
            'proximas_7d' => (int) Database::getValue(
                "SELECT COUNT(*) FROM rrhh_vacaciones WHERE estado = 'aprobada' AND fecha_inicio BETWEEN ? AND ?",
                [date('Y-m-d'), date('Y-m-d', strtotime('+7 days'))]
            ),
            'sugeridos' => count(self::sugeridos()),
        ];
    }
}
