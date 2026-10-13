<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Destino de un equipo de TI: PERSONA, AREA, PROYECTO u OBRA
//
// Cada asignacion guarda a quien (o a donde) fue el equipo:
//   persona  -> asignaciones.personal_id
//   area     -> asignaciones.area_id          (+ responsable que recibe)
//   proyecto -> asignaciones.proyecto_id      (+ responsable que recibe)
//   obra     -> asignaciones.proyecto_id      (+ responsable que recibe)
// Proyectos y obras viven en UNA tabla (proyectos) con el campo "tipo".
//
// Todo se crea solo la primera vez (tabla y columnas), como el resto
// de ayudantes del sistema: no hace falta ejecutar nada a mano.
// ============================================================

class Destinos
{
    public const TIPOS = ['persona' => 'Persona', 'area' => 'Área', 'proyecto' => 'Proyecto', 'obra' => 'Obra'];

    // Une los datos del destino a "asignaciones a". Alias fijos: dp, da, dy, dr.
    public const SQL_JOIN =
        ' LEFT JOIN personal dp ON dp.id = a.personal_id
          LEFT JOIN areas da ON da.id = a.area_id
          LEFT JOIN proyectos dy ON dy.id = a.proyecto_id
          LEFT JOIN personal dr ON dr.id = a.responsable_id ';

    // Nombre del destino, listo para mostrar u ordenar.
    public const SQL_NOMBRE =
        "CASE WHEN a.proyecto_id IS NOT NULL THEN dy.nombre
              WHEN a.personal_id IS NOT NULL THEN CONCAT(dp.nombres, ' ', dp.apellidos)
              ELSE da.nombre END";

    private static bool $ok = false;

    /** Crea la tabla proyectos y las columnas nuevas de asignaciones/solicitudes si faltan. */
    public static function asegurar(): void
    {
        if (self::$ok) return;

        try {
            Database::run('SELECT id FROM proyectos LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS proyectos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(20) NOT NULL,
                nombre VARCHAR(120) NOT NULL,
                tipo VARCHAR(10) NOT NULL DEFAULT 'proyecto',
                cliente VARCHAR(120) NULL,
                ubicacion VARCHAR(150) NULL,
                responsable_id INT UNSIGNED NULL,
                fecha_inicio DATE NULL,
                fecha_fin DATE NULL,
                estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
                creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_proyecto_codigo (codigo),
                KEY idx_proyecto_tipo (tipo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        $faltaTipo = false;
        try {
            Database::run('SELECT destino_tipo FROM asignaciones LIMIT 0');
        } catch (Throwable $e) {
            $faltaTipo = true;
        }
        Esquema::columna('asignaciones', 'destino_tipo', "VARCHAR(10) NOT NULL DEFAULT 'persona'");
        Esquema::columna('asignaciones', 'proyecto_id', 'INT UNSIGNED NULL');
        Esquema::columna('asignaciones', 'responsable_id', 'INT UNSIGNED NULL');
        Esquema::columna('asignaciones', 'destino_detalle', 'VARCHAR(150) NULL');
        Esquema::columna('asignaciones', 'aviso_dev', 'TINYINT NOT NULL DEFAULT 0');     // 0 nada · 1 aviso previo · 2 aviso de vencida
        Esquema::columna('proyectos', 'descripcion', 'VARCHAR(250) NULL');
        Esquema::columna('proyectos', 'fecha_cierre', 'DATE NULL');
        Esquema::columna('solicitudes_equipo', 'proyecto_id', 'INT UNSIGNED NULL');
        Esquema::columna('solicitudes_equipo', 'entregados', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');

        if ($faltaTipo) {
            // las entregas antiguas a un area (personal_id vacio) quedan como destino "area"
            Database::run("UPDATE asignaciones SET destino_tipo = 'area'
                           WHERE personal_id IS NULL AND area_id IS NOT NULL");
            // en las de persona, el responsable es la propia persona
            Database::run("UPDATE asignaciones SET responsable_id = personal_id
                           WHERE personal_id IS NOT NULL AND responsable_id IS NULL");
        }
        self::$ok = true;
    }

    /** Dias antes de la devolucion estipulada en que se avisa. */
    public const DIAS_AVISO_DEV = 3;

    /** El destino es un proyecto o una obra (los equipos se envian por un tiempo estipulado). */
    public static function esProyecto(string $tipo): bool
    {
        return $tipo === 'proyecto' || $tipo === 'obra';
    }

    /**
     * Situacion de un proyecto / obra segun sus fechas: finalizado | por_iniciar | vencido | por_vencer | en_curso.
     * «vencido» = paso la fecha de fin y sigue abierto.
     */
    public static function situacion(array $p, ?string $hoy = null): string
    {
        $hoy = $hoy ?: date('Y-m-d');
        if (($p['estado'] ?? 'activo') !== 'activo') return 'finalizado';
        $ini = !empty($p['fecha_inicio']) ? substr((string)$p['fecha_inicio'], 0, 10) : null;
        $fin = !empty($p['fecha_fin']) ? substr((string)$p['fecha_fin'], 0, 10) : null;
        if ($ini && $ini > $hoy) return 'por_iniciar';
        if ($fin && $fin < $hoy) return 'vencido';
        if ($fin && $fin <= date('Y-m-d', strtotime($hoy . ' +7 days'))) return 'por_vencer';
        return 'en_curso';
    }

    /** Dias que faltan para una fecha (negativo = ya paso). */
    public static function dias(string $fecha, ?string $hoy = null): int
    {
        $hoy = $hoy ?: date('Y-m-d');
        return (int)round((strtotime(substr($fecha, 0, 10)) - strtotime($hoy)) / 86400);
    }

    /**
     * La devolucion estipulada de un equipo enviado a un proyecto / obra debe caer dentro de su plazo.
     * $p necesita nombre, tipo, fecha_inicio y fecha_fin. Devuelve el mensaje de error o null si esta bien.
     */
    public static function errorPlazo(array $p, string $retorno): ?string
    {
        $que = (($p['tipo'] ?? '') === 'obra') ? 'la obra' : 'el proyecto';
        $fin = !empty($p['fecha_fin']) ? substr((string)$p['fecha_fin'], 0, 10) : '';
        $ini = !empty($p['fecha_inicio']) ? substr((string)$p['fecha_inicio'], 0, 10) : '';
        if ($fin !== '' && $retorno > $fin) {
            return 'La devolución (' . $retorno . ') supera el fin de ' . $que . ' «' . $p['nombre'] . '» (' . $fin
                . '). Elija una fecha hasta el ' . $fin . ' o amplíe el plazo en Proyectos y Obras.';
        }
        if ($ini !== '' && $retorno < $ini) {
            return 'La devolución (' . $retorno . ') es anterior al inicio de ' . $que . ' «' . $p['nombre'] . '» (' . $ini . ').';
        }
        return null;
    }

    /**
     * Aviso de devolucion: a quien recibio el equipo (responsable) y un resumen a quienes registran
     * devoluciones. Dos avisos por asignacion: DIAS_AVISO_DEV dias antes y cuando ya vencio.
     * Nunca lanza. Devuelve cuantos avisos nuevos se generaron.
     */
    public static function avisarDevoluciones(?string $hoy = null): int
    {
        try {
            self::asegurar();
            $hoy = $hoy ?: date('Y-m-d');
            $hasta = date('Y-m-d', strtotime($hoy . ' +' . self::DIAS_AVISO_DEV . ' days'));
            $filas = Database::get(
                "SELECT a.id, a.aviso_dev, a.fecha_devolucion_esperada AS fecha, a.responsable_id,
                        e.codigo AS eq, dy.nombre AS destino, dy.codigo AS dcod
                 FROM asignaciones a
                 INNER JOIN equipos e ON e.id = a.equipo_id
                 INNER JOIN proyectos dy ON dy.id = a.proyecto_id
                 WHERE a.estado IN ('activa','vencida') AND a.fecha_devolucion_esperada IS NOT NULL
                   AND a.fecha_devolucion_esperada <= ?
                 ORDER BY a.fecha_devolucion_esperada ASC", [$hasta]);
            $n = 0;
            $resumen = [];
            foreach ($filas as $f) {
                $fecha = substr((string)$f['fecha'], 0, 10);
                $vencida = $fecha < $hoy;
                $nivel = $vencida ? 2 : 1;
                if ((int)$f['aviso_dev'] >= $nivel) continue;
                $d = self::dias($fecha, $hoy);
                $cuando = $vencida ? 'VENCIDA hace ' . abs($d) . ' d' : ($d === 0 ? 'vence hoy' : ($d === 1 ? 'vence mañana' : "vence en $d d"));
                if (!empty($f['responsable_id'])) {
                    try {
                        $u = Database::getValue("SELECT id FROM usuarios WHERE personal_id = ? AND estado = 'activo'", [(int)$f['responsable_id']]);
                        if ($u) {
                            Notificador::avisar((int)$u, 'sistema', 'Devolución de equipo: ' . $f['eq'],
                                'El equipo ' . $f['eq'] . ' enviado a ' . $f['dcod'] . ' · ' . $f['destino'] . ' ' . $cuando . ' (' . $fecha . ').',
                                'proyectos/lista');
                        }
                    } catch (Throwable $e) {
                        error_log('[SIGTI] avisarDevoluciones/responsable: ' . $e->getMessage());
                    }
                }
                $resumen[] = $f['eq'] . ' → ' . $f['dcod'] . ' (' . $fecha . ')';
                Database::update('asignaciones', ['aviso_dev' => $nivel], 'id = ?', [$f['id']]);
                $n++;
            }
            if ($resumen) {
                Notificador::avisarPermiso('asignaciones', 'devolver', 'sistema',
                    'Devoluciones de equipos de proyectos/obras: ' . count($resumen),
                    implode(' · ', array_slice($resumen, 0, 8)), 'proyectos/lista');
            }
            return $n;
        } catch (Throwable $e) {
            error_log('[SIGTI] avisarDevoluciones: ' . $e->getMessage());
            return 0;
        }
    }

    public static function nombreTipo(string $tipo): string
    {
        return self::TIPOS[$tipo] ?? $tipo;
    }

    /** Codigo nuevo: PRY-001 / OBR-001 (el siguiente libre del tipo). */
    /**
     * Un codigo de proyecto/obra no debe poder confundirse con el codigo de un colaborador (AREA-0001):
     * se rechaza el formato PREFIJO-0000 (4 digitos) y cualquier codigo que ya tenga una persona.
     */
    public static function errorCodigo(string $codigo): ?string
    {
        if ($codigo === '') return null;
        if (preg_match('/^[A-Z0-9]{2,6}-\d{4}$/', $codigo)) {
            return "El formato $codigo (letras-4 digitos) es el de los codigos del personal. Use PRY-001 / OBR-001 o un nombre propio.";
        }
        try {
            if (Database::getValue('SELECT id FROM personal WHERE codigo_usuario = ?', [$codigo])) {
                return "El codigo $codigo ya lo tiene un colaborador.";
            }
        } catch (Throwable $e) { /* sin columna: no hay codigos de personal */ }
        return null;
    }

    public static function codigoNuevo(string $tipo): string
    {
        $pre = $tipo === 'obra' ? 'OBR' : 'PRY';
        $max = 0;
        foreach (Database::get('SELECT codigo FROM proyectos WHERE codigo LIKE ?', [$pre . '-%']) as $f) {
            if (preg_match('/^' . $pre . '-(\d+)$/', (string)$f['codigo'], $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
        return sprintf('%s-%03d', $pre, $max + 1);
    }

    /** Texto corto para equipos.ubicacion (maximo 100 caracteres). */
    public static function ubicacionTexto(array $d): string
    {
        switch ($d['tipo']) {
            case 'persona':  $t = 'Con ' . $d['nombre']; break;
            case 'area':     $t = 'Área: ' . $d['nombre']; break;
            case 'obra':     $t = 'Obra: ' . $d['nombre']; break;
            default:         $t = 'Proyecto: ' . $d['nombre'];
        }
        return mb_substr($t, 0, 100);
    }

    /** Texto para historial / bitacora: "Obra OBR-001 · Torre Norte (resp. Ana Perez)". */
    public static function descripcion(array $d): string
    {
        if ($d['tipo'] === 'persona') return $d['nombre'];
        $t = self::nombreTipo($d['tipo']) . ' ' . ($d['codigo'] ? $d['codigo'] . ' · ' : '') . $d['nombre'];
        if (!empty($d['responsable']['nombre'])) $t .= ' (resp. ' . $d['responsable']['nombre'] . ')';
        return $t;
    }

    /** ¿La asignacion (fila de asignaciones) corresponde a este destino? */
    public static function mismo(array $asig, array $d): bool
    {
        switch ($d['tipo']) {
            case 'persona': return (int)$asig['personal_id'] === (int)$d['personal_id'];
            case 'area':    return $asig['personal_id'] === null && (int)$asig['area_id'] === (int)$d['area_id'];
            default:        return (int)($asig['proyecto_id'] ?? 0) === (int)$d['proyecto_id'];
        }
    }

    /**
     * Lee y valida el destino que llega por POST. Si algo falla responde 422 y termina.
     * Devuelve: tipo, personal_id, area_id, proyecto_id, nombre, codigo, detalle,
     *           ubicacion, cliente, responsable (fila de personal + nombre).
     */
    public static function resolver(array $in): array
    {
        self::asegurar();

        $tipo = (string)($in['destino'] ?? 'persona');
        if (!isset(self::TIPOS[$tipo])) {
            Response::validation(['destino' => 'Destino invalido: use persona, area, proyecto u obra.']);
        }

        $d = ['tipo' => $tipo, 'personal_id' => null, 'area_id' => null, 'proyecto_id' => null,
              'nombre' => '', 'codigo' => '', 'detalle' => null, 'ubicacion' => '', 'cliente' => '',
              'fecha_inicio' => null, 'fecha_fin' => null, 'responsable' => null];

        if ($tipo === 'persona') {
            $p = self::personal((int)($in['personal_id'] ?? 0));
            if (!$p) Response::validation(['personal_id' => 'Seleccione el colaborador.']);
            self::exigirVigente($p, 'personal_id');
            $d['personal_id'] = (int)$p['id'];
            $d['nombre'] = $p['nombre'];
            $d['responsable'] = $p;
            return $d;
        }

        $respId = (int)($in['responsable_id'] ?? 0);

        if ($tipo === 'area') {
            $ar = Database::getOne('SELECT id, nombre, estado FROM areas WHERE id = ?', [(int)($in['area_id'] ?? 0)]);
            if (!$ar) Response::validation(['area_id' => 'Seleccione el area.']);
            if ($ar['estado'] !== 'activo') Response::validation(['area_id' => 'El area esta inactiva.']);
            $d['area_id'] = (int)$ar['id'];
            $d['nombre'] = $ar['nombre'];
        } else {
            $py = Database::getOne('SELECT * FROM proyectos WHERE id = ?', [(int)($in['proyecto_id'] ?? 0)]);
            $que = $tipo === 'obra' ? 'la obra' : 'el proyecto';
            if (!$py) Response::validation(['proyecto_id' => "Seleccione $que."]);
            if ($py['tipo'] !== $tipo) {
                Response::validation(['proyecto_id' => 'El registro elegido es ' . ($py['tipo'] === 'obra' ? 'una obra' : 'un proyecto') . '.']);
            }
            if ($py['estado'] !== 'activo') {
                Response::validation(['proyecto_id' => ucfirst($que) . ' «' . $py['nombre'] . '» esta inactivo: no recibe equipos.']);
            }
            $d['proyecto_id'] = (int)$py['id'];
            $d['nombre'] = $py['nombre'];
            $d['codigo'] = (string)$py['codigo'];
            $d['ubicacion'] = (string)($py['ubicacion'] ?? '');
            $d['cliente'] = (string)($py['cliente'] ?? '');
            $d['fecha_inicio'] = $py['fecha_inicio'] ?: null;
            $d['fecha_fin'] = $py['fecha_fin'] ?: null;
            if ($respId === 0) $respId = (int)($py['responsable_id'] ?? 0);   // por defecto, el del proyecto
        }

        $resp = self::personal($respId);
        if (!$resp) {
            Response::validation(['responsable_id' => 'Indique el responsable que recibe y custodia los equipos.']);
        }
        self::exigirVigente($resp, 'responsable_id');
        $d['responsable'] = $resp;

        $det = trim((string)($in['destino_detalle'] ?? ''));
        $d['detalle'] = $det !== '' ? mb_substr($det, 0, 150) : null;
        return $d;
    }

    /** Persona con su area y nombre armado, o null. */
    public static function personal(int $id): ?array
    {
        if ($id <= 0) return null;
        $p = Database::getOne(
            'SELECT p.*, a.nombre AS area FROM personal p LEFT JOIN areas a ON a.id = p.area_id WHERE p.id = ?', [$id]);
        if (!$p) return null;
        $p['nombre'] = trim($p['nombres'] . ' ' . $p['apellidos']);
        return $p;
    }

    private static function exigirVigente(array $p, string $campo): void
    {
        if (in_array($p['estado'], ['cesado', 'en_proceso_cese'], true)) {
            Response::validation([$campo => $p['nombre'] . ' esta cesado: no puede recibir equipos.']);
        }
    }

    /** Datos del destino de una asignacion ya guardada (para mostrar). */
    public static function deAsignacion(int $asigId): ?array
    {
        self::asegurar();
        return Database::getOne(
            "SELECT a.id, a.destino_tipo, a.personal_id, a.area_id, a.proyecto_id, a.responsable_id, a.destino_detalle,
                    " . self::SQL_NOMBRE . " AS destino_nombre,
                    dy.codigo AS destino_codigo, dy.ubicacion AS destino_ubicacion, dy.cliente AS destino_cliente,
                    CONCAT(dr.nombres, ' ', dr.apellidos) AS responsable
             FROM asignaciones a " . self::SQL_JOIN . " WHERE a.id = ?", [$asigId]) ?: null;
    }
}
