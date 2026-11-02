<?php
declare(strict_types=1);

/** CSRF protection for every state-changing (POST) request. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token = null): bool
    {
        $token ??= $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return is_string($token) && $token !== '' && hash_equals(self::token(), $token);
    }

    /** Stop the request unless the token is valid. */
    public static function require(): void
    {
        if (!self::verify()) {
            abort(419, 'Your session expired. Reload the page and try again.');
        }
    }
}
