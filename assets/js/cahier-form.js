(function(){
  let step = 1;
  const total = 3;
  const panels = document.querySelectorAll('.cdc-step-panel');
  const steps = document.querySelectorAll('.cdc-stepper .step');
  const bar = document.getElementById('cdcProgress');
  const btnPrev = document.getElementById('btnPrev');
  const btnNext = document.getElementById('btnNext');

  function show(n) {
    step = Math.max(1, Math.min(total, n));
    panels.forEach(p => p.classList.toggle('active', +p.dataset.panel === step));
    steps.forEach(s => {
      const sn = +s.dataset.step;
      s.classList.toggle('active', sn === step);
      s.classList.toggle('done', sn < step);
    });
    bar.style.width = ((step / total) * 100) + '%';
    btnPrev.disabled = step === 1;
    btnNext.style.display = step === total ? 'none' : '';
  }
  btnPrev.addEventListener('click', () => show(step - 1));
  btnNext.addEventListener('click', () => show(step + 1));
  steps.forEach(s => s.addEventListener('click', () => show(+s.dataset.step)));
  const params = new URLSearchParams(window.location.search);
  const initialStep = parseInt(params.get('step') || params.get('onglet') || '1', 10);
  show(initialStep >= 1 && initialStep <= total ? initialStep : 1);

  // localStorage draft backup
  const form = document.getElementById('cdcForm');
  const key = 'cdc_draft_<?= (int)$projet_id ?>';
  try {
    const saved = localStorage.getItem(key);
    // server data has priority; only restore empty fields if needed — skip for simplicity
  } catch(e) {}
  form.addEventListener('change', () => {
    try {
      const fd = new FormData(form);
      const obj = {};
      for (const [k,v] of fd.entries()) {
        if (k.endsWith('[]')) {
          const kk = k.slice(0,-2);
          if (!obj[kk]) obj[kk] = [];
          obj[kk].push(v);
        } else obj[k] = v;
      }
      localStorage.setItem(key, JSON.stringify(obj));
    } catch(e) {}
  });
})();


(function() {
  const body = document.getElementById('sfBody');
  const addBtn = document.getElementById('sfAddRow');
  if (!body || !addBtn) return;

  function renumber() {
    body.querySelectorAll('.sf-row').forEach((row, i) => {
      const idSpan = row.querySelector('.sf-id');
      if (idSpan) idSpan.textContent = 'S.F.' + (i + 1);
    });
  }

  function bindDel(btn) {
    btn.addEventListener('click', () => {
      const rows = body.querySelectorAll('.sf-row');
      if (rows.length <= 1) {
        // clear last row instead of removing
        const row = rows[0];
        row.querySelector('input[type="text"]').value = '';
        row.querySelector('select').value = 'Obligatoire';
        renumber();
        return;
      }
      btn.closest('.sf-row').remove();
      renumber();
    });
  }

  body.querySelectorAll('.btn-sf-del').forEach(bindDel);

  addBtn.addEventListener('click', () => {
    const i = body.querySelectorAll('.sf-row').length;
    const tr = document.createElement('tr');
    tr.className = 'sf-row';
    tr.innerHTML =
      '<td><span class="sf-id">S.F.' + (i + 1) + '</span></td>' +
      '<td><input type="hidden" name="sf_uid[]" value=""><input type="text" name="sf_description[]" class="form-control" value="" placeholder="Description de la fonction…"></td>' +
      '<td><select name="sf_indicateur[]" class="form-control">' +
        '<option value="Obligatoire" selected>Obligatoire</option>' +
        '<option value="Facultative">Facultative</option>' +
        '<option value="Optionnel">Optionnel</option>' +
      '</select></td>' +
      '<td><button type="button" class="btn-sf-del" title="Supprimer" aria-label="Supprimer">&times;</button></td>';
    body.appendChild(tr);
    bindDel(tr.querySelector('.btn-sf-del'));
    renumber();
  });
})();



(function() {
  const body = document.getElementById('jalonBody');
  const addBtn = document.getElementById('jalonAddRow');
  if (!body || !addBtn) return;

  function bindDel(btn) {
    btn.addEventListener('click', () => {
      const rows = body.querySelectorAll('.jalon-row');
      if (rows.length <= 1) {
        const row = rows[0];
        row.querySelectorAll('input').forEach(inp => { inp.value = ''; });
        return;
      }
      btn.closest('.jalon-row').remove();
    });
  }
  body.querySelectorAll('.btn-jalon-del').forEach(bindDel);

  addBtn.addEventListener('click', () => {
    const tr = document.createElement('tr');
    tr.className = 'jalon-row';
    tr.innerHTML =
      '<td><input type="text" name="jalon_nom[]" class="form-control" value="" placeholder="Ex. Revue GO/NO GO, Livraison proto…"></td>' +
      '<td><input type="date" name="jalon_date[]" class="form-control" value=""></td>' +
      '<td><button type="button" class="btn-sf-del btn-jalon-del" title="Supprimer" aria-label="Supprimer">&times;</button></td>';
    body.appendChild(tr);
    bindDel(tr.querySelector('.btn-jalon-del'));
  });
})();
