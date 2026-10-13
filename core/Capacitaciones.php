<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Capacitaciones: cualquier persona del sistema (de cualquier area) solicita una
// capacitacion a Sistemas y ve en que va su solicitud.
//
//   Estados (lo que ve quien solicita, en palabras claras):
//     solicitada   Enviada       llego a Sistemas
//     en_revision  En revision   Sistemas la esta evaluando
//     programada   Programada    ya tiene fecha, capacitador y lugar
//     realizada    Realizada     se dio la capacitacion (se puede calificar)
//     rechazada    Rechazada     con el motivo
//     cancelada    Cancelada     la cancelo quien la pidio o Sistemas
//
//   Permisos:
//     capacitaciones.solicitar  pedir una capacitacion y ver / cancelar / calificar las propias
//     capacitaciones.ver_area   ver ademas las solicitudes de su area (jefaturas)
//     capacitaciones.ver        ver todas las solicitudes y la agenda
//     capacitaciones.gestionar  revisar, programar, rechazar, cerrar y administrar los temas
//
// Las tablas se crean solas la primera vez (script opcional: db/migraciones/2026-10-12-capacitaciones.sql).
// ============================================================

class Capacitaciones
{
    /** estado => [etiqueta que ve la persona, explicacion, color] */
    public const ESTADOS = [
        'solicitada'  => ['Enviada',     'Su solicitud llegó a Sistemas y espera ser revisada.', 'azul'],
        'en_revision' => ['En revisión', 'Sistemas la está evaluando.',                          'ambar'],
        'programada'  => ['Programada',  'Ya tiene fecha, capacitador y lugar.',                 'verde'],
        'realizada'   => ['Realizada',   'La capacitación ya se dio.',                           'gris'],
        'rechazada'   => ['Rechazada',   'Sistemas no pudo atenderla (vea el motivo).',          'rojo'],
        'cancelada'   => ['Cancelada',   'Fue cancelada.',                                       'gris'],
    ];
    /** Estados en curso (todavia se espera algo). */
    public const EN_CURSO = ['solicitada', 'en_revision', 'programada'];
    public const MODALIDADES = ['indistinto' => 'Indistinto', 'presencial' => 'Presencial', 'virtual' => 'Virtual'];
    public const PRIORIDADES = ['baja' => 'Baja', 'normal' => 'Normal', 'alta' => 'Alta'];

    private static bool $ok = false;

    // ----------------------------------------------------------------------------------
    //  Esquema
    // ----------------------------------------------------------------------------------
    public static function asegurar(): void
    {
        if (self::$ok) return;

        $nuevo = false;
        try { Database::run('SELECT id FROM capacitaciones LIMIT 0'); } catch (Throwable $e) { $nuevo = true; }

        if ($nuevo) {
            Database::run("CREATE TABLE IF NOT EXISTS capacitacion_temas (
                id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre         VARCHAR(120) NOT NULL,
                descripcion    VARCHAR(255) NULL,
                duracion_horas DECIMAL(4,1) NULL,
                activo         TINYINT(1) NOT NULL DEFAULT 1,
                created_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            Database::run("CREATE TABLE IF NOT EXISTS capacitaciones (
                id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo                 VARCHAR(20) NOT NULL,
                solicitante_usuario_id INT UNSIGNED NOT NULL,
                area_id                INT UNSIGNED NULL,
                tema_id                INT UNSIGNED NULL,
                tema                   VARCHAR(150) NOT NULL,
                detalle                VARCHAR(500) NULL,
                participantes          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                modalidad              VARCHAR(12) NOT NULL DEFAULT 'indistinto',
                fecha_deseada          DATE NULL,
                prioridad              VARCHAR(8) NOT NULL DEFAULT 'normal',
                estado                 VARCHAR(12) NOT NULL DEFAULT 'solicitada',
                capacitador_usuario_id INT UNSIGNED NULL,
                fecha_programada       DATETIME NULL,
                duracion_min           SMALLINT UNSIGNED NULL,
                lugar                  VARCHAR(150) NULL,
                motivo                 VARCHAR(255) NULL,
                resultado              VARCHAR(500) NULL,
                asistentes             SMALLINT UNSIGNED NULL,
                fecha_realizada        DATETIME NULL,
                calificacion           TINYINT UNSIGNED NULL,
                comentario_eval        VARCHAR(300) NULL,
                created_at             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at             TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_cap_codigo (codigo),
                KEY idx_cap_estado (estado),
                KEY idx_cap_solicitante (solicitante_usuario_id),
                KEY idx_cap_area (area_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            Database::run("CREATE TABLE IF NOT EXISTS capacitacion_historial (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                capacitacion_id INT UNSIGNED NOT NULL,
                estado          VARCHAR(12) NOT NULL,
                usuario_id      INT UNSIGNED NULL,
                comentario      VARCHAR(400) NULL,
                created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_caph_cap (capacitacion_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        self::$ok = true;

        if ($nuevo) {
            try { self::semilla(); } catch (Throwable $e) { error_log('[SIGTI] Capacitaciones semilla: ' . $e->getMessage()); }
        }
    }

    /** Temas habituales; se pueden editar, desactivar o agregar mas. */
    private static function semilla(): void
    {
        $temas = [
            ['Uso del sistema SIGTI y mesa de ayuda', 'Cómo crear y seguir tickets, solicitar equipos y usar el chat', 1.0],
            ['Microsoft Excel', 'Fórmulas, tablas dinámicas y reportes', 3.0],
            ['Microsoft Word y PowerPoint', 'Documentos y presentaciones', 2.0],
            ['Correo, Teams y reuniones virtuales', 'Correo corporativo, calendario y videollamadas', 1.5],
            ['Seguridad informática', 'Contraseñas, correos engañosos (phishing) y buenas prácticas', 1.0],
            ['Uso de equipos, impresoras y escáner', 'Cuidado básico y solución de problemas comunes', 1.0],
        ];
        foreach ($temas as [$n, $d, $h]) {
            Database::insert('capacitacion_temas', ['nombre' => $n, 'descripcion' => $d, 'duracion_horas' => $h]);
        }
    }

    // ----------------------------------------------------------------------------------
    //  Alcance: que solicitudes puede ver esta persona
    // ----------------------------------------------------------------------------------
    /** @return array{modo:string,area_id:?int}|null  null = sin acceso al modulo */
    public static function alcance(): ?array
    {
        if (Auth::can('capacitaciones', 'ver') || Auth::can('capacitaciones', 'gestionar')) {
            return ['modo' => 'todos', 'area_id' => null];
        }
        if (Auth::can('capacitaciones', 'ver_area')) {
            return ['modo' => 'area', 'area_id' => self::areaDelUsuario(Auth::userId())];
        }
        if (Auth::can('capacitaciones', 'solicitar')) {
            return ['modo' => 'propias', 'area_id' => null];
        }
        return null;
    }

    public static function areaDelUsuario(int $uid): ?int
    {
        $pid = Database::getValue('SELECT personal_id FROM usuarios WHERE id = ?', [$uid]);
        if (!$pid) return null;
        $a = Database::getValue('SELECT area_id FROM personal WHERE id = ?', [(int)$pid]);
        return $a ? (int)$a : null;
    }

    /** Condicion SQL de visibilidad para el alias $c. Siempre ve las propias. @return array{0:string,1:array} */
    public static function condicion(array $alc, string $c = 'c'): array
    {
        if ($alc['modo'] === 'todos') return ['', []];
        if ($alc['modo'] === 'area' && $alc['area_id']) {
            return [" AND ($c.area_id = ? OR $c.solicitante_usuario_id = ?)", [$alc['area_id'], Auth::userId()]];
        }
        return [" AND $c.solicitante_usuario_id = ?", [Auth::userId()]];
    }

    /** ¿Puede esta persona ver esta solicitud (fila de capacitaciones)? */
    public static function puedeVer(array $fila): bool
    {
        $alc = self::alcance();
        if (!$alc) return false;
        if ((int)$fila['solicitante_usuario_id'] === Auth::userId()) return true;
        if ($alc['modo'] === 'todos') return true;
        return $alc['modo'] === 'area' && $alc['area_id'] && (int)$fila['area_id'] === (int)$alc['area_id'];
    }

    // ----------------------------------------------------------------------------------
    //  Utilidades
    // ----------------------------------------------------------------------------------
    public static function codigo(): string
    {
        $anio = date('Y');
        $n = (int) Database::getValue('SELECT COUNT(*) FROM capacitaciones WHERE codigo LIKE ?', ['CAP-' . $anio . '-%']) + 1;
        for ($i = 0; $i < 20; $i++, $n++) {
            $cod = sprintf('CAP-%s-%04d', $anio, $n);
            if (!Database::getValue('SELECT id FROM capacitaciones WHERE codigo = ?', [$cod])) return $cod;
        }
        return 'CAP-' . $anio . '-' . substr(md5(uniqid('', true)), 0, 6);
    }

    public static function historial(int $id, string $estado, ?string $comentario = null): void
    {
        Database::insert('capacitacion_historial', [
            'capacitacion_id' => $id, 'estado' => $estado, 'usuario_id' => Auth::userId(),
            'comentario' => $comentario !== null && $comentario !== '' ? mb_substr($comentario, 0, 400) : null,
        ]);
    }

    /** Respuesta correcta con un mensaje para mostrar (el navegador lee data.mensaje). */
    public static function ok($datos, string $mensaje): void
    {
        Response::ok(array_merge(is_array($datos) ? $datos : [], ['mensaje' => $mensaje]));
    }

    public static function etiqueta(string $estado): string
    {
        return self::ESTADOS[$estado][0] ?? $estado;
    }

    /** Fila lista para enviar al navegador (con etiqueta y explicacion del estado). */
    public static function presentar(array $f): array
    {
        $e = self::ESTADOS[$f['estado']] ?? [$f['estado'], '', 'gris'];
        $f['estado_texto'] = $e[0];
        $f['estado_ayuda'] = $e[1];
        $f['estado_color'] = $e[2];
        $f['en_curso'] = in_array($f['estado'], self::EN_CURSO, true) ? 1 : 0;
        $f['id'] = (int)$f['id'];
        $f['participantes'] = (int)$f['participantes'];
        $f['modalidad_texto'] = self::MODALIDADES[$f['modalidad']] ?? $f['modalidad'];
        $f['prioridad_texto'] = self::PRIORIDADES[$f['prioridad']] ?? $f['prioridad'];
        $f['calificacion'] = ($f['calificacion'] ?? null) !== null ? (int)$f['calificacion'] : null;
        $f['asistentes'] = ($f['asistentes'] ?? null) !== null ? (int)$f['asistentes'] : null;
        $f['duracion_min'] = ($f['duracion_min'] ?? null) !== null ? (int)$f['duracion_min'] : null;
        $hoy = date('Y-m-d H:i:s');
        // programada y la fecha ya paso: falta cerrarla
        $f['por_cerrar'] = ($f['estado'] === 'programada' && !empty($f['fecha_programada']) && $f['fecha_programada'] < $hoy) ? 1 : 0;
        return $f;
    }
}
