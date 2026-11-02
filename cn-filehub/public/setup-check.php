<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

// Development helper only. Hidden unless APP_ENV=local.
if (Env::get('APP_ENV') !== 'local') {
    abort(404, 'Not found.');
}

$checks = [];
$add = function (string $state, string $label, string $hint = '') use (&$checks): void {
    $checks[] = compact('state', 'label', 'hint');
};

// --- PHP and extensions
version_compare(PHP_VERSION, '8.2.0', '>=')
    ? $add('ok', 'PHP ' . PHP_VERSION)
    : $add('fail', 'PHP ' . PHP_VERSION, 'PHP 8.2 or newer is required.');
foreach (['curl', 'fileinfo', 'openssl', 'mbstring', 'json'] as $ext) {
    extension_loaded($ext)
        ? $add('ok', "Extension: $ext")
        : $add('fail', "Extension: $ext", 'Enable it in php.ini (Laragon: Menu > PHP > Extensions).');
}

// --- .env
is_file(ROOT_PATH . '/.env')
    ? $add('ok', '.env file found')
    : $add('fail', '.env file missing', 'Copy .env.example to .env and fill in the keys.');
foreach (['SUPABASE_URL', 'SUPABASE_ANON_KEY', 'SUPABASE_SERVICE_ROLE_KEY'] as $var) {
    Env::has($var)
        ? $add('ok', "$var is set")
        : $add('fail', "$var is empty", 'Add it to .env.');
}

// --- Supabase (only if configured)
if (Env::has('SUPABASE_URL') && Env::has('SUPABASE_ANON_KEY') && Env::has('SUPABASE_SERVICE_ROLE_KEY')) {
    $settings = Supabase::request('GET', '/auth/v1/settings', ['key' => 'anon']);
    if ($settings['status'] === 200) {
        $add('ok', 'Supabase reachable, anon key accepted');
        $autoconfirm = $settings['data']['mailer_autoconfirm'] ?? null;
        if ($autoconfirm === true) {
            $add('ok', 'Email confirmation is off (new admins can log in right away)');
        } else {
            $add('warn', 'Email confirmation is on', 'Authentication > Sign In / Providers > Email > turn off "Confirm email".');
        }
    } else {
        $add('fail', 'Cannot reach Supabase Auth', (string) ($settings['error'] ?? ('HTTP ' . $settings['status']))
            . ' If this is an SSL certificate error, set CURL_CA_BUNDLE in .env.');
    }

    $types = Supabase::rest('GET', 'document_types', ['query' => ['select' => 'name', 'order' => 'sort_order']]);
    if ($types['status'] === 200 && is_array($types['data'])) {
        count($types['data']) === 7
            ? $add('ok', 'Database schema installed (7 document types)')
            : $add('warn', 'Document types found: ' . count($types['data']) . ' (expected 7)', 'Re-run database/schema.sql.');
    } elseif ($types['status'] === 401 || $types['status'] === 403) {
        $add('fail', 'Service-role key rejected', 'Check SUPABASE_SERVICE_ROLE_KEY in .env.');
    } else {
        $add('fail', 'Database schema not found', 'Run database/schema.sql in the Supabase SQL Editor.');
    }

    $bucket = Supabase::request('GET', '/storage/v1/bucket/' . rawurlencode((string) Env::get('STORAGE_BUCKET', 'student-documents')));
    if ($bucket['status'] === 200) {
        ($bucket['data']['public'] ?? true) === false
            ? $add('ok', 'Storage bucket exists and is private')
            : $add('fail', 'Storage bucket is PUBLIC', 'Make the bucket private. Student documents must not be public.');
    } else {
        $add('fail', 'Storage bucket not found', 'Run database/schema.sql (it creates the private bucket).');
    }

    // Security sanity check: the public anon key must not be able to read students.
    $probe = Supabase::rest('GET', 'students', ['key' => 'anon', 'query' => ['select' => 'id', 'limit' => '1']]);
    ($probe['status'] === 401 || $probe['status'] === 403 || $probe['data'] === [])
        ? $add('ok', 'Student data is not readable with the public key')
        : $add('fail', 'Student data is exposed to the public key', 'Re-run database/schema.sql (Row Level Security section).');
}

$failed = count(array_filter($checks, fn ($c) => $c['state'] === 'fail'));
$labels = ['ok' => 'Pass', 'fail' => 'Fix', 'warn' => 'Check'];

layout_header(['title' => 'Setup check', 'bare' => true]);
?>
<h1>Setup check</h1>
<p class="page-sub"><?= $failed === 0 ? 'Everything required is in place.' : $failed . ' item' . ($failed > 1 ? 's' : '') . ' to fix before moving on.' ?></p>
<section class="card" style="margin-top:2rem">
  <ul class="check-list">
    <?php foreach ($checks as $c): ?>
      <li class="<?= e($c['state']) ?>">
        <span class="mark"><?= e($labels[$c['state']]) ?></span>
        <span><?= e($c['label']) ?><?php if ($c['hint'] !== ''): ?><span class="hint"><?= e($c['hint']) ?></span><?php endif; ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php layout_footer();
