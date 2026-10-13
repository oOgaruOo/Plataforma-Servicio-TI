<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Datos del desarrollador del sistema (un solo lugar).
// Se muestran en el pie del ingreso, en «Acerca de SIGTI» (menú de la foto), en la consola del navegador
// y van firmados como comentario al inicio de cada archivo del código.
// Para cambiar el nombre o el código de autor basta editar las constantes de esta clase.
// ============================================================
final class Autor
{
    public const NOMBRE  = 'Ing. Alejandro Aldo Meneses Aliaga';
    public const CODIGO  = 'SIGTI-DEV-AMA-2026';
    public const SISTEMA = 'SIGTI — Plataforma de Servicio TI';
    public const ANIO    = '2026';
    public const ROL     = 'Desarrollador del sistema';
    public const ORCID   = '0000-0003-1788-1978';   // identificador ORCID del autor (https://orcid.org/0000-0003-1788-1978)

    /** Sello de autoría: huella corta que identifica nombre + código + sistema (sirve para comprobar que no se alteró). */
    public static function sello(): string
    {
        $h = strtoupper(substr(hash('sha256', self::NOMBRE . '|' . self::CODIGO . '|' . self::SISTEMA . '|' . self::ANIO . '|' . self::ORCID), 0, 12));
        return implode('-', str_split($h, 4));
    }

    public static function datos(): array
    {
        return [
            'nombre' => self::NOMBRE, 'codigo' => self::CODIGO, 'sistema' => self::SISTEMA,
            'anio' => self::ANIO, 'rol' => self::ROL, 'sello' => self::sello(),
            'orcid' => self::ORCID, 'orcid_url' => 'https://orcid.org/' . self::ORCID,
        ];
    }
}
