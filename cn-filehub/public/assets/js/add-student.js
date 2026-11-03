// Add Student: Academic Year is unavailable when "former student" is ticked.
(() => {
  const former = document.getElementById('is_former');
  const year = document.getElementById('academic_year');
  if (!former || !year) return;
  const sync = () => {
    year.disabled = former.checked;
    year.required = !former.checked;
    if (former.checked) year.value = '';
  };
  former.addEventListener('change', sync);
  sync();
})();
