<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Pantalla Permisos
//
// GET  ?vista=catalogo (por defecto)  -> permisos con roles que los tienen, ajustes por usuario, marca sensible/sistema
// GET  ?vista=matriz                  -> roles x permisos (para marcar / quitar)
// GET  ?vista=ajustes                 -> ajustes individuales (permisos concedidos o quitados a un usuario)
// GET  ?vista=quien&id=N              -> roles y usuarios con ajuste de un permiso
//
// POST op=guardar   (id, modulo, accion, descripcion)  crea o edita la descripcion
// POST op=eliminar  (id)                               solo permisos propios (no los del sistema)
// POST op=toggle_rol (rol_id, permiso_id, concedido)   marca / quita un permiso de un rol (matriz)
// POST op=quitar_ajuste (usuario_id, permiso_id)       vuelve al permiso que da el rol
//
// Ver: configuracion.gestionar. Cambiar permisos de un rol: ademas usuarios.gestionar.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

/** Una fila de permiso con los datos que pinta la pantalla. */
function permisoFila(array $p): array
{
    $p['id'] = (int)$p['id'];
    $p['modulo_nombre'] = Roles::etiquetaModulo($p['modulo']);
    $p['sensible'] = Roles::esSensible($p['modulo'], $p['accion']) ? 1 : 0;
    $p['sistema'] = Roles::esBase($p['modulo'], $p['accion']) ? 1 : 0;
    return $p;
}

try {
    Auth::check();
    Roles::asegurar();
    $get = $_SERVER['REQUEST_METHOD'] === 'GET';
    if (!$get) Csrf::validate();
    Auth::requirePermission('configuracion', 'gestionar');

    // ------------------------------------------------------------------ GET
    if ($get) {
        $vista = (string)($_GET['vista'] ?? 'catalogo');

        if ($vista === 'catalogo') {
            $permisos = Database::get('SELECT id, modulo, accion, descripcion FROM permisos ORDER BY modulo, accion');
            $roles = Database::get('SELECT id, nombre FROM roles ORDER BY id');
            $nombreRol = [];
            foreach ($roles as $r) $nombreRol[(int)$r['id']] = $r['nombre'];

            $porPermiso = [];
            foreach (Database::get('SELECT rol_id, permiso_id FROM rol_permisos') as $rp) {
                $porPermiso[(int)$rp['permiso_id']][] = $nombreRol[(int)$rp['rol_id']] ?? ('#' . $rp['rol_id']);
            }
            $ajustes = [];
            foreach (Database::get('SELECT permiso_id, concedido, COUNT(*) AS n FROM usuario_permisos GROUP BY permiso_id, concedido') as $a) {
                $k = (int)$a['permiso_id'];
                $ajustes[$k]['conc'] = ($ajustes[$k]['conc'] ?? 0) + ((int)$a['concedido'] === 1 ? (int)$a['n'] : 0);
                $ajustes[$k]['rev']  = ($ajustes[$k]['rev'] ?? 0)  + ((int)$a['concedido'] === 1 ? 0 : (int)$a['n']);
            }
            $superId = (int)Database::getValue("SELECT id FROM roles WHERE nombre = 'super_admin'");

            $lista = [];
            foreach ($permisos as $p) {
                $p = permisoFila($p);
                $roles_p = $porPermiso[$p['id']] ?? [];
                // super_admin siempre lo tiene aunque no figure en rol_permisos
                if ($superId && !in_array('super_admin', $roles_p, true)) array_unshift($roles_p, 'super_admin');
                $p['roles'] = array_values($roles_p);
                $p['n_roles'] = count($roles_p);
                $p['n_roles_reales'] = count($porPermiso[$p['id']] ?? []);
                $p['aj_concedidos'] = (int)($ajustes[$p['id']]['conc'] ?? 0);
                $p['aj_revocados']  = (int)($ajustes[$p['id']]['rev'] ?? 0);
                $lista[] = $p;
            }
            $modulos = [];
            foreach ($lista as $p) $modulos[$p['modulo']] = $p['modulo_nombre'];
            ksort($modulos);
            Response::ok([
                'permisos' => $lista,
                'modulos' => $modulos,
                'puede_roles' => Auth::can('usuarios', 'gestionar'),
            ]);
        }

        if ($vista === 'matriz') {
            $roles = Database::get(
                'SELECT r.id, r.nombre, r.descripcion, r.es_sistema,
                        (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS n_usuarios
                 FROM roles r ORDER BY r.id');
            foreach ($roles as &$r) {
                $r['id'] = (int)$r['id'];
                $r['n_usuarios'] = (int)$r['n_usuarios'];
                $r['super'] = $r['nombre'] === 'super_admin' ? 1 : 0;
                $r['protegido'] = Roles::protegido($r) ? 1 : 0;
            }
            unset($r);
            $permisos = array_map('permisoFila',
                Database::get('SELECT id, modulo, accion, descripcion FROM permisos ORDER BY modulo, accion'));
            $asignado = [];
            foreach (Database::get('SELECT rol_id, permiso_id FROM rol_permisos') as $rp) {
                $asignado[(int)$rp['rol_id']][] = (int)$rp['permiso_id'];
            }
            $mapa = [];
            foreach ($roles as $r) $mapa[$r['id']] = $asignado[$r['id']] ?? [];
            Response::ok([
                'roles' => $roles,
                'permisos' => $permisos,
                'asignado' => $mapa,
                'puede_editar' => Auth::can('usuarios', 'gestionar'),
                'mi_rol_id' => (int)Database::getValue('SELECT rol_id FROM usuarios WHERE id = ?', [Auth::userId()]),
            ]);
        }

        if ($vista === 'ajustes') {
            $filas = Database::get(
                "SELECT up.usuario_id, up.permiso_id, up.concedido,
                        u.usuario, u.nombre_completo, r.nombre AS rol,
                        p.modulo, p.accion,
                        (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.rol_id = u.rol_id AND rp.permiso_id = up.permiso_id) AS en_rol
                 FROM usuario_permisos up
                 INNER JOIN usuarios u ON u.id = up.usuario_id
                 INNER JOIN roles r ON r.id = u.rol_id
                 INNER JOIN permisos p ON p.id = up.permiso_id
                 ORDER BY u.nombre_completo, p.modulo, p.accion");
            foreach ($filas as &$f) {
                $f['usuario_id'] = (int)$f['usuario_id'];
                $f['permiso_id'] = (int)$f['permiso_id'];
                $f['concedido'] = (int)$f['concedido'];
                $f['permiso'] = $f['modulo'] . '.' . $f['accion'];
                $f['modulo_nombre'] = Roles::etiquetaModulo($f['modulo']);
                $f['sensible'] = Roles::esSensible($f['modulo'], $f['accion']) ? 1 : 0;
                // ajuste que no cambia nada: concede lo que el rol ya da, o quita lo que el rol no da
                $f['redundante'] = ($f['concedido'] === 1 && (int)$f['en_rol'] > 0)
                                || ($f['concedido'] === 0 && (int)$f['en_rol'] === 0) ? 1 : 0;
            }
            unset($f);
            Response::ok(['ajustes' => $filas, 'puede_quitar' => Auth::can('usuarios', 'gestionar')]);
        }

        if ($vista === 'quien') {
            $id = (int)($_GET['id'] ?? 0);
            $p = Database::getOne('SELECT id, modulo, accion, descripcion FROM permisos WHERE id = ?', [$id]);
            if (!$p) Response::error('El permiso no existe.');
            $roles = Database::get(
                'SELECT r.id, r.nombre, (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS n_usuarios
                 FROM rol_permisos rp INNER JOIN roles r ON r.id = rp.rol_id
                 WHERE rp.permiso_id = ? ORDER BY r.nombre', [$id]);
            if (!in_array('super_admin', array_column($roles, 'nombre'), true)) {
                $sa = Database::getOne("SELECT id, nombre, (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = roles.id) AS n_usuarios FROM roles WHERE nombre = 'super_admin'");
                if ($sa) array_unshift($roles, $sa);
            }
            $usuarios = Database::get(
                "SELECT u.id, u.usuario, u.nombre_completo, r.nombre AS rol, up.concedido
                 FROM usuario_permisos up
                 INNER JOIN usuarios u ON u.id = up.usuario_id
                 INNER JOIN roles r ON r.id = u.rol_id
                 WHERE up.permiso_id = ? ORDER BY u.nombre_completo", [$id]);
            Response::ok(['permiso' => permisoFila($p), 'roles' => $roles, 'usuarios' => $usuarios]);
        }

        Response::error('Vista invalida.');
    }

    // ------------------------------------------------------------------ POST
    $op = (string)($_POST['op'] ?? '');

    if ($op === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $desc = mb_substr(trim((string)($_POST['descripcion'] ?? '')), 0, 200);

        if ($id > 0) {
            $antes = Database::getOne('SELECT * FROM permisos WHERE id = ?', [$id]);
            if (!$antes) Response::error('El permiso no existe.');
            if ((string)$antes['descripcion'] === $desc) Response::ok(['mensaje' => 'No había cambios.'], 'No había cambios.');
            Database::update('permisos', ['descripcion' => $desc !== '' ? $desc : null], 'id = ?', [$id]);
            Auditoria::registrar('actualizar', 'permisos', $id,
                ['descripcion' => $antes['descripcion']], ['descripcion' => $desc],
                ['accion_detalle' => 'permiso_editado', 'submodulo' => $antes['modulo'] . '.' . $antes['accion']]);
            Response::ok(['mensaje' => 'Descripción actualizada.'], 'Descripción actualizada.');
        }

        $modulo = strtolower(trim((string)($_POST['modulo'] ?? '')));
        $accionP = strtolower(trim((string)($_POST['accion'] ?? '')));
        $err = [];
        if ($modulo === '') $err['modulo'] = 'Indique el módulo.';
        elseif (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $modulo)) $err['modulo'] = 'Use minúsculas, números y _ (ej: inventario).';
        if ($accionP === '') $err['accion'] = 'Indique la acción.';
        elseif (!preg_match('/^[a-z][a-z0-9_]{1,19}$/', $accionP)) $err['accion'] = 'Use minúsculas, números y _ (máximo 20, ej: ver).';
        if ($err) Response::validation($err);
        if (Database::getValue('SELECT id FROM permisos WHERE modulo = ? AND accion = ?', [$modulo, $accionP])) {
            Response::validation(['accion' => "Ya existe {$modulo}.{$accionP}."]);
        }
        $nuevo = Database::insert('permisos', ['modulo' => $modulo, 'accion' => $accionP, 'descripcion' => $desc !== '' ? $desc : null]);
        Auditoria::registrar('crear', 'permisos', (int)$nuevo, null,
            ['permiso' => "$modulo.$accionP", 'descripcion' => $desc],
            ['accion_detalle' => 'permiso_creado', 'submodulo' => "$modulo.$accionP"]);
        Response::ok(['id' => (int)$nuevo, 'mensaje' => "Permiso {$modulo}.{$accionP} creado. Asígnelo a un rol en la pestaña «Matriz por rol»."],
            "Permiso {$modulo}.{$accionP} creado.");
    }

    if ($op === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $p = Database::getOne('SELECT * FROM permisos WHERE id = ?', [$id]);
        if (!$p) Response::error('El permiso no existe.');
        $codigo = $p['modulo'] . '.' . $p['accion'];
        if (Roles::esBase($p['modulo'], $p['accion'])) {
            Response::error("«{$codigo}» lo usa el sistema: no se puede eliminar (si no quiere que alguien lo tenga, quítelo del rol).");
        }
        $nRoles = (int)Database::getValue('SELECT COUNT(*) FROM rol_permisos WHERE permiso_id = ?', [$id]);
        $nUsu = (int)Database::getValue('SELECT COUNT(*) FROM usuario_permisos WHERE permiso_id = ?', [$id]);
        Database::begin();
        try {
            Database::delete('rol_permisos', 'permiso_id = ?', [$id]);
            Database::delete('usuario_permisos', 'permiso_id = ?', [$id]);
            Database::delete('permisos', 'id = ?', [$id]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            Response::error('No se pudo eliminar el permiso.');
        }
        Auditoria::registrar('eliminar', 'permisos', $id,
            ['permiso' => $codigo, 'descripcion' => $p['descripcion'], 'roles_afectados' => $nRoles, 'ajustes_afectados' => $nUsu], null,
            ['accion_detalle' => 'permiso_eliminado', 'submodulo' => $codigo]);
        Response::ok(['mensaje' => "Permiso {$codigo} eliminado."], "Permiso {$codigo} eliminado.");
    }

    if ($op === 'toggle_rol') {
        Auth::requirePermission('usuarios', 'gestionar');
        $rolId = (int)($_POST['rol_id'] ?? 0);
        $permId = (int)($_POST['permiso_id'] ?? 0);
        $conceder = (int)($_POST['concedido'] ?? 0) === 1;
        $rol = Database::getOne('SELECT * FROM roles WHERE id = ?', [$rolId]);
        if (!$rol) Response::error('El rol no existe.');
        if ($rol['nombre'] === 'super_admin') Response::error('super_admin siempre tiene todos los permisos.');
        $p = Database::getOne('SELECT * FROM permisos WHERE id = ?', [$permId]);
        if (!$p) Response::error('El permiso no existe.');
        $codigo = $p['modulo'] . '.' . $p['accion'];

        if (!$conceder && $codigo === 'usuarios.gestionar' && Auth::rol() !== 'super_admin') {
            $miRol = (int)Database::getValue('SELECT rol_id FROM usuarios WHERE id = ?', [Auth::userId()]);
            if ($miRol === $rolId) {
                Response::error('No puede quitarle «usuarios.gestionar» a su propio rol: perdería el acceso a la gestión de roles.');
            }
        }
        if (!$conceder && $codigo === 'configuracion.gestionar' && Auth::rol() !== 'super_admin') {
            $miRol = (int)Database::getValue('SELECT rol_id FROM usuarios WHERE id = ?', [Auth::userId()]);
            if ($miRol === $rolId) {
                Response::error('No puede quitarle «configuracion.gestionar» a su propio rol: perdería el acceso a esta pantalla.');
            }
        }

        $existe = Database::getValue('SELECT 1 FROM rol_permisos WHERE rol_id = ? AND permiso_id = ?', [$rolId, $permId]) !== null;
        if ($conceder && !$existe) Database::insert('rol_permisos', ['rol_id' => $rolId, 'permiso_id' => $permId]);
        elseif (!$conceder && $existe) Database::delete('rol_permisos', 'rol_id = ? AND permiso_id = ?', [$rolId, $permId]);
        else Response::ok(['mensaje' => 'Sin cambios.', 'concedido' => $conceder ? 1 : 0], 'Sin cambios.');

        Auditoria::registrar('actualizar', 'roles', $rolId, null,
            ['rol' => $rol['nombre'], ($conceder ? 'agregados' : 'quitados') => [$codigo]],
            ['accion_detalle' => $conceder ? 'permiso_rol_agregado' : 'permiso_rol_quitado', 'submodulo' => $codigo]);

        $usuarios = (int)Database::getValue('SELECT COUNT(*) FROM usuarios WHERE rol_id = ?', [$rolId]);
        $msg = ($conceder ? "«{$codigo}» agregado a " : "«{$codigo}» quitado de ") . $rol['nombre'] . '.'
             . ($usuarios ? " Aplica a $usuarios usuario(s) en menos de un minuto." : '');
        Response::ok(['mensaje' => $msg, 'concedido' => $conceder ? 1 : 0, 'usuarios' => $usuarios], $msg);
    }

    if ($op === 'quitar_ajuste') {
        Auth::requirePermission('usuarios', 'gestionar');
        $uid = (int)($_POST['usuario_id'] ?? 0);
        $pid = (int)($_POST['permiso_id'] ?? 0);
        $aj = Database::getOne(
            "SELECT up.concedido, u.usuario, p.modulo, p.accion
             FROM usuario_permisos up
             INNER JOIN usuarios u ON u.id = up.usuario_id
             INNER JOIN permisos p ON p.id = up.permiso_id
             WHERE up.usuario_id = ? AND up.permiso_id = ?", [$uid, $pid]);
        if (!$aj) Response::error('Ese ajuste ya no existe.');
        if ($uid === Auth::userId()) Response::error('No puede modificar sus propios permisos.');
        Database::delete('usuario_permisos', 'usuario_id = ? AND permiso_id = ?', [$uid, $pid]);
        $codigo = $aj['modulo'] . '.' . $aj['accion'];
        Auditoria::registrar('actualizar', 'usuario_permisos', $uid,
            ['permiso' => $codigo, 'ajuste' => (int)$aj['concedido'] === 1 ? 'concedido' : 'revocado'],
            ['permiso' => $codigo, 'ajuste' => 'quitado (vuelve a lo que da su rol)'],
            ['accion_detalle' => 'ajuste_usuario_quitado', 'submodulo' => $codigo]);
        $msg = "Ajuste de «{$codigo}» quitado a {$aj['usuario']}: vuelve a lo que le da su rol.";
        Response::ok(['mensaje' => $msg], $msg);
    }

    Response::error('Operación inválida.');

} catch (Throwable $ex) {
    Response::error('permisos: ' . $ex->getMessage());
}
