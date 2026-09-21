<?php
// ============================================================
// SIGTI - Conexion PDO (singleton) + helpers de CRUD
// ============================================================

class Database
{
    private static ?PDO $conn = null;

    public static function conn(): PDO
    {
        if (self::$conn === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            self::$conn = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$conn;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function get(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function getOne(string $sql, array $params = []): ?array
    {
        $fila = self::run($sql, $params)->fetch();
        return $fila === false ? null : $fila;
    }

    public static function getValue(string $sql, array $params = [])
    {
        $valor = self::run($sql, $params)->fetchColumn();
        return $valor === false ? null : $valor;
    }

    public static function insert(string $tabla, array $datos): int
    {
        $columnas = self::soloColumnasSeguras(array_keys($datos));
        $marcadores = implode(', ', array_map(fn($c) => ":$c", $columnas));
        $sql = "INSERT INTO $tabla (" . implode(', ', $columnas) . ") VALUES ($marcadores)";
        $params = [];
        foreach ($columnas as $c) $params[":$c"] = $datos[$c];
        self::run($sql, $params);
        return (int) self::conn()->lastInsertId();
    }

    public static function update(string $tabla, array $datos, string $where, array $whereParams = []): int
    {
        $columnas = self::soloColumnasSeguras(array_keys($datos));
        $set = implode(', ', array_map(fn($c) => "$c = :set_$c", $columnas));
        $sql = "UPDATE $tabla SET $set WHERE $where";
        $params = [];
        foreach ($columnas as $c) $params[":set_$c"] = $datos[$c];
        foreach ($whereParams as $i => $valor) {
            $sql = preg_replace('/\?/', ":w$i", $sql, 1);
            $params[":w$i"] = $valor;
        }
        return self::run($sql, $params)->rowCount();
    }

    public static function delete(string $tabla, string $where, array $params = []): int
    {
        return self::run("DELETE FROM $tabla WHERE $where", $params)->rowCount();
    }

    public static function begin(): void  { self::conn()->beginTransaction(); }
    public static function commit(): void { self::conn()->commit(); }
    public static function rollback(): void {
        if (self::conn()->inTransaction()) self::conn()->rollBack();
    }

    private static function soloColumnasSeguras(array $columnas): array
    {
        $limpias = array_values(array_filter(
            $columnas,
            fn($c) => is_string($c) && preg_match('/^[a-zA-Z0-9_]+$/', $c)
        ));
        if (empty($limpias)) {
            throw new InvalidArgumentException('Insert/Update sin columnas validas.');
        }
        return $limpias;
    }
}