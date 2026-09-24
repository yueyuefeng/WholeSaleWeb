(() => {
  document.querySelectorAll('[data-chat-config]').forEach(root => {
    const config = JSON.parse(root.dataset.chatConfig), t = config.strings;
    const form = root.querySelector('form'), input = form.querySelector('textarea');
    const log = root.querySelector('[role=log]'), status = root.querySelector('[role=status]');
    const handoff = root.querySelector('[data-handoff]'), remove = root.querySelector('[data-delete]'), start = root.querySelector('[data-new]');
    const key = `shadowalker-chat-v1:${config.locale}:${config.product_id}${config.plan ? ':' + config.plan : ''}`;
    let session = null, busy = false, polling = false, pending = null, stopped = false, generation = 0;
    const rendered = new Set();
    try { const value = JSON.parse(sessionStorage.getItem(key)); if (Number.isInteger(value?.id) && /^[a-f0-9]{64}$/.test(value?.token)) session = value; } catch (_) { /* Private browsing may disable storage. */ }
    const notify = (text, error = false) => { status.textContent = text; status.classList.toggle('is-error', error); };
    const controls = value => { busy = value; if (value) generation++; root.querySelectorAll('button').forEach(button => { button.disabled = value; }); input.readOnly = value; };
    const save = () => { try { if (session) sessionStorage.setItem(key, JSON.stringify(session)); else sessionStorage.removeItem(key); } catch (_) {} };
    async function request(suffix, method = 'GET', body) {
      const url = new URL(config.base);
      if (url.searchParams.has('rest_route')) url.searchParams.set('rest_route', url.searchParams.get('rest_route').replace(/\/$/, '') + suffix);
      else url.pathname = url.pathname.replace(/\/$/, '') + suffix;
      const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 20000);
      try {
        const response = await fetch(url, {method, credentials:'same-origin', cache:'no-store', signal:controller.signal,
          headers:{'Content-Type':'application/json', 'X-Shadowalker-Nonce':config.nonce, 'X-WP-Nonce':config.restNonce, ...(session ? {'X-Shadowalker-Token':session.token} : {})},
          ...(body ? {body:JSON.stringify(body)} : {})});
        const data = await response.json();
        if (!response.ok) throw Object.assign(new Error(data.code || 'network'), {code:data.code});
        return data;
      } finally { clearTimeout(timer); }
    }
    function failure(error) {
      let text = t.error;
      if (error.code === 'selection_plan') { stopped = true; text = t.plan; }
      if (error.code === 'chat_rate' || error.code === 'chat_busy') text = t.rate;
      if (error.code === 'chat_session' || error.code === 'chat_full') { stopped = true; start.hidden = false; text = error.code === 'chat_full' ? t.full : t.expired; }
      if (error.code === 'chat_nonce' || error.code === 'rest_cookie_invalid_nonce') { stopped = true; text = t.reload; }
      notify(text, true);
    }
    async function ensureSession() {
      if (!session) { session = await request('', 'POST', {locale:config.locale, product_id:config.product_id, plan:config.plan || ''}); save(); }
    }
    function render(data) {
      const atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 70;
      let changed = false;
      for (const message of data.messages) {
        if (rendered.has(message.id)) continue;
        const role = ['visitor','assistant','agent'].includes(message.role) ? message.role : 'agent';
        const bubble = document.createElement('div'); bubble.className = 'sw-chat-message ' + role;
        const name = document.createElement('span'); name.textContent = t[role];
        const text = document.createElement('p'); text.textContent = message.text;
        const time = document.createElement('time'); time.dateTime = message.time;
        const date = new Date(message.time); time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString(config.locale.replace('_','-'), {hour:'2-digit',minute:'2-digit'});
        bubble.append(name, text, time); log.append(bubble); rendered.add(message.id); changed = true;
      }
      if (changed && (atBottom || busy)) log.scrollTop = log.scrollHeight;
      const last = data.messages[data.messages.length - 1];
      notify(data.mode === 'bot' ? t.ready : last?.role === 'agent' ? t.replied : t.saved);
    }
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (busy || stopped || !input.value.trim()) return;
      const text = input.value.trim();
      if (!pending || pending.text !== text) pending = {text, request_id:crypto.randomUUID()};
      controls(true); notify(t.sending);
      try { await ensureSession(); render(await request(`/${session.id}/messages`, 'POST', pending)); input.value = ''; pending = null; }
      catch (error) { failure(error); }
      finally { controls(false); input.focus(); }
    });
    input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); } });
    root.querySelectorAll('.sw-chat-topic').forEach(button => button.addEventListener('click', () => { input.value = button.textContent.replace('↗','').trim(); input.focus(); }));
    handoff.addEventListener('click', async () => {
      if (busy || stopped) return;
      controls(true);
      try { await ensureSession(); render(await request(`/${session.id}/handoff`, 'POST', {})); notify(t.human); }
      catch (error) { failure(error); } finally { controls(false); }
    });
    function reset() { generation++; session = null; pending = null; stopped = false; save(); rendered.clear(); log.querySelectorAll('.sw-chat-message:not(.sw-chat-welcome)').forEach(item => item.remove()); start.hidden = true; }
    remove.addEventListener('click', async () => {
      if (busy || !session || !window.confirm(t.confirm)) return;
      controls(true);
      try { await request(`/${session.id}`, 'DELETE'); reset(); notify(t.deleted); }
      catch (error) { failure(error); } finally { controls(false); }
    });
    start.addEventListener('click', () => { reset(); notify(''); input.focus(); });
    async function poll() {
      if (!session || busy || polling || stopped || document.hidden) return;
      polling = true;
      const current = generation;
      try { const data = await request(`/${session.id}`); if (current === generation && session && !busy) render(data); }
      catch (error) { if (current === generation && session && !busy) failure(error); } finally { polling = false; }
    }
    poll();
    let interval = setInterval(poll, 6000);
    window.addEventListener('pagehide', () => clearInterval(interval));
    window.addEventListener('pageshow', event => { if (event.persisted) { clearInterval(interval); interval = setInterval(poll, 6000); poll(); } });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  });
})();
