<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Apellidos del personal: paterno y materno por separado
//
//  personal.apellido_paterno / personal.apellido_materno  -> datos reales
//  personal.apellidos                                     -> "paterno materno" (se mantiene
//        siempre armado por api/personal/guardar.php; asi actas, tickets, reportes y demas
//        modulos que muestran el nombre siguen funcionando sin cambios)
// ============================================================

class Apellidos
{
    private static bool $listo = false;

    /** Particulas que forman parte de un apellido compuesto ("De la Cruz", "Del Castillo"). */
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'san', 'santa', 'van', 'von', 'da', 'di'];

    /**
     * Separa "Perez De la Cruz" en [paterno, materno].
     * Una sola palabra = solo paterno. Con varias, el materno es la ultima palabra
     * junto con las particulas que la preceden.
     */
    public static function dividir(string $texto): array
    {
        $p = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY);
        if (!$p) return ['', ''];
        if (count($p) === 1) return [$p[0], ''];

        $i = count($p) - 1;                       // inicio del materno
        while ($i > 1 && in_array(mb_strtolower($p[$i - 1]), self::PARTICULAS, true)) $i--;
        if ($i < 1) $i = 1;
        return [implode(' ', array_slice($p, 0, $i)), implode(' ', array_slice($p, $i))];
    }

    public static function unir(?string $paterno, ?string $materno): string
    {
        return trim(preg_replace('/\s+/u', ' ', trim((string)$paterno) . ' ' . trim((string)$materno)));
    }

    /**
     * Crea las columnas si faltan y reparte los apellidos de quienes se registraron antes
     * (solo los que aun no tienen paterno). Es barato: una consulta cuando no hay nada que hacer.
     */
    public static function asegurar(): void
    {
        if (self::$listo) return;
        Esquema::columna('personal', 'apellido_paterno', 'VARCHAR(40) NULL');
        Esquema::columna('personal', 'apellido_materno', 'VARCHAR(40) NULL');

        $pend = Database::get(
            "SELECT id, apellidos FROM personal
             WHERE (apellido_paterno IS NULL OR apellido_paterno = '') AND apellidos IS NOT NULL AND apellidos <> ''
             LIMIT 2000");
        foreach ($pend as $f) {
            [$pat, $mat] = self::dividir($f['apellidos']);
            Database::update('personal',
                ['apellido_paterno' => mb_substr($pat, 0, 40), 'apellido_materno' => $mat === '' ? null : mb_substr($mat, 0, 40)],
                'id = ?', [(int)$f['id']]);
        }
        self::$listo = true;
    }
}
