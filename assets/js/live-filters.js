(() => {
  'use strict';
  document.querySelectorAll('form[data-live-filters]').forEach(form => {
    if (form.dataset.liveBound) return;
    form.dataset.liveBound = '1';
    let timer, controller, generation = 0;
    const resultKey = form.classList.contains('decision-history-filters') ? 'history' : 'tasks';
    const selector = `[data-live-results="${resultKey}"]`;
    const feedback = document.createElement('p');
    feedback.setAttribute('role', 'status');
    feedback.style.cssText = 'font-size:.8rem;color:#617087;margin:6px 0';
    form.after(feedback);
    const refresh = async (pageUrl) => {
      clearTimeout(timer);
      controller?.abort();
      controller = new AbortController();
      const current = ++generation;
      const target = document.querySelector(selector);
      if (!target) return;
      const url = pageUrl ? new URL(pageUrl, location.href) : new URL(form.action, location.href);
      if (!pageUrl) url.search = new URLSearchParams(new FormData(form)).toString();
      target.setAttribute('aria-busy', 'true');
      feedback.textContent = 'Mise à jour…';
      try {
        const response = await fetch(url, {signal: controller.signal, credentials: 'same-origin'});
        if (!response.ok) throw new Error('request');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = doc.querySelector(selector);
        if (!next || !doc.querySelector('form[data-live-filters]')) throw new Error('session');
        if (current !== generation) return;
        target.replaceChildren(...next.childNodes);
        history.replaceState(null, '', url);
        feedback.textContent = '';
      } catch (error) {
        if (error.name !== 'AbortError' && current === generation) feedback.textContent = 'Mise à jour impossible. Modifiez un critère pour réessayer, ou rechargez la page si votre session a expiré.';
      } finally {
        if (current === generation) target.removeAttribute('aria-busy');
      }
    };
    const schedule = (delay) => {
      clearTimeout(timer);
      // Invalidate and cancel immediately so an older response cannot overwrite new criteria.
      generation++;
      controller?.abort();
      timer = setTimeout(() => refresh(), delay);
    };
    form.addEventListener('submit', event => { event.preventDefault(); refresh(); });
    form.addEventListener('input', event => {
      if (event.isComposing) return;
      schedule(event.target.type === 'search' || event.target.type === 'text' ? 250 : 0);
    });
    form.addEventListener('change', () => schedule(0));
    form.addEventListener('compositionend', () => schedule(250));
    document.addEventListener('click', event => {
      const link = event.target.closest('.history-pagination a');
      if (resultKey !== 'history' || !link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      refresh(link.href);
    });
  });
})();
