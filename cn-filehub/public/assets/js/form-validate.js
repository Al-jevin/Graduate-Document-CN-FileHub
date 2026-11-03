// Show/hide password + live validation for forms marked data-live-form.
// Password rules mirror src/Auth.php (the server is always the final check).
(() => {
  const rules = [
    [(p) => p.length >= 8, 'must be 8 characters long'],
    [(p) => p.length <= 72, 'must be 72 characters or fewer'],
    [(p) => /[A-Z]/.test(p), 'needs an uppercase letter'],
    [(p) => /[a-z]/.test(p), 'needs a lowercase letter'],
    [(p) => /[0-9]/.test(p), 'needs a number'],
    [(p) => /[^A-Za-z0-9\s]/.test(p), 'needs a special character'],
  ];
  const firstError = (p) => (rules.find(([ok]) => !ok(p)) || [null, ''])[1];

  document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    const input = document.getElementById(btn.dataset.togglePassword);
    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.querySelector('.eye-open').hidden = show;
      btn.querySelector('.eye-shut').hidden = !show;
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  document.querySelectorAll('form[data-live-form]').forEach((form) => {
    const pw = form.querySelector('[data-password]');
    const confirm = form.querySelector('[data-confirm]');
    const terms = form.querySelector('[data-terms]');
    const submit = form.querySelector('[data-needs-valid]');
    const msg = (el, text) => {
      const out = form.querySelector(`[data-error-for="${el.id}"]`);
      if (out) out.textContent = text;
    };
    const dirty = new Set();

    const update = () => {
      let ok = form.checkValidity();
      if (pw) {
        const m = firstError(pw.value);
        msg(pw, dirty.has(pw) ? m : '');
        if (m) ok = false;
      }
      if (confirm && pw) {
        const mismatch = confirm.value !== pw.value;
        msg(confirm, dirty.has(confirm) && mismatch ? 'password confirmation does not match' : '');
        if (mismatch) ok = false;
      }
      if (terms && !terms.checked) ok = false;
      if (submit) submit.disabled = !ok;
    };

    form.addEventListener('input', (ev) => { dirty.add(ev.target); update(); });
    form.addEventListener('change', update);
    update();
  });
})();
