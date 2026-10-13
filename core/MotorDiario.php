<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - MOTOR DIARIO AUTONOMO DE PREVENTIVOS
// Se ejecuta solo, una vez al dia, desde bootstrap.php
// Reglas:
//   1. Calendario: mantenimientos_programados vencidos
//   2. Vida util: equipo que supero los meses de uso
//      (tipo_equipos.vida_util_meses, ej. 65 meses; respaldo: anos*12)
//   3. Fallas tecnicas recurrentes: 2+ correctivos en los ultimos
//      6 meses -> evaluacion de reparacion vs. reemplazo
// Nunca interrumpe la operacion (todo en try/catch).
// ============================================================

class MotorDiario
{
    private static function sentinel(): string
    {
        return STORAGE_PATH . '/.motor_dia';
    }

    public static function ultimaEjecucion(): ?string
    {
        $f = self::sentinel();
        return is_file($f) ? (string) file_get_contents($f) : null;
    }

    /** Segundos de espera antes de reintentar si la ejecucion fallo. */
    private const ESPERA_TRAS_FALLO = 1800;

    private static function archivoFallo(): string
    {
        return STORAGE_PATH . '/.motor_fallo';
    }

    private static function ultimoFallo(): ?int
    {
        $f = self::archivoFallo();
        return is_file($f) ? (int) file_get_contents($f) : null;
    }

    /**
     * Corre solo si hoy aun no corre. Devuelve generados o null si ya corrio.
     * - Bloqueo (flock): si varias peticiones llegan a la vez, solo UNA ejecuta
     *   el motor; las demas salen sin hacer nada (evita mantenimientos duplicados).
     * - La marca de "ya corrio hoy" se escribe solo si termino bien.
     * - Si falla, no se reintenta en cada peticion: espera ESPERA_TRAS_FALLO s.
     */
    public static function ejecutarSiToca(): ?array
    {
        $fp = null;
        try {
            $hoy = date('Y-m-d');
            if (self::ultimaEjecucion() === $hoy) return null;      // camino rapido
            // sin licencia vigente (solo lectura) o con Mantenimiento apagado no se crean registros solos
            if (class_exists('Licencia') && (Licencia::soloLectura() || !Licencia::moduloActivo('mantenimiento'))) return null;

            $fallo = self::ultimoFallo();
            if ($fallo !== null && (time() - $fallo) < self::ESPERA_TRAS_FALLO) return null;

            $fp = @fopen(STORAGE_PATH . '/.motor_lock', 'c');
            if (!$fp) return null;
            if (!flock($fp, LOCK_EX | LOCK_NB)) return null;        // otro proceso lo esta ejecutando

            // doble verificacion ya dentro del bloqueo
            if (trim((string) self::ultimaEjecucion()) === $hoy) return null;

            $generados = self::ejecutar();
            self::avisarCumpleanos();   // nunca lanza: tiene su propio try/catch
            Programados::avisarProximos();   // aviso previo de preventivos; nunca lanza
            Destinos::avisarDevoluciones();  // devoluciones de equipos en proyectos/obras; nunca lanza
            @file_put_contents(self::sentinel(), $hoy);
            @unlink(self::archivoFallo());
            return $generados;
        } catch (Throwable $e) {
            error_log('[SIGTI] MotorDiario: ' . $e->getMessage());
            @file_put_contents(self::archivoFallo(), (string) time());
            return null;
        } finally {
            if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
        }
    }

    /** Dry-run: que generaria hoy (sin escribir). */
    public static function estado(): array
    {
        Programados::asegurar();
        $porCalendario = Database::get(
            "SELECT mp.id, mp.proxima_fecha, mp.frecuencia, mp.tipo,
                    e.codigo, e.marca, t.nombre AS tipo_eq,
                    CONCAT(u.nombre_completo,'') AS responsable
             FROM mantenimientos_programados mp
             INNER JOIN equipos e ON e.id = mp.equipo_id
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             LEFT JOIN usuarios u ON u.id = mp.responsable_id
             WHERE mp.activo = 1 AND mp.mantenimiento_actual_id IS NULL AND mp.proxima_fecha <= ?",
            [date('Y-m-d')]);

        $porVida = Database::get(
            "SELECT e.codigo, e.marca, t.nombre AS tipo_eq, e.fecha_compra,
                    COALESCE(t.vida_util_meses, t.vida_util_anos * 12) AS vida_meses,
                    DATE_ADD(e.fecha_compra,
                        INTERVAL COALESCE(t.vida_util_meses, t.vida_util_anos * 12) MONTH) AS fin_vida
             FROM equipos e
             INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
             WHERE e.fecha_compra IS NOT NULL
               AND e.estado NOT IN ('dado_de_baja','en_mantenimiento','en_reparacion_externa')
               AND COALESCE(t.vida_util_meses, t.vida_util_anos * 12) IS NOT NULL
               AND DATE_ADD(e.fecha_compra,
                        INTERVAL COALESCE(t.vida_util_meses, t.vida_util_anos * 12) MONTH) <= CURDATE()");

        $porFallas = self::equiposConFallas();

        return [
            'por_calendario'  => $porCalendario,
            'por_vida'        => $porVida,
            'por_fallas'      => $porFallas,
            'generaria'       => count($porCalendario) + count($porVida) + count($porFallas),
            'ultima_ejecucion' => self::ultimaEjecucion(),
        ];
    }

    /** Equipos con 2+ mantenimientos correctivos en los ultimos 6 meses, sin evaluacion abierta. */
    private static function equiposConFallas(): array
    {
        return Database::get(
            "SELECT e.id, e.codigo, e.marca, COUNT(*) AS fallas
             FROM mantenimientos m
             INNER JOIN equipos e ON e.id = m.equipo_id
             WHERE m.tipo = 'correctivo'
               AND m.fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
               AND e.estado NOT IN ('dado_de_baja')
               AND NOT EXISTS (
                   SELECT 1 FROM mantenimientos m2
                   WHERE m2.equipo_id = e.id
                     AND m2.tipo = 'actualizacion'
                     AND m2.origen = 'programado'
                     AND m2.estado IN ('solicitado','en_evaluacion'))
             GROUP BY e.id, e.codigo, e.marca
             HAVING fallas >= 2
             ORDER BY fallas DESC");
    }

    /**
     * Cumpleanos de HOY: avisa (campana de notificaciones) a los usuarios que gestionan
     * personal (super admin + permiso personal.editar) y felicita a la propia persona si
     * tiene cuenta. No duplica: si ya se aviso hoy por esa persona, no repite.
     * Se llama desde el motor diario y tambien puede llamarse a mano. Devuelve cuantas personas.
     */
    public static function avisarCumpleanos(): int
    {
        try {
            Esquema::columna('personal', 'fecha_nacimiento', 'DATE NULL');
            $hoy = new DateTimeImmutable('today');

            $personas = Database::get(
                "SELECT p.id, p.nombres, p.apellidos, p.cargo, p.fecha_nacimiento, a.nombre AS area
                 FROM personal p
                 LEFT JOIN areas a ON a.id = p.area_id
                 WHERE p.fecha_nacimiento IS NOT NULL
                   AND p.estado IN ('activo','cese_programado','en_proceso_cese')");
            $cumplen = array_values(array_filter($personas,
                fn($p) => Cumpleanos::esHoy((string)$p['fecha_nacimiento'], $hoy)));
            if (!$cumplen) return 0;

            // quienes gestionan personal (no se excluye a nadie: el que dispara el motor tambien debe enterarse)
            $gestores = Database::get(
                "SELECT u.id FROM usuarios u
                 WHERE u.estado = 'activo'
                   AND (u.rol_id = 1 OR EXISTS (
                        SELECT 1 FROM rol_permisos rp
                        INNER JOIN permisos pe ON pe.id = rp.permiso_id
                        WHERE rp.rol_id = u.rol_id AND pe.modulo = 'personal' AND pe.accion = 'editar'))");

            $yaAvisado = fn(int $dest, string $titulo) => (bool) Database::getValue(
                "SELECT id FROM notificaciones
                 WHERE usuario_destino_id = ? AND titulo = ? AND created_at >= ?",
                [$dest, $titulo, $hoy->format('Y-m-d') . ' 00:00:00']);

            foreach ($cumplen as $p) {
                $nombre = trim($p['nombres'] . ' ' . $p['apellidos']);
                $edad   = Cumpleanos::edad((string)$p['fecha_nacimiento'], $hoy);
                $titulo = mb_substr('Cumpleaños hoy: ' . $nombre, 0, 120);   // igual que Notificador (evita repetir el aviso)
                $msg    = 'Hoy cumple ' . $edad . ' años'
                        . ($p['area'] ? ' · Área: ' . $p['area'] : '')
                        . ($p['cargo'] ? ' · ' . $p['cargo'] : '');

                foreach ($gestores as $g) {
                    if (!$yaAvisado((int)$g['id'], $titulo)) {
                        Notificador::avisar((int)$g['id'], 'sistema', $titulo, $msg, 'personal/lista');
                    }
                }

                // felicitacion a la propia persona (si tiene cuenta activa)
                $cuentas = Database::get(
                    "SELECT id FROM usuarios WHERE personal_id = ? AND estado = 'activo'", [(int)$p['id']]);
                foreach ($cuentas as $c) {
                    $t2 = '¡Feliz cumpleaños, ' . $p['nombres'] . '!';
                    if (!$yaAvisado((int)$c['id'], $t2)) {
                        Notificador::avisar((int)$c['id'], 'sistema', $t2,
                            'El equipo te desea un excelente día.', null);
                    }
                }
            }
            return count($cumplen);
        } catch (Throwable $e) {
            error_log('[SIGTI] avisarCumpleanos: ' . $e->getMessage());
            return 0;
        }
    }

    /** Ejecuta las 3 reglas y genera los mantenimientos. */
    public static function ejecutar(): array
    {
        $generados = [];
        Programados::asegurar();      // DDL antes de abrir la transaccion

        Database::begin();
        try {
            // ---- 1. Calendario ----
            // Genera lo que ya llego a su fecha y no tiene un mantenimiento en curso. La proxima fecha
            // NO se mueve aqui: se calcula sola cuando el mantenimiento se FINALIZA (core/Programados.php).
            $generados = array_merge($generados, Programados::generarVencidos());

            // ---- 2. Vida util vencida (meses de uso) ----
            $vidaVencida = Database::get(
                "SELECT e.id, e.codigo,
                        COALESCE(t.vida_util_meses, t.vida_util_anos * 12) AS vida_meses
                 FROM equipos e
                 INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
                 WHERE e.fecha_compra IS NOT NULL
                   AND e.estado IN ('en_stock','asignado','en_prestamo','obsoleto')
                   AND COALESCE(t.vida_util_meses, t.vida_util_anos * 12) IS NOT NULL
                   AND DATE_ADD(e.fecha_compra,
                            INTERVAL COALESCE(t.vida_util_meses, t.vida_util_anos * 12) MONTH) <= CURDATE()");

            foreach ($vidaVencida as $v) {
                $enCurso = Database::getValue(
                    "SELECT id FROM mantenimientos WHERE equipo_id = ? AND estado IN ('solicitado','en_evaluacion')",
                    [$v['id']]);
                if ($enCurso) continue;
                $codigo = Correlativo::generar('mantenimiento');
                Database::insert('mantenimientos', [
                    'codigo' => $codigo,
                    'equipo_id' => $v['id'],
                    'tipo' => 'actualizacion',
                    'origen' => 'programado',
                    'estado' => 'solicitado',
                    'diagnostico' => 'EVALUACION DE RENOVACION - tiempo de uso cumplido (' .
                        $v['vida_meses'] . ' meses). Evaluar reemplazo vs. reparacion.',
                    'fecha_inicio' => date('Y-m-d H:i:s'),
                ]);
                $generados[] = ['codigo' => $codigo, 'equipo' => $v['codigo'], 'origen' => 'vida_util'];
            }

            // ---- 3. Fallas tecnicas recurrentes ----
            foreach (self::equiposConFallas() as $f) {
                $codigo = Correlativo::generar('mantenimiento');
                Database::insert('mantenimientos', [
                    'codigo' => $codigo,
                    'equipo_id' => $f['id'],
                    'tipo' => 'actualizacion',
                    'origen' => 'programado',
                    'estado' => 'solicitado',
                    'diagnostico' => 'FALLAS TECNICAS RECURRENTES - ' . $f['fallas'] .
                        ' correctivos en los ultimos 6 meses. Evaluar reparacion vs. reemplazo.',
                    'fecha_inicio' => date('Y-m-d H:i:s'),
                ]);
                $generados[] = ['codigo' => $codigo, 'equipo' => $f['codigo'], 'origen' => 'fallas'];
            }

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }

        if ($generados) {
            try {
                Auditoria::registrar('crear', 'mantenimientos', null, null,
                    ['motor_auto' => count($generados) . ' preventivos']);
                $det = [];
                foreach (array_slice($generados, 0, 8) as $g) {
                    $det[] = $g['codigo'] . ' (' . $g['equipo'] . ')';
                }
                foreach ($generados as $g) {
                    if (!empty($g['responsable_id'])) {
                        Notificador::avisar((int)$g['responsable_id'], 'mantenimiento',
                            'Preventivo asignado: ' . $g['equipo'],
                            'Se generó el mantenimiento ' . $g['codigo'] . ' (programado). Revíselo en Mantenimiento.',
                            'mantenimiento/lista');
                    }
                }
                Notificador::avisarPermiso('mantenimiento', 'ver', 'mantenimiento',
                    'Mantenimientos automáticos generados: ' . count($generados),
                    implode(' · ', $det) . ' — generados por el motor diario.',
                    'mantenimiento/lista');
            } catch (Throwable $e) { /* auditoria/notificacion no bloquean */ }
        }
        return $generados;
    }
}
