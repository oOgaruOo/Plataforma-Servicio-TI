-- ============================================================
-- SIGTI - Capacitaciones: cualquier área solicita una capacitación a Sistemas y ve su estado
-- Seguro de ejecutar varias veces. El sistema también crea estas tablas (y los 6 temas habituales)
-- solo al abrir Capacitaciones; este archivo es opcional.
-- Los permisos (capacitaciones.solicitar / ver_area / ver / gestionar) se crean solos al iniciar sesión.
-- ============================================================
CREATE TABLE IF NOT EXISTS capacitacion_temas (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(120) NOT NULL,
    descripcion    VARCHAR(255) NULL,
    duracion_horas DECIMAL(4,1) NULL,
    activo         TINYINT(1) NOT NULL DEFAULT 1,
    created_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS capacitaciones (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo                 VARCHAR(20) NOT NULL,
    solicitante_usuario_id INT UNSIGNED NOT NULL,
    area_id                INT UNSIGNED NULL,
    tema_id                INT UNSIGNED NULL,
    tema                   VARCHAR(150) NOT NULL,
    detalle                VARCHAR(500) NULL,
    participantes          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    modalidad              VARCHAR(12) NOT NULL DEFAULT 'indistinto',
    fecha_deseada          DATE NULL,
    prioridad              VARCHAR(8) NOT NULL DEFAULT 'normal',
    estado                 VARCHAR(12) NOT NULL DEFAULT 'solicitada',
    capacitador_usuario_id INT UNSIGNED NULL,
    fecha_programada       DATETIME NULL,
    duracion_min           SMALLINT UNSIGNED NULL,
    lugar                  VARCHAR(150) NULL,
    motivo                 VARCHAR(255) NULL,
    resultado              VARCHAR(500) NULL,
    asistentes             SMALLINT UNSIGNED NULL,
    fecha_realizada        DATETIME NULL,
    calificacion           TINYINT UNSIGNED NULL,
    comentario_eval        VARCHAR(300) NULL,
    created_at             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_cap_codigo (codigo),
    KEY idx_cap_estado (estado),
    KEY idx_cap_solicitante (solicitante_usuario_id),
    KEY idx_cap_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS capacitacion_historial (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capacitacion_id INT UNSIGNED NOT NULL,
    estado          VARCHAR(12) NOT NULL,
    usuario_id      INT UNSIGNED NULL,
    comentario      VARCHAR(400) NULL,
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_caph_cap (capacitacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
