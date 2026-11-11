// Redigeringsläge (cms-core). Laddas av webbplatsens sidor när adressen innehåller ?edit.
// Sidor som ritas av servern (PHP) laddar också server-sync.js, som uppdaterar sidan efter varje ändring.
// Element med data-cms="sökväg" blir redigerbar text, data-cms-img="sökväg" blir utbytbar bild.
// Strukturen är låst: bara befintliga värden kan ändras, inget kan läggas till eller tas bort.
// Undantag: länklistorna i config.php ('link_lists') – där kan länkar läggas till, ändras, flyttas och tas bort
// (data-cms-addlink="<sökväg till ägaren>" ger knappen för en ny länk).
// Efter varje ändring skickas händelsen "cms:change" på window, så att sidan kan rita om sig från window.SITE.
(() => {
  const API = 'cms/api.php';
  const DRAFT_KEY = 'cms-draft:' + location.pathname;
  let csrf = null, version = null, user = null;
  let session = { menu: [], linkLists: [], pages: {}, help: 'cms/hjalp.php' };
  const thisFile = () => decodeURIComponent(location.pathname.split('/').pop());
  let undo = [];          // [{ path, old, val }] sedan senaste publicering
  let editing = null;     // pågående inline-redigering
  let pop = null;         // öppen popover

  // ---- Sökvägar i window.SITE ----
  const parse = (p) => p.split('.').map(s => /^\d+$/.test(s) ? +s : s);
  const get = (path) => parse(path).reduce((o, k) => (o == null ? undefined : o[k]), window.SITE);
  const set = (path, v) => { const ks = parse(path); const last = ks.pop(); ks.reduce((o, k) => o[k], window.SITE)[last] = v; };
  const isText = (path) => !!path && ['string', 'number'].includes(typeof get(path));
  const isImage = (path) => !!path && typeof get(path) === 'string';
  const rerender = () => window.dispatchEvent(new Event('cms:change'));

  async function api(action, body, form) {
    const opts = { method: body || form ? 'POST' : 'GET', credentials: 'same-origin', headers: {} };
    if (csrf) opts.headers['X-CSRF-Token'] = csrf;
    if (form) opts.body = form;
    else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    const r = await fetch(API + '?action=' + action, opts);
    const d = await r.json().catch(() => ({ error: 'Oväntat svar från servern.' }));
    if (!r.ok) { const e = new Error(d.error || 'Något gick fel.'); e.status = r.status; throw e; }
    return d;
  }

  // ---- Ändringar, ångra, utkast ----
  function change(path, old, val) {
    if (old === val) return;
    set(path, val);
    undo.push({ path, old, val });
    saveDraft();
    rerender();
    updateBar();
  }

  function undoLast() {
    const u = undo.pop();
    if (!u) return;
    set(u.path, u.old);
    saveDraft();
    rerender();
    updateBar();
    toast('Ångrade senaste ändringen');
  }

  function saveDraft() {
    try {
      if (undo.length) localStorage.setItem(DRAFT_KEY, JSON.stringify({ base: version, undo }));
      else localStorage.removeItem(DRAFT_KEY);
    } catch (e) { /* privat läge e.d. – utkast är bara en bekvämlighet */ }
  }

  function restoreDraft() {
    let d = null;
    try { d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { return; }
    if (!d || !d.undo || !d.undo.length) return;
    if (d.base !== version) { try { localStorage.removeItem(DRAFT_KEY); } catch (e) {} return; }
    if (!confirm('Du har ' + d.undo.length + ' opublicerade ändringar sedan förra gången. Vill du fortsätta med dem?')) {
      try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
      return;
    }
    for (const u of d.undo) if (JSON.stringify(get(u.path)) === JSON.stringify(u.old)) { set(u.path, u.val); undo.push(u); }
    rerender();
    updateBar();
  }

  // ---- Inline-redigering ----
  function startInline(el, ev) {
    const path = el.dataset.cms;
    const span = el.querySelector('.sc-interp') || el;
    const value = get(path);
    const isNum = typeof value === 'number';
    const nodes = [...span.childNodes];
    const orig = span.textContent;
    if (isNum && nodes[0]) nodes[0].nodeValue = String(value);
    editing = { el, span, path, nodes, orig, isNum, last: span.textContent, link: el.closest('a') };
    if (editing.link) editing.link.setAttribute('draggable', 'false');
    el.classList.add('cms-editing');
    span.contentEditable = 'plaintext-only';
    if (span.contentEditable !== 'plaintext-only') span.contentEditable = 'true';
    span.spellcheck = true;
    span.focus();
    const sel = getSelection();
    const r = caretAt(ev.clientX, ev.clientY);
    sel.removeAllRanges();
    if (r && !isNum && span.contains(r.startContainer)) sel.addRange(r);
    else { const all = document.createRange(); all.selectNodeContents(span); sel.addRange(all); }
    span.addEventListener('keydown', onKey);
    span.addEventListener('input', onInput);
    span.addEventListener('paste', onPaste);
    span.addEventListener('blur', onBlur);
  }

  function caretAt(x, y) {
    if (document.caretRangeFromPoint) return document.caretRangeFromPoint(x, y);
    const p = document.caretPositionFromPoint && document.caretPositionFromPoint(x, y);
    if (!p) return null;
    const r = document.createRange();
    r.setStart(p.offsetNode, p.offset);
    return r;
  }

  const onInput = () => { if (editing) editing.last = editing.span.textContent; };
  const onBlur = () => finishInline(true);
  function onKey(e) {
    e.stopPropagation();
    if (e.key === 'Enter') { e.preventDefault(); finishInline(true); }
    else if (e.key === 'Escape') { e.preventDefault(); finishInline(false); }
  }
  function onPaste(e) {
    e.preventDefault();
    const t = (e.clipboardData || window.clipboardData).getData('text/plain').replace(/\s+/g, ' ');
    document.execCommand('insertText', false, t);
  }

  function finishInline(keep) {
    if (!editing) return;
    const { el, span, path, nodes, orig, isNum, link } = editing;
    const text = (span.isConnected ? span.textContent : editing.last).replace(/\s+/g, ' ').trim();
    editing = null;
    span.removeEventListener('keydown', onKey);
    span.removeEventListener('input', onInput);
    span.removeEventListener('paste', onPaste);
    span.removeEventListener('blur', onBlur);
    span.removeAttribute('contenteditable');
    el.classList.remove('cms-editing');
    if (link) link.removeAttribute('draggable');
    // Lämna tillbaka exakt de DOM-noder React äger, så att React själv kan rita det nya värdet.
    span.replaceChildren(...nodes);
    if (nodes[0]) nodes[0].nodeValue = orig;
    getSelection().removeAllRanges();
    if (keep) commitValue(path, text, isNum);
  }

  function commitValue(path, text, isNum) {
    const old = get(path);
    if (isNum) {
      const n = parseInt(text.replace(/[^\d]/g, ''), 10);
      if (isNaN(n)) return toast('Skriv ett heltal, t.ex. 995', true);
      return change(path, old, n);
    }
    if (!text) return toast('Texten kan inte vara tom – ändringen ångrades.', true);
    change(path, old, text);
  }

  // ---- Popover för text i knappar (där inline-redigering fungerar dåligt) ----
  // och för text med radbrytningar (data-cms-multiline): Enter ger ny rad, Ctrl+Enter är Klar.
  // Radbrytningarna sparas som \n, och sidan ritar dem själv (t.ex. <br> eller white-space: pre-line).
  const clean = (s, multi) => multi
    ? s.replace(/\r/g, '').split('\n').map(l => l.replace(/\s+/g, ' ').trim()).join('\n').replace(/\n{3,}/g, '\n\n').trim()
    : s.replace(/\s+/g, ' ').trim();
  function startPopoverText(el, multi) {
    const path = el.dataset.cms;
    const old = get(path);
    const box = openPop(el, `
      <label class="cms-l">Text</label>
      <textarea class="cms-in" rows="${multi ? 6 : 2}"></textarea>
      ${multi ? '<p class="cms-hint">Enter ger en ny rad. Ctrl+Enter sparar.</p>' : ''}
      <div class="cms-row"><button class="cms-btn" data-x="cancel">Avbryt</button><button class="cms-btn cms-pri" data-x="ok">Klar</button></div>`, multi ? 460 : 340);
    const ta = box.querySelector('textarea');
    ta.value = String(old);
    ta.focus();
    ta.select();
    ta.addEventListener('input', () => { set(path, clean(ta.value, multi) || old); rerender(); });
    ta.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && (!multi || e.ctrlKey || e.metaKey)) { e.preventDefault(); done(true); }
      if (e.key === 'Escape') { e.preventDefault(); done(false); }
    });
    box.querySelector('[data-x=ok]').onclick = () => done(true);
    box.querySelector('[data-x=cancel]').onclick = () => done(false);
    pop.onClose = () => done(true);
    function done(keep) {
      const text = clean(ta.value, multi);
      set(path, old);
      closePop();
      if (keep) commitValue(path, text, typeof old === 'number');
      else rerender();
    }
  }

  // ---- Länklistor ----
  // Samma regler som servern (valid_href i lib.php). Hela listan ändras i ett steg så att Ångra fungerar.
  // Mönstren kommer från config.php: 'pages.*.blocks.#.links' → ägaren 'pages.x.blocks.2', listan 'links'.
  let linkPatterns = [];
  const buildLinkPatterns = () => {
    linkPatterns = session.linkLists.map(p => {
      const segs = p.split('.'), key = segs.pop();
      const owner = segs.map(s => s === '*' ? '[^.]+' : s === '#' ? '\\d+' : s.replace(/[.*+?^${}()|[\]\\-]/g, '\\$&')).join('\\.');
      return { key, owner: new RegExp('^' + owner + '$'), label: new RegExp('^(' + owner + ')\\.' + key + '\\.(\\d+)\\.label$') };
    });
  };
  // Är path texten på en länk i en länklista? Returnerar sökvägen till listan och länkens index.
  const linkLabel = (path) => {
    for (const p of linkPatterns) { const m = p.label.exec(path); if (m) return [m[1] + '.' + p.key, +m[2]]; }
    return null;
  };
  // Sökvägen till länklistan för ett data-cms-addlink-element (ägaren).
  const listPathFor = (owner) => { const p = linkPatterns.find(p => p.owner.test(owner)); return p ? owner + '.' + p.key : null; };
  const LINKS_MAX = 8;
  const validHref = (h) => /^(#\/[\w\-/]*|#[\w\-]+|https?:\/\/[^\s"<>]+|mailto:[^\s"<>]+|tel:[\d+\- ]+)$/.test(h);
  function normHref(h) {
    h = h.trim();
    const page = h.replace(/^[\/#]+/, '');
    if (/^www\./i.test(h)) return 'https://' + h;
    if (/^[^\s@\/:]+@[^\s@\/]+\.[a-z]{2,}$/i.test(h)) return 'mailto:' + h;
    if (/^\+?[\d\s-]{6,}$/.test(h)) return 'tel:' + h.replace(/[\s-]/g, '');
    if (page && (window.SITE.pages || {})[page]) return '#/' + page;
    return h;
  }

  function startLink(anchor, listPath, index) {
    const list = get(listPath) || [];
    const isNew = index >= list.length;
    if (isNew && list.length >= LINKS_MAX) return toast('Max ' + LINKS_MAX + ' länkar per avsnitt.', true);
    const cur = list[index] || { label: '', href: '' };
    const pages = window.SITE.pages || {};
    // Textlänkar visas ovanför filmerna, så pilarna flyttar bara förbi länkar av samma sort.
    const isVideo = (l) => /(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))[\w-]{11}/.test(l.href);
    const same = list.map((l, i) => i).filter(i => isVideo(list[i]) === isVideo(cur));
    const pos = same.indexOf(index);
    const prev = same[pos - 1], next = same[pos + 1];
    const box = openPop(anchor, `
      <label class="cms-l">Länktext</label>
      <input class="cms-in" data-f="label" type="text" maxlength="80">
      <label class="cms-l">Adress</label>
      <input class="cms-in" data-f="href" type="text" list="cms-pages" placeholder="https://… eller välj en sida" autocomplete="off">
      <datalist id="cms-pages">${Object.keys(pages).map(k => `<option value="#/${escapeHtml(k)}">${escapeHtml(pages[k].title || k)}</option>`).join('')}</datalist>
      <p class="cms-hint">Webbadress, sida på webbplatsen (#/priser), e-postadress eller telefonnummer. YouTube-länkar visas som film.</p>
      <p class="cms-err"></p>
      <div class="cms-row">
        ${isNew ? '' : `<button class="cms-btn" data-x="up" title="Flytta upp"${prev === undefined ? ' disabled' : ''}>↑</button>
        <button class="cms-btn" data-x="down" title="Flytta ner"${next === undefined ? ' disabled' : ''}>↓</button>
        <button class="cms-btn cms-danger" data-x="del">Ta bort</button>`}
        <span class="cms-grow"></span>
        <button class="cms-btn" data-x="cancel">Avbryt</button>
        <button class="cms-btn cms-pri" data-x="ok">${isNew ? 'Lägg till' : 'Klar'}</button>
      </div>`, 380);
    const [label, href] = ['label', 'href'].map(f => box.querySelector(`[data-f=${f}]`));
    const err = box.querySelector('.cms-err');
    label.value = cur.label;
    href.value = cur.href;
    label.focus();
    label.select();
    const save = (next) => { closePop(); change(listPath, list, next); };
    const ok = () => {
      const l = label.value.replace(/\s+/g, ' ').trim(), h = normHref(href.value);
      if (!l) { err.textContent = 'Skriv en länktext.'; return label.focus(); }
      if (!validHref(h)) { err.textContent = 'Adressen ser inte ut som en länk. Använd t.ex. https://…, #/priser, en e-postadress eller ett telefonnummer.'; return href.focus(); }
      if (l === cur.label && h === cur.href) return closePop();
      const next = list.slice();
      next[index] = { label: l, href: h };
      save(next);
    };
    const move = (to) => { const out = list.slice(); [out[index], out[to]] = [out[to], out[index]]; save(out); };
    box.querySelector('[data-x=ok]').onclick = ok;
    box.querySelector('[data-x=cancel]').onclick = closePop;
    if (!isNew) {
      box.querySelector('[data-x=up]').onclick = () => move(prev);
      box.querySelector('[data-x=down]').onclick = () => move(next);
      box.querySelector('[data-x=del]').onclick = () => { save(list.filter((_, i) => i !== index)); toast('Länken är borttagen. Ångra om det var fel.'); };
    }
    box.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); ok(); }
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closePop(); }
    });
    pop.onClose = closePop;
  }

  // ---- Bilder ----
  function startImage(el) {
    const path = el.dataset.cmsImg;
    const altPath = el.dataset.cmsAlt;
    const hasAlt = altPath && typeof get(altPath) === 'string';
    const box = openPop(el, `
      <div class="cms-thumb"><img alt=""></div>
      <button class="cms-btn cms-pri cms-wide" data-x="pick">Byt bild…</button>
      <input type="file" accept="image/jpeg,image/png,image/webp" hidden>
      <p class="cms-hint">JPG, PNG eller WebP. Stora bilder skalas ner automatiskt.</p>
      ${hasAlt ? '<label class="cms-l">Bildbeskrivning (för skärmläsare och Google)</label><input class="cms-in" type="text">' : ''}
      <div class="cms-row"><button class="cms-btn" data-x="close">Klar</button></div>`);
    const img = box.querySelector('.cms-thumb img');
    img.src = get(path);
    const file = box.querySelector('input[type=file]');
    const pick = box.querySelector('[data-x=pick]');
    pick.onclick = () => file.click();
    file.onchange = async () => {
      if (!file.files[0]) return;
      const fd = new FormData();
      fd.append('file', file.files[0]);
      pick.disabled = true;
      pick.textContent = 'Laddar upp…';
      try {
        const d = await api('upload', null, fd);
        change(path, get(path), d.path);
        img.src = d.path;
        toast('Bilden är bytt');
      } catch (e) {
        toast(e.message, true);
      } finally {
        pick.disabled = false;
        pick.textContent = 'Byt bild…';
        file.value = '';
      }
    };
    const alt = box.querySelector('input[type=text]');
    const oldAlt = hasAlt ? get(altPath) : null;
    if (alt) alt.value = oldAlt;
    const done = () => {
      closePop();
      if (alt && alt.value.trim() && alt.value.trim() !== oldAlt) change(altPath, oldAlt, alt.value.trim());
    };
    box.querySelector('[data-x=close]').onclick = done;
    pop.onClose = done;
  }

  // ---- Popover-ram ----
  function openPop(anchor, html, width = 340) {
    closePop();
    const box = document.createElement('div');
    box.className = 'cms-pop';
    box.innerHTML = html;
    document.body.appendChild(box);
    const r = anchor.getBoundingClientRect();
    const w = Math.min(width, innerWidth - 32);
    box.style.width = w + 'px';
    box.style.left = Math.max(16, Math.min(r.left, innerWidth - w - 16)) + 'px';
    // Förankra mot den sida som har mest plats, så att innehåll som fylls i senare växer inåt på skärmen.
    const spaceBelow = innerHeight - r.bottom - 16, spaceAbove = r.top - 16;
    if (spaceBelow >= 320 || spaceBelow >= spaceAbove) {
      box.style.top = (r.bottom + 8) + 'px';
      box.style.maxHeight = (spaceBelow - 8) + 'px';
    } else {
      box.style.bottom = (innerHeight - r.top + 8) + 'px';
      box.style.maxHeight = (spaceAbove - 8) + 'px';
    }
    pop = { box, onClose: null };
    return box;
  }
  function closePop() {
    if (!pop) return;
    pop.box.remove();
    pop = null;
  }

  // Andra sidor på samma webbplats öppnas i redigeringsläget, så att man kan fortsätta ändra där.
  function followLink(a) {
    const url = new URL(a.getAttribute('href'), location.href);
    if (url.origin === location.origin && !/[?&]edit\b/.test(url.search) && /(\/|\.php|\.html?)$/.test(url.pathname))
      url.search += (url.search ? '&' : '?') + 'edit';
    if (a.target === '_blank') open(url.href, '_blank', 'noopener');
    else location.href = url.href;
  }

  // ---- Klick på sidan ----
  function onClick(e) {
    if (e.target.closest('.cms-bar, .cms-pop, .cms-toast')) return;
    if (pop) { const p = pop; p.onClose ? p.onClose() : closePop(); e.preventDefault(); e.stopPropagation(); return; }
    if (editing && editing.span.contains(e.target)) { e.preventDefault(); e.stopPropagation(); return; }
    if (e.altKey) {
      // Alt+klick följer länken. Webbläsaren skulle annars ladda ner sidan (Alt+klick = spara länk).
      const a = e.target.closest('a[href]');
      if (a) { e.preventDefault(); e.stopPropagation(); followLink(a); }
      return;
    }
    const add = e.target.closest('[data-cms-addlink]');
    const addList = add && listPathFor(add.dataset.cmsAddlink);
    if (addList) {
      e.preventDefault();
      e.stopPropagation();
      if (editing) finishInline(true);
      startLink(add, addList, (get(addList) || []).length);
      return;
    }
    const txt = e.target.closest('[data-cms]');
    const link = txt && linkLabel(txt.dataset.cms);
    if (link) {
      e.preventDefault();
      e.stopPropagation();
      if (editing) finishInline(true);
      startLink(txt, link[0], link[1]);
      return;
    }
    if (txt && isText(txt.dataset.cms)) {
      e.preventDefault();
      e.stopPropagation();
      if (editing) finishInline(true);
      const multi = 'cmsMultiline' in txt.dataset;
      if (multi || txt.closest('button')) startPopoverText(txt, multi);
      else startInline(txt, e);
      return;
    }
    const img = e.target.closest('[data-cms-img]');
    if (img && isImage(img.dataset.cmsImg)) {
      e.preventDefault();
      e.stopPropagation();
      if (editing) finishInline(true);
      startImage(img);
      return;
    }
    const a = e.target.closest('a[href]');
    if (a && !a.getAttribute('href').startsWith('#')) {
      e.preventDefault();
      toast('Länkar som lämnar sidan är avstängda medan du redigerar. Håll Alt och klicka för att öppna ändå.');
    }
  }

  // ---- Verktygsfält ----
  let bar;
  function buildBar() {
    bar = document.createElement('div');
    bar.className = 'cms-bar';
    bar.innerHTML = `
      <span class="cms-dot"></span><strong>Redigeringsläge</strong>
      <select class="cms-sel" aria-label="Gå till sida"></select>
      <span class="cms-count"></span>
      <button class="cms-btn" data-x="undo" title="Ångra (Ctrl+Z)">Ångra</button>
      <button class="cms-btn" data-x="history">Historik</button>
      <button class="cms-btn cms-pri" data-x="publish">Publicera</button>
      <button class="cms-btn cms-ghost" data-x="exit">Avsluta</button>
      <button class="cms-btn cms-ghost" data-x="logout" title="Inloggad som ${escapeHtml(user)}">Logga ut</button>
      <a class="cms-help-link" href="${escapeHtml(session.help)}" target="_blank" rel="noopener">Hjälp</a>
      <span class="cms-help">Klicka på en text, bild eller länk för att ändra den. Alt+klick följer länkar och knappar.</span>`;
    document.body.appendChild(bar);

    const sel = bar.querySelector('select');
    const files = Object.keys(session.pages || {});
    if (files.length) {
      // Webbplats med flera sidfiler ('pages' i config.php): byt fil och stanna i redigeringsläget.
      sel.innerHTML = '<optgroup label="Sidor">' + files
        .map(f => `<option value="${escapeHtml(f)}">${escapeHtml(session.pages[f])}</option>`).join('') + '</optgroup>' +
        (session.menu.length ? '<optgroup label="Mer">' + session.menu
          .map(m => `<option value="${escapeHtml(m.href)}">${escapeHtml(m.label)}</option>`).join('') + '</optgroup>' : '');
      const cur = files.includes(thisFile()) ? thisFile() : (thisFile() === '' ? files[0] : null);
      if (cur) sel.value = cur;
      else sel.selectedIndex = -1;
      sel.onchange = () => {
        const v = sel.value;
        if (cur) sel.value = cur;
        location.href = v.startsWith('cms/') ? v : v + '?edit';
      };
    } else {
    const pages = window.SITE.pages || {};
    // Sidorna på webbplatsen (window.SITE.pages, adresser #/<nyckel>), plus "Mer": modulernas sidor,
    // som öppnas i stället för att byta sida (värde börjar med "cms/").
    sel.innerHTML = '<optgroup label="Sidor"><option value="hem">Startsida</option>' + Object.keys(pages)
      .map(k => `<option value="${escapeHtml(k)}">${escapeHtml(pages[k].title || k)}</option>`).join('') + '</optgroup>' +
      (session.menu.length ? '<optgroup label="Mer">' + session.menu
        .map(m => `<option value="${escapeHtml(m.href)}">${escapeHtml(m.label)}</option>`).join('') + '</optgroup>' : '');
    const syncSel = () => { sel.value = (location.hash.replace(/^#\/?/, '').split('?')[0]) || 'hem'; };
    syncSel();
    addEventListener('hashchange', syncSel);
    sel.onchange = () => {
      const v = sel.value;
      if (v.startsWith('cms/')) { syncSel(); location.href = v; return; }
      location.hash = v === 'hem' ? '#/' : '#/' + v;
    };
    }

    bar.querySelector('[data-x=undo]').onclick = undoLast;
    bar.querySelector('[data-x=history]').onclick = (e) => showHistory(e.currentTarget);
    bar.querySelector('[data-x=publish]').onclick = publish;
    bar.querySelector('[data-x=exit]').onclick = exit;
    bar.querySelector('[data-x=logout]').onclick = logout;
    updateBar();
  }

  function updateBar() {
    if (!bar) return;
    const n = undo.length;
    bar.querySelector('.cms-count').textContent = n ? n + (n === 1 ? ' ändring' : ' ändringar') + ' ej publicerade' : 'Allt är publicerat';
    bar.querySelector('.cms-count').classList.toggle('cms-warn', n > 0);
    bar.querySelector('[data-x=undo]').disabled = !n;
    bar.querySelector('[data-x=publish]').disabled = !n;
  }

  async function publish() {
    if (editing) finishInline(true);
    const btn = bar.querySelector('[data-x=publish]');
    btn.disabled = true;
    btn.textContent = 'Publicerar…';
    try {
      const d = await api('save', { site: window.SITE, base: version });
      version = d.version;
      undo = [];
      saveDraft();
      toast('Publicerat! Ändringarna syns nu för besökarna.');
    } catch (e) {
      if (e.status === 401) toast('Du har blivit utloggad. Logga in i en ny flik (cms/) och klicka Publicera igen – dina ändringar finns kvar här.', true);
      else toast(e.message, true);
    } finally {
      btn.textContent = 'Publicera';
      updateBar();
    }
  }

  async function showHistory(anchor) {
    const box = openPop(anchor, '<label class="cms-l">Tidigare versioner</label><div class="cms-hist">Hämtar…</div>');
    try {
      const { items } = await api('history');
      const list = box.querySelector('.cms-hist');
      if (!items.length) { list.textContent = 'Inga tidigare versioner ännu.'; return; }
      list.innerHTML = items.map(i => `<div class="cms-hrow"><span>${escapeHtml(i.date)} <em>${escapeHtml(i.user)}</em></span><button class="cms-btn" data-id="${escapeHtml(i.id)}">Återställ</button></div>`).join('');
      list.onclick = async (e) => {
        const id = e.target.dataset && e.target.dataset.id;
        if (!id) return;
        if (!confirm('Återställa webbplatsen till versionen från ' + e.target.parentNode.querySelector('span').textContent + '? Den nuvarande versionen sparas i historiken.')) return;
        try {
          const d = await api('restore', { id });
          window.SITE = d.site;
          version = d.version;
          undo = [];
          saveDraft();
          closePop();
          rerender();
          updateBar();
          toast('Versionen är återställd och publicerad.');
        } catch (err) { toast(err.message, true); }
      };
    } catch (e) { box.querySelector('.cms-hist').textContent = e.message; }
  }

  function exit() {
    if (undo.length && !confirm('Du har ändringar som inte är publicerade. Lämna ändå? (De sparas som utkast i den här webbläsaren.)')) return;
    location.href = location.pathname + location.hash;
  }

  async function logout() {
    if (undo.length && !confirm('Du har ändringar som inte är publicerade. Logga ut ändå? (De sparas som utkast i den här webbläsaren.)')) return;
    try { await api('logout', {}); } catch (e) { toast(e.message, true); return; }
    undo = [];
    location.href = 'cms/index.php';
  }

  // ---- Notiser ----
  let toastTimer;
  function toast(msg, bad) {
    let t = document.querySelector('.cms-toast');
    if (!t) { t = document.createElement('div'); t.className = 'cms-toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
    t.textContent = msg;
    t.classList.toggle('cms-bad', !!bad);
    t.classList.add('cms-show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('cms-show'), bad ? 7000 : 3500);
  }

  const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function injectStyles() {
    const css = `
      body.cms-on [data-cms]:not([data-cms^="-"]), body.cms-on [data-cms-img]:not([data-cms-img^="-"]) { pointer-events: auto !important; }
      body.cms-on [data-cms]:not([data-cms^="-"]):hover { outline: 2px dashed #2563EB; outline-offset: 2px; cursor: text; }
      body.cms-on [data-cms-img]:not([data-cms-img^="-"]):hover { outline: 3px solid #2563EB; outline-offset: -3px; cursor: pointer; }
      body.cms-on .cms-editing { outline: 2px solid #2563EB !important; outline-offset: 2px; background-color: rgba(37,99,235,.12); }
      body.cms-on .cms-editing .sc-interp { outline: none; cursor: text; }
      body.cms-on .cms-focus { outline: 3px solid #F59E0B !important; outline-offset: 3px; background-color: rgba(245,158,11,.18) !important; transition: background-color .3s; }
      body.cms-on { padding-bottom: 72px; }
      .cms-bar, .cms-pop, .cms-toast { font: 14px/1.4 system-ui, -apple-system, 'Segoe UI', sans-serif; color: #1F2937; box-sizing: border-box; }
      .cms-bar *, .cms-pop * { box-sizing: border-box; font: inherit; }
      .cms-bar { position: fixed; left: 50%; bottom: 12px; transform: translateX(-50%); z-index: 2147483000; display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px;
        width: max-content; max-width: calc(100vw - 24px); padding: 10px 14px; background: #fff; border: 1px solid #D1D5DB; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
      .cms-dot { width: 8px; height: 8px; border-radius: 50%; background: #2563EB; }
      .cms-sel { height: 32px; border: 1px solid #D1D5DB; border-radius: 6px; padding: 0 8px; background: #fff; max-width: 200px; }
      .cms-count { color: #6B7280; }
      .cms-count.cms-warn { color: #B45309; font-weight: 600; }
      .cms-help { flex-basis: 100%; color: #6B7280; font-size: 12px; }
      .cms-btn { height: 32px; padding: 0 12px; border: 1px solid #D1D5DB; border-radius: 6px; background: #fff; color: #1F2937; cursor: pointer; white-space: nowrap; }
      .cms-btn:hover:not(:disabled) { background: #F3F4F6; }
      .cms-btn:disabled { opacity: .45; cursor: default; }
      .cms-pri { background: #2563EB; border-color: #2563EB; color: #fff; font-weight: 600; }
      .cms-pri:hover:not(:disabled) { background: #1D4ED8; }
      .cms-ghost { border-color: transparent; }
      .cms-danger { color: #B91C1C; }
      .cms-grow { flex: 1; }
      a.cms-btn { display: inline-flex; align-items: center; text-decoration: none; }
      .cms-help-link { margin-left: auto; padding: 0 4px; color: #2563EB; text-decoration: underline; text-underline-offset: 2px; white-space: nowrap; }
      .cms-help-link:hover { color: #1D4ED8; }
      .cms-wide { width: 100%; }
      .cms-pop { position: fixed; z-index: 2147483001; background: #fff; border: 1px solid #D1D5DB; border-radius: 10px; box-shadow: 0 12px 32px rgba(0,0,0,.2); padding: 14px; display: flex; flex-direction: column; gap: 10px; max-height: 70vh; overflow: auto; }
      .cms-pop > * { flex-shrink: 0; }
      .cms-l { font-weight: 600; font-size: 13px; }
      .cms-in { width: 100%; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px; resize: vertical; }
      .cms-row { display: flex; justify-content: flex-end; gap: 8px; }
      .cms-hint { margin: 0; color: #6B7280; font-size: 12px; }
      .cms-thumb { background: #F3F4F6; border-radius: 6px; overflow: hidden; aspect-ratio: 16/10; }
      .cms-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
      .cms-hrow { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0; border-top: 1px solid #F3F4F6; }
      .cms-hrow em { color: #6B7280; font-style: normal; margin-left: 4px; }
      .cms-err { margin: 0; color: #B91C1C; font-size: 13px; }
      .cms-err:empty { display: none; }
      .cms-toast { position: fixed; left: 50%; bottom: 110px; transform: translate(-50%, 12px); z-index: 2147483002; max-width: min(520px, calc(100vw - 32px)); padding: 10px 16px;
        background: #111827; color: #fff; border-radius: 8px; opacity: 0; pointer-events: none; transition: opacity .2s, transform .2s; }
      .cms-toast.cms-show { opacity: 1; transform: translate(-50%, 0); }
      .cms-toast.cms-bad { background: #B91C1C; }`;
    const s = document.createElement('style');
    s.textContent = css;
    document.head.appendChild(s);
  }

  // ?edit&focus=<sökväg>: visa och markera ett fält (t.ex. från översättningssidan, "Visa på sidan").
  // Sidan kan rita fältet en stund efter start (byte av sida via #/…), så vi letar några gånger.
  function focusFromUrl() {
    const path = new URLSearchParams(location.search).get('focus');
    if (!path) return;
    let tries = 0;
    const find = () => {
      const el = [...document.querySelectorAll('[data-cms], [data-cms-alt], [data-cms-img]')]
        .find(e => e.dataset.cms === path || e.dataset.cmsAlt === path || e.dataset.cmsImg === path);
      if (el) {
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        el.classList.add('cms-focus');
        setTimeout(() => el.classList.remove('cms-focus'), 5000);
        return;
      }
      if (++tries < 20) setTimeout(find, 150);
      else toast('Texten syns inte på den här sidan just nu (den kan ligga i en meny eller på en annan sida).', true);
    };
    find();
  }

  // ---- Start ----
  async function init() {
    let s;
    try { s = await api('session'); } catch (e) { s = {}; }
    if (!s.user) { location.href = 'cms/index.php?hash=' + encodeURIComponent(location.hash) + '&page=' + encodeURIComponent(thisFile()); return; }
    user = s.user;
    csrf = s.csrf;
    session = { ...session, ...s };
    buildLinkPatterns();
    const d = await api('load');
    window.SITE = d.site;
    version = d.version;
    injectStyles();
    document.body.classList.add('cms-on');
    buildBar();
    rerender();
    restoreDraft();
    focusFromUrl();

    addEventListener('click', onClick, true);
    addEventListener('keydown', (e) => {
      if (editing || (pop && e.key !== 'Escape')) return;
      if (e.key === 'Escape' && pop) { pop.onCancel ? pop.onCancel() : closePop(); return; }
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.target.closest('input, textarea, select')) { e.preventDefault(); undoLast(); }
    });
    addEventListener('beforeunload', (e) => { if (undo.length) { e.preventDefault(); e.returnValue = ''; } });
    // Om elementet som redigeras försvinner (t.ex. en meny som stängs) sparas texten ändå.
    new MutationObserver(() => { if (editing && !editing.span.isConnected) finishInline(true); })
      .observe(document.body, { childList: true, subtree: true });
  }

  const start = () => init().catch(e => { console.error(e); alert('Redigeringsläget kunde inte startas: ' + e.message); });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
