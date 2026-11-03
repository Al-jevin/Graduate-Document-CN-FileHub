<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/components.php';

$user = Session::requireLogin();

$keys = ['first_name', 'last_name', 'student_number', 'graduate_year_level', 'section_cohort', 'email', 'academic_year'];
$v = array_fill_keys($keys, '');
$former = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::require();
    foreach ($keys as $k) $v[$k] = trim((string) ($_POST[$k] ?? ''));
    $former = !empty($_POST['is_former']);
    $result = Students::create($_POST, $user['id']);
    if ($result['ok']) {
        Session::flash('toast_success', 'Student record created. You can now upload their document.');
        redirect('records.php?student=' . rawurlencode((string) $result['id']));
    }
    $errors = $result['errors'];
}
$err = fn (string $k) => $errors[$k] ?? '';
$years = Students::academicYears();

layout_header(['title' => 'Add Student', 'nav' => 'records', 'css' => ['app.css']]);
?>
<h1>Add Student</h1>
<p class="page-sub">Create a digital record for a student whose thesis or research documents are being digitized from the physical archive.</p>

<?php if ($err('_form')): ?><div class="alert alert-error" role="alert"><?= e($err('_form')) ?></div><?php endif; ?>
<form method="post" action="add-student.php" class="app-form" novalidate id="student-form">
  <?= Csrf::field() ?>
  <div class="grid-2">
    <?php text_field('first_name', 'First Name', $v['first_name'], $err('first_name'), ['placeholder' => 'Enter first name...', 'maxlength' => '60']); ?>
    <?php text_field('last_name', 'Last Name', $v['last_name'], $err('last_name'), ['placeholder' => 'Enter last name...', 'maxlength' => '60']); ?>
  </div>
  <div class="grid-3b">
    <?php text_field('student_number', 'Student Number (XXXX-XXXXX)', $v['student_number'], $err('student_number'), ['placeholder' => 'Example: 2024-12345', 'pattern' => '[0-9]{4}-[0-9]{5}', 'title' => 'Format: XXXX-XXXXX', 'maxlength' => '10']); ?>
    <?php text_field('graduate_year_level', 'Graduate Year Level', $v['graduate_year_level'], $err('graduate_year_level'), ['placeholder' => 'Enter year...', 'maxlength' => '30']); ?>
    <?php text_field('section_cohort', 'Section / Cohort', $v['section_cohort'], $err('section_cohort'), ['placeholder' => 'Enter section / cohort...', 'maxlength' => '40']); ?>
  </div>
  <?php text_field('email', 'Email Address', $v['email'], $err('email'), ['type' => 'email', 'placeholder' => 'Enter email address...', 'maxlength' => '254']); ?>

  <div class="grid-former">
    <div class="form-row">
      <div class="label-row"><label for="is_former">Former student?</label></div>
      <label class="check-inline" for="is_former">
        <input type="checkbox" id="is_former" name="is_former" value="1"<?= $former ? ' checked' : '' ?>>
        <span>Yes, this is a former student</span>
      </label>
    </div>
    <div class="form-row">
      <div class="label-row">
        <label for="academic_year">Academic Year</label>
        <span class="field-error"><?= e($err('academic_year')) ?></span>
      </div>
      <select id="academic_year" name="academic_year" class="field"<?= $former ? ' disabled' : ' required' ?>>
        <option value="">Example: 2024-2025</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= e($y) ?>"<?= $v['academic_year'] === $y ? ' selected' : '' ?>><?= e($y) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="btn-row">
    <button type="submit" class="btn btn-lg">Create Archived Student Record</button>
    <a class="btn btn-lg btn-muted" href="records.php">back</a>
  </div>
</form>
<?php layout_footer(['add-student.js']);
