<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - RR.HH.: Requerimiento de personal
// Cualquier área pide personal a Sistemas / RR.HH. (residente, analista, practicante, etc.),
// indicando si necesita equipos de TI (laptop, módem…) y/o EPP, el salario referencial y el
// plazo para contratar y firmar los documentos. Una vez creado, el área YA NO puede editarlo
// ni cancelarlo: solo puede pedir un cambio por el chat interno o un ticket; quien gestiona
// (rrhh.gestionar) es quien lo tramita, corrige y cierra. Gestión interna, sin validez
// tributaria/legal.
//
// El puesto solicitado se apoya en un catálogo autónomo (rrhh_puesto_catalogo +
// rrhh_puesto_requisitos): cualquiera que puede solicitar propone puestos nuevos con solo
// el nombre (quedan "pendiente" hasta que Sistemas/RR.HH. los valide); al escribir un puesto
// ya activo en el catálogo, el formulario muestra sus requisitos (educación, formación,
// experiencia, etc.) y sugiere tipo de contrato y rango salarial. Los requisitos salen de
// una biblioteca reutilizable (rrhh_requisito_catalogo): se elige una frase ya usada en
// otro puesto o se escribe una nueva, que se suma sola a la biblioteca. Sistemas/RR.HH.
// agrega, edita, activa/desactiva y elimina puestos, requisitos y equipos libremente. Los
// equipos de TI también vienen de un catálogo configurable (rrhh_equipo_catalogo), igual
// que el de EPP. Mientras un requerimiento sigue en curso, Sistemas/RR.HH. también puede
// editar los equipos y EPP pedidos (agregar o quitar ítems), sin perder lo ya entregado.
//
//   Permisos:
//     rrhh.solicitar   pedir personal para su área, ver las propias solicitudes y proponer puestos nuevos
//     rrhh.ver_area     ver además los requerimientos de su área (jefaturas)
//     rrhh.ver          ver todos los requerimientos (comparte modulo con Contratos/Vacaciones/etc.)
//     rrhh.gestionar    tomar, editar (incluye equipos/EPP), marcar equipos/EPP entregados,
//                       cubrir, cancelar y administrar (crear/editar/validar/activar/desactivar)
//                       los catálogos de puestos, equipos de TI y biblioteca de requisitos
//     rrhh.eliminar     borrar de verdad (no solo desactivar) del catálogo de puestos,
//                       equipos de TI y biblioteca de requisitos; también borra un
//                       requerimiento ya CERRADO (cubierto o cancelado) — uno en curso no se
//                       puede borrar, solo cubrir o cancelar
//
// Las tablas se crean solas la primera vez que se abre el modulo.
// ============================================================

class Requerimientos
{
    /** estado => [etiqueta, explicacion, color] */
    public const ESTADOS = [
        'pendiente'  => ['Pendiente',   'Llegó a Sistemas / RR.HH. y espera ser atendido.',                    'azul'],
        'en_proceso' => ['En proceso',  'Sistemas / RR.HH. ya lo está gestionando.',                           'ambar'],
        'cubierto'   => ['Cubierto',    'La vacante se cubrió: el personal quedó contratado.',                 'verde'],
        'cancelado'  => ['Cancelado',   'Fue cancelado por Sistemas / RR.HH.',                                 'gris'],
    ];
    /** Estados en curso (todavia se espera algo). */
    public const EN_CURSO = ['pendiente', 'en_proceso'];

    /** Items de equipo "de fábrica" con los que se siembra el catálogo (editable luego). */
    public const ITEMS_EQUIPO = [
        'laptop'        => 'Laptop',
        'pc_escritorio' => 'Computadora de escritorio',
        'modem_router'  => 'Módem / Router',
        'celular'       => 'Celular',
        'impresora'     => 'Impresora',
        'otro'          => 'Otro (especificar)',
    ];

    /** estado del puesto en el catálogo => etiqueta */
    public const ESTADOS_PUESTO = [
        'pendiente' => 'Pendiente de validar',
        'activo'    => 'Activo',
        'inactivo'  => 'Inactivo',
    ];

    private static bool $ok = false;

    // ----------------------------------------------------------------------------------
    //  Esquema
    // ----------------------------------------------------------------------------------
    public static function asegurar(): void
    {
        if (self::$ok) return;

        $nuevo = false;
        try { Database::run('SELECT id FROM rrhh_requerimientos LIMIT 0'); } catch (Throwable $e) { $nuevo = true; }

        if ($nuevo) {
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_requerimientos (
                id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo                 VARCHAR(20) NOT NULL,
                solicitante_usuario_id INT UNSIGNED NOT NULL,
                area_id                INT UNSIGNED NULL,
                puesto                 VARCHAR(120) NOT NULL,
                puesto_id              INT UNSIGNED NULL,
                cantidad               SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                tipo_contrato          VARCHAR(20) NULL,
                salario_min            DECIMAL(10,2) NULL,
                salario_max            DECIMAL(10,2) NULL,
                justificacion          VARCHAR(500) NOT NULL,
                fecha_limite           DATE NULL,
                requiere_equipo        TINYINT(1) NOT NULL DEFAULT 0,
                requiere_epp           TINYINT(1) NOT NULL DEFAULT 0,
                observaciones          VARCHAR(500) NULL,
                estado                 VARCHAR(12) NOT NULL DEFAULT 'pendiente',
                motivo_cierre          VARCHAR(255) NULL,
                contrato_id            INT UNSIGNED NULL,
                created_at             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at             TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_req_codigo (codigo),
                KEY idx_req_estado (estado),
                KEY idx_req_area (area_id),
                KEY idx_req_solicitante (solicitante_usuario_id),
                KEY idx_req_puesto (puesto_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            Database::run("CREATE TABLE IF NOT EXISTS rrhh_requerimiento_equipos (
                id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                requerimiento_id  INT UNSIGNED NOT NULL,
                item              VARCHAR(20) NOT NULL,
                equipo_id         INT UNSIGNED NULL,
                nombre            VARCHAR(100) NULL,
                detalle           VARCHAR(200) NULL,
                entregado         TINYINT(1) NOT NULL DEFAULT 0,
                entregado_en      TIMESTAMP NULL,
                KEY idx_reqeq_req (requerimiento_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            Database::run("CREATE TABLE IF NOT EXISTS rrhh_requerimiento_epp (
                id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                requerimiento_id  INT UNSIGNED NOT NULL,
                epp_id            INT UNSIGNED NULL,
                nombre            VARCHAR(100) NOT NULL,
                cantidad          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                entregado         TINYINT(1) NOT NULL DEFAULT 0,
                entregado_en      TIMESTAMP NULL,
                KEY idx_reqepp_req (requerimiento_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            Database::run("CREATE TABLE IF NOT EXISTS rrhh_requerimiento_historial (
                id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                requerimiento_id  INT UNSIGNED NOT NULL,
                estado            VARCHAR(12) NOT NULL,
                usuario_id        INT UNSIGNED NULL,
                comentario        VARCHAR(400) NULL,
                created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_reqh_req (requerimiento_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            Database::run("CREATE TABLE IF NOT EXISTS rrhh_epp_catalogo (
                id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre  VARCHAR(100) NOT NULL,
                orden   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                activo  TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            foreach ([
                'Casco de seguridad', 'Guantes', 'Lentes de protección', 'Chaleco / casaca reflectiva',
                'Zapatos de seguridad', 'Protector auditivo', 'Mascarilla / respirador', 'Arnés de seguridad',
            ] as $i => $nombre) {
                Database::insert('rrhh_epp_catalogo', ['nombre' => $nombre, 'orden' => $i + 1, 'activo' => 1]);
            }
        }

        // Si ya existia de una version anterior (sin puesto_id/salario/equipo_id), completar lo que falte
        if (!$nuevo) {
            Esquema::columna('rrhh_requerimientos', 'puesto_id', 'INT UNSIGNED NULL');
            Esquema::columna('rrhh_requerimientos', 'salario_min', 'DECIMAL(10,2) NULL');
            Esquema::columna('rrhh_requerimientos', 'salario_max', 'DECIMAL(10,2) NULL');
            Esquema::columna('rrhh_requerimiento_equipos', 'equipo_id', 'INT UNSIGNED NULL');
            Esquema::columna('rrhh_requerimiento_equipos', 'nombre', 'VARCHAR(100) NULL');
        }

        // ---- catalogo de equipos de TI (nuevo: antes era una lista fija en el codigo) ----
        $nuevoEq = false;
        try { Database::run('SELECT id FROM rrhh_equipo_catalogo LIMIT 0'); } catch (Throwable $e) { $nuevoEq = true; }
        if ($nuevoEq) {
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_equipo_catalogo (
                id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre  VARCHAR(100) NOT NULL,
                orden   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                activo  TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $i = 0;
            foreach (self::ITEMS_EQUIPO as $nombre) {
                Database::insert('rrhh_equipo_catalogo', ['nombre' => $nombre, 'orden' => ++$i, 'activo' => 1]);
            }
        }

        // ---- catalogo de puestos y sus requisitos (nuevo) ----
        $nuevoPu = false;
        try { Database::run('SELECT id FROM rrhh_puesto_catalogo LIMIT 0'); } catch (Throwable $e) { $nuevoPu = true; }
        if ($nuevoPu) {
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_puesto_catalogo (
                id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre                 VARCHAR(120) NOT NULL,
                tipo_contrato_sugerido VARCHAR(20) NULL,
                salario_min            DECIMAL(10,2) NULL,
                salario_max            DECIMAL(10,2) NULL,
                estado                 VARCHAR(10) NOT NULL DEFAULT 'activo',
                creado_por             INT UNSIGNED NULL,
                created_at             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at             TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_puesto_nombre (nombre),
                KEY idx_puesto_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            Database::run("CREATE TABLE IF NOT EXISTS rrhh_puesto_requisitos (
                id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                puesto_id     INT UNSIGNED NOT NULL,
                requisito_id  INT UNSIGNED NULL,
                categoria     VARCHAR(60) NOT NULL,
                detalle       TEXT NOT NULL,
                orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_preq_puesto (puesto_id),
                KEY idx_preq_requisito (requisito_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            Esquema::columna('rrhh_puesto_requisitos', 'requisito_id', 'INT UNSIGNED NULL');
        }

        // ---- biblioteca reutilizable de requisitos (nuevo): frases de requisito compartidas
        //      entre varios puestos; se elige de aqui o se escribe una nueva (se suma sola) ----
        $nuevoReqCat = false;
        try { Database::run('SELECT id FROM rrhh_requisito_catalogo LIMIT 0'); } catch (Throwable $e) { $nuevoReqCat = true; }
        if ($nuevoReqCat) {
            Database::run("CREATE TABLE IF NOT EXISTS rrhh_requisito_catalogo (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                categoria  VARCHAR(60) NOT NULL,
                detalle    TEXT NOT NULL,
                orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                activo     TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_reqcat_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        self::$ok = true;
    }

    // ----------------------------------------------------------------------------------
    //  Alcance: que requerimientos puede ver esta persona
    // ----------------------------------------------------------------------------------
    /** @return array{modo:string,area_id:?int}|null  null = sin acceso */
    public static function alcance(): ?array
    {
        if (Auth::can('rrhh', 'ver') || Auth::can('rrhh', 'gestionar')) {
            return ['modo' => 'todos', 'area_id' => null];
        }
        if (Auth::can('rrhh', 'ver_area')) {
            return ['modo' => 'area', 'area_id' => self::areaDelUsuario(Auth::userId())];
        }
        if (Auth::can('rrhh', 'solicitar')) {
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

    /** Condicion SQL de visibilidad para el alias $r. Siempre ve las propias. @return array{0:string,1:array} */
    public static function condicion(array $alc, string $r = 'r'): array
    {
        if ($alc['modo'] === 'todos') return ['', []];
        if ($alc['modo'] === 'area' && $alc['area_id']) {
            return [" AND ($r.area_id = ? OR $r.solicitante_usuario_id = ?)", [$alc['area_id'], Auth::userId()]];
        }
        return [" AND $r.solicitante_usuario_id = ?", [Auth::userId()]];
    }

    /** ¿Puede esta persona ver este requerimiento (fila de rrhh_requerimientos)? */
    public static function puedeVer(array $fila): bool
    {
        $alc = self::alcance();
        if (!$alc) return false;
        if ((int)$fila['solicitante_usuario_id'] === Auth::userId()) return true;
        if ($alc['modo'] === 'todos') return true;
        return $alc['modo'] === 'area' && $alc['area_id'] && (int)$fila['area_id'] === (int)$alc['area_id'];
    }

    // ----------------------------------------------------------------------------------
    //  Catálogo de EPP (configurable, como los conceptos de Planillas)
    // ----------------------------------------------------------------------------------
    public static function catalogoEpp(bool $soloActivos = true): array
    {
        return Database::get('SELECT * FROM rrhh_epp_catalogo' . ($soloActivos ? ' WHERE activo = 1' : '') . ' ORDER BY orden, nombre');
    }

    public static function guardarEpp(array $d, int $id = 0): int
    {
        $datos = ['nombre' => trim((string)$d['nombre']), 'orden' => (int)($d['orden'] ?? 0)];
        if ($id) { Database::update('rrhh_epp_catalogo', $datos, 'id = ?', [$id]); return $id; }
        return Database::insert('rrhh_epp_catalogo', $datos + ['activo' => 1]);
    }

    public static function toggleEpp(int $id, bool $activo): void
    {
        Database::update('rrhh_epp_catalogo', ['activo' => $activo ? 1 : 0], 'id = ?', [$id]);
    }

    // ----------------------------------------------------------------------------------
    //  Catálogo de equipos de TI (configurable; reemplaza la lista fija anterior)
    // ----------------------------------------------------------------------------------
    public static function catalogoEquipos(bool $soloActivos = true): array
    {
        return Database::get('SELECT * FROM rrhh_equipo_catalogo' . ($soloActivos ? ' WHERE activo = 1' : '') . ' ORDER BY orden, nombre');
    }

    public static function guardarEquipo(array $d, int $id = 0): int
    {
        $datos = ['nombre' => trim(mb_substr((string)$d['nombre'], 0, 100)), 'orden' => (int)($d['orden'] ?? 0)];
        if ($id) { Database::update('rrhh_equipo_catalogo', $datos, 'id = ?', [$id]); return $id; }
        return Database::insert('rrhh_equipo_catalogo', $datos + ['activo' => 1]);
    }

    public static function toggleEquipo(int $id, bool $activo): void
    {
        Database::update('rrhh_equipo_catalogo', ['activo' => $activo ? 1 : 0], 'id = ?', [$id]);
    }

    /** ¿El nombre de este equipo corresponde a un "otro" de detalle libre? */
    public static function equipoEsOtro(string $nombre): bool
    {
        return mb_stripos(trim($nombre), 'otro') === 0;
    }

    /** Hard delete solo si ningun requerimiento ya lo pidio; si no, hay que desactivarlo. */
    public static function eliminarEquipo(int $id): bool
    {
        if (Database::getValue('SELECT id FROM rrhh_requerimiento_equipos WHERE equipo_id = ? LIMIT 1', [$id])) return false;
        Database::run('DELETE FROM rrhh_equipo_catalogo WHERE id = ?', [$id]);
        return true;
    }

    // ----------------------------------------------------------------------------------
    //  Biblioteca de requisitos (reutilizable entre puestos)
    //  - Se elige una frase existente o se escribe una nueva: la nueva se suma sola a la
    //    biblioteca (igual que un puesto nuevo se suma al catálogo de puestos).
    //  - rrhh.gestionar administra la biblioteca (editar/activar/desactivar); rrhh.eliminar
    //    la borra de verdad, solo si ningun puesto la tiene asignada.
    // ----------------------------------------------------------------------------------
    public static function catalogoRequisitos(bool $soloActivos = true): array
    {
        return Database::get('SELECT * FROM rrhh_requisito_catalogo' . ($soloActivos ? ' WHERE activo = 1' : '') . ' ORDER BY categoria, orden, detalle');
    }

    public static function guardarRequisitoCatalogo(array $d, int $id = 0): int
    {
        $datos = [
            'categoria' => trim(mb_substr((string)($d['categoria'] ?? ''), 0, 60)),
            'detalle'   => trim(mb_substr((string)($d['detalle'] ?? ''), 0, 2000)),
            'orden'     => (int)($d['orden'] ?? 0),
        ];
        if ($id) { Database::update('rrhh_requisito_catalogo', $datos, 'id = ?', [$id]); return $id; }
        return Database::insert('rrhh_requisito_catalogo', $datos + ['activo' => 1]);
    }

    public static function toggleRequisitoCatalogo(int $id, bool $activo): void
    {
        Database::update('rrhh_requisito_catalogo', ['activo' => $activo ? 1 : 0], 'id = ?', [$id]);
    }

    /** Hard delete solo si ningun puesto lo tiene asignado; si no, hay que desactivarlo. */
    public static function eliminarRequisitoCatalogo(int $id): bool
    {
        if (Database::getValue('SELECT id FROM rrhh_puesto_requisitos WHERE requisito_id = ? LIMIT 1', [$id])) return false;
        Database::run('DELETE FROM rrhh_requisito_catalogo WHERE id = ?', [$id]);
        return true;
    }

    /**
     * Encuentra una frase ya existente en la biblioteca (misma categoria + detalle, sin
     * distinguir mayusculas) o la crea; asi la biblioteca crece sola con el uso real.
     */
    public static function buscarOCrearRequisito(string $categoria, string $detalle): int
    {
        $categoria = trim(mb_substr($categoria, 0, 60));
        $detalle = trim(mb_substr($detalle, 0, 2000));
        if ($categoria === '' || $detalle === '') return 0;
        $id = (int) Database::getValue(
            'SELECT id FROM rrhh_requisito_catalogo WHERE LOWER(categoria) = LOWER(?) AND LOWER(detalle) = LOWER(?)',
            [$categoria, $detalle]
        );
        if ($id) return $id;
        try {
            return Database::insert('rrhh_requisito_catalogo', ['categoria' => $categoria, 'detalle' => $detalle, 'activo' => 1]);
        } catch (Throwable $e) {
            $id = (int) Database::getValue(
                'SELECT id FROM rrhh_requisito_catalogo WHERE LOWER(categoria) = LOWER(?) AND LOWER(detalle) = LOWER(?)',
                [$categoria, $detalle]
            );
            if ($id) return $id;
            throw $e;
        }
    }

    // ----------------------------------------------------------------------------------
    //  Catálogo de puestos y requisitos (autónomo)
    //  - Cualquiera con rrhh.solicitar propone un puesto nuevo (queda "pendiente").
    //  - rrhh.gestionar valida, edita (nombre, contrato sugerido, salario) y define los
    //    requisitos (categoría + detalle libres: Educación, Formación, Experiencia, Otros…
    //    o lo que haga falta según el puesto); también activa/inactiva/elimina.
    // ----------------------------------------------------------------------------------
    public static function catalogoPuestos(?string $estado = null): array
    {
        $sql = 'SELECT p.*, (SELECT COUNT(*) FROM rrhh_puesto_requisitos WHERE puesto_id = p.id) AS n_requisitos
                FROM rrhh_puesto_catalogo p';
        $par = [];
        if ($estado !== null && $estado !== '') { $sql .= ' WHERE p.estado = ?'; $par[] = $estado; }
        $sql .= ' ORDER BY p.nombre';
        return Database::get($sql, $par);
    }

    /** Puesto con su lista de requisitos, o null si no existe. */
    public static function puesto(int $id): ?array
    {
        $p = Database::getOne('SELECT * FROM rrhh_puesto_catalogo WHERE id = ?', [$id]);
        if (!$p) return null;
        $p['requisitos'] = Database::get('SELECT * FROM rrhh_puesto_requisitos WHERE puesto_id = ? ORDER BY orden, id', [$id]);
        return $p;
    }

    /** Busca un puesto activo por nombre (para la ficha de referencia al escribir). */
    public static function puestoPorNombre(string $nombre): ?array
    {
        $id = (int) Database::getValue("SELECT id FROM rrhh_puesto_catalogo WHERE LOWER(nombre) = LOWER(?) AND estado = 'activo'", [trim($nombre)]);
        return $id ? self::puesto($id) : null;
    }

    /**
     * Devuelve el id del puesto que coincide con $nombre (cualquier estado); si no existe
     * lo crea como "pendiente" a nombre de $uid (asi el catálogo crece solo con el uso real).
     */
    public static function buscarOProponerPuesto(string $nombre, int $uid): int
    {
        $nombre = trim(mb_substr($nombre, 0, 120));
        if ($nombre === '') return 0;
        $id = (int) Database::getValue('SELECT id FROM rrhh_puesto_catalogo WHERE LOWER(nombre) = LOWER(?)', [$nombre]);
        if ($id) return $id;
        try {
            return Database::insert('rrhh_puesto_catalogo', ['nombre' => $nombre, 'estado' => 'pendiente', 'creado_por' => $uid]);
        } catch (Throwable $e) {
            // dos areas proponiendo el mismo puesto a la vez -> la UNIQUE key choco, reusar el que gano
            $id = (int) Database::getValue('SELECT id FROM rrhh_puesto_catalogo WHERE LOWER(nombre) = LOWER(?)', [$nombre]);
            if ($id) return $id;
            throw $e;
        }
    }

    /** Alta directa (gestionar, propone sin requisitos) o edicion completa con requisitos. */
    public static function guardarPuesto(array $d, int $id = 0): int
    {
        $datos = [
            'nombre'                 => trim(mb_substr((string)($d['nombre'] ?? ''), 0, 120)),
            'tipo_contrato_sugerido' => trim((string)($d['tipo_contrato_sugerido'] ?? '')) ?: null,
            'salario_min'            => ($d['salario_min'] ?? '') !== '' ? (float)$d['salario_min'] : null,
            'salario_max'            => ($d['salario_max'] ?? '') !== '' ? (float)$d['salario_max'] : null,
        ];
        $estado = trim((string)($d['estado'] ?? ''));
        if ($estado !== '' && isset(self::ESTADOS_PUESTO[$estado])) $datos['estado'] = $estado;

        if ($id) {
            Database::update('rrhh_puesto_catalogo', $datos + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        } else {
            $id = Database::insert('rrhh_puesto_catalogo', $datos + ['estado' => $datos['estado'] ?? 'activo']);
        }

        // Los requisitos se reemplazan completos: agregar/editar/eliminar es, en los hechos,
        // volver a guardar la lista completa (asi queda todo autonomo en un solo paso). Cada
        // fila puede venir de la biblioteca (requisito_id) o ser texto nuevo, que se suma solo
        // a la biblioteca para poder reutilizarse en otros puestos.
        if (array_key_exists('requisitos', $d)) {
            Database::run('DELETE FROM rrhh_puesto_requisitos WHERE puesto_id = ?', [$id]);
            $orden = 0;
            foreach ((array)$d['requisitos'] as $r) {
                $cat = trim(mb_substr((string)($r['categoria'] ?? ''), 0, 60));
                $det = trim((string)($r['detalle'] ?? ''));
                if ($cat === '' || $det === '') continue;
                $ridCat = (int)($r['requisito_id'] ?? 0);
                if ($ridCat && !Database::getValue('SELECT id FROM rrhh_requisito_catalogo WHERE id = ?', [$ridCat])) $ridCat = 0;
                if (!$ridCat) $ridCat = self::buscarOCrearRequisito($cat, $det);
                Database::insert('rrhh_puesto_requisitos', [
                    'puesto_id' => $id, 'requisito_id' => $ridCat ?: null,
                    'categoria' => $cat, 'detalle' => mb_substr($det, 0, 2000), 'orden' => ++$orden,
                ]);
            }
        }
        return $id;
    }

    /**
     * Hard delete de un requerimiento ya CERRADO (cubierto o cancelado); uno en curso
     * (pendiente / en_proceso) no se puede borrar, solo cubrir o cancelar. Solo rrhh.eliminar
     * (en la practica, el area de Sistemas) tiene este boton.
     */
    public static function eliminar(int $id): bool
    {
        $f = Database::getOne('SELECT estado FROM rrhh_requerimientos WHERE id = ?', [$id]);
        if (!$f || !in_array($f['estado'], ['cubierto', 'cancelado'], true)) return false;
        Database::run('DELETE FROM rrhh_requerimiento_equipos WHERE requerimiento_id = ?', [$id]);
        Database::run('DELETE FROM rrhh_requerimiento_epp WHERE requerimiento_id = ?', [$id]);
        Database::run('DELETE FROM rrhh_requerimiento_historial WHERE requerimiento_id = ?', [$id]);
        Database::run('DELETE FROM rrhh_requerimientos WHERE id = ?', [$id]);
        return true;
    }

    /** Hard delete solo si ningun requerimiento ya lo uso; si no, hay que inactivarlo. */
    public static function eliminarPuesto(int $id): bool
    {
        if (Database::getValue('SELECT id FROM rrhh_requerimientos WHERE puesto_id = ? LIMIT 1', [$id])) return false;
        Database::run('DELETE FROM rrhh_puesto_requisitos WHERE puesto_id = ?', [$id]);
        Database::run('DELETE FROM rrhh_puesto_catalogo WHERE id = ?', [$id]);
        return true;
    }

    public static function presentarPuesto(array $p): array
    {
        $p['id'] = (int)$p['id'];
        $p['n_requisitos'] = (int)($p['n_requisitos'] ?? 0);
        $p['estado_texto'] = self::ESTADOS_PUESTO[$p['estado']] ?? $p['estado'];
        $p['tipo_contrato_texto'] = $p['tipo_contrato_sugerido'] ? (Contratos::TIPOS[$p['tipo_contrato_sugerido']] ?? $p['tipo_contrato_sugerido']) : null;
        return $p;
    }

    // ----------------------------------------------------------------------------------
    //  Utilidades
    // ----------------------------------------------------------------------------------
    public static function codigo(): string
    {
        $anio = date('Y');
        $n = (int) Database::getValue('SELECT COUNT(*) FROM rrhh_requerimientos WHERE codigo LIKE ?', ['REQ-' . $anio . '-%']) + 1;
        for ($i = 0; $i < 20; $i++, $n++) {
            $cod = sprintf('REQ-%s-%04d', $anio, $n);
            if (!Database::getValue('SELECT id FROM rrhh_requerimientos WHERE codigo = ?', [$cod])) return $cod;
        }
        return 'REQ-' . $anio . '-' . substr(md5(uniqid('', true)), 0, 6);
    }

    public static function historial(int $id, string $estado, ?string $comentario = null): void
    {
        Database::insert('rrhh_requerimiento_historial', [
            'requerimiento_id' => $id, 'estado' => $estado, 'usuario_id' => Auth::userId(),
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

    public static function equipoTexto(string $item): string
    {
        return self::ITEMS_EQUIPO[$item] ?? $item;
    }

    /** Fila lista para enviar al navegador (con etiqueta, color y si está atrasada). */
    public static function presentar(array $f): array
    {
        $e = self::ESTADOS[$f['estado']] ?? [$f['estado'], '', 'gris'];
        $f['estado_texto'] = $e[0];
        $f['estado_ayuda'] = $e[1];
        $f['estado_color'] = $e[2];
        $f['en_curso'] = in_array($f['estado'], self::EN_CURSO, true) ? 1 : 0;
        $f['id'] = (int)$f['id'];
        $f['cantidad'] = (int)$f['cantidad'];
        $f['requiere_equipo'] = (int)$f['requiere_equipo'];
        $f['requiere_epp'] = (int)$f['requiere_epp'];
        $f['tipo_contrato_texto'] = $f['tipo_contrato'] ? (Contratos::TIPOS[$f['tipo_contrato']] ?? $f['tipo_contrato']) : null;
        $hoy = date('Y-m-d');
        $f['atrasado'] = ($f['en_curso'] && !empty($f['fecha_limite']) && $f['fecha_limite'] < $hoy) ? 1 : 0;
        $f['puesto_id'] = isset($f['puesto_id']) && $f['puesto_id'] !== null ? (int)$f['puesto_id'] : null;
        $f['salario_min'] = isset($f['salario_min']) && $f['salario_min'] !== null ? (float)$f['salario_min'] : null;
        $f['salario_max'] = isset($f['salario_max']) && $f['salario_max'] !== null ? (float)$f['salario_max'] : null;
        return $f;
    }
}
