-- ============================================================
-- SIGTI - Finalizacion de TICKETS y de MANTENIMIENTOS
--   Tickets: cuando / quien / como se finalizo, tiempo invertido y motivo del retraso (SLA)
--   Mantenimiento: resultado, percances y motivo del atraso
-- Seguro de ejecutar varias veces (MariaDB / XAMPP).
-- El sistema ya crea todo esto solo la primera vez que se usa; este archivo
-- sirve para aplicarlo a mano o en instalaciones nuevas.
-- ============================================================

ALTER TABLE tickets
  ADD COLUMN IF NOT EXISTS tipo_solucion        VARCHAR(30)  NULL,
  ADD COLUMN IF NOT EXISTS tiempo_invertido_min INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS resuelto_por         INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS cerrado_por          INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS motivo_retraso       VARCHAR(250) NULL;

ALTER TABLE mantenimientos
  ADD COLUMN IF NOT EXISTS resultado_final VARCHAR(15)  NULL,
  ADD COLUMN IF NOT EXISTS finalizado_por  INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS atraso_min      INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS motivo_atraso   VARCHAR(250) NULL;

CREATE TABLE IF NOT EXISTS mantenimiento_percances (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mantenimiento_id INT UNSIGNED NOT NULL,
  tipo             VARCHAR(30)  NOT NULL,
  detalle          VARCHAR(250) NOT NULL,
  usuario_id       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL,
  KEY idx_percance_mt (mantenimiento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
