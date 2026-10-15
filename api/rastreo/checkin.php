<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - CHECK-IN de rastreo del equipo
// El agente del equipo llama a esta URL cada pocos minutos con su token.
//
//   Recomendado (POST, el token NO queda en la URL ni en los logs):
//     POST api/rastreo/checkin.php   token=...&ip_local=...&mac=...&hostname=...
//          opcionales: ip_publica, wifi, lat, lon, precision
//   Compatible con el agente .bat antiguo (GET con los mismos campos).
//
// Los datos son OBSERVACIONES del agente: no modifican el inventario.
// Orden para ubicar el equipo: posicion del equipo (gps) > sede conocida > IP publica (aprox.)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Rastreo::asegurar();

    // NO requiere sesion: el equipo se autentica con su token
    $in = array_merge($_GET, $_POST);       // si llega por ambos, manda POST
    $token = trim((string)($in['token'] ?? ''));
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        Response::error('Token inválido.');
    }

    $config = Database::getOne(
        'SELECT * FROM equipos_rastreo_config WHERE token_rastreo = ? AND activo = 1', [$token]);
    if (!$config) {
        Response::error('Token no registrado o inactivo.');
    }
    $equipoId = (int)$config['equipo_id'];
    $equipo = Database::getOne('SELECT id, codigo, estado, mac FROM equipos WHERE id = ?', [$equipoId]);
    if (!$equipo) Response::error('El equipo ya no existe.');

    // ---- recoger lo que informa el equipo ----
    $ipConexion = Geolocalizacion::ipReal();                 // lo que ve el servidor
    $ipPublica  = null;
    if (Rastreo::esIpPublica($ipConexion)) {
        $ipPublica = $ipConexion;
    } else {
        // el servidor esta en la misma red del equipo: la IP publica la informa el propio agente
        $declarada = trim((string)($in['ip_publica'] ?? ''));
        if (Rastreo::esIpPublica($declarada)) $ipPublica = $declarada;
    }
    $ipLocal = trim((string)($in['ip_local'] ?? ''));
    if (!filter_var($ipLocal, FILTER_VALIDATE_IP)) $ipLocal = '';
    if ($ipLocal === '' && filter_var($ipConexion, FILTER_VALIDATE_IP) && Geolocalizacion::esInterna($ipConexion)
        && !in_array($ipConexion, ['127.0.0.1', '::1', '0.0.0.0'], true)) {
        $ipLocal = $ipConexion;                              // el servidor la ve directamente
    }
    $mac       = Rastreo::normalizarMac($in['mac'] ?? '');
    $hostname  = mb_substr(preg_replace('/[^A-Za-z0-9._\- ]/', '', (string)($in['hostname'] ?? '')), 0, 100);
    $wifi      = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string)($in['wifi'] ?? ''))), 0, 120);
    $userAgent = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    $coords    = Rastreo::coordenadas($in['lat'] ?? null, $in['lon'] ?? null);
    $precision = isset($in['precision']) && is_numeric($in['precision'])
        ? max(0, min(100000, (int)$in['precision'])) : null;

    // ---- no llenar la tabla si el agente llama sin parar ----
    $previo = Database::getOne(
        'SELECT id, created_at, sede_id, mac_coincide FROM equipos_rastreo WHERE equipo_id = ? ORDER BY id DESC LIMIT 1',
        [$equipoId]);
    if ($previo && strtotime($previo['created_at']) > time() - 20) {
        Response::ok([
            'perdido'       => (int)$config['modo_perdido'],
            'siguiente_min' => (int)$config['modo_perdido'] === 1 ? 1 : 5,
            'omitido'       => true,
        ], 'Check-in reciente: no se repite.');
    }

    // ---- donde esta ----
    $sede = Rastreo::sedePara($ipPublica, $ipLocal);
    $geo  = $ipPublica ? Geolocalizacion::datosCompletos($ipPublica) : null;
    $textoGeo = null;
    if ($geo) {
        $textoGeo = trim(($geo['ciudad'] ?? '') . (($geo['pais'] ?? '') !== '' ? ', ' . $geo['pais'] : ''), ', ');
        if (!empty($geo['isp'])) $textoGeo .= ($textoGeo !== '' ? ' ' : '') . '(' . $geo['isp'] . ')';
    }

    $fuente = 'red'; $ubicacion = null; $lat = null; $lon = null;
    // Windows sin GPS estima la posicion por Wi-Fi/IP con margenes de varios kilometros: eso NO es la direccion del equipo
    $posImprecisa = $coords && $precision !== null && $precision > Rastreo::PRECISION_UTIL_M;
    if ($coords && !$posImprecisa) {
        $fuente = 'gps';
        [$lat, $lon] = $coords;
        $ubicacion = $sede ? $sede['nombre']
            : ($textoGeo ? 'Cerca de ' . $textoGeo : 'Posición informada por el equipo');
    } elseif ($posImprecisa && !($sede && $sede['latitud'] !== null && $sede['longitud'] !== null)) {
        $fuente = 'aprox';
        [$lat, $lon] = $coords;
        $km = $precision >= 1000 ? round($precision / 1000, 1) . ' km' : $precision . ' m';
        $ubicacion = ($sede ? $sede['nombre'] . ' · ' : '') . 'Ubicación aproximada (±' . $km . ')';
    } elseif ($sede) {
        $fuente = 'sede';
        $ubicacion = $sede['nombre'];
        if ($sede['latitud'] !== null && $sede['longitud'] !== null) {
            $lat = (float)$sede['latitud']; $lon = (float)$sede['longitud'];
        } elseif ($geo) {
            $lat = $geo['latitud']; $lon = $geo['longitud'];
        }
    } elseif ($geo) {
        $fuente = 'ip';
        $ubicacion = $textoGeo ?: null;
        $lat = $geo['latitud']; $lon = $geo['longitud'];
    } elseif ($ipLocal !== '') {
        $ubicacion = 'Red interna ' . $ipLocal . ' (sede no identificada)';
    }
    if ($ubicacion !== null) $ubicacion = mb_substr($ubicacion, 0, 200);

    // ---- la MAC que informa coincide con la registrada en el inventario? ----
    $macRegistrada = Rastreo::normalizarMac($equipo['mac']);
    $macCoincide = ($mac && $macRegistrada) ? ($mac === $macRegistrada ? 1 : 0) : null;

    // ---- registrar (check-in + ultimo check-in + auditoria, todo o nada) ----
    Database::begin();
    $rastreoId = Database::insert('equipos_rastreo', [
        'equipo_id'   => $equipoId,
        'ip_publica'  => $ipPublica,
        'ip_local'    => $ipLocal ?: null,
        'mac_address' => $mac,
        'hostname'    => $hostname ?: null,
        'user_agent'  => $userAgent,
        'ubicacion'   => $ubicacion,
        'latitud'     => $lat,
        'longitud'    => $lon,
        'fuente'      => $fuente,
        'precision_m' => $fuente === 'gps' || $fuente === 'aprox' ? $precision : null,
        'wifi'        => $wifi ?: null,
        'sede_id'     => $sede ? (int)$sede['id'] : null,
        'mac_coincide'=> $macCoincide,
        // hora de PHP (la misma con la que se calcula "en linea" y el antiflood), no la del motor de base de datos
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
    Database::update('equipos_rastreo_config', ['ultimo_checkin' => date('Y-m-d H:i:s')], 'equipo_id = ?', [$equipoId]);

    $registrado = Auditoria::registrar('crear', 'equipos_rastreo', $rastreoId, null, [
        'equipo_id' => $equipoId, 'checkin_id' => $rastreoId, 'fuente' => $fuente,
        'ubicacion_disponible' => $ubicacion !== null,
    ], [
        'actor_tipo' => 'agente',
        'modulo' => 'equipos', 'submodulo' => 'rastreo',
        'accion_detalle' => 'checkin_equipo_registrado', 'resultado' => 'exito',
        'equipo_id' => $equipoId, 'ubicacion' => $ubicacion,
        'latitud' => $lat, 'longitud' => $lon,
        'fuente_geolocalizacion' => $lat !== null ? ($fuente === 'gps' ? 'gps_dispositivo' : ($fuente === 'sede' ? 'sede_registrada' : 'ip_aproximada')) : null,
    ]);
    if (!$registrado) {
        Database::rollback();
        Response::error('No se pudo registrar el check-in.', null, 500);
    }
    Database::commit();

    // ---- el historial se limpia solo (90 dias) ----
    if (mt_rand(1, 50) === 1) {
        try {
            Database::run('DELETE FROM equipos_rastreo WHERE equipo_id = ? AND created_at < ?',
                [$equipoId, date('Y-m-d H:i:s', strtotime('-90 days'))]);
        } catch (Throwable $e) { /* sin importancia */ }
    }

    // ---- alertas ----
    $donde = $ubicacion ?: ($ipPublica ?: ($ipLocal ?: 'ubicación desconocida'));
    $perdido = (int)$config['modo_perdido'] === 1;
    if ($perdido) {
        $ultima = $config['ultima_alerta'] ? strtotime($config['ultima_alerta']) : 0;
        if ($ultima < time() - 600) {             // un aviso cada 10 minutos como maximo
            Notificador::avisarPermiso('equipos', 'editar', 'sistema',
                'EQUIPO ' . ($config['perdido_motivo'] === 'robo' ? 'ROBADO' : 'PERDIDO') . ' reporta: ' . $equipo['codigo'],
                'Se conectó desde ' . $donde . ($ipPublica ? ' (IP ' . $ipPublica . ')' : ''),
                'equipos/seguimiento');
            Database::update('equipos_rastreo_config', ['ultima_alerta' => date('Y-m-d H:i:s')], 'equipo_id = ?', [$equipoId]);
        }
    }
    if ($equipo['estado'] === 'dado_de_baja' && !$perdido) {
        Notificador::avisarPermiso('equipos', 'ver', 'sistema',
            'ALERTA: Equipo dado de baja reporta ubicación',
            $equipo['codigo'] . ' hizo check-in desde ' . $donde, 'equipos/seguimiento');
    }
    if ($macCoincide === 0 && ($previo === null || (string)$previo['mac_coincide'] !== '0')) {
        Notificador::avisarPermiso('equipos', 'editar', 'sistema',
            'MAC distinta a la registrada: ' . $equipo['codigo'],
            'El agente informó ' . $mac . ' y el inventario tiene ' . $macRegistrada . '. Revise si cambió la tarjeta de red o el agente está en otro equipo.',
            'equipos/seguimiento');
    }

    Response::ok([
        'ip_publica'    => $ipPublica,
        'ubicacion'     => $ubicacion,
        'timestamp'     => date('Y-m-d H:i:s'),
        'perdido'       => $perdido ? 1 : 0,
        'siguiente_min' => $perdido ? 1 : 5,
    ], 'Check-in registrado.');

} catch (Throwable $e) {
    try { Database::rollback(); } catch (Throwable $r) {}
    error_log('[SIGTI][RASTREO] Check-in fallido; codigo='
        . ($e instanceof PDOException ? ($e->errorInfo[0] ?? $e->getCode()) : $e->getCode()));
    Response::error('No se pudo registrar el check-in.', null, 500);
}
