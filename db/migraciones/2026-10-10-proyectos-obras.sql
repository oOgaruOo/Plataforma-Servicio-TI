-- ============================================================
-- SIGTI - Proyectos y Obras con plazo y devolucion estipulada de los equipos
-- Seguro de ejecutar varias veces (MariaDB / XAMPP).
-- El sistema crea estas columnas solo la primera vez que se usan; este archivo es opcional.
-- ============================================================

ALTER TABLE proyectos
  ADD COLUMN IF NOT EXISTS descripcion  VARCHAR(250) NULL,
  ADD COLUMN IF NOT EXISTS fecha_cierre DATE NULL;

ALTER TABLE asignaciones
  ADD COLUMN IF NOT EXISTS aviso_dev TINYINT NOT NULL DEFAULT 0;
