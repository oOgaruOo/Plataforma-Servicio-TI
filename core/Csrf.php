<?php
// ============================================================
// SIGTI - Token anti-CSRF para peticiones AJAX
// ============================================================

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(): void
    {
        $recibido = $_SERVER['HTTP_X_CSRF_TOKEN']
                    ?? ($_POST['csrf_token'] ?? '');

        if (empty($_SESSION['csrf_token'])
            || !is_string($recibido)
            || !hash_equals($_SESSION['csrf_token'], $recibido)) {
            Response::errorCsrf();
        }
    }
}