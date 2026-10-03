(() => {
  'use strict';
  document.querySelectorAll('form[data-live-filters]').forEach(form => {
    if (form.dataset.liveBound) return;
    form.dataset.liveBound = '1';
    let timer, controller, generation = 0;
    const resultKey = form.classList.contains('decision-history-filters') ? 'history' : 'tasks';
    const selector = `[data-live-results="${resultKey}"]`;
    const highlight = () => {
      const target = document.querySelector(selector);
      if (!target) return;
      target.querySelectorAll('mark.search-match').forEach(mark => mark.replaceWith(document.createTextNode(mark.textContent)));
      target.normalize();
      const query = form.querySelector('input[type="search"]')?.value.trim();
      if (!query) return;
      const color = /^#[0-9a-f]{6}$/i.test(form.dataset.highlightColor || '') ? form.dataset.highlightColor : '#ffad42';
      const rgb = [1, 3, 5].map(i => parseInt(color.slice(i, i + 2), 16) / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
      const foreground = .2126 * rgb[0] + .7152 * rgb[1] + .0722 * rgb[2] > .179 ? '#111111' : '#ffffff';
      const pattern = new RegExp(query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'giu');
      target.querySelectorAll(resultKey === 'tasks' ? '.r1b-kanban-card' : '.decision-history-table tbody').forEach(root => {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) {
          if (!walker.currentNode.parentElement.closest('script, style, select, option, textarea, button, mark')) nodes.push(walker.currentNode);
        }
        nodes.forEach(node => {
          const value = node.textContent;
          pattern.lastIndex = 0;
          const matches = [...value.matchAll(pattern)];
          if (!matches.length) return;
          const fragment = document.createDocumentFragment();
          let offset = 0;
          matches.forEach(match => {
            fragment.append(document.createTextNode(value.slice(offset, match.index)));
            const mark = document.createElement('mark');
            mark.className = 'search-match';
            mark.style.backgroundColor = color;
            mark.style.color = foreground;
            mark.style.borderRadius = '2px';
            mark.textContent = match[0];
            fragment.append(mark);
            offset = match.index + match[0].length;
          });
          fragment.append(document.createTextNode(value.slice(offset)));
          node.replaceWith(fragment);
        });
      });
    };
    highlight();
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
        highlight();
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
