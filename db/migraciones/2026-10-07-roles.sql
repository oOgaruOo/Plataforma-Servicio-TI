-- SIGTI - Roles: columna del area, permisos que el codigo usa y faltaban, y tabla de ajustes por usuario
-- Es opcional ejecutarlo a mano: la pantalla Usuarios > Roles lo crea sola al abrirse.
-- Es seguro ejecutarlo mas de una vez.
ALTER TABLE roles ADD COLUMN IF NOT EXISTS area_id TINYINT UNSIGNED NULL;
INSERT INTO permisos (modulo, accion, descripcion)
SELECT 'tickets', 'eliminar', 'Eliminar tickets' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE modulo = 'tickets' AND accion = 'eliminar');
INSERT INTO permisos (modulo, accion, descripcion)
SELECT 'mantenimiento', 'eliminar', 'Eliminar mantenimientos' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE modulo = 'mantenimiento' AND accion = 'eliminar');
-- admin_ti tiene "todo"
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r JOIN permisos p
WHERE r.nombre = 'admin_ti' AND p.modulo IN ('tickets','mantenimiento') AND p.accion = 'eliminar'
  AND NOT EXISTS (SELECT 1 FROM rol_permisos x WHERE x.rol_id = r.id AND x.permiso_id = p.id);
