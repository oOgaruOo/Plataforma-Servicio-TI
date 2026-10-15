<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH.: Planillas (gestion interna; NO tiene validez tributaria/legal: no reemplaza PLAME,
// comprobantes electronicos ni un sistema contable oficial. Calcula un neto referencial por mes.)
// Sueldo base = salario del contrato vigente. Conceptos: catalogo de ingresos/descuentos (fijo o %).
// Las tablas se crean solas la primera vez que se abre el modulo.
// ============================================================

class Planillas
{
    private static bool $ok = false;

    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT id FROM rrhh_conceptos LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_conceptos (
                id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre  VARCHAR(80) NOT NULL,
                tipo    VARCHAR(10) NOT NULL,
                modo    VARCHAR(12) NOT NULL DEFAULT 'fijo',
                valor   DECIMAL(10,2) NOT NULL DEFAULT 0,
                orden   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                activo  TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_planillas (
                id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                periodo           VARCHAR(7) NOT NULL,
                personal_id       INT UNSIGNED NOT NULL,
                sueldo_base       DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_ingresos    DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_descuentos  DECIMAL(10,2) NOT NULL DEFAULT 0,
                neto              DECIMAL(10,2) NOT NULL DEFAULT 0,
                estado            VARCHAR(10) NOT NULL DEFAULT 'borrador',
                generado_en       DATETIME NOT NULL,
                generado_por      INT UNSIGNED NULL,
                cerrado_en        DATETIME NULL,
                cerrado_por       INT UNSIGNED NULL,
                UNIQUE KEY uq_periodo_personal (periodo, personal_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_planilla_detalle (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                planilla_id  INT UNSIGNED NOT NULL,
                nombre       VARCHAR(80) NOT NULL,
                tipo         VARCHAR(10) NOT NULL,
                monto        DECIMAL(10,2) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            foreach ([
                ['Asignación familiar', 'ingreso', 'fijo', 0, 1], ['Bonificación / movilidad', 'ingreso', 'fijo', 0, 2],
                ['Tardanzas y faltas', 'descuento', 'fijo', 0, 1], ['Préstamo / adelanto', 'descuento', 'fijo', 0, 2],
                ['Otro descuento', 'descuento', 'fijo', 0, 3],
            ] as $c) {
                Database::insert('rrhh_conceptos', ['nombre' => $c[0], 'tipo' => $c[1], 'modo' => $c[2], 'valor' => $c[3], 'orden' => $c[4], 'activo' => 1]);
            }
        }
        self::$ok = true;
    }

    public static function conceptos(bool $soloActivos = true): array
    {
        return Database::get('SELECT * FROM rrhh_conceptos' . ($soloActivos ? ' WHERE activo = 1' : '') . ' ORDER BY tipo DESC, orden, nombre');
    }

    public static function guardarConcepto(array $d, int $id = 0): int
    {
        $datos = ['nombre' => $d['nombre'], 'tipo' => $d['tipo'], 'modo' => $d['modo'], 'valor' => $d['valor'] ?: 0, 'orden' => (int) ($d['orden'] ?? 0)];
        if ($id) { Database::update('rrhh_conceptos', $datos, 'id = ?', [$id]); return $id; }
        return Database::insert('rrhh_conceptos', $datos + ['activo' => 1]);
    }

    public static function toggleConcepto(int $id, bool $activo): void
    {
        Database::update('rrhh_conceptos', ['activo' => $activo ? 1 : 0], 'id = ?', [$id]);
    }

    /** Genera (o recalcula) en borrador las planillas de un periodo, para el personal activo con contrato vigente. */
    public static function generar(string $periodo, int $uid): array
    {
        $personas = Database::get("SELECT id FROM personal WHERE estado = 'activo'");
        $conceptos = self::conceptos(true);
        $n = 0; $omitidos = 0;
        foreach ($personas as $pe) {
            $pid = (int) $pe['id'];
            $c = Contratos::vigenteDe($pid);
            if (!$c) { $omitidos++; continue; }
            $estActual = Database::getValue('SELECT estado FROM rrhh_planillas WHERE periodo = ? AND personal_id = ?', [$periodo, $pid]);
            if ($estActual === 'cerrada') continue;
            $base = (float) ($c['salario'] ?? 0);
            $ing = 0.0; $des = 0.0; $det = [];
            foreach ($conceptos as $cp) {
                $m = $cp['modo'] === 'porcentaje' ? round($base * (float) $cp['valor'] / 100, 2) : (float) $cp['valor'];
                if ($m == 0) continue;
                $det[] = ['nombre' => $cp['nombre'], 'tipo' => $cp['tipo'], 'monto' => $m];
                if ($cp['tipo'] === 'ingreso') $ing += $m; else $des += $m;
            }
            $neto = $base + $ing - $des;
            $existe = Database::getValue('SELECT id FROM rrhh_planillas WHERE periodo = ? AND personal_id = ?', [$periodo, $pid]);
            if ($existe) {
                Database::update('rrhh_planillas', ['sueldo_base' => $base, 'total_ingresos' => $ing, 'total_descuentos' => $des, 'neto' => $neto, 'generado_en' => date('Y-m-d H:i:s'), 'generado_por' => $uid], 'id = ?', [$existe]);
                Database::run('DELETE FROM rrhh_planilla_detalle WHERE planilla_id = ?', [$existe]);
                $planillaId = (int) $existe;
            } else {
                $planillaId = Database::insert('rrhh_planillas', ['periodo' => $periodo, 'personal_id' => $pid, 'sueldo_base' => $base, 'total_ingresos' => $ing, 'total_descuentos' => $des, 'neto' => $neto, 'estado' => 'borrador', 'generado_en' => date('Y-m-d H:i:s'), 'generado_por' => $uid]);
            }
            foreach ($det as $d) Database::insert('rrhh_planilla_detalle', ['planilla_id' => $planillaId, 'nombre' => $d['nombre'], 'tipo' => $d['tipo'], 'monto' => $d['monto']]);
            $n++;
        }
        return ['generadas' => $n, 'omitidas_sin_contrato' => $omitidos];
    }

    public static function cerrar(string $periodo, int $uid): int
    {
        return Database::update('rrhh_planillas', ['estado' => 'cerrada', 'cerrado_en' => date('Y-m-d H:i:s'), 'cerrado_por' => $uid], "periodo = ? AND estado = 'borrador'", [$periodo]);
    }

    public static function reabrir(int $id): bool
    {
        return (bool) Database::update('rrhh_planillas', ['estado' => 'borrador', 'cerrado_en' => null, 'cerrado_por' => null], "id = ? AND estado = 'cerrada'", [$id]);
    }

    public static function detalle(int $id): ?array
    {
        $p = Database::getOne(
            "SELECT pl.*, p.nombres, p.apellidos, p.dni, p.cargo, a.nombre AS area
             FROM rrhh_planillas pl INNER JOIN personal p ON p.id = pl.personal_id LEFT JOIN areas a ON a.id = p.area_id
             WHERE pl.id = ?", [$id]
        );
        if (!$p) return null;
        $p['detalle'] = Database::get('SELECT * FROM rrhh_planilla_detalle WHERE planilla_id = ? ORDER BY tipo DESC, id', [$id]);
        return $p;
    }

    public static function resumen(): array
    {
        $periodo = date('Y-m');
        return [
            'periodo_actual' => $periodo,
            'generadas' => (int) Database::getValue('SELECT COUNT(*) FROM rrhh_planillas WHERE periodo = ?', [$periodo]),
            'cerradas'  => (int) Database::getValue("SELECT COUNT(*) FROM rrhh_planillas WHERE periodo = ? AND estado = 'cerrada'", [$periodo]),
            'total_neto' => (float) (Database::getValue('SELECT COALESCE(SUM(neto),0) FROM rrhh_planillas WHERE periodo = ?', [$periodo]) ?? 0),
        ];
    }
}
