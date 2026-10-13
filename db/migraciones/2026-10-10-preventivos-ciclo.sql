-- ============================================================
-- SIGTI - Ciclo automatico de los preventivos programados
--   Al finalizar el mantenimiento se anota la fecha real y se calcula sola la proxima fecha.
-- Seguro de ejecutar varias veces (MariaDB / XAMPP).
-- El sistema crea estas columnas solo la primera vez que se usa; este archivo sirve para
-- aplicarlo a mano o en instalaciones nuevas.
-- ============================================================

ALTER TABLE mantenimientos
  ADD COLUMN IF NOT EXISTS programado_id INT UNSIGNED NULL;

ALTER TABLE mantenimientos_programados
  ADD COLUMN IF NOT EXISTS mantenimiento_actual_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS ultimo_mantenimiento_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS aviso_para DATE NULL;
