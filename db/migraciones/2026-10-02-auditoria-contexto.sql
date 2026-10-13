-- SIGTI: metadatos contextuales aditivos para auditoria.
-- Compatible con la MariaDB activa 10.4.32; ADD COLUMN IF NOT EXISTS
-- hace que el script sea seguro si un entorno ya contiene alguno de estos campos.
-- No modifica ni elimina las columnas/filas de auditoria existentes.

ALTER TABLE auditoria_logs
  ADD COLUMN IF NOT EXISTS usuario VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS usuario_nombre VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS rol_snapshot VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS actor_tipo VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS modulo VARCHAR(40) NULL,
  ADD COLUMN IF NOT EXISTS submodulo VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS accion_detalle VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS equipo_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS resultado VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS motivo VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS fuente_ip VARCHAR(40) NULL,
  ADD COLUMN IF NOT EXISTS session_ref CHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS precision_geo DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS fuente_geolocalizacion VARCHAR(30) NULL;
