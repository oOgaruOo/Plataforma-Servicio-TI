<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - CRUD de roles
// GET  -> roles con area, n_usuarios, n_permisos y si estan protegidos
// POST accion=guardar  (id, nombre, descripcion, area_id, copiar_de = rol del que copiar permisos al CREAR)
// POST accion=eliminar (id)
// Permiso usuarios.gestionar
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Auth::requirePermission('usuarios', 'gestionar');
    Roles::asegurar();

    // ---------- GET ----------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $roles = Database::get(
            "SELECT r.id, r.nombre, r.descripcion, r.es_sistema, r.area_id,
                    a.nombre AS area,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS n_usuarios,
                    (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.rol_id = r.id) AS n_permisos
             FROM roles r
             LEFT JOIN areas a ON a.id = r.area_id
             ORDER BY r.es_sistema DESC, r.id");
        $total = (int)Database::getValue('SELECT COUNT(*) FROM permisos');
        foreach ($roles as &$r) {
            $r['id']          = (int)$r['id'];
            $r['n_usuarios']  = (int)$r['n_usuarios'];
            $r['protegido']   = Roles::protegido($r) ? 1 : 0;
            // super_admin no usa la tabla: tiene todo siempre
            $r['n_permisos']  = $r['nombre'] === 'super_admin' ? $total : (int)$r['n_permisos'];
            $r['total_permisos'] = $total;
        }
        unset($r);
        Response::ok($roles);
    }

    // ---------- POST ----------
    Csrf::validate();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id          = (int)($_POST['id'] ?? 0);
        $nombre      = trim((string)($_POST['nombre'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $areaId      = trim((string)($_POST['area_id'] ?? '')) !== '' ? (int)$_POST['area_id'] : null;
        $copiarDe    = (int)($_POST['copiar_de'] ?? 0);

        if ($nombre === '') Response::validation(['nombre' => 'Indique el nombre del rol.']);
        if (!preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $nombre)) {
            Response::validation(['nombre' => 'Solo letras, numeros, guion y guion bajo, sin espacios (3 a 50).']);
        }
        if (mb_strlen($descripcion) > 200) Response::validation(['descripcion' => 'Maximo 200 caracteres.']);
        if ($areaId !== null && !Database::getValue('SELECT id FROM areas WHERE id = ?', [$areaId])) {
            Response::validation(['area_id' => 'El area no existe.']);
        }

        if ($id > 0) {
            // ----- editar -----
            $rol = Database::getOne('SELECT * FROM roles WHERE id = ?', [$id]);
            if (!$rol) Response::error('El rol no existe.');

            if (Roles::protegido($rol)) {
                // protegido: el codigo depende de su nombre; solo se edita la descripcion
                if ($nombre !== $rol['nombre']) {
                    Response::error('Este rol no puede cambiar de nombre (el sistema lo usa por su nombre). Solo su descripcion.');
                }
                Database::update('roles', ['descripcion' => $descripcion ?: null], 'id = ?', [$id]);
                Auditoria::registrar('actualizar', 'roles', $id, $rol, ['descripcion' => $descripcion]);
                Response::ok(null, 'Descripcion actualizada.');
            }
            if (Database::getValue('SELECT id FROM roles WHERE nombre = ? AND id <> ?', [$nombre, $id])) {
                Response::validation(['nombre' => "El rol «{$nombre}» ya existe."]);
            }
            $datos = ['nombre' => $nombre, 'descripcion' => $descripcion ?: null, 'area_id' => $areaId];
            Database::update('roles', $datos, 'id = ?', [$id]);
            Auditoria::registrar('actualizar', 'roles', $id, $rol, $datos);
            Response::ok(['id' => $id], 'Rol actualizado.');
        }

        // ----- crear -----
        $nombre = strtolower($nombre);          // los roles nuevos se guardan en minusculas
        if (Database::getValue('SELECT id FROM roles WHERE nombre = ?', [$nombre])) {
            Response::validation(['nombre' => "El rol «{$nombre}» ya existe."]);
        }
        $origen = null;
        if ($copiarDe > 0) {
            $origen = Database::getOne('SELECT id, nombre FROM roles WHERE id = ?', [$copiarDe]);
            if (!$origen) Response::validation(['copiar_de' => 'El rol de origen no existe.']);
        }

        $datos = ['nombre' => $nombre, 'descripcion' => $descripcion ?: null, 'area_id' => $areaId, 'es_sistema' => 0];
        $copiados = 0;
        Database::begin();
        try {
            $nuevo = Database::insert('roles', $datos);
            if ($origen) {
                if ($origen['nombre'] === 'super_admin') {
                    // super_admin tiene todo sin filas: se copian todos los permisos
                    $ids = array_column(Database::get('SELECT id FROM permisos'), 'id');
                } else {
                    $ids = array_column(Database::get('SELECT permiso_id FROM rol_permisos WHERE rol_id = ?', [(int)$origen['id']]), 'permiso_id');
                }
                foreach ($ids as $pid) {
                    Database::insert('rol_permisos', ['rol_id' => $nuevo, 'permiso_id' => (int)$pid]);
                    $copiados++;
                }
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            Response::error('Error al crear el rol: ' . $e->getMessage());
        }
        Auditoria::registrar('crear', 'roles', $nuevo, null, $datos + ['copiar_de' => $origen['nombre'] ?? null, 'permisos_copiados' => $copiados]);
        Response::ok(['id' => $nuevo, 'copiados' => $copiados],
            $origen ? "Rol «{$nombre}» creado con $copiados permiso(s) copiados de «{$origen['nombre']}»."
                    : "Rol «{$nombre}» creado. Defina sus permisos.");

    } elseif ($accion === 'eliminar') {
        $id  = (int)($_POST['id'] ?? 0);
        $rol = Database::getOne('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$rol) Response::error('El rol no existe.');
        if (Roles::protegido($rol)) {
            Response::error('Este rol es del sistema y no se puede eliminar.');
        }
        $nUsuarios = (int)Database::getValue('SELECT COUNT(*) FROM usuarios WHERE rol_id = ?', [$id]);
        if ($nUsuarios > 0) {
            Response::error("No se puede eliminar: hay $nUsuarios usuario(s) con este rol. Cambie su rol primero.");
        }
        Database::begin();
        try {
            Database::delete('rol_permisos', 'rol_id = ?', [$id]);
            Database::delete('roles', 'id = ?', [$id]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            Response::error('Error al eliminar: ' . $e->getMessage());
        }
        Auditoria::registrar('eliminar', 'roles', $id, $rol, null);
        Response::ok(null, "Rol «{$rol['nombre']}» eliminado.");
    }

    Response::error('Accion invalida.');

} catch (Throwable $ex) {
    Response::error('usuarios/roles: ' . $ex->getMessage());
}
