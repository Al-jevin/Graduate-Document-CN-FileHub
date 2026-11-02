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
