<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Documento: validar DNI / RUC y consultar sus datos
//
//  * dniValido / rucValido : validacion local (RUC con digito verificador, modulo 11)
//  * consultar()           : trae los datos de SUNAT (RUC) o RENIEC (DNI) usando
//                            un servicio externo con token (apiperu.dev o Decolecta).
//                            El token lo configura el administrador (tabla configuracion).
//  * Cada consulta correcta se guarda 7 dias (storage/cache-documentos) para no gastar
//    la cuota del servicio ni demorar al usuario.
//
// Nada de esto se ejecuta en el navegador: el token nunca sale del servidor.
// ============================================================

class DocumentoError extends Exception
{
    /** no_configurado | token_invalido | no_encontrado | limite | servicio_no_disponible | ssl | respuesta_invalida | red */
    public string $codigo;

    public function __construct(string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
        $this->codigo = $codigo;
    }
}

class Documento
{
    const CFG_SERVICIO = 'consulta_doc_servicio';   // '' | apiperu | decolecta
    const CFG_TOKEN    = 'consulta_doc_token';
    const CFG_CACERT   = 'consulta_doc_cacert';     // ruta opcional a cacert.pem (XAMPP/Windows)

    const SERVICIOS = [
        'apiperu'   => 'apiperu.dev',
        'decolecta' => 'Decolecta',
    ];
    const VIGENCIA_CACHE = 7 * 86400;               // 7 dias

    // ---------------------------------------------------------------- validacion

    public static function limpiar(?string $n): string
    {
        return preg_replace('/\D+/', '', (string)$n);
    }

    public static function dniValido(string $n): bool
    {
        return (bool)preg_match('/^\d{8}$/', $n);
    }

    /** RUC: 11 digitos, prefijo SUNAT valido y digito verificador correcto (modulo 11). */
    public static function rucValido(string $n): bool
    {
        return self::rucError($n) === null;
    }

    /** null si es valido; si no, el motivo en texto. */
    public static function rucError(string $n): ?string
    {
        if (!preg_match('/^\d{11}$/', $n)) {
            return 'El RUC debe tener 11 dígitos.';
        }
        if (!in_array(substr($n, 0, 2), ['10', '15', '16', '17', '20'], true)) {
            return 'El RUC debe empezar con 10, 15, 16, 17 o 20.';
        }
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) $suma += (int)$n[$i] * $pesos[$i];
        $dv = 11 - ($suma % 11);
        if ($dv === 10) $dv = 0;
        elseif ($dv === 11) $dv = 1;
        if ($dv !== (int)$n[10]) {
            return 'RUC no válido: el dígito verificador no coincide. Revise el número.';
        }
        return null;
    }

    // ---------------------------------------------------------------- configuracion

    private static function cfgGet(string $k): string
    {
        return (string) (Database::getValue('SELECT valor FROM configuracion WHERE clave = ?', [$k]) ?? '');
    }

    private static function cfgSet(string $k, string $v, string $desc): void
    {
        if (Database::getValue('SELECT id FROM configuracion WHERE clave = ?', [$k]) !== null) {
            Database::update('configuracion', ['valor' => $v], 'clave = ?', [$k]);
        } else {
            Database::insert('configuracion', ['clave' => $k, 'valor' => $v, 'descripcion' => $desc]);
        }
    }

    public static function config(): array
    {
        $s = self::cfgGet(self::CFG_SERVICIO);
        return [
            'servicio' => isset(self::SERVICIOS[$s]) ? $s : '',
            'token'    => self::cfgGet(self::CFG_TOKEN),
            'cacert'   => self::cfgGet(self::CFG_CACERT),
        ];
    }

    /** $token null = conservar el actual; '' = borrarlo. */
    public static function guardarConfig(string $servicio, ?string $token, string $cacert): void
    {
        self::cfgSet(self::CFG_SERVICIO, $servicio, 'Servicio de consulta DNI/RUC (apiperu|decolecta)');
        if ($token !== null) self::cfgSet(self::CFG_TOKEN, $token, 'Token del servicio de consulta DNI/RUC');
        self::cfgSet(self::CFG_CACERT, $cacert, 'Ruta opcional de cacert.pem para consultas HTTPS');
    }

    public static function configurado(): bool
    {
        $c = self::config();
        return $c['servicio'] !== '' && $c['token'] !== '';
    }

    // ---------------------------------------------------------------- consulta

    private static function base(string $servicio): string
    {
        // Solo para pruebas automaticas: define SIGTI_DOC_BASES (nunca se define en produccion)
        if (defined('SIGTI_DOC_BASES') && isset(SIGTI_DOC_BASES[$servicio])) return SIGTI_DOC_BASES[$servicio];
        return $servicio === 'decolecta' ? 'https://api.decolecta.com' : 'https://api.apiperu.dev';
    }

    private static function archivoCache(string $tipo, string $numero): string
    {
        return STORAGE_PATH . '/cache-documentos/' . $tipo . '_' . $numero . '.json';
    }

    /**
     * Devuelve los datos normalizados:
     *  RUC: tipo, numero, nombre (razon social), direccion, estado, condicion, activo_habido
     *  DNI: tipo, numero, nombres, apellido_paterno, apellido_materno, apellidos, nombre
     *  + servicio, desde_cache
     * $opciones['sin_cache'] = true fuerza una consulta nueva.
     * @throws DocumentoError
     */
    public static function consultar(string $tipo, string $numero, array $opciones = []): array
    {
        if ($tipo !== 'dni' && $tipo !== 'ruc') throw new DocumentoError('respuesta_invalida', 'Tipo de documento inválido.');
        if ($tipo === 'dni' ? !self::dniValido($numero) : !self::rucValido($numero)) {
            throw new DocumentoError('respuesta_invalida', 'Número de documento inválido.');
        }

        $cfg = self::config();
        if ($cfg['servicio'] === '' || $cfg['token'] === '') {
            throw new DocumentoError('no_configurado',
                'No hay un servicio de consulta configurado. Un administrador debe indicar el servicio y su token.');
        }

        // ---- cache
        $arch = self::archivoCache($tipo, $numero);
        if (empty($opciones['sin_cache']) && is_file($arch) && (time() - (int)@filemtime($arch)) < self::VIGENCIA_CACHE) {
            $c = json_decode((string)@file_get_contents($arch), true);
            if (is_array($c) && ($c['servicio'] ?? '') === $cfg['servicio']) {
                $c['desde_cache'] = true;
                return $c;
            }
        }

        $datos = ($cfg['servicio'] === 'decolecta')
            ? self::consultarDecolecta($tipo, $numero, $cfg)
            : self::consultarApiPeru($tipo, $numero, $cfg);
        $datos['servicio'] = $cfg['servicio'];
        $datos['desde_cache'] = false;

        // ---- guardar en cache (si falla, no importa)
        $dir = dirname($arch);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($arch, json_encode($datos, JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $datos;
    }

    private static function consultarApiPeru(string $tipo, string $numero, array $cfg): array
    {
        $r = self::http('POST', self::base('apiperu') . '/' . $tipo,
            ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $cfg['token']],
            json_encode([$tipo => $numero]), $cfg['cacert']);
        $j = self::resolver($r, 'apiperu');
        $d = $j['data'] ?? null;
        if (empty($j['success']) || !is_array($d)) {
            throw new DocumentoError('respuesta_invalida', (string)($j['message'] ?? 'Respuesta inesperada del servicio.'));
        }

        if ($tipo === 'ruc') {
            return self::normalizarRuc($numero, $d['nombre_o_razon_social'] ?? '', $d['direccion'] ?? '',
                [$d['distrito'] ?? '', $d['provincia'] ?? '', $d['departamento'] ?? ''],
                $d['estado'] ?? '', $d['condicion'] ?? '');
        }
        return self::normalizarDni($numero, $d['nombres'] ?? '', $d['apellido_paterno'] ?? '', $d['apellido_materno'] ?? '');
    }

    private static function consultarDecolecta(string $tipo, string $numero, array $cfg): array
    {
        $ruta = $tipo === 'ruc' ? '/v1/sunat/ruc' : '/v1/reniec/dni';
        $r = self::http('GET', self::base('decolecta') . $ruta . '?numero=' . rawurlencode($numero),
            ['Accept: application/json', 'Authorization: Bearer ' . $cfg['token']], null, $cfg['cacert']);
        $d = self::resolver($r, 'decolecta');

        if ($tipo === 'ruc') {
            if (empty($d['razon_social'])) throw new DocumentoError('no_encontrado', 'El RUC no existe en SUNAT.');
            return self::normalizarRuc($numero, $d['razon_social'], $d['direccion'] ?? '',
                [$d['distrito'] ?? '', $d['provincia'] ?? '', $d['departamento'] ?? ''],
                $d['estado'] ?? '', $d['condicion'] ?? '');
        }
        if (empty($d['first_name']) && empty($d['first_last_name'])) {
            throw new DocumentoError('no_encontrado', 'El DNI no existe en RENIEC.');
        }
        return self::normalizarDni($numero, $d['first_name'] ?? '', $d['first_last_name'] ?? '', $d['second_last_name'] ?? '');
    }

    private static function normalizarRuc(string $numero, $razon, $direccion, array $zona, $estado, $condicion): array
    {
        $direccion = trim((string)$direccion);
        if ($direccion === '-') $direccion = '';          // SUNAT usa "-" cuando no hay direccion
        $partes = [$direccion];
        foreach ($zona as $z) {
            $z = trim((string)$z);
            if ($z !== '' && $z !== '-' && stripos($direccion, $z) === false && !in_array($z, $partes, true)) $partes[] = $z;
        }
        $estado = strtoupper(trim((string)$estado));
        $condicion = strtoupper(trim((string)$condicion));
        return [
            'tipo' => 'ruc', 'numero' => $numero,
            'nombre' => trim((string)$razon),
            'direccion' => mb_substr(trim(implode(' - ', array_filter($partes, fn($x) => $x !== ''))), 0, 200),
            'estado' => $estado, 'condicion' => $condicion,
            'activo_habido' => ($estado === 'ACTIVO' && $condicion === 'HABIDO'),
        ];
    }

    private static function normalizarDni(string $numero, $nombres, $paterno, $materno): array
    {
        $nombres = trim((string)$nombres);
        $apellidos = trim(trim((string)$paterno) . ' ' . trim((string)$materno));
        return [
            'tipo' => 'dni', 'numero' => $numero,
            'nombres' => $nombres,
            'apellido_paterno' => trim((string)$paterno), 'apellido_materno' => trim((string)$materno),
            'apellidos' => $apellidos,
            'nombre' => trim($nombres . ' ' . $apellidos),
        ];
    }

    /** Convierte la respuesta HTTP en JSON o lanza el error que corresponde. */
    private static function resolver(array $r, string $servicio): array
    {
        if ($r['errno'] !== 0) {
            if (in_array($r['errno'], [35, 51, 58, 60, 77, 83], true)) {
                throw new DocumentoError('ssl',
                    'No se pudo verificar el certificado HTTPS del servicio (configuración de PHP/XAMPP). '
                    . 'Descargue cacert.pem desde curl.se/docs/caextract.html e indique su ruta en la configuración del servicio '
                    . '(o en curl.cainfo de php.ini).');
            }
            throw new DocumentoError('red', 'No se pudo conectar con el servicio de consulta (' . $r['error'] . '). Revise la conexión a internet.');
        }
        $j = json_decode($r['cuerpo'], true);
        $msg = is_array($j) ? (string)($j['message'] ?? $j['error'] ?? '') : '';
        $cod = $r['codigo'];

        if ($cod === 401 || $cod === 403) {
            throw new DocumentoError('token_invalido', 'El servicio rechazó el token (inválido, vencido o sin permisos). Revíselo en la configuración.');
        }
        if ($cod === 404) {
            throw new DocumentoError('no_encontrado', $msg !== '' ? $msg : 'El documento no existe.');
        }
        if ($cod === 429 || ($cod === 402)) {
            throw new DocumentoError('limite', 'Se agotó la cuota de consultas del servicio. Intente más tarde o revise su plan.');
        }
        if ($cod >= 500) {
            throw new DocumentoError('servicio_no_disponible', 'El servicio de consulta no está disponible por el momento. Intente de nuevo en unos minutos.');
        }
        if ($cod >= 400) {
            throw new DocumentoError('respuesta_invalida', $msg !== '' ? $msg : 'El servicio no aceptó la consulta (código ' . $cod . ').');
        }
        if (!is_array($j)) {
            throw new DocumentoError('respuesta_invalida', 'El servicio devolvió una respuesta que no se entiende.');
        }
        return $j;
    }

    /** @return array{codigo:int,cuerpo:string,errno:int,error:string} */
    private static function http(string $metodo, string $url, array $cabeceras, ?string $cuerpo, string $cacert): array
    {
        if (!function_exists('curl_init')) {
            throw new DocumentoError('red', 'PHP no tiene habilitada la extensión cURL (actívela en php.ini: extension=curl).');
        }
        $ch = curl_init($url);
        $op = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_USERAGENT      => 'SIGTI/1.0',
            CURLOPT_SSL_VERIFYPEER => true,        // nunca se desactiva la verificacion
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($metodo === 'POST') {
            $op[CURLOPT_POST] = true;
            $op[CURLOPT_POSTFIELDS] = (string)$cuerpo;
        }
        if ($cacert !== '' && is_file($cacert)) $op[CURLOPT_CAINFO] = $cacert;
        curl_setopt_array($ch, $op);
        $cuerpoResp = curl_exec($ch);
        $res = [
            'codigo' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'cuerpo' => is_string($cuerpoResp) ? $cuerpoResp : '',
            'errno'  => curl_errno($ch),
            'error'  => curl_error($ch),
        ];
        curl_close($ch);
        return $res;
    }
}
