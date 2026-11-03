<?php
declare(strict_types=1);

/** Escape text for HTML output (XSS protection). Use on every dynamic value. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function asset(string $path): string
{
    return 'assets/' . ltrim($path, '/');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message !== '' ? $message : 'Request could not be completed.';
    exit;
}

function uuid_v4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function is_uuid(string $v): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $v);
}

/** UTC timestamp from the database -> "2026-10-30 11:46:13" in Manila time. */
function format_manila(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    try {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return '';
    }
}
