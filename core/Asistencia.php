<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH.: Asistencia del personal (gestion interna; sin validez tributaria/legal)
// Se registra por DIA (no es un reloj biometrico): RR.HH. o el jefe marca a cada persona activa
// Normal / Tardanza / Falta / Falta justificada / Permiso. Un registro por persona y por dia.
// Las tablas se crean solas la primera vez que se abre el modulo.
// ============================================================

class Asistencia
{
    public const TIPOS = [
        'normal' => ['Normal', 'verde'], 'tardanza' => ['Tardanza', 'ambar'], 'falta' => ['Falta', 'rojo'],
        'falta_justificada' => ['Falta justificada', 'azul'], 'permiso' => ['Permiso', 'azul'],
        'vacaciones' => ['Vacaciones', 'azul'],
    ];

    /** Horario referencial: [hora de entrada, hora de salida, inicio almuerzo, fin almuerzo]. Solo informativo por ahora,
     *  salvo la hora de entrada que se usa para calcular Normal/Tardanza (ver calcularTipoEntrada). */
    public const HORARIO = [
        'semana' => ['entrada' => '08:00', 'salida' => '17:00', 'almuerzo_inicio' => '13:00', 'almuerzo_fin' => '14:00'], // lunes a viernes
        'sabado' => ['entrada' => '08:00', 'salida' => '13:00', 'almuerzo_inicio' => null, 'almuerzo_fin' => null],       // jornada continua, sin almuerzo
    ];

    /** Tolerancia de ingreso en minutos sobre la hora de entrada (8:00 u 8:15 cuentan como Normal). */
    public const TOLERANCIA_ENTRADA_MIN = 15;

    private static bool $ok = false;

    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT id FROM rrhh_asistencia LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_asistencia (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                personal_id     INT UNSIGNED NOT NULL,
                fecha           DATE NOT NULL,
                tipo            VARCHAR(20) NOT NULL DEFAULT 'normal',
                hora_entrada    VARCHAR(5) NULL,
                observacion     VARCHAR(200) NULL,
                registrado_por  INT UNSIGNED NULL,
                registrado_en   DATETIME NOT NULL,
                UNIQUE KEY uq_persona_fecha (personal_id, fecha)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        Esquema::columna('rrhh_asistencia', 'origen', "VARCHAR(20) NULL DEFAULT 'manual'");
        Esquema::columna('rrhh_asistencia', 'hora_salida_almuerzo', 'VARCHAR(5) NULL');
        Esquema::columna('rrhh_asistencia', 'hora_entrada_almuerzo', 'VARCHAR(5) NULL');
        Esquema::columna('rrhh_asistencia', 'hora_salida', 'VARCHAR(5) NULL');
        if (class_exists('Vacaciones')) Vacaciones::asegurar(); // dia()/resumenHoy()/resumenPersona() cruzan con vacaciones aprobadas
        self::$ok = true;
    }

    /**
     * Normal o Tardanza segun la hora de entrada marcada, con la tolerancia de ingreso (8:00 u 8:15 = Normal).
     * Misma tolerancia de lunes a sabado (la hora de entrada es la misma; solo cambia la hora de salida del sabado).
     */
    public static function calcularTipoEntrada(?string $horaEntrada): string
    {
        if (!$horaEntrada) return 'normal';
        $base = self::HORARIO['semana']['entrada'];
        $limite = date('H:i', strtotime($base) + self::TOLERANCIA_ENTRADA_MIN * 60);
        return ($horaEntrada <= $limite) ? 'normal' : 'tardanza';
    }

    /**
     * Personal activo (de un area, opcional) con su marca de ese dia (null si aun no se marco).
     * Si la persona tiene una solicitud de vacaciones APROBADA que cubre ese dia y no tiene marca manual,
     * se devuelve ya como 'vacaciones' (automatico): no hace falta que nadie la marque a mano.
     */
    public static function dia(string $fecha, ?int $areaId = null): array
    {
        $w = ''; $params = [$fecha, $fecha];
        if ($areaId) { $w = ' AND p.area_id = ?'; $params[] = $areaId; }
        $filas = Database::get(
            "SELECT p.id AS personal_id, p.nombres, p.apellidos, p.dni, a.nombre AS area,
                    r.id AS reg_id, r.tipo, r.hora_entrada, r.hora_salida_almuerzo, r.hora_entrada_almuerzo, r.hora_salida,
                    r.observacion, r.origen,
                    v.id AS vacacion_id
             FROM personal p LEFT JOIN areas a ON a.id = p.area_id
             LEFT JOIN rrhh_asistencia r ON r.personal_id = p.id AND r.fecha = ?
             LEFT JOIN rrhh_vacaciones v ON v.personal_id = p.id AND v.estado = 'aprobada' AND ? BETWEEN v.fecha_inicio AND v.fecha_fin
             WHERE p.estado = 'activo'$w ORDER BY p.apellidos, p.nombres",
            $params
        );
        foreach ($filas as &$f) {
            $f['automatico'] = !$f['tipo'] && $f['vacacion_id'] ? true : false;
            $f['tipo_efectivo'] = $f['tipo'] ?: ($f['vacacion_id'] ? 'vacaciones' : null);
        }
        unset($f);
        return $filas;
    }

    public static function registrarDia(string $fecha, array $filas, int $uid): int
    {
        $n = 0;
        foreach ($filas as $f) {
            $pid = (int) ($f['personal_id'] ?? 0);
            if (!$pid) continue;
            $tipo = in_array($f['tipo'] ?? '', array_keys(self::TIPOS), true) ? $f['tipo'] : 'normal';
            $datos = [
                'tipo' => $tipo, 'hora_entrada' => $f['hora_entrada'] ?: null,
                'hora_salida_almuerzo' => ($f['hora_salida_almuerzo'] ?? null) ?: null,
                'hora_entrada_almuerzo' => ($f['hora_entrada_almuerzo'] ?? null) ?: null,
                'hora_salida' => ($f['hora_salida'] ?? null) ?: null,
                'observacion' => $f['observacion'] ?: null,
                'registrado_por' => $uid, 'registrado_en' => date('Y-m-d H:i:s'), 'origen' => 'manual',
            ];
            $ex = Database::getValue('SELECT id FROM rrhh_asistencia WHERE personal_id = ? AND fecha = ?', [$pid, $fecha]);
            if ($ex) Database::update('rrhh_asistencia', $datos, 'id = ?', [$ex]);
            else Database::insert('rrhh_asistencia', $datos + ['personal_id' => $pid, 'fecha' => $fecha]);
            $n++;
        }
        return $n;
    }

    /** Resumen de una persona en un rango: cuenta la marca explicita, o 'vacaciones' automatico si no hay marca y tiene vacaciones aprobadas ese dia. */
    public static function resumenPersona(int $personalId, string $desde, string $hasta): array
    {
        $marcas = Database::get('SELECT fecha, tipo FROM rrhh_asistencia WHERE personal_id = ? AND fecha BETWEEN ? AND ?', [$personalId, $desde, $hasta]);
        $mapa = [];
        foreach ($marcas as $m) $mapa[$m['fecha']] = $m['tipo'];

        $vac = Database::get(
            "SELECT fecha_inicio, fecha_fin FROM rrhh_vacaciones WHERE personal_id = ? AND estado = 'aprobada' AND fecha_fin >= ? AND fecha_inicio <= ?",
            [$personalId, $desde, $hasta]
        );

        $out = ['normal' => 0, 'tardanza' => 0, 'falta' => 0, 'falta_justificada' => 0, 'permiso' => 0, 'vacaciones' => 0];
        $cur = new DateTime($desde);
        $fin = new DateTime($hasta);
        while ($cur <= $fin) {
            $f = $cur->format('Y-m-d');
            $tipo = $mapa[$f] ?? null;
            if (!$tipo) {
                foreach ($vac as $v) {
                    if ($f >= $v['fecha_inicio'] && $f <= $v['fecha_fin']) { $tipo = 'vacaciones'; break; }
                }
            }
            if ($tipo && isset($out[$tipo])) $out[$tipo]++;
            $cur->modify('+1 day');
        }
        return $out;
    }

    public static function resumenHoy(): array
    {
        $h = date('Y-m-d');
        $filas = Database::get(
            "SELECT p.id,
                    CASE WHEN r.tipo IS NOT NULL THEN r.tipo WHEN v.id IS NOT NULL THEN 'vacaciones' ELSE NULL END AS tipo
             FROM personal p
             LEFT JOIN rrhh_asistencia r ON r.personal_id = p.id AND r.fecha = ?
             LEFT JOIN rrhh_vacaciones v ON v.personal_id = p.id AND v.estado = 'aprobada' AND ? BETWEEN v.fecha_inicio AND v.fecha_fin
             WHERE p.estado = 'activo'",
            [$h, $h]
        );
        $out = ['normal' => 0, 'tardanza' => 0, 'falta' => 0, 'falta_justificada' => 0, 'permiso' => 0, 'vacaciones' => 0];
        $marcados = 0;
        foreach ($filas as $f) {
            if ($f['tipo']) { $out[$f['tipo']] = ($out[$f['tipo']] ?? 0) + 1; $marcados++; }
        }
        $out['sin_marcar'] = max(0, count($filas) - $marcados);
        return $out;
    }

    // =================== IMPORTAR DESDE EXCEL / CSV (marcador de huella u otro) ===================

    /** Columnas esperadas (encabezados flexibles: sin tildes, mayus/minus no importa). Solo DNI y Fecha son obligatorias. */
    public const COLUMNAS_IMPORTAR = ['dni', 'fecha', 'hora_entrada', 'hora_salida_almuerzo', 'hora_entrada_almuerzo', 'hora_salida', 'tipo', 'observacion'];

    /**
     * Procesa filas ya leidas (ImportadorArchivo::leer) e inserta/actualiza rrhh_asistencia.
     * Cada fila es un arreglo asociativo con claves normalizadas (dni, fecha, hora, tipo, observacion, ...).
     * Devuelve ['ok' => filas guardadas, 'errores' => [texto por cada fila con problema]].
     */
    public static function importarFilas(array $filas, int $uid): array
    {
        $ok = 0; $errores = [];
        foreach ($filas as $i => $f) {
            $nFila = $i + 2; // +1 indice 0-based, +1 por la fila de encabezado
            $dni = trim((string) ($f['dni'] ?? $f['documento'] ?? $f['dni_o_codigo'] ?? ''));
            if ($dni === '') { $errores[] = "Fila $nFila: falta el DNI."; continue; }

            $fecha = self::normalizarFecha((string) ($f['fecha'] ?? ''));
            if (!$fecha) { $errores[] = "Fila $nFila: la fecha está vacía o no se reconoce."; continue; }
            if ($fecha > date('Y-m-d')) { $errores[] = "Fila $nFila: la fecha ($fecha) es futura, se omite."; continue; }

            $pid = Database::getValue('SELECT id FROM personal WHERE dni = ?', [$dni]);
            if (!$pid) { $errores[] = "Fila $nFila: no hay ninguna persona con DNI $dni."; continue; }

            $hora = self::normalizarHora((string) ($f['hora_entrada'] ?? $f['hora'] ?? ''));
            $horaSalAlm = self::normalizarHora((string) ($f['hora_salida_almuerzo'] ?? $f['salida_almuerzo'] ?? ''));
            $horaEntAlm = self::normalizarHora((string) ($f['hora_entrada_almuerzo'] ?? $f['regreso_almuerzo'] ?? $f['entrada_almuerzo'] ?? ''));
            $horaSal = self::normalizarHora((string) ($f['hora_salida'] ?? $f['salida'] ?? ''));

            $tipoCol = trim((string) ($f['tipo'] ?? ''));
            // Si el archivo no trae el tipo (caso tipico del marcador de huella, que solo exporta la hora), se calcula
            // solo segun la hora de entrada y la tolerancia de ingreso; si trae un tipo explicito, se respeta tal cual.
            $tipo = $tipoCol !== '' ? self::mapearTipo($tipoCol) : self::calcularTipoEntrada($hora);
            $datos = [
                'tipo' => $tipo, 'hora_entrada' => $hora,
                'hora_salida_almuerzo' => $horaSalAlm, 'hora_entrada_almuerzo' => $horaEntAlm, 'hora_salida' => $horaSal,
                'observacion' => trim((string) ($f['observacion'] ?? $f['observaciones'] ?? '')) ?: null,
                'registrado_por' => $uid, 'registrado_en' => date('Y-m-d H:i:s'), 'origen' => 'excel',
            ];
            $ex = Database::getValue('SELECT id FROM rrhh_asistencia WHERE personal_id = ? AND fecha = ?', [$pid, $fecha]);
            if ($ex) Database::update('rrhh_asistencia', $datos, 'id = ?', [$ex]);
            else Database::insert('rrhh_asistencia', $datos + ['personal_id' => $pid, 'fecha' => $fecha]);
            $ok++;
        }
        return ['ok' => $ok, 'errores' => $errores];
    }

    private static function mapearTipo(string $t): string
    {
        $t = str_replace(' ', '_', mb_strtolower(trim($t)));
        if ($t === '') return 'normal';
        $map = [
            'normal' => 'normal', 'asistio' => 'normal', 'asistencia' => 'normal', 'presente' => 'normal', 'entrada' => 'normal',
            'tardanza' => 'tardanza', 'tarde' => 'tardanza',
            'falta' => 'falta', 'ausente' => 'falta', 'ausencia' => 'falta',
            'falta_justificada' => 'falta_justificada', 'justificada' => 'falta_justificada',
            'permiso' => 'permiso', 'vacaciones' => 'vacaciones', 'vacacion' => 'vacaciones',
        ];
        return $map[$t] ?? (array_key_exists($t, self::TIPOS) ? $t : 'normal');
    }

    /** Acepta YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, o un numero de serie de fecha de Excel. */
    private static function normalizarFecha(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
        if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})#', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        if (preg_match('/^\d+(\.\d+)?$/', $s)) {
            $n = (float) $s;
            if ($n > 20000 && $n < 60000) { // fecha de Excel (dias desde 1899-12-30), rango aprox. 1954-2064
                return gmdate('Y-m-d', (int) round(($n - 25569) * 86400));
            }
        }
        return null;
    }

    /** Acepta HH:MM, HH:MM:SS, o una fraccion de dia de Excel (0..1). */
    private static function normalizarHora(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') return null;
        if (preg_match('/^(\d{1,2}):(\d{2})/', $s, $m)) return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        if (preg_match('/^0?\.\d+$/', $s)) {
            $mins = (int) round(((float) $s) * 24 * 60);
            return sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
        }
        return null;
    }
}
