<?php
declare(strict_types=1);

/** Session handling: hardened cookie, idle timeout, ID rotation on login. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('cnfilehub_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        $idle = Env::int('SESSION_LIFETIME_MINUTES', 60) * 60;
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $idle) {
            self::destroy();
            session_start();
        }
        $_SESSION['last_activity'] = time();
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    /** Send visitors who are not signed in back to the login page. */
    public static function requireLogin(): array
    {
        if (!self::check()) {
            redirect('login.php');
        }
        return $_SESSION['user'];
    }

    public static function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    public static function pullFlash(string $key): ?string
    {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }
}
