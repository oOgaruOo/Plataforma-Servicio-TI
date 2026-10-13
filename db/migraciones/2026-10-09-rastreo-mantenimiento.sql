-- ============================================================
-- SIGTI - Rastreo en vivo de equipos (IP / MAC) y cierre de mantenimientos
-- Seguro de ejecutar varias veces (MariaDB / XAMPP).
-- El sistema ya crea todo esto solo la primera vez que se usa la pantalla;
-- este archivo sirve para aplicarlo a mano o en instalaciones nuevas.
-- (Finalizar mantenimiento no necesita cambios de base de datos.)
-- ============================================================

CREATE TABLE IF NOT EXISTS rastreo_sedes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(120) NOT NULL,
  ip_publica    VARCHAR(45) NULL,
  prefijo_local VARCHAR(20) NULL,          -- ej. 192.168.1.
  proyecto_id   INT UNSIGNED NULL,
  direccion     VARCHAR(200) NULL,
  latitud       DECIMAL(10,7) NULL,
  longitud      DECIMAL(10,7) NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  creado_en     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sede_ip (ip_publica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ip_geocache (
  ip          VARCHAR(45) PRIMARY KEY,
  ciudad      VARCHAR(100) NULL,
  region      VARCHAR(100) NULL,
  pais        VARCHAR(100) NULL,
  pais_codigo VARCHAR(5) NULL,
  isp         VARCHAR(150) NULL,
  latitud     DECIMAL(10,7) NULL,
  longitud    DECIMAL(10,7) NULL,
  consultado  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE equipos_rastreo ADD COLUMN IF NOT EXISTS fuente       VARCHAR(15) NULL;
ALTER TABLE equipos_rastreo ADD COLUMN IF NOT EXISTS precision_m  INT UNSIGNED NULL;
ALTER TABLE equipos_rastreo ADD COLUMN IF NOT EXISTS wifi         VARCHAR(120) NULL;
ALTER TABLE equipos_rastreo ADD COLUMN IF NOT EXISTS sede_id      INT UNSIGNED NULL;
ALTER TABLE equipos_rastreo ADD COLUMN IF NOT EXISTS mac_coincide TINYINT(1) NULL;

ALTER TABLE equipos_rastreo_config ADD COLUMN IF NOT EXISTS modo_perdido   TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE equipos_rastreo_config ADD COLUMN IF NOT EXISTS perdido_desde  DATETIME NULL;
ALTER TABLE equipos_rastreo_config ADD COLUMN IF NOT EXISTS perdido_motivo VARCHAR(10) NULL;
ALTER TABLE equipos_rastreo_config ADD COLUMN IF NOT EXISTS perdido_nota   VARCHAR(250) NULL;
ALTER TABLE equipos_rastreo_config ADD COLUMN IF NOT EXISTS perdido_por    INT UNSIGNED NULL;
ALTER TABLE equipos_rastreo_config ADD COLUMN IF NOT EXISTS ultima_alerta  DATETIME NULL;

-- ------------------------------------------------------------
-- Mantenimientos: columnas y catalogo que usan las pantallas (si su base no los tiene)
-- ------------------------------------------------------------
ALTER TABLE mantenimientos ADD COLUMN IF NOT EXISTS sla_limite    DATETIME NULL;
ALTER TABLE mantenimientos ADD COLUMN IF NOT EXISTS destino_envio VARCHAR(150) NULL;

CREATE TABLE IF NOT EXISTS destinos_envio (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(100) NOT NULL,
  tipo    ENUM('distrito','provincia','departamento','otro') NOT NULL DEFAULT 'distrito',
  estado  ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  UNIQUE KEY uq_destino_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
