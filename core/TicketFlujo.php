<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Flujo de estados del ticket y datos de FINALIZACION
// Unica fuente de la maquina de estados (antes estaba duplicada
// en estado.php y detalle.php).
// ============================================================
class TicketFlujo
{
    public const FLUJO = [
        'abierto'             => ['asignado', 'cancelado'],
        'asignado'            => ['en_atencion', 'pendiente_proveedor', 'cancelado'],
        'en_atencion'         => ['pendiente_usuario', 'pendiente_proveedor', 'resuelto'],
        'pendiente_usuario'   => ['en_atencion', 'resuelto', 'cancelado'],
        'pendiente_proveedor' => ['en_atencion', 'resuelto', 'cancelado'],
        'resuelto'            => ['cerrado', 'en_atencion'],
        'cerrado'             => ['en_atencion'],
        'cancelado'           => [],
    ];

    /** Estados desde los que el tecnico puede dar el ticket por terminado con el boton «Finalizar». */
    public const FINALIZABLES = ['abierto', 'asignado', 'en_atencion', 'pendiente_usuario', 'pendiente_proveedor'];

    /** Como se resolvio. */
    public const TIPOS_SOLUCION = [
        'remoto'       => 'Resuelto a distancia (remoto)',
        'en_sitio'     => 'Resuelto en sitio',
        'configuracion'=> 'Configuración o ajuste',
        'reparacion'   => 'Reparación o cambio de equipo / repuesto',
        'orientacion'  => 'Orientación al usuario',
        'proveedor'    => 'Resuelto por un proveedor',
        'no_procede'   => 'No procede / duplicado / ya resuelto',
    ];

    private static bool $ok = false;

    /** Crea (una sola vez) las columnas del cierre. Llamar ANTES de Database::begin(). */
    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT tipo_solucion, tiempo_invertido_min, resuelto_por, cerrado_por, motivo_retraso FROM tickets LIMIT 0');
        } catch (Throwable $e) {
            Esquema::columna('tickets', 'tipo_solucion', 'VARCHAR(30) NULL');
            Esquema::columna('tickets', 'tiempo_invertido_min', 'INT UNSIGNED NULL');
            Esquema::columna('tickets', 'resuelto_por', 'INT UNSIGNED NULL');
            Esquema::columna('tickets', 'cerrado_por', 'INT UNSIGNED NULL');
            Esquema::columna('tickets', 'motivo_retraso', 'VARCHAR(250) NULL');
        }
        self::$ok = true;
    }

    public static function transiciones(string $estado): array
    {
        return self::FLUJO[$estado] ?? [];
    }

    public static function finalizable(string $estado): bool
    {
        return in_array($estado, self::FINALIZABLES, true);
    }

    /** Minutos de atraso de $ref (timestamp) respecto del SLA; 0 si va a tiempo o no hay SLA. */
    public static function atrasoMin(array $t, ?int $ref = null): int
    {
        if (empty($t['sla_limite'])) return 0;
        $limite = strtotime((string)$t['sla_limite']);
        if ($limite === false) return 0;
        $ref = $ref ?? time();
        return $ref > $limite ? (int)ceil(($ref - $limite) / 60) : 0;
    }

    public static function texto(int $min): string
    {
        if ($min < 60) return $min . ' min';
        if ($min < 1440) return floor($min / 60) . ' h' . ($min % 60 ? ' ' . ($min % 60) . ' min' : '');
        $d = (int)floor($min / 1440); $h = (int)floor(($min % 1440) / 60);
        return $d . ' d' . ($h ? ' ' . $h . ' h' : '');
    }

    /** Guarda los archivos de $_FILES['evidencias'] como adjuntos del ticket; devuelve cuantos se guardaron. */
    public static function guardarEvidencias(int $ticketId): int
    {
        if (empty($_FILES['evidencias']['name'][0])) return 0;
        $n = count($_FILES['evidencias']['name']);
        $guardadas = 0;
        for ($i = 0; $i < $n; $i++) {
            $file = [
                'name'     => $_FILES['evidencias']['name'][$i],
                'type'     => $_FILES['evidencias']['type'][$i],
                'tmp_name' => $_FILES['evidencias']['tmp_name'][$i],
                'error'    => $_FILES['evidencias']['error'][$i],
                'size'     => $_FILES['evidencias']['size'][$i],
            ];
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
            try {
                $up = Upload::guardar($file, 'adjuntos-tickets');
                Database::insert('ticket_adjuntos', [
                    'ticket_id' => $ticketId, 'usuario_id' => Auth::userId(),
                    'archivo' => $up['archivo'], 'nombre_original' => $up['nombre_original'],
                    'tamano_kb' => $up['tamano_kb'],
                ]);
                $guardadas++;
            } catch (Throwable $e) {
                error_log('[SIGTI] Evidencia fallida: ' . $e->getMessage());
            }
        }
        if ($guardadas > 0) {
            Database::insert('ticket_historial', [
                'ticket_id' => $ticketId, 'usuario_id' => Auth::userId(),
                'evento' => 'evidencia',
                'detalle' => $guardadas . ' evidencia(s) adjuntada(s) a la solución',
            ]);
        }
        return $guardadas;
    }
}
