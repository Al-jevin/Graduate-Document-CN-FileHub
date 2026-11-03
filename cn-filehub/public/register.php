<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/components.php';

if (Session::check()) redirect('home.php');

$errors = [];
$v = array_fill_keys(['first_name', 'last_name', 'employee_number', 'position', 'email', 'username'], '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::require();
    foreach ($v as $k => $_) $v[$k] = trim((string) ($_POST[$k] ?? ''));
    $result = Auth::register($_POST);
    if ($result['ok']) {
        Session::flash('success', 'Account created. Log in with your username and password.');
        redirect('login.php');
    }
    $errors = $result['errors'];
}
$err = fn (string $k) => $errors[$k] ?? '';

// DRAFT wording for administrators: please review before it is final.
$terms = [
  ['Purpose of Data Collection', 'Student information and administrator account details are collected for student identification, account management, document submission, submission tracking, and management of graduate research and thesis records.'],
  ['Document Storage', 'CN FILEHUB stores thesis manuscripts, research papers, revisions, supporting documents, and other graduate academic records that administrators upload on behalf of students.'],
  ['Access to Documents', 'Only registered administrators of the College of Nursing and the Graduate Program may access student records and documents, and only when necessary for review, administration, document management, and archival purposes.'],
  ['Use of Submitted Documents', 'Documents are used only for legitimate academic, administrative, review, tracking, and archival purposes related to the Graduate Program.'],
  ['Data Confidentiality', 'Student information and submitted files are confidential. Do not disclose, copy, or share them outside your authorized responsibilities.'],
  ['Data Retention', 'Student records and submitted documents may be retained as institutional academic and archival records according to applicable institutional policies and requirements. Deleted records are hidden from view and retained in the system.'],
  ['Graduate Research Repository Consent', 'A thesis or research paper is not automatically published or included in the Graduate Research Repository. Repository inclusion only proceeds with the student’s consent and approval by authorized personnel.'],
  ['Appropriate System Use', 'CN FILEHUB must only be used for legitimate academic and administrative purposes. Do not access, modify, download, or view records without a work-related reason. Administrative actions are recorded in an activity log.'],
  ['Account Security', 'You are responsible for keeping your account and password secure. Sharing your account credentials with another person is prohibited.'],
  ['Accuracy of Information and Documents', 'You are responsible for ensuring that the student information and documents you enter or upload are accurate, complete, and match the original records.'],
];

layout_header(['title' => 'Create New Account', 'css' => ['auth.css']]);
?>
<section class="reg-card">
  <a class="reg-close" href="login.php" aria-label="Back to Log in" title="Back to Log in">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" fill="none"/></svg>
  </a>
  <h1>Create New Account</h1>
  <?php if ($err('_form')): ?><div class="alert alert-error" role="alert"><?= e($err('_form')) ?></div><?php endif; ?>
  <form method="post" action="register.php" novalidate data-live-form>
    <?= Csrf::field() ?>
    <h2>Administrator Information</h2>
    <p class="intro">This registration is intended for College of Nursing and Graduate Program administrative personnel (staff and faculty) who manage student research and thesis documents through CN FILEHUB.</p>
    <div class="grid-2">
      <?php text_field('first_name', 'First Name', $v['first_name'], $err('first_name'), ['placeholder' => 'Enter first name...', 'maxlength' => '60', 'autocomplete' => 'given-name']); ?>
      <?php text_field('last_name', 'Last Name', $v['last_name'], $err('last_name'), ['placeholder' => 'Enter last name...', 'maxlength' => '60', 'autocomplete' => 'family-name']); ?>
      <?php text_field('employee_number', 'Employee Number', $v['employee_number'], $err('employee_number'), ['placeholder' => 'Example: 2024-12345', 'pattern' => '[A-Za-z0-9\-]{3,30}', 'title' => 'Letters, numbers and hyphens (3 to 30)', 'maxlength' => '30']); ?>
      <?php text_field('position', 'Position', $v['position'], $err('position'), ['placeholder' => 'Enter position...', 'maxlength' => '80']); ?>
    </div>
    <?php text_field('email', 'Email Address', $v['email'], $err('email'), ['type' => 'email', 'placeholder' => 'Enter email address...', 'maxlength' => '254', 'autocomplete' => 'email']); ?>
    <hr class="rule">
    <h2>Account Information</h2>
    <?php text_field('username', 'Username', $v['username'], $err('username'), ['placeholder' => 'Choose a username...', 'pattern' => '[A-Za-z0-9_.]{4,30}', 'title' => '4 to 30 letters, numbers, underscores or periods', 'maxlength' => '30', 'autocomplete' => 'username']); ?>
    <?php password_field('password', 'Password', $err('password'), ['style' => 'box', 'role' => 'new', 'placeholder' => 'Enter password...', 'autocomplete' => 'new-password']); ?>
    <?php password_field('password_confirm', 'Confirm Password', $err('password_confirm'), ['style' => 'box', 'role' => 'confirm', 'placeholder' => 'Confirm password...', 'autocomplete' => 'new-password']); ?>
    <hr class="rule">
    <h2>Terms and Conditions and Data Privacy Notice</h2>
    <div class="terms">
      <p>Before creating your account, please read and understand how student information and documents are managed within CN FILEHUB.</p>
      <ol>
        <?php foreach ($terms as [$title, $text]): ?>
          <li><strong><?= e($title) ?>.</strong> <?= e($text) ?></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <hr class="rule">
    <div class="terms-check">
      <input type="checkbox" id="terms" name="terms" value="1" data-terms required>
      <label for="terms">I have read and understood the Terms and Conditions and Data Privacy Notice.</label>
    </div>
    <?php if ($err('terms')): ?><div class="field-error"><?= e($err('terms')) ?></div><?php endif; ?>
    <button type="submit" class="btn" data-needs-valid disabled>Create New Account</button>
    <a class="btn btn-outline reg-back" href="login.php">back to Log in</a>
  </form>
</section>
<?php layout_footer(['form-validate.js']);
