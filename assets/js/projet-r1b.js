/* One calculation for both estimate and supplier rows. */
function recalcEstimationRow(row, hasCost = true) {
  const decimal = value => Math.max(0, parseFloat(String(value || '').replace(',', '.')) || 0);
  const qty = row.querySelector('.cp-qty');
  const unit = row.querySelector('.cp-unit');
  if (!qty || !unit) return 0;
  qty.hidden = !hasCost;
  qty.readOnly = unit.readOnly = !hasCost;
  row.querySelectorAll('.estimation-cost').forEach(cell => { cell.hidden = !hasCost; });
  const total = hasCost ? decimal(qty.value) * decimal(unit.value) : 0;
  const output = row.querySelector('.cp-total');
  if (output) output.value = total.toFixed(2).replace('.', ',') + ' €';
  const saved = row.querySelector('.estimation-total-input');
  if (saved) saved.value = total.toFixed(2);
  const tax = row.querySelector('.cp-taxe')?.value || 'HT';
  const label = row.querySelector('.cp-total-taxe');
  const taxInput = row.querySelector('.cp-total-taxe-input');
  if (label) label.textContent = tax;
  if (taxInput) taxInput.value = tax;
  return total;
}
function estimationCells(prefix) {
  return document.getElementById('estimation-' + prefix + '-cells').innerHTML;
}
/* JS page projet */
(function() {
  function renumberBlock(block) {
    const sfNum = block.getAttribute('data-sf-num');
    const sfId = block.getAttribute('data-sf');
    block.querySelectorAll('.st-row').forEach((row, i) => {
      const idSpan = row.querySelector('.st-id');
      if (idSpan) idSpan.textContent = 'S.T.' + sfNum + '.' + (i + 1);
      const hid = row.querySelector('input[name="st_sf[]"]');
      if (hid) hid.value = sfId;
    });
  }

  document.querySelectorAll('.st-sf-block:not(.cp-st-block)').forEach(block => {
    const body = block.querySelector('.st-body');
    if (!body) return;
    const sfNum = block.getAttribute('data-sf-num');
    const sfId = block.getAttribute('data-sf');

    function parseStCost(value) {
      return parseFloat(String(value || '').replace(',', '.')) || 0;
    }

    function recalcSfCost() {
      let ht = 0, ttc = 0;
      body.querySelectorAll('.st-row').forEach(row => {
        const hasCost = ['Matériel', 'Composant', 'Prestataire', 'PCB'].includes(row.querySelector('[name="st_type[]"]').value);
        const cost = recalcEstimationRow(row, hasCost);
        const tax = row.querySelector('.cp-taxe')?.value || 'HT';
        if (tax === 'TTC') ttc += cost; else ht += cost;
      });
      const out = block.querySelector('.sf-cost-value');
      if (out) {
        out.textContent = ht.toFixed(2).replace('.', ',') + ' € HT' +
          (ttc > 0 ? ' + ' + ttc.toFixed(2).replace('.', ',') + ' € TTC' : '');
      }
    }

    function bindDel(btn) {
      btn.addEventListener('click', () => {
        const rows = body.querySelectorAll('.st-row');
        if (rows.length <= 1) {
          const row = rows[0];
          row.querySelector('input[type="text"]').value = '';
          row.querySelector('select').value = 'Matériel';
          const cost = row.querySelector('.cp-unit');
          if (cost) cost.value = '0';
          row.querySelector('.cp-qty').value = '1';
          row.querySelector('[name="st_variation[]"]').value = 'Moyen';
          const delay = row.querySelector('.st-delay');
          if (delay) delay.value = '';
          const tax = row.querySelector('.cp-taxe');
          if (tax) tax.value = 'HT';
          renumberBlock(block);
          recalcSfCost();
          return;
        }
        btn.closest('.st-row').remove();
        renumberBlock(block);
        recalcSfCost();
      });
    }
    body.querySelectorAll('.btn-st-del').forEach(bindDel);

    const addBtn = block.querySelector('.btn-st-add');
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        const i = body.querySelectorAll('.st-row').length;
        const tr = document.createElement('tr');
        tr.className = 'st-row';
        tr.innerHTML =
          '<td><span class="st-id">S.T.' + sfNum + '.' + (i + 1) + '</span>' +
          '<input type="hidden" name="st_sf[]" value="' + sfId + '"></td>' +
          '<td><input type="text" name="st_description[]" class="form-control" value="" placeholder="Description technique…"></td>' +
          '<td><select name="st_type[]" class="form-control">' +
            '<option value="Matériel" selected>Matériel</option>' +
            '<option value="Composant">Composant</option>' +
            '<option value="Prestataire">Prestataire</option>' +
            '<option value="Logiciel">Logiciel</option>' +
            '<option value="3D">3D</option>' +
            '<option value="PCB">PCB</option>' +
          '</select></td>' +
          estimationCells('st') +
          '<td><input type="number" min="0" step="0.01" name="st_delai_jours[]" class="form-control st-delay" aria-label="Délai estimé en jours"></td>' +
          '<td><button type="button" class="btn-sf-del btn-st-del" title="Supprimer">&times;</button></td>';
        body.appendChild(tr);
        bindDel(tr.querySelector('.btn-st-del'));
        renumberBlock(block);
        recalcSfCost();
      });
    }

    body.addEventListener('input', e => {
      if (e.target.matches('.cp-unit, .cp-qty')) recalcSfCost();
    });
    body.addEventListener('change', e => {
      if (e.target.matches('.cp-taxe, [name="st_type[]"]')) recalcSfCost();
    });
    recalcSfCost();

  });
})();


(function() {
  function renumber(block) {
    const prefix = block.getAttribute('data-prefix') || 'X';
    block.querySelectorAll('.cp-row').forEach((row, i) => {
      const idSpan = row.querySelector('.cp-id');
      if (idSpan) idSpan.textContent = prefix + '.' + (i + 1);
    });
  }

  function parseDecimal(value) {
    return parseFloat(String(value || '').replace(',', '.')) || 0;
  }

  function recalcBlock(block) {
    let ht = 0, ttc = 0;
    block.querySelectorAll('.cp-row').forEach(row => {
      const total = parseDecimal((row.querySelector('.cp-total') || {}).value);
      const tax = (row.querySelector('select[name="cp_cout_unitaire_taxe[]"]') || {}).value || 'HT';
      if (tax === 'TTC') ttc += total; else ht += total;
    });
    const out = block.querySelector('.cp-st-cost-value');
    if (out) out.textContent = ht.toFixed(2).replace('.', ',') + ' € HT' + (ttc > 0 ? ' + ' + ttc.toFixed(2).replace('.', ',') + ' € TTC' : '');
  }

  function recalc(row) {
    recalcEstimationRow(row);
    const block = row.closest('.cp-st-block');
    if (block) recalcBlock(block);
  }

  document.querySelectorAll('.cp-st-block').forEach(block => {
    const body = block.querySelector('.cp-body');
    const type = block.getAttribute('data-st-type');
    const stId = block.getAttribute('data-st-id');
    const prefix = block.getAttribute('data-prefix');

    function bindRow(row) {
      const del = row.querySelector('.btn-cp-del');
      if (del) {
        del.addEventListener('click', () => {
          const rows = body.querySelectorAll('.cp-row');
          if (rows.length <= 1) {
            row.querySelectorAll('input:not([type="hidden"]), select').forEach(el => {
              if (el.tagName === 'SELECT') {
                if (el.options.length) el.selectedIndex = 0;
              } else if (!el.readOnly) {
                el.value = '';
              }
            });
            const tot = row.querySelector('.cp-total');
            if (tot) tot.value = '';
            renumber(block);
            recalcBlock(block);
            return;
          }
          row.remove();
          renumber(block);
          recalcBlock(block);
        });
      }
      const qty = row.querySelector('.cp-qty');
      const unit = row.querySelector('.cp-unit');
      if (qty) qty.addEventListener('input', () => recalc(row));
      if (unit) unit.addEventListener('input', () => recalc(row));
      const unitTax = row.querySelector('select[name="cp_cout_unitaire_taxe[]"]');
      if (unitTax) unitTax.addEventListener('change', () => recalc(row));
    }

    body.querySelectorAll('.cp-row').forEach(row => {
      bindRow(row);
      if (['Matériel', 'Composant', 'Prestataire', 'PCB'].includes(type)) recalc(row);
    });
    if (['Matériel', 'Composant', 'Prestataire', 'PCB'].includes(type)) {
      body.addEventListener('input', e => {
        const row = e.target.closest('.cp-row');
        if (row && (e.target.matches('.cp-qty') || e.target.matches('.cp-unit'))) recalc(row);
      });
      body.addEventListener('change', e => {
        const row = e.target.closest('.cp-row');
        if (row && e.target.matches('select[name="cp_cout_unitaire_taxe[]"]')) recalc(row);
      });
      recalcBlock(block);
    }

    const addBtn = block.querySelector('.btn-cp-add');
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        const i = body.querySelectorAll('.cp-row').length;
        const tr = document.createElement('tr');
        tr.className = 'cp-row';
        if (['Matériel', 'Composant', 'Prestataire', 'PCB'].includes(type)) {
          tr.innerHTML =
            '<td><span class="cp-id">' + prefix + '.' + (i+1) + '</span>' +
            '<input type="hidden" name="cp_st_id[]" value="' + stId + '">' +
            '<input type="hidden" name="cp_type[]" value="' + type + '">' +
            '<input type="hidden" name="cp_duree[]" value="">' +
            (type === 'PCB' ? '<label>Affectation PCB</label><select name="cp_affectation[]" class="form-control"><option value="">—</option>' + (window.PROJECTFLOW_USERS || []).map(u => '<option value="' + u.replace(/"/g, '&quot;') + '">' + u.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</option>').join('') + '</select>' : '<input type="hidden" name="cp_affectation[]" value="">') + '</td>' +
            '<td><input type="text" name="cp_designation[]" class="form-control" value=""></td>' +
            '<td><input type="text" name="cp_reference[]" class="form-control" value=""></td>' +
            '<td><input type="text" name="cp_fournisseur[]" class="form-control" value=""></td>' +
            estimationCells('cp') +
            '<td><input type="number" min="0" step="0.01" name="cp_delai_jours[]" class="form-control" aria-label="Délai en jours"></td>' +
            '<td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>';
        } else {
          const userOpts = window.PROJECTFLOW_USERS || [];
          let opts = '<option value="">—</option>';
          (userOpts || []).forEach(u => { opts += '<option value="' + u.replace(/"/g, '&quot;') + '">' + u + '</option>'; });
          tr.innerHTML =
            '<td><span class="cp-id">' + prefix + '.' + (i+1) + '</span>' +
            '<input type="hidden" name="cp_st_id[]" value="' + stId + '">' +
            '<input type="hidden" name="cp_type[]" value="' + type + '">' +
            '<input type="hidden" name="cp_designation[]" value=""><input type="hidden" name="cp_reference[]" value="">' +
            '<input type="hidden" name="cp_fournisseur[]" value=""><input type="hidden" name="cp_quantite[]" value="">' +
            '<input type="hidden" name="cp_cout_unitaire[]" value=""><input type="hidden" name="cp_cout_unitaire_taxe[]" value="HT">' +
            '<input type="hidden" name="cp_cout_total_taxe[]" value="HT"></td>' +
            '<td><select name="cp_affectation[]" class="form-control">' + opts + '</select><input type="hidden" name="cp_duree[]" value=""></td>' +
            '<td>' + document.getElementById('estimation-cp-variation').innerHTML + '</td>' +
            '<td><input type="number" min="0" step="0.01" name="cp_delai_jours[]" class="form-control" aria-label="Délai en jours"></td>' +
            '<td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>';
        }
        body.appendChild(tr);
        bindRow(tr);
        renumber(block);
        recalcEstimationRow(tr);
        recalcBlock(block);
      });
    }
  });
})();

/* Follow the actual history boundary, including steps with short content. */
(function () {
  const scroll = document.querySelector('.r1b-process-scroll');
  const tools = scroll?.querySelector('.r1b-step-tools');
  const history = scroll?.querySelector('.history-step-panel');
  if (!tools || !history) return;
  let shift = 0;
  let pending = false;
  const fields = [...tools.querySelectorAll('textarea')].filter(field => !field.closest('dialog'));
  let fitKey = '';
  function fitFields() {
    const style = getComputedStyle(scroll);
    const available = scroll.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
    const key = [available, tools.clientWidth, ...fields.map(field => field.value)].join('|');
    if (key === fitKey) return;
    fitKey = key;
    tools.classList.remove('r1b-tools-compact');
    fields.forEach(field => { field.style.height = '38px'; });
    if (tools.scrollHeight + 2 > available) tools.classList.add('r1b-tools-compact');
    const extra = Math.max(0, available - tools.scrollHeight - 2);
    const wants = fields.map(field => Math.max(0, Math.min(220, field.scrollHeight + 2) - 38));
    const total = wants.reduce((sum, height) => sum + height, 0);
    fields.forEach((field, index) => {
      field.style.height = (38 + (total ? Math.min(extra, total) * wants[index] / total : 0)) + 'px';
    });
  }
  function update() {
    pending = false;
    fitFields();
    const naturalTop = tools.getBoundingClientRect().top - shift;
    const pinnedTop = scroll.getBoundingClientRect().top + scroll.clientTop +
      parseFloat(getComputedStyle(scroll).paddingTop);
    const boundary = history.getBoundingClientRect().top;
    shift = Math.max(0, Math.min(pinnedTop - naturalTop,
      boundary - naturalTop - tools.getBoundingClientRect().height));
    tools.style.transform = `translateY(${shift}px)`;
  }
  function schedule() {
    if (!pending) { pending = true; requestAnimationFrame(update); }
  }
  scroll.addEventListener('scroll', schedule, { passive: true });
  tools.addEventListener('input', schedule);
  window.addEventListener('resize', schedule);
  const observer = new ResizeObserver(schedule);
  observer.observe(scroll);
  observer.observe(tools);
  observer.observe(scroll.querySelector('.r1b-step-workspace'));
  update();
})();
