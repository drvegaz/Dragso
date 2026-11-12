// Formulär från CMS:et på statiska sidor (sidor som ritas med PHP använder render.php i stället).
//   <script src="cms/modules/formular/form.js"></script>
//   <div id="kontakt"></div>
//   <script>cmsForm(document.getElementById('kontakt'), { id: 'kontakt', buttonClass: 'btn' });</script>
// Samma uppmärkning som render.php. Listval som hämtas från webbplatsen ("dynamic") stöds inte här.
//
// Filen gör också att foton krymps i webbläsaren innan de skickas (högst 1920 px bred, JPEG), så att
// stora mobilbilder går snabbt att ladda upp och inte stoppas av storleksgränsen. render.php läser in den.
(function () {
  const IMG_WIDTH = 1920;

  // Krymper ett foto. Går det inte (okänt format, gammal webbläsare) skickas originalet, och servern krymper det.
  function shrink(file) {
    return new Promise((resolve) => {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.URL || !HTMLCanvasElement.prototype.toBlob) return resolve(file);
      const url = URL.createObjectURL(file), img = new Image();
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      // Webbläsaren vänder bilden rätt efter EXIF när den ritas, så den nya filen behöver ingen EXIF.
      img.onload = () => {
        URL.revokeObjectURL(url);
        const s = Math.min(1, IMG_WIDTH / img.naturalWidth);
        if (s === 1 && file.size < 1.5e6) return resolve(file);
        const c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(img.naturalWidth * s));
        c.height = Math.max(1, Math.round(img.naturalHeight * s));
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob((b) => resolve(b && b.size < file.size
          ? new File([b], file.name.replace(/\.[^.]*$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.85);
      };
      img.src = url;
    });
  }

  document.addEventListener('change', async (ev) => {
    const input = ev.target;
    if (!input.matches || !input.matches('.cms-form input[type=file]') || !input.files.length || !window.DataTransfer) return;
    const btn = input.form && input.form.querySelector('[type=submit]');
    if (btn) btn.disabled = true;
    try {
      const dt = new DataTransfer();
      for (const f of input.files) dt.items.add(await shrink(f));
      input.files = dt.files;
    } catch (e) { /* originalet skickas */ }
    finally { if (btn) btn.disabled = false; }
  });

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const MAX = { text: 200, textarea: 3000, email: 200, tel: 30 };

  function field(id, x) {
    const fid = `f-${id}-${x.id}`, req = x.required ? ' required' : '';
    const help = x.help ? `<small class="help" id="${fid}-help">${esc(x.help)}</small>` : '';
    const desc = x.help ? ` aria-describedby="${fid}-help"` : '';
    const ph = x.placeholder ? ` placeholder="${esc(x.placeholder)}"` : '';
    const name = ` id="${fid}" name="${esc(x.id)}"`;
    let input;
    if (x.type === 'textarea') input = `<textarea${name}${req}${desc}${ph} maxlength="${MAX.textarea}"></textarea>`;
    else if (x.type === 'select') input = `<select${name}${req}${desc}><option value="">${esc(x.placeholder || 'Välj …')}</option>`
      + x.options.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('') + '</select>';
    else if (x.type === 'image') input = `<input type="file"${name}${req}${desc} accept="image/jpeg,image/png,image/webp">`;
    else input = `<input type="${x.type === 'email' || x.type === 'tel' ? x.type : 'text'}"${name}${req}${desc}${ph}`
      + (x.type === 'email' || x.type === 'tel' ? ` autocomplete="${x.type}"` : '') + ` maxlength="${MAX[x.type]}">`;
    return `<div class="field field-${x.type}"><label for="${fid}">${esc(x.label)}${x.required ? '' : ' <span class="optional">(frivilligt)</span>'}</label>${input}${help}</div>`;
  }

  window.cmsForm = async function (el, opt) {
    const base = (opt.base || 'cms/').replace(/\/?$/, '/');
    let d;
    try {
      const r = await fetch(base + 'api.php?action=form&id=' + encodeURIComponent(opt.id));
      d = await r.json();
      if (!r.ok) throw new Error(d.error);
    } catch (e) { el.innerHTML = ''; if (opt.onEmpty) opt.onEmpty(); return; }
    const f = d.form, id = f.id;
    el.innerHTML = `<div class="cms-form" id="form-${esc(id)}"><div class="errors" role="alert" hidden></div>
      <form method="post"><input type="hidden" name="cms_form" value="${esc(id)}"><input type="hidden" name="cms_t" value="${esc(d.token)}">
      <div class="field hp" aria-hidden="true"><label for="f-${id}-hp">Lämna tomt</label><input type="text" id="f-${id}-hp" name="webbplats" tabindex="-1" autocomplete="off"></div>
      ${f.fields.map(x => field(id, x)).join('')}
      <button type="submit" class="${esc(opt.buttonClass || 'btn')}">${esc(f.button)}</button></form></div>`;
    const form = el.querySelector('form'), errors = el.querySelector('.errors'), btn = form.querySelector('button[type=submit]');
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      btn.disabled = true;
      try {
        const r = await fetch(base + 'api.php?action=form_submit', { method: 'POST', body: new FormData(form) });
        const res = await r.json().catch(() => ({ errors: ['Oväntat svar från servern. Försök igen.'] }));
        if (r.ok && res.ok) {
          el.querySelector('.cms-form').innerHTML = `<div class="success" role="status">${esc(res.message).replace(/\n/g, '<br>')}</div>`;
          return;
        }
        if (res.token) form.elements.cms_t.value = res.token;
        errors.innerHTML = '<ul>' + (res.errors || [res.error || 'Något gick fel.']).map(e => `<li>${esc(e)}</li>`).join('') + '</ul>';
        errors.hidden = false;
        errors.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } catch (e) {
        errors.innerHTML = '<ul><li>Kunde inte skicka formuläret. Kontrollera anslutningen och försök igen.</li></ul>';
        errors.hidden = false;
      } finally { btn.disabled = false; }
    });
  };
})();
