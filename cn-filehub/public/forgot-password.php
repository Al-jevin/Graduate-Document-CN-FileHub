<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/components.php';

if (Session::check()) redirect('home.php');

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::require();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!Env::bool('DEMO_PASSWORD_RESET')) {
        $error = 'Email-based password reset is not set up yet.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (Auth::isRateLimited('reset', $email)) {
        $error = Auth::lockoutMessage();
    } else {
        $profile = Auth::findActiveProfileByEmail($email);
        if ($profile) {
            Auth::recordAttempt('reset', $email, true);
            session_regenerate_id(true);
            $_SESSION['reset'] = ['user_id' => $profile['id'], 'expires' => time() + 600];
            redirect('reset-password.php');
        }
        Auth::recordAttempt('reset', $email, false);
        $error = 'No administrator account uses that email address.'; // demo only: reveals whether an email exists
    }
}

layout_header(['title' => 'Forgot Password', 'css' => ['auth.css']]);
?>
<section class="auth">
  <h1>Forgot Password</h1>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="forgot-password.php" novalidate>
    <?= Csrf::field() ?>
    <?php text_field('email', 'Email Address', $email, '', ['type' => 'email', 'style' => 'pill', 'autocomplete' => 'email', 'maxlength' => '254']); ?>
    <button type="submit" class="btn">Reset Password</button>
  </form>
  <a class="btn btn-outline" href="login.php" style="margin-top:1.2rem">back to Log in</a>
</section>
<?php layout_footer();
