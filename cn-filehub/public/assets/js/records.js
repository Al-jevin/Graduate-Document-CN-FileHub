// Records page: student auto-fill, PDF checks, upload with progress.
(() => {
  const form = document.getElementById('upload-form');
  if (!form) return;
  const $ = (id) => document.getElementById(id);
  const student = $('student'), num = $('student-number'), lvl = $('student-level'), type = $('doc-type'), file = $('file');
  const btn = $('upload-btn'), wrap = $('progress'), bar = $('progress-bar'), text = $('progress-text');
  const maxBytes = Number(form.dataset.maxMb || 10) * 1024 * 1024;

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
    if (!f) return CN.toast('Choose a PDF file to upload.', 'error');

    const data = new FormData();
    data.append('student_id', student.value);
    data.append('document_type_id', type.value);
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
        type.value = ''; file.value = '';
      } else {
        wrap.hidden = true;
        CN.toast(res.error || 'The upload failed. Try again.', 'error');
      }
    };
    xhr.onerror = () => { btn.disabled = false; wrap.hidden = true; CN.toast('Network error. Check your connection and try again.', 'error'); };
    xhr.send(data);
  });
})();
