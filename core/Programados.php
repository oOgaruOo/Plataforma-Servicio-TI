<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Ciclo de los preventivos PROGRAMADOS
//
// Antes: al «ejecutar» una programacion se movia la proxima fecha enseguida, aunque el
// trabajo no se hubiera hecho, y nada decia si se habia cumplido ni cuando tocaba el siguiente.
//
// Ahora el ciclo es:
//   1. Llega la fecha  -> se GENERA el mantenimiento (queda vinculado a su programacion,
//                         que pasa a «en curso»). La proxima fecha NO se mueve todavia.
//   2. Se FINALIZA el mantenimiento -> se anota la fecha real de ejecucion y se calcula SOLA
//                         la proxima fecha (frecuencia contada desde el dia en que se termino).
//   3. Si se CANCELA / no es reparable / se elimina -> la programacion se libera
//                         (cancelado: se reintenta en 7 dias; no reparable: se pausa).
//   4. 7 dias antes de la fecha se avisa al responsable (una sola vez por fecha).
//
// Las fechas se calculan en PHP (no con CURDATE/NOW de la base) para que no dependan de la zona
// horaria del servidor de base de datos.
// ============================================================
class Programados
{
    public const MESES = ['mensual' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12];
    public const DIAS_AVISO = 7;                 // aviso previo
    public const DIAS_REINTENTO = 7;             // tras cancelar un mantenimiento programado
    /** Plazo interno (SLA) segun el tipo; el mismo de «Registrar mantenimiento». */
    public const SLA_DIAS = ['preventivo' => 10, 'limpieza' => 5, 'actualizacion' => 30];
    public const TIPOS = ['preventivo', 'limpieza', 'actualizacion'];

    private static bool $ok = false;

    /** Crea (una sola vez) las columnas del vinculo. Llamar ANTES de Database::begin(). */
    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT programado_id FROM mantenimientos LIMIT 0');
        } catch (Throwable $e) {
            Esquema::columna('mantenimientos', 'programado_id', 'INT UNSIGNED NULL');
        }
        try {
            Database::run('SELECT mantenimiento_actual_id, ultimo_mantenimiento_id, aviso_para FROM mantenimientos_programados LIMIT 0');
        } catch (Throwable $e) {
            Esquema::columna('mantenimientos_programados', 'mantenimiento_actual_id', 'INT UNSIGNED NULL');
            Esquema::columna('mantenimientos_programados', 'ultimo_mantenimiento_id', 'INT UNSIGNED NULL');
            Esquema::columna('mantenimientos_programados', 'aviso_para', 'DATE NULL');
        }
        self::$ok = true;
    }

    /** $desde (Y-m-d) + la frecuencia, sin desbordar el fin de mes (31-ene + 1 mes = 28/29-feb). */
    public static function proximaDesde(string $desde, string $frecuencia): string
    {
        $meses = self::MESES[$frecuencia] ?? 6;
        $d = new DateTimeImmutable(substr($desde, 0, 10));
        $y = (int)$d->format('Y');
        $m = (int)$d->format('n') + $meses;
        $dia = (int)$d->format('j');
        $y += intdiv($m - 1, 12);
        $m = ($m - 1) % 12 + 1;
        $ultimo = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
        return sprintf('%04d-%02d-%02d', $y, $m, min($dia, $ultimo));
    }

    /** Dias desde $hoy hasta $fecha (negativo = ya paso). */
    public static function dias(string $fecha, ?string $hoy = null): int
    {
        $a = new DateTimeImmutable($hoy ?: date('Y-m-d'));
        $b = new DateTimeImmutable(substr($fecha, 0, 10));
        return (int)$a->diff($b)->format('%r%a');
    }

    /** pausada | en_curso | vencida | por_vencer | al_dia */
    public static function estadoCiclo(array $p, ?string $hoy = null): string
    {
        if (!empty($p['mantenimiento_actual_id'])) return 'en_curso';
        if ((int)($p['activo'] ?? 1) !== 1) return 'pausada';
        $d = self::dias((string)$p['proxima_fecha'], $hoy);
        if ($d < 0) return 'vencida';
        if ($d <= self::DIAS_AVISO) return 'por_vencer';
        return 'al_dia';
    }

    /**
     * Genera el mantenimiento de una programacion y la deja «en curso».
     * No abre transaccion (la abre quien llama). NO mueve la proxima fecha.
     */
    public static function generar(array $p, ?int $usuarioId, string $motivo): array
    {
        $codigo = Correlativo::generar('mantenimiento');
        $dias = self::SLA_DIAS[$p['tipo']] ?? 10;
        $sla = (new DateTime('now'))->add(new DateInterval('P' . $dias . 'D'))->format('Y-m-d 23:59:59');

        $id = Database::insert('mantenimientos', [
            'codigo'       => $codigo,
            'equipo_id'    => $p['equipo_id'],
            'tipo'         => $p['tipo'],
            'origen'       => 'programado',
            'tecnico_usuario_id' => $p['responsable_id'] ?: null,
            'estado'       => 'solicitado',
            'diagnostico'  => $motivo . ' (' . $p['frecuencia'] . ')',
            'fecha_inicio' => date('Y-m-d H:i:s'),
            'sla_limite'   => $sla,
            'programado_id' => $p['id'],
        ]);
        Database::update('equipos', ['estado' => 'en_mantenimiento'], 'id = ?', [$p['equipo_id']]);
        Database::insert('equipo_historial', [
            'equipo_id' => $p['equipo_id'], 'usuario_id' => $usuarioId, 'evento' => 'mantenimiento',
            'detalle'   => "Preventivo programado ($codigo) generado",
        ]);
        Database::update('mantenimientos_programados',
            ['mantenimiento_actual_id' => $id, 'aviso_para' => null], 'id = ?', [$p['id']]);

        return ['id' => (int)$id, 'codigo' => $codigo, 'sla_limite' => $sla];
    }

    /**
     * Regla de calendario del motor: genera los preventivos cuya fecha llego y que aun no tienen
     * un mantenimiento en curso. Si el equipo esta ocupado con otro mantenimiento, se espera
     * (NO se salta el ciclo). Devuelve los generados. La transaccion la abre quien llama.
     */
    public static function generarVencidos(?string $hoy = null): array
    {
        $hoy = $hoy ?: date('Y-m-d');
        $generados = [];
        $vencidos = Database::get(
            "SELECT mp.*, e.codigo AS eq_codigo, e.estado AS eq_estado
             FROM mantenimientos_programados mp
             INNER JOIN equipos e ON e.id = mp.equipo_id
             WHERE mp.activo = 1 AND mp.mantenimiento_actual_id IS NULL AND mp.proxima_fecha <= ?
             ORDER BY mp.proxima_fecha ASC, mp.id ASC", [$hoy]);

        foreach ($vencidos as $p) {
            if ($p['eq_estado'] === 'dado_de_baja') continue;
            $enCurso = Database::getValue(
                "SELECT id FROM mantenimientos WHERE equipo_id = ?
                 AND estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')",
                [$p['equipo_id']]);
            if ($enCurso) continue;                       // se reintenta mañana: no se pierde el ciclo
            $g = self::generar($p, null, 'PREVENTIVO AUTOMATICO - generado por calendario');
            $generados[] = ['codigo' => $g['codigo'], 'equipo' => $p['eq_codigo'], 'origen' => 'calendario',
                            'responsable_id' => $p['responsable_id'] ? (int)$p['responsable_id'] : null];
        }
        return $generados;
    }

    /** Programacion a la que pertenece un mantenimiento (o a la que reprogramaria), o null. */
    public static function deMantenimiento(array $mt): ?array
    {
        if (!empty($mt['programado_id'])) {
            $p = Database::getOne('SELECT * FROM mantenimientos_programados WHERE id = ?', [$mt['programado_id']]);
            if (!$p) return null;
            // si la programacion ya esta ocupada por OTRO mantenimiento, este no la mueve
            if (!empty($p['mantenimiento_actual_id']) && (int)$p['mantenimiento_actual_id'] !== (int)$mt['id']) {
                return (int)($p['ultimo_mantenimiento_id'] ?? 0) === (int)$mt['id'] ? $p : null;
            }
            return $p;
        }
        // mantenimiento hecho a mano (no generado por el calendario) del mismo tipo y equipo
        if (($mt['origen'] ?? '') !== 'programado' && in_array($mt['tipo'] ?? '', self::TIPOS, true)) {
            return Database::getOne(
                "SELECT * FROM mantenimientos_programados
                 WHERE equipo_id = ? AND tipo = ? AND activo = 1 AND mantenimiento_actual_id IS NULL
                 ORDER BY proxima_fecha ASC, id ASC LIMIT 1", [$mt['equipo_id'], $mt['tipo']]);
        }
        return null;
    }

    /**
     * El mantenimiento se FINALIZO: anota la fecha real y calcula la proxima.
     * Dentro de la transaccion de quien llama. Devuelve null si no pertenece a ninguna programacion.
     */
    public static function alCerrar(array $mt, ?string $fechaFin = null): ?array
    {
        $p = self::deMantenimiento($mt);
        if (!$p) return null;
        $fecha = substr($fechaFin ?: date('Y-m-d'), 0, 10);
        $proxima = self::proximaDesde($fecha, (string)$p['frecuencia']);

        Database::update('mantenimientos_programados', [
            'ultima_ejecucion'        => $fecha,
            'proxima_fecha'           => $proxima,
            'mantenimiento_actual_id' => null,
            'ultimo_mantenimiento_id' => $mt['id'],
            'aviso_para'              => null,
        ], 'id = ?', [$p['id']]);
        Database::insert('equipo_historial', [
            'equipo_id' => $mt['equipo_id'], 'usuario_id' => Auth::userId(), 'evento' => 'mantenimiento',
            'detalle'   => "MT {$mt['codigo']}: preventivo {$p['frecuencia']} realizado el $fecha · próximo programado: $proxima",
        ]);
        return ['programado_id' => (int)$p['id'], 'frecuencia' => $p['frecuencia'],
                'ultima' => $fecha, 'proxima' => $proxima, 'activo' => (int)$p['activo'] === 1];
    }

    /**
     * El mantenimiento se CANCELO o resulto NO REPARABLE: la programacion no se cumplio.
     * Cancelado -> se reintenta en DIAS_REINTENTO dias. No reparable -> se pausa.
     */
    public static function alCancelar(array $mt, bool $pausar = false): ?array
    {
        if (empty($mt['programado_id'])) return null;
        $p = Database::getOne('SELECT * FROM mantenimientos_programados WHERE id = ?', [$mt['programado_id']]);
        if (!$p || (int)$p['mantenimiento_actual_id'] !== (int)$mt['id']) return null;

        $datos = ['mantenimiento_actual_id' => null, 'aviso_para' => null];
        $reintento = null;
        if ($pausar) {
            $datos['activo'] = 0;
        } else {
            $reintento = date('Y-m-d', strtotime('+' . self::DIAS_REINTENTO . ' days'));
            if ($reintento > (string)$p['proxima_fecha']) $datos['proxima_fecha'] = $reintento;
            else $reintento = (string)$p['proxima_fecha'];
        }
        Database::update('mantenimientos_programados', $datos, 'id = ?', [$p['id']]);
        Database::insert('equipo_historial', [
            'equipo_id' => $mt['equipo_id'], 'usuario_id' => Auth::userId(), 'evento' => 'mantenimiento',
            'detalle'   => "MT {$mt['codigo']}: el preventivo programado NO se realizó · "
                . ($pausar ? 'programación pausada' : "se reintentará el $reintento"),
        ]);
        return ['programado_id' => (int)$p['id'], 'pausada' => $pausar, 'reintento' => $reintento];
    }

    /** El mantenimiento se ELIMINO: libera el vinculo (la fecha queda como estaba y el motor lo regenera). */
    public static function alEliminar(array $mt): void
    {
        if (!empty($mt['programado_id'])) {
            Database::update('mantenimientos_programados', ['mantenimiento_actual_id' => null],
                'id = ? AND mantenimiento_actual_id = ?', [$mt['programado_id'], $mt['id']]);
        }
        Database::update('mantenimientos_programados', ['ultimo_mantenimiento_id' => null],
            'ultimo_mantenimiento_id = ?', [$mt['id']]);
    }

    /** Datos de la programacion para mostrar en el detalle de un mantenimiento (o null). */
    public static function infoParaMt(array $mt): ?array
    {
        $terminal = in_array($mt['estado'] ?? '', MantenimientoFlujo::TERMINALES, true);
        if ($terminal && empty($mt['programado_id'])) return null;   // un mantenimiento viejo hecho a mano no muestra la programacion actual
        $p = self::deMantenimiento($mt);
        if (!$p) return null;
        return [
            'id'              => (int)$p['id'],
            'frecuencia'      => $p['frecuencia'],
            'proxima_fecha'   => $p['proxima_fecha'],
            'ultima_ejecucion' => $p['ultima_ejecucion'],
            'activo'          => (int)$p['activo'] === 1,
            'vinculado'       => !empty($mt['programado_id']),
            // si se finaliza hoy, la siguiente quedaria asi
            'proxima_si_finaliza' => $terminal ? null : self::proximaDesde(date('Y-m-d'), (string)$p['frecuencia']),
        ];
    }

    /**
     * Aviso previo: a quien es responsable (y un resumen a quienes ven mantenimiento) cuando faltan
     * DIAS_AVISO dias o menos. Una sola vez por fecha (aviso_para). Nunca lanza. Devuelve cuantos.
     */
    public static function avisarProximos(?string $hoy = null): int
    {
        try {
            self::asegurar();
            $hoy = $hoy ?: date('Y-m-d');
            $hasta = date('Y-m-d', strtotime($hoy . ' +' . self::DIAS_AVISO . ' days'));
            $filas = Database::get(
                "SELECT mp.*, e.codigo AS eq_codigo
                 FROM mantenimientos_programados mp
                 INNER JOIN equipos e ON e.id = mp.equipo_id
                 WHERE mp.activo = 1 AND mp.mantenimiento_actual_id IS NULL
                   AND mp.proxima_fecha >= ? AND mp.proxima_fecha <= ?
                   AND (mp.aviso_para IS NULL OR mp.aviso_para <> mp.proxima_fecha)
                   AND e.estado <> 'dado_de_baja'
                 ORDER BY mp.proxima_fecha ASC", [$hoy, $hasta]);
            if (!$filas) return 0;

            $resumen = [];
            foreach ($filas as $p) {
                $d = self::dias((string)$p['proxima_fecha'], $hoy);
                $cuando = $d === 0 ? 'hoy' : ($d === 1 ? 'mañana' : "en $d días");
                if (!empty($p['responsable_id'])) {
                    Notificador::avisar((int)$p['responsable_id'], 'mantenimiento',
                        'Preventivo próximo: ' . $p['eq_codigo'],
                        ucfirst($p['tipo']) . ' ' . $p['frecuencia'] . ' programado ' . $cuando
                        . ' (' . $p['proxima_fecha'] . ').', 'mantenimiento/lista');
                }
                $resumen[] = $p['eq_codigo'] . ' (' . $p['proxima_fecha'] . ')';
                Database::update('mantenimientos_programados', ['aviso_para' => $p['proxima_fecha']], 'id = ?', [$p['id']]);
            }
            Notificador::avisarPermiso('mantenimiento', 'ver', 'mantenimiento',
                'Preventivos próximos: ' . count($filas),
                implode(' · ', array_slice($resumen, 0, 8)), 'mantenimiento/lista');
            return count($filas);
        } catch (Throwable $e) {
            error_log('[SIGTI] avisarProximos: ' . $e->getMessage());
            return 0;
        }
    }
}
