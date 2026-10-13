<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Chat interno
//
// Tipos de conversacion (chat_salas.tipo):
//   general      sala 1: todos los usuarios activos
//   directo      persona <-> persona (solo los dos; Sistemas puede supervisar)
//   grupo        grupo creado por un usuario con los miembros que elija
//   area         canal de un Area: todo su personal (miembros dinamicos)
//   entre_areas  canal entre dos Areas: el personal de ambas (dinamico)
//   reunion      reunion programada (uno con varios) con enlace de videollamada
//
// Las tablas se crean solas la primera vez (y se completan las
// columnas que falten si el chat ya existia en una version anterior).
// ============================================================

class Chat
{
    public const TIPOS = ['general', 'directo', 'grupo', 'area', 'entre_areas', 'reunion'];

    private static bool $ok = false;
    private const ESQ = 2;                 // sube cuando cambia la estructura (se revisa 1 vez por sesion)

    public const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    public const EXT_ARCHIVO = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
    ];

    public static function asegurar(): void
    {
        if (self::$ok) return;
        if (isset($_SESSION) && ($_SESSION['chat_esq'] ?? 0) === self::ESQ) { self::$ok = true; return; }

        $faltaSalas = false;
        try { Database::run('SELECT id FROM chat_salas LIMIT 0'); } catch (Throwable $e) { $faltaSalas = true; }
        if ($faltaSalas) {
            Database::run("CREATE TABLE IF NOT EXISTS chat_salas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                descripcion VARCHAR(255) NULL,
                tipo VARCHAR(15) NOT NULL DEFAULT 'grupo',
                usuario1_id INT UNSIGNED NULL,
                usuario2_id INT UNSIGNED NULL,
                area1_id SMALLINT UNSIGNED NULL,
                area2_id SMALLINT UNSIGNED NULL,
                creada_por INT UNSIGNED NULL,
                activa TINYINT NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_chat_sala_tipo (tipo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $faltaMsg = false;
        try { Database::run('SELECT id FROM chat_mensajes LIMIT 0'); } catch (Throwable $e) { $faltaMsg = true; }
        if ($faltaMsg) {
            Database::run("CREATE TABLE IF NOT EXISTS chat_mensajes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sala_id INT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED NOT NULL,
                mensaje VARCHAR(1000) NOT NULL,
                sistema TINYINT NOT NULL DEFAULT 0,
                responde_a INT UNSIGNED NULL,
                editado TINYINT NOT NULL DEFAULT 0,
                eliminado TINYINT NOT NULL DEFAULT 0,
                archivo VARCHAR(80) NULL,
                archivo_nombre VARCHAR(160) NULL,
                archivo_tipo VARCHAR(100) NULL,
                archivo_size INT UNSIGNED NULL,
                menciones VARCHAR(255) NULL,
                act INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_chat_msg_sala (sala_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $faltaSu = false;
        try { Database::run('SELECT sala_id FROM chat_sala_usuarios LIMIT 0'); } catch (Throwable $e) { $faltaSu = true; }
        if ($faltaSu) {
            Database::run("CREATE TABLE IF NOT EXISTS chat_sala_usuarios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sala_id INT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED NOT NULL,
                rol VARCHAR(10) NOT NULL DEFAULT 'miembro',
                ultimo_leido_id INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_chat_sala_usuario (sala_id, usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $faltaReu = false;
        try { Database::run('SELECT id FROM chat_reuniones LIMIT 0'); } catch (Throwable $e) { $faltaReu = true; }
        if ($faltaReu) {
            Database::run("CREATE TABLE IF NOT EXISTS chat_reuniones (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sala_id INT UNSIGNED NOT NULL,
                titulo VARCHAR(120) NOT NULL,
                descripcion VARCHAR(255) NULL,
                inicio DATETIME NOT NULL,
                duracion_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
                enlace VARCHAR(250) NOT NULL,
                organizador_id INT UNSIGNED NOT NULL,
                estado VARCHAR(12) NOT NULL DEFAULT 'programada',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_chat_reu_inicio (inicio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // Si el chat ya existia de una version anterior, completar lo que falte
        if (!$faltaSalas) {
            Esquema::columna('chat_salas', 'descripcion', 'VARCHAR(255) NULL');
            Esquema::columna('chat_salas', 'usuario1_id', 'INT UNSIGNED NULL');
            Esquema::columna('chat_salas', 'usuario2_id', 'INT UNSIGNED NULL');
            Esquema::columna('chat_salas', 'area1_id', 'SMALLINT UNSIGNED NULL');
            Esquema::columna('chat_salas', 'area2_id', 'SMALLINT UNSIGNED NULL');
            Esquema::columna('chat_salas', 'creada_por', 'INT UNSIGNED NULL');
            Esquema::columna('chat_salas', 'activa', 'TINYINT NOT NULL DEFAULT 1');
            self::tipoLibre();
        }
        foreach ([
            'sistema' => 'TINYINT NOT NULL DEFAULT 0', 'responde_a' => 'INT UNSIGNED NULL', 'editado' => 'TINYINT NOT NULL DEFAULT 0',
            'eliminado' => 'TINYINT NOT NULL DEFAULT 0', 'archivo' => 'VARCHAR(80) NULL', 'archivo_nombre' => 'VARCHAR(160) NULL',
            'archivo_tipo' => 'VARCHAR(100) NULL', 'archivo_size' => 'INT UNSIGNED NULL', 'menciones' => 'VARCHAR(255) NULL',
            'act' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        ] as $col => $def) Esquema::columna('chat_mensajes', $col, $def);

        $faltaRea = false;
        try { Database::run('SELECT id FROM chat_reacciones LIMIT 0'); } catch (Throwable $e) { $faltaRea = true; }
        if ($faltaRea) {
            Database::run("CREATE TABLE IF NOT EXISTS chat_reacciones (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                mensaje_id INT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED NOT NULL,
                emoji VARCHAR(16) NOT NULL,
                UNIQUE KEY uq_chat_reaccion (mensaje_id, usuario_id, emoji)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        self::utf8mb4();
        if (!$faltaSu) {
            Esquema::columna('chat_sala_usuarios', 'rol', "VARCHAR(10) NOT NULL DEFAULT 'miembro'");
            Esquema::columna('chat_sala_usuarios', 'ultimo_leido_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        }

        // Sala general (id 1)
        if (!Database::getValue('SELECT id FROM chat_salas WHERE id = 1')) {
            try {
                Database::run("INSERT INTO chat_salas (id, nombre, descripcion, tipo, activa) VALUES (1, 'General', 'Todo el personal', 'general', 1)");
            } catch (Throwable $e) { /* otro proceso la creo a la vez */ }
        }
        // La sala 1 de versiones anteriores era un "grupo": ahora es la sala general
        try { Database::run("UPDATE chat_salas SET tipo = 'general' WHERE id = 1 AND tipo <> 'general'"); } catch (Throwable $e) { /* sin permiso: se ignora */ }
        self::$ok = true;
        if (isset($_SESSION)) $_SESSION['chat_esq'] = self::ESQ;
    }

    /** Los emojis ocupan 4 bytes: las tablas del chat deben ser utf8mb4 (instalaciones antiguas pueden ser utf8). */
    private static function utf8mb4(): void
    {
        foreach (['chat_mensajes' => 'mensaje', 'chat_salas' => 'nombre', 'chat_reacciones' => 'emoji'] as $t => $col) {
            try {
                $c = Database::getOne("SHOW FULL COLUMNS FROM $t LIKE '$col'");
                if ($c && !empty($c['Collation']) && stripos((string)$c['Collation'], 'utf8mb4') !== 0) {
                    Database::run("ALTER TABLE $t CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
            } catch (Throwable $e) { /* SQLite u otro motor: no aplica */ }
        }
    }

    /** Version anterior: tipo era ENUM('grupo','personal','soporte','directo'). Lo volvemos libre. */
    private static function tipoLibre(): void
    {
        try {
            $c = Database::getOne("SHOW COLUMNS FROM chat_salas LIKE 'tipo'");
            $t = strtolower((string)($c['Type'] ?? ''));
            if (strpos($t, 'enum') === 0) {
                Database::run("ALTER TABLE chat_salas MODIFY tipo VARCHAR(15) NOT NULL DEFAULT 'grupo'");
            }
        } catch (Throwable $e) { /* SQLite u otro: nada que hacer */ }
    }

    // ---------------------------------------------------------
    // Personas y areas
    // ---------------------------------------------------------

    /** Area (id) a la que pertenece el usuario segun su ficha de personal; null si no tiene. */
    public static function areaDe(int $usuarioId): ?int
    {
        try {
            $a = Database::getValue(
                'SELECT p.area_id FROM usuarios u INNER JOIN personal p ON p.id = u.personal_id WHERE u.id = ?',
                [$usuarioId]);
            return $a ? (int)$a : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Ids de usuarios activos de un area. */
    public static function usuariosDeArea(int $areaId): array
    {
        try {
            $f = Database::get(
                "SELECT u.id FROM usuarios u INNER JOIN personal p ON p.id = u.personal_id
                 WHERE p.area_id = ? AND u.estado = 'activo'", [$areaId]);
            return array_map(fn($r) => (int)$r['id'], $f);
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function esSistemas(): bool
    {
        return Auth::can('usuarios', 'gestionar') || Auth::rol() === 'super_admin';
    }

    // ---------------------------------------------------------
    // Membresia
    // ---------------------------------------------------------

    public static function unir(int $salaId, int $usuarioId, string $rol = 'miembro'): void
    {
        if (Database::getValue('SELECT 1 FROM chat_sala_usuarios WHERE sala_id = ? AND usuario_id = ?', [$salaId, $usuarioId])) return;
        // Lo ya existente en la sala no cuenta como "no leido" para quien recien entra
        $ult = (int)(Database::getValue('SELECT COALESCE(MAX(id),0) FROM chat_mensajes WHERE sala_id = ?', [$salaId]) ?? 0);
        Database::insert('chat_sala_usuarios', [
            'sala_id' => $salaId, 'usuario_id' => $usuarioId, 'rol' => $rol, 'ultimo_leido_id' => $ult,
        ]);
    }

    public static function esMiembro(int $salaId, int $usuarioId): bool
    {
        return (bool)Database::getValue('SELECT 1 FROM chat_sala_usuarios WHERE sala_id = ? AND usuario_id = ?', [$salaId, $usuarioId]);
    }

    public static function esAdmin(int $salaId, int $usuarioId): bool
    {
        return (bool)Database::getValue(
            "SELECT 1 FROM chat_sala_usuarios WHERE sala_id = ? AND usuario_id = ? AND rol = 'admin'", [$salaId, $usuarioId]);
    }

    public static function sala(int $id): ?array
    {
        return Database::getOne('SELECT * FROM chat_salas WHERE id = ?', [$id]);
    }

    /** Canal del area (lo crea si no existe). */
    public static function canalArea(int $areaId): int
    {
        $id = Database::getValue("SELECT id FROM chat_salas WHERE tipo = 'area' AND area1_id = ?", [$areaId]);
        if ($id) return (int)$id;
        $n = Database::getValue('SELECT nombre FROM areas WHERE id = ?', [$areaId]) ?: ('Área ' . $areaId);
        return Database::insert('chat_salas', [
            'nombre' => (string)$n, 'descripcion' => 'Canal del área', 'tipo' => 'area', 'area1_id' => $areaId, 'activa' => 1,
        ]);
    }

    /** Canal entre dos areas (lo crea si no existe). El par se guarda ordenado (menor, mayor). */
    public static function canalEntreAreas(int $a, int $b, ?int $creador = null): int
    {
        $x = min($a, $b);
        $y = max($a, $b);
        $id = Database::getValue("SELECT id FROM chat_salas WHERE tipo = 'entre_areas' AND area1_id = ? AND area2_id = ?", [$x, $y]);
        if ($id) return (int)$id;
        $nx = Database::getValue('SELECT nombre FROM areas WHERE id = ?', [$x]) ?: $x;
        $ny = Database::getValue('SELECT nombre FROM areas WHERE id = ?', [$y]) ?: $y;
        return Database::insert('chat_salas', [
            'nombre' => $nx . ' ↔ ' . $ny, 'descripcion' => 'Comunicación entre áreas', 'tipo' => 'entre_areas',
            'area1_id' => $x, 'area2_id' => $y, 'creada_por' => $creador, 'activa' => 1,
        ]);
    }

    /**
     * Deja al usuario en las salas que le corresponden: General, el canal de su area y
     * los canales entre areas que incluyen su area; lo saca de los de un area que ya no es suya.
     * Se ejecuta como mucho cada 10 minutos por sesion (o siempre con $forzar).
     */
    public static function sincronizar(int $usuarioId, bool $forzar = false): void
    {
        self::asegurar();
        if (!$forzar && isset($_SESSION['chat_sync']) && (time() - (int)$_SESSION['chat_sync']) < 600) return;

        self::unir(1, $usuarioId);
        $area = self::areaDe($usuarioId);

        $debe = [];
        if ($area) {
            $debe[] = self::canalArea($area);
            $f = Database::get(
                "SELECT id FROM chat_salas WHERE tipo = 'entre_areas' AND activa = 1 AND (area1_id = ? OR area2_id = ?)",
                [$area, $area]);
            foreach ($f as $r) $debe[] = (int)$r['id'];
        }
        foreach ($debe as $sid) self::unir($sid, $usuarioId);

        // chats directos creados por la version anterior (no guardaban a sus participantes)
        $directos = Database::get(
            "SELECT id FROM chat_salas WHERE tipo = 'directo' AND activa = 1 AND (usuario1_id = ? OR usuario2_id = ?)",
            [$usuarioId, $usuarioId]);
        foreach ($directos as $d) self::unir((int)$d['id'], $usuarioId);

        // sacarlo de canales de area que ya no le tocan (cambio de area), sin tocar los que se unio Sistemas a proposito
        $mias = Database::get(
            "SELECT s.id, s.tipo FROM chat_sala_usuarios csu INNER JOIN chat_salas s ON s.id = csu.sala_id
             WHERE csu.usuario_id = ? AND s.tipo IN ('area','entre_areas')", [$usuarioId]);
        $esSis = false;
        foreach ($mias as $m) {
            if (in_array((int)$m['id'], $debe, true)) continue;
            if ($esSis === false) $esSis = self::esSistemasDe($usuarioId) ? 1 : 0;
            if ($esSis === 1 && $m['tipo'] === 'area') continue;   // Sistemas puede estar en canales de otras areas
            Database::delete('chat_sala_usuarios', 'sala_id = ? AND usuario_id = ?', [(int)$m['id'], $usuarioId]);
        }
        $_SESSION['chat_sync'] = time();
    }

    private static function esSistemasDe(int $usuarioId): bool
    {
        return $usuarioId === (int)Auth::userId() && self::esSistemas();
    }

    // ---------------------------------------------------------
    // Acceso
    // ---------------------------------------------------------

    /** 'escribir' | 'leer' (solo supervision de Sistemas) | null (sin acceso) */
    public static function acceso(array $sala, int $usuarioId, bool $sistemas): ?string
    {
        if ((int)$sala['activa'] !== 1) return null;
        if ((int)$sala['id'] === 1) return 'escribir';
        if (self::esMiembro((int)$sala['id'], $usuarioId)) return 'escribir';
        if ($sala['tipo'] === 'directo' && $sistemas) return 'leer';
        return null;
    }

    public static function mensaje(int $salaId, int $usuarioId, string $texto, bool $sistema = false): int
    {
        return Database::insert('chat_mensajes', [
            'sala_id' => $salaId, 'usuario_id' => $usuarioId, 'mensaje' => $texto, 'sistema' => $sistema ? 1 : 0,
        ]);
    }

    public static function marcarLeido(int $salaId, int $usuarioId): void
    {
        $max = (int)(Database::getValue('SELECT COALESCE(MAX(id),0) FROM chat_mensajes WHERE sala_id = ?', [$salaId]) ?? 0);
        Database::run('UPDATE chat_sala_usuarios SET ultimo_leido_id = ? WHERE sala_id = ? AND usuario_id = ? AND ultimo_leido_id < ?',
            [$max, $salaId, $usuarioId, $max]);
    }

    public static function noLeidos(int $usuarioId): int
    {
        return (int)(Database::getValue(
            "SELECT COALESCE(SUM(t.c),0) FROM (
                SELECT (SELECT COUNT(*) FROM chat_mensajes m
                        WHERE m.sala_id = csu.sala_id AND m.id > csu.ultimo_leido_id AND m.usuario_id <> ?) AS c
                FROM chat_sala_usuarios csu INNER JOIN chat_salas s ON s.id = csu.sala_id
                WHERE csu.usuario_id = ? AND s.activa = 1) t", [$usuarioId, $usuarioId]) ?? 0);
    }

    // ---------------------------------------------------------
    // Mensajes: cambios, archivos
    // ---------------------------------------------------------

    /** Marca el mensaje como modificado (editado, borrado o con reaccion) para que los demas lo vean al refrescar. */
    public static function marcarCambio(int $salaId, int $mensajeId): void
    {
        $n = (int)(Database::getValue('SELECT COALESCE(MAX(act),0) FROM chat_mensajes WHERE sala_id = ?', [$salaId]) ?? 0) + 1;
        Database::run('UPDATE chat_mensajes SET act = ? WHERE id = ?', [$n, $mensajeId]);
    }

    public static function rutaArchivo(string $nombre): string
    {
        return STORAGE_PATH . '/chat/' . basename($nombre);
    }

    /**
     * Guarda un adjunto del chat. Solo extensiones permitidas, contenido que coincida con el tipo,
     * nombre aleatorio en storage/chat (no accesible por URL: se descarga por api/chat/archivo.php).
     * @return array{archivo:string,nombre:string,tipo:string,size:int}
     */
    public static function guardarArchivo(array $f): array
    {
        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('El archivo es demasiado grande.');
        if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el archivo (código ' . $err . ').');

        $max = 10;
        try { $max = (int)(Database::getValue("SELECT valor FROM configuracion WHERE clave = 'max_upload_mb'") ?: 10); } catch (Throwable $e) { /* sin tabla: 10 MB */ }
        if ((int)$f['size'] > $max * 1024 * 1024) throw new RuntimeException("El archivo supera el máximo de {$max} MB.");
        if ((int)$f['size'] <= 0) throw new RuntimeException('El archivo está vacío.');

        $orig = trim(basename(str_replace('\\', '/', (string)$f['name'])));
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset(self::EXT_ARCHIVO[$ext])) {
            throw new RuntimeException('Tipo de archivo no permitido. Use: ' . implode(', ', array_keys(self::EXT_ARCHIVO)) . '.');
        }
        $cab = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
        $valido = true;
        if (in_array($ext, ['jpg', 'jpeg'], true)) $valido = substr($cab, 0, 3) === "\xFF\xD8\xFF";
        elseif ($ext === 'png') $valido = substr($cab, 0, 4) === "\x89PNG";
        elseif ($ext === 'gif') $valido = substr($cab, 0, 3) === 'GIF';
        elseif ($ext === 'webp') $valido = substr($cab, 0, 4) === 'RIFF';
        elseif ($ext === 'pdf') $valido = substr($cab, 0, 4) === '%PDF';
        elseif (in_array($ext, ['docx', 'xlsx', 'pptx', 'zip'], true)) $valido = substr($cab, 0, 2) === 'PK';
        elseif (in_array($ext, ['doc', 'xls', 'ppt'], true)) $valido = substr($cab, 0, 4) === "\xD0\xCF\x11\xE0";
        if (!$valido) throw new RuntimeException('El contenido del archivo no corresponde a su tipo (.' . $ext . ').');

        $dir = STORAGE_PATH . '/chat';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new RuntimeException('No se pudo preparar la carpeta de archivos del chat.');
        $nombre = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $mover = is_uploaded_file($f['tmp_name']) ? 'move_uploaded_file' : 'rename';
        if (!@$mover($f['tmp_name'], $dir . '/' . $nombre)) throw new RuntimeException('No se pudo guardar el archivo.');

        return ['archivo' => $nombre, 'nombre' => mb_substr($orig, 0, 160), 'tipo' => self::EXT_ARCHIVO[$ext], 'size' => (int)$f['size']];
    }

    // ---------------------------------------------------------
    // Reuniones
    // ---------------------------------------------------------

    /** programada | en_curso | finalizada | cancelada */
    public static function estadoReunion(array $r): string
    {
        if ($r['estado'] === 'cancelada') return 'cancelada';
        $ini = strtotime((string)$r['inicio']);
        $fin = $ini + ((int)$r['duracion_min']) * 60;
        $ahora = time();
        if ($ahora < $ini) return 'programada';
        if ($ahora <= $fin) return 'en_curso';
        return 'finalizada';
    }

    /** Enlace de videollamada propio de la reunion (Jitsi Meet publico, sin cuentas). */
    public static function enlaceNuevo(): string
    {
        return 'https://meet.jit.si/SIGTI-' . bin2hex(random_bytes(6));
    }
}
