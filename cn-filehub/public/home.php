<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = Session::requireLogin();

// First paint comes with the page; dashboard.js refreshes it every 30 seconds.
$payload = json_encode(Dashboard::snapshot(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

layout_header(['title' => 'Home', 'nav' => 'home', 'css' => ['app.css', 'dashboard.css']]);
?>
<section class="greeting">
  <h1>Hi, <?= e($user['first_name']) ?>!</h1>
  <p>Manage student research submissions and digitize former student records from the physical archive.</p>
</section>

<h2 class="section-title">Submission Overview</h2>
<div class="overview">
  <button type="button" class="ov-card ov-docs" data-overview="documents">
    <span class="ov-label">Archived Documents</span><span class="ov-num" data-count="documents">0</span>
  </button>
  <button type="button" class="ov-card ov-students" data-overview="students">
    <span class="ov-label">Student Records</span><span class="ov-num" data-count="students">0</span>
  </button>
  <button type="button" class="ov-card ov-month" data-overview="month">
    <span class="ov-label">Archived Files This Month</span><span class="ov-num" data-count="month">0</span>
  </button>
</div>

<div class="dash-grid">
  <section class="card cal-card" id="cal-card" data-today="<?= e(date('Y-m-d')) ?>">
    <h2>Calendar</h2>
    <hr class="cal-rule">
    <div class="cal-head">
      <div class="cal-title">
        <select id="cal-month" class="cal-select" aria-label="Month"></select>
        <select id="cal-year" class="cal-select" aria-label="Year"></select>
      </div>
      <div class="cal-nav">
        <button type="button" id="cal-prev" aria-label="Previous month">‹</button>
        <button type="button" id="cal-today" class="cal-today">Today</button>
        <button type="button" id="cal-next" aria-label="Next month">›</button>
      </div>
    </div>
    <div class="cal-grid" id="cal-grid"></div>
    <p class="cal-legend"><span class="dot"></span> Day with document activity (uploaded or reviewed)</p>
    <div class="day-panel" id="day-panel" hidden>
      <h3 id="day-title"></h3>
      <div id="day-list"></div>
    </div>
  </section>

  <section class="card recent-card">
    <h2>Recent Uploads</h2>
    <hr class="cal-rule">
    <div id="recent-list" class="recent-list"></div>
  </section>
</div>

<div class="tasks-head">
  <h2 class="section-title">Tasks</h2>
  <a class="view-all" href="tasks.php">view all</a>
</div>
<div class="task-strip" id="task-strip"></div>

<div id="ov-modal" class="modal-overlay" hidden>
  <div class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="ov-title">
    <div class="modal-head">
      <h2 id="ov-title"></h2>
      <button type="button" class="modal-close" id="ov-close" aria-label="Close">×</button>
    </div>
    <p id="ov-note" class="muted" hidden></p>
    <div id="ov-body" class="modal-body"></div>
  </div>
</div>

<script type="application/json" id="dash-data"><?= $payload ?></script>
<?php layout_footer(['dashboard.js']);
