<?php
declare(strict_types=1);

/**
 * Shared page chrome: green top bar, seals, optional admin nav pill.
 * $opts: title (string), nav (home|submissions|records|tasks|null), bare (bool, auth pages)
 */
function layout_header(array $opts = []): void
{
    $title = $opts['title'] ?? 'CN FILEHUB';
    $nav   = $opts['nav'] ?? null;
    $user  = Session::user();
    $seals = ['seal-college.png', 'seal-university.png'];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | CN FILEHUB</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">
    <?php foreach ($seals as $seal): ?>
      <?php if (is_file(__DIR__ . '/../public/assets/img/' . $seal)): ?>
        <img src="<?= e(asset('img/' . $seal)) ?>" alt="" class="seal">
      <?php endif; ?>
    <?php endforeach; ?>
    <span class="brand-name">CN FILEHUB</span>
  </a>
  <?php if ($user): ?>
    <form method="post" action="logout.php" class="logout-form">
      <?= Csrf::field() ?>
      <button type="submit" class="btn-pill">Log out</button>
    </form>
  <?php endif; ?>
</header>
<?php if ($user && $nav !== null): ?>
  <nav class="mainnav" aria-label="Main">
    <span class="nav-user">
      <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><circle cx="12" cy="7.5" r="4.5"/><path d="M3 22c0-5 4-8 9-8s9 3 9 8z"/></svg>
      <?= e($user['first_name'] ?? 'Admin') ?>
    </span>
    <ul>
      <?php foreach (['home' => 'Home', 'submissions' => 'Submissions', 'records' => 'Records', 'tasks' => 'Tasks'] as $key => $label): ?>
        <li><a href="<?= e($key) ?>.php"<?= $nav === $key ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
<?php endif; ?>
<main class="page">
<?php
}

function layout_footer(array $scripts = []): void
{
    ?>
</main>
<?php foreach ($scripts as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
