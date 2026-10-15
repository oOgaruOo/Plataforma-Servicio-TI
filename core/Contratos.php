<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH.: Contratos del personal (gestion interna; sin validez tributaria/legal)
// Un contrato VIGENTE por persona. Renovar cierra el vigente (motivo_fin=renovado) y crea uno nuevo.
// Las tablas se crean solas la primera vez que se abre el modulo.
// ============================================================

class Contratos
{
    public const TIPOS = [
        'indeterminado' => 'Plazo indeterminado', 'plazo_fijo' => 'Plazo fijo',
        'practicante'   => 'Practicante',          'locacion'   => 'Locación de servicios', 'part_time' => 'Part time',
        'por_horas'     => 'Por horas',
    ];

    private static bool $ok = false;

    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT id FROM personal_contratos LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS personal_contratos (
                id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                personal_id    INT UNSIGNED NOT NULL,
                tipo           VARCHAR(20) NOT NULL DEFAULT 'indeterminado',
                puesto         VARCHAR(80) NULL,
                salario        DECIMAL(10,2) NULL,
                fecha_inicio   DATE NOT NULL,
                fecha_fin      DATE NULL,
                vigente        TINYINT(1) NOT NULL DEFAULT 1,
                motivo_fin     VARCHAR(30) NULL,
                observaciones  VARCHAR(250) NULL,
                creado_en      DATETIME NOT NULL,
                creado_por     INT UNSIGNED NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        self::$ok = true;
    }

    public static function vigenteDe(int $personalId): ?array
    {
        return Database::getOne('SELECT * FROM personal_contratos WHERE personal_id = ? AND vigente = 1 ORDER BY id DESC LIMIT 1', [$personalId]);
    }

    public static function crear(int $personalId, array $d, int $uid): array
    {
        if (self::vigenteDe($personalId)) {
            return ['ok' => false, 'error' => 'Esta persona ya tiene un contrato vigente; use «Renovar» o finalice el actual primero.'];
        }
        $id = Database::insert('personal_contratos', [
            'personal_id' => $personalId, 'tipo' => $d['tipo'], 'puesto' => $d['puesto'] ?: null,
            'salario' => ($d['salario'] ?? '') !== '' ? $d['salario'] : null,
            'fecha_inicio' => $d['fecha_inicio'], 'fecha_fin' => $d['fecha_fin'] ?: null, 'vigente' => 1,
            'observaciones' => $d['observaciones'] ?: null, 'creado_en' => date('Y-m-d H:i:s'), 'creado_por' => $uid,
        ]);
        return ['ok' => true, 'id' => $id];
    }

    public static function editar(int $id, array $d): void
    {
        Database::update('personal_contratos', [
            'tipo' => $d['tipo'], 'puesto' => $d['puesto'] ?: null,
            'salario' => ($d['salario'] ?? '') !== '' ? $d['salario'] : null,
            'fecha_inicio' => $d['fecha_inicio'], 'fecha_fin' => $d['fecha_fin'] ?: null, 'observaciones' => $d['observaciones'] ?: null,
        ], 'id = ?', [$id]);
    }

    public static function renovar(int $personalId, array $d, int $uid): array
    {
        $v = self::vigenteDe($personalId);
        if (!$v) return ['ok' => false, 'error' => 'Esta persona no tiene un contrato vigente que renovar.'];
        Database::update('personal_contratos', ['vigente' => 0, 'motivo_fin' => 'renovado'], 'id = ?', [$v['id']]);
        $id = Database::insert('personal_contratos', [
            'personal_id' => $personalId, 'tipo' => $d['tipo'] ?: $v['tipo'], 'puesto' => $d['puesto'] ?: $v['puesto'],
            'salario' => ($d['salario'] ?? '') !== '' ? $d['salario'] : $v['salario'],
            'fecha_inicio' => $d['fecha_inicio'], 'fecha_fin' => $d['fecha_fin'] ?: null, 'vigente' => 1,
            'observaciones' => $d['observaciones'] ?: null, 'creado_en' => date('Y-m-d H:i:s'), 'creado_por' => $uid,
        ]);
        return ['ok' => true, 'id' => $id];
    }

    public static function finalizar(int $id, string $motivo): bool
    {
        return (bool) Database::update('personal_contratos', ['vigente' => 0, 'motivo_fin' => $motivo ?: 'finalizado'], 'id = ? AND vigente = 1', [$id]);
    }

    /** [etiqueta, color] para pintar en pantalla. */
    public static function estado(array $c): array
    {
        if (!$c['vigente']) return [$c['motivo_fin'] === 'renovado' ? 'Renovado' : 'Finalizado', 'gris'];
        if ($c['fecha_fin']) {
            $dias = (int) floor((strtotime($c['fecha_fin']) - strtotime(date('Y-m-d'))) / 86400);
            if ($dias < 0) return ['Vencido', 'rojo'];
            if ($dias <= 30) return ['Vence en ' . $dias . ' día(s)', 'ambar'];
        }
        return ['Vigente', 'verde'];
    }

    public static function porVencer(int $dias = 30): array
    {
        return Database::get(
            "SELECT c.*, p.nombres, p.apellidos, p.dni FROM personal_contratos c INNER JOIN personal p ON p.id = c.personal_id
             WHERE c.vigente = 1 AND c.fecha_fin IS NOT NULL AND c.fecha_fin >= ? AND c.fecha_fin <= ? ORDER BY c.fecha_fin",
            [date('Y-m-d'), date('Y-m-d', strtotime('+' . $dias . ' days'))]
        );
    }

    public static function resumen(): array
    {
        return [
            'vigentes'   => (int) Database::getValue('SELECT COUNT(*) FROM personal_contratos WHERE vigente = 1'),
            'por_vencer' => count(self::porVencer(30)),
            'vencidos'   => (int) Database::getValue("SELECT COUNT(*) FROM personal_contratos WHERE vigente = 1 AND fecha_fin IS NOT NULL AND fecha_fin < ?", [date('Y-m-d')]),
            'indeterminados' => (int) Database::getValue('SELECT COUNT(*) FROM personal_contratos WHERE vigente = 1 AND fecha_fin IS NULL'),
        ];
    }
}
