-- ============================================================
-- SIGTI - Columnas que el codigo usa y db/sigti.sql no creaba
-- Seguro de ejecutar varias veces (MariaDB / XAMPP): si la
-- columna ya existe, no hace nada. Tu BD actual ya las tiene.
-- Sirve para instalaciones nuevas a partir de sigti.sql.
-- ============================================================
ALTER TABLE personal ADD COLUMN IF NOT EXISTS genero  VARCHAR(10) NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto    VARCHAR(255) NULL;
ALTER TABLE roles    ADD COLUMN IF NOT EXISTS area_id TINYINT UNSIGNED NULL;
ALTER TABLE actas    ADD COLUMN IF NOT EXISTS token   VARCHAR(64) NULL;
