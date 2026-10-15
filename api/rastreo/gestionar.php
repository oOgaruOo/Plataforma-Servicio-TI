<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Gestion del rastreo de equipos
// GET  accion=estado&equipo_id=X  -> config + ultimos rastreos
// POST accion=activar|desactivar|generar_token
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('equipos', 'ver');
    Rastreo::asegurar();

    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($metodo === 'GET') {
        $equipoId = (int)($_GET['equipo_id'] ?? 0);
        if ($equipoId === 0) Response::error('Equipo no especificado.');

        $equipo = Database::getOne(
            "SELECT e.id, e.codigo, e.marca, e.modelo, e.nro_serie, e.mac, e.especificaciones, e.estado,
                    t.nombre AS tipo
             FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id WHERE e.id = ?", [$equipoId]);
        if (!$equipo) Response::error('El equipo no existe.');

        // el token NUNCA se devuelve aqui (solo con la accion ver_token y permiso de edicion)
        $config = Database::getOne(
            "SELECT erc.equipo_id, erc.activo, erc.ultimo_checkin, erc.modo_perdido, erc.perdido_desde,
                    erc.perdido_motivo, erc.perdido_nota,
                    e.codigo, e.marca, e.modelo, e.estado AS equipo_estado
             FROM equipos_rastreo_config erc
             INNER JOIN equipos e ON e.id = erc.equipo_id
             WHERE erc.equipo_id = ?", [$equipoId]);

        $rastreos = Database::get(
            "SELECT er.id, er.equipo_id, er.ip_publica, er.ip_local,
                    er.mac_address, er.hostname, er.user_agent, er.ubicacion,
                    er.latitud, er.longitud, er.fuente, er.precision_m, er.wifi, er.mac_coincide,
                    er.created_at, e.codigo, s.nombre AS sede
             FROM equipos_rastreo er
             INNER JOIN equipos e ON e.id = er.equipo_id
             LEFT JOIN rastreo_sedes s ON s.id = er.sede_id
             WHERE er.equipo_id = ?
             ORDER BY er.id DESC LIMIT 100", [$equipoId]);

        $con = Rastreo::conexion((bool)$config && (int)$config['activo'] === 1, $config['ultimo_checkin'] ?? null);

        Response::ok([
            'equipo' => [
                'id' => (int)$equipo['id'], 'codigo' => $equipo['codigo'], 'tipo' => $equipo['tipo'],
                'marca' => $equipo['marca'], 'modelo' => $equipo['modelo'], 'estado' => $equipo['estado'],
                'mac' => Rastreo::normalizarMac($equipo['mac']) ?: $equipo['mac'],
                'ip_registrada' => Rastreo::ipRegistrada($equipo['especificaciones']),
            ],
            'config' => $config,
            'conexion' => $con,
            'rastreos' => $rastreos,
            'puede_editar' => Auth::can('equipos', 'editar'),
        ]);
    } elseif ($metodo !== 'POST') {
        header('Allow: GET, POST');
        Response::error('Método no permitido.', null, 405);
    }

    Csrf::validate();
    Auth::requirePermission('equipos', 'editar');

    $accion = $_POST['accion'] ?? '';
    $equipoId = (int)($_POST['equipo_id'] ?? 0);
    if (!in_array($accion, ['activar', 'desactivar', 'regenerar', 'ver_token', 'perdido', 'encontrado'], true) || $equipoId < 1) {
        Response::error('Acción o equipo inválido.');
    }
    if (!Database::getOne('SELECT id FROM equipos WHERE id = ?', [$equipoId])) {
        Response::error('El equipo no existe.');
    }
    $existente = Database::getOne(
        'SELECT activo, token_rastreo FROM equipos_rastreo_config WHERE equipo_id = ?', [$equipoId]);

    if ($accion === 'activar') {
        Database::begin();
        if ($existente) {
            Database::update('equipos_rastreo_config', ['activo' => 1], 'equipo_id = ?', [$equipoId]);
            $token = $existente['token_rastreo'];
            $antes = ['activo' => (int)$existente['activo'], 'token_configurado' => true];
        } else {
            $token = bin2hex(random_bytes(32));
            Database::insert('equipos_rastreo_config', [
                'equipo_id' => $equipoId, 'token_rastreo' => $token, 'activo' => 1,
            ]);
            $antes = ['activo' => false, 'token_configurado' => false];
        }
        $registrado = Auditoria::registrar('actualizar', 'equipos_rastreo_config', $equipoId,
            $antes, ['activo' => true, 'token_configurado' => true], [
                'modulo' => 'equipos', 'submodulo' => 'rastreo',
                'accion_detalle' => 'rastreo_activado', 'resultado' => 'exito', 'equipo_id' => $equipoId,
            ]);
        if (!$registrado) {
            Database::rollback();
            Response::error('No se pudo registrar la activación en auditoría.', null, 500);
        }
        Database::commit();
        Response::ok(['token' => $token], $existente
            ? 'Rastreo activado.' : 'Rastreo activado. Configure el agente en el equipo.');

    } elseif ($accion === 'desactivar') {
        if (!$existente) Response::error('El rastreo no está configurado para este equipo.');
        if ((int)Database::getValue('SELECT modo_perdido FROM equipos_rastreo_config WHERE equipo_id = ?', [$equipoId]) === 1) {
            Response::error('El equipo está marcado como perdido/robado: márquelo como «encontrado» antes de desactivar el rastreo.');
        }
        Database::begin();
        Database::update('equipos_rastreo_config', ['activo' => 0], 'equipo_id = ?', [$equipoId]);
        $registrado = Auditoria::registrar('actualizar', 'equipos_rastreo_config', $equipoId,
            ['activo' => (int)$existente['activo'], 'token_configurado' => true],
            ['activo' => false, 'token_configurado' => true], [
                'modulo' => 'equipos', 'submodulo' => 'rastreo',
                'accion_detalle' => 'rastreo_desactivado', 'resultado' => 'exito', 'equipo_id' => $equipoId,
            ]);
        if (!$registrado) {
            Database::rollback();
            Response::error('No se pudo registrar la desactivación en auditoría.', null, 500);
        }
        Database::commit();
        Response::ok(null, 'Rastreo desactivado.');

    } elseif ($accion === 'regenerar') {
        if (!$existente) Response::error('El rastreo no está configurado para este equipo.');
        $token = bin2hex(random_bytes(32));
        Database::begin();
        Database::update('equipos_rastreo_config',
            ['token_rastreo' => $token, 'activo' => 1], 'equipo_id = ?', [$equipoId]);
        $registrado = Auditoria::registrar('actualizar', 'equipos_rastreo_config', $equipoId,
            ['activo' => (int)$existente['activo'], 'token_configurado' => true],
            ['activo' => true, 'token_configurado' => true, 'token_regenerado' => true], [
                'modulo' => 'equipos', 'submodulo' => 'rastreo',
                'accion_detalle' => 'token_rastreo_regenerado', 'resultado' => 'exito', 'equipo_id' => $equipoId,
            ]);
        if (!$registrado) {
            Database::rollback();
            Response::error('No se pudo registrar la regeneración en auditoría.', null, 500);
        }
        Database::commit();
        Response::ok(['token' => $token], 'Nuevo token generado.');

    } elseif ($accion === 'ver_token') {
        // para instalar el agente a mano; queda en auditoria quien lo consulto
        if (!$existente) Response::error('El rastreo no está configurado para este equipo.');
        $registrado = Auditoria::registrar('actualizar', 'equipos_rastreo_config', $equipoId, null,
            ['token_consultado' => true], [
                'modulo' => 'equipos', 'submodulo' => 'rastreo',
                'accion_detalle' => 'token_rastreo_consultado', 'resultado' => 'exito', 'equipo_id' => $equipoId,
            ]);
        if (!$registrado) Response::error('No se pudo registrar la consulta en auditoría.', null, 500);
        Response::ok(['token' => $existente['token_rastreo']]);

    } elseif ($accion === 'perdido') {
        $motivo = ($_POST['motivo'] ?? 'perdido') === 'robo' ? 'robo' : 'perdido';
        $nota = mb_substr(trim((string)($_POST['nota'] ?? '')), 0, 250);
        $eq = Database::getOne('SELECT codigo, estado FROM equipos WHERE id = ?', [$equipoId]);
        if ($eq['estado'] === 'dado_de_baja') Response::error('El equipo ya está dado de baja.');

        Database::begin();
        if ($existente) {
            Database::update('equipos_rastreo_config', [
                'activo' => 1, 'modo_perdido' => 1, 'perdido_desde' => date('Y-m-d H:i:s'),
                'perdido_motivo' => $motivo, 'perdido_nota' => $nota ?: null, 'perdido_por' => Auth::userId(),
                'ultima_alerta' => null,
            ], 'equipo_id = ?', [$equipoId]);
        } else {
            // sin agente todavia: se deja listo para que reporte apenas se instale/encienda
            Database::insert('equipos_rastreo_config', [
                'equipo_id' => $equipoId, 'token_rastreo' => bin2hex(random_bytes(32)), 'activo' => 1,
                'modo_perdido' => 1, 'perdido_desde' => date('Y-m-d H:i:s'),
                'perdido_motivo' => $motivo, 'perdido_nota' => $nota ?: null, 'perdido_por' => Auth::userId(),
            ]);
        }
        Database::insert('equipo_historial', [
            'equipo_id' => $equipoId, 'usuario_id' => Auth::userId(), 'evento' => 'rastreo',
            'detalle' => 'Marcado como ' . ($motivo === 'robo' ? 'ROBADO' : 'PERDIDO') . ($nota ? ': ' . $nota : ''),
        ]);
        $registrado = Auditoria::registrar('actualizar', 'equipos_rastreo_config', $equipoId,
            ['modo_perdido' => (int)($existente['modo_perdido'] ?? 0)], ['modo_perdido' => 1, 'motivo' => $motivo], [
                'modulo' => 'equipos', 'submodulo' => 'rastreo',
                'accion_detalle' => 'equipo_marcado_perdido', 'resultado' => 'exito',
                'motivo' => $nota ?: $motivo, 'equipo_id' => $equipoId,
            ]);
        if (!$registrado) {
            Database::rollback();
            Response::error('No se pudo registrar en auditoría.', null, 500);
        }
        Database::commit();
        Notificador::avisarPermiso('equipos', 'editar', 'sistema',
            'Equipo ' . ($motivo === 'robo' ? 'ROBADO' : 'PERDIDO') . ': ' . $eq['codigo'],
            'Se activó el seguimiento intensivo. Recibirá un aviso cada vez que el equipo se conecte.',
            'equipos/seguimiento');
        Response::ok(null, 'Equipo marcado como ' . ($motivo === 'robo' ? 'ROBADO' : 'PERDIDO')
            . '. Se avisará cada vez que se conecte.');

    } elseif ($accion === 'encontrado') {
        if (!$existente) Response::error('El rastreo no está configurado para este equipo.');
        Database::begin();
        Database::update('equipos_rastreo_config', [
            'modo_perdido' => 0, 'perdido_desde' => null, 'perdido_motivo' => null,
            'perdido_nota' => null, 'ultima_alerta' => null,
        ], 'equipo_id = ?', [$equipoId]);
        Database::insert('equipo_historial', [
            'equipo_id' => $equipoId, 'usuario_id' => Auth::userId(), 'evento' => 'rastreo',
            'detalle' => 'Equipo ENCONTRADO: se quitó la marca de perdido/robado',
        ]);
        $registrado = Auditoria::registrar('actualizar', 'equipos_rastreo_config', $equipoId,
            ['modo_perdido' => 1], ['modo_perdido' => 0], [
                'modulo' => 'equipos', 'submodulo' => 'rastreo',
                'accion_detalle' => 'equipo_marcado_encontrado', 'resultado' => 'exito', 'equipo_id' => $equipoId,
            ]);
        if (!$registrado) {
            Database::rollback();
            Response::error('No se pudo registrar en auditoría.', null, 500);
        }
        Database::commit();
        Response::ok(null, 'Equipo marcado como encontrado.');
    }

} catch (Throwable $e) {
    try { Database::rollback(); } catch (Throwable $rollbackError) {}
    $codigo = $e instanceof PDOException
        ? (string)($e->errorInfo[0] ?? $e->getCode())
        : (string)$e->getCode();
    error_log('[SIGTI][RASTREO] Gestión fallida; codigo=' . preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $codigo));
    Response::error('No se pudo actualizar la configuración de rastreo.', null, 500);
}
