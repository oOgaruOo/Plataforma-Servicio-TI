<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Licenciamiento
//
// Una instalacion por empresa. La licencia es un texto firmado (RSA-2048 / SHA-256) que emite el proveedor con su clave PRIVADA
// (que nunca viaja con el sistema). Aqui solo esta la clave PUBLICA, que permite VERIFICAR la firma sin internet.
//
// La licencia queda atada a:
//   - la EMPRESA (RUC): al activar se registran el nombre y el RUC de la licencia; si luego cambian, la licencia deja de valer.
//   - la INSTALACION (codigo INST-XXXX-XXXX-XXXX que genera cada copia del sistema): una licencia no sirve en otra copia.
// Vigencia: fecha de vencimiento (o permanente). Estados:
//   prueba        sin licencia, dentro de los primeros DIAS_PRUEBA dias de uso       -> funciona completo
//   activa        vigente (mas de DIAS_AVISO dias para vencer o permanente)          -> funciona completo
//   por_vencer    faltan DIAS_AVISO dias o menos                                      -> funciona completo + aviso
//   gracia        vencida hace DIAS_GRACIA dias o menos                               -> funciona completo + aviso urgente
//   vencida       vencida hace mas de DIAS_GRACIA dias                                -> SOLO LECTURA
//   sin_licencia  sin licencia y la prueba termino                                    -> SOLO LECTURA
//   invalida      firma, RUC o instalacion no coinciden / reloj alterado              -> SOLO LECTURA
// En "solo lectura" se puede entrar, consultar, buscar y exportar; no se puede registrar ni modificar. Los datos de la empresa
// nunca se bloquean ni se borran. Siempre se puede activar una licencia nueva (api/licencia/).
// ============================================================

final class Licencia
{
    public const PREFIJO     = 'SIGTI1';
    public const DIAS_PRUEBA = 15;
    public const DIAS_AVISO  = 30;
    public const DIAS_GRACIA = 15;

    /** Clave publica del proveedor (la privada NO esta en este sistema). */
    private const PUBLICA = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArZzdG2Jb2DF2LzdeKqPV
hrbhBsnOSbk5AgCqQNkgIG+tdv0z9nCVTMcUqnAbbBSumyeJc4QNTf2o6I4x5vCc
GXSS9LS2+w2/fWKiQ/pj4QC5A3e9sTHZhoyfgekLPoZNRtQZLVR2phOyH7vV0Lji
RC3QQ+0Oblo62IMp0jQ3OTM+Qdr42zHc/pzN3pfGMG7GhS6nXNq3vlfJoZX2sQ0h
o47X9Oz2I6MrFBpGprDXh/UPduIsJGPIvGMwFKCk/fc47zYYy8NYMiMZ/Ty7R9Yp
D3BGXp/Q6vpPWwmVauSUDFbtT1cjdtFf630+Ri0DCuujV1UUsCP58V5TwwS6yH5K
KQIDAQAB
-----END PUBLIC KEY-----
PEM;

    /** Modulos que pueden encenderse o apagarse por empresa: clave => [nombre, carpetas de api, rutas del menu]. */
    public const MODULOS = [
        'proyectos'      => ['Proyectos y sedes',         ['proyectos'],            ['proyectos/lista']],
        'capacitaciones' => ['Capacitaciones',            ['capacitaciones'],       ['capacitaciones/lista']],
        'mantenimiento'  => ['Mantenimiento',             ['mantenimiento', 'preventivos'], ['mantenimiento/lista']],
        'software'       => ['Software y licencias',      ['software'],             ['software/lista']],
        'rastreo'        => ['Seguimiento y rastreo',     ['rastreo'],              ['equipos/seguimiento']],
        'carnets'        => ['Carnets',                   ['carnets'],              ['carnets/lista']],
        'etiquetas'      => ['Etiquetas de equipos',      ['etiquetas'],            ['etiquetas/plantillas']],
        'chat'           => ['Chat interno',              ['chat'],                 ['chat/principal', 'chat/auditoria']],
        'rrhh'           => ['RR.HH. ampliado',           ['contratos', 'vacaciones', 'ausencias', 'asistencia', 'planillas', 'requerimientos', 'rrhh'],
                              ['rrhh/contratos', 'rrhh/vacaciones', 'rrhh/ausencias', 'rrhh/asistencia', 'rrhh/planillas', 'rrhh/requerimientos']],
    ];

    /** POST que solo CONSULTAN (por ejemplo las listas con DataTables): se permiten aunque el sistema este en solo lectura. */
    private const POST_DE_LECTURA = ['listar', 'stats', 'select', 'consulta', 'exportar', 'resumen', 'detalle', 'contar', 'indicadores',
        'graficos', 'para-atencion', 'datos', 'insignias', 'panel', 'pendientes', 'perfiles', 'tipos',
        'ver-archivo', 'subcategorias', 'descargar', 'accesorios-tipos', 'foto', 'avatar',
        'encabezado', 'cumpleanos', 'permisos-efectivos', 'tickets-disponibles'];

    private static ?array $cache = null;
    private static bool $tabla = false;

    // ------------------------------------------------------------------ almacenamiento
    private static function asegurar(): void
    {
        if (self::$tabla) return;
        try {
            Database::run('SELECT 1 FROM sistema_licencia LIMIT 1');
        } catch (Throwable $e) {
            Database::run('CREATE TABLE IF NOT EXISTS sistema_licencia (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token TEXT NOT NULL,
                licencia_id VARCHAR(40) NULL,
                activada_en DATETIME NULL,
                activada_por INT NULL
            ) ENGINE=InnoDB');
        }
        self::$tabla = true;
    }

    public static function cfg(string $clave, ?string $def = null): ?string
    {
        try {
            $v = Database::getValue('SELECT valor FROM configuracion WHERE clave = ?', [$clave]);
            return ($v === null || $v === false) ? $def : (string)$v;
        } catch (Throwable $e) {
            return $def;
        }
    }

    public static function setCfg(string $clave, string $valor, string $desc = ''): void
    {
        $existe = Database::getValue('SELECT 1 FROM configuracion WHERE clave = ?', [$clave]);
        if ($existe) Database::update('configuracion', ['valor' => $valor], 'clave = ?', [$clave]);
        else Database::insert('configuracion', ['clave' => $clave, 'valor' => $valor, 'descripcion' => $desc ?: 'Licenciamiento']);
    }

    /** Codigo de esta instalacion (INST-XXXX-XXXX-XXXX). Se crea la primera vez y se conserva con la base de datos. */
    public static function instalacionId(): string
    {
        $id = self::cfg('instalacion_id');
        if ($id && preg_match('/^INST-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', $id)) return $id;
        $h = strtoupper(bin2hex(random_bytes(6)));
        $id = 'INST-' . substr($h, 0, 4) . '-' . substr($h, 4, 4) . '-' . substr($h, 8, 4);
        self::setCfg('instalacion_id', $id, 'Codigo de esta instalacion (para la licencia)');
        return $id;
    }

    // ------------------------------------------------------------------ verificacion del texto de licencia
    private static function b64d(string $s): string
    {
        return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /** @return array{ok:bool, error?:string, datos?:array} */
    public static function verificar(string $token): array
    {
        $token = preg_replace('/\s+/', '', $token);
        $p = explode('.', (string)$token);
        if (count($p) !== 3 || $p[0] !== self::PREFIJO) return ['ok' => false, 'error' => 'El texto no tiene el formato de una licencia SIGTI.'];
        $json = self::b64d($p[1]);
        $sig = self::b64d($p[2]);
        if ($json === '' || $sig === '') return ['ok' => false, 'error' => 'La licencia esta incompleta (revise que copio todo el texto).'];
        if (!function_exists('openssl_verify')) return ['ok' => false, 'error' => 'Falta la extension OpenSSL de PHP (habilite extension=openssl en php.ini).'];
        $v = @openssl_verify($p[0] . '.' . $p[1], $sig, self::PUBLICA, OPENSSL_ALGO_SHA256);
        if ($v !== 1) return ['ok' => false, 'error' => 'La firma de la licencia no es valida: el texto esta alterado o no fue emitido por el proveedor.'];
        $d = json_decode($json, true);
        if (!is_array($d) || (int)($d['v'] ?? 0) !== 1) return ['ok' => false, 'error' => 'Version de licencia no reconocida.'];
        foreach (['id', 'empresa', 'ruc'] as $k) {
            if (!isset($d[$k]) || !is_string($d[$k]) || trim($d[$k]) === '') return ['ok' => false, 'error' => 'La licencia no trae el dato «' . $k . '».'];
        }
        if (isset($d['vence']) && $d['vence'] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['vence'])) {
            return ['ok' => false, 'error' => 'La fecha de vencimiento de la licencia no es valida.'];
        }
        $d['vence'] = $d['vence'] ?? null;
        $d['instalacion'] = $d['instalacion'] ?? null;
        return ['ok' => true, 'datos' => $d];
    }

    private static function dias(string $desde, string $hasta): int
    {
        return (int)round((strtotime($hasta . ' 00:00:00') - strtotime($desde . ' 00:00:00')) / 86400);
    }

    // ------------------------------------------------------------------ estado
    public static function estado(bool $forzar = false): array
    {
        if (self::$cache !== null && !$forzar) return self::$cache;
        try {
            $e = self::calcular();
        } catch (Throwable $ex) {
            // nunca dejar el sistema inutilizable por un fallo del propio licenciamiento
            error_log('[SIGTI] Licencia::estado ' . $ex->getMessage());
            $e = self::armar('activa', 'ok', false, 'No se pudo comprobar la licencia.', null);
        }
        return self::$cache = $e;
    }

    public static function limpiarCache(): void { self::$cache = null; }

    private static function armar(string $estado, string $nivel, bool $lectura, string $msg, ?int $dias, array $extra = []): array
    {
        return array_merge([
            'estado' => $estado, 'nivel' => $nivel, 'solo_lectura' => $lectura, 'mensaje' => $msg, 'dias' => $dias,
            'licencia_id' => null, 'empresa' => null, 'ruc' => null, 'vence' => null, 'permanente' => false,
            'emitida' => null, 'rubro_licencia' => null, 'prueba_hasta' => null,
        ], $extra);
    }

    private static function calcular(): array
    {
        self::asegurar();
        $hoy = date('Y-m-d');
        $inst = self::instalacionId();

        // reloj: si la fecha del servidor retrocede varios dias se considera manipulada
        $ult = self::cfg('licencia_ultima_fecha');
        $reloj = false;
        if ($ult && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ult)) {
            if (self::dias($ult, $hoy) < -2) $reloj = true;
            elseif ($hoy > $ult) self::setCfg('licencia_ultima_fecha', $hoy);
        } else {
            self::setCfg('licencia_ultima_fecha', $hoy);
        }
        $primer = self::cfg('licencia_primer_uso');
        if (!$primer || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $primer)) { $primer = $hoy; self::setCfg('licencia_primer_uso', $hoy); }

        $row = Database::getOne('SELECT token FROM sistema_licencia ORDER BY id DESC LIMIT 1');
        $base = ['instalacion' => $inst];

        if (!$row || trim((string)$row['token']) === '') {
            $fin = date('Y-m-d', strtotime($primer . ' +' . self::DIAS_PRUEBA . ' days'));
            $rest = self::dias($hoy, $fin);
            if ($reloj) return self::armar('invalida', 'critico', true, 'La fecha del servidor es anterior a la ultima registrada. Corrija la fecha del equipo.', null, $base);
            if ($rest >= 0) {
                return self::armar('prueba', $rest <= 5 ? 'aviso' : 'info', false,
                    $rest === 0 ? 'Periodo de prueba: hoy es el ultimo dia. Active su licencia.' : 'Periodo de prueba: quedan ' . $rest . ' dia(s). Active su licencia para continuar sin interrupciones.',
                    $rest, $base + ['prueba_hasta' => $fin]);
            }
            return self::armar('sin_licencia', 'critico', true, 'El periodo de prueba termino. El sistema esta en modo solo lectura: active una licencia para volver a registrar informacion.', $rest, $base + ['prueba_hasta' => $fin]);
        }

        $v = self::verificar((string)$row['token']);
        if (!$v['ok']) return self::armar('invalida', 'critico', true, $v['error'], null, $base);
        $d = $v['datos'];
        $extra = $base + [
            'licencia_id' => $d['id'], 'empresa' => $d['empresa'], 'ruc' => $d['ruc'], 'vence' => $d['vence'],
            'permanente' => $d['vence'] === null, 'emitida' => $d['emitida'] ?? null, 'rubro_licencia' => $d['rubro'] ?? null,
        ];
        if (!empty($d['instalacion']) && strtoupper((string)$d['instalacion']) !== $inst) {
            return self::armar('invalida', 'critico', true, 'Esta licencia fue emitida para otra instalacion (' . $d['instalacion'] . '), no para esta copia (' . $inst . ').', null, $extra);
        }
        $rucCfg = preg_replace('/\s+/', '', (string)self::cfg('empresa_ruc', ''));
        if ($rucCfg !== '' && $rucCfg !== preg_replace('/\s+/', '', $d['ruc'])) {
            return self::armar('invalida', 'critico', true, 'El RUC configurado (' . $rucCfg . ') no coincide con el de la licencia (' . $d['ruc'] . ').', null, $extra);
        }
        if ($reloj) return self::armar('invalida', 'critico', true, 'La fecha del servidor es anterior a la ultima registrada. Corrija la fecha del equipo.', null, $extra);

        if ($d['vence'] === null) return self::armar('activa', 'ok', false, 'Licencia permanente vigente.', null, $extra);
        $dias = self::dias($hoy, $d['vence']);
        if ($dias > self::DIAS_AVISO) return self::armar('activa', 'ok', false, 'Licencia vigente hasta el ' . date('d/m/Y', strtotime($d['vence'])) . '.', $dias, $extra);
        if ($dias >= 0) return self::armar('por_vencer', 'aviso', false,
            $dias === 0 ? 'Su licencia vence HOY. Renueve para no interrumpir el servicio.' : 'Su licencia vence en ' . $dias . ' dia(s) (' . date('d/m/Y', strtotime($d['vence'])) . '). Solicite la renovacion.', $dias, $extra);
        $pasados = -$dias;
        if ($pasados <= self::DIAS_GRACIA) return self::armar('gracia', 'critico', false,
            'Su licencia vencio hace ' . $pasados . ' dia(s). Quedan ' . (self::DIAS_GRACIA - $pasados) . ' dia(s) de gracia; luego el sistema pasara a solo lectura.', $dias, $extra);
        return self::armar('vencida', 'critico', true, 'Su licencia vencio el ' . date('d/m/Y', strtotime($d['vence'])) . '. El sistema esta en modo solo lectura: active la licencia renovada para volver a registrar.', $dias, $extra);
    }

    public static function soloLectura(): bool { return (bool)self::estado()['solo_lectura']; }

    /** Datos para el navegador (todas las personas que ingresan). */
    public static function resumenCliente(): array
    {
        $e = self::estado();
        return [
            'licencia' => [
                'estado' => $e['estado'], 'nivel' => $e['nivel'], 'solo_lectura' => $e['solo_lectura'],
                'mensaje' => $e['mensaje'], 'dias' => $e['dias'],
            ],
            'empresa' => ['nombre' => Empresa::nombre(), 'siglas' => Empresa::siglas()],
            'rubro' => Rubros::actual(),
            'modulos_off' => self::modulosOff(),
            'terminos' => Rubros::terminos(),
        ];
    }

    // ------------------------------------------------------------------ activacion
    /** @return array{ok:bool, error?:string, estado?:array} */
    public static function activar(string $token, int $usuarioId = 0): array
    {
        $v = self::verificar($token);
        if (!$v['ok']) return $v;
        $d = $v['datos'];
        $inst = self::instalacionId();
        if (!empty($d['instalacion']) && strtoupper((string)$d['instalacion']) !== $inst) {
            return ['ok' => false, 'error' => 'Esta licencia es para otra instalacion (' . $d['instalacion'] . '). El codigo de esta instalacion es ' . $inst . '.'];
        }
        $rucLic = preg_replace('/\s+/', '', $d['ruc']);
        $rucCfg = preg_replace('/\s+/', '', (string)self::cfg('empresa_ruc', ''));
        if ($rucCfg !== '' && $rucCfg !== $rucLic) {
            return ['ok' => false, 'error' => 'El RUC de la licencia (' . $rucLic . ') no coincide con el RUC registrado en este sistema (' . $rucCfg . ').'];
        }
        if ($d['vence'] !== null && self::dias(date('Y-m-d'), $d['vence']) < -self::DIAS_GRACIA) {
            return ['ok' => false, 'error' => 'Esta licencia ya vencio el ' . date('d/m/Y', strtotime($d['vence'])) . '. Solicite una vigente.'];
        }
        self::asegurar();
        $antes = self::estado(true);
        Database::run('DELETE FROM sistema_licencia');
        Database::insert('sistema_licencia', [
            'token' => preg_replace('/\s+/', '', $token), 'licencia_id' => (string)$d['id'],
            'activada_en' => date('Y-m-d H:i:s'), 'activada_por' => $usuarioId ?: null,
        ]);
        // datos de la empresa segun la licencia
        self::setCfg('empresa_nombre', (string)$d['empresa'], 'Nombre de la empresa (licencia)');
        self::setCfg('empresa_ruc', $rucLic, 'RUC de la empresa (licencia)');
        if (!empty($d['siglas'])) self::setCfg('empresa_siglas', (string)$d['siglas'], 'Siglas de la empresa');
        Empresa::ajustarFormatos();
        // el rubro de la licencia se aplica solo la primera vez
        if (!empty($d['rubro']) && !Rubros::actual() && Rubros::existe((string)$d['rubro'])) Rubros::aplicar((string)$d['rubro']);
        self::limpiarCache();
        $nuevo = self::estado(true);
        try {
            Auditoria::registrar('actualizar', 'configuracion', 0, ['licencia' => $antes['estado']],
                ['licencia' => $d['id'], 'empresa' => $d['empresa'], 'vence' => $d['vence'] ?? 'permanente'], ['modulo' => 'licencia']);
        } catch (Throwable $e) { /* la auditoria no debe impedir activar */ }
        return ['ok' => true, 'estado' => $nuevo];
    }

    // ------------------------------------------------------------------ modulos
    public static function modulosOff(): array
    {
        $c = (string)self::cfg('modulos_off', '');
        $l = array_filter(array_map('trim', explode(',', $c)), fn($m) => isset(self::MODULOS[$m]));
        return array_values($l);
    }

    public static function moduloActivo(string $m): bool { return !in_array($m, self::modulosOff(), true); }

    public static function guardarModulosOff(array $off): void
    {
        $off = array_values(array_intersect(array_keys(self::MODULOS), array_map('strval', $off)));
        self::setCfg('modulos_off', implode(',', $off), 'Modulos apagados para esta empresa');
    }

    // ------------------------------------------------------------------ guardia de cada peticion
    public static function guardia(): void
    {
        if (PHP_SAPI === 'cli') return;
        try {
            $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            if (!preg_match('#/(api|views)/([a-z0-9_\-]+)/([a-z0-9_\-]+)\.php$#i', $path, $m)) return;
            [, $zona, $dir, $arch] = $m;
            $arch = strtolower($arch);
            $dir = strtolower($dir);
            if ($dir === 'auth' || $dir === 'licencia') return;

            // modulo apagado para esta empresa
            foreach (self::MODULOS as $clave => $def) {
                if (in_array($dir, $def[1], true) && !self::moduloActivo($clave)) {
                    if ($zona === 'views') {
                        http_response_code(403);
                        echo '<div class="alert alert-warning m-3">Este modulo (' . htmlspecialchars($def[0]) . ') no esta habilitado para su empresa.</div>';
                        exit;
                    }
                    Response::error('Este modulo (' . $def[0] . ') no esta habilitado para su empresa.', ['modulo' => $clave], 403);
                }
            }

            // solo lectura
            if ($zona !== 'api') return;
            $metodo = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            if (in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) return;
            if ($dir === 'notificaciones' && $arch === 'marcar') return;
            if (isset($_POST['draw']) || in_array($arch, self::POST_DE_LECTURA, true)) return;
            if (self::soloLectura()) {
                $e = self::estado();
                Response::error($e['mensaje'], ['licencia' => 'solo_lectura'], 403);
            }
        } catch (Throwable $ex) {
            error_log('[SIGTI] Licencia::guardia ' . $ex->getMessage());
        }
    }
}
