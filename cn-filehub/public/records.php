<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

Session::requireLogin();

$students = Students::all();
$types    = Students::documentTypes();
$pre      = (string) ($_GET['student'] ?? '');
$maxMb    = min(Env::int('MAX_UPLOAD_MB', 10), 10);
$residency = Students::RESIDENCY_YEARS;
$semesters = Students::SEMESTERS;
$takes     = Students::EXAM_TAKES;

layout_header(['title' => 'Records', 'nav' => 'records', 'css' => ['app.css']]);
?>
<h1>Upload Archived Student Document</h1>
<p class="page-sub">Digitize existing physical records and save them to the student’s centralized digital record.</p>

<section class="card notice-card">
  <h2>Student But Not Listed?</h2>
  <p class="notice-text">If the student does not have an existing CN FILEHUB digital record, create a student record before uploading the document.</p>
  <a class="btn btn-lg" href="add-student.php">+ Add Student</a>
</section>

<form id="upload-form" class="app-form" novalidate data-max-mb="<?= e((string) $maxMb) ?>"
      data-preselect="<?= is_uuid($pre) ? e($pre) : '' ?>"
      data-exam-type="<?= e(Students::EXAM_TYPE) ?>">
  <div class="grid-3">
    <div>
      <label for="student">Select Student Name</label>
      <select id="student" name="student_id" class="field">
        <option value="">Select Student</option>
        <?php foreach ($students as $s): ?>
          <option value="<?= e($s['id']) ?>" data-number="<?= e($s['student_number']) ?>" data-level="<?= e($s['graduate_year_level']) ?>">
            [<?= $s['is_former'] ? 'FORMER / ARCHIVED' : 'CURRENT' ?>] <?= e($s['student_number']) ?> - <?= e($s['last_name']) ?>, <?= e($s['first_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Both current students and archived former records can be selected.</p>
    </div>
    <div>
      <label for="student-number">Student Number</label>
      <input id="student-number" class="field" readonly tabindex="-1">
    </div>
    <div>
      <label for="student-level">Entry Year</label>
      <input id="student-level" class="field" readonly tabindex="-1">
    </div>
  </div>

  <div class="form-block">
    <label for="doc-type">Select Document Type</label>
    <select id="doc-type" name="document_type_id" class="field">
      <option value="">Choose Document</option>
      <?php foreach ($types as $t): ?>
        <option value="<?= e((string) $t['id']) ?>" data-name="<?= e($t['name']) ?>"><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="grid-3 form-block">
    <fieldset id="comp-exam" class="exam-group" disabled>
      <legend>Comprehensive Examination</legend>
      <div class="exam-row">
        <div class="date-seg" role="group" aria-label="Date of comprehensive examination (month, day, year)">
          <input id="exam-mm" class="seg" inputmode="numeric" maxlength="2" placeholder="MM" autocomplete="off" aria-label="Month">
          <span class="seg-sep" aria-hidden="true">|</span>
          <input id="exam-dd" class="seg" inputmode="numeric" maxlength="2" placeholder="DD" autocomplete="off" aria-label="Day">
          <span class="seg-sep" aria-hidden="true">|</span>
          <input id="exam-yyyy" class="seg seg-year" inputmode="numeric" maxlength="4" placeholder="YYYY" autocomplete="off" aria-label="Year">
        </div>
        <select id="exam-takes" class="field" aria-label="Number of takes">
          <option value="">Choose number of takes</option>
          <?php foreach ($takes as $n => $label): ?>
            <option value="<?= $n ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </fieldset>
    <div>
      <label for="residency">Year of Residency</label>
      <select id="residency" name="year_of_residency" class="field">
        <option value="">Choose year of residency</option>
        <?php foreach ($residency as $n => $label): ?>
          <option value="<?= $n ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="semester">Semester</label>
      <select id="semester" name="semester" class="field">
        <option value="">Choose semester</option>
        <?php foreach ($semesters as $sem): ?>
          <option value="<?= e($sem) ?>"><?= e($sem) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-block">
    <label for="file">Upload Archived File</label>
    <input type="file" id="file" name="file" class="field field-file" accept="application/pdf,.pdf">
    <p class="hint">Only PDF files are allowed.</p>
  </div>

  <button type="submit" class="btn btn-lg" id="upload-btn">Upload to Student Record</button>
  <div id="progress" class="progress" hidden>
    <div class="progress-track"><div id="progress-bar" class="progress-bar"></div></div>
    <span id="progress-text" aria-live="polite"></span>
  </div>
</form>
<?php layout_footer(['records.js']);
