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

// User menu dropdown
const userBtn = document.getElementById('userMenuBtn');
const userMenu = document.getElementById('userMenu');
if (userBtn && userMenu) {
    userBtn.addEventListener('click', e => {
        e.stopPropagation();
        userMenu.classList.toggle('show');
        userBtn.setAttribute('aria-expanded', userMenu.classList.contains('show'));
    });
    document.addEventListener('click', () => userMenu.classList.remove('show'));
}
