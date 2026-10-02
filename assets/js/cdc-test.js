(() => {
 const form=document.getElementById('cdc-test-form'),state=document.getElementById('cdc-save-state');
 if(!form)return;
 let dirty=false;
 form.addEventListener('input',()=>{dirty=true;state.textContent='Modifications non enregistrées';});
 form.addEventListener('submit',()=>{dirty=false;});
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
