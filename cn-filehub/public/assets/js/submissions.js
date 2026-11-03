// Submissions + Tasks tables: live search, paging, soft-delete confirmation.
(() => {
  const list = document.getElementById('list');
  if (!list) return;
  const scope = list.dataset.scope;
  const showStatus = scope === 'all';
  const $ = (id) => document.getElementById(id);
  const input = $('search'), rows = $('rows'), statusText = $('status-text'), empty = $('empty');
  const pager = $('pager'), prev = $('prev'), next = $('next'), pageInfo = $('page-info');
  let page = 1, timer = null, controller = null;

  const cell = (text, cls) => { const td = document.createElement('td'); td.textContent = text; if (cls) td.className = cls; return td; };

  function render(data) {
    rows.replaceChildren();
    data.rows.forEach((r) => {
      const tr = document.createElement('tr');
      tr.append(cell(r.n), cell(r.tracking_id), cell(r.student_name), cell(r.student_number), cell(r.document_name), cell(r.date));
      if (showStatus) {
        const td = document.createElement('td');
        const b = document.createElement('span');
        b.className = 'badge badge-' + r.status.toLowerCase();
        b.textContent = r.status;
        td.append(b);
        tr.append(td);
      }
      const act = document.createElement('td');
      act.className = 'actions';
      const view = document.createElement('a');
      view.className = 'btn btn-sm btn-dark';
      view.href = 'review.php?id=' + encodeURIComponent(r.id) + '&from=' + scope;
      view.textContent = 'View';
      const del = document.createElement('button');
      del.type = 'button';
      del.className = 'btn btn-sm btn-danger';
      del.textContent = 'Delete';
      del.addEventListener('click', () => openDelete(r));
      act.append(view, del);
      tr.append(act);
      rows.append(tr);
    });

    const q = data.q;
    empty.hidden = data.rows.length > 0;
    if (!data.rows.length) {
      empty.textContent = q ? `No submissions found for “${q}”.` : (scope === 'tasks' ? 'Nothing to review right now.' : 'No submissions yet.');
    }
    statusText.textContent = q
      ? `Showing ${data.total} result${data.total === 1 ? '' : 's'} for “${q}”`
      : 'Showing all submissions';
    pager.hidden = data.pages <= 1;
    pageInfo.textContent = `Page ${data.page} of ${data.pages}`;
    prev.disabled = data.page <= 1;
    next.disabled = data.page >= data.pages;
  }

  async function load() {
    controller?.abort();
    controller = new AbortController();
    try {
      const params = new URLSearchParams({ scope, q: input.value.trim(), page });
      const res = await fetch('api/submissions.php?' + params, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (res.status === 401) { location.href = 'login.php'; return; }
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Could not load submissions.');
      if (data.page > data.pages) { page = data.pages; return load(); }
      render(data);
    } catch (err) {
      if (err.name === 'AbortError') return;
      empty.hidden = false;
      empty.textContent = err.message;
    }
  }

  input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { page = 1; load(); }, 250); });
  prev.addEventListener('click', () => { page -= 1; load(); });
  next.addEventListener('click', () => { page += 1; load(); });

  // ----- Delete confirmation
  const modal = $('delete-modal'), tracking = $('delete-tracking'), cancel = $('delete-cancel'), confirmBtn = $('delete-confirm');
  let target = null, opener = null;
  const closeModal = () => { modal.hidden = true; target = null; opener?.focus(); };
  function openDelete(r) { target = r; opener = document.activeElement; tracking.textContent = r.tracking_id; modal.hidden = false; cancel.focus(); }
  cancel.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) closeModal(); });

  confirmBtn.addEventListener('click', async () => {
    if (!target) return;
    confirmBtn.disabled = true;
    try {
      const body = new URLSearchParams({ id: target.id });
      const res = await fetch('api/delete-submission.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'X-CSRF-Token': CN.csrf(), Accept: 'application/json' } });
      if (res.status === 401) { location.href = 'login.php'; return; }
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Could not delete the submission.');
      CN.toast('Submission deleted.');
      closeModal();
      load();
    } catch (err) {
      CN.toast(err.message, 'error');
    } finally {
      confirmBtn.disabled = false;
    }
  });

  load();
})();
