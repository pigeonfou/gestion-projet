(() => {
 const form=document.getElementById('cdcForm');
 if(!form)return;
 const panels=[...document.querySelectorAll('.cdc-step-panel')],tabs=[...document.querySelectorAll('.cdc-stepper .step')];
 const status=document.getElementById('cdcSaveStatus');
 let dirty=false,busy=false,pendingAction='generate';
 function show(n){
  n=Math.max(1,Math.min(4,n));
  panels.forEach(p=>{const active=Number(p.dataset.panel)===n;p.classList.toggle('active',active);p.setAttribute('role','tabpanel');p.id='cdcPanel'+p.dataset.panel;p.setAttribute('aria-labelledby','cdcTab'+p.dataset.panel);});
  tabs.forEach(t=>{const active=Number(t.dataset.step)===n;t.classList.toggle('active',active);t.id='cdcTab'+t.dataset.step;t.setAttribute('role','tab');t.setAttribute('aria-controls','cdcPanel'+t.dataset.step);t.setAttribute('aria-selected',String(active));t.tabIndex=active?0:-1;});
  document.getElementById('cdcProgress').style.width=(n/4*100)+'%';
  if(n===4)refreshHistory();
 }
 async function refreshHistory(){
  const endpoint=new URL(form.dataset.validationUrl,location.href);endpoint.searchParams.set('mode','history');endpoint.searchParams.set('fragment','1');
  try{const r=await fetch(endpoint,{credentials:'same-origin'});if(r.ok&&r.headers.get('Content-Type')?.includes('text/html'))document.getElementById('cdcValidationHistory').innerHTML=await r.text();}catch{}
 }
 tabs.forEach((tab,index)=>{
  tab.addEventListener('click',()=>show(Number(tab.dataset.step)));
  tab.addEventListener('keydown',e=>{let next=index;if(e.key==='ArrowRight')next=(index+1)%tabs.length;else if(e.key==='ArrowLeft')next=(index+tabs.length-1)%tabs.length;else if(e.key==='Home')next=0;else if(e.key==='End')next=tabs.length-1;else return;e.preventDefault();show(next+1);tabs[next].focus();});
 });
 const initial=new URLSearchParams(location.search);
 show(form.dataset.inline==='1'?1:Number(initial.get('onglet')||initial.get('step')||1));
 form.addEventListener('input',()=>dirty=true);form.addEventListener('change',()=>dirty=true);
 form.addEventListener('click',e=>{if(e.target.closest('#sfAddRow,.btn-sf-del,#jalonAddRow,.btn-jalon-del'))dirty=true;});
 form.addEventListener('invalid',e=>{const panel=e.target.closest('[data-panel]');if(panel)show(Number(panel.dataset.panel));},true);
 function openValidation(mode){
  const dialog=document.getElementById('cdcValidation-'+mode),frame=dialog.querySelector('iframe');
  const endpoint=new URL(form.dataset.validationUrl,location.href);endpoint.searchParams.set('embedded','1');endpoint.searchParams.set('mode',mode);
  frame.src=endpoint.href;dialog.showModal();
 }
 document.querySelectorAll('[data-close-cdc-validation]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));
 document.querySelectorAll('.cdc-validation-dialog').forEach(d=>d.addEventListener('close',refreshHistory));
 window.addEventListener('message',e=>{const d=document.getElementById('cdcValidation-import');if(e.origin===location.origin&&e.source===d.querySelector('iframe').contentWindow&&e.data?.type==='cdc-response-imported'){d.close();status.textContent='Réponse importée et archivée.';refreshHistory();}});
 async function persist(action){
  if(busy)return;
  if(action!=='save'&&!form.reportValidity())return;
  busy=true;status.textContent='Enregistrement du CDC…';
  const controls=['saveCdcDraft','generateCdcPdf','requestCdcValidation','importCdcReply'].map(id=>document.getElementById(id)).filter(Boolean);controls.forEach(b=>b.disabled=true);
  try{
   const data=new FormData(form);data.set('form_action',action);data.set('cdc_ajax','1');
   const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});
   if(!response.headers.get('Content-Type')?.includes('application/json'))throw new Error('La session ou l’enregistrement n’a pas pu être confirmé. Votre saisie reste affichée.');
   const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'Le CDC n’a pas pu être enregistré.');
   form.querySelector('[name=cdc_revision]').value=result.revision;dirty=false;status.textContent=result.message;
   if(action==='generate'){
    let frame=document.getElementById('cdcDownloadFrame');if(!frame){frame=document.createElement('iframe');frame.id='cdcDownloadFrame';frame.hidden=true;frame.title='Téléchargement du CDC';document.body.append(frame);}frame.src=result.download_url;
   }
   if(action==='validate')openValidation('request');
  }catch(error){status.textContent=error.message;}finally{busy=false;controls.forEach(b=>b.disabled=false);}
 }
 form.addEventListener('submit',e=>{e.preventDefault();persist('save');});
 function requestAction(action){
  if(busy)return;
  if(action==='validate'&&!dirty){openValidation('request');return;}
  if(!dirty){persist(action);return;}
  pendingAction=action;
  const dialog=document.getElementById('cdcGenerateConfirm');
  document.getElementById('cdcConfirmTitle').textContent=action==='validate'?'Préparer une demande':'Générer le CDC';
  document.getElementById('cdcConfirmMessage').textContent=action==='validate'?'La demande de validation enregistrera les modifications du CDC. Continuer ?':'Générer le CDC enregistrera les modifications. Continuer ?';
  dialog.showModal();
 }
 document.getElementById('generateCdcPdf').addEventListener('click',()=>requestAction('generate'));
 document.getElementById('requestCdcValidation').addEventListener('click',()=>requestAction('validate'));
 document.getElementById('importCdcReply').addEventListener('click',()=>openValidation('import'));
 document.getElementById('cancelCdcGenerate').addEventListener('click',()=>document.getElementById('cdcGenerateConfirm').close());
 document.getElementById('confirmCdcGenerate').addEventListener('click',()=>{document.getElementById('cdcGenerateConfirm').close();persist(pendingAction);});
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
      '<td><input type="text" name="sf_description[]" class="form-control" value="" placeholder="Description de la fonction…"></td>' +
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
