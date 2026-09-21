<?php
// ============================================================
// SIGTI - Autenticacion, sesion, roles y permisos
// ============================================================

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name('SIGTISESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);
        session_start();
    }

    public static function attempt(string $usuario, string $password): array
    {
        $usuario = trim($usuario);
        if ($usuario === '' || $password === '') {
            return ['success' => false, 'message' => 'Ingrese usuario y contrasena.'];
        }

        $u = Database::getOne(
            "SELECT u.id, u.usuario, u.password_hash, u.estado, u.intentos_fallidos,
                    u.rol_id, u.nombre_completo, r.nombre AS rol
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE u.usuario = ?",
            [$usuario]
        );

        $generico = 'Usuario o contrasena incorrectos.';

        if (!$u) {
            Auditoria::registrar('login_fallido', 'usuarios', null, null, "usuario='$usuario' (no existe)");
            return ['success' => false, 'message' => $generico];
        }

        if ($u['password_hash'] === 'PENDIENTE_ACTIVAR') {
            return ['success' => false,
                    'message' => 'La cuenta admin aun no esta activada. Ejecute crear_admin.php primero.'];
        }

        if ($u['estado'] === 'bloqueado') {
            Auditoria::registrar('login_fallido', 'usuarios', (int)$u['id'], null, 'cuenta bloqueada');
            return ['success' => false, 'message' => 'La cuenta esta bloqueada. Contacte al administrador.'];
        }

        if ($u['estado'] === 'inactivo') {
            return ['success' => false, 'message' => 'La cuenta esta inactiva.'];
        }

        if (!password_verify($password, $u['password_hash'])) {

            $intentos = (int)$u['intentos_fallidos'] + 1;

            if ($intentos >= MAX_INTENTOS_LOGIN) {
                Database::update('usuarios',
                    ['estado' => 'bloqueado', 'intentos_fallidos' => $intentos],
                    'id = ?', [$u['id']]);
                Auditoria::registrar('login_fallido', 'usuarios', (int)$u['id'], null,
                    "cuenta BLOQUEADA al intento $intentos");
                return ['success' => false,
                        'message' => 'Contrasena incorrecta. La cuenta fue BLOQUEADA (' . MAX_INTENTOS_LOGIN . ' intentos).'];
            }

            Database::update('usuarios', ['intentos_fallidos' => $intentos], 'id = ?', [$u['id']]);
            Auditoria::registrar('login_fallido', 'usuarios', (int)$u['id'], null, "intento $intentos");

            $restan = MAX_INTENTOS_LOGIN - $intentos;
            return ['success' => false,
                    'message' => "Usuario o contrasena incorrectos. Quedan $restan intento(s)."];
        }

        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id'       => (int)$u['id'],
            'usuario'  => $u['usuario'],
            'nombre'   => $u['nombre_completo'],
            'rol'      => $u['rol'],
            'permisos' => self::cargarPermisos((int)$u['rol_id']),
        ];
        $_SESSION['ultima_actividad'] = time();

        Database::update('usuarios',
            ['intentos_fallidos' => 0, 'ultimo_acceso' => date('Y-m-d H:i:s')],
            'id = ?', [$u['id']]);

        Auditoria::registrar('login', 'usuarios', (int)$u['id']);

        return ['success' => true, 'message' => 'Bienvenido, ' . $u['nombre_completo']];
    }

    public static function isLogged(): bool
    {
        return isset($_SESSION['usuario']);
    }

    public static function check(): void
    {
        if (!self::isLogged()) {
            Response::noAuth('Sesion no iniciada.');
        }
        $limite = SESSION_TIMEOUT_MIN * 60;
        if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > $limite) {
            self::logout();
            Response::noAuth('Sesion expirada por inactividad.');
        }
        $_SESSION['ultima_actividad'] = time();
    }

    public static function userId(): ?int
    {
        return $_SESSION['usuario']['id'] ?? null;
    }

    public static function nombre(): string
    {
        return $_SESSION['usuario']['nombre'] ?? '';
    }

    public static function rol(): ?string
    {
        return $_SESSION['usuario']['rol'] ?? null;
    }

    public static function info(): array
    {
        $u = $_SESSION['usuario'] ?? null;
        if (!$u) return [];
        unset($u['permisos']);
        return $u;
    }

    public static function esRol(string ...$roles): bool
    {
        return in_array(self::rol(), $roles, true);
    }

    public static function requireRol(string ...$roles): void
    {
        if (!self::esRol(...$roles)) {
            Response::forbidden('Esta accion requiere rol: ' . implode(' o ', $roles));
        }
    }

    public static function can(string $modulo, string $accion): bool
    {
        if (self::rol() === 'super_admin') return true;
        return in_array("$modulo.$accion", $_SESSION['usuario']['permisos'] ?? [], true);
    }

    public static function requirePermission(string $modulo, string $accion): void
    {
        if (!self::can($modulo, $accion)) {
            Response::forbidden("No tiene permiso para [{$modulo}.{$accion}].");
        }
    }

    private static function cargarPermisos(int $rolId): array
    {
        $filas = Database::get(
            "SELECT CONCAT(p.modulo, '.', p.accion) AS permiso
             FROM rol_permisos rp
             INNER JOIN permisos p ON p.id = rp.permiso_id
             WHERE rp.rol_id = ?",
            [$rolId]
        );
        return array_column($filas, 'permiso');
    }

    public static function logout(): void
    {
        if (self::isLogged()) {
            Auditoria::registrar('logout', 'usuarios', self::userId());
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}