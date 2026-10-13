-- SIGTI - Personal: apellido paterno y apellido materno por separado
-- Es opcional ejecutarlo a mano: el sistema crea las columnas y reparte los apellidos
-- de las personas ya registradas la primera vez que se usa el modulo Personal.
-- La columna 'apellidos' se conserva ("paterno materno") para que actas, tickets y reportes sigan igual.
ALTER TABLE personal ADD COLUMN IF NOT EXISTS apellido_paterno VARCHAR(40) NULL AFTER nombres;
ALTER TABLE personal ADD COLUMN IF NOT EXISTS apellido_materno VARCHAR(40) NULL AFTER apellido_paterno;
