(() => {
  'use strict';
  const caches = new WeakMap();
  const index = form => {
    let cached = caches.get(form);
    if (!cached) {
      const rows = [...form.querySelectorAll('.st-row')];
      cached = {rows, byUid: new Map(rows.map(row => [row.querySelector('[name="st_uid[]"]').value, row]))};
      caches.set(form, cached);
    }
    return cached;
  };
  document.addEventListener('st-rows-changed', () => {
    const form = document.getElementById('formSpecsTech');
    if(form) caches.delete(form);
  });
  window.ProjectFlow = window.ProjectFlow || {};
  window.ProjectFlow.stIndex = index;
})();
