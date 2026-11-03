<?php
declare(strict_types=1);

/** Submissions page (scope "all") and Tasks page (scope "tasks"): same layout, tasks hides Status. */
function render_submission_list(string $scope): void
{
    $tasks = $scope === 'tasks';
    layout_header(['title' => $tasks ? 'Tasks' : 'Submissions', 'nav' => $tasks ? 'tasks' : 'submissions', 'css' => ['app.css']]);
    ?>
<h1><?= $tasks ? 'Tasks to Review' : 'Track Student Submissions' ?></h1>
<p class="page-sub"><?= $tasks ? 'Review student tasks' : 'Search, Review, and Manage student document submissions.' ?></p>

<section class="card search-card">
  <h2>Search Submissions</h2>
  <input id="search" class="field field-search" type="search" autocomplete="off" maxlength="60"
         placeholder="search by Tracking ID, student name, student number, document, or status..."
         aria-label="Search submissions">
  <p id="status-text" class="muted" aria-live="polite">Showing all submissions</p>
</section>

<section class="card table-card" id="list" data-scope="<?= e($scope) ?>">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th><th>Tracking ID</th><th>Student</th><th>Student Number</th><th>Document</th><th>Date Submitted</th>
          <?php if (!$tasks): ?><th>Status</th><?php endif; ?>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="rows"></tbody>
    </table>
  </div>
  <p id="empty" class="empty" hidden></p>
  <div id="pager" class="pager" hidden>
    <button type="button" class="btn btn-sm btn-muted" id="prev">Previous</button>
    <span id="page-info"></span>
    <button type="button" class="btn btn-sm btn-muted" id="next">Next</button>
  </div>
</section>

<div id="delete-modal" class="modal-overlay" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-title">
    <div class="modal-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="44" height="44"><path d="M6 6l12 12M18 6L6 18" stroke="#fff" stroke-width="3.2" stroke-linecap="round" fill="none"/></svg>
    </div>
    <h2 id="delete-title">Delete this File?</h2>
    <p id="delete-tracking" class="modal-tracking"></p>
    <div class="modal-actions">
      <button type="button" class="btn-modal btn-modal-cancel" id="delete-cancel">Cancel</button>
      <button type="button" class="btn-modal btn-modal-delete" id="delete-confirm">Delete</button>
    </div>
  </div>
</div>
<?php
    layout_footer(['submissions.js']);
}
