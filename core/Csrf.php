<?php

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        return !empty($_SESSION['csrf_token']) && !empty($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireValid(): void
    {
        // request_str() guards against an array-typed csrf_token field (e.g.
        // "csrf_token[]=x") reaching verify()'s `?string` parameter, which
        // would otherwise throw an uncaught TypeError -- reachable by anyone,
        // with no valid token needed, before any controller logic runs.
        $token = request_str($_POST['csrf_token'] ?? '') ?: null;

        if (!self::verify($token)) {
            http_response_code(419);
            require __DIR__ . '/../app/views/errors/500.php';
            exit;
        }
    }
}
