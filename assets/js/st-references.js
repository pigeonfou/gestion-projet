/* Project-local S.T. relations: IDs are stable; labels follow current numbering. */
(function () {
  const form = document.getElementById('formSpecsTech');
  const dialog = document.getElementById('st-reference-dialog');
  if (!form || !dialog) return;
  const saved = JSON.parse(document.getElementById('st-reference-data').textContent);
  const search = document.getElementById('st-reference-search');
  const list = document.getElementById('st-reference-list');
  const classic = document.getElementById('st-reference-classic');
  let current = null;
  let previous = null;
  let applying = false;
  const rows = () => [...form.querySelectorAll('.st-row')];
  const input = (row, name) => row.querySelector(`[name="${name}[]"]`);
  function uid(row) { return input(row, 'st_uid').value; }
  function refresh() {
    rows().forEach(row => {
      const select = input(row, 'st_type');
      const ref = input(row, 'st_reference_uid').value;
      const option = select.querySelector('[value="S.T.x.x"]');
      const target = rows().find(candidate => uid(candidate) === ref);
      const label = select.value !== 'S.T.x.x' ? 'S.T.x.x' :
        target ? target.querySelector('.st-id').textContent : 'S.T. supprimé / référence invalide';
      if (option.textContent !== label) option.textContent = label;
      const linked = select.value === 'S.T.x.x';
      ['st_delai_jours', 'st_variation'].forEach(name => {
        const field = input(row, name);
        // Keep the table columns in place when reference-only controls are hidden.
        field.hidden = linked;
        if (name === 'st_delai_jours') field.readOnly = linked;
      });
      select.dataset.previousType = select.value;
    });
  }
  function wouldCycle(candidate) {
    const byUid = new Map(rows().map(row => [uid(row), row]));
    const seen = new Set([uid(current)]);
    let cursor = candidate.uid;
    while (cursor) {
      if (seen.has(cursor)) return true;
      seen.add(cursor);
      const row = byUid.get(cursor);
      if (!row || input(row, 'st_type').value !== 'S.T.x.x') break;
      cursor = input(row, 'st_reference_uid').value;
    }
    return false;
  }
  function render() {
    list.replaceChildren();
    const query = search.value.trim().toLocaleLowerCase();
    saved.forEach(st => {
      const row = rows().find(row => uid(row) === st.uid);
      if (!row || st.uid === uid(current) || wouldCycle(st)) return;
      const label = row.querySelector('.st-id').textContent + ' — ' + input(row, 'st_description').value;
      if (!(label + ' ' + st.type + ' ' + st.sf).toLocaleLowerCase().includes(query)) return;
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn btn-secondary'; button.textContent = label;
      button.setAttribute('aria-pressed', String(input(current, 'st_reference_uid').value === st.uid));
      button.addEventListener('click', () => {
        input(current, 'st_reference_uid').value = st.uid;
        input(current, 'st_type').value = 'S.T.x.x';
        commit();
      });
      list.append(button);
    });
    if (!list.children.length) {
      const p = document.createElement('p');
      p.textContent = 'Aucun S.T. enregistré et valide disponible. Enregistrez les nouvelles lignes avant de les référencer.';
      list.append(p);
    }
  }
  function open(row, previousType) {
    if (dialog.open) return;
    current = row;
    previous = { type: previousType, ref: input(row, 'st_reference_uid').value };
    search.value = ''; classic.value = ''; render(); dialog.showModal(); search.focus();
  }
  function commit() {
    applying = true;
    input(current, 'st_type').dispatchEvent(new Event('change', { bubbles:true }));
    applying = false;
    previous = null; dialog.close(); refresh();
  }
  function cancel() {
    if (current && previous) {
      input(current, 'st_type').value = previous.type;
      input(current, 'st_reference_uid').value = previous.ref;
      applying = true;
      input(current, 'st_type').dispatchEvent(new Event('change', { bubbles:true }));
      applying = false;
    }
    previous = null; refresh();
  }
  form.addEventListener('change', e => {
    if (!e.target.matches('[name="st_type[]"]')) return;
    const row = e.target.closest('.st-row');
    if (applying) return;
    if (e.target.value === 'S.T.x.x') open(row, e.target.dataset.previousType || 'Matériel');
    else { input(row, 'st_reference_uid').value = ''; refresh(); }
  });
  form.addEventListener('click', e => {
    if (e.target.matches('[name="st_type[]"]') && e.target.value === 'S.T.x.x') {
      e.preventDefault(); open(e.target.closest('.st-row'), 'S.T.x.x');
    }
  });
  search.addEventListener('input', render);
  classic.addEventListener('change', () => {
    if (!classic.value) return;
    input(current, 'st_type').value = classic.value;
    input(current, 'st_reference_uid').value = ''; commit();
  });
  document.getElementById('st-reference-cancel').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', cancel);
  new MutationObserver(refresh).observe(form, { childList:true, subtree:true });
  refresh();
})();
