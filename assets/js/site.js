// Dragsö: mobilmenyn, filtret på boendesidan, skugga på sidhuvudet och innehåll som tonar fram vid skrollning.
// Sidan fungerar utan skriptet; allt här är förbättringar.
(() => {
  const btn = document.getElementById('menuBtn'), nav = document.getElementById('mainnav');
  if (btn && nav) btn.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Boende: visa en grupp i taget.
  const chips = document.querySelector('[data-filter-chips]');
  if (chips) {
    chips.hidden = false;
    chips.addEventListener('click', (e) => {
      const b = e.target.closest('[data-filter]');
      if (!b || document.body.classList.contains('cms-on')) return;
      chips.querySelectorAll('[data-filter]').forEach(c => c.setAttribute('aria-pressed', c === b ? 'true' : 'false'));
      document.querySelectorAll('[data-group]').forEach(g => { g.hidden = b.dataset.filter !== 'all' && g.dataset.group !== b.dataset.filter; });
    });
  }

  const head = document.querySelector('.site-head');
  if (head) addEventListener('scroll', () => head.classList.toggle('scrolled', scrollY > 8), { passive: true });

  // Tonar fram det som ligger under första skärmen. Inte i redigeringsläget, och inte för den som valt minskade rörelser.
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduced || !('IntersectionObserver' in window) || /[?&]edit\b/.test(location.search)) return;
  const io = new IntersectionObserver((entries) => entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.remove('pending'); io.unobserve(e.target); }
  }), { rootMargin: '0px 0px -8% 0px' });
  document.querySelectorAll('.section-head, .path, .tile, .season, .find > *, .zone, .stat-row, .split > *, .cabin-main section, .help, .group-head, .stay').forEach(el => {
    if (el.getBoundingClientRect().top < innerHeight) return;
    el.style.transitionDelay = (Array.prototype.indexOf.call(el.parentNode.children, el) % 4) * 80 + 'ms';
    el.classList.add('reveal', 'pending');
    io.observe(el);
  });
})();
