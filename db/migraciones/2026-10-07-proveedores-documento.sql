-- SIGTI - Proveedores: tipo de documento (RUC o DNI) y direccion
-- Es opcional ejecutarlo a mano: el sistema crea las columnas solo la primera vez
-- que se usa Proveedores. Es seguro ejecutarlo mas de una vez.
-- La columna 'ruc' guarda el numero del documento (RUC u DNI segun tipo_documento).
ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS tipo_documento VARCHAR(3) NOT NULL DEFAULT 'ruc';
ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS direccion VARCHAR(200) NULL;
-- El servicio de consulta (token de apiperu.dev / Decolecta) se configura desde
-- Configuracion > Proveedores > "Servicio de consulta DNI/RUC"; no hace falta insertar nada aqui.
