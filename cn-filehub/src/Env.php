<?php
declare(strict_types=1);

/** Minimal .env reader. Values never leave the server. */
final class Env
{
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            self::$vars[trim($key)] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $env = getenv($key);
        return $env !== false ? $env : $default;
    }

    public static function has(string $key): bool
    {
        $v = self::get($key);
        return $v !== null && $v !== '';
    }

    public static function int(string $key, int $default): int
    {
        return self::has($key) ? (int) self::get($key) : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return self::has($key)
            ? in_array(strtolower((string) self::get($key)), ['1', 'true', 'yes', 'on'], true)
            : $default;
    }
}
