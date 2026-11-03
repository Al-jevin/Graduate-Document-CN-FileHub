<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/components.php';

if (Session::check()) redirect('home.php');

$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::require();
    $username = trim((string) ($_POST['username'] ?? ''));
    $result = Auth::attemptLogin($username, (string) ($_POST['password'] ?? ''));
    if ($result['ok']) redirect('home.php');
    $error = $result['error'];
}
$notice = Session::pullFlash('success');

layout_header(['title' => 'Admin Portal', 'css' => ['auth.css']]);
?>
<section class="auth">
  <h1>Admin Portal</h1>
  <?php if ($notice): ?><div class="alert alert-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="login.php" novalidate data-live-form>
    <?= Csrf::field() ?>
    <?php text_field('username', 'Username', $username, '', ['style' => 'pill', 'autocomplete' => 'username', 'maxlength' => '60']); ?>
    <?php password_field('password', 'Password'); ?>
    <p class="auth-help">Forgot Password? <a href="forgot-password.php">Click here.</a></p>
    <button type="submit" class="btn">Login</button>
  </form>
  <hr class="rule">
  <p class="auth-help">Don’t have an account?</p>
  <a class="btn btn-outline" href="register.php">Create New Account</a>
</section>
<?php layout_footer(['form-validate.js']);
