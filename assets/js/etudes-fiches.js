/* Experimental presentation only: keep the existing form, UIDs and business calculations. */
(() => {
  const form = document.getElementById('formSpecsTech');
  if (!form) return;
  const make = (tag, cls, text) => {
    const el = document.createElement(tag); el.className = cls;
    if (text) el.textContent = text;
    return el;
  };
  const setText = (el, text) => { if (el.textContent !== text) el.textContent = text; };
  const button = (cls, text) => { const el = make('button', cls, text); el.type = 'button'; return el; };
  const rows = () => [...form.querySelectorAll('.st-row')];
  const field = (row, name) => row.querySelector(`[name="st_${name}[]"]`);
  const value = (row, name) => field(row, name)?.value || '';
  const costly = row => ['Matériel', 'Composant', 'Prestataire', 'PCB'].includes(value(row, 'type'));
  const money = amount => new Intl.NumberFormat('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(amount) + ' €';
  const decimal = text => Math.max(0, parseFloat(String(text).replace(',', '.')) || 0);
  const totals = list => {
    let ht = 0, ttc = 0, unknown = 0, delays = 0;
    list.filter(row => value(row, 'description').trim()).forEach(row => {
      if (costly(row)) {
        if (estimateIsUnknown(value(row, 'cout_unitaire'))) unknown++;
        else {
          const amount = decimal(value(row, 'quantite')) * decimal(value(row, 'cout_unitaire'));
          if (value(row, 'cout_taxe') === 'TTC') ttc += amount; else ht += amount;
        }
      }
      if (value(row, 'type') !== 'S.T.x.x' && (!value(row, 'delai_jours').trim() || estimateIsUnknown(value(row, 'delai_jours')))) delays++;
    });
    return {ht, ttc, unknown, delays};
  };
  const amountText = t => money(t.ht) + ' HT' + (t.ttc ? ' + ' + money(t.ttc) + ' TTC' : '');
  const summary = make('section', 'ef-overview'); summary.setAttribute('aria-label', 'Synthèse de l’étude');
  const total = make('strong', 'ef-overview-total');
  const missing = make('span', 'ef-missing');
  summary.append(make('span', '', 'Estimation connue'), total, missing);
  form.prepend(summary);
  const intro = make('p', 'ef-intro', 'Consultez les solutions par fonction. Dépliez une fiche pour modifier son estimation.');
  summary.before(intro);
  const dirty = make('span', 'ef-save-status'); dirty.setAttribute('role', 'status');
  const save = document.querySelector('[type="submit"][form="formSpecsTech"]');
  save?.parentElement.prepend(dirty);
  save?.parentElement.classList.add('ef-save-actions');
  const signature = () => JSON.stringify([...new FormData(form)]);
  let baseline;
  let submitting = false;
  let scheduled = false;
  function setOpen(row, open) {
    row.classList.toggle('ef-open', open);
    row.querySelector('.ef-toggle').setAttribute('aria-expanded', String(open));
    setText(row.querySelector('.ef-edit'), open ? 'Replier' : 'Modifier');
  }
  function show(row) {
    const block = row.closest('.st-sf-block');
    block.classList.remove('ef-folded');
    block.querySelector('.ef-section-toggle').setAttribute('aria-expanded', 'true');
    setOpen(row, true);
  }
  function unknownControl(row, name, label) {
    const input = field(row, name);
    const wrap = make('label', 'ef-unknown');
    const toggle = document.createElement('input'); toggle.type = 'checkbox'; toggle.dataset.unknownField = name;
    wrap.append(toggle, document.createTextNode(label)); input.closest('td').append(wrap);
    let known = estimateIsUnknown(input.value) ? '' : input.value;
    toggle.addEventListener('change', () => {
      if (toggle.checked) { known = input.value; input.value = 'inconnue'; }
      else input.value = known;
      input.dispatchEvent(new Event('input', {bubbles: true}));
    });
  }
  function enhance(row) {
    if (row.querySelector('.ef-card-head')) return;
    const cells = [...row.children];
    const labels = ['Référence', 'Description et rattachement', 'Type', 'Quantité', 'Coût unitaire et taxe', 'Total calculé', 'Variation / incertitude', 'Délai estimé (jours)', 'Actions'];
    cells.forEach((cell, index) => {
      cell.classList.add('ef-field', 'ef-field-' + index);
      const title = make('span', 'ef-field-label', labels[index]); cell.prepend(title);
      const input = cell.querySelector('input:not([type=hidden]),select');
      if (input && !input.hasAttribute('aria-label')) input.setAttribute('aria-label', labels[index]);
    });
    const head = make('td', 'ef-card-head'); head.colSpan = 9;
    const toggle = button('ef-toggle', '');
    const icon = make('i', 'fas fa-microchip ef-icon'); icon.setAttribute('aria-hidden', 'true');
    const text = make('span', 'ef-card-text');
    text.append(make('span', 'ef-card-meta'), make('strong', 'ef-card-title'), make('span', 'ef-card-detail'));
    const numbers = make('span', 'ef-card-numbers'); numbers.append(make('strong', 'ef-card-cost'), make('span', 'ef-card-delay'));
    toggle.append(icon, text, numbers, make('span', 'ef-edit', 'Modifier'));
    toggle.addEventListener('click', () => setOpen(row, !row.classList.contains('ef-open')));
    head.append(toggle); row.prepend(head);
    const footer = make('td', 'ef-card-footer'); footer.colSpan = 9;
    const child = button('btn btn-secondary btn-sm', '+ Ajouter un sous-élément');
    const done = button('btn btn-secondary btn-sm', 'Terminer la saisie');
    done.addEventListener('click', () => { setOpen(row, false); toggle.focus(); });
    child.addEventListener('click', () => {
      const block = row.closest('.st-sf-block');
      block.querySelector('.btn-st-add').click();
      const added = [...block.querySelectorAll('.st-row')].at(-1);
      field(added, 'parent_uid').value = value(row, 'uid');
      window.stHierarchy?.refresh(); refresh(); show(added); field(added, 'description').focus();
    });
    footer.append(child, make('span', 'ef-local-hint', 'Enregistrez l’étude pour conserver vos modifications.'), done); row.append(footer);
    unknownControl(row, 'cout_unitaire', 'Coût à préciser');
    unknownControl(row, 'delai_jours', 'Délai à préciser');
    row.querySelector('.btn-st-del').setAttribute('aria-label', 'Supprimer cette solution technique');
    const open = !value(row, 'description').trim();
    setOpen(row, open);
  }
  form.querySelectorAll('.st-sf-block').forEach((block, index) => {
    const head = block.querySelector('.st-sf-head');
    const toggle = button('ef-section-toggle', ''); toggle.setAttribute('aria-expanded', 'true');
    const content = block.querySelector('.sf-table-wrap'); content.id = 'ef-function-' + index;
    toggle.setAttribute('aria-controls', content.id);
    while (head.firstChild) toggle.append(head.firstChild);
    head.append(toggle);
    toggle.addEventListener('click', () => {
      const folded = block.classList.toggle('ef-folded'); toggle.setAttribute('aria-expanded', String(!folded));
    });
    block.querySelector('.btn-st-add').addEventListener('click', () => {
      refresh();
      const added = [...block.querySelectorAll('.st-row')].at(-1);
      show(added); field(added, 'description').focus();
    });
  });
  function refresh() {
    scheduled = false;
    const list = rows(); list.forEach(enhance);
    const byUid = new Map(list.map(row => [value(row, 'uid'), row]));
    list.forEach(row => {
      const type = value(row, 'type'); const desc = value(row, 'description').trim();
      const id = row.querySelector('.st-id').textContent;
      setText(row.querySelector('.ef-card-meta'), type + ' · ' + id);
      setText(row.querySelector('.ef-card-title'), desc || 'Nouvelle solution technique');
      const parent = byUid.get(value(row, 'parent_uid'));
      const detail = [costly(row) ? value(row, 'quantite') + ' unité(s)' : '', parent ? 'Sous-élément de ' + parent.querySelector('.st-id').textContent : ''].filter(Boolean).join(' · ');
      setText(row.querySelector('.ef-card-detail'), detail);
      const unknown = estimateIsUnknown(value(row, 'cout_unitaire'));
      const cost = !costly(row) ? (type === 'S.T.x.x' ? 'Référence liée' : 'Sans coût direct') : unknown ? 'Coût à préciser' : money(decimal(value(row, 'quantite')) * decimal(value(row, 'cout_unitaire'))) + ' ' + value(row, 'cout_taxe');
      setText(row.querySelector('.ef-card-cost'), cost);
      row.querySelector('.ef-card-cost').classList.toggle('ef-needs-info', costly(row) && unknown);
      const delay = value(row, 'delai_jours');
      setText(row.querySelector('.ef-card-delay'), type === 'S.T.x.x' ? '' : (!delay || estimateIsUnknown(delay) ? 'Délai à préciser' : 'Délai : ' + delay + ' jours'));
      row.querySelector('.ef-toggle').setAttribute('aria-label', (desc || 'Nouvelle solution technique') + ' — ' + id);
      let depth = 0, cursor = parent; const seen = new Set([row]);
      while (cursor && !seen.has(cursor)) { seen.add(cursor); depth++; cursor = byUid.get(value(cursor, 'parent_uid')); }
      row.style.setProperty('--ef-depth', String(Math.min(depth, 3)));
      row.classList.toggle('ef-child', !!parent);
      [['cout_unitaire', !costly(row)], ['delai_jours', type === 'S.T.x.x']].forEach(([name, hidden]) => {
        const control = row.querySelector(`[data-unknown-field="${name}"]`);
        control.checked = estimateIsUnknown(value(row, name)); control.closest('label').hidden = hidden;
      });
      [3, 4, 5].forEach(index => row.querySelector('.ef-field-' + index).classList.toggle('ef-inapplicable', !costly(row)));
      [6, 7].forEach(index => row.querySelector('.ef-field-' + index).classList.toggle('ef-inapplicable', type === 'S.T.x.x'));
    });
    const t = totals(list); setText(total, amountText(t));
    setText(missing, [t.unknown ? t.unknown + ' coût(s) à préciser' : '', t.delays ? t.delays + ' délai(s) à préciser' : ''].filter(Boolean).join(' · '));
    form.querySelectorAll('.st-sf-block').forEach(block => {
      const live = [...block.querySelectorAll('.st-row')].filter(row => value(row, 'description').trim());
      const t = totals(live);
      setText(block.querySelector('.sf-cost-badge'), live.length + ' solution(s) · ' + amountText(t) + (t.unknown ? ' · sous-total connu' : ''));
    });
    if (baseline !== undefined) {
      const changed = signature() !== baseline;
      setText(dirty, changed ? 'Modifications non enregistrées' : 'Toutes les modifications sont enregistrées');
      dirty.classList.toggle('ef-needs-info', changed);
    }
  }
  function schedule() { if (!scheduled) { scheduled = true; queueMicrotask(refresh); } }
  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  document.addEventListener('st-rows-changed', schedule);
  // Open the editor before the existing hierarchy handler focuses a nested field.
  form.addEventListener('click', event => {
    const link = event.target.closest('[data-st-target]');
    if (link) { const row = rows().find(row => value(row, 'uid') === link.dataset.stTarget); if (row) show(row); }
  }, true);
  form.addEventListener('submit', () => { submitting = true; setTimeout(() => { submitting = false; }, 0); });
  window.addEventListener('beforeunload', event => {
    if (!submitting && baseline !== signature()) { event.preventDefault(); event.returnValue = ''; }
  });
  form.classList.add('etudes-fiches');
  form.closest('.r1b-step-workspace')?.classList.add('ef-workspace');
  const structure = form.querySelector('.st-structure'); if (structure) structure.open = false;
  refresh(); baseline = signature(); refresh();
})();
