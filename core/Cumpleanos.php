<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Calculos de cumpleanos (sin SQL, funciona igual en cualquier BD)
// - Quien nacio un 29 de febrero celebra el 28 en los anios no bisiestos.
// ============================================================

class Cumpleanos
{
    /** Convierte 'YYYY-MM-DD' en fecha; null si esta vacia o es invalida (ej. 0000-00-00). */
    public static function fecha(?string $f): ?DateTimeImmutable
    {
        $f = trim((string)$f);
        if ($f === '') return null;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $f);
        if (!$d || $d->format('Y-m-d') !== $f || (int)$d->format('Y') < 1900) return null;
        return $d;
    }

    private static function bisiesto(int $anio): bool
    {
        return ($anio % 4 === 0 && $anio % 100 !== 0) || $anio % 400 === 0;
    }

    /** Dia en que se celebra en el anio indicado. */
    public static function enAnio(DateTimeImmutable $nac, int $anio): DateTimeImmutable
    {
        $m = (int)$nac->format('n');
        $d = (int)$nac->format('j');
        if ($m === 2 && $d === 29 && !self::bisiesto($anio)) $d = 28;
        return (new DateTimeImmutable('today'))->setDate($anio, $m, $d)->setTime(0, 0);
    }

    public static function esHoy(?string $fnac, ?DateTimeImmutable $hoy = null): bool
    {
        $nac = self::fecha($fnac);
        if (!$nac) return false;
        $hoy = $hoy ?? new DateTimeImmutable('today');
        return self::enAnio($nac, (int)$hoy->format('Y'))->format('Y-m-d') === $hoy->format('Y-m-d');
    }

    /** Proximo cumpleanos desde hoy (hoy mismo cuenta). */
    public static function proximo(string $fnac, ?DateTimeImmutable $hoy = null): ?DateTimeImmutable
    {
        $nac = self::fecha($fnac);
        if (!$nac) return null;
        $hoy = $hoy ?? new DateTimeImmutable('today');
        $y = (int)$hoy->format('Y');
        $c = self::enAnio($nac, $y);
        return $c < $hoy ? self::enAnio($nac, $y + 1) : $c;
    }

    public static function dias(string $fnac, ?DateTimeImmutable $hoy = null): ?int
    {
        $hoy = $hoy ?? new DateTimeImmutable('today');
        $p = self::proximo($fnac, $hoy);
        return $p ? (int)$hoy->diff($p)->days : null;
    }

    /** Edad que tiene (o tendra) en la fecha indicada. */
    public static function edadEn(string $fnac, DateTimeImmutable $fecha): ?int
    {
        $nac = self::fecha($fnac);
        return $nac ? (int)$nac->diff($fecha)->y : null;
    }

    public static function edad(string $fnac, ?DateTimeImmutable $hoy = null): ?int
    {
        return self::edadEn($fnac, $hoy ?? new DateTimeImmutable('today'));
    }
}
