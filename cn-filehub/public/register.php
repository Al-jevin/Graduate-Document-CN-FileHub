<?php
require_once __DIR__ . '/../src/bootstrap.php';
if (Auth::user()) { redirect('/dashboard.php'); }

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'student_number' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($old as $k => $_) { $old[$k] = trim((string) ($_POST[$k] ?? '')); }
    $r = Auth::register($_POST);
    if ($r['ok']) {
        flash('success', 'Account created. We sent a confirmation link to your PLM email — verify it, then log in.');
        redirect('/login.php?portal=student');
    }
    $errors = $r['errors'];
}

page_header('Create New Account');
?>
<main class="card">
  <h1>Create New Account</h1>
  <?php if ($errors): ?>
    <div class="alert err" role="alert"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" autocomplete="on" novalidate>
    <?= csrf_field() ?>
    <h2>Graduate Student Information</h2>
    <p class="muted small">This registration is intended for currently enrolled graduate students who will submit and manage their research or thesis documents through CN FILEHUB.</p>
    <div class="row">
      <div><label for="first_name">First Name</label>
        <input id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" maxlength="100" autocomplete="given-name" required></div>
      <div><label for="last_name">Last Name</label>
        <input id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" maxlength="100" autocomplete="family-name" required></div>
    </div>
    <label for="student_number">Student Number</label>
    <input id="student_number" name="student_number" value="<?= e($old['student_number']) ?>" maxlength="20" inputmode="numeric" required>
    <label for="email">PLM Email Address</label>
    <input id="email" type="email" name="email" value="<?= e($old['email']) ?>" maxlength="254" autocomplete="email" required>

    <hr>
    <h2>Account Information</h2>
    <label for="password">Password <span class="hint">8+ characters, upper &amp; lower case, number</span></label>
    <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
    <label for="confirm_password">Confirm Password</label>
    <input id="confirm_password" type="password" name="confirm_password" maxlength="72" autocomplete="new-password" required>

    <hr>
    <?php require __DIR__ . '/../src/views/terms.php'; ?>
    <hr>
    <label class="check"><input type="checkbox" name="terms" value="1" required>
      I have read and understood the Terms and Conditions and Data Privacy Notice.</label>
    <button class="btn" type="submit">Create New Account</button>
    <p class="center small"><a href="/login.php?portal=student">Back to login</a></p>
  </form>
</main>
<?php page_footer();
