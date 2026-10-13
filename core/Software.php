<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software instalado en los equipos (control interno / auditoria)
//
//   software_catalogo   programas conocidos (nombre, categoria, autorizacion, si pide licencia)
//   software_licencias  licencias compradas: tipo, clave, PUESTOS, vencimiento, costo
//   equipo_software     que programa esta instalado en que equipo (con historial)
//
//   * Todo equipo (laptop, PC, servidor, celular...) puede tener software.
//   * Una instalacion consume 1 puesto de una licencia. No se puede pasar de los puestos.
//   * Desinstalar no borra: queda la fila con fecha (historial para auditoria).
//   * Se crea solo la primera vez (como Anuncios): no hace falta ejecutar SQL.
// ============================================================

class Software
{
    public const CATEGORIAS = [
        'sistema_operativo' => 'Sistema operativo',
        'ofimatica'         => 'Ofimática',
        'seguridad'         => 'Seguridad / antivirus',
        'navegador'         => 'Navegador',
        'comunicacion'      => 'Comunicación y reuniones',
        'diseno'            => 'Diseño, CAD y construcción',
        'desarrollo'        => 'Desarrollo y bases de datos',
        'acceso_remoto'     => 'Acceso remoto',
        'utilitario'        => 'Utilitarios',
        'gestion'           => 'Gestión / ERP',
        'otro'              => 'Otros',
    ];
    public const AUTORIZACION = [
        'autorizado' => 'Autorizado',
        'evaluacion' => 'En evaluación',
        'prohibido'  => 'No autorizado',
    ];
    public const TIPOS_LIC = [
        'perpetua'    => 'Perpetua',
        'suscripcion' => 'Suscripción',
        'oem'         => 'OEM (viene con el equipo)',
        'volumen'     => 'Por volumen',
    ];
    /** Unidades de duracion de una licencia (para calcular el vencimiento desde la activacion). */
    public const UNIDADES_DURACION = ['dias' => 'día(s)', 'meses' => 'mes(es)', 'anios' => 'año(s)'];
    /** Licencias que vencen dentro de este plazo se marcan «por vencer». */
    public const DIAS_ALERTA = 60;
    /** Un equipo cuyo inventario de software no se confirma en este plazo se marca «sin verificar». */
    public const DIAS_VERIFICACION = 180;

    /** Instalacion vigente: instalada y en un equipo que no esta de baja. Requiere alias es / eq. */
    public const INST_VIG = "es.estado = 'instalado' AND eq.estado <> 'dado_de_baja'";

    private static bool $ok = false;

    // ----------------------------------------------------------------------------------
    //  Esquema
    // ----------------------------------------------------------------------------------
    public static function asegurar(): void
    {
        if (self::$ok) return;

        $nuevo = false;
        try { Database::run('SELECT 1 FROM software_catalogo LIMIT 0'); }
        catch (Throwable $e) { $nuevo = true; }

        Database::run("CREATE TABLE IF NOT EXISTS software_catalogo (
            id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre            VARCHAR(100) NOT NULL,
            fabricante        VARCHAR(80) NULL,
            categoria         VARCHAR(20) NOT NULL DEFAULT 'otro',
            requiere_licencia TINYINT(1) NOT NULL DEFAULT 1,
            autorizacion      VARCHAR(12) NOT NULL DEFAULT 'autorizado',
            estandar          TINYINT(1) NOT NULL DEFAULT 0,
            descripcion       VARCHAR(255) NULL,
            activo            TINYINT(1) NOT NULL DEFAULT 1,
            creado_por        INT UNSIGNED NULL,
            created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP NULL DEFAULT NULL,
            KEY idx_sw_cat_nombre (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        Database::run("CREATE TABLE IF NOT EXISTS software_licencias (
            id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            software_id       INT UNSIGNED NOT NULL,
            tipo              VARCHAR(12) NOT NULL DEFAULT 'perpetua',
            clave             VARCHAR(190) NULL,
            puestos           INT UNSIGNED NOT NULL DEFAULT 1,
            fecha_compra      DATE NULL,
            fecha_activacion  DATE NULL,
            duracion_valor    SMALLINT UNSIGNED NULL,
            duracion_unidad   VARCHAR(6) NULL,
            fecha_vencimiento DATE NULL,
            precio_unitario   DECIMAL(10,2) NULL,
            costo             DECIMAL(10,2) NULL,
            proveedor         VARCHAR(100) NULL,
            documento         VARCHAR(60) NULL,
            observaciones     VARCHAR(255) NULL,
            factura_archivo   VARCHAR(120) NULL,
            factura_nombre    VARCHAR(200) NULL,
            factura_kb        INT UNSIGNED NULL,
            factura_subida_en DATETIME NULL,
            activo            TINYINT(1) NOT NULL DEFAULT 1,
            legacy_id         INT UNSIGNED NULL,
            creado_por        INT UNSIGNED NULL,
            created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP NULL DEFAULT NULL,
            KEY idx_sw_lic_sw (software_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        Database::run("CREATE TABLE IF NOT EXISTS equipo_software (
            id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            equipo_id           INT UNSIGNED NOT NULL,
            software_id         INT UNSIGNED NOT NULL,
            version             VARCHAR(40) NULL,
            licencia_id         INT UNSIGNED NULL,
            estado              VARCHAR(12) NOT NULL DEFAULT 'instalado',
            fecha_instalacion   DATE NULL,
            fecha_desinstalacion DATE NULL,
            instalado_por       INT UNSIGNED NULL,
            desinstalado_por    INT UNSIGNED NULL,
            observaciones       VARCHAR(255) NULL,
            legacy_id           INT UNSIGNED NULL,
            created_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          TIMESTAMP NULL DEFAULT NULL,
            KEY idx_es_equipo (equipo_id),
            KEY idx_es_sw (software_id),
            KEY idx_es_lic (licencia_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        Esquema::columna('equipos', 'software_verificado_en', 'DATETIME NULL');
        Esquema::columna('equipos', 'software_verificado_por', 'INT UNSIGNED NULL');
        // Activacion de la licencia + duracion = vencimiento calculado
        Esquema::columna('software_licencias', 'fecha_activacion', 'DATE NULL');
        Esquema::columna('software_licencias', 'duracion_valor', 'SMALLINT UNSIGNED NULL');
        Esquema::columna('software_licencias', 'duracion_unidad', 'VARCHAR(6) NULL');
        // Precio por puesto: el costo total (costo) = precio_unitario x puestos
        Esquema::columna('software_licencias', 'precio_unitario', 'DECIMAL(10,2) NULL');
        // Factura / comprobante de la compra (PDF o imagen), se ve desde la lista de licencias
        Esquema::columna('software_licencias', 'factura_archivo', 'VARCHAR(120) NULL');
        Esquema::columna('software_licencias', 'factura_nombre', 'VARCHAR(200) NULL');
        Esquema::columna('software_licencias', 'factura_kb', 'INT UNSIGNED NULL');
        Esquema::columna('software_licencias', 'factura_subida_en', 'DATETIME NULL');

        self::$ok = true;

        // Si las tablas ya las creo db/sigti.sql (vacias), el catalogo inicial se carga una sola vez
        if (!$nuevo) {
            try {
                $hecha = Database::getValue("SELECT valor FROM configuracion WHERE clave = 'software_semilla'");
                if (!$hecha && (int)Database::getValue('SELECT COUNT(*) FROM software_catalogo') === 0
                    && (int)Database::getValue('SELECT COUNT(*) FROM equipo_software') === 0) {
                    $nuevo = true;
                }
            } catch (Throwable $e) { /* sin tabla configuracion: no se siembra de nuevo */ }
        }

        if ($nuevo) {
            try {
                self::semilla();
                self::migrarLicenciasAntiguas();
                try {
                    Database::insert('configuracion', ['clave' => 'software_semilla', 'valor' => '1',
                        'descripcion' => 'Catalogo inicial de software ya cargado']);
                } catch (Throwable $e2) { /* ya existia */ }
            } catch (Throwable $e) {
                error_log('[SIGTI] Software::asegurar (semilla): ' . $e->getMessage());
            }
        }
    }

    /** Catalogo inicial: lo habitual en una empresa. Todo se puede editar o desactivar. */
    private static function semilla(): void
    {
        // [nombre, fabricante, categoria, requiere_licencia, autorizacion, estandar]
        $lista = [
            ['Windows 11 Pro',            'Microsoft', 'sistema_operativo', 1, 'autorizado', 1],
            ['Windows 10 Pro',            'Microsoft', 'sistema_operativo', 1, 'autorizado', 0],
            ['Windows Server',            'Microsoft', 'sistema_operativo', 1, 'autorizado', 0],
            ['Microsoft Office',          'Microsoft', 'ofimatica',         1, 'autorizado', 1],
            ['Microsoft 365 (Apps)',      'Microsoft', 'ofimatica',         1, 'autorizado', 0],
            ['LibreOffice',               'The Document Foundation', 'ofimatica', 0, 'autorizado', 0],
            ['Antivirus corporativo',     '',          'seguridad',         1, 'autorizado', 1],
            ['Google Chrome',             'Google',    'navegador',         0, 'autorizado', 1],
            ['Mozilla Firefox',           'Mozilla',   'navegador',         0, 'autorizado', 0],
            ['Microsoft Edge',            'Microsoft', 'navegador',         0, 'autorizado', 0],
            ['Adobe Acrobat Reader',      'Adobe',     'utilitario',        0, 'autorizado', 1],
            ['Adobe Acrobat Pro',         'Adobe',     'utilitario',        1, 'autorizado', 0],
            ['7-Zip',                     'Igor Pavlov', 'utilitario',      0, 'autorizado', 0],
            ['WinRAR',                    'RARLAB',    'utilitario',        1, 'autorizado', 0],
            ['VLC media player',          'VideoLAN',  'utilitario',        0, 'autorizado', 0],
            ['Microsoft Teams',           'Microsoft', 'comunicacion',      0, 'autorizado', 0],
            ['Zoom',                      'Zoom',      'comunicacion',      0, 'autorizado', 0],
            ['AnyDesk',                   'AnyDesk',   'acceso_remoto',     0, 'evaluacion', 0],
            ['TeamViewer',                'TeamViewer', 'acceso_remoto',    0, 'evaluacion', 0],
            ['AutoCAD',                   'Autodesk',  'diseno',            1, 'autorizado', 0],
            ['Revit',                     'Autodesk',  'diseno',            1, 'autorizado', 0],
            ['S10 Presupuestos',          'S10',       'gestion',           1, 'autorizado', 0],
        ];
        foreach ($lista as [$n, $f, $c, $r, $a, $e]) {
            Database::insert('software_catalogo', [
                'nombre' => $n, 'fabricante' => $f !== '' ? $f : null, 'categoria' => $c,
                'requiere_licencia' => $r, 'autorizacion' => $a, 'estandar' => $e,
            ]);
        }
    }

    /**
     * La tabla equipo_licencias (version anterior) no tenia pantalla. Si alguien cargo filas,
     * se pasan al modelo nuevo una sola vez: cada fila = una licencia de 1 puesto ya instalada en su equipo.
     */
    private static function migrarLicenciasAntiguas(): void
    {
        try { $viejas = Database::get('SELECT * FROM equipo_licencias ORDER BY id'); }
        catch (Throwable $e) { return; }
        foreach ($viejas as $v) {
            $nombre = trim((string)$v['software']);
            if ($nombre === '') continue;
            $sid = (int) Database::getValue('SELECT id FROM software_catalogo WHERE LOWER(nombre) = ?', [mb_strtolower($nombre)]);
            if (!$sid) {
                $sid = Database::insert('software_catalogo', [
                    'nombre' => mb_substr($nombre, 0, 100), 'categoria' => 'otro', 'requiere_licencia' => 1,
                ]);
            }
            $tipo = in_array($v['tipo'] ?? '', array_keys(self::TIPOS_LIC), true) ? $v['tipo'] : 'perpetua';
            $lid = Database::insert('software_licencias', [
                'software_id' => $sid, 'tipo' => $tipo, 'clave' => $v['clave'] ?: null, 'puestos' => 1,
                'fecha_compra' => $v['fecha_inicio'] ?: null, 'fecha_vencimiento' => $v['fecha_vencimiento'] ?: null,
                'costo' => $v['costo'] ?? null, 'legacy_id' => (int)$v['id'],
                'observaciones' => 'Migrada de la lista anterior de licencias.',
            ]);
            Database::insert('equipo_software', [
                'equipo_id' => (int)$v['equipo_id'], 'software_id' => $sid, 'licencia_id' => $lid,
                'estado' => 'instalado', 'fecha_instalacion' => $v['fecha_inicio'] ?: date('Y-m-d'),
                'observaciones' => 'Migrado de la lista anterior de licencias.', 'legacy_id' => (int)$v['id'],
            ]);
        }
    }

    // ----------------------------------------------------------------------------------
    //  Licencias
    // ----------------------------------------------------------------------------------
    /** Puestos en uso por licencia: [licencia_id => n]. Solo cuenta instalaciones vigentes. */
    public static function usoPorLicencia(): array
    {
        $uso = [];
        $filas = Database::get(
            "SELECT es.licencia_id, COUNT(*) AS n
             FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id
             WHERE " . self::INST_VIG . " AND es.licencia_id IS NOT NULL
             GROUP BY es.licencia_id");
        foreach ($filas as $f) $uso[(int)$f['licencia_id']] = (int)$f['n'];
        return $uso;
    }

    /**
     * Vencimiento = fecha de activacion + duracion. «El mismo dia» del mes o anio siguiente
     * (activada el 15/01 por 12 meses -> vence el 15/01 del anio siguiente); si ese dia no
     * existe (31 + 1 mes) toma el ultimo dia del mes. null si los datos no son validos.
     */
    public static function calcularVencimiento(?string $activacion, $valor, ?string $unidad): ?string
    {
        $valor = (int)$valor;
        if (!$activacion || $valor < 1 || !isset(self::UNIDADES_DURACION[(string)$unidad])) return null;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $activacion, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return null;
        [$a, $mes, $dia] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        if ($unidad === 'dias') return date('Y-m-d', strtotime($activacion . ' +' . $valor . ' days'));
        $meses = $unidad === 'anios' ? $valor * 12 : $valor;
        $tot = $a * 12 + ($mes - 1) + $meses;
        $na = intdiv($tot, 12); $nm = $tot % 12 + 1;
        $ult = (int)date('t', mktime(0, 0, 0, $nm, 1, $na));
        return sprintf('%04d-%02d-%02d', $na, $nm, min($dia, $ult));
    }

    /** «12 mes(es)» -> texto legible: «12 meses», «1 año», «30 días». */
    public static function textoDuracion($valor, ?string $unidad): string
    {
        $v = (int)$valor;
        if ($v < 1 || !isset(self::UNIDADES_DURACION[(string)$unidad])) return '';
        $u = ['dias' => ['día', 'días'], 'meses' => ['mes', 'meses'], 'anios' => ['año', 'años']][$unidad];
        return $v . ' ' . ($v === 1 ? $u[0] : $u[1]);
    }

    /** vigente | por_vencer | vencida | sin_vencimiento */
    public static function estadoLicencia(?string $vence, ?string $hoy = null): string
    {
        if ($vence === null || $vence === '') return 'sin_vencimiento';
        $hoy = $hoy ?: date('Y-m-d');
        if ($vence < $hoy) return 'vencida';
        if ($vence <= date('Y-m-d', strtotime($hoy . ' +' . self::DIAS_ALERTA . ' days'))) return 'por_vencer';
        return 'vigente';
    }

    /** Licencia con puestos libres y sin vencer (la que vence antes, primero). null si no hay. */
    public static function licenciaLibre(int $softwareId): ?int
    {
        $hoy = date('Y-m-d');
        $uso = self::usoPorLicencia();
        $lics = Database::get(
            "SELECT id, puestos FROM software_licencias
             WHERE software_id = ? AND activo = 1 AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= ?)
             ORDER BY CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento, id", [$softwareId, $hoy]);
        foreach ($lics as $l) {
            if (($uso[(int)$l['id']] ?? 0) < (int)$l['puestos']) return (int)$l['id'];
        }
        return null;
    }

    /** Valida que se pueda tomar un puesto de la licencia. $excluirInst = instalacion que ya la usa (al editar). */
    private static function validarLicencia(int $licId, int $softwareId, int $excluirInst = 0): void
    {
        $l = Database::getOne('SELECT * FROM software_licencias WHERE id = ?', [$licId]);
        if (!$l || (int)$l['software_id'] !== $softwareId) throw new InvalidArgumentException('La licencia no corresponde a este software.');
        if ((int)$l['activo'] !== 1) throw new InvalidArgumentException('La licencia está dada de baja.');
        if ($l['fecha_vencimiento'] && $l['fecha_vencimiento'] < date('Y-m-d')) {
            throw new InvalidArgumentException('La licencia venció el ' . $l['fecha_vencimiento'] . '.');
        }
        $usados = (int) Database::getValue(
            "SELECT COUNT(*) FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id
             WHERE " . self::INST_VIG . " AND es.licencia_id = ? AND es.id <> ?", [$licId, $excluirInst]);
        if ($usados >= (int)$l['puestos']) {
            throw new InvalidArgumentException("La licencia no tiene puestos libres ($usados de " . (int)$l['puestos'] . ' en uso).');
        }
    }

    // ----------------------------------------------------------------------------------
    //  Instalaciones
    // ----------------------------------------------------------------------------------
    private static function fechaValida(?string $f): ?string
    {
        $f = trim((string)$f);
        if ($f === '') return null;
        $d = DateTime::createFromFormat('Y-m-d', $f);
        if (!$d || $d->format('Y-m-d') !== $f) throw new InvalidArgumentException('La fecha de instalación no es válida.');
        if ($f > date('Y-m-d')) throw new InvalidArgumentException('La fecha de instalación no puede ser futura.');
        return $f;
    }

    /**
     * Registra un programa instalado en un equipo.
     * $licencia: '' (sin licencia) | 'auto' (la primera con puestos libres) | id de licencia.
     * Devuelve ['id' => ..., 'licencia_id' => ... , 'aviso' => ...|null]. Lanza InvalidArgumentException con el motivo.
     */
    public static function instalar(int $equipoId, int $softwareId, string $version = '', $licencia = '',
                                    ?string $fecha = null, string $obs = '', ?int $uid = null): array
    {
        self::asegurar();
        $eq = Database::getOne('SELECT id, codigo, estado FROM equipos WHERE id = ?', [$equipoId]);
        if (!$eq) throw new InvalidArgumentException('El equipo no existe.');
        if ($eq['estado'] === 'dado_de_baja') throw new InvalidArgumentException("El equipo {$eq['codigo']} está dado de baja: es un registro histórico.");

        $sw = Database::getOne('SELECT * FROM software_catalogo WHERE id = ?', [$softwareId]);
        if (!$sw || (int)$sw['activo'] !== 1) throw new InvalidArgumentException('El software no existe o está desactivado.');

        $yaEsta = Database::getValue("SELECT id FROM equipo_software WHERE equipo_id = ? AND software_id = ? AND estado = 'instalado'", [$equipoId, $softwareId]);
        if ($yaEsta) throw new InvalidArgumentException("«{$sw['nombre']}» ya está registrado en {$eq['codigo']}.");

        $version = trim($version);
        if (mb_strlen($version) > 40) throw new InvalidArgumentException('La versión es demasiado larga (máximo 40 caracteres).');
        $obs = trim($obs);
        if (mb_strlen($obs) > 255) throw new InvalidArgumentException('Las observaciones son demasiado largas (máximo 255 caracteres).');
        $fecha = self::fechaValida($fecha) ?? date('Y-m-d');

        $licId = null; $aviso = null;
        if ((int)$sw['requiere_licencia'] === 1) {
            if ($licencia === 'auto') {
                $licId = self::licenciaLibre($softwareId);
                if ($licId === null) $aviso = 'sin_licencia_disponible';
            } elseif ((int)$licencia > 0) {
                $licId = (int)$licencia;
                self::validarLicencia($licId, $softwareId);
            } else {
                $aviso = 'sin_licencia';
            }
        }

        $id = Database::insert('equipo_software', [
            'equipo_id' => $equipoId, 'software_id' => $softwareId, 'version' => $version !== '' ? $version : null,
            'licencia_id' => $licId, 'estado' => 'instalado', 'fecha_instalacion' => $fecha,
            'instalado_por' => $uid, 'observaciones' => $obs !== '' ? $obs : null,
        ]);
        Database::insert('equipo_historial', [
            'equipo_id' => $equipoId, 'usuario_id' => $uid, 'evento' => 'software_instalado',
            'detalle' => mb_substr($sw['nombre'] . ($version !== '' ? ' ' . $version : ''), 0, 200),
        ]);
        Auditoria::registrar('crear', 'equipo_software', $id,
            null, ['equipo' => $eq['codigo'], 'software' => $sw['nombre'], 'version' => $version, 'licencia_id' => $licId],
            ['accion_detalle' => 'software_instalado', 'submodulo' => 'software']);
        return ['id' => $id, 'licencia_id' => $licId, 'aviso' => $aviso, 'software' => $sw['nombre'], 'equipo' => $eq['codigo']];
    }

    /** Marca la instalacion como desinstalada (queda en el historial, libera el puesto). */
    public static function desinstalar(int $instId, string $motivo = '', ?int $uid = null): array
    {
        self::asegurar();
        $i = Database::getOne(
            "SELECT es.*, s.nombre AS sw, eq.codigo, eq.estado AS eq_estado
             FROM equipo_software es
             INNER JOIN software_catalogo s ON s.id = es.software_id
             INNER JOIN equipos eq ON eq.id = es.equipo_id WHERE es.id = ?", [$instId]);
        if (!$i) throw new InvalidArgumentException('La instalación no existe.');
        if ($i['eq_estado'] === 'dado_de_baja') throw new InvalidArgumentException("El equipo {$i['codigo']} está dado de baja: es un registro histórico.");
        if ($i['estado'] !== 'instalado') throw new InvalidArgumentException('Ese software ya figura como desinstalado.');
        $motivo = trim($motivo);
        if (mb_strlen($motivo) > 200) throw new InvalidArgumentException('El motivo es demasiado largo (máximo 200 caracteres).');

        Database::update('equipo_software', [
            'estado' => 'desinstalado', 'fecha_desinstalacion' => date('Y-m-d'), 'desinstalado_por' => $uid,
            'updated_at' => date('Y-m-d H:i:s'),
            'observaciones' => $motivo !== '' ? mb_substr(trim(($i['observaciones'] ? $i['observaciones'] . ' · ' : '') . 'Retiro: ' . $motivo), 0, 255) : $i['observaciones'],
        ], 'id = ?', [$instId]);
        Database::insert('equipo_historial', [
            'equipo_id' => (int)$i['equipo_id'], 'usuario_id' => $uid, 'evento' => 'software_desinstalado',
            'detalle' => mb_substr($i['sw'] . ($i['version'] ? ' ' . $i['version'] : '') . ($motivo !== '' ? ' — ' . $motivo : ''), 0, 200),
        ]);
        Auditoria::registrar('actualizar', 'equipo_software', $instId,
            ['estado' => 'instalado', 'equipo' => $i['codigo'], 'software' => $i['sw']], ['estado' => 'desinstalado', 'motivo' => $motivo],
            ['accion_detalle' => 'software_desinstalado', 'submodulo' => 'software']);
        return ['equipo' => $i['codigo'], 'software' => $i['sw']];
    }

    /** Cambia version, licencia u observaciones de una instalacion vigente. */
    public static function editar(int $instId, string $version, $licencia, string $obs, ?int $uid = null): array
    {
        self::asegurar();
        $i = Database::getOne(
            "SELECT es.*, s.nombre AS sw, s.requiere_licencia, eq.codigo, eq.estado AS eq_estado
             FROM equipo_software es
             INNER JOIN software_catalogo s ON s.id = es.software_id
             INNER JOIN equipos eq ON eq.id = es.equipo_id WHERE es.id = ?", [$instId]);
        if (!$i) throw new InvalidArgumentException('La instalación no existe.');
        if ($i['eq_estado'] === 'dado_de_baja') throw new InvalidArgumentException("El equipo {$i['codigo']} está dado de baja: es un registro histórico.");
        if ($i['estado'] !== 'instalado') throw new InvalidArgumentException('Esa instalación ya fue retirada.');
        $version = trim($version); $obs = trim($obs);
        if (mb_strlen($version) > 40) throw new InvalidArgumentException('La versión es demasiado larga (máximo 40 caracteres).');
        if (mb_strlen($obs) > 255) throw new InvalidArgumentException('Las observaciones son demasiado largas (máximo 255 caracteres).');

        $licId = $i['licencia_id'] !== null ? (int)$i['licencia_id'] : null;
        if ((int)$i['requiere_licencia'] === 1) {
            if ($licencia === 'auto') {
                $licId = $licId ?: self::licenciaLibre((int)$i['software_id']);
            } elseif ((int)$licencia > 0) {
                if ((int)$licencia !== $licId) self::validarLicencia((int)$licencia, (int)$i['software_id'], $instId);
                $licId = (int)$licencia;
            } elseif ($licencia !== null) {
                $licId = null;   // '' / 0 = quitar la licencia (null = dejarla como esta)
            }
        } else {
            $licId = null;
        }
        $nuevo = ['version' => $version !== '' ? $version : null, 'licencia_id' => $licId,
                  'observaciones' => $obs !== '' ? $obs : null, 'updated_at' => date('Y-m-d H:i:s')];
        Database::update('equipo_software', $nuevo, 'id = ?', [$instId]);
        $cambios = [];
        if ((string)$i['version'] !== (string)($nuevo['version'] ?? '')) $cambios[] = 'versión «' . $i['version'] . '» → «' . ($nuevo['version'] ?? '') . '»';
        if ((int)$i['licencia_id'] !== (int)$licId) $cambios[] = 'licencia #' . ($i['licencia_id'] ?: '—') . ' → #' . ($licId ?: '—');
        if ($cambios) {
            Database::insert('equipo_historial', [
                'equipo_id' => (int)$i['equipo_id'], 'usuario_id' => $uid, 'evento' => 'software_actualizado',
                'detalle' => mb_substr($i['sw'] . ': ' . implode('; ', $cambios), 0, 200),
            ]);
        }
        Auditoria::registrar('actualizar', 'equipo_software', $instId,
            ['version' => $i['version'], 'licencia_id' => $i['licencia_id'], 'observaciones' => $i['observaciones']],
            ['version' => $nuevo['version'], 'licencia_id' => $licId, 'observaciones' => $nuevo['observaciones']],
            ['accion_detalle' => 'software_editado', 'submodulo' => 'software']);
        return ['equipo' => $i['codigo'], 'software' => $i['sw'], 'cambios' => $cambios];
    }

    /** Equipo dado de baja: sus programas pasan a «desinstalado» (libera puestos, queda el historial). */
    public static function liberarEquipo(int $equipoId, ?int $uid = null): int
    {
        try {
            self::asegurar();
            $n = Database::update('equipo_software', [
                'estado' => 'desinstalado', 'fecha_desinstalacion' => date('Y-m-d'), 'desinstalado_por' => $uid,
                'updated_at' => date('Y-m-d H:i:s'),
            ], "equipo_id = ? AND estado = 'instalado'", [$equipoId]);
            if ($n > 0) {
                Database::insert('equipo_historial', [
                    'equipo_id' => $equipoId, 'usuario_id' => $uid, 'evento' => 'software_liberado',
                    'detalle' => "Baja del equipo: $n programa(s) quedan como desinstalados (puestos de licencia liberados)",
                ]);
            }
            return $n;
        } catch (Throwable $e) {
            error_log('[SIGTI] Software::liberarEquipo: ' . $e->getMessage());
            return 0;
        }
    }

    // ----------------------------------------------------------------------------------
    //  Cumplimiento (lo que mira la auditoria / gerencia)
    // ----------------------------------------------------------------------------------
    /** Una fila por programa activo: instaladas, puestos vigentes, deficit... */
    public static function cumplimiento(): array
    {
        self::asegurar();
        $hoy = date('Y-m-d');

        $inst = [];
        $filas = Database::get(
            "SELECT es.software_id, COUNT(*) AS n,
                    SUM(CASE WHEN es.licencia_id IS NOT NULL THEN 1 ELSE 0 END) AS con_lic,
                    SUM(CASE WHEN l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento < ? THEN 1 ELSE 0 END) AS con_venc
             FROM equipo_software es
             INNER JOIN equipos eq ON eq.id = es.equipo_id
             LEFT JOIN software_licencias l ON l.id = es.licencia_id
             WHERE " . self::INST_VIG . " GROUP BY es.software_id", [$hoy]);
        foreach ($filas as $f) $inst[(int)$f['software_id']] = $f;

        $lic = [];
        $filas = Database::get(
            "SELECT software_id, COUNT(*) AS n, SUM(puestos) AS total,
                    SUM(CASE WHEN fecha_vencimiento IS NULL OR fecha_vencimiento >= ? THEN puestos ELSE 0 END) AS vigentes,
                    SUM(COALESCE(costo, 0)) AS costo
             FROM software_licencias WHERE activo = 1 GROUP BY software_id", [$hoy]);
        foreach ($filas as $f) $lic[(int)$f['software_id']] = $f;

        $out = [];
        foreach (Database::get('SELECT * FROM software_catalogo WHERE activo = 1 ORDER BY nombre') as $s) {
            $id = (int)$s['id'];
            $n = (int)($inst[$id]['n'] ?? 0);
            $conLic = (int)($inst[$id]['con_lic'] ?? 0);
            $conVenc = (int)($inst[$id]['con_venc'] ?? 0);
            $vig = (int)($lic[$id]['vigentes'] ?? 0);
            $req = (int)$s['requiere_licencia'] === 1;
            $deficit = $req ? max(0, $n - $vig) : 0;
            $estado = !$req ? 'libre' : ($n === 0 ? 'sin_uso' : ($deficit > 0 ? 'deficit' : 'ok'));
            $out[] = [
                'id' => $id, 'nombre' => $s['nombre'], 'fabricante' => $s['fabricante'], 'categoria' => $s['categoria'],
                'requiere_licencia' => $req ? 1 : 0, 'autorizacion' => $s['autorizacion'],
                'instaladas' => $n, 'con_licencia' => $conLic, 'sin_licencia' => $req ? $n - $conLic : 0,
                'con_licencia_vencida' => $req ? $conVenc : 0,
                'licencias' => (int)($lic[$id]['n'] ?? 0), 'puestos_total' => (int)($lic[$id]['total'] ?? 0),
                'puestos_vigentes' => $vig, 'deficit' => $deficit,
                'sobrantes' => $req ? max(0, $vig - $n) : 0,
                'costo' => (float)($lic[$id]['costo'] ?? 0), 'estado' => $estado,
            ];
        }
        return $out;
    }

    /** Numeros de la portada: tarjetas y alertas. */
    public static function resumen(): array
    {
        self::asegurar();
        $hoy = date('Y-m-d');
        $limite = date('Y-m-d', strtotime($hoy . ' +' . self::DIAS_ALERTA . ' days'));
        $corteVerif = date('Y-m-d H:i:s', strtotime($hoy . ' -' . self::DIAS_VERIFICACION . ' days'));

        $cumpl = self::cumplimiento();
        $deficit = 0; $faltantes = 0;
        foreach ($cumpl as $c) { if ($c['estado'] === 'deficit') { $deficit++; $faltantes += $c['deficit']; } }

        $q = fn(string $sql, array $p = []) => (int) Database::getValue($sql, $p);
        $vig = self::INST_VIG;
        $r = [
            'programas'        => $q("SELECT COUNT(*) FROM software_catalogo WHERE activo = 1"),
            'instalaciones'    => $q("SELECT COUNT(*) FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id WHERE $vig"),
            'equipos_con'      => $q("SELECT COUNT(DISTINCT es.equipo_id) FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id WHERE $vig"),
            'equipos_computo'  => $q("SELECT COUNT(*) FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id WHERE t.familia = 'computo' AND e.estado <> 'dado_de_baja'"),
            'equipos_computo_sin' => $q("SELECT COUNT(*) FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
                 WHERE t.familia = 'computo' AND e.estado <> 'dado_de_baja'
                   AND NOT EXISTS (SELECT 1 FROM equipo_software es WHERE es.equipo_id = e.id AND es.estado = 'instalado')"),
            'licencias'        => $q("SELECT COUNT(*) FROM software_licencias WHERE activo = 1"),
            'puestos'          => $q("SELECT COALESCE(SUM(puestos), 0) FROM software_licencias WHERE activo = 1 AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= ?)", [$hoy]),
            'inversion'        => (float) Database::getValue("SELECT COALESCE(SUM(costo), 0) FROM software_licencias WHERE activo = 1"),
            // alertas
            'no_autorizado'    => $q("SELECT COUNT(*) FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id
                 INNER JOIN software_catalogo s ON s.id = es.software_id WHERE $vig AND s.autorizacion = 'prohibido'"),
            'sin_licencia'     => $q("SELECT COUNT(*) FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id
                 INNER JOIN software_catalogo s ON s.id = es.software_id WHERE $vig AND s.requiere_licencia = 1 AND es.licencia_id IS NULL"),
            'licencia_vencida' => $q("SELECT COUNT(*) FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id
                 INNER JOIN software_licencias l ON l.id = es.licencia_id
                 WHERE $vig AND l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento < ?", [$hoy]),
            'lic_por_vencer'   => $q("SELECT COUNT(*) FROM software_licencias WHERE activo = 1 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento >= ? AND fecha_vencimiento <= ?", [$hoy, $limite]),
            'lic_vencidas'     => $q("SELECT COUNT(*) FROM software_licencias WHERE activo = 1 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < ?", [$hoy]),
            'programas_deficit' => $deficit,
            'puestos_faltantes' => $faltantes,
            'sin_verificar'    => $q("SELECT COUNT(*) FROM equipos e WHERE e.estado <> 'dado_de_baja'
                 AND EXISTS (SELECT 1 FROM equipo_software es WHERE es.equipo_id = e.id AND es.estado = 'instalado')
                 AND (e.software_verificado_en IS NULL OR e.software_verificado_en < ?)", [$corteVerif]),
        ];
        $r['cobertura'] = $r['equipos_computo'] > 0
            ? (int) round(($r['equipos_computo'] - $r['equipos_computo_sin']) * 100 / $r['equipos_computo'])
            : 100;
        $r['alertas'] = $r['no_autorizado'] + $r['sin_licencia'] + $r['licencia_vencida'] + $r['programas_deficit'] + $r['equipos_computo_sin'];
        $r['por_categoria'] = self::porCategoria();
        $ordenado = $cumpl;
        usort($ordenado, fn($a, $b) => $b['instaladas'] <=> $a['instaladas'] ?: strcmp($a['nombre'], $b['nombre']));
        $r['top'] = array_slice(array_values(array_filter($ordenado, fn($c) => $c['instaladas'] > 0)), 0, 8);
        $r['cumplimiento'] = $cumpl;
        return $r;
    }

    private static function porCategoria(): array
    {
        $filas = Database::get(
            "SELECT s.categoria, COUNT(*) AS n
             FROM equipo_software es INNER JOIN equipos eq ON eq.id = es.equipo_id
             INNER JOIN software_catalogo s ON s.id = es.software_id
             WHERE " . self::INST_VIG . " GROUP BY s.categoria ORDER BY n DESC");
        foreach ($filas as &$f) {
            $f['n'] = (int)$f['n'];
            $f['nombre'] = self::CATEGORIAS[$f['categoria']] ?? $f['categoria'];
        }
        return $filas;
    }

    public static function etiquetaCategoria(?string $c): string { return self::CATEGORIAS[$c ?? ''] ?? (string)$c; }
}
