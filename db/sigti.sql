-- ============================================================
-- SIGTI - Sistema Integral de Gestión TI
-- PASO 1: Base de Datos completa
-- Motor: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

DROP DATABASE IF EXISTS sigti;
CREATE DATABASE sigti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sigti;

-- ============================================================
-- 1. SEGURIDAD: ROLES, PERMISOS, USUARIOS
-- ============================================================
CREATE TABLE roles (
  id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(50) NOT NULL UNIQUE,
  descripcion   VARCHAR(200),
  es_sistema    TINYINT(1) NOT NULL DEFAULT 0,
  area_id       TINYINT UNSIGNED NULL            -- area a la que pertenece el rol (opcional)
) ENGINE=InnoDB;

CREATE TABLE permisos (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  modulo        VARCHAR(40) NOT NULL,
  accion        VARCHAR(20) NOT NULL,
  descripcion   VARCHAR(200),
  UNIQUE KEY uq_modulo_accion (modulo, accion)
) ENGINE=InnoDB;

CREATE TABLE rol_permisos (
  rol_id        TINYINT UNSIGNED NOT NULL,
  permiso_id    SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  FOREIGN KEY (rol_id)     REFERENCES roles(id),
  FOREIGN KEY (permiso_id) REFERENCES permisos(id)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario           VARCHAR(50)  NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NOT NULL,
  personal_id       INT UNSIGNED NULL,          -- vínculo con personal (FK se agrega luego)
  rol_id            TINYINT UNSIGNED NOT NULL,
  nombre_completo   VARCHAR(120) NOT NULL,
  correo            VARCHAR(120),
  estado            ENUM('activo','bloqueado','inactivo') NOT NULL DEFAULT 'activo',
  ultimo_acceso     DATETIME NULL,
  intentos_fallidos TINYINT UNSIGNED DEFAULT 0,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ============================================================
-- 2. ORGANIZACIÓN: ÁREAS
-- ============================================================
CREATE TABLE areas (
  id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(80) NOT NULL UNIQUE,
  codigo      VARCHAR(8) NULL,                    -- prefijo del codigo de usuario del personal (SIS, RH, ...)
  descripcion VARCHAR(200),
  estado      ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB;

-- ============================================================
-- 3. PERSONAL (ALTAS Y CESES)
-- ============================================================
CREATE TABLE personal (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dni                 VARCHAR(15) NOT NULL UNIQUE,
  nombres             VARCHAR(80) NOT NULL,
  apellido_paterno    VARCHAR(40) NULL,
  apellido_materno    VARCHAR(40) NULL,
  apellidos           VARCHAR(80) NOT NULL,   -- "paterno materno" (lo arma el sistema; lo leen actas, tickets y reportes)
  correo_personal     VARCHAR(120),
  correo_corporativo  VARCHAR(120),
  telefono            VARCHAR(20),
  area_id             TINYINT UNSIGNED NULL,
  cargo               VARCHAR(80),
  jefe_id             INT UNSIGNED NULL,
  fecha_ingreso       DATE NULL,
  fecha_cese          DATE NULL,
  fecha_nacimiento    DATE NULL,
  codigo_usuario      VARCHAR(20) NULL,       -- SIS-0001: prefijo del area + correlativo (lo asigna el sistema)
  locker              VARCHAR(20) NULL,       -- locker asignado (opcional)
  foto                VARCHAR(80) NULL,       -- foto propia para el carnet (storage/fotos-personal)
  tipo_personal       ENUM('empleado','contratista','practicante','tercero') NOT NULL DEFAULT 'empleado',
  estado              ENUM('pre_ingreso','activo','cese_programado','en_proceso_cese','cesado') NOT NULL DEFAULT 'pre_ingreso',
  observaciones       TEXT,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id)  REFERENCES areas(id),
  FOREIGN KEY (jefe_id)  REFERENCES personal(id)
) ENGINE=InnoDB;

-- FK de usuarios -> personal (recién posible ahora)
ALTER TABLE usuarios ADD FOREIGN KEY (personal_id) REFERENCES personal(id);

-- Ajustes por usuario sobre los permisos de su rol (concedido=1 agrega, 0 quita)
CREATE TABLE IF NOT EXISTS usuario_permisos (
  usuario_id INT UNSIGNED NOT NULL,
  permiso_id SMALLINT UNSIGNED NOT NULL,
  concedido  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (usuario_id, permiso_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Carnets del personal
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

-- ============================================================
-- 4. CHECKLISTS (INGRESO Y CESE)
-- ============================================================
CREATE TABLE checklist_plantillas (
  id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo        ENUM('ingreso','cese') NOT NULL,
  nombre      VARCHAR(100) NOT NULL,
  descripcion VARCHAR(200),
  activo      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE checklist_items (
  id           SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plantilla_id TINYINT UNSIGNED NOT NULL,
  item         VARCHAR(200) NOT NULL,
  orden        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  responsable  VARCHAR(60),                     -- área responsable (Sistemas, Seguridad...)
  obligatorio  TINYINT(1) NOT NULL DEFAULT 1,
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (plantilla_id) REFERENCES checklist_plantillas(id)
) ENGINE=InnoDB;

CREATE TABLE personal_checklist (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  personal_id           INT UNSIGNED NOT NULL,
  item_id               SMALLINT UNSIGNED NOT NULL,
  responsable_usuario_id INT UNSIGNED NULL,     -- usuario del sistema que lo ejecutó
  completado            TINYINT(1) NOT NULL DEFAULT 0,
  fecha_completado      DATETIME NULL,
  observacion           VARCHAR(250),
  UNIQUE KEY uq_personal_item (personal_id, item_id),
  FOREIGN KEY (personal_id)            REFERENCES personal(id),
  FOREIGN KEY (item_id)                REFERENCES checklist_items(id),
  FOREIGN KEY (responsable_usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. MESA DE AYUDA: CATÁLOGOS Y TICKETS
-- ============================================================
CREATE TABLE categorias (
  id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(60) NOT NULL UNIQUE,
  descripcion VARCHAR(200),
  activo      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE subcategorias (
  id           SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria_id TINYINT UNSIGNED NOT NULL,
  nombre       VARCHAR(80) NOT NULL,
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

CREATE TABLE prioridades (
  id        TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(20) NOT NULL UNIQUE,
  nivel     TINYINT UNSIGNED NOT NULL,
  color     VARCHAR(10) NOT NULL DEFAULT '#6c757d',
  sla_horas SMALLINT UNSIGNED NOT NULL,
  activo    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE tickets (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo                 VARCHAR(20) NOT NULL UNIQUE,        -- TK-2025-00001
  titulo                 VARCHAR(150) NOT NULL,
  descripcion            TEXT NOT NULL,
  solicitante_personal_id INT UNSIGNED NOT NULL,
  area_afectada_id       TINYINT UNSIGNED NOT NULL,
  categoria_id           TINYINT UNSIGNED NOT NULL,
  subcategoria_id        SMALLINT UNSIGNED NULL,
  prioridad_id           TINYINT UNSIGNED NOT NULL,
  impacto                ENUM('individual','area','empresa') NOT NULL DEFAULT 'individual',
  equipo_id              INT UNSIGNED NULL,                  -- vínculo opcional a inventario
  tecnico_usuario_id     INT UNSIGNED NULL,
  estado                 ENUM('abierto','asignado','en_atencion','pendiente_usuario',
                              'pendiente_proveedor','resuelto','cerrado','cancelado') NOT NULL DEFAULT 'abierto',
  nivel_atencion         TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- N1 / N2 / N3
  reaperturas            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fecha_creacion         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_asignacion       DATETIME NULL,
  fecha_primera_respuesta DATETIME NULL,
  fecha_resolucion       DATETIME NULL,
  fecha_cierre           DATETIME NULL,
  sla_limite             DATETIME NULL,                       -- fecha límite según SLA
  solucion               TEXT,
  tipo_solucion          VARCHAR(30) NULL,                    -- como se resolvio (remoto, en sitio...)
  tiempo_invertido_min   INT UNSIGNED NULL,                   -- minutos de trabajo
  resuelto_por           INT UNSIGNED NULL,                   -- quien lo finalizo
  cerrado_por            INT UNSIGNED NULL,
  motivo_retraso         VARCHAR(250) NULL,                   -- obligatorio si se finalizo fuera del SLA
  motivo_cancelacion     VARCHAR(250),
  created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ticket_estado (estado),
  INDEX idx_ticket_tecnico (tecnico_usuario_id),
  INDEX idx_ticket_solicitante (solicitante_personal_id),
  INDEX idx_ticket_fecha (fecha_creacion),
  FOREIGN KEY (solicitante_personal_id) REFERENCES personal(id),
  FOREIGN KEY (area_afectada_id) REFERENCES areas(id),
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id),
  FOREIGN KEY (prioridad_id) REFERENCES prioridades(id),
  FOREIGN KEY (tecnico_usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE ticket_comentarios (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  mensaje    TEXT NOT NULL,
  interno    TINYINT(1) NOT NULL DEFAULT 0,     -- 1 = visible solo para técnicos
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE ticket_adjuntos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id       INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  archivo         VARCHAR(255) NOT NULL,        -- nombre guardado en disco
  nombre_original VARCHAR(255) NOT NULL,
  tamano_kb       INT UNSIGNED,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id)  REFERENCES tickets(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE ticket_historial (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  evento     VARCHAR(60) NOT NULL,              -- creado, asignado, cambio_estado, comentario...
  detalle    VARCHAR(250),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id)  REFERENCES tickets(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE encuestas (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT UNSIGNED NOT NULL UNIQUE,
  puntaje    TINYINT UNSIGNED NOT NULL,         -- 1 a 5
  comentario VARCHAR(300),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. INVENTARIO DE EQUIPOS
-- ============================================================
CREATE TABLE tipo_equipos (
  id     TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL UNIQUE,
  familia VARCHAR(20) NOT NULL DEFAULT 'otro',   -- define el contenido del acta: computo, movil, impresion, red, energia, visual, periferico, telefonia, otro
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE equipos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo           VARCHAR(20) NOT NULL UNIQUE,           -- EQ-00001
  tipo_equipo_id   TINYINT UNSIGNED NOT NULL,
  marca            VARCHAR(60),
  modelo           VARCHAR(80),
  nro_serie        VARCHAR(80),
  activo_fijo      VARCHAR(40),                           -- código contable
  imei             VARCHAR(20),
  mac              VARCHAR(20),
  estado           ENUM('en_stock','asignado','en_prestamo','en_revision',
                        'en_mantenimiento','en_reparacion_externa','obsoleto',
                        'dado_de_baja') NOT NULL DEFAULT 'en_stock',
  condicion        ENUM('nuevo','bueno','regular','danado','irreparable') NOT NULL DEFAULT 'nuevo',
  especificaciones TEXT,                                  -- JSON: CPU, RAM, disco, SO...
  fecha_compra     DATE NULL,
  proveedor_compra VARCHAR(100),
  costo            DECIMAL(12,2) NULL,
  garantia_hasta   DATE NULL,
  ubicacion        VARCHAR(100),
  verificado_ubicacion DATETIME NULL,
  software_verificado_en  DATETIME NULL,                  -- ultima confirmacion del inventario de software
  software_verificado_por INT UNSIGNED NULL,
  observaciones    TEXT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_equipo_estado (estado),
  INDEX idx_equipo_tipo (tipo_equipo_id),
  FOREIGN KEY (tipo_equipo_id) REFERENCES tipo_equipos(id)
) ENGINE=InnoDB;

-- Vínculo tickets -> equipos (recién posible ahora)
ALTER TABLE tickets ADD FOREIGN KEY (equipo_id) REFERENCES equipos(id);

CREATE TABLE equipo_accesorios (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id INT UNSIGNED NOT NULL,
  nombre    VARCHAR(80) NOT NULL,               -- cargador, maletín, tóner...
  entregado TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (equipo_id) REFERENCES equipos(id)
) ENGINE=InnoDB;

CREATE TABLE equipo_licencias (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id         INT UNSIGNED NOT NULL,
  software          VARCHAR(80) NOT NULL,
  clave             VARCHAR(150),
  tipo              ENUM('perpetua','suscripcion','oem') DEFAULT 'perpetua',
  fecha_inicio      DATE NULL,
  fecha_vencimiento DATE NULL,
  costo             DECIMAL(10,2) NULL,
  FOREIGN KEY (equipo_id) REFERENCES equipos(id)
) ENGINE=InnoDB;

CREATE TABLE equipo_historial (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id  INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  evento     VARCHAR(60) NOT NULL,              -- creado, asignado, devuelto, mantenimiento...
  detalle    VARCHAR(250),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (equipo_id)  REFERENCES equipos(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Seguimiento manual de ubicaciones (presente en la base activa).
CREATE TABLE equipo_ubicaciones (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id     INT UNSIGNED NOT NULL,
  ubicacion     VARCHAR(150) NOT NULL,
  tipo          ENUM('verificacion','traslado','asignacion','devolucion','mantenimiento','alta') NOT NULL,
  personal_id   INT UNSIGNED NULL,
  observaciones VARCHAR(250),
  usuario_id    INT UNSIGNED NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_eq (equipo_id)
) ENGINE=InnoDB;

-- Check-in del agente existente. Los datos reportados son observaciones del agente,
-- no atributos verificados del navegador ni cambios automáticos del inventario.
CREATE TABLE equipos_rastreo_config (
  equipo_id       INT UNSIGNED PRIMARY KEY,
  token_rastreo   VARCHAR(64) NOT NULL,
  activo          TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_checkin  DATETIME NULL,
  modo_perdido    TINYINT(1) NOT NULL DEFAULT 0,      -- equipo marcado como perdido / robado
  perdido_desde   DATETIME NULL,
  perdido_motivo  VARCHAR(10) NULL,                   -- perdido | robo
  perdido_nota    VARCHAR(250) NULL,
  perdido_por     INT UNSIGNED NULL,
  ultima_alerta   DATETIME NULL
) ENGINE=InnoDB;

-- Sedes / redes conocidas: ubican a un equipo en su oficina u obra aunque no tenga GPS
CREATE TABLE rastreo_sedes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(120) NOT NULL,
  ip_publica    VARCHAR(45) NULL,
  prefijo_local VARCHAR(20) NULL,
  proyecto_id   INT UNSIGNED NULL,
  direccion     VARCHAR(200) NULL,
  latitud       DECIMAL(10,7) NULL,
  longitud      DECIMAL(10,7) NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  creado_en     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sede_ip (ip_publica)
) ENGINE=InnoDB;

CREATE TABLE equipos_rastreo (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id     INT UNSIGNED NOT NULL,
  ip_publica    VARCHAR(45),
  ip_local      VARCHAR(45),
  mac_address   VARCHAR(20),
  hostname      VARCHAR(100),
  user_agent    VARCHAR(250),
  ubicacion     VARCHAR(200),
  latitud       DECIMAL(10,7),
  longitud      DECIMAL(10,7),
  fuente        VARCHAR(15) NULL,                     -- gps | sede | ip | red
  precision_m   INT UNSIGNED NULL,
  wifi          VARCHAR(120) NULL,
  sede_id       INT UNSIGNED NULL,
  mac_coincide  TINYINT NULL,                         -- 1 = igual a la MAC del inventario, 0 = distinta
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_equipo (equipo_id)
) ENGINE=InnoDB;

-- ============================================================
-- 7. PROVEEDORES
-- ============================================================
CREATE TABLE proveedores (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_documento VARCHAR(3) NOT NULL DEFAULT 'ruc',     -- 'ruc' | 'dni'
  ruc          VARCHAR(15),                              -- numero del documento (RUC u DNI)
  nombre       VARCHAR(120) NOT NULL,
  direccion    VARCHAR(200) NULL,
  contacto     VARCHAR(100),
  telefono     VARCHAR(30),
  correo       VARCHAR(120),
  especialidad VARCHAR(120),
  estado       ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB;

-- ============================================================
-- 8. MANTENIMIENTO
-- ============================================================
CREATE TABLE mantenimientos (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo                 VARCHAR(20) NOT NULL UNIQUE,       -- MT-2025-00001
  equipo_id              INT UNSIGNED NOT NULL,
  ticket_id              INT UNSIGNED NULL,
  tipo                   ENUM('correctivo','preventivo','limpieza','actualizacion') NOT NULL,
  origen                 ENUM('solicitud','ticket','programado') NOT NULL DEFAULT 'solicitud',
  solicitante_personal_id INT UNSIGNED NULL,
  tecnico_usuario_id     INT UNSIGNED NULL,
  proveedor_id           INT UNSIGNED NULL,
  estado                 ENUM('solicitado','en_evaluacion','en_proceso','enviado_proveedor',
                              'cotizado','aprobado','rechazado','en_reparacion',
                              'recibido_reparado','listo','entregado','devuelto_stock',
                              'cerrado','cancelado','no_reparable') NOT NULL DEFAULT 'solicitado',
  diagnostico            TEXT,
  solucion               TEXT,
  fecha_inicio           DATETIME NULL,
  fecha_envio_proveedor  DATETIME NULL,
  fecha_retorno_estimada DATE NULL,
  fecha_fin              DATETIME NULL,
  costo_mano_obra        DECIMAL(10,2) DEFAULT 0,
  costo_repuestos        DECIMAL(10,2) DEFAULT 0,
  costo_total            DECIMAL(10,2) DEFAULT 0,
  cotizacion_archivo     VARCHAR(255),
  cotizacion_monto       DECIMAL(10,2) NULL,
  autorizado_por         INT UNSIGNED NULL,     -- usuario que aprueba el costo
  motivo_cancelacion     VARCHAR(250),
  observaciones          TEXT,
  sla_limite             DATETIME NULL,
  programado_id          INT UNSIGNED NULL,                   -- programacion preventiva que lo genero
  resultado_final        VARCHAR(15) NULL,                    -- completado | parcial
  finalizado_por         INT UNSIGNED NULL,
  atraso_min             INT UNSIGNED NULL,                   -- minutos de atraso al finalizar (0 = a tiempo)
  motivo_atraso          VARCHAR(250) NULL,
  destino_envio          VARCHAR(150) NULL,
  created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_mant_estado (estado),
  INDEX idx_mant_equipo (equipo_id),
  FOREIGN KEY (equipo_id)              REFERENCES equipos(id),
  FOREIGN KEY (ticket_id)              REFERENCES tickets(id),
  FOREIGN KEY (solicitante_personal_id) REFERENCES personal(id),
  FOREIGN KEY (tecnico_usuario_id)     REFERENCES usuarios(id),
  FOREIGN KEY (proveedor_id)           REFERENCES proveedores(id),
  FOREIGN KEY (autorizado_por)         REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE mantenimiento_repuestos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mantenimiento_id INT UNSIGNED NOT NULL,
  descripcion     VARCHAR(150) NOT NULL,
  cantidad        DECIMAL(10,2) NOT NULL DEFAULT 1,
  costo_unitario  DECIMAL(10,2) NOT NULL DEFAULT 0,
  proveedor_id    INT UNSIGNED NULL,
  FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id),
  FOREIGN KEY (proveedor_id)     REFERENCES proveedores(id)
) ENGINE=InnoDB;

CREATE TABLE mantenimiento_percances (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mantenimiento_id INT UNSIGNED NOT NULL,
  tipo            VARCHAR(30) NOT NULL,                       -- falta_repuesto, demora_proveedor, dano_adicional...
  detalle         VARCHAR(250) NOT NULL,
  usuario_id      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL,
  KEY idx_percance_mt (mantenimiento_id)
) ENGINE=InnoDB;

CREATE TABLE mantenimiento_evidencias (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mantenimiento_id INT UNSIGNED NOT NULL,
  tipo            ENUM('antes','despues','otro') NOT NULL DEFAULT 'otro',
  archivo         VARCHAR(255) NOT NULL,
  descripcion     VARCHAR(200),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id)
) ENGINE=InnoDB;

CREATE TABLE mantenimientos_programados (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id       INT UNSIGNED NOT NULL,
  tipo            ENUM('preventivo','limpieza','actualizacion') NOT NULL DEFAULT 'preventivo',
  frecuencia      ENUM('mensual','trimestral','semestral','anual') NOT NULL DEFAULT 'semestral',
  ultima_ejecucion DATE NULL,
  proxima_fecha   DATE NOT NULL,
  responsable_id  INT UNSIGNED NULL,
  activo          TINYINT(1) NOT NULL DEFAULT 1,
  mantenimiento_actual_id  INT UNSIGNED NULL,   -- mantenimiento generado y aun sin finalizar (ciclo en curso)
  ultimo_mantenimiento_id  INT UNSIGNED NULL,   -- ultimo mantenimiento finalizado de esta programacion
  aviso_para      DATE NULL,                    -- fecha para la que ya se envio el aviso previo
  FOREIGN KEY (equipo_id)      REFERENCES equipos(id),
  FOREIGN KEY (responsable_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 9. SOLICITUDES, ASIGNACIONES Y ACTAS
-- ============================================================
CREATE TABLE solicitudes_equipo (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo                 VARCHAR(20) NOT NULL UNIQUE,       -- SQ-2025-00001
  solicitante_personal_id INT UNSIGNED NOT NULL,
  area_id                TINYINT UNSIGNED NOT NULL,
  tipo_equipo_id         TINYINT UNSIGNED NOT NULL,
  cantidad               TINYINT UNSIGNED NOT NULL DEFAULT 1,
  entregados             TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- equipos ya entregados de esta solicitud
  proyecto_id            INT UNSIGNED NULL,                      -- si se pide para un proyecto u obra
  justificacion          VARCHAR(300) NOT NULL,
  aprobado_por           INT UNSIGNED NULL,
  estado                 ENUM('pendiente','aprobada','rechazada','atendida','cancelada') NOT NULL DEFAULT 'pendiente',
  ticket_id              INT UNSIGNED NULL,
  fecha_solicitud        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_aprobacion       DATETIME NULL,
  observaciones          VARCHAR(300),
  FOREIGN KEY (solicitante_personal_id) REFERENCES personal(id),
  FOREIGN KEY (area_id)        REFERENCES areas(id),
  FOREIGN KEY (tipo_equipo_id) REFERENCES tipo_equipos(id),
  FOREIGN KEY (aprobado_por)   REFERENCES usuarios(id),
  FOREIGN KEY (ticket_id)      REFERENCES tickets(id)
) ENGINE=InnoDB;

CREATE TABLE actas (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo                VARCHAR(20) NOT NULL UNIQUE,        -- AC-2025-00001
  tipo                  VARCHAR(20) NOT NULL,                -- entrega | cambio | devolucion (o tipo_N de un tipo propio)
  personal_id           INT UNSIGNED NOT NULL,
  responsable_usuario_id INT UNSIGNED NOT NULL,              -- técnico de TI
  asignacion_id         INT UNSIGNED NULL,                   -- (sin FK: evita referencia circular)
  fecha                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  contenido             TEXT,                                -- JSON con el detalle del acta
  archivo_pdf           VARCHAR(255),
  firmado               TINYINT(1) NOT NULL DEFAULT 0,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  token                 VARCHAR(64) NULL,                    -- enlace para ver / imprimir el acta
  familia               VARCHAR(20) NULL,                    -- tipo de equipo del acta (o 'mixto')
  destino_tipo          VARCHAR(10) NULL,                    -- persona | area | proyecto | obra
  destino_nombre        VARCHAR(150) NULL,
  equipos_resumen       VARCHAR(300) NULL,
  firmado_en            DATETIME NULL,
  firmado_por           INT UNSIGNED NULL,
  INDEX idx_acta_asignacion (asignacion_id),
  FOREIGN KEY (personal_id)            REFERENCES personal(id),
  FOREIGN KEY (responsable_usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Proyectos y obras: destinos a los que se asignan equipos de TI (tipo = 'proyecto' | 'obra')
CREATE TABLE proyectos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo         VARCHAR(20) NOT NULL,                      -- PRY-001 / OBR-001 (o el codigo propio)
  nombre         VARCHAR(120) NOT NULL,
  tipo           VARCHAR(10) NOT NULL DEFAULT 'proyecto',   -- proyecto | obra
  cliente        VARCHAR(120) NULL,
  ubicacion      VARCHAR(150) NULL,
  responsable_id INT UNSIGNED NULL,                         -- jefe de proyecto / residente (personal)
  fecha_inicio   DATE NULL,
  fecha_fin      DATE NULL,
  estado         ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',     -- inactivo = finalizado
  descripcion    VARCHAR(250) NULL,
  fecha_cierre   DATE NULL,                                 -- cuando se finalizo
  creado_en      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_proyecto_codigo (codigo),
  KEY idx_proyecto_tipo (tipo)
) ENGINE=InnoDB;

CREATE TABLE asignaciones (
  id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id                 INT UNSIGNED NOT NULL,
  personal_id               INT UNSIGNED NULL,               -- solo si el destino es una persona
  area_id                   TINYINT UNSIGNED NULL,           -- solo si el destino es un area
  proyecto_id               INT UNSIGNED NULL,               -- solo si el destino es un proyecto u obra
  destino_tipo              VARCHAR(10) NOT NULL DEFAULT 'persona',  -- persona | area | proyecto | obra
  responsable_id            INT UNSIGNED NULL,               -- quien recibe y firma el acta (en persona = la propia persona)
  destino_detalle           VARCHAR(150) NULL,               -- frente / zona / piso
  tipo                      ENUM('permanente','prestamo') NOT NULL DEFAULT 'permanente',
  estado                    ENUM('activa','devuelta','vencida','cancelada') NOT NULL DEFAULT 'activa',
  fecha_entrega             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_devolucion_esperada DATE NULL,                       -- préstamos y equipos enviados a proyecto / obra
  aviso_dev                 TINYINT NOT NULL DEFAULT 0,      -- 0 sin aviso · 1 aviso previo · 2 aviso de vencida
  fecha_devolucion_real     DATETIME NULL,
  condicion_entrega         ENUM('nuevo','bueno','regular','danado') NULL,
  condicion_devolucion      ENUM('nuevo','bueno','regular','danado','faltante') NULL,
  obs_devolucion            VARCHAR(300),
  acta_entrega_id           INT UNSIGNED NULL,               -- (sin FK: evita referencia circular)
  acta_devolucion_id        INT UNSIGNED NULL,               -- (sin FK: evita referencia circular)
  created_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_asig_equipo (equipo_id),
  INDEX idx_asig_personal (personal_id),
  INDEX idx_asig_proyecto (proyecto_id),
  INDEX idx_asig_destino (destino_tipo, estado),
  FOREIGN KEY (equipo_id)   REFERENCES equipos(id),
  FOREIGN KEY (personal_id) REFERENCES personal(id),
  FOREIGN KEY (area_id)     REFERENCES areas(id)
) ENGINE=InnoDB;

CREATE TABLE bajas_equipos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id      INT UNSIGNED NOT NULL,
  motivo         ENUM('obsolescencia','danado_irreparable','robo','venta','perdida','otros') NOT NULL,
  autorizado_por INT UNSIGNED NOT NULL,
  fecha          DATE NOT NULL,
  destino        VARCHAR(100),
  observaciones  VARCHAR(300),
  FOREIGN KEY (equipo_id)      REFERENCES equipos(id),
  FOREIGN KEY (autorizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 10. SOPORTE: CORRELATIVOS, NOTIFICACIONES, AUDITORÍA, CONFIG
-- ============================================================
CREATE TABLE correlativos (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_doc      ENUM('ticket','mantenimiento','solicitud','acta','equipo') NOT NULL,
  anio          SMALLINT UNSIGNED NOT NULL,
  ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_doc_anio (tipo_doc, anio)
) ENGINE=InnoDB;

CREATE TABLE notificaciones (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_destino_id INT UNSIGNED NOT NULL,
  tipo               ENUM('ticket','mantenimiento','asignacion','cese','solicitud','sistema') NOT NULL,
  titulo             VARCHAR(120) NOT NULL,
  mensaje            VARCHAR(300),
  url                VARCHAR(200),
  leido              TINYINT(1) NOT NULL DEFAULT 0,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_usuario (usuario_destino_id, leido),
  FOREIGN KEY (usuario_destino_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE auditoria_logs (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id       INT UNSIGNED NULL,
  usuario          VARCHAR(50) NULL,
  usuario_nombre   VARCHAR(120) NULL,
  rol_snapshot     VARCHAR(50) NULL,
  actor_tipo       VARCHAR(20) NULL,
  modulo           VARCHAR(40) NULL,
  submodulo        VARCHAR(60) NULL,
  accion           ENUM('crear','actualizar','eliminar','login','logout',
                        'login_fallido','exportar','aprobar','cambiar_estado') NOT NULL,
  accion_detalle   VARCHAR(80) NULL,
  tabla            VARCHAR(60),
  registro_id      INT UNSIGNED,
  equipo_id        INT UNSIGNED NULL,
  resultado        VARCHAR(20) NULL,
  motivo           VARCHAR(500) NULL,
  datos_anteriores TEXT,
  datos_nuevos     TEXT,
  ip               VARCHAR(45),
  ip_real          VARCHAR(45),
  fuente_ip        VARCHAR(40) NULL,
  user_agent       VARCHAR(250),
  session_ref      CHAR(64) NULL,
  precision_geo    DECIMAL(10,2) NULL,
  fuente_geolocalizacion VARCHAR(30) NULL,
  ubicacion        VARCHAR(200) NULL,
  latitud          DECIMAL(10,7) NULL,
  longitud         DECIMAL(10,7) NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_usuario (usuario_id),
  INDEX idx_audit_tabla (tabla, registro_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE ip_geocache (
  ip           VARCHAR(45) PRIMARY KEY,
  ciudad       VARCHAR(100),
  region       VARCHAR(100),
  pais         VARCHAR(100),
  pais_codigo  VARCHAR(5),
  isp          VARCHAR(150),
  latitud      DECIMAL(10,7),
  longitud     DECIMAL(10,7),
  consultado   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(60) NOT NULL UNIQUE,
  valor       VARCHAR(250),
  descripcion VARCHAR(200)
) ENGINE=InnoDB;

-- ============================================================
-- SOFTWARE (catalogo, licencias e instalaciones por equipo; control para auditorias y gerencia)
-- El sistema tambien las crea solo la primera vez que se abre el modulo Software.
-- ============================================================
CREATE TABLE IF NOT EXISTS software_catalogo (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(100) NOT NULL,
    fabricante        VARCHAR(80) NULL,
    categoria         VARCHAR(20) NOT NULL DEFAULT 'otro',
    requiere_licencia TINYINT(1) NOT NULL DEFAULT 1,
    autorizacion      VARCHAR(12) NOT NULL DEFAULT 'autorizado',
    estandar          TINYINT(1) NOT NULL DEFAULT 0,
    descripcion       VARCHAR(255) NULL,
    activo            TINYINT(1) NOT NULL DEFAULT 1,
    creado_por        INT UNSIGNED NULL,
    created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    KEY idx_sw_cat_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS software_licencias (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    software_id       INT UNSIGNED NOT NULL,
    tipo              VARCHAR(12) NOT NULL DEFAULT 'perpetua',
    clave             VARCHAR(190) NULL,
    puestos           INT UNSIGNED NOT NULL DEFAULT 1,
    fecha_compra      DATE NULL,
    fecha_vencimiento DATE NULL,
    costo             DECIMAL(10,2) NULL,
    proveedor         VARCHAR(100) NULL,
    documento         VARCHAR(60) NULL,
    observaciones     VARCHAR(255) NULL,
    activo            TINYINT(1) NOT NULL DEFAULT 1,
    legacy_id         INT UNSIGNED NULL,
    creado_por        INT UNSIGNED NULL,
    created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    KEY idx_sw_lic_sw (software_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS equipo_software (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id           INT UNSIGNED NOT NULL,
    software_id         INT UNSIGNED NOT NULL,
    version             VARCHAR(40) NULL,
    licencia_id         INT UNSIGNED NULL,
    estado              VARCHAR(12) NOT NULL DEFAULT 'instalado',
    fecha_instalacion   DATE NULL,
    fecha_desinstalacion DATE NULL,
    instalado_por       INT UNSIGNED NULL,
    desinstalado_por    INT UNSIGNED NULL,
    observaciones       VARCHAR(255) NULL,
    legacy_id           INT UNSIGNED NULL,
    created_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NULL DEFAULT NULL,
    KEY idx_es_equipo (equipo_id),
    KEY idx_es_sw (software_id),
    KEY idx_es_lic (licencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CHAT INTERNO (personas, áreas, entre áreas, grupos y reuniones)
-- El sistema también las crea solo la primera vez que se usa el Chat.
-- ============================================================
-- Anuncios del dashboard (vigencia, destinatarios por rol o area, fijos) y quien los cerro
CREATE TABLE IF NOT EXISTS anuncios (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo       VARCHAR(150) NOT NULL,
  mensaje      TEXT NOT NULL,
  tipo         VARCHAR(15) NOT NULL DEFAULT 'info',
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  fecha_desde  DATE NULL,
  hora_desde   TIME NULL,
  fecha_hasta  DATE NULL,
  hora_hasta   TIME NULL,
  destino      VARCHAR(10) NOT NULL DEFAULT 'todos',
  destino_ids  VARCHAR(255) NULL,
  fijo         TINYINT(1) NOT NULL DEFAULT 0,
  creado_por   INT UNSIGNED NULL,
  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anuncio_cierres (
  anuncio_id  INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED NOT NULL,
  cerrado_en  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (anuncio_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- ============================================================
-- DATOS INICIALES (SEEDERS)
-- ============================================================

-- ---------- ROLES ----------
INSERT INTO roles (id, nombre, descripcion, es_sistema) VALUES
(1,'super_admin',  'Super Administrador - acceso total', 1),
(2,'admin_ti',     'Administrador de TI', 0),
(3,'supervisor',   'Supervisor de Mesa de Ayuda', 0),
(4,'tecnico',      'Técnico de Soporte', 0),
(5,'jefe_area',    'Jefe de Área', 0),
(6,'usuario_final','Usuario Final', 0),
(7,'rrhh',         'Recursos Humanos', 0);

-- ---------- PERMISOS ----------
INSERT INTO permisos (modulo, accion, descripcion) VALUES
('personal','ver','Ver listado de personal'),
('personal','crear','Registrar nuevo personal'),
('personal','editar','Editar datos de personal'),
('personal','eliminar','Eliminar personal (lógico)'),
('personal','cese','Registrar y procesar ceses'),
('tickets','ver','Ver todos los tickets'),
('tickets','ver_propios','Ver únicamente sus tickets'),
('tickets','ver_area','Ver tickets de su área'),
('tickets','crear','Crear tickets'),
('tickets','editar','Editar tickets'),
('tickets','asignar','Asignar/reasignar técnicos'),
('tickets','cambiar_estado','Cambiar estado de tickets'),
('equipos','ver','Ver inventario de equipos'),
('equipos','crear','Registrar equipos'),
('equipos','editar','Editar equipos'),
('equipos','baja','Dar de baja equipos'),
('mantenimiento','ver','Ver mantenimientos'),
('mantenimiento','crear','Registrar mantenimientos'),
('mantenimiento','editar','Editar mantenimientos'),
('mantenimiento','aprobar_costo','Aprobar cotizaciones de proveedor'),
('mantenimiento','cambiar_estado','Cambiar estado de mantenimientos'),
('asignaciones','ver','Ver asignaciones de equipos'),
('asignaciones','crear','Registrar entregas/asignaciones'),
('asignaciones','devolver','Registrar devoluciones'),
('asignaciones','aprobar','Aprobar solicitudes de equipo'),
('asignaciones','solicitar','Solicitar equipos'),
('actas','generar','Generar actas PDF'),
('reportes','ver','Ver todos los reportes'),
('reportes','ver_area','Ver reportes de su área'),
('configuracion','gestionar','Gestionar catálogos y parámetros'),
('usuarios','gestionar','Gestionar usuarios del sistema'),
('auditoria','ver','Ver logs de auditoría'),
('tickets','eliminar','Eliminar tickets'),
('mantenimiento','eliminar','Eliminar mantenimientos');

-- ---------- ASIGNACIÓN ROL -> PERMISOS ----------
-- Super Admin y Admin TI: TODO
INSERT INTO rol_permisos (rol_id, permiso_id) SELECT 1, id FROM permisos;
INSERT INTO rol_permisos (rol_id, permiso_id) SELECT 2, id FROM permisos;

-- Supervisor: todo excepto config, usuarios y auditoría
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 3, p.id FROM permisos p
WHERE p.modulo NOT IN ('configuracion','usuarios','auditoria')
  AND p.accion <> 'eliminar';

-- Técnico: operación en tickets, equipos, mantenimiento, asignaciones, actas
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 4, p.id FROM permisos p
WHERE (p.modulo IN ('tickets','equipos','mantenimiento','asignaciones','actas')
       AND p.accion IN ('ver','crear','editar','cambiar_estado','devolver'))
   OR (p.modulo='personal' AND p.accion='ver');

-- Jefe de Área: tickets de su área, aprobar solicitudes, reportes de área
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 5, p.id FROM permisos p
WHERE (p.modulo='tickets' AND p.accion IN ('ver_area','crear'))
   OR (p.modulo='reportes' AND p.accion='ver_area')
   OR (p.modulo='asignaciones' AND p.accion IN ('solicitar','aprobar'))
   OR (p.modulo='personal' AND p.accion='ver');

-- Usuario Final: sus tickets y solicitar equipos
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 6, p.id FROM permisos p
WHERE (p.modulo='tickets' AND p.accion IN ('ver_propios','crear'))
   OR (p.modulo='asignaciones' AND p.accion='solicitar');

-- RRHH: gestión completa de personal + reportes
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 7, p.id FROM permisos p
WHERE (p.modulo='personal')
   OR (p.modulo='reportes' AND p.accion='ver');

-- ---------- ÁREAS ----------
INSERT INTO areas (nombre, descripcion) VALUES
('Administración','Dirección y administración general'),
('Sistemas / TI','Área de Tecnología de la Información'),
('Recursos Humanos','Gestión del personal'),
('Contabilidad y Finanzas','Tesorería y contabilidad'),
('Ventas y Marketing','Área comercial'),
('Logística y Almacén','Almacén y distribución'),
('Operaciones','Producción / operaciones'),
('Legal','Área legal');

-- ---------- CHECKLISTS ----------
INSERT INTO checklist_plantillas (tipo, nombre, descripcion) VALUES
('ingreso','Onboarding estándar TI','Checklist de preparación para nuevo ingreso'),
('cese','Offboarding estándar TI','Checklist de salida y bloqueo de accesos');

INSERT INTO checklist_items (plantilla_id, item, orden, responsable, obligatorio) VALUES
(1,'Crear correo corporativo',1,'Sistemas',1),
(1,'Crear usuario de dominio / Active Directory',2,'Sistemas',1),
(1,'Configurar extensión o teléfono asignado',3,'Sistemas',1),
(1,'Otorgar accesos a sistemas internos según cargo',4,'Sistemas',1),
(1,'Configurar acceso VPN (si aplica)',5,'Sistemas',0),
(1,'Entrega de equipo(s) de cómputo y accesorios',6,'Sistemas',1),
(1,'Configurar impresoras y carpetas de red',7,'Sistemas',0);

INSERT INTO checklist_items (plantilla_id, item, orden, responsable, obligatorio) VALUES
(2,'Bloquear usuario de dominio / Active Directory',1,'Sistemas',1),
(2,'Desactivar correo corporativo (respaldo si aplica)',2,'Sistemas',1),
(2,'Revocar accesos a sistemas internos y VPN',3,'Sistemas',1),
(2,'Recuperar equipos y accesorios asignados',4,'Sistemas',1),
(2,'Cierre de líneas telefónicas / plan de celular',5,'Sistemas',0),
(2,'Retiro de accesos físicos y entrega de carnet',6,'Seguridad',1);

-- ---------- CATEGORÍAS Y SUBCATEGORÍAS ----------
INSERT INTO categorias (nombre, descripcion) VALUES
('Hardware','Fallas o solicitudes de equipos físicos'),
('Software','Instalaciones, licencias y errores de aplicaciones'),
('Red y Conectividad','Internet, WiFi, VPN, cableado'),
('Correo','Correo corporativo y Outlook'),
('Accesos y Permisos','Contraseñas, usuarios y permisos'),
('Impresión','Impresoras, drivers y suministros'),
('Equipos TI','Solicitud, cambio, préstamo y devolución de equipos'),
('Telefonía','Líneas fijas y celulares corporativos');

INSERT INTO subcategorias (categoria_id, nombre) VALUES
(1,'Laptop'),(1,'PC de escritorio'),(1,'Impresora'),(1,'Monitor'),
(1,'Periférico (mouse/teclado)'),(1,'Celular'),
(2,'Instalación de programa'),(2,'Licencia de software'),
(2,'Error de aplicación'),(2,'Actualización'),
(3,'Sin internet'),(3,'Internet intermitente'),(3,'WiFi'),(3,'VPN'),(3,'Puerto / cableado de red'),
(4,'Configuración de Outlook'),(4,'Buzón lleno'),(4,'No envía / no recibe'),(4,'Lista de distribución'),
(5,'Olvido de contraseña'),(5,'Bloqueo de cuenta'),(5,'Nuevo permiso de acceso'),(5,'Carpetas compartidas'),
(6,'Driver no instala'),(6,'Cola de impresión atascada'),(6,'Tóner / suministros'),(6,'Impresora en red no responde'),
(7,'Solicitud de equipo nuevo'),(7,'Cambio de equipo'),(7,'Préstamo temporal'),(7,'Devolución de equipo'),
(8,'Línea fija / extensión'),(8,'Celular corporativo'),(8,'Plan de datos / llamadas');

-- ---------- PRIORIDADES + SLA ----------
INSERT INTO prioridades (nombre, nivel, color, sla_horas) VALUES
('Crítica',1,'#dc3545',2),
('Alta',   2,'#fd7e14',8),
('Media',  3,'#ffc107',24),
('Baja',   4,'#28a745',72);

-- ---------- TIPOS DE EQUIPO ----------
INSERT INTO tipo_equipos (nombre, familia) VALUES
('Laptop','computo'),('PC de escritorio','computo'),('Servidor','computo'),('Celular','movil'),('Tablet','movil'),
('Impresora','impresion'),('Escáner','impresion'),('Monitor','visual'),('Proyector','visual'),
('Router / Switch','red'),('Access Point','red'),('UPS','energia'),('Periférico','periferico'),('Otro','otro');

-- ---------- USUARIO ADMIN INICIAL ----------
-- ⚠️ La contraseña se activa en el PASO 2 ejecutando crear_admin.php
INSERT INTO usuarios (usuario, password_hash, rol_id, nombre_completo, correo, estado) VALUES
('admin','PENDIENTE_ACTIVAR',1,'Administrador del Sistema','admin@empresa.com','activo');

-- ---------- CONFIGURACIÓN ----------
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('nombre_empresa','Mi Empresa S.A.C.','Nombre mostrado en actas y reportes'),
('moneda','PEN','Moneda para costos (PEN/USD)'),
('dias_alerta_cese','7','Días de anticipación para alertar ceses próximos'),
('horas_alerta_sla','2','Horas antes del vencimiento para alertar SLA'),
('max_upload_mb','10','Tamaño máximo de archivos adjuntos en MB'),
('extensiones_permitidas','jpg,jpeg,png,pdf,docx,xlsx,txt,zip','Extensiones permitidas en adjuntos'),
('acta_formato_entrega','TI-FOR-003','Código del formato de acta de entrega'),
('acta_formato_entrega_movil','TI-FOR-004','Código del formato de acta de entrega de equipos móviles'),
('acta_formato_cambio','TI-FOR-002','Código del formato de acta de cambio'),
('acta_formato_devolucion','TI-FOR-001','Código del formato de acta de devolución');
