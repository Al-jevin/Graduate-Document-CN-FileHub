// Shared helpers: CSRF token for fetch calls and toast notifications.
window.CN = {
  csrf: () => document.querySelector('meta[name="csrf-token"]')?.content || '',
  toast(message, type = 'success') {
    const box = document.getElementById('toasts');
    if (!box) return;
    const t = document.createElement('div');
    t.className = 'toast toast-' + (type === 'error' ? 'error' : 'success');
    t.setAttribute('role', type === 'error' ? 'alert' : 'status');
    t.textContent = message;
    box.appendChild(t);
    setTimeout(() => t.remove(), type === 'error' ? 7000 : 4500);
  },
};
document.querySelectorAll('[data-flash]').forEach((n) => CN.toast(n.textContent.trim(), n.dataset.type));
