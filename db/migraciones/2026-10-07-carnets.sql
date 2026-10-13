-- SIGTI - Carnets del personal: tabla, foto propia y aviso de reimpresion
-- Es opcional ejecutarlo a mano: el sistema crea la tabla/columnas la primera vez que se usa Carnets o Personal.
-- Es seguro ejecutarlo mas de una vez.
ALTER TABLE personal ADD COLUMN IF NOT EXISTS foto VARCHAR(80) NULL;
CREATE TABLE IF NOT EXISTS carnets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  personal_id INT NOT NULL,
  codigo_carnet VARCHAR(30) NOT NULL,
  fecha_emision DATE NOT NULL,
  fecha_vigencia DATE NOT NULL,
  estado VARCHAR(15) NOT NULL DEFAULT 'pendiente',   -- pendiente | impreso | entregado (vencido se calcula por la vigencia)
  impreso_por INT NULL,
  fecha_impresion DATETIME NULL,
  entregado_por INT NULL,
  fecha_entrega DATETIME NULL,
  revierto_a VARCHAR(15) NULL,
  fecha_reversion DATETIME NULL,
  revertido_por INT NULL,
  datos_cambiaron TINYINT(1) NOT NULL DEFAULT 0,     -- 1 = cambio algo que va impreso: hay que reimprimir
  creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_car_personal (personal_id),
  KEY idx_car_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE carnets ADD COLUMN IF NOT EXISTS datos_cambiaron TINYINT(1) NOT NULL DEFAULT 0;
