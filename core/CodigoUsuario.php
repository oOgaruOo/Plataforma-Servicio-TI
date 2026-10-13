<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Codigo de usuario del personal, segun su area
//
//   Area "Sistemas" (prefijo SIS)  ->  SIS-0001, SIS-0002, ...
//   Area "Recursos Humanos" (RH)   ->  RH-0001, ...
//
//  * areas.codigo            prefijo del area (se genera solo desde el nombre; se puede editar)
//  * personal.codigo_usuario codigo de la persona (lo asigna el sistema al registrarla)
//  * personal.locker         locker asignado (opcional)
// El correlativo es por area y nunca se reutiliza dentro del mismo prefijo.
// ============================================================

class CodigoUsuario
{
    private static bool $listo = false;
    private const PALABRAS_VACIAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'EL', 'Y', 'E', 'EN', 'PARA', 'POR', 'A'];

    /** Prefijos de los codigos de Proyectos (PRY-001) y Obras (OBR-001): ningun area puede usarlos. */
    public const RESERVADOS = ['PRY', 'OBR'];

    public static function reservado(string $p): bool
    {
        return in_array(strtoupper($p), self::RESERVADOS, true);
    }

    /** Prefijo de area valido: 2 a 6 letras/numeros en mayusculas. */
    public static function prefijoValido(string $p): bool
    {
        return (bool)preg_match('/^[A-Z0-9]{2,6}$/', $p);
    }

    /** "Recursos Humanos" -> "RH"; "Sistemas" -> "SIS"; "Tecnología de la Información" -> "TI". */
    public static function prefijoDeNombre(string $nombre, array $usados = []): string
    {
        $usados = array_merge($usados, self::RESERVADOS);
        $t = strtr(mb_strtoupper($nombre),
            ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $palabras = array_values(array_filter(preg_split('/[^A-Z0-9]+/', $t),
            fn($w) => $w !== '' && !in_array($w, self::PALABRAS_VACIAS, true)));
        if (!$palabras) return 'GEN';

        if (count($palabras) >= 2) {
            $base = '';
            foreach (array_slice($palabras, 0, 4) as $w) $base .= $w[0];
            $candidatos = [$base];
        } else {
            $w = $palabras[0];
            $candidatos = [];
            if (strlen($w) <= 4) $candidatos[] = $w;                     // siglas: RRHH, TI, SIG
            foreach ([3, 4, 5, 6] as $n) if (strlen($w) >= $n) $candidatos[] = substr($w, 0, $n);
            if (!$candidatos) $candidatos[] = str_pad($w, 2, 'X');
        }
        foreach ($candidatos as $c) {
            if (strlen($c) < 2) $c = str_pad($c, 2, 'X');
            if (!in_array($c, $usados, true)) return $c;
        }
        $base = substr($candidatos[0], 0, 5);
        for ($i = 2; $i < 100; $i++) {
            if (!in_array($base . $i, $usados, true)) return $base . $i;
        }
        return $base . random_int(100, 999);
    }

    /** Crea columnas, da prefijo a las areas que no tienen y codigo a las personas que no tienen. */
    public static function asegurar(): void
    {
        if (self::$listo) return;
        self::asegurarAreas();
        Esquema::columna('personal', 'codigo_usuario', 'VARCHAR(20) NULL');
        Esquema::columna('personal', 'locker', 'VARCHAR(20) NULL');

        $sin = Database::get(
            "SELECT id, area_id FROM personal
             WHERE (codigo_usuario IS NULL OR codigo_usuario = '') ORDER BY id LIMIT 3000");
        if ($sin) {
            $cuenta = [];                                   // prefijo => ultimo correlativo usado
            foreach ($sin as $f) {
                $pre = self::prefijoArea($f['area_id'] ? (int)$f['area_id'] : 0);
                if (!isset($cuenta[$pre])) $cuenta[$pre] = self::maximo($pre);
                $cuenta[$pre]++;
                Database::update('personal',
                    ['codigo_usuario' => sprintf('%s-%04d', $pre, $cuenta[$pre])], 'id = ?', [(int)$f['id']]);
            }
        }
        self::$listo = true;
    }

    private static bool $areasListas = false;

    /** Solo la parte de areas (la usan los catalogos). */
    public static function asegurarAreas(): void
    {
        if (self::$areasListas) return;
        Esquema::columna('areas', 'codigo', 'VARCHAR(8) NULL');
        $sin = Database::get("SELECT id, nombre FROM areas WHERE codigo IS NULL OR codigo = '' ORDER BY id");
        if ($sin) {
            $usados = array_column(Database::get("SELECT codigo FROM areas WHERE codigo IS NOT NULL AND codigo <> ''"), 'codigo');
            foreach ($sin as $a) {
                $p = self::prefijoDeNombre($a['nombre'], $usados);
                $usados[] = $p;
                Database::update('areas', ['codigo' => $p], 'id = ?', [(int)$a['id']]);
            }
        }
        self::$areasListas = true;
    }

    /** Prefijo del area (o GEN si la persona no tiene area). */
    public static function prefijoArea(int $areaId): string
    {
        if ($areaId <= 0) return 'GEN';
        self::asegurarAreas();
        $c = Database::getValue('SELECT codigo FROM areas WHERE id = ?', [$areaId]);
        return ($c && self::prefijoValido((string)$c)) ? (string)$c : 'GEN';
    }

    /** Mayor correlativo ya usado con ese prefijo. */
    private static function maximo(string $pre): int
    {
        $max = 0;
        foreach (Database::get('SELECT codigo_usuario FROM personal WHERE codigo_usuario LIKE ?', [$pre . '-%']) as $r) {
            if (preg_match('/^' . preg_quote($pre, '/') . '-(\d+)$/', (string)$r['codigo_usuario'], $m)) $max = max($max, (int)$m[1]);
        }
        return $max;
    }

    /**
     * El area cambio (nombre y/o prefijo): el personal vigente toma el prefijo nuevo, conservando
     * su orden (OPE-0002 -> PRO-0001, OPE-0005 -> PRO-0002...). Los cesados conservan el codigo con el
     * que figuran en su historial. Si la persona ya tenia carnet impreso queda marcado para reimprimir.
     * Devuelve ['codigo_cambio','personas','carnets','roles'].
     */
    public static function sincronizarArea(int $areaId, array $antes, array $despues): array
    {
        $res = ['codigo_cambio' => false, 'personas' => 0, 'carnets' => 0, 'roles' => 0];
        $preViejo = (string)($antes['codigo'] ?? '');
        $preNuevo = (string)($despues['codigo'] ?? '');

        if ($preNuevo !== '' && $preNuevo !== $preViejo) {
            self::asegurar();
            $res['codigo_cambio'] = true;
            $gente = Database::get(
                "SELECT id, codigo_usuario FROM personal WHERE area_id = ? AND estado <> 'cesado' ORDER BY id", [$areaId]);
            $num = function ($c) { return preg_match('/-(\d+)$/', (string)$c, $m) ? (int)$m[1] : PHP_INT_MAX; };
            usort($gente, function ($a, $b) use ($num) {
                return [$num($a['codigo_usuario']), (int)$a['id']] <=> [$num($b['codigo_usuario']), (int)$b['id']];
            });
            $n = self::maximo($preNuevo);
            foreach ($gente as $p) {
                if (strpos((string)$p['codigo_usuario'], $preNuevo . '-') === 0) continue;
                do {
                    $n++;
                    $cod = sprintf('%s-%04d', $preNuevo, $n);
                } while (Database::getValue('SELECT id FROM personal WHERE codigo_usuario = ?', [$cod]));
                Database::update('personal', ['codigo_usuario' => $cod], 'id = ?', [(int)$p['id']]);
                $res['personas']++;
                if (class_exists('Carnets') && Carnets::datosCambiaron((int)$p['id'], 'codigo_usuario')) $res['carnets']++;
            }
        }
        require_once __DIR__ . '/crearRolesArea.php';
        if (($antes['nombre'] ?? '') !== ($despues['nombre'] ?? '')) {
            $res['roles'] = renombrarRolesArea($areaId, (string)$antes['nombre'], (string)$despues['nombre']);
        }
        return $res;
    }

    public static function siguiente(int $areaId): string
    {
        $pre = self::prefijoArea($areaId);
        $n = self::maximo($pre);
        do {
            $n++;
            $cod = sprintf('%s-%04d', $pre, $n);
        } while (Database::getValue('SELECT id FROM personal WHERE codigo_usuario = ?', [$cod]));
        return $cod;
    }

    /** Asigna a la persona el siguiente codigo de su area y lo devuelve. */
    public static function asignar(int $personalId, int $areaId): string
    {
        self::asegurar();
        $cod = self::siguiente($areaId);
        Database::update('personal', ['codigo_usuario' => $cod], 'id = ?', [$personalId]);
        return $cod;
    }
}
