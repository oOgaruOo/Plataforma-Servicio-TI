<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Response.php (JSON estandarizado, ESTABLE)
// ============================================================

class Response
{
    private static function json(array $arr, int $code = 200): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok($data = null, string $message = 'Operacion realizada'): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data, 'errors' => null]);
    }

    public static function error(string $message = 'Error', ?array $errors = null, int $code = 400): void
    {
        self::json(['success' => false, 'message' => $message, 'data' => null, 'errors' => $errors], $code);
    }

    public static function noAuth(string $message = 'Sesion no iniciada o expirada'): void
    {
        self::error($message, null, 401);
    }

    public static function forbidden(string $message = 'No tiene permisos'): void
    {
        self::error($message, null, 403);
    }

    public static function errorCsrf(): void
    {
        self::error('Token de seguridad invalido.', null, 419);
    }

    public static function validation(array $errors): void
    {
        self::error('Datos invalidos.', $errors, 422);
    }

    public static function datatable(int $draw, int $recordsTotal, int $recordsFiltered, array $rows): void
    {
        self::json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }
}