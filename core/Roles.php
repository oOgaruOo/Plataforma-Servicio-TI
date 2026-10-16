<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Roles y permisos: catalogo, reglas y auto-reparacion del esquema
//   * roles.area_id (sigti.sql antiguo no la traia)
//   * permisos que el codigo exige y la BD no tenia (tickets.eliminar, mantenimiento.eliminar)
//   * etiquetas legibles y marca de permisos "sensibles" para la pantalla de Roles
// ============================================================

class Roles
{
    /** Roles que el codigo nombra explicitamente: no se renombran ni se eliminan. */
    public const NOMBRES_FIJOS = ['super_admin', 'usuario_final'];

    public const MODULOS = [
        'personal'      => 'Personal',
        'tickets'       => 'Tickets (mesa de ayuda)',
        'equipos'       => 'Equipos',
        'mantenimiento' => 'Mantenimiento',
        'asignaciones'  => 'Asignaciones',
        'actas'         => 'Actas',
        'reportes'      => 'Reportes',
        'configuracion' => 'Configuración',
        'usuarios'      => 'Usuarios y roles',
        'auditoria'     => 'Auditoría',
        'software'      => 'Software instalado',
        'capacitaciones' => 'Capacitaciones',
        'rrhh'          => 'RR.HH. ampliado',
        'chat'          => 'Chat',
    ];

    /** Permisos que dan control amplio o borran datos: se resaltan al asignarlos. */
    public const SENSIBLES = [
        'usuarios.gestionar', 'configuracion.gestionar', 'auditoria.ver', 'auditoria.ver_area',
        'personal.eliminar', 'personal.cese', 'equipos.baja',
        'tickets.eliminar', 'mantenimiento.eliminar', 'mantenimiento.aprobar_costo',
        'software.gestionar',
        'rrhh.gestionar', 'rrhh.eliminar',
        'chat.auditoria',
    ];

    /** Permisos que el codigo usa. Si falta alguno en la BD se crea. [modulo, accion, descripcion] */
    private const CATALOGO = [
        ['tickets', 'eliminar', 'Eliminar tickets'],
        ['mantenimiento', 'eliminar', 'Eliminar mantenimientos'],
        // 4to dato: al crearse, lo reciben los roles que ya tienen ese permiso (despues cada administrador lo ajusta)
        ['software', 'ver', 'Ver el software instalado, las licencias y los informes', 'equipos.ver'],
        ['software', 'registrar', 'Registrar y retirar software instalado en los equipos', 'equipos.editar'],
        ['software', 'gestionar', 'Gestionar el catálogo de programas y las licencias (claves, puestos, costos)', null],
        // Capacitaciones: quien ya puede crear tickets puede pedir una capacitacion; quien ve tickets ve las solicitudes
        ['capacitaciones', 'solicitar', 'Solicitar capacitaciones y seguir las propias', 'tickets.crear'],
        ['capacitaciones', 'ver_area', 'Ver las solicitudes de capacitación de su área', 'tickets.ver_area'],
        ['capacitaciones', 'ver', 'Ver todas las solicitudes de capacitación y la agenda', 'tickets.ver'],
        ['capacitaciones', 'gestionar', 'Revisar, programar y cerrar capacitaciones; administrar los temas', 'tickets.asignar'],
        // RR.HH. ampliado (Contratos, Vacaciones, Asistencia y Planillas): gestion interna, sin validez tributaria/legal
        ['rrhh', 'ver', 'Ver contratos, vacaciones, asistencia y planillas de RR.HH.', 'personal.ver'],
        ['rrhh', 'gestionar', 'Gestionar contratos, vacaciones, asistencia y planillas de RR.HH.', 'personal.editar'],
        // Requerimiento de personal: quien ya puede crear tickets puede pedir personal para su area; quien ve tickets de su area ve esas solicitudes
        ['rrhh', 'solicitar', 'Solicitar personal para su área (Requerimientos) y ver las propias', 'tickets.crear'],
        ['rrhh', 'ver_area', 'Ver los requerimientos de personal de su área', 'tickets.ver_area'],
        // Separado de "gestionar": borrar (no solo desactivar) del catálogo de Puestos, Equipos de TI y Requisitos
        ['rrhh', 'eliminar', 'Eliminar puestos, equipos de TI y requisitos del catálogo, y requerimientos de personal ya cerrados (cubiertos o cancelados)', 'rrhh.gestionar'],
        // Chat: antes solo se controlaba revisando usuarios.gestionar; ahora es un permiso propio y asignable por rol
        ['chat', 'auditoria', 'Ver el historial de cualquier sala de chat (auditoría de chat)', 'usuarios.gestionar'],
        // Auditoria por area: ve solo lo que hizo el personal de su propia area (sin heredar de nada: se asigna a mano)
        ['auditoria', 'ver_area', 'Ver la auditoría de las acciones hechas por el personal de su área', null],
    ];

    /** Catalogo base del sistema: el codigo los usa (menus, endpoints, avisos). No se pueden eliminar desde la pantalla Permisos. */
    public const BASE = [
        'personal.ver','personal.crear','personal.editar','personal.eliminar','personal.cese',
        'tickets.ver','tickets.ver_propios','tickets.ver_area','tickets.crear','tickets.editar',
        'tickets.asignar','tickets.cambiar_estado','tickets.eliminar',
        'equipos.ver','equipos.crear','equipos.editar','equipos.baja',
        'mantenimiento.ver','mantenimiento.crear','mantenimiento.editar','mantenimiento.aprobar_costo',
        'mantenimiento.cambiar_estado','mantenimiento.eliminar',
        'asignaciones.ver','asignaciones.crear','asignaciones.devolver','asignaciones.aprobar','asignaciones.solicitar',
        'actas.generar','reportes.ver','reportes.ver_area',
        'configuracion.gestionar','usuarios.gestionar','auditoria.ver','auditoria.ver_area',
        'software.ver','software.registrar','software.gestionar',
        'capacitaciones.solicitar','capacitaciones.ver_area','capacitaciones.ver','capacitaciones.gestionar',
        'rrhh.ver','rrhh.gestionar','rrhh.solicitar','rrhh.ver_area','rrhh.eliminar',
        'chat.auditoria',
    ];

    public static function esBase(string $modulo, string $accion): bool
    {
        return in_array($modulo . '.' . $accion, self::BASE, true);
    }

    private static bool $ok = false;

    public static function asegurar(): void
    {
        if (self::$ok) return;
        Esquema::columna('roles', 'area_id', 'TINYINT UNSIGNED NULL');
        foreach (self::CATALOGO as $def) {
            [$mod, $acc, $desc] = $def;
            $hereda = $def[3] ?? null;
            $existe = Database::getValue('SELECT id FROM permisos WHERE modulo = ? AND accion = ?', [$mod, $acc]);
            if ($existe) continue;
            $pid = Database::insert('permisos', ['modulo' => $mod, 'accion' => $acc, 'descripcion' => $desc]);
            // admin_ti tiene "todo": el permiso nuevo tambien (los demas roles lo reciben solo si se les asigna)
            $roles = [];
            $admin = Database::getValue("SELECT id FROM roles WHERE nombre = 'admin_ti'");
            if ($admin) $roles[(int)$admin] = true;
            // los roles que ya tenian el permiso "padre" lo reciben tambien (por unica vez)
            if ($hereda) {
                [$hm, $ha] = explode('.', $hereda);
                $filas = Database::get(
                    'SELECT rp.rol_id FROM rol_permisos rp INNER JOIN permisos p ON p.id = rp.permiso_id
                     WHERE p.modulo = ? AND p.accion = ?', [$hm, $ha]);
                foreach ($filas as $f) $roles[(int)$f['rol_id']] = true;
            }
            foreach (array_keys($roles) as $rid) {
                Database::insert('rol_permisos', ['rol_id' => $rid, 'permiso_id' => (int)$pid]);
            }
        }
        self::$ok = true;
    }

    public static function etiquetaModulo(string $modulo): string
    {
        return self::MODULOS[$modulo] ?? ucfirst(str_replace('_', ' ', $modulo));
    }

    public static function esSensible(string $modulo, string $accion): bool
    {
        return in_array($modulo . '.' . $accion, self::SENSIBLES, true);
    }

    /** Un rol esta protegido si es de sistema o el codigo lo nombra. */
    public static function protegido(array $rol): bool
    {
        return (int)($rol['es_sistema'] ?? 0) === 1 || in_array($rol['nombre'] ?? '', self::NOMBRES_FIJOS, true);
    }
}
