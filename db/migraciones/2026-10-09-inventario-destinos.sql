-- ============================================================
-- SIGTI - Inventario: equipos asignados a PERSONA, AREA, PROYECTO u OBRA
-- Seguro de ejecutar varias veces (MariaDB / XAMPP).
-- El sistema ya crea todo esto solo la primera vez que se usa la pantalla;
-- este archivo sirve para aplicarlo a mano o en instalaciones nuevas.
-- ============================================================

CREATE TABLE IF NOT EXISTS proyectos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo         VARCHAR(20) NOT NULL,
  nombre         VARCHAR(120) NOT NULL,
  tipo           VARCHAR(10) NOT NULL DEFAULT 'proyecto',   -- proyecto | obra
  cliente        VARCHAR(120) NULL,
  ubicacion      VARCHAR(150) NULL,
  responsable_id INT UNSIGNED NULL,
  fecha_inicio   DATE NULL,
  fecha_fin      DATE NULL,
  estado         ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_en      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_proyecto_codigo (codigo),
  KEY idx_proyecto_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE asignaciones ADD COLUMN IF NOT EXISTS destino_tipo    VARCHAR(10) NOT NULL DEFAULT 'persona';
ALTER TABLE asignaciones ADD COLUMN IF NOT EXISTS proyecto_id     INT UNSIGNED NULL;
ALTER TABLE asignaciones ADD COLUMN IF NOT EXISTS responsable_id  INT UNSIGNED NULL;
ALTER TABLE asignaciones ADD COLUMN IF NOT EXISTS destino_detalle VARCHAR(150) NULL;

ALTER TABLE solicitudes_equipo ADD COLUMN IF NOT EXISTS proyecto_id INT UNSIGNED NULL;
ALTER TABLE solicitudes_equipo ADD COLUMN IF NOT EXISTS entregados  TINYINT UNSIGNED NOT NULL DEFAULT 0;

-- Las entregas antiguas a un area (sin persona) pasan a destino "area"
UPDATE asignaciones SET destino_tipo = 'area'
 WHERE personal_id IS NULL AND area_id IS NOT NULL AND destino_tipo = 'persona';
-- En las de persona, el responsable es la propia persona
UPDATE asignaciones SET responsable_id = personal_id
 WHERE personal_id IS NOT NULL AND responsable_id IS NULL;

CREATE TABLE IF NOT EXISTS accesorios_tipos (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(80) NOT NULL,
  familia VARCHAR(20) NOT NULL DEFAULT 'general',
  activo  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_acc_tipo (familia, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
