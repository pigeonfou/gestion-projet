(() => {
 const dialog=document.getElementById('notes-dialog'), choice=document.getElementById('note-choice');
 if(!dialog||!choice)return;
 const content=document.getElementById('note-content');
 const update=()=>{const o=choice.selectedOptions[0],isNew=choice.value==='0',deleted=o.dataset.deleted==='1';content.value=o.dataset.content||'';content.readOnly=deleted;document.getElementById('note-revision').value=o.dataset.revision||'0';for(const [id,show] of [['note-add',isNew],['note-edit',!isNew&&!deleted],['note-delete',!isNew&&!deleted],['note-restore',!isNew&&deleted]])document.getElementById(id).hidden=!show;};
 document.getElementById('notes-open').addEventListener('click',()=>{update();dialog.showModal();content.focus();});
 document.getElementById('notes-close').addEventListener('click',()=>dialog.close());choice.addEventListener('change',update);
 document.getElementById('note-editor').addEventListener('submit',e=>{if(['add','edit'].includes(e.submitter?.value)&&!content.value.trim()){e.preventDefault();content.focus();}});
})();
