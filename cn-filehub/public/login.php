<?php
require_once __DIR__ . '/../src/bootstrap.php';

$portal = ($_GET['portal'] ?? $_POST['portal'] ?? 'admin') === 'student' ? 'student' : 'admin';
if (Auth::user()) { redirect('/dashboard.php'); }

$error = flash('error');
$ok    = flash('success');
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = (string) ($_POST['email'] ?? '');
    $r = Auth::login($email, (string) ($_POST['password'] ?? ''), $portal);
    if ($r['ok']) { redirect('/dashboard.php'); }
    $error = $r['error'];
}

page_header(ucfirst($portal) . ' Portal');
?>
<main class="login">
  <h1><?= e(ucfirst($portal)) ?> Portal</h1>
  <?php if ($ok): ?><p class="alert ok"><?= e($ok) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="alert err" role="alert"><?= e($error) ?></p><?php endif; ?>

  <form method="post" autocomplete="on" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="portal" value="<?= e($portal) ?>">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" maxlength="254" required autofocus>
    <label for="password">Password</label>
    <input id="password" type="password" name="password" autocomplete="current-password" maxlength="128" required>
    <button class="btn" type="submit">Login</button>
  </form>

  <?php if ($portal === 'student'): ?>
    <hr>
    <p class="muted center">Don’t have an account?</p>
    <a class="btn outline" href="/register.php">Create New Account</a>
  <?php endif; ?>
</main>
<?php page_footer();
