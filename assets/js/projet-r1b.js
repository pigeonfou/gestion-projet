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

    function bindDel(btn) {
      btn.addEventListener('click', () => {
        const rows = body.querySelectorAll('.st-row');
        if (rows.length <= 1) {
          const row = rows[0];
          row.querySelector('input[type="text"]').value = '';
          row.querySelector('select').value = 'Matériel';
          renumberBlock(block);
          return;
        }
        btn.closest('.st-row').remove();
        renumberBlock(block);
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
            '<option value="Logiciel">Logiciel</option>' +
            '<option value="3D">3D</option>' +
            '<option value="PCB">PCB</option>' +
          '</select></td>' +
          '<td><div class="cp-cost-cell"><input type="number" min="0" step="any" inputmode="decimal" name="st_cout_estime[]" class="form-control st-cost" value="0">' +
          '<select name="st_cout_taxe[]" class="form-control st-tax"><option value="HT">HT</option><option value="TTC">TTC</option></select></div></td>' +
          '<td><div class="cp-cost-cell"><input type="text" class="form-control st-total" value="0.00" readonly tabindex="-1"><span class="form-control st-total-tax">HT</span></div></td>' +
          '<td><button type="button" class="btn-sf-del btn-st-del" title="Supprimer">&times;</button></td>';
        body.appendChild(tr);
        bindDel(tr.querySelector('.btn-st-del'));
        renumberBlock(block);
      });
    }

    function syncStCost(row) {
      const cost = row.querySelector('.st-cost');
      const tax = row.querySelector('.st-tax');
      const total = row.querySelector('.st-total');
      const totalTax = row.querySelector('.st-total-tax');
      if (total && cost) {
        const value = parseFloat(String(cost.value || '0').replace(',', '.')) || 0;
        total.value = value.toFixed(2);
      }
      if (totalTax && tax) totalTax.textContent = tax.value;
    }
    body.addEventListener('input', e => {
      const row = e.target.closest('.st-row');
      if (row && e.target.matches('.st-cost')) syncStCost(row);
    });
    body.addEventListener('change', e => {
      const row = e.target.closest('.st-row');
      if (row && e.target.matches('.st-tax')) syncStCost(row);
    });
    body.querySelectorAll('.st-row').forEach(syncStCost);
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
    const qty = parseDecimal((row.querySelector('.cp-qty') || {}).value);
    const unit = parseDecimal((row.querySelector('.cp-unit') || {}).value);
    const tot = row.querySelector('.cp-total');
    if (tot) tot.value = (qty * unit).toFixed(2);
    const unitTax = row.querySelector('select[name="cp_cout_unitaire_taxe[]"]');
    const totalTax = row.querySelector('.cp-total-taxe');
    const totalTaxInput = row.querySelector('.cp-total-taxe-input');
    const tax = unitTax ? unitTax.value : 'HT';
    if (totalTax) totalTax.textContent = tax;
    if (totalTaxInput) totalTaxInput.value = tax;
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
      if (type === 'Matériel') recalc(row);
    });
    if (type === 'Matériel') {
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
        if (type === 'Matériel') {
          tr.innerHTML =
            '<td><span class="cp-id">' + prefix + '.' + (i+1) + '</span>' +
            '<input type="hidden" name="cp_st_id[]" value="' + stId + '">' +
            '<input type="hidden" name="cp_type[]" value="Matériel"></td>' +
            '<td><input type="text" name="cp_designation[]" class="form-control" value=""></td>' +
            '<td><input type="text" name="cp_reference[]" class="form-control" value=""></td>' +
            '<td><input type="text" name="cp_fournisseur[]" class="form-control" value=""></td>' +
            '<td><input type="number" step="any" min="0" name="cp_quantite[]" class="form-control cp-qty" value=""></td>' +
            '<td><div class="cp-cost-cell"><input type="number" step="any" min="0" inputmode="decimal" name="cp_cout_unitaire[]" class="form-control cp-unit" value="">' +
            '<select name="cp_cout_unitaire_taxe[]" class="form-control cp-taxe"><option value="HT" selected>HT</option><option value="TTC">TTC</option></select></div></td>' +
            '<td><div class="cp-cost-cell"><input type="text" class="form-control cp-total" value="" readonly tabindex="-1">' +
            '<span class="form-control cp-total-taxe">HT</span><input type="hidden" name="cp_cout_total_taxe[]" value="HT" class="cp-total-taxe-input"></div>' +
            '<input type="hidden" name="cp_affectation[]" value=""><input type="hidden" name="cp_duree[]" value=""><input type="hidden" name="cp_variation[]" value=""></td>' +
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
            '<td><select name="cp_affectation[]" class="form-control">' + opts + '</select></td>' +
            '<td><input type="text" name="cp_duree[]" class="form-control" value="" placeholder="ex. 3 j"></td>' +
            '<td><select name="cp_variation[]" class="form-control"><option value="Forte">Forte</option><option value="Moyenne" selected>Moyenne</option><option value="Faible">Faible</option></select></td>' +
            '<td><button type="button" class="btn-sf-del btn-cp-del" title="Supprimer">&times;</button></td>';
        }
        body.appendChild(tr);
        bindRow(tr);
        renumber(block);
        recalcBlock(block);
      });
    }
  });
})();
