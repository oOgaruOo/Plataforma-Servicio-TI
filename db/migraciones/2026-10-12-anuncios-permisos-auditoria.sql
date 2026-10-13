-- Anuncios mejorados: destinatarios por rol/area, anuncios fijos, quien cerro cada uno.
-- (La aplicacion tambien lo crea sola al abrir Anuncios; este script es por si se prefiere aplicar a mano.)
CREATE TABLE IF NOT EXISTS anuncios (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo       VARCHAR(150) NOT NULL,
  mensaje      TEXT NOT NULL,
  tipo         VARCHAR(15) NOT NULL DEFAULT 'info',
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  fecha_desde  DATE NULL,
  hora_desde   TIME NULL,
  fecha_hasta  DATE NULL,
  hora_hasta   TIME NULL,
  destino      VARCHAR(10) NOT NULL DEFAULT 'todos',
  destino_ids  VARCHAR(255) NULL,
  fijo         TINYINT(1) NOT NULL DEFAULT 0,
  creado_por   INT UNSIGNED NULL,
  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE anuncios
  ADD COLUMN IF NOT EXISTS destino VARCHAR(10) NOT NULL DEFAULT 'todos',
  ADD COLUMN IF NOT EXISTS destino_ids VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS fijo TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS anuncio_cierres (
  anuncio_id  INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED NOT NULL,
  cerrado_en  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (anuncio_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Permisos y Auditoria no necesitan cambios de tablas.
