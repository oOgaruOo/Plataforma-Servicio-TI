<?php
// ============================================================
// SIGTI - Estandariza TODAS las respuestas JSON
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

    public static function ok($data = null, string $message = 'Operacion realizada correctamente'): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data, 'errors' => null]);
    }

    public static function error(string $message = 'Error en la operacion', ?array $errors = null, int $code = 400): void
    {
        self::json(['success' => false, 'message' => $message, 'data' => null, 'errors' => $errors], $code);
    }

    public static function noAuth(string $message = 'Sesion no iniciada o expirada'): void
    {
        self::error($message, null, 401);
    }

    public static function forbidden(string $message = 'No tiene permisos para esta accion'): void
    {
        self::error($message, null, 403);
    }

    public static function errorCsrf(): void
    {
        self::error('Token de seguridad invalido. Recargue la pagina e intente de nuevo.', null, 419);
    }

    public static function validation(array $errors): void
    {
        self::error('Datos invalidos. Revise el formulario.', $errors, 422);
    }

    public static function exception(string $mensajeTecnico = ''): void
    {
        error_log('[SIGTI] Excepcion: ' . $mensajeTecnico);
        self::error('Error interno del servidor. Contacte al administrador.', null, 500);
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