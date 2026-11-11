// För webbplatser där servern (t.ex. PHP) ritar sidan från site.json i stället för JavaScript.
// Laddas tillsammans med editor.js i redigeringsläget, och uppdaterar de märkta elementen
// från window.SITE efter varje ändring (händelsen "cms:change"), så att man ser resultatet direkt:
//   data-cms="sökväg"            texten (med data-cms-multiline blir radbrytningar <br>)
//   data-cms-img="sökväg"        <img> får ny src; annars den första <img> inuti, en CSS-variabel
//                                (data-cms-bgvar="--hero-image") eller background-image
//   data-cms-alt="sökväg"        alt-texten på bilden
// Länkar och annat som servern räknar fram (t.ex. tel:-adresser) uppdateras vid nästa sidladdning.
(() => {
  const parse = (p) => p.split('.').map(s => /^\d+$/.test(s) ? +s : s);
  const get = (path) => parse(path).reduce((o, k) => (o == null ? undefined : o[k]), window.SITE);

  function setText(el, value) {
    if ('cmsMultiline' in el.dataset) {
      el.replaceChildren(...String(value).split('\n').flatMap((line, i) => i ? [document.createElement('br'), line] : [line]));
    } else if (el.textContent !== String(value)) {
      el.textContent = String(value);
    }
  }

  function setImage(el, src, alt) {
    const img = el.tagName === 'IMG' ? el : el.querySelector('img');
    const url = `url("${String(src).replace(/["\\]/g, '\\$&')}")`;
    if (el.dataset.cmsBgvar) el.style.setProperty(el.dataset.cmsBgvar, url);
    else if (img) { if (img.getAttribute('src') !== src) img.src = src; }
    else el.style.backgroundImage = url;
    if (img && typeof alt === 'string') img.alt = alt;
  }

  function sync() {
    if (!window.SITE) return;
    document.querySelectorAll('[data-cms]').forEach(el => {
      if (el.isContentEditable) return;
      const v = get(el.dataset.cms);
      if (typeof v === 'string' || typeof v === 'number') setText(el, v);
    });
    document.querySelectorAll('[data-cms-img]').forEach(el => {
      const v = get(el.dataset.cmsImg);
      if (typeof v === 'string' && v) setImage(el, v, el.dataset.cmsAlt ? get(el.dataset.cmsAlt) : undefined);
    });
  }

  window.addEventListener('cms:change', sync);
})();
