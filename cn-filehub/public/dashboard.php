<?php
require_once __DIR__ . '/../src/bootstrap.php';
$user = Auth::guard();          // any logged-in user; use Auth::guard('admin') on admin-only pages

page_header('Dashboard');
?>
<main class="card">
  <h1>Welcome, <?= e($user['name'] ?: $user['email']) ?></h1>
  <p class="muted">Signed in as <strong><?= e($user['email']) ?></strong> (<?= e($user['role']) ?>)</p>
  <p>Login works. Build the <?= $user['role'] === 'admin' ? 'admin document management' : 'thesis submission' ?> pages from here.</p>
  <form method="post" action="/logout.php">
    <?= csrf_field() ?>
    <button class="btn outline" type="submit">Log out</button>
  </form>
</main>
<?php page_footer();
