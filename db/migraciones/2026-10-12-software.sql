-- Software: catalogo de programas, licencias (con puestos y vencimiento) e instalaciones por equipo.
-- (La aplicacion tambien lo crea sola al abrir Software; este script es por si se prefiere aplicar a mano.)
-- Los permisos software.ver / software.registrar / software.gestionar los crea la aplicacion al iniciar sesion.

CREATE TABLE IF NOT EXISTS software_catalogo (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(100) NOT NULL,
    fabricante        VARCHAR(80) NULL,
    categoria         VARCHAR(20) NOT NULL DEFAULT 'otro',
    requiere_licencia TINYINT(1) NOT NULL DEFAULT 1,
    autorizacion      VARCHAR(12) NOT NULL DEFAULT 'autorizado',
    estandar          TINYINT(1) NOT NULL DEFAULT 0,
    descripcion       VARCHAR(255) NULL,
    activo            TINYINT(1) NOT NULL DEFAULT 1,
    creado_por        INT UNSIGNED NULL,
    created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    KEY idx_sw_cat_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS software_licencias (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    software_id       INT UNSIGNED NOT NULL,
    tipo              VARCHAR(12) NOT NULL DEFAULT 'perpetua',
    clave             VARCHAR(190) NULL,
    puestos           INT UNSIGNED NOT NULL DEFAULT 1,
    fecha_compra      DATE NULL,
    fecha_vencimiento DATE NULL,
    costo             DECIMAL(10,2) NULL,
    proveedor         VARCHAR(100) NULL,
    documento         VARCHAR(60) NULL,
    observaciones     VARCHAR(255) NULL,
    activo            TINYINT(1) NOT NULL DEFAULT 1,
    legacy_id         INT UNSIGNED NULL,
    creado_por        INT UNSIGNED NULL,
    created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    KEY idx_sw_lic_sw (software_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS equipo_software (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id           INT UNSIGNED NOT NULL,
    software_id         INT UNSIGNED NOT NULL,
    version             VARCHAR(40) NULL,
    licencia_id         INT UNSIGNED NULL,
    estado              VARCHAR(12) NOT NULL DEFAULT 'instalado',
    fecha_instalacion   DATE NULL,
    fecha_desinstalacion DATE NULL,
    instalado_por       INT UNSIGNED NULL,
    desinstalado_por    INT UNSIGNED NULL,
    observaciones       VARCHAR(255) NULL,
    legacy_id           INT UNSIGNED NULL,
    created_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NULL DEFAULT NULL,
    KEY idx_es_equipo (equipo_id),
    KEY idx_es_sw (software_id),
    KEY idx_es_lic (licencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE equipos
  ADD COLUMN IF NOT EXISTS software_verificado_en DATETIME NULL,
  ADD COLUMN IF NOT EXISTS software_verificado_por INT UNSIGNED NULL;
