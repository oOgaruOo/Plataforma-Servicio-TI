-- SIGTI - Fecha de nacimiento (cumpleanos) del personal
-- Es opcional ejecutarlo a mano: el sistema crea la columna solo la primera vez
-- que se usa el modulo Personal. Es seguro ejecutarlo mas de una vez.
ALTER TABLE personal ADD COLUMN IF NOT EXISTS fecha_nacimiento DATE NULL;
