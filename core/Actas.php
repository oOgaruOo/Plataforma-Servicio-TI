<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Actas: esquema, datos del equipo y alta del acta
//
// Una acta guarda TODO su contenido en actas.contenido (JSON) para que
// el documento no cambie aunque despues cambie el equipo o la persona.
// contenido v2 (version actual):
//   v, tipo (entrega|devolucion|cambio), formato_codigo, familia,
//   colaborador{nombre,dni,cargo,area,jefe,fecha_cese},
//   destino{tipo,tipo_texto,nombre,codigo,ubicacion,cliente,detalle},
//   modalidad{tipo:permanente|prestamo, retorno},
//   motivo, motivo_texto,
//   equipos_entregados[], equipos_devueltos[]   (ver Actas::equipo)
//   verificacion_accesos[], registros[], observaciones, fecha, fecha_formal
//
// Las actas anteriores (sin "v") se siguen mostrando con su formato original.
// Las columnas nuevas y los datos antiguos se completan solos la primera vez.
// ============================================================

class Actas
{
    private static bool $ok = false;
    private const ESQ = 2;                 // sube cuando cambia la estructura (se revisa 1 vez por sesion)

    public const VERSION = 2;

    /** Tipos de equipo conocidos -> familia (solo si la columna familia se crea ahora). */
    private const FAMILIA_POR_NOMBRE = [
        'Laptop' => 'computo', 'PC de escritorio' => 'computo', 'Servidor' => 'computo',
        'Celular' => 'movil', 'Tablet' => 'movil',
        'Impresora' => 'impresion', 'Escáner' => 'impresion',
        'Monitor' => 'visual', 'Proyector' => 'visual',
        'Router / Switch' => 'red', 'Access Point' => 'red',
        'UPS' => 'energia', 'Periférico' => 'periferico',
    ];

    public static function asegurar(): void
    {
        if (self::$ok) return;
        if (isset($_SESSION) && ($_SESSION['actas_esq'] ?? 0) === self::ESQ) { self::$ok = true; return; }

        // tipo_equipos.familia (las instalaciones nuevas desde sigti.sql no la traian)
        $faltaFamilia = false;
        try { Database::run('SELECT familia FROM tipo_equipos LIMIT 0'); } catch (Throwable $e) { $faltaFamilia = true; }
        if ($faltaFamilia) {
            Esquema::columna('tipo_equipos', 'familia', "VARCHAR(20) NOT NULL DEFAULT 'otro'");
            foreach (self::FAMILIA_POR_NOMBRE as $nombre => $fam) {
                Database::run('UPDATE tipo_equipos SET familia = ? WHERE nombre = ?', [$fam, $nombre]);
            }
        }

        Esquema::columna('actas', 'token', 'VARCHAR(64) NULL');
        $faltaResumen = false;
        try { Database::run('SELECT equipos_resumen FROM actas LIMIT 0'); } catch (Throwable $e) { $faltaResumen = true; }
        Esquema::columna('actas', 'familia', 'VARCHAR(20) NULL');
        Esquema::columna('actas', 'destino_tipo', 'VARCHAR(10) NULL');
        Esquema::columna('actas', 'destino_nombre', 'VARCHAR(150) NULL');
        Esquema::columna('actas', 'equipos_resumen', 'VARCHAR(300) NULL');
        Esquema::columna('actas', 'firmado_en', 'DATETIME NULL');
        Esquema::columna('actas', 'firmado_por', 'INT UNSIGNED NULL');
        // Archivo del acta firmada (escaneo o foto, PDF o imagen)
        Esquema::columna('actas', 'archivo_firmado', 'VARCHAR(120) NULL');
        Esquema::columna('actas', 'archivo_nombre', 'VARCHAR(200) NULL');
        Esquema::columna('actas', 'archivo_kb', 'INT UNSIGNED NULL');
        Esquema::columna('actas', 'archivo_subido_en', 'DATETIME NULL');
        Esquema::columna('actas', 'archivo_subido_por', 'INT UNSIGNED NULL');

        // actas.tipo era ENUM(entrega, cambio, devolucion): los tipos propios (tipo_N) no cabian
        try {
            $c = Database::getOne("SHOW COLUMNS FROM actas LIKE 'tipo'");
            if (strpos(strtolower((string)($c['Type'] ?? '')), 'enum') === 0) {
                Database::run("ALTER TABLE actas MODIFY tipo VARCHAR(20) NOT NULL");
            }
        } catch (Throwable $e) { /* SQLite u otro: nada que hacer */ }

        // configuracion: codigos de formato por defecto (no pisa los que ya existan)
        foreach ([
            'acta_formato_entrega'       => 'TI-FOR-003',
            'acta_formato_entrega_movil' => 'TI-FOR-004',
            'acta_formato_cambio'        => 'TI-FOR-002',
            'acta_formato_devolucion'    => 'TI-FOR-001',
        ] as $clave => $valor) {
            try {
                if (Database::getValue('SELECT 1 FROM configuracion WHERE clave = ?', [$clave]) === null) {
                    Database::insert('configuracion', ['clave' => $clave, 'valor' => $valor,
                                                       'descripcion' => 'Código del formato de acta']);
                }
            } catch (Throwable $e) { /* sin tabla configuracion: se usan los valores por defecto del codigo */ }
        }

        self::completarAntiguas();

        self::$ok = true;
        if (isset($_SESSION)) $_SESSION['actas_esq'] = self::ESQ;
    }

    /**
     * Rellena el resumen (familia, destino, equipos) de las actas antiguas y vincula cada acta con su
     * asignacion para que «Generar acta» no ofrezca como pendientes movimientos que ya tienen acta.
     */
    private static function completarAntiguas(): void
    {
        try {
            $filas = Database::get(
                'SELECT id, tipo, contenido, personal_id, asignacion_id, fecha FROM actas
                 WHERE equipos_resumen IS NULL ORDER BY id LIMIT 1000');
        } catch (Throwable $e) {
            return;
        }
        foreach ($filas as $f) {
            $c = json_decode((string)$f['contenido'], true);
            if (!is_array($c)) $c = [];
            $col = $c['colaborador']['nombre'] ?? '';
            Database::update('actas', [
                'familia'         => self::familiaDe($c) ?: null,
                'destino_tipo'    => $c['destino']['tipo'] ?? ($col !== '' ? 'persona' : null),
                'destino_nombre'  => mb_substr(self::destinoTexto($c), 0, 150) ?: null,
                'equipos_resumen' => self::resumen($c),
            ], 'id = ?', [(int)$f['id']]);

            // vinculos con las asignaciones (solo si todavia no estaban)
            try {
                $t = strtotime((string)$f['fecha']) ?: time();
                $desde = date('Y-m-d H:i:s', $t - 300);
                $hasta = date('Y-m-d H:i:s', $t + 300);
                if (!empty($f['asignacion_id'])) {
                    Database::run('UPDATE asignaciones SET acta_entrega_id = ? WHERE id = ? AND acta_entrega_id IS NULL',
                        [(int)$f['id'], (int)$f['asignacion_id']]);
                }
                foreach (($c['equipos_devueltos'] ?? []) as $eq) {
                    $eid = (int)($eq['id'] ?? 0);
                    if ($eid <= 0) continue;
                    Database::run(
                        "UPDATE asignaciones SET acta_devolucion_id = ?
                         WHERE id = (SELECT id FROM (SELECT id FROM asignaciones
                                     WHERE equipo_id = ? AND estado = 'devuelta' AND acta_devolucion_id IS NULL
                                       AND fecha_devolucion_real IS NOT NULL
                                       AND fecha_devolucion_real BETWEEN ? AND ?
                                     ORDER BY id DESC LIMIT 1) z)",
                        [(int)$f['id'], $eid, $desde, $hasta]);
                }
            } catch (Throwable $e) { /* el vinculo es opcional */ }
        }
    }

    // ---------------------------------------------------------
    // Configuracion y formatos
    // ---------------------------------------------------------

    public static function cfg(string $clave, string $def = ''): string
    {
        try {
            $v = Database::getValue('SELECT valor FROM configuracion WHERE clave = ?', [$clave]);
        } catch (Throwable $e) {
            return $def;
        }
        return ($v === null || $v === '') ? $def : (string)$v;
    }

    public static function empresa(): string
    {
        return Empresa::nombre();
    }

    /** Codigo del formato base (el de siempre) de un movimiento. */
    public static function formatoBase(string $mov, ?string $familia): string
    {
        if ($mov === 'cambio')     return self::cfg('acta_formato_cambio', 'TI-FOR-002');
        if ($mov === 'devolucion') return self::cfg('acta_formato_devolucion', 'TI-FOR-001');
        return $familia === 'movil'
            ? self::cfg('acta_formato_entrega_movil', 'TI-FOR-004')
            : self::cfg('acta_formato_entrega', 'TI-FOR-003');
    }

    /** Codigo del formato: el propio de esa familia si se configuro, y si no el base del movimiento. */
    public static function formato(string $mov, ?string $familia): string
    {
        $fam = ActaPerfiles::claveFamilia($familia);
        $propio = self::cfg("acta_formato_{$mov}_{$fam}");
        return $propio !== '' ? $propio : self::formatoBase($mov, $fam);
    }

    /** Guarda (o quita, si $valor viene vacio) el codigo propio de una familia para un movimiento. */
    public static function guardarFormato(string $mov, string $familia, string $valor): void
    {
        if (!isset(ActaPerfiles::MOVIMIENTOS[$mov]) || !ActaPerfiles::existe($familia)) return;
        $clave = "acta_formato_{$mov}_{$familia}";
        $existe = Database::getValue('SELECT 1 FROM configuracion WHERE clave = ?', [$clave]) !== null;
        if ($valor === '') {
            if ($existe) Database::delete('configuracion', 'clave = ?', [$clave]);
            return;
        }
        if ($existe) Database::update('configuracion', ['valor' => $valor], 'clave = ?', [$clave]);
        else Database::insert('configuracion', ['clave' => $clave, 'valor' => $valor,
                                                'descripcion' => "Formato de acta de $mov ($familia)"]);
    }

    // ---------------------------------------------------------
    // Datos del equipo dentro del acta
    // ---------------------------------------------------------

    /**
     * Foto del equipo para el acta. $e = fila de equipos + tipo (nombre) + familia.
     * $dev: el equipo se esta recibiendo. $x: condicion, obs, faltantes, checks.
     */
    public static function equipo(array $e, bool $dev = false, array $x = []): array
    {
        $acc = Database::get('SELECT nombre FROM equipo_accesorios WHERE equipo_id = ? ORDER BY id', [$e['id']]);
        $specs = !empty($e['especificaciones']) ? (json_decode((string)$e['especificaciones'], true) ?: []) : [];
        $fam = ActaPerfiles::claveFamilia($e['familia'] ?? 'otro');
        $cond = (string)($e['condicion'] ?? '');
        $condDev = (string)($x['condicion'] ?? '');
        return [
            'id' => (int)$e['id'], 'codigo' => $e['codigo'], 'tipo' => $e['tipo'] ?? ($e['tipo_eq'] ?? ''),
            'familia' => $fam, 'marca' => $e['marca'] ?? '', 'modelo' => $e['modelo'] ?: '',
            'serie' => $e['nro_serie'] ?: '', 'imei' => $e['imei'] ?: '', 'mac' => $e['mac'] ?? '',
            'activo_fijo' => $e['activo_fijo'] ?? '',
            'specs' => $specs,
            'accesorios' => array_column($acc, 'nombre'),
            'condicion' => ActaPerfiles::CONDICIONES[$cond] ?? ucfirst($cond),
            'condicion_devolucion' => $dev ? $condDev : '',
            'obs_devolucion' => $dev ? mb_substr(trim((string)($x['obs'] ?? '')), 0, 300) : '',
            'acc_faltantes' => $dev ? mb_substr(trim((string)($x['faltantes'] ?? '')), 0, 200) : '',
            'checks' => ActaPerfiles::checksValidos($dev ? 'dev' : 'ent', $fam, $x['checks'] ?? []),
        ];
    }

    // ---------------------------------------------------------
    // Alta del acta
    // ---------------------------------------------------------

    /**
     * Guarda el acta y devuelve [id, codigo, token, url].
     * $c = contenido (ver arriba); aqui se completan version, fechas, formato y familia.
     * Debe llamarse dentro de la transaccion de quien registra el movimiento.
     */
    public static function crear(string $tipo, array $c, int $personalId, ?int $asigId = null): array
    {
        self::asegurar();
        if (!isset(ActaPerfiles::MOVIMIENTOS[$tipo])) throw new InvalidArgumentException("Tipo de acta invalido: $tipo");

        $ent = $c['equipos_entregados'] ?? [];
        $dev = $c['equipos_devueltos'] ?? [];
        $principal = $tipo === 'devolucion'
            ? ($dev[0]['familia'] ?? 'otro')
            : ($ent[0]['familia'] ?? ($dev[0]['familia'] ?? 'otro'));

        $c['v'] = self::VERSION;
        $c['tipo'] = $tipo;
        $c['familia'] = self::familiaDe($c) ?: $principal;
        $c['formato_codigo'] = self::formato($tipo, $principal);
        $c['ti_nombre'] = $c['ti_nombre'] ?? Auth::nombre();
        $c['fecha'] = $c['fecha'] ?? date('Y-m-d H:i:s');
        $c['fecha_formal'] = $c['fecha_formal'] ?? (date('d') . ' / ' . date('m') . ' / ' . date('Y'));

        $codigo = Correlativo::generar('acta');
        $token = bin2hex(random_bytes(32));
        $col = $c['colaborador']['nombre'] ?? '';
        $id = Database::insert('actas', [
            'codigo' => $codigo, 'tipo' => $tipo, 'personal_id' => $personalId,
            'responsable_usuario_id' => Auth::userId(), 'asignacion_id' => $asigId,
            'fecha' => $c['fecha'], 'contenido' => json_encode($c, JSON_UNESCAPED_UNICODE),
            'token' => $token, 'firmado' => 0,
            'familia' => $c['familia'],
            'destino_tipo' => $c['destino']['tipo'] ?? ($col !== '' ? 'persona' : null),
            'destino_nombre' => mb_substr(self::destinoTexto($c), 0, 150) ?: null,
            'equipos_resumen' => self::resumen($c),
        ]);
        return ['id' => $id, 'codigo' => $codigo, 'token' => $token, 'url' => BASE_URL . 'acta.php?token=' . $token];
    }

    /** 'mixto' si intervienen varias familias; la familia unica si es una sola; '' si no hay equipos. */
    public static function familiaDe(array $c): string
    {
        $f = [];
        foreach (array_merge($c['equipos_entregados'] ?? [], $c['equipos_devueltos'] ?? []) as $e) {
            $f[] = ActaPerfiles::claveFamilia($e['familia'] ?? 'otro');
        }
        $f = array_values(array_unique($f));
        return count($f) === 0 ? '' : (count($f) === 1 ? $f[0] : 'mixto');
    }

    /** «Persona», «Área: X», «Obra: X»... */
    public static function destinoTexto(array $c): string
    {
        $d = $c['destino'] ?? null;
        if ($d && ($d['tipo'] ?? 'persona') !== 'persona') {
            return ($d['tipo_texto'] ?? $d['tipo']) . ': ' . ($d['nombre'] ?? '');
        }
        return (string)($c['colaborador']['nombre'] ?? ($d['nombre'] ?? ''));
    }

    /** Texto corto con los equipos del acta (para buscar y listar). */
    public static function resumen(array $c): string
    {
        $t = [];
        foreach (array_merge($c['equipos_entregados'] ?? [], $c['equipos_devueltos'] ?? []) as $e) {
            $t[] = trim(($e['codigo'] ?? '') . ' ' . ($e['tipo'] ?? '') . ' ' . ($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? ''));
        }
        return mb_substr(implode('; ', $t), 0, 300);
    }

    /** Enlaza el acta con la asignacion (entrega / devolucion). */
    public static function vincular(int $asigId, string $que, int $actaId): void
    {
        $col = $que === 'devolucion' ? 'acta_devolucion_id' : 'acta_entrega_id';
        Database::run("UPDATE asignaciones SET $col = ? WHERE id = ?", [$actaId, $asigId]);
    }
}
