(() => {
  const config = window.swSelection;
  if (!config) return;
  const t = config.strings;
  function download(text) {
    const url = URL.createObjectURL(new Blob([text], {type:'text/plain;charset=utf-8'}));
    const a = document.createElement('a'); a.href = url; a.download = 'Shadowalker-plan.txt'; a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }
  document.querySelectorAll('[data-download-summary]').forEach(button => button.addEventListener('click', () => download(button.closest('.sw-plan-result').querySelector('pre').textContent)));
  const buttons = [...document.querySelectorAll('[data-compare-id]')];
  if (buttons.length) {
    let selected = [];
    try { const stored = JSON.parse(localStorage.getItem('sw-compare-v1')); if (Array.isArray(stored)) selected = stored.filter(x => /^\d+$/.test(x?.id) && ['cart','boat','bike','kit'].includes(x.kind)).slice(0,3); } catch (_) {}
    if (selected.some(x => x.kind !== selected[0].kind)) selected = [];
    const bar = document.createElement('div'); bar.className = 'sw-compare-bar';
    const link = document.createElement('a'), clear = document.createElement('button'), status = document.createElement('span');
    status.setAttribute('role','status'); clear.type = 'button'; clear.textContent = t.clear; bar.append(link,clear,status); document.body.append(bar);
    function render(message = '') {
      try { localStorage.setItem('sw-compare-v1',JSON.stringify(selected)); } catch (_) {}
      buttons.forEach(b => b.setAttribute('aria-pressed', String(selected.some(x => x.id === b.dataset.compareId))));
      bar.hidden = !selected.length && !message; const url = new URL(config.compare); url.searchParams.set('ids',selected.map(x => x.id).join(','));
      link.href = url; link.textContent = `${t.compare} (${selected.length}/3) ↗`; status.textContent = message;
    }
    clear.addEventListener('click', () => {selected = []; render();});
    buttons.forEach(button => button.addEventListener('click', () => {
      const id = button.dataset.compareId, kind = button.dataset.kind;
      if (selected.some(x => x.id === id)) {selected = selected.filter(x => x.id !== id); render(t.removed);}
      else if (selected.length >= 3 || selected.some(x => x.kind !== kind)) render(t.limit);
      else {selected.push({id,kind}); render(t.added);}
    })); render();
  }
  document.querySelectorAll('.sw-selection-form').forEach(form => {
    const status = form.querySelector('[role=status]'), result = form.parentElement.querySelector('.sw-plan-result'), button = form.querySelector('[type=submit]');
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (button.disabled) return;
      const fields = new FormData(form), body = {product_id:form.dataset.product,mode:form.dataset.mode,country:fields.get('country'),quantity:fields.get('quantity'),choices:{},measurements:{},bicycle:{}};
      for (const [key,value] of fields) { const match = key.match(/^(choices|measurements|bicycle)\[([a-z0-9_-]+)\]$/); if (match) body[match[1]][match[2]] = value; }
      button.disabled = true; status.textContent = t.saving; result.hidden = true;
      const controller = new AbortController(), timer = setTimeout(() => controller.abort(),20000);
      try {
        const response = await fetch(config.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{'Content-Type':'application/json','X-Shadowalker-Nonce':config.nonce,'X-WP-Nonce':config.restNonce},body:JSON.stringify(body)});
        const data = await response.json(); if (!response.ok) throw new Error(data.code);
        result.replaceChildren(); const heading = document.createElement('h3'), summary = document.createElement('pre'), actions = document.createElement('div'); actions.className = 'sw-selection-actions';
        heading.textContent = t.saved; summary.textContent = data.summary.join('\n');
        for (const [label,url] of [[t.chat,data.chat_url],[t.share,data.url]]) { const a = document.createElement('a'); a.textContent = label + ' ↗'; a.href = url; a.className = 'button'; actions.append(a); }
        const save = document.createElement('button'); save.type = 'button'; save.textContent = t.download; save.addEventListener('click', () => download(summary.textContent)); actions.append(save);
        result.append(heading,summary,actions); result.hidden = false; status.textContent = t.saved;
        result.setAttribute('tabindex','-1'); result.focus({preventScroll:true}); result.scrollIntoView({block:'nearest',behavior:'auto'});
      } catch (error) { status.textContent = error.message === 'selection_conflict' ? t.conflict : t.error; }
      finally { clearTimeout(timer); button.disabled = false; }
    });
  });
})();
