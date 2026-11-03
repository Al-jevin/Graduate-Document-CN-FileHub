<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/components.php';

// Only reachable right after step 1 (forgot-password.php), never by typing the URL.
$reset = $_SESSION['reset'] ?? null;
if (!$reset || $reset['expires'] < time()) {
    unset($_SESSION['reset']);
    redirect('forgot-password.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::require();
    $new = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if (($m = Auth::passwordError($new)) !== null) $errors['password'] = $m;
    if ($new !== $confirm) $errors['password_confirm'] = 'password confirmation does not match';
    if (!$errors) {
        $result = Auth::setPassword($reset['user_id'], $new);
        if ($result['ok']) {
            unset($_SESSION['reset']);
            Session::flash('success', 'Password updated. Log in with your new password.');
            redirect('login.php');
        }
        $errors['_form'] = $result['error'] ?? 'Could not update the password.';
    }
}
$err = fn (string $k) => $errors[$k] ?? '';

layout_header(['title' => 'Reset Password', 'css' => ['auth.css']]);
?>
<section class="auth">
  <h1>Reset Password</h1>
  <?php if ($err('_form')): ?><div class="alert alert-error" role="alert"><?= e($err('_form')) ?></div><?php endif; ?>
  <form method="post" action="reset-password.php" novalidate data-live-form>
    <?= Csrf::field() ?>
    <?php password_field('password', 'New Password', $err('password'), ['role' => 'new', 'autocomplete' => 'new-password']); ?>
    <?php password_field('password_confirm', 'Confirm Password', $err('password_confirm'), ['role' => 'confirm', 'autocomplete' => 'new-password']); ?>
    <button type="submit" class="btn" data-needs-valid disabled>Save Password</button>
  </form>
  <a class="btn btn-outline" href="login.php" style="margin-top:1.2rem">back to Log in</a>
</section>
<?php layout_footer(['form-validate.js']);
