-- SIGTI - Codigo de usuario por area, locker y tipo de etiqueta
-- Es opcional ejecutarlo a mano: el sistema crea las columnas, asigna el prefijo a cada area
-- y el codigo a cada persona ya registrada la primera vez que se usa Personal o Configuracion.
-- Es seguro ejecutarlo mas de una vez.
ALTER TABLE areas    ADD COLUMN IF NOT EXISTS codigo VARCHAR(8) NULL;
ALTER TABLE personal ADD COLUMN IF NOT EXISTS codigo_usuario VARCHAR(20) NULL;
ALTER TABLE personal ADD COLUMN IF NOT EXISTS locker VARCHAR(20) NULL;
-- Tipo de plantilla de etiqueta: equipo (las que ya existian) | personal | locker
ALTER TABLE etiquetas_plantillas ADD COLUMN IF NOT EXISTS objeto VARCHAR(10) NOT NULL DEFAULT 'equipo';
