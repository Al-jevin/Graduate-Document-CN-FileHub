<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

/* ---------- config ---------- */
function env(string $key, ?string $default = null): ?string
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = ROOT . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) { continue; }
                [$k, $v] = explode('=', $line, 2);
                $v = preg_replace('/\s+#.*$/', '', trim($v));
                $vars[trim($k)] = trim($v, " \t\"'");
            }
        }
    }
    if (isset($vars[$key])) { return $vars[$key]; }
    $e = getenv($key);
    return $e !== false ? $e : $default;
}

$IS_PROD = env('APP_ENV', 'local') === 'production';
ini_set('display_errors', $IS_PROD ? '0' : '1');
ini_set('log_errors', '1');

/* ---------- security headers (no-store => Back button can't re-show protected pages after logout) ---------- */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
if ($IS_PROD) { header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); }

/* ---------- session ---------- */
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name($IS_PROD ? '__Host-cnfh' : 'cnfh');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $IS_PROD,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

/* ---------- helpers ---------- */
function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function redirect(string $to): never { header('Location: ' . $to, true, 303); exit; }

function client_ip(): string
{
    if (env('TRUST_PROXY') === 'true' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) { return $ip; }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page, and try again.');
    }
}

function flash(string $type, ?string $msg = null): ?string
{
    if ($msg !== null) { $_SESSION['flash'][$type] = $msg; return null; }
    $m = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $m;
}

function page_header(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . '<title>' . e($title) . ' | CN FileHub</title>'
       . '<link rel="stylesheet" href="/assets/style.css"></head><body>'
       . '<header class="topbar"><span class="brand">CN FILEHUB</span></header>';
}
function page_footer(): void { echo '</body></html>'; }

require_once __DIR__ . '/Supabase.php';
require_once __DIR__ . '/Auth.php';
