// Records page: student auto-fill, PDF checks, upload with progress.
(() => {
  const form = document.getElementById('upload-form');
  if (!form) return;
  const $ = (id) => document.getElementById(id);
  const student = $('student'), num = $('student-number'), lvl = $('student-level'), type = $('doc-type'), file = $('file');
  const btn = $('upload-btn'), wrap = $('progress'), bar = $('progress-bar'), text = $('progress-text');
  const maxBytes = Number(form.dataset.maxMb || 10) * 1024 * 1024;
  const residency = $('residency'), semester = $('semester');
  const examBox = $('comp-exam'), mm = $('exam-mm'), dd = $('exam-dd'), yyyy = $('exam-yyyy'), takes = $('exam-takes');
  const segs = [mm, dd, yyyy];

  // ---- Comprehensive Examination: lights up only when that document type is chosen
  const isExam = () => type.selectedOptions[0]?.dataset.name === form.dataset.examType;
  const syncExam = () => {
    const on = isExam();
    examBox.disabled = !on;
    examBox.classList.toggle('is-active', on);
    if (!on) { segs.forEach((i) => { i.value = ''; i.classList.remove('seg-bad'); }); takes.value = ''; }
    else mm.focus();
  };
  type.addEventListener('change', syncExam);

  // ---- Segmented date: digits only, auto-advance, backspace steps back
  segs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/\D/g, '').slice(0, inp.maxLength);
      inp.classList.remove('seg-bad');
      const full = inp.value.length === inp.maxLength;
      const n = Number(inp.value);
      // Jump ahead early when a leading digit can only mean a 2-digit value (e.g. month 4 -> 04)
      const early = i === 0 ? n > 1 : i === 1 ? n > 3 : false;
      if (inp.value.length === 1 && early && i < 2) { inp.value = '0' + inp.value; }
      if ((full || (early && i < 2)) && i < 2) segs[i + 1].focus();
      else if (full && i === 2) takes.focus();
    });
    inp.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && inp.value === '' && i > 0) { e.preventDefault(); segs[i - 1].focus(); }
      if (e.key === 'ArrowLeft' && inp.selectionStart === 0 && i > 0) { e.preventDefault(); segs[i - 1].focus(); }
      if (e.key === 'ArrowRight' && inp.selectionStart === inp.value.length && i < 2) { e.preventDefault(); segs[i + 1].focus(); }
    });
    inp.addEventListener('blur', () => { if (i < 2 && inp.value.length === 1) inp.value = '0' + inp.value; });
    // Pasting 04/18/2026, 04-18-2026 or 2026-04-18 into any box fills all three
    inp.addEventListener('paste', (e) => {
      const t = (e.clipboardData || window.clipboardData).getData('text').trim();
      let m = t.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/), parts = null;
      if (m) parts = [m[1], m[2], m[3]];
      else if ((m = t.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/))) parts = [m[2], m[3], m[1]];
      if (!parts) return;
      e.preventDefault();
      segs.forEach((s2, k) => { s2.value = parts[k].padStart(k === 2 ? 4 : 2, '0'); s2.classList.remove('seg-bad'); });
      takes.focus();
    });
  });

  /** Returns {iso} for a valid, non-future date, or {error}. */
  const readExamDate = () => {
    const m = Number(mm.value), d = Number(dd.value), y = Number(yyyy.value);
    if (!mm.value || !dd.value || yyyy.value.length !== 4) return { error: 'Enter the full examination date (MM | DD | YYYY).' };
    const dt = new Date(y, m - 1, d);
    if (y < 1950 || dt.getFullYear() !== y || dt.getMonth() !== m - 1 || dt.getDate() !== d) {
      segs.forEach((i) => i.classList.add('seg-bad'));
      return { error: 'That examination date does not exist. Check the month, day and year.' };
    }
    if (dt > new Date()) { segs.forEach((i) => i.classList.add('seg-bad')); return { error: 'The examination date cannot be in the future.' }; }
    return { iso: `${yyyy.value}-${mm.value.padStart(2, '0')}-${dd.value.padStart(2, '0')}` };
  };

  const fill = () => {
    const o = student.selectedOptions[0];
    num.value = o?.dataset.number || '';
    lvl.value = o?.dataset.level || '';
  };
  student.addEventListener('change', fill);
  if (form.dataset.preselect) { student.value = form.dataset.preselect; fill(); }

  file.addEventListener('change', () => {
    const f = file.files[0];
    if (f && !/\.pdf$/i.test(f.name)) { CN.toast('Only PDF files are allowed.', 'error'); file.value = ''; }
    else if (f && f.size > maxBytes) { CN.toast(`The file must be ${form.dataset.maxMb} MB or smaller.`, 'error'); file.value = ''; }
  });

  form.addEventListener('submit', (ev) => {
    ev.preventDefault();
    const f = file.files[0];
    if (!student.value) return CN.toast('Select a student first.', 'error');
    if (!type.value) return CN.toast('Select a document type.', 'error');
    let examIso = '';
    if (isExam()) {
      const r = readExamDate();
      if (r.error) { (mm.value && dd.value ? yyyy : (mm.value ? dd : mm)).focus(); return CN.toast(r.error, 'error'); }
      if (!takes.value) { takes.focus(); return CN.toast('Choose the number of takes.', 'error'); }
      examIso = r.iso;
    }
    if (!residency.value) return CN.toast('Choose the year of residency.', 'error');
    if (!semester.value) return CN.toast('Choose the semester.', 'error');
    if (!f) return CN.toast('Choose a PDF file to upload.', 'error');

    const data = new FormData();
    data.append('student_id', student.value);
    data.append('document_type_id', type.value);
    data.append('year_of_residency', residency.value);
    data.append('semester', semester.value);
    if (isExam()) { data.append('exam_date', examIso); data.append('exam_takes', takes.value); }
    data.append('file', f);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/upload.php');
    xhr.setRequestHeader('X-CSRF-Token', CN.csrf());
    xhr.setRequestHeader('Accept', 'application/json');
    btn.disabled = true;
    wrap.hidden = false;
    bar.style.width = '0%';
    text.textContent = 'Uploading…';

    xhr.upload.onprogress = (e) => {
      if (!e.lengthComputable) return;
      const p = Math.round((e.loaded / e.total) * 100);
      bar.style.width = p + '%';
      text.textContent = p < 100 ? `Uploading… ${p}%` : 'Saving…';
    };
    xhr.onload = () => {
      btn.disabled = false;
      let res = {};
      try { res = JSON.parse(xhr.responseText); } catch (e) { /* non-JSON error page */ }
      if (xhr.status === 401) { location.href = 'login.php'; return; }
      if (xhr.status >= 200 && xhr.status < 300 && res.ok) {
        wrap.hidden = true;
        CN.toast(`Uploaded successfully. Tracking ID: ${res.tracking_id}`);
        type.value = ''; file.value = ''; syncExam();
      } else {
        wrap.hidden = true;
        CN.toast(res.error || 'The upload failed. Try again.', 'error');
      }
    };
    xhr.onerror = () => { btn.disabled = false; wrap.hidden = true; CN.toast('Network error. Check your connection and try again.', 'error'); };
    xhr.send(data);
  });
})();
