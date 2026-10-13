-- ============================================================
-- SIGTI - Tablas base que usa el código pero que db/sigti.sql no trae
-- (constructor de tipos de acta, plantillas de etiquetas y ubicaciones físicas).
-- Seguro de ejecutar varias veces: en una base que ya las tiene no hace nada.
-- Úselo en una instalación nueva hecha SOLO con db/sigti.sql.
-- ============================================================

CREATE TABLE IF NOT EXISTS ubicaciones_fisicas (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(120) NOT NULL,
    tipo      VARCHAR(20)  NOT NULL DEFAULT 'oficina',
    estado    VARCHAR(10)  NOT NULL DEFAULT 'activo',
    created_at TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ubicacion_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS etiquetas_plantillas (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(120) NOT NULL,
    ancho_mm   SMALLINT UNSIGNED NOT NULL DEFAULT 40,
    alto_mm    SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    elementos  LONGTEXT NULL,
    activa     TINYINT(1) NOT NULL DEFAULT 1,
    objeto     VARCHAR(10) NOT NULL DEFAULT 'equipo',
    creada_por INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS actas_tipos (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    codigo              VARCHAR(30)  NOT NULL,
    nombre              VARCHAR(120) NOT NULL,
    tipo_base           VARCHAR(12)  NOT NULL DEFAULT 'libre',
    titulo_doc          VARCHAR(200) NOT NULL,
    declaracion         TEXT NULL,
    firmantes           VARCHAR(200) NOT NULL DEFAULT 'Colaborador,Área de TI',
    incluye_equipos     TINYINT(1) NOT NULL DEFAULT 0,
    incluye_colaborador TINYINT(1) NOT NULL DEFAULT 0,
    titulo_campos       VARCHAR(100) NULL,
    activo              TINYINT(1) NOT NULL DEFAULT 1,
    creado_por          INT UNSIGNED NULL,
    created_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_actas_tipos_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS actas_tipos_campos (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    acta_tipo_id INT UNSIGNED NOT NULL,
    etiqueta     VARCHAR(100) NOT NULL,
    tipo_campo   VARCHAR(12)  NOT NULL DEFAULT 'texto',
    opciones     TEXT NULL,
    obligatorio  TINYINT(1) NOT NULL DEFAULT 0,
    orden        INT NOT NULL DEFAULT 0,
    activo       TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_atc_tipo (acta_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
