<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require_once __DIR__ . '/Env.php';
Env::load(ROOT_PATH . '/.env');

date_default_timezone_set('Asia/Manila');

if (Env::get('APP_ENV', 'production') === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Supabase.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Csrf.php';

// Baseline security headers for every page.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

Session::start();
