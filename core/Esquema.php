<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Esquema: asegura que exista una columna nueva.
// Si todavia no se ejecuto la migracion SQL, la crea una sola vez
// (evita que una pantalla completa falle por "Unknown column").
// ============================================================

class Esquema
{
    private static array $ok = [];

    /** $tabla, $col y $def son constantes del codigo (nunca datos del usuario). */
    public static function columna(string $tabla, string $col, string $def): void
    {
        $k = $tabla . '.' . $col;
        if (isset(self::$ok[$k])) return;
        try {
            Database::run("SELECT `$col` FROM `$tabla` LIMIT 0");
        } catch (Throwable $e) {
            try {
                Database::run("ALTER TABLE `$tabla` ADD COLUMN IF NOT EXISTS `$col` $def");
            } catch (Throwable $e2) {
                // MySQL sin soporte de IF NOT EXISTS
                Database::run("ALTER TABLE `$tabla` ADD COLUMN `$col` $def");
            }
        }
        self::$ok[$k] = true;
    }
}
