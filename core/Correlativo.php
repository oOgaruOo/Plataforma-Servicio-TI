<?php
// ============================================================
// SIGTI - Generacion de codigos correlativos unicos
// TK-2025-00001, MT-..., SQ-..., AC-..., EQ-...
// ============================================================

class Correlativo
{
    private const PREFIJOS = [
        'ticket'       => 'TK',
        'mantenimiento'=> 'MT',
        'solicitud'    => 'SQ',
        'acta'         => 'AC',
        'equipo'       => 'EQ',
    ];

    public static function generar(string $tipo): string
    {
        if (!isset(self::PREFIJOS[$tipo])) {
            throw new InvalidArgumentException("Tipo de correlativo desconocido: $tipo");
        }

        $anio = (int) date('Y');
        $pdo  = Database::conn();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "SELECT ultimo_numero FROM correlativos
                 WHERE tipo_doc = ? AND anio = ? FOR UPDATE"
            );
            $stmt->execute([$tipo, $anio]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $nuevo = (int)$fila['ultimo_numero'] + 1;
                $pdo->prepare("UPDATE correlativos SET ultimo_numero = ? WHERE tipo_doc = ? AND anio = ?")
                    ->execute([$nuevo, $tipo, $anio]);
            } else {
                $nuevo = 1;
                $pdo->prepare("INSERT INTO correlativos (tipo_doc, anio, ultimo_numero) VALUES (?, ?, ?)")
                    ->execute([$tipo, $anio, $nuevo]);
            }

            $pdo->commit();
            return sprintf('%s-%d-%05d', self::PREFIJOS[$tipo], $anio, $nuevo);

        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }
}