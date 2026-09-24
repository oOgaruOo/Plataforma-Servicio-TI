<?php
// ============================================================
// SIGTI - Generacion de codigos correlativos unicos
// TK-2025-00001, MT-..., SQ-..., AC-..., EQ-...
// VERSION 2: compatible con transacciones externas.
// Si el llamador (ej: guardar.php) ya abrio una transaccion,
// la REUTILIZA en lugar de abrir otra (PDO no anida).
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

        // ¿el llamador ya tiene una transaccion abierta?
        $propia = !$pdo->inTransaction();
        if ($propia) {
            $pdo->beginTransaction();
        }

        try {
            // Bloquea la fila del anio para otros procesos.
            // Con transaccion externa, el bloqueo se mantiene hasta que
            // ESA transaccion haga commit -> nunca genera duplicados.
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

            if ($propia) {
                $pdo->commit();
            }

            return sprintf('%s-%d-%05d', self::PREFIJOS[$tipo], $anio, $nuevo);

        } catch (Throwable $e) {
            // Solo deshacemos si la transaccion es NUESTRA;
            // si es del llamador, el la manejara en su propio catch.
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}