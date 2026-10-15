<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - WhatsApp: envio de avisos por WhatsApp
//
//   * SIGTI (PHP) NO habla el protocolo de WhatsApp. Eso lo hace un servicio
//     aparte en Node.js (carpeta whatsapp-bridge/, fuera de este archivo),
//     vinculado a un numero real por codigo QR (como WhatsApp Web).
//   * Esta clase es solo un cliente HTTP hacia ese puente: le pide el
//     estado (vinculado si/no, el QR para vincular) y le pide enviar
//     mensajes, con un token compartido para que nadie mas lo use.
//   * La configuracion (direccion del puente + token + activo) vive en la
//     tabla "configuracion" (clave/valor), igual que core/Correo.php y
//     core/Documento.php.
//   * Nunca rompe el flujo que la llama: enviarSilencioso() atrapa
//     cualquier error y solo lo deja en el log.
// ============================================================

class WhatsAppError extends Exception
{
}

class WhatsApp
{
    private const CFG_URL    = 'whatsapp_bridge_url';   // ej: http://127.0.0.1:3300
    private const CFG_TOKEN  = 'whatsapp_token';
    private const CFG_ACTIVO = 'whatsapp_activo';

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
        return [
            'url'    => rtrim(self::cfgGet(self::CFG_URL), '/'),
            'token'  => self::cfgGet(self::CFG_TOKEN),
            'activo' => self::cfgGet(self::CFG_ACTIVO) === '1',
        ];
    }

    /** $token null = conservar el actual; '' = borrarlo (y desactiva). */
    public static function guardarConfig(string $url, ?string $token, bool $activo): void
    {
        self::cfgSet(self::CFG_URL, rtrim(trim($url), '/'), 'Dirección del puente de WhatsApp (ej: http://127.0.0.1:3300)');
        if ($token !== null) self::cfgSet(self::CFG_TOKEN, $token, 'Token compartido con el puente de WhatsApp');
        self::cfgSet(self::CFG_ACTIVO, $activo ? '1' : '0', 'WhatsApp activo: 1 = enviar anuncios también por WhatsApp');
    }

    public static function configurado(): bool
    {
        $c = self::config();
        return $c['url'] !== '' && $c['token'] !== '';
    }

    public static function activo(): bool
    {
        $c = self::config();
        return $c['activo'] && self::configurado();
    }

    // ------------------------------------------------------------ cliente HTTP hacia el puente

    private static function llamar(string $ruta, array $datos = [], string $metodo = 'GET'): array
    {
        $c = self::config();
        if ($c['url'] === '') throw new WhatsAppError('No se configuró la dirección del puente de WhatsApp.');
        if (!function_exists('curl_init')) throw new WhatsAppError('La extensión cURL de PHP no está activa.');

        $url = $c['url'] . '/' . ltrim($ruta, '/');
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $c['token'], 'Content-Type: application/json'],
        ];
        if ($metodo === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($datos, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new WhatsAppError('No se pudo conectar con el puente de WhatsApp (' . $err . '). ¿Está encendido el servicio de Node.js?');
        }
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $resp, true);
        if (!is_array($data)) {
            throw new WhatsAppError('El puente de WhatsApp respondió algo inesperado (código ' . $code . ').');
        }
        if ($code === 401 || $code === 403) {
            throw new WhatsAppError('El puente de WhatsApp rechazó el token. Revise que coincida con el configurado ahí.');
        }
        if ($code >= 400) {
            throw new WhatsAppError((string) ($data['error'] ?? ('El puente de WhatsApp devolvió un error (código ' . $code . ').')));
        }
        return $data;
    }

    /** ['vinculado'=>bool, 'numero'=>?string, 'qr'=>?string (data URL), 'configurado'=>bool, 'error'=>?string] */
    public static function estado(): array
    {
        if (!self::configurado()) {
            return ['vinculado' => false, 'numero' => null, 'qr' => null, 'configurado' => false, 'error' => null];
        }
        try {
            $r = self::llamar('estado');
            return [
                'vinculado'   => !empty($r['vinculado']),
                'numero'      => $r['numero'] ?? null,
                'qr'          => $r['qr'] ?? null,
                'configurado' => true,
                'error'       => null,
            ];
        } catch (Throwable $e) {
            return ['vinculado' => false, 'numero' => null, 'qr' => null, 'configurado' => true, 'error' => $e->getMessage()];
        }
    }

    public static function desvincular(): void
    {
        self::llamar('desvincular', [], 'POST');
    }

    /** Intenta dejar el numero en formato internacional (Peru por defecto: 51 + 9 digitos). */
    public static function normalizarNumero(?string $n): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $n);
        if ($d === '') return null;
        if (strlen($d) === 9 && $d[0] === '9') return '51' . $d;        // celular peruano sin codigo de pais
        if (strlen($d) >= 10 && strlen($d) <= 15) return $d;            // ya viene con codigo de pais
        return null;
    }

    /** Lanza WhatsAppError si falla (para el botón "Probar" y para quien quiera manejarlo). */
    public static function enviar(string $numero, string $mensaje): void
    {
        if (!self::activo()) throw new WhatsAppError('WhatsApp no está activado.');
        $n = self::normalizarNumero($numero);
        if ($n === null) throw new WhatsAppError("Número de WhatsApp inválido: $numero");
        self::llamar('enviar', ['numero' => $n, 'mensaje' => $mensaje], 'POST');
    }

    /** No lanza excepción: para usar desde flujos automáticos (anuncios) sin romper la operación principal. */
    public static function enviarSilencioso(string $numero, string $mensaje): bool
    {
        try {
            self::enviar($numero, $mensaje);
            return true;
        } catch (Throwable $e) {
            error_log('[SIGTI] WhatsApp a ' . $numero . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Varios envios seguidos (el puente ya mantiene la sesion abierta: no hace falta "una sola conexion" aqui). */
    public static function enviarLote(array $destinatarios, string $mensaje): array
    {
        $r = ['enviados' => 0, 'fallidos' => 0];
        if (!self::activo()) return $r;
        foreach ($destinatarios as $d) {
            if (empty($d['telefono'])) continue;
            if (self::enviarSilencioso($d['telefono'], $mensaje)) $r['enviados']++; else $r['fallidos']++;
        }
        return $r;
    }
}
