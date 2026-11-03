// Home dashboard: counts, overview popups, calendar, recent uploads, task preview.
// Everything is drawn with textContent (never innerHTML) so stored text can't inject markup.
(() => {
  const dataEl = document.getElementById('dash-data');
  if (!dataEl) return;
  let data = JSON.parse(dataEl.textContent);

  const $ = (id) => document.getElementById(id);
  const mk = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; };
  const badge = (s) => mk('span', 'badge ' + (s === 'Submitted' ? 'badge-home' : 'badge-' + s.toLowerCase()), s);
  const reviewUrl = (id) => 'review.php?id=' + encodeURIComponent(id) + '&from=home';
  const pad = (n) => String(n).padStart(2, '0');
  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

  async function api(params) {
    const res = await fetch('api/dashboard.php?' + new URLSearchParams(params), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (res.status === 401) { location.href = 'login.php'; throw new Error('Session expired'); }
    const body = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(body.error || 'Could not load data.');
    return body;
  }

  // ---------- Counts, recent uploads, tasks ----------
  let lastError = null;
  function renderSnapshot() {
    document.querySelectorAll('[data-count]').forEach((n) => { n.textContent = data.counts[n.dataset.count] ?? 0; });

    const recent = $('recent-list');
    recent.replaceChildren();
    if (!data.recent.length) recent.append(mk('p', 'empty', 'No uploads yet.'));
    data.recent.forEach((r) => {
      const a = mk('a', 'recent-item');
      a.href = reviewUrl(r.id);
      const foot = mk('span', 'ri-foot');
      foot.append(mk('span', '', r.date), badge(r.status));
      a.append(mk('span', 'ri-title', r.document_name), mk('span', 'ri-student', r.student_name), foot);
      recent.append(a);
    });

    const strip = $('task-strip');
    strip.replaceChildren();
    if (!data.tasks.length) strip.append(mk('p', 'empty', 'No pending tasks. Everything has been reviewed.'));
    data.tasks.forEach((t) => {
      const a = mk('a', 'task-card');
      a.href = reviewUrl(t.id);
      a.append(mk('span', 'tk-title', t.document_name), mk('span', 'tk-student', t.student_name), mk('span', 'tk-date', t.date), badge(t.status));
      strip.append(a);
    });

    if (data.error && data.error !== lastError) CN.toast(data.error, 'error');
    lastError = data.error || null;
  }

  // ---------- Overview popups ----------
  const ov = $('ov-modal'), ovTitle = $('ov-title'), ovBody = $('ov-body'), ovNote = $('ov-note');
  const titles = { documents: 'All Archived Documents', students: 'Student Records', month: 'All Archived Files This Month' };
  let opener = null;

  const table = (heads, rows) => {
    const t = mk('table', 'table'), thead = mk('thead'), hr = mk('tr');
    heads.forEach((h) => hr.append(mk('th', '', h)));
    thead.append(hr);
    const tb = mk('tbody');
    rows.forEach((cells) => {
      const tr = mk('tr');
      cells.forEach((c) => { const td = mk('td'); td.append(c instanceof Node ? c : document.createTextNode(c)); tr.append(td); });
      tb.append(tr);
    });
    t.append(thead, tb);
    const wrap = mk('div', 'table-wrap');
    wrap.append(t);
    return wrap;
  };

  function renderOverview(d) {
    ovBody.replaceChildren();
    ovNote.hidden = d.total <= d.rows.length;
    ovNote.textContent = `Showing the latest ${d.rows.length} of ${d.total}.`;
    if (!d.rows.length) { ovBody.append(mk('p', 'empty', d.type === 'students' ? 'No student records yet.' : 'No documents to show.')); return; }
    if (d.type === 'students') {
      ovBody.append(table(['Student Number', 'Name', 'Year Level', 'Section / Cohort', 'Record', 'Academic Year'],
        d.rows.map((s) => [s.student_number, s.name, s.level, s.section, s.former ? 'Former / Archived' : 'Current', s.academic_year || '—'])));
      return;
    }
    ovBody.append(table(['Tracking ID', 'Student', 'Document', 'Date Submitted', 'Status', 'Action'],
      d.rows.map((r) => {
        const view = mk('a', 'btn btn-sm btn-dark', 'View');
        view.href = reviewUrl(r.id);
        return [r.tracking_id, r.student_name, r.document_name, r.date, badge(r.status), view];
      })));
  }

  async function openOverview(type, from) {
    opener = from;
    ovTitle.textContent = titles[type];
    ovNote.hidden = true;
    ovBody.replaceChildren(mk('p', 'empty', 'Loading…'));
    ov.hidden = false;
    $('ov-close').focus();
    try { renderOverview(await api({ part: 'overview', type })); }
    catch (err) { ovBody.replaceChildren(mk('p', 'empty', err.message)); }
  }
  const closeOverview = () => { ov.hidden = true; opener?.focus(); };
  document.querySelectorAll('[data-overview]').forEach((b) => b.addEventListener('click', () => openOverview(b.dataset.overview, b)));
  $('ov-close').addEventListener('click', closeOverview);
  ov.addEventListener('click', (e) => { if (e.target === ov) closeOverview(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !ov.hidden) closeOverview(); });

  // ---------- Calendar ----------
  const card = $('cal-card');
  const [ty, tm, td] = card.dataset.today.split('-').map(Number);
  const todayKey = `${ty}-${pad(tm)}-${pad(td)}`;
  const view = { y: ty, m: tm };
  let activity = {}, selected = null, monthToken = 0, dayToken = 0;
  const selMonth = $('cal-month'), selYear = $('cal-year');
  MONTHS.forEach((n, i) => selMonth.add(new Option(n, i + 1)));
  for (let y = ty + 5; y >= ty - 25; y--) selYear.add(new Option(y, y));

  function longDate(key) {
    const [y, m, d] = key.split('-').map(Number);
    return `${MONTHS[m - 1]} ${d}, ${y}`;
  }

  function renderCalendar() {
    selMonth.value = view.m;
    selYear.value = view.y;
    const grid = $('cal-grid');
    grid.replaceChildren();
    ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach((d) => grid.append(mk('div', 'cal-dow', d)));
    const first = new Date(view.y, view.m - 1, 1).getDay();
    const days = new Date(view.y, view.m, 0).getDate(); // handles 28/29/30/31 and leap years
    for (let i = 0; i < first; i++) grid.append(mk('div'));
    for (let d = 1; d <= days; d++) {
      const key = `${view.y}-${pad(view.m)}-${pad(d)}`;
      const a = activity[key];
      const b = mk('button', 'cal-day', d);
      b.type = 'button';
      b.dataset.date = key;
      if (key === todayKey) b.classList.add('today');
      if (a) b.classList.add('has-activity');
      if (key === selected) b.classList.add('selected');
      b.setAttribute('aria-label', longDate(key) + (a ? `: ${a.uploaded} uploaded, ${a.reviewed} reviewed` : ''));
      b.addEventListener('click', () => selectDay(key));
      grid.append(b);
    }
  }

  async function loadMonth() {
    const token = ++monthToken;
    try {
      const res = await api({ part: 'month', year: view.y, month: view.m });
      if (token !== monthToken) return;
      activity = res.days && !Array.isArray(res.days) ? res.days : {};
    } catch (err) { activity = {}; CN.toast(err.message, 'error'); }
    renderCalendar();
  }

  async function loadDay() {
    if (!selected) return;
    const token = ++dayToken;
    const panel = $('day-panel'), list = $('day-list');
    panel.hidden = false;
    $('day-title').textContent = 'Documents on ' + longDate(selected);
    try {
      const res = await api({ part: 'day', date: selected });
      if (token !== dayToken) return;
      list.replaceChildren();
      if (!res.items.length) list.append(mk('p', 'empty', 'No document activity on this date.'));
      res.items.forEach((i) => {
        const a = mk('a', 'day-item');
        a.href = reviewUrl(i.id);
        a.append(mk('strong', '', i.document_name + ' — ' + i.student_name), mk('span', 'day-meta', `${i.action} at ${i.time}`),
                 mk('span', '', i.tracking_id), badge(i.status));
        list.append(a);
      });
    } catch (err) { list.replaceChildren(mk('p', 'empty', err.message)); }
  }

  function selectDay(key) { selected = key; renderCalendar(); loadDay(); }
  function go(y, m) {
    if (m < 1) { m = 12; y -= 1; } else if (m > 12) { m = 1; y += 1; }
    view.y = Math.min(Math.max(y, ty - 25), ty + 5);
    view.m = m;
    loadMonth();
  }
  selMonth.addEventListener('change', () => go(view.y, Number(selMonth.value)));
  selYear.addEventListener('change', () => go(Number(selYear.value), view.m));
  $('cal-prev').addEventListener('click', () => go(view.y, view.m - 1));
  $('cal-next').addEventListener('click', () => go(view.y, view.m + 1));
  $('cal-today').addEventListener('click', () => { view.y = ty; view.m = tm; loadMonth(); });

  // ---------- Start + auto refresh (picks up uploads from other admins) ----------
  renderSnapshot();
  renderCalendar();
  loadMonth();
  setInterval(async () => {
    if (document.hidden) return;
    try { data = await api({ part: 'snapshot' }); renderSnapshot(); } catch (e) { /* try again next time */ }
    loadMonth();
    loadDay();
  }, 30000);
})();
