<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Correo: envio de correo por SMTP (sin PHPMailer ni composer)
//
//   * La configuracion vive en la tabla "configuracion" (clave/valor), igual
//     que el servicio de consulta DNI/RUC (ver core/Documento.php).
//   * Habla SMTP a mano (EHLO, STARTTLS u SSL directo, AUTH LOGIN, DATA) por
//     un socket con stream_socket_client(); no hace falta "composer install".
//   * enviarLote() abre UNA sola conexion SMTP y manda varios mensajes
//     (uno por MAIL FROM/RCPT TO/DATA), para no reconectar por cada persona.
//   * Nunca rompe el flujo que la llama: los metodos "...Silencioso" atrapan
//     cualquier error y solo lo dejan en el log.
// ============================================================

class CorreoError extends Exception
{
}

class Correo
{
    private const CFG_HOST       = 'correo_smtp_host';
    private const CFG_PUERTO     = 'correo_smtp_puerto';
    private const CFG_SEGURIDAD  = 'correo_smtp_seguridad';   // tls | ssl | ninguna
    private const CFG_USUARIO    = 'correo_smtp_usuario';
    private const CFG_CLAVE      = 'correo_smtp_clave';
    private const CFG_REMITENTE  = 'correo_remitente';
    private const CFG_REM_NOMBRE = 'correo_remitente_nombre';
    private const CFG_CACERT     = 'correo_cacert';
    private const CFG_ACTIVO     = 'correo_activo';

    public const SEGURIDADES = ['tls' => 'STARTTLS (recomendado, puerto 587)', 'ssl' => 'SSL directo (puerto 465)', 'ninguna' => 'Sin cifrar (no recomendado)'];

    // ------------------------------------------------------------ configuracion (tabla "configuracion")

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
        $seg = self::cfgGet(self::CFG_SEGURIDAD);
        return [
            'host'             => self::cfgGet(self::CFG_HOST),
            'puerto'           => (int) (self::cfgGet(self::CFG_PUERTO) ?: 587),
            'seguridad'        => isset(self::SEGURIDADES[$seg]) ? $seg : 'tls',
            'usuario'          => self::cfgGet(self::CFG_USUARIO),
            'clave'            => self::cfgGet(self::CFG_CLAVE),
            'remitente'        => self::cfgGet(self::CFG_REMITENTE),
            'remitente_nombre' => self::cfgGet(self::CFG_REM_NOMBRE) ?: 'SIGTI',
            'cacert'           => self::cfgGet(self::CFG_CACERT),
            'activo'           => self::cfgGet(self::CFG_ACTIVO) === '1',
        ];
    }

    /** $clave null = conservar la actual; '' = borrarla. */
    public static function guardarConfig(array $d, ?string $clave): void
    {
        self::cfgSet(self::CFG_HOST, (string) ($d['host'] ?? ''), 'Servidor SMTP (ej: smtp.gmail.com)');
        self::cfgSet(self::CFG_PUERTO, (string) ((int) ($d['puerto'] ?? 587)), 'Puerto SMTP (587 STARTTLS, 465 SSL, 25 sin cifrar)');
        self::cfgSet(self::CFG_SEGURIDAD, isset(self::SEGURIDADES[$d['seguridad'] ?? '']) ? $d['seguridad'] : 'tls', 'Seguridad SMTP: tls | ssl | ninguna');
        self::cfgSet(self::CFG_USUARIO, (string) ($d['usuario'] ?? ''), 'Usuario / correo SMTP');
        if ($clave !== null) self::cfgSet(self::CFG_CLAVE, $clave, 'Contraseña / contraseña de aplicación SMTP');
        self::cfgSet(self::CFG_REMITENTE, (string) ($d['remitente'] ?? ''), 'Correo remitente (From)');
        self::cfgSet(self::CFG_REM_NOMBRE, (string) ($d['remitente_nombre'] ?? ''), 'Nombre del remitente que ven los destinatarios');
        self::cfgSet(self::CFG_CACERT, (string) ($d['cacert'] ?? ''), 'Ruta opcional de cacert.pem (solo si TLS falla por certificado, comun en XAMPP)');
        self::cfgSet(self::CFG_ACTIVO, !empty($d['activo']) ? '1' : '0', 'Correo activo: 1 = enviar anuncios tambien por correo');
    }

    public static function activo(): bool
    {
        $c = self::config();
        return $c['activo'] && $c['host'] !== '' && $c['remitente'] !== '' && filter_var($c['remitente'], FILTER_VALIDATE_EMAIL);
    }

    // ------------------------------------------------------------ SMTP (sin dependencias)

    private static function escribir($fp, string $linea): void
    {
        if (@fwrite($fp, $linea . "\r\n") === false) {
            throw new CorreoError('Se perdió la conexión con el servidor de correo al escribir.');
        }
    }

    private static function leer($fp): array
    {
        $buffer = '';
        do {
            $linea = @fgets($fp, 1024);
            if ($linea === false) throw new CorreoError('El servidor de correo cerró la conexión inesperadamente.');
            $buffer .= $linea;
        } while (isset($linea[3]) && $linea[3] === '-');
        return ['codigo' => (int) substr($buffer, 0, 3), 'texto' => trim($buffer)];
    }

    private static function esperar($fp, array $codigosOk, string $etiqueta): array
    {
        $r = self::leer($fp);
        if (!in_array($r['codigo'], $codigosOk, true)) {
            throw new CorreoError($etiqueta . ': el servidor respondió "' . $r['texto'] . '".');
        }
        return $r;
    }

    /** Abre, saluda, activa TLS si corresponde y autentica. Devuelve el socket. */
    private static function conectar()
    {
        $c = self::config();
        if (!self::activo()) throw new CorreoError('El correo no está configurado o está desactivado.');
        if ($c['host'] === '') throw new CorreoError('Falta el servidor SMTP.');

        $prefijo = $c['seguridad'] === 'ssl' ? 'ssl://' : 'tcp://';
        $opciones = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
        if ($c['cacert'] !== '') $opciones['ssl']['cafile'] = $c['cacert'];
        $ctx = stream_context_create($opciones);

        $fp = @stream_socket_client($prefijo . $c['host'] . ':' . $c['puerto'], $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new CorreoError("No se pudo conectar a {$c['host']}:{$c['puerto']} ($errstr). Revise el host, el puerto y que el firewall no lo bloquee.");
        }
        stream_set_timeout($fp, 20);

        self::esperar($fp, [220], 'Conexión');
        $local = 'sigti.local';
        self::escribir($fp, 'EHLO ' . $local);
        self::esperar($fp, [250], 'EHLO');

        if ($c['seguridad'] === 'tls') {
            self::escribir($fp, 'STARTTLS');
            self::esperar($fp, [220], 'STARTTLS');
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                @fclose($fp);
                throw new CorreoError('No se pudo activar la conexión segura (TLS) con el servidor de correo.'
                    . ($c['cacert'] === '' ? ' Si el error persiste (común en XAMPP/Windows), indique la ruta de un cacert.pem en la configuración.' : ''));
            }
            self::escribir($fp, 'EHLO ' . $local);
            self::esperar($fp, [250], 'EHLO (tras TLS)');
        }

        if ($c['usuario'] !== '') {
            self::escribir($fp, 'AUTH LOGIN');
            self::esperar($fp, [334], 'AUTH LOGIN');
            self::escribir($fp, base64_encode($c['usuario']));
            self::esperar($fp, [334], 'Usuario');
            self::escribir($fp, base64_encode($c['clave']));
            self::esperar($fp, [235], 'Autenticación (revise el usuario y la contraseña de aplicación)');
        }

        return $fp;
    }

    private static function enviarMensaje($fp, string $para, ?string $nombrePara, string $asunto, string $texto): void
    {
        $c = self::config();
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) throw new CorreoError("Correo inválido: $para");

        self::escribir($fp, 'MAIL FROM:<' . $c['remitente'] . '>');
        self::esperar($fp, [250], 'MAIL FROM');
        self::escribir($fp, 'RCPT TO:<' . $para . '>');
        self::esperar($fp, [250, 251], "RCPT TO ($para)");
        self::escribir($fp, 'DATA');
        self::esperar($fp, [354], 'DATA');

        $cod = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $de = $c['remitente_nombre'] !== '' ? $cod($c['remitente_nombre']) . ' <' . $c['remitente'] . '>' : $c['remitente'];
        $a = $nombrePara ? $cod($nombrePara) . " <$para>" : $para;

        $headers = "From: $de\r\n" .
            "To: $a\r\n" .
            'Subject: ' . $cod($asunto) . "\r\n" .
            'Date: ' . date('r') . "\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n";
        $cuerpo = chunk_split(base64_encode($texto));   // ya termina en \r\n

        self::escribir($fp, $headers . "\r\n" . $cuerpo . '.');
        self::esperar($fp, [250], "Envío ($para)");
    }

    private static function cerrar($fp): void
    {
        try {
            self::escribir($fp, 'QUIT');
            self::leer($fp);
        } catch (Throwable $e) {
            // da igual: ya mandamos lo que había que mandar
        }
        @fclose($fp);
    }

    // ------------------------------------------------------------ uso publico

    /** Un solo correo. Lanza CorreoError si algo falla (para el botón "Probar"). */
    public static function enviar(string $para, string $asunto, string $texto, ?string $nombrePara = null): void
    {
        $fp = self::conectar();
        try {
            self::enviarMensaje($fp, $para, $nombrePara, $asunto, $texto);
        } finally {
            self::cerrar($fp);
        }
    }

    public static function enviarPrueba(string $para): void
    {
        self::enviar($para, 'Correo de prueba — SIGTI',
            "Este es un correo de prueba enviado desde SIGTI.\nSi lo recibió, la configuración de correo está funcionando correctamente.");
    }

    /**
     * Varios correos en UNA sola conexión SMTP.
     * $destinatarios: [['correo'=>string, 'nombre'=>?string], ...]
     * Nunca lanza excepción: un destinatario que falla no afecta a los demás.
     */
    public static function enviarLote(array $destinatarios, string $asunto, string $texto): array
    {
        $r = ['enviados' => 0, 'fallidos' => 0];
        $destinatarios = array_values(array_filter($destinatarios, fn($d) => !empty($d['correo'])));
        if (!self::activo() || !$destinatarios) return $r;

        try {
            $fp = self::conectar();
        } catch (Throwable $e) {
            error_log('[SIGTI] Correo (lote, conexión): ' . $e->getMessage());
            $r['fallidos'] = count($destinatarios);
            return $r;
        }
        foreach ($destinatarios as $d) {
            try {
                self::enviarMensaje($fp, $d['correo'], $d['nombre'] ?? null, $asunto, $texto);
                $r['enviados']++;
            } catch (Throwable $e) {
                error_log('[SIGTI] Correo a ' . $d['correo'] . ': ' . $e->getMessage());
                $r['fallidos']++;
            }
        }
        self::cerrar($fp);
        return $r;
    }
}
