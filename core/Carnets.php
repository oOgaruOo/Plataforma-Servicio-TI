<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Carnets del personal: tabla autocreada + reglas comunes
// Estados guardados: pendiente -> impreso -> entregado.
// "Vencido" no se guarda: se calcula cuando fecha_vigencia < hoy.
// ============================================================

class Carnets
{
    public const ACTIVOS = "'pendiente','impreso','entregado'";
    private static bool $ok = false;

    /** Crea la tabla si falta (sigti.sql antiguo no la traia) y las columnas nuevas. */
    public static function asegurar(): void
    {
        if (self::$ok) return;
        try {
            Database::run('SELECT id FROM carnets LIMIT 0');
        } catch (Throwable $e) {
            Database::run("CREATE TABLE IF NOT EXISTS carnets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                personal_id INT NOT NULL,
                codigo_carnet VARCHAR(30) NOT NULL,
                fecha_emision DATE NOT NULL,
                fecha_vigencia DATE NOT NULL,
                estado VARCHAR(15) NOT NULL DEFAULT 'pendiente',
                impreso_por INT NULL,
                fecha_impresion DATETIME NULL,
                entregado_por INT NULL,
                fecha_entrega DATETIME NULL,
                revierto_a VARCHAR(15) NULL,
                fecha_reversion DATETIME NULL,
                revertido_por INT NULL,
                datos_cambiaron TINYINT(1) NOT NULL DEFAULT 0,
                creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_car_personal (personal_id),
                KEY idx_car_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        Esquema::columna('carnets', 'datos_cambiaron', 'TINYINT(1) NOT NULL DEFAULT 0');
        Apellidos::asegurar();
        CodigoUsuario::asegurar();
        Esquema::columna('personal', 'foto', 'VARCHAR(80) NULL');
        self::$ok = true;
    }

    public static function vigenciaMeses(): int
    {
        $m = (int)(Database::getValue("SELECT valor FROM configuracion WHERE clave='carnet_vigencia_meses'") ?: 24);
        return ($m >= 1 && $m <= 120) ? $m : 24;
    }

    /** Carnet vigente de la persona (pendiente / impreso / entregado) o null. */
    public static function activo(int $personalId): ?array
    {
        return Database::getOne(
            'SELECT * FROM carnets WHERE personal_id = ? AND estado IN (' . self::ACTIVOS . ') ORDER BY id DESC LIMIT 1',
            [$personalId]) ?: null;
    }

    /** CR-AAAA-NNNNN; si el codigo ya existe (carnet anterior de la misma persona) agrega -2, -3... */
    public static function codigoNuevo(int $personalId): string
    {
        $base = 'CR-' . date('Y') . '-' . str_pad((string)$personalId, 5, '0', STR_PAD_LEFT);
        $cod = $base; $n = 1;
        while (Database::getValue('SELECT id FROM carnets WHERE codigo_carnet = ?', [$cod])) {
            $n++; $cod = $base . '-' . $n;
        }
        return $cod;
    }

    /** Crea el carnet pendiente de una persona. Devuelve [id|null, 'motivo si no se pudo']. */
    public static function generar(int $personalId): array
    {
        $p = Database::getOne('SELECT id, nombres, apellidos, estado FROM personal WHERE id = ?', [$personalId]);
        if (!$p) return [null, "La persona #$personalId no existe."];
        $nom = trim($p['nombres'] . ' ' . $p['apellidos']);
        if ($p['estado'] === 'cesado') return [null, "$nom esta cesado."];
        if (self::activo($personalId)) return [null, "$nom ya tiene carnet."];
        $id = Database::insert('carnets', [
            'personal_id'    => $personalId,
            'codigo_carnet'  => self::codigoNuevo($personalId),
            'fecha_emision'  => date('Y-m-d'),
            'fecha_vigencia' => date('Y-m-d', strtotime('+' . self::vigenciaMeses() . ' months')),
            'estado'         => 'pendiente',
        ]);
        return [(int)$id, null];
    }

    /**
     * Datos impresos en el carnet cambiaron (nombre, cargo, area, DNI, codigo): si ya se imprimio,
     * se marca para reimprimir. Devuelve true si habia un carnet impreso/entregado que quedo marcado.
     */
    public static function datosCambiaron(int $personalId, string $motivo = ''): bool
    {
        try {
            self::asegurar();
            $c = self::activo($personalId);
            if (!$c || $c['estado'] === 'pendiente') return false;   // pendiente: se imprime con los datos nuevos
            Database::update('carnets', ['datos_cambiaron' => 1], 'id = ?', [$c['id']]);
            Auditoria::registrar('actualizar', 'carnets', (int)$c['id'], null,
                ['datos_cambiaron' => 1, 'motivo' => $motivo]);
            return true;
        } catch (Throwable $e) {
            return false;   // un fallo aqui nunca debe impedir guardar a la persona
        }
    }
}
