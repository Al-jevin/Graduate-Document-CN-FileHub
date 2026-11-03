<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = Session::requireLogin();

$from = (string) ($_GET['from'] ?? $_POST['from'] ?? 'submissions');
if (!in_array($from, ['submissions', 'tasks', 'home'], true)) $from = 'submissions';
$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');

$sub = is_uuid($id) ? Submissions::find($id) : null;
if ($sub === null) {
    Session::flash('toast_error', 'That submission could not be found. It may have been deleted.');
    redirect($from . '.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::require();
    $status  = (string) ($_POST['status'] ?? '');
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    if (!in_array($status, Submissions::REVIEW_STATUSES, true)) {
        $error = 'Choose a status before updating.';
    } elseif (mb_strlen($remarks) > 1000) {
        $error = 'Remarks must be 1000 characters or fewer.';
    } else {
        $result = Submissions::review($id, $status, $remarks, $user['id']);
        if ($result['ok']) {
            Session::flash('toast_success', 'Status updated to ' . $status . '.');
            redirect('review.php?id=' . rawurlencode($id) . '&from=' . rawurlencode($from));
        }
        $error = 'Could not update the status. ' . $result['error'];
    }
    $sub['remarks'] = $remarks; // keep what the admin typed
}

$history  = Submissions::history($id);
$reviewed = $sub['status'] !== 'Submitted';
$selected = $_POST['status'] ?? ($reviewed ? $sub['status'] : '');

layout_header(['title' => 'Review Submission', 'nav' => $from === 'tasks' ? 'tasks' : ($from === 'home' ? 'home' : 'submissions'), 'css' => ['app.css']]);
?>
<h1>Review Submission</h1>
<hr class="rule rule-title">

<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

<div class="review-grid">
  <section class="card">
    <h2>Student Information</h2>
    <dl class="info">
      <div><dt>Name</dt><dd>: <?= e($sub['student_name']) ?></dd></div>
      <div><dt>Student Number</dt><dd>: <?= e($sub['student_number']) ?></dd></div>
      <div><dt>Document</dt><dd>: <?= e($sub['document_name']) ?></dd></div>
      <div><dt>Tracking ID</dt><dd>: <?= e($sub['tracking_id']) ?></dd></div>
      <div><dt>Current Status</dt><dd>: <?= e($sub['status']) ?></dd></div>
    </dl>
  </section>
  <section class="card">
    <h2>Uploaded File</h2>
    <p class="file-label">Filename</p>
    <p class="file-name"><?= e($sub['original_filename'] ?? 'No file on record') ?></p>
    <?php if (!empty($sub['storage_path'])): ?>
      <a class="btn btn-block" href="document.php?id=<?= e(rawurlencode($id)) ?>" target="_blank" rel="noopener">Open Document</a>
    <?php endif; ?>
  </section>
</div>

<form method="post" action="review.php" novalidate>
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= e($id) ?>">
  <input type="hidden" name="from" value="<?= e($from) ?>">
  <section class="card">
    <h2>Update Status</h2>
    <div class="status-grid">
      <div>
        <label for="status">Status</label>
        <select id="status" name="status" class="field" required>
          <option value="">Choose Status</option>
          <?php foreach (Submissions::REVIEW_STATUSES as $s): ?>
            <option value="<?= e($s) ?>"<?= $selected === $s ? ' selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="remarks">Remarks</label>
        <input id="remarks" name="remarks" class="field" maxlength="1000" placeholder="Write down remarks..."
               value="<?= e((string) ($sub['remarks'] ?? '')) ?>">
      </div>
    </div>
  </section>
  <div class="btn-row">
    <button type="submit" class="btn btn-lg">Update Status</button>
    <a class="btn btn-lg btn-muted" href="<?= e($from) ?>.php">back</a>
  </div>
</form>

<?php if ($history): ?>
<section class="card history-card">
  <h2>Review History</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Date</th><th>Admin</th><th>Change</th><th>Remarks</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td><?= e(format_manila($h['changed_at'])) ?></td>
            <td><?= e(trim(($h['profiles']['first_name'] ?? '') . ' ' . ($h['profiles']['last_name'] ?? ''))) ?></td>
            <td><?= e($h['old_status']) ?> → <?= e($h['new_status']) ?></td>
            <td><?= e((string) ($h['remarks'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
<?php layout_footer();
