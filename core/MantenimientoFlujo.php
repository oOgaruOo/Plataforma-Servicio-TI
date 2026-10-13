<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Flujo de estados del mantenimiento (una sola fuente)
// La usan estado.php, finalizar.php y detalle.php; antes cada
// archivo tenia su propia copia de la tabla de transiciones.
// ============================================================

class MantenimientoFlujo
{
    public const FLUJO = [
        'solicitado'        => ['en_evaluacion', 'cancelado'],
        'en_evaluacion'     => ['en_proceso', 'enviado_proveedor', 'no_reparable', 'cancelado'],
        'en_proceso'        => ['listo', 'no_reparable', 'cancelado'],
        // desde cualquier punto «con el proveedor» se puede REVERTIR (anular el envio) y seguir en Sistemas
        'enviado_proveedor' => ['cotizado', 'cancelado', 'en_evaluacion', 'en_proceso'],
        'cotizado'          => ['aprobado', 'rechazado', 'cancelado', 'en_evaluacion', 'en_proceso'],
        'aprobado'          => ['en_reparacion', 'cancelado', 'en_evaluacion', 'en_proceso'],
        'rechazado'         => ['en_evaluacion', 'cancelado', 'en_proceso'],
        'en_reparacion'     => ['recibido_reparado', 'no_reparable', 'cancelado', 'en_evaluacion', 'en_proceso'],
        'recibido_reparado' => ['listo'],
        'listo'             => ['entregado', 'devuelto_stock'],
        'entregado'         => ['cerrado'],
        'devuelto_stock'    => ['cerrado'],
        'cerrado'           => [],
        'cancelado'         => [],
        'no_reparable'      => [],
    ];

    /** Estados sin trabajo pendiente (el equipo ya salio del taller). */
    public const SIN_TRABAJO = ['cerrado', 'cancelado', 'no_reparable', 'entregado', 'devuelto_stock'];

    /** Estados que ya no admiten ningun cambio. */
    public const TERMINALES = ['cerrado', 'cancelado', 'no_reparable'];

    /**
     * Desde donde se puede "Finalizar" de un solo paso y por que estados intermedios pasa.
     * No incluye enviado_proveedor / cotizado / aprobado: ahi falta la cotizacion o la
     * aprobacion del costo y no deben saltarse.
     */
    public const RUTA_FINALIZAR = [
        'solicitado'        => ['en_evaluacion', 'en_proceso', 'listo'],
        'en_evaluacion'     => ['en_proceso', 'listo'],
        'en_proceso'        => ['listo'],
        'en_reparacion'     => ['recibido_reparado', 'listo'],
        'recibido_reparado' => ['listo'],
        'listo'             => [],
        'entregado'         => [],
        'devuelto_stock'    => [],
    ];

    /** Como termino el trabajo. */
    public const RESULTADOS = [
        'completado' => 'Completado',
        'parcial'    => 'Completado con observaciones',
    ];

    /** Contratiempos que pueden retrasar un mantenimiento. */
    public const PERCANCES = [
        'falta_repuesto'        => 'Falta de repuesto',
        'demora_proveedor'      => 'Demora del proveedor',
        'dano_adicional'        => 'Daño adicional en el equipo',
        'sin_aprobacion'        => 'Demora en la aprobación del costo',
        'usuario_no_disponible' => 'Usuario o equipo no disponible',
        'energia_red'           => 'Corte de energía o de red',
        'otro'                  => 'Otro',
    ];

    private static bool $ok = false;

    /**
     * Crea (una sola vez) lo que necesita el cierre: resultado, quien finalizo, atraso y su motivo,
     * y el registro de percances. Debe llamarse ANTES de Database::begin() (el DDL cierra la transaccion).
     */
    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT resultado_final, finalizado_por, atraso_min, motivo_atraso FROM mantenimientos LIMIT 0');
        } catch (Throwable $e) {
            Esquema::columna('mantenimientos', 'resultado_final', 'VARCHAR(15) NULL');
            Esquema::columna('mantenimientos', 'finalizado_por', 'INT UNSIGNED NULL');
            Esquema::columna('mantenimientos', 'atraso_min', 'INT UNSIGNED NULL');
            Esquema::columna('mantenimientos', 'motivo_atraso', 'VARCHAR(250) NULL');
        }
        try {
            Database::run('SELECT id FROM mantenimiento_percances LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS mantenimiento_percances (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                mantenimiento_id INT UNSIGNED NOT NULL,
                tipo VARCHAR(30) NOT NULL,
                detalle VARCHAR(250) NOT NULL,
                usuario_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_percance_mt (mantenimiento_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        self::$ok = true;
    }

    /**
     * Plazo comprometido: la fecha de retorno estimada si lo hace un proveedor,
     * si no el limite interno (SLA) fijado al registrar. null si no hay ninguno.
     */
    public static function plazo(array $m): ?array
    {
        if (!empty($m['proveedor_id']) && !empty($m['fecha_retorno_estimada'])) {
            return ['limite' => substr((string)$m['fecha_retorno_estimada'], 0, 10) . ' 23:59:59', 'origen' => 'retorno'];
        }
        if (!empty($m['sla_limite'])) return ['limite' => (string)$m['sla_limite'], 'origen' => 'sla'];
        return null;
    }

    /** Minutos de atraso respecto del plazo (0 si va a tiempo o no hay plazo). */
    public static function atrasoMin(array $m, ?int $ahora = null): int
    {
        $p = self::plazo($m);
        if (!$p) return 0;
        $limite = strtotime($p['limite']);
        if ($limite === false) return 0;
        $ref = $ahora ?? time();
        return $ref > $limite ? (int)ceil(($ref - $limite) / 60) : 0;
    }

    public static function textoAtraso(int $min): string
    {
        if ($min < 60) return $min . ' min';
        if ($min < 1440) return floor($min / 60) . ' h ' . ($min % 60 ? ($min % 60) . ' min' : '');
        $d = (int)floor($min / 1440); $h = (int)floor(($min % 1440) / 60);
        return $d . ' d' . ($h ? ' ' . $h . ' h' : '');
    }

    /**
     * Campos de cierre. Si el mantenimiento esta atrasado exige el motivo del atraso (422 si falta).
     * Hay que llamarlo ya con MantenimientoFlujo::asegurar() hecho.
     */
    public static function datosCierre(array $m, string $resultado, string $motivoAtraso): array
    {
        if (!isset(self::RESULTADOS[$resultado])) $resultado = 'completado';
        $atraso = self::atrasoMin($m);
        $motivoAtraso = trim($motivoAtraso);
        if ($atraso > 0 && $motivoAtraso === '') {
            Response::validation(['motivo_atraso' => 'Este mantenimiento terminó con ' . self::textoAtraso($atraso)
                . ' de atraso sobre el plazo. Indique por qué (por ejemplo el percance ocurrido).']);
        }
        return [
            'resultado_final' => $resultado,
            'finalizado_por'  => Auth::userId(),
            'atraso_min'      => $atraso,
            'motivo_atraso'   => $atraso > 0 ? mb_substr($motivoAtraso, 0, 250) : null,
        ];
    }

    public static function transiciones(string $estado): array
    {
        return self::FLUJO[$estado] ?? [];
    }

    public static function finalizable(string $estado): bool
    {
        return isset(self::RUTA_FINALIZAR[$estado]);
    }

    /** Estados en los que el envio al proveedor sigue vigente (se puede anular y atender en Sistemas). */
    public const CON_PROVEEDOR = ['enviado_proveedor', 'cotizado', 'aprobado', 'rechazado', 'en_reparacion'];

    /** Estados en los que el equipo esta con el proveedor o esperando la cotizacion / aprobacion del costo. */
    public const ESPERA_PROVEEDOR = ['enviado_proveedor', 'cotizado', 'aprobado', 'rechazado'];

    /** Estados del ticket que significan «ya se atendio». */
    public const TICKET_ATENDIDO = ['resuelto', 'cerrado'];

    /**
     * Igual que finalizable(), pero si el TICKET vinculado ya fue resuelto o cerrado tambien se puede
     * finalizar un mantenimiento que sigue «con el proveedor» (antes el equipo quedaba para siempre en
     * «Reparacion externa» aunque el ticket ya estuviera cerrado).
     */
    public static function finalizableConTicket(string $estado, ?string $ticketEstado): bool
    {
        if (self::finalizable($estado)) return true;
        return in_array($estado, self::ESPERA_PROVEEDOR, true)
            && in_array((string)$ticketEstado, self::TICKET_ATENDIDO, true);
    }

    /** Pasos que se recorren al finalizar (solo informativos: el cierre es un unico paso). */
    public static function rutaFinalizar(string $estado): array
    {
        return self::RUTA_FINALIZAR[$estado] ?? ['en_reparacion', 'recibido_reparado', 'listo'];
    }

    /** Mantenimientos de un ticket que siguen abiertos (el equipo aun no salio del taller / proveedor). */
    public static function abiertosDeTicket(int $ticketId): array
    {
        return Database::get(
            "SELECT m.id, m.codigo, m.estado, m.equipo_id, e.codigo AS equipo_codigo, e.estado AS equipo_estado
             FROM mantenimientos m
             INNER JOIN equipos e ON e.id = m.equipo_id
             WHERE m.ticket_id = ? AND m.estado NOT IN ('cerrado','cancelado','no_reparable','entregado','devuelto_stock')
             ORDER BY m.id ASC", [$ticketId]);
    }

    /**
     * Estado en el que debe quedar el equipo al salir de mantenimiento:
     * si tiene una asignacion vigente vuelve a su destino (persona, area, proyecto u obra),
     * si no, a stock. Devuelve [estado_equipo, asignacion|null].
     */
    public static function estadoAlVolver(int $equipoId): array
    {
        $asig = Database::getOne(
            "SELECT id, tipo FROM asignaciones WHERE equipo_id = ? AND estado IN ('activa','vencida')
             ORDER BY id DESC LIMIT 1", [$equipoId]);
        if (!$asig) return ['en_stock', null];
        return [$asig['tipo'] === 'prestamo' ? 'en_prestamo' : 'asignado', $asig];
    }

    /** Texto "Obra X" / "Persona Y" / "stock" para mensajes y para la pantalla. */
    public static function destinoTexto(int $equipoId): string
    {
        [$estado, $asig] = self::estadoAlVolver($equipoId);
        if (!$asig) return 'stock';
        try {
            Destinos::asegurar();
            $d = Destinos::deAsignacion((int)$asig['id']);
            if ($d) {
                $tipo = $d['destino_tipo'] ?: 'persona';
                $t = $tipo === 'persona' ? (string)$d['destino_nombre']
                    : Destinos::nombreTipo($tipo) . ' ' . ($d['destino_codigo'] ? $d['destino_codigo'] . ' · ' : '') . $d['destino_nombre'];
                if ($tipo !== 'persona' && $d['responsable']) $t .= ' (resp. ' . $d['responsable'] . ')';
                return trim($t) !== '' ? $t : 'su destino';
            }
        } catch (Throwable $e) {
            // sin datos de destino: se informa solo el estado
        }
        return $estado === 'en_prestamo' ? 'su usuario (préstamo)' : 'su usuario';
    }
}
