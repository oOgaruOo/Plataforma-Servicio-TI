<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Sedes y redes conocidas del rastreo
// Una sede se reconoce por su IP publica y/o por el prefijo de su red local (ej. 192.168.1.).
// Cuando un equipo reporta desde una sede, el panel dice «Oficina Central»;
// si reporta desde fuera de todas ellas, se ve en el mapa como ubicacion externa.
// GET lista (equipos.ver) · POST guardar|toggle|eliminar (equipos.editar)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('equipos', 'ver');
    Rastreo::asegurar();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        Response::ok(Database::get(
            "SELECT s.*, p.nombre AS proyecto,
                    (SELECT COUNT(*) FROM equipos_rastreo r WHERE r.sede_id = s.id) AS reportes
             FROM rastreo_sedes s LEFT JOIN proyectos p ON p.id = s.proyecto_id
             ORDER BY s.activo DESC, s.nombre"));
    }

    Csrf::validate();
    Auth::requirePermission('equipos', 'editar');
    Destinos::asegurar();

    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'guardar') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $ip     = trim((string)($_POST['ip_publica'] ?? ''));
        $pref   = trim((string)($_POST['prefijo_local'] ?? ''));
        $dir    = trim((string)($_POST['direccion'] ?? ''));
        $errores = [];

        if ($nombre === '' || mb_strlen($nombre) > 120) $errores['nombre'] = 'Escriba el nombre de la sede (máximo 120 caracteres).';
        if ($ip !== '' && !Rastreo::esIpPublica($ip)) $errores['ip_publica'] = 'Debe ser una IP pública válida (no 192.168.x.x ni 10.x.x.x).';
        if ($pref !== '') {
            if (!preg_match('/^(\d{1,3}\.){1,3}\d{0,3}\.?$/', $pref)) {
                $errores['prefijo_local'] = 'Use el inicio de la red, por ejemplo 192.168.1.';
            } else {
                $pref = rtrim($pref, '.') . '.';          // "192.168.1" -> "192.168.1." (evita que 192.168.1 coincida con 192.168.10.x)
                if (substr_count($pref, '.') < 2) $errores['prefijo_local'] = 'Indique al menos dos números, por ejemplo 192.168.';
            }
        }
        if ($ip === '' && $pref === '' && !isset($errores['ip_publica']) && !isset($errores['prefijo_local'])) {
            $errores['ip_publica'] = 'Indique la IP pública o el prefijo de la red local de la sede.';
        }
        if (mb_strlen($dir) > 200) $errores['direccion'] = 'Máximo 200 caracteres.';

        $latTxt = trim((string)($_POST['latitud'] ?? '')); $lonTxt = trim((string)($_POST['longitud'] ?? ''));
        $lat = null; $lon = null;
        if ($latTxt !== '' || $lonTxt !== '') {
            $c = Rastreo::coordenadas($latTxt, $lonTxt);
            if (!$c) $errores['latitud'] = 'Coordenadas inválidas (latitud −90 a 90, longitud −180 a 180). Cópielas de Google Maps.';
            else [$lat, $lon] = $c;
        }
        $proy = (int)($_POST['proyecto_id'] ?? 0);
        if ($proy > 0 && !Database::getValue('SELECT id FROM proyectos WHERE id = ?', [$proy])) {
            $errores['proyecto_id'] = 'El proyecto u obra no existe.';
        }
        if (!$errores && Database::getValue('SELECT id FROM rastreo_sedes WHERE nombre = ? AND id <> ?', [$nombre, $id])) {
            $errores['nombre'] = 'Ya existe una sede con ese nombre.';
        }
        if (!$errores && $ip !== '' && Database::getValue('SELECT id FROM rastreo_sedes WHERE ip_publica = ? AND id <> ?', [$ip, $id])) {
            $errores['ip_publica'] = 'Esa IP pública ya pertenece a otra sede.';
        }
        if ($errores) Response::validation($errores);

        $datos = [
            'nombre' => $nombre, 'ip_publica' => $ip ?: null, 'prefijo_local' => $pref ?: null,
            'proyecto_id' => $proy > 0 ? $proy : null, 'direccion' => $dir ?: null,
            'latitud' => $lat, 'longitud' => $lon,
        ];
        if ($id > 0) {
            $antes = Database::getOne('SELECT * FROM rastreo_sedes WHERE id = ?', [$id]);
            if (!$antes) Response::error('La sede no existe.');
            Database::update('rastreo_sedes', $datos, 'id = ?', [$id]);
            Auditoria::registrar('actualizar', 'rastreo_sedes', $id, $antes, $datos);
            Response::ok(['id' => $id], 'Sede actualizada.');
        }
        $nuevo = Database::insert('rastreo_sedes', $datos);
        Auditoria::registrar('crear', 'rastreo_sedes', $nuevo, null, $datos);
        Response::ok(['id' => $nuevo], 'Sede registrada.');

    } elseif ($accion === 'toggle') {
        $s = Database::getOne('SELECT * FROM rastreo_sedes WHERE id = ?', [$id]);
        if (!$s) Response::error('La sede no existe.');
        $nuevo = (int)$s['activo'] === 1 ? 0 : 1;
        Database::update('rastreo_sedes', ['activo' => $nuevo], 'id = ?', [$id]);
        Auditoria::registrar('cambiar_estado', 'rastreo_sedes', $id, ['activo' => (int)$s['activo']], ['activo' => $nuevo]);
        Response::ok(null, $nuevo ? 'Sede activada.' : 'Sede desactivada.');

    } elseif ($accion === 'eliminar') {
        $s = Database::getOne('SELECT * FROM rastreo_sedes WHERE id = ?', [$id]);
        if (!$s) Response::error('La sede no existe.');
        // los reportes antiguos conservan el nombre de la ubicacion; solo se suelta el vinculo
        Database::update('equipos_rastreo', ['sede_id' => null], 'sede_id = ?', [$id]);
        Database::delete('rastreo_sedes', 'id = ?', [$id]);
        Auditoria::registrar('eliminar', 'rastreo_sedes', $id, $s, null);
        Response::ok(null, 'Sede eliminada.');

    } else {
        Response::error('Acción inválida.');
    }

} catch (Throwable $e) {
    try { Database::rollback(); } catch (Throwable $r) {}
    error_log('[SIGTI][RASTREO] sedes: ' . $e->getMessage());
    Response::error('No se pudo completar la operación con las sedes.', null, 500);
}
