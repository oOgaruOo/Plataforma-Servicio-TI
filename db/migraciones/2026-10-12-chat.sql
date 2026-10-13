-- ============================================================
-- SIGTI - Chat interno: personas, áreas, entre áreas, grupos y reuniones
-- Seguro de ejecutar varias veces (MariaDB / XAMPP).
-- El sistema crea estas tablas solo la primera vez que se abre el Chat; este archivo es opcional.
-- Si ya existía un chat anterior, solo se completan las columnas que falten.
-- ============================================================
CREATE TABLE IF NOT EXISTS chat_salas (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(120) NOT NULL,
  descripcion  VARCHAR(255) NULL,
  tipo         VARCHAR(15) NOT NULL DEFAULT 'grupo',   -- general | directo | grupo | area | entre_areas | reunion
  usuario1_id  INT UNSIGNED NULL,                      -- chat directo
  usuario2_id  INT UNSIGNED NULL,
  area1_id     SMALLINT UNSIGNED NULL,                 -- canal de área / entre áreas
  area2_id     SMALLINT UNSIGNED NULL,
  creada_por   INT UNSIGNED NULL,
  activa       TINYINT NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_chat_sala_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_mensajes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sala_id     INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED NOT NULL,
  mensaje     VARCHAR(1000) NOT NULL,
  sistema     TINYINT NOT NULL DEFAULT 0,              -- 1 = aviso automático (alguien entró, reunión reprogramada…)
  responde_a  INT UNSIGNED NULL,                       -- mensaje al que responde
  editado     TINYINT NOT NULL DEFAULT 0,
  eliminado   TINYINT NOT NULL DEFAULT 0,
  archivo     VARCHAR(80) NULL,                        -- nombre guardado en storage/chat
  archivo_nombre VARCHAR(160) NULL,
  archivo_tipo   VARCHAR(100) NULL,
  archivo_size   INT UNSIGNED NULL,
  menciones   VARCHAR(255) NULL,                       -- ids de usuarios mencionados
  act         INT UNSIGNED NOT NULL DEFAULT 0,         -- sube al editar / borrar / reaccionar (para refrescar)
  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_chat_msg_sala (sala_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_sala_usuarios (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sala_id          INT UNSIGNED NOT NULL,
  usuario_id       INT UNSIGNED NOT NULL,
  rol              VARCHAR(10) NOT NULL DEFAULT 'miembro',   -- admin | miembro
  ultimo_leido_id  INT UNSIGNED NOT NULL DEFAULT 0,          -- para contar los mensajes sin leer
  created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_chat_sala_usuario (sala_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_reacciones (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mensaje_id  INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED NOT NULL,
  emoji       VARCHAR(16) NOT NULL,
  UNIQUE KEY uq_chat_reaccion (mensaje_id, usuario_id, emoji)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_reuniones (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sala_id         INT UNSIGNED NOT NULL,
  titulo          VARCHAR(120) NOT NULL,
  descripcion     VARCHAR(255) NULL,
  inicio          DATETIME NOT NULL,
  duracion_min    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  enlace          VARCHAR(250) NOT NULL,
  organizador_id  INT UNSIGNED NOT NULL,
  estado          VARCHAR(12) NOT NULL DEFAULT 'programada',  -- programada | cancelada (en curso / finalizada se calculan)
  created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_chat_reu_inicio (inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO chat_salas (id, nombre, descripcion, tipo, activa)
SELECT 1, 'General', 'Todo el personal', 'general', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM chat_salas WHERE id = 1);

ALTER TABLE chat_salas
  ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS usuario1_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS usuario2_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS area1_id SMALLINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS area2_id SMALLINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS creada_por INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS activa TINYINT NOT NULL DEFAULT 1,
  MODIFY COLUMN tipo VARCHAR(15) NOT NULL DEFAULT 'grupo';
ALTER TABLE chat_mensajes
  ADD COLUMN IF NOT EXISTS sistema TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS responde_a INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS editado TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS eliminado TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS archivo VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS archivo_nombre VARCHAR(160) NULL,
  ADD COLUMN IF NOT EXISTS archivo_tipo VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS archivo_size INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS menciones VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS act INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE chat_mensajes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- para los emojis
ALTER TABLE chat_sala_usuarios
  ADD COLUMN IF NOT EXISTS rol VARCHAR(10) NOT NULL DEFAULT 'miembro',
  ADD COLUMN IF NOT EXISTS ultimo_leido_id INT UNSIGNED NOT NULL DEFAULT 0;
