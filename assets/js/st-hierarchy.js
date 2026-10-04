/* Structural parent/child links, independent of technical references and estimates. */
(() => {
  const form = document.getElementById('formSpecsTech');
  if (!form) return;
  const tree = document.getElementById('st-structure-tree');
  const status = document.getElementById('st-hierarchy-status');
  const saved = JSON.parse(document.getElementById('st-reference-data')?.textContent || '[]');
  const rows = () => [...form.querySelectorAll('.st-row')];
  const field = (row, name) => row.querySelector(`[name="${name}[]"]`);
  const uid = row => field(row, 'st_uid').value;
  const label = row => `${row.querySelector('.st-id').textContent} — ${field(row, 'st_description').value.trim() || '(sans description)'}`;
  const parent = row => field(row, 'st_parent_uid')?.value || '';
  const active = () => rows().filter(row => field(row, 'st_description').value.trim());
  const make = (tag, text, className) => {
    const el = document.createElement(tag);
    if (text) el.textContent = text;
    if (className) el.className = className;
    return el;
  };
  function cycle(row, target, byUid) {
    const seen = new Set([uid(row)]);
    while (target && byUid.has(target)) {
      if (seen.has(target)) return true;
      seen.add(target);
      target = parent(byUid.get(target));
    }
    return false;
  }
  function link(row, text = label(row)) {
    const a = make('a', text);
    a.href = '#st-node-' + uid(row);
    a.dataset.stTarget = uid(row);
    return a;
  }
  function ensure(row) {
    if (row.querySelector('.st-parent-control')) return;
    let input = field(row, 'st_parent_uid');
    if (!input) {
      input = make('input'); input.type = 'hidden'; input.name = 'st_parent_uid[]';
      field(row, 'st_description').parentElement.append(input);
    }
    const control = make('div', '', 'st-parent-control');
    const caption = make('label', 'Parent : ');
    const select = make('select', '', 'form-control st-parent-select');
    caption.append(select); control.append(caption, make('div', '', 'st-parent-meta'));
    input.parentElement.append(control);
  }
  function refresh() {
    const all = rows(); all.forEach(ensure);
    const list = active(), byUid = new Map(list.map(row => [uid(row), row]));
    const children = new Map();
    list.forEach(row => {
      const key = parent(row);
      if (!children.has(key)) children.set(key, []);
      children.get(key).push(row);
    });
    all.forEach(row => {
      row.id = 'st-node-' + uid(row);
      const select = row.querySelector('.st-parent-select');
      select.setAttribute('aria-label', 'Parent de ' + label(row));
      const options = [['', 'Aucun parent'], ...list.filter(candidate => !cycle(row, uid(candidate), byUid)).map(candidate => [uid(candidate), label(candidate)])];
      const selected = parent(row);
      if (selected && !options.some(([value]) => value === selected)) options.push([selected, 'Parent indisponible — choisissez un autre parent']);
      const signature = JSON.stringify(options);
      if (select.dataset.options !== signature) {
        select.replaceChildren(...options.map(([value, text]) => new Option(text, value)));
        select.dataset.options = signature;
      }
      select.value = selected;
      const meta = row.querySelector('.st-parent-meta');
      const ownChildren = children.get(uid(row)) || [];
      const metaSignature = JSON.stringify([selected, byUid.has(selected) ? label(byUid.get(selected)) : '', ownChildren.map(child => [uid(child), label(child)])]);
      if (meta.dataset.signature !== metaSignature) {
        meta.replaceChildren();
        if (selected && byUid.has(selected)) meta.append(make('span', 'Parent : '), link(byUid.get(selected)));
        else meta.append(make('span', selected ? 'Parent indisponible' : 'S.T. racine'));
        if (ownChildren.length) {
          const details = make('details', '', 'st-children');
          details.append(make('summary', `${ownChildren.length} enfant${ownChildren.length > 1 ? 's' : ''}`));
          const ul = make('ul');
          ownChildren.forEach(child => {const li = make('li'); li.append(link(child)); ul.append(li);});
          details.append(ul); meta.append(details);
        }
        meta.dataset.signature = metaSignature;
      }
    });
    const signature = JSON.stringify(list.map(row => [uid(row), parent(row), label(row)]));
    if (tree.dataset.signature === signature) return;
    tree.dataset.signature = signature;
    tree.replaceChildren();
    if (!list.length) {tree.append(make('p', 'Ajoutez une S.T. avec une description pour afficher sa structure.')); return;}
    const ul = make('ul'); tree.append(ul);
    const stack = [...list.filter(row => !parent(row) || !byUid.has(parent(row))).reverse()].map(row => [row, ul]);
    const visited = new Set();
    while (stack.length) {
      const [row, container] = stack.pop();
      if (visited.has(uid(row))) continue;
      visited.add(uid(row));
      const li = make('li'); container.append(li);
      const kids = children.get(uid(row)) || [];
      if (!kids.length) {li.append(link(row)); continue;}
      const details = make('details'); details.open = true;
      const summary = make('summary'); summary.append(link(row), make('span', ` · ${kids.length} enfant${kids.length > 1 ? 's' : ''}`));
      const nested = make('ul'); details.append(summary, nested); li.append(details);
      kids.slice().reverse().forEach(child => stack.push([child, nested]));
    }
  }
  window.stHierarchy = {
    refresh,
    beforeDelete(row) {
      const target = uid(row);
      const children = active().filter(child => parent(child) === target);
      const historical = saved.filter(child => child.parent_uid === target && active().some(live => uid(live) === child.uid));
      const count = new Set([...children.map(uid), ...historical.map(child => child.uid)]).size;
      if (count && !window.confirm(`Cette S.T. possède ${count} sous-tâche${count > 1 ? 's' : ''} enfant${count > 1 ? 's' : ''}. Supprimer le parent ? Ses enfants seront conservés et deviendront des S.T. racines.`)) return false;
      if (count && ![...form.querySelectorAll('[name="st_deleted_parent_uid[]"]')].some(input => input.value === target)) {
        const input = make('input'); input.type = 'hidden'; input.name = 'st_deleted_parent_uid[]'; input.value = target; form.append(input);
      }
      children.forEach(child => {field(child, 'st_parent_uid').value = '';});
      if (count) status.textContent = `${count} enfant${count > 1 ? 's' : ''} conservé${count > 1 ? 's' : ''} sans parent. Enregistrez pour confirmer.`;
      return true;
    }
  };
  form.addEventListener('change', event => {
    if (!event.target.matches('.st-parent-select')) return;
    const row = event.target.closest('.st-row');
    const byUid = new Map(active().map(item => [uid(item), item]));
    if (cycle(row, event.target.value, byUid)) {
      status.textContent = 'Cette relation créerait une boucle dans la hiérarchie des S.T.';
      event.target.value = parent(row); return;
    }
    field(row, 'st_parent_uid').value = event.target.value;
    status.textContent = 'Parent modifié. Enregistrez les spécifications techniques pour conserver la relation.';
    refresh();
  });
  // Clearing a parent description also deletes it on save: use the same explicit warning.
  form.addEventListener('submit', event => {
    for (const row of rows()) {
      if (!field(row, 'st_description').value.trim() && !window.stHierarchy.beforeDelete(row)) {event.preventDefault(); return;}
    }
  });
  form.addEventListener('input', event => {if (event.target.matches('[name="st_description[]"]')) refresh();});
  form.addEventListener('click', event => {
    const a = event.target.closest('[data-st-target]');
    if (!a) return;
    const row = rows().find(item => uid(item) === a.dataset.stTarget);
    if (!row) return;
    event.preventDefault();
    form.querySelectorAll('.st-highlight').forEach(item => item.classList.remove('st-highlight'));
    row.classList.add('st-highlight');
    row.scrollIntoView({block: 'center'}); field(row, 'st_description').focus({preventScroll: true});
  });
  document.addEventListener('st-rows-changed', refresh);
  refresh();
})();
