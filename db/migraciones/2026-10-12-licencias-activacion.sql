-- ============================================================
-- SIGTI - Licencias de software: fecha de activación + duración = vencimiento calculado
-- Seguro de ejecutar varias veces. El sistema también lo hace solo al abrir Software; este archivo es opcional.
-- ============================================================
ALTER TABLE software_licencias ADD COLUMN IF NOT EXISTS fecha_activacion DATE NULL AFTER fecha_compra;
ALTER TABLE software_licencias ADD COLUMN IF NOT EXISTS duracion_valor SMALLINT UNSIGNED NULL AFTER fecha_activacion;
ALTER TABLE software_licencias ADD COLUMN IF NOT EXISTS duracion_unidad VARCHAR(6) NULL AFTER duracion_valor;
ALTER TABLE software_licencias ADD COLUMN IF NOT EXISTS precio_unitario DECIMAL(10,2) NULL AFTER duracion_unidad;
