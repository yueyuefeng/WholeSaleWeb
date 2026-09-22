(() => {
  const button = document.querySelector('.menu-toggle');
  const nav = document.getElementById('primary-nav');
  if (!button || !nav) return;
  const close = () => { button.setAttribute('aria-expanded', 'false'); nav.classList.remove('is-open'); };
  button.addEventListener('click', () => {
    const expanded = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(expanded));
    nav.classList.toggle('is-open', expanded);
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') { close(); button.focus(); } });
  document.addEventListener('click', event => { if (!nav.contains(event.target) && !button.contains(event.target)) close(); });
})();
