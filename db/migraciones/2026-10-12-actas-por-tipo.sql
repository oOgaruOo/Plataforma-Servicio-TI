-- ============================================================
-- SIGTI - Actas de entrega, devolución y cambio según el tipo de equipo
-- Seguro de ejecutar varias veces (MariaDB / XAMPP).
-- El sistema hace esto solo la primera vez que se abre Actas o se entrega / recibe un equipo;
-- este archivo es opcional.
-- ============================================================

-- Tipo de equipo -> define el contenido del acta (cómputo, móvil, impresión, red, energía, visual, periférico, telefonía, otro)
ALTER TABLE tipo_equipos ADD COLUMN IF NOT EXISTS familia VARCHAR(20) NOT NULL DEFAULT 'otro';
UPDATE tipo_equipos SET familia = 'computo'   WHERE nombre IN ('Laptop','PC de escritorio','Servidor') AND familia = 'otro';
UPDATE tipo_equipos SET familia = 'movil'     WHERE nombre IN ('Celular','Tablet') AND familia = 'otro';
UPDATE tipo_equipos SET familia = 'impresion' WHERE nombre IN ('Impresora','Escáner') AND familia = 'otro';
UPDATE tipo_equipos SET familia = 'visual'    WHERE nombre IN ('Monitor','Proyector') AND familia = 'otro';
UPDATE tipo_equipos SET familia = 'red'       WHERE nombre IN ('Router / Switch','Access Point') AND familia = 'otro';
UPDATE tipo_equipos SET familia = 'energia'   WHERE nombre = 'UPS' AND familia = 'otro';
UPDATE tipo_equipos SET familia = 'periferico' WHERE nombre = 'Periférico' AND familia = 'otro';

-- Actas: datos de resumen para filtrar y buscar
ALTER TABLE actas MODIFY COLUMN tipo VARCHAR(20) NOT NULL;
ALTER TABLE actas ADD COLUMN IF NOT EXISTS token VARCHAR(64) NULL;
ALTER TABLE actas ADD COLUMN IF NOT EXISTS familia VARCHAR(20) NULL;
ALTER TABLE actas ADD COLUMN IF NOT EXISTS destino_tipo VARCHAR(10) NULL;
ALTER TABLE actas ADD COLUMN IF NOT EXISTS destino_nombre VARCHAR(150) NULL;
ALTER TABLE actas ADD COLUMN IF NOT EXISTS equipos_resumen VARCHAR(300) NULL;
ALTER TABLE actas ADD COLUMN IF NOT EXISTS firmado_en DATETIME NULL;
ALTER TABLE actas ADD COLUMN IF NOT EXISTS firmado_por INT UNSIGNED NULL;

-- Códigos de formato por defecto (se pueden cambiar por tipo de equipo en Actas > Códigos de formato)
INSERT INTO configuracion (clave, valor, descripcion)
SELECT 'acta_formato_entrega','TI-FOR-003','Código del formato de acta de entrega' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'acta_formato_entrega');
INSERT INTO configuracion (clave, valor, descripcion)
SELECT 'acta_formato_entrega_movil','TI-FOR-004','Código del formato de acta de entrega de equipos móviles' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'acta_formato_entrega_movil');
INSERT INTO configuracion (clave, valor, descripcion)
SELECT 'acta_formato_cambio','TI-FOR-002','Código del formato de acta de cambio' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'acta_formato_cambio');
INSERT INTO configuracion (clave, valor, descripcion)
SELECT 'acta_formato_devolucion','TI-FOR-001','Código del formato de acta de devolución' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'acta_formato_devolucion');
