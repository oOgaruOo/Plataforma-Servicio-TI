<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Datos de la empresa (nombre, siglas, RUC) para actas, informes y carnets.
// Un solo lugar: asi el sistema sirve para cualquier empresa y no queda ningun nombre fijo en el codigo.
// ============================================================

final class Empresa
{
    private static function cfg(string $k): string
    {
        try {
            $v = Database::getValue('SELECT valor FROM configuracion WHERE clave = ?', [$k]);
            return $v === null || $v === false ? '' : trim((string)$v);
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function nombre(): string
    {
        $n = self::cfg('empresa_nombre');
        if ($n === '') {
            $n = self::cfg('nombre_empresa');
            if ($n === 'Mi Empresa S.A.C.') $n = '';
        }
        return $n !== '' ? $n : 'Su empresa';
    }

    public static function ruc(): string { return self::cfg('empresa_ruc'); }

    /** Siglas configuradas; si no hay, se arman con las iniciales del nombre. */
    public static function siglas(): string
    {
        $s = self::cfg('empresa_siglas');
        if ($s !== '') return strtoupper($s);
        $n = self::cfg('empresa_nombre');
        if ($n === '') return 'EMP';
        $ini = '';
        foreach (preg_split('/[\s\.,]+/u', $n) as $p) {
            if (mb_strlen($p) < 3 || in_array(mb_strtoupper($p), ['SAC', 'SRL', 'EIRL', 'SAA', 'LTDA', 'DEL', 'LAS', 'LOS', 'CON', 'POR'], true)) continue;
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $ini !== '' ? substr(preg_replace('/[^A-Z0-9]/', '', $ini), 0, 8) ?: 'EMP' : 'EMP';
    }

    /** Como se nombra a la empresa dentro de textos legales («propiedad exclusiva de ...»). */
    public static function corto(): string
    {
        $s = self::cfg('empresa_siglas');
        return $s !== '' ? strtoupper($s) : self::nombre();
    }

    /**
     * Al activar la licencia, los codigos de formato de las actas que sigan con su forma original
     * (TI-FOR-003 o <OTRAS SIGLAS>-TI-FOR-003) toman las siglas de la empresa. Los que la empresa personalizo no se tocan.
     */
    public static function ajustarFormatos(): void
    {
        $s = self::cfg('empresa_siglas');
        if ($s === '') return;
        $s = strtoupper($s);
        foreach (['acta_formato_entrega', 'acta_formato_entrega_movil', 'acta_formato_cambio', 'acta_formato_devolucion'] as $k) {
            $v = self::cfg($k);
            if ($v !== '' && preg_match('/^(?:[A-Z0-9]{2,8}-)?(TI-FOR-\d{3})$/', $v, $m)) {
                $nuevo = $s . '-' . $m[1];
                if ($nuevo !== $v) Database::update('configuracion', ['valor' => $nuevo], 'clave = ?', [$k]);
            }
        }
    }
}
