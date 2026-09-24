(() => {
  const config = window.swSelection;
  if (!config) return;
  const t = config.strings;
  async function copyPlan(url, status) {
    status.replaceChildren();
    try { await navigator.clipboard.writeText(url); status.textContent = t.copied; }
    catch (_) {
      const input = document.createElement('input'); input.value = url; input.readOnly = true; input.setAttribute('aria-label',t.copy);
      status.append(document.createTextNode(t.copyFallback),input); input.focus(); input.select();
    }
  }
  document.querySelectorAll('[data-copy-plan]').forEach(button => button.addEventListener('click', () => copyPlan(button.dataset.copyPlan,button.parentElement.querySelector('.sw-copy-status'))));
  document.querySelectorAll('.sw-product-picker select,.sw-compare-picker select').forEach(select => {
    const wrapper = document.createElement('div'); wrapper.className = 'sw-searchable-picker';
    const label = document.createElement('label'), search = document.createElement('input'), status = document.createElement('span');
    label.textContent = t.search; search.type = 'search'; search.autocomplete = 'off'; search.maxLength = 100; label.append(search);
    status.setAttribute('role','status'); select.before(wrapper); wrapper.append(label,select,status);
    search.addEventListener('input', () => {
      const term = search.value.trim().toLocaleLowerCase(); let count = 0;
      [...select.options].forEach(option => { option.hidden = !!option.value && !option.selected && !option.text.toLocaleLowerCase().includes(term); if(option.value && !option.hidden && !option.disabled) count++; });
      status.textContent = count ? '' : t.noOptions;
    });
  });
  document.querySelectorAll('.sw-compare-picker').forEach(form => {
    const selects = [...form.querySelectorAll('select')], status = document.createElement('p'); status.setAttribute('role','status'); status.className = 'sw-compare-validation'; form.append(status);
    function validate(announce = false) {
      const chosen = selects.filter(s => s.value), ids = chosen.map(s => s.value), kinds = chosen.map(s => s.selectedOptions[0].dataset.kind);
      const valid = ids.length >= 2 && new Set(ids).size === ids.length && new Set(kinds).size === 1;
      selects.forEach(select => {
        const others = selects.filter(s => s !== select && s.value), kind = others[0]?.selectedOptions[0].dataset.kind;
        [...select.options].forEach(option => { option.disabled = !!option.value && (others.some(s => s.value === option.value) || (!!kind && option.dataset.kind !== kind)); });
      });
      status.textContent = announce && !valid ? t.compareHelp : ''; return valid;
    }
    form.addEventListener('change', () => validate(true)); form.addEventListener('submit', event => { if (!validate(true)) event.preventDefault(); }); validate();
  });
  document.querySelectorAll('[data-differences-only]').forEach(toggle => toggle.addEventListener('change', () => {
    const root = toggle.closest('.sw-selection'), rows = [...root.querySelectorAll('tbody tr:not([data-always-show])')];
    rows.forEach(row => { row.hidden = toggle.checked && !row.classList.contains('sw-difference'); });
    root.querySelector('.sw-difference-status').textContent = toggle.checked && rows.every(row => row.hidden) ? t.noDifference : '';
  }));
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
    const rules = JSON.parse(form.dataset.rules || '[]'), fields = [...form.querySelectorAll('[name]')];
    const draftKey = `sw-selection-draft-v1:${config.locale}:${form.dataset.mode}:${form.dataset.product}`;
    const note = document.createElement('p'), reset = document.createElement('button'); note.className = 'sw-draft-note'; note.setAttribute('role','status'); note.textContent = t.draftNote;
    reset.type = 'button'; reset.textContent = t.reset; reset.dataset.clearDraft = ''; form.append(note,reset);
    let busy = false;
    function validateRules() {
      const chosen = Object.fromEntries(fields.map(field => [field.name,field.value])); let conflict = false;
      rules.forEach(group => {
        const field = form.elements.namedItem(`choices[${group.id}]`); if (!field) return;
        const option = group.options.find(o => o.id === field.value);
        const invalid = option && (Object.entries(option.requires || {}).some(([gid,oid]) => chosen[`choices[${gid}]`] && chosen[`choices[${gid}]`] !== oid) || Object.entries(option.excludes || {}).some(([gid,oid]) => chosen[`choices[${gid}]`] === oid));
        field.setCustomValidity(invalid ? t.conflict : ''); field.setAttribute('aria-invalid', String(!!invalid)); conflict ||= !!invalid;
      }); return conflict;
    }
    try {
      const draft = JSON.parse(sessionStorage.getItem(draftKey));
      if (draft?.revision === form.dataset.revision && Number.isFinite(draft.time) && Date.now() - draft.time >= 0 && Date.now() - draft.time < 86400000 && draft.values && typeof draft.values === 'object') {
        fields.forEach(field => { const value = draft.values[field.name]; if (typeof value === 'string' && value.length <= 200 && (field.tagName !== 'SELECT' || [...field.options].some(o => o.value === value))) field.value = value; });
        note.textContent = t.draft; if (validateRules()) status.textContent = t.conflict;
      } else sessionStorage.removeItem(draftKey);
    } catch (_) { /* Storage can be unavailable; normal form submission still works. */ }
    function changed() {
      if (busy) return;
      const wasSaved = !result.hidden || status.textContent === t.changed; result.hidden = true;
      try { sessionStorage.setItem(draftKey,JSON.stringify({revision:form.dataset.revision,time:Date.now(),values:Object.fromEntries(fields.map(f => [f.name,f.value]))})); } catch (_) {}
      status.textContent = validateRules() ? t.conflict : wasSaved ? t.changed : '';
    }
    form.addEventListener('input',changed); form.addEventListener('change',changed);
    reset.addEventListener('click', () => { form.reset(); fields.forEach(f => {f.setCustomValidity(''); f.removeAttribute('aria-invalid');}); result.hidden = true; status.textContent = ''; note.textContent = t.draftNote; try {sessionStorage.removeItem(draftKey);} catch (_) {} });
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (busy) return;
      if (validateRules()) { status.textContent = t.conflict; form.reportValidity(); return; }
      const fields = new FormData(form), body = {product_id:form.dataset.product,mode:form.dataset.mode,country:fields.get('country'),quantity:fields.get('quantity'),choices:{},measurements:{},bicycle:{}};
      for (const [key,value] of fields) { const match = key.match(/^(choices|measurements|bicycle)\[([a-z0-9_-]+)\]$/); if (match) body[match[1]][match[2]] = value; }
      busy = true; const controls = [...form.elements], disabled = controls.map(control => control.disabled); controls.forEach(control => {control.disabled = true;});
      form.setAttribute('aria-busy','true'); status.textContent = t.saving; result.hidden = true;
      const controller = new AbortController(), timer = setTimeout(() => controller.abort(),20000);
      try {
        const response = await fetch(config.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{'Content-Type':'application/json','X-Shadowalker-Nonce':config.nonce,'X-WP-Nonce':config.restNonce},body:JSON.stringify(body)});
        const data = await response.json(); if (!response.ok) throw new Error(data.code);
        result.replaceChildren(); const heading = document.createElement('h3'), summary = document.createElement('pre'), actions = document.createElement('div'); actions.className = 'sw-selection-actions';
        heading.textContent = t.saved; summary.textContent = data.summary.join('\n');
        for (const [label,url] of [[t.chat,data.chat_url],[t.share,data.url]]) { const a = document.createElement('a'); a.textContent = label + ' ↗'; a.href = url; a.className = 'button'; actions.append(a); }
        const save = document.createElement('button'); save.type = 'button'; save.textContent = t.download; save.addEventListener('click', () => download(summary.textContent)); actions.append(save);
        const copy = document.createElement('button'), copyStatus = document.createElement('p'); copy.type = 'button'; copy.textContent = t.copy; copy.dataset.copyResult = ''; copyStatus.setAttribute('role','status'); copyStatus.className = 'sw-copy-status'; copy.addEventListener('click', () => copyPlan(data.url,copyStatus)); actions.append(copy);
        result.append(heading,summary,actions,copyStatus); result.hidden = false; status.textContent = t.saved;
        try {sessionStorage.removeItem(draftKey);} catch (_) {}
        result.setAttribute('tabindex','-1'); result.focus({preventScroll:true}); result.scrollIntoView({block:'nearest',behavior:'auto'});
      } catch (error) { status.textContent = error.message === 'selection_conflict' ? t.conflict : t.error; }
      finally { clearTimeout(timer); controls.forEach((control,i) => {control.disabled = disabled[i];}); busy = false; form.removeAttribute('aria-busy'); }
    });
  });
})();
