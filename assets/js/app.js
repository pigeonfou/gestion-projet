document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabId = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const content = document.getElementById('tab-' + tabId);
        if (content) content.classList.add('active');
    });
});
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm || 'Confirmer la suppression ?')) e.preventDefault();
    });
});
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => { alert.style.opacity='0'; alert.style.transition='opacity .4s'; setTimeout(()=>alert.remove(),400); }, 5000);
});
