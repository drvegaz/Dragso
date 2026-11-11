<?php
// Modulen "banners": meddelanden högst upp på alla sidor (site.json → notices). Sparas och publiceras direkt.
// Körs via cms/page.php, som redan har kontrollerat inloggningen.
$pages = array_keys(load_site()['site']['pages'] ?? []);
// Förslag i länkfältet: sidfilerna i config 'pages' (PHP-sidor), annars sidorna i site.json (#/nyckel).
$links = config()['pages'] ?: array_combine(array_map(fn($k) => "#/$k", $pages), $pages);
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Banners – <?= h(config()['site_name']) ?></title>
<link rel="icon" href="../favicon.ico?v=2" sizes="any">
<link rel="icon" href="../favicon.svg?v=2" type="image/svg+xml">
<style>
  <?= brand_css() ?>
  *{box-sizing:border-box}
  body{margin:0;background:#F5F3EF;color:#1F2937;font:15px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif}
  header{background:#404040;color:#fff}
  header div{max-width:860px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between}
  header a{color:#fff}
  main{max-width:860px;margin:0 auto;padding:24px 16px 120px;display:grid;gap:16px}
  h1{font-size:24px;margin:0}
  .lead{color:#6B7280;margin:4px 0 0}
  .card{background:#fff;border:1px solid #E4E0D9;padding:16px 20px 20px}
  .card.off .preview{opacity:.45}
  .top{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin:0 0 12px}
  .top .no{font-weight:700}
  .tools{display:flex;gap:4px;margin-left:auto}
  .check{display:flex;gap:8px;align-items:center;font-weight:600;cursor:pointer}
  .check input{width:18px;height:18px;accent-color:#2563EB}
  .badge{font-size:12px;font-weight:600;padding:2px 8px;border-radius:999px}
  .st-on{background:#DCFCE7;color:#166534}.st-wait{background:#FEF3C7;color:#92400E}.st-off{background:#F3F4F6;color:#6B7280}
  .preview{background:var(--accent);color:#fff;padding:10px 16px;font-weight:500;line-height:1.4;margin:0 0 14px}
  .preview a{color:inherit;font-weight:700}
  .colors{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px;border:0;padding:0}
  .colors legend{font-weight:600;font-size:14px;margin:0 0 4px;padding:0;width:100%}
  .colors label{display:flex;align-items:center;gap:8px;margin:0;padding:6px 12px 6px 8px;border:1px solid #D1D5DB;border-radius:999px;cursor:pointer;font-weight:600}
  .colors label:has(input:checked){border-color:#1F2937;box-shadow:0 0 0 1px #1F2937}
  .colors input{position:absolute;opacity:0;width:1px;height:1px}
  .colors input:focus-visible + .sw{outline:2px solid #2563EB;outline-offset:2px}
  .sw{width:20px;height:20px;border-radius:50%;border:1px solid rgba(0,0,0,.15)}
  label{display:block;font-weight:600;font-size:14px;margin:0 0 4px}
  input[type=text],input[type=datetime-local],textarea{width:100%;height:40px;padding:0 10px;border:1px solid #CFC9BF;font:inherit;background:#fff}
  textarea{height:auto;min-height:60px;padding:8px 10px;resize:vertical;margin:0 0 12px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px}
  @media (max-width:560px){.grid{grid-template-columns:1fr}.tools{margin-left:0;width:100%}}
  .hint{color:#6B7280;font-size:13px;margin:8px 0 0}
  .btn{height:38px;padding:0 14px;border:1px solid #D1D5DB;background:#fff;color:#1F2937;font:inherit;font-weight:600;cursor:pointer;border-radius:6px}
  .btn:hover:not(:disabled){background:#F3F4F6}
  .btn.pri{background:#2563EB;border-color:#2563EB;color:#fff}
  .btn.pri:hover:not(:disabled){background:#1D4ED8}
  .btn.del{color:#B91C1C}
  .btn:disabled{opacity:.45;cursor:default}
  .empty{background:#fff;border:1px dashed #CFC9BF;padding:24px;text-align:center;color:#6B7280}
  .save{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid #E4E0D9;box-shadow:0 -6px 20px rgba(0,0,0,.08)}
  .save div{max-width:860px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px}
  .save .msg{flex:1;min-width:200px;font-size:14px;color:#6B7280}
  .save .msg.bad{color:#B91C1C}.save .msg.good{color:#166534}.save .msg.warn{color:#B45309;font-weight:600}
</style>
</head>
<body>
<header><div><strong>Banners</strong><a href="<?= h(edit_url()) ?>">← Till redigeringsläget</a></div></header>
<main>
  <div>
    <h1>Banners högst upp på sidan</h1>
    <p class="lead">Meddelanden som visas överst på alla sidor – till exempel <em>"Julstängt 24–26 december"</em>. Ändringarna syns på webbplatsen så fort du sparar.</p>
  </div>
  <div id="list"><p class="empty">Hämtar…</p></div>
  <datalist id="cmsPages"><?php foreach ($links as $href => $name): ?><option value="<?= h((string)$href) ?>"><?= h((string)$name) ?></option><?php endforeach; ?></datalist>
  <div><button class="btn" type="button" id="add">+ Ny banner</button></div>
</main>
<div class="save"><div>
  <span class="msg" id="msg" role="status"></span>
  <button class="btn" type="button" id="undo" disabled>Ångra ändringar</button>
  <button class="btn pri" type="button" id="save" disabled>Spara och publicera</button>
</div></div>
<script>
const csrf = <?= json_encode(csrf_token()) ?>;
const MAX = 10;
const PAGE_FILES = <?= json_encode(array_keys(config()['pages'])) ?>;
// Färgerna från config 'banner_colors'. Den första är standard.
const COLORS = <?= json_encode((object)array_map(fn($c) => ['label' => (string)($c['label'] ?? ''), 'bg' => (string)($c['bg'] ?? ''), 'fg' => (string)($c['fg'] ?? '#fff')], config()['banner_colors']), JSON_UNESCAPED_UNICODE) ?>;
const COLOR_KEYS = Object.keys(COLORS);
const safeColor = (v, d) => /^#[0-9a-f]{3,8}$/i.test(v) ? v : d;
const previewStyle = (n) => { const c = COLORS[n.color] || COLORS[COLOR_KEYS[0]]; return c ? `background:${safeColor(c.bg, 'var(--accent)')};color:${safeColor(c.fg, '#fff')}` : ''; };
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const pad = (n) => String(n).padStart(2, '0');
const toLocal = (iso) => { if (!iso) return ''; const d = new Date(iso); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`; };
const fromLocal = (v) => v ? new Date(v).toISOString() : '';
const listEl = document.getElementById('list'), msg = document.getElementById('msg');
const saveBtn = document.getElementById('save'), undoBtn = document.getElementById('undo'), addBtn = document.getElementById('add');
let list = [], saved = '[]', base = '';

async function api(action, body) {
  const r = await fetch('api.php?action=' + action, { method: body ? 'POST' : 'GET',
    headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const d = await r.json().catch(() => ({ error: 'Oväntat svar från servern.' }));
  if (r.status === 401) { location.href = 'index.php'; throw new Error(d.error); }
  if (!r.ok) throw new Error(d.error || 'Något gick fel.');
  return d;
}

function status(n) {
  const now = Date.now();
  if (!n.enabled) return ['Inaktiv', 'off'];
  if (n.start && Date.parse(n.start) > now) return ['Schemalagd', 'wait'];
  if (n.end && Date.parse(n.end) <= now) return ['Utgången', 'off'];
  return ['Visas nu', 'on'];
}

const previewHtml = (n) => esc(n.text || 'Skriv en text…') + (n.linkLabel && n.linkHref ? ` <a>${esc(n.linkLabel)} →</a>` : '');
const dirty = () => JSON.stringify(list) !== saved;

function setMsg(text, cls = '') { msg.textContent = text; msg.className = 'msg ' + cls; }
function updateBar() {
  saveBtn.disabled = undoBtn.disabled = !dirty();
  addBtn.disabled = list.length >= MAX;
  if (dirty()) setMsg('Du har ändringar som inte är sparade.', 'warn');
  else if (msg.classList.contains('warn')) setMsg('');
}

function render() {
  listEl.innerHTML = list.length ? list.map((n, i) => {
    const [st, cls] = status(n);
    return `<section class="card${cls === 'on' ? '' : ' off'}" data-i="${i}">
      <div class="top">
        <span class="no">Banner ${i + 1}</span>
        <label class="check"><input type="checkbox" data-k="enabled" ${n.enabled ? 'checked' : ''}> Aktiv</label>
        <span class="badge st-${cls}">${st}</span>
        <div class="tools">
          <button class="btn" type="button" data-m="up" aria-label="Flytta upp" ${i === 0 ? 'disabled' : ''}>↑</button>
          <button class="btn" type="button" data-m="down" aria-label="Flytta ner" ${i === list.length - 1 ? 'disabled' : ''}>↓</button>
          <button class="btn del" type="button" data-m="del">Ta bort</button>
        </div>
      </div>
      <div class="preview" aria-hidden="true" style="${previewStyle(n)}">${previewHtml(n)}</div>
      ${COLOR_KEYS.length ? `<fieldset class="colors"><legend>Färg</legend>${COLOR_KEYS.map(k => `<label><input type="radio" name="c${i}" data-k="color" value="${esc(k)}"${(n.color || COLOR_KEYS[0]) === k ? ' checked' : ''}><span class="sw" style="background:${safeColor(COLORS[k].bg, '#ccc')}"></span>${esc(COLORS[k].label || k)}</label>`).join('')}</fieldset>` : ''}
      <label for="t${i}">Text</label>
      <textarea id="t${i}" data-k="text" maxlength="500" rows="2">${esc(n.text)}</textarea>
      <div class="grid">
        <div><label for="ll${i}">Länktext (valfri)</label><input id="ll${i}" type="text" data-k="linkLabel" maxlength="80" value="${esc(n.linkLabel)}" placeholder="Läs mer"></div>
        <div><label for="lh${i}">Länk</label><input id="lh${i}" type="text" data-k="linkHref" maxlength="500" value="${esc(n.linkHref)}" list="cmsPages" placeholder="${PAGE_FILES.length ? 'Välj en sida eller skriv https://…' : '#/priser eller https://…'}"></div>
        <div><label for="s${i}">Publiceras</label><input id="s${i}" type="datetime-local" data-k="start" value="${toLocal(n.start)}"></div>
        <div><label for="e${i}">Avpubliceras</label><input id="e${i}" type="datetime-local" data-k="end" value="${toLocal(n.end)}"></div>
      </div>
      <p class="hint">Tomma tider betyder att bannern visas direkt och tills vidare. <?php if ($links): ?>Sidor på webbplatsen: <?= h(implode(', ', array_keys($links))) ?>.<?php endif; ?></p>
    </section>`;
  }).join('') : '<p class="empty">Inga banners just nu. Tryck <strong>+ Ny banner</strong> för att lägga till en.</p>';
  updateBar();
}

// Fälten uppdaterar listan direkt; bara förhandsvisning och status ritas om så att markören inte hoppar.
listEl.addEventListener('input', (e) => {
  const card = e.target.closest('[data-i]'), k = e.target.dataset.k;
  if (!card || !k) return;
  const n = list[+card.dataset.i];
  n[k] = k === 'enabled' ? e.target.checked : (k === 'start' || k === 'end') ? fromLocal(e.target.value) : e.target.value;
  const [st, cls] = status(n);
  const badge = card.querySelector('.badge');
  badge.textContent = st;
  badge.className = 'badge st-' + cls;
  card.classList.toggle('off', cls !== 'on');
  card.querySelector('.preview').innerHTML = previewHtml(n);
  card.querySelector('.preview').style.cssText = previewStyle(n);
  updateBar();
});

listEl.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-m]');
  if (!b) return;
  const i = +b.closest('[data-i]').dataset.i, m = b.dataset.m;
  if (m === 'del') {
    if (!confirm('Ta bort banner ' + (i + 1) + '?')) return;
    list.splice(i, 1);
  } else {
    const j = m === 'up' ? i - 1 : i + 1;
    [list[i], list[j]] = [list[j], list[i]];
  }
  render();
});

addBtn.onclick = () => {
  list.push({ id: 'n' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6), text: '', linkLabel: '', linkHref: '', enabled: true, start: '', end: '', ...(COLOR_KEYS.length ? { color: COLOR_KEYS[0] } : {}) });
  render();
  listEl.querySelector('.card:last-child textarea').focus();
};

undoBtn.onclick = () => {
  if (!confirm('Ta bort alla ändringar sedan du senast sparade?')) return;
  list = JSON.parse(saved);
  render();
};

function problem() {
  for (const [i, n] of list.entries()) {
    const name = 'Banner ' + (i + 1);
    if (!n.text.trim()) return name + ' saknar text.';
    if (n.linkLabel.trim() && !n.linkHref.trim()) return name + ' har länktext men ingen länk.';
    const page = n.linkHref.trim().match(/^([\w-]+\.(?:php|html?))(?:\?[\w=&%.-]*)?(?:#[\w-]*)?$/);
    if (page && !PAGE_FILES.includes(page[1])) return name + ': sidan ' + page[1] + ' finns inte på webbplatsen.';
    if (n.linkHref && !page && !/^(#\/[\w\-/]*|#[\w-]+|https?:\/\/\S+|mailto:\S+|tel:[\d+\- ]+)$/.test(n.linkHref.trim()))
      return name + ': länken ska vara en sida på webbplatsen eller börja med ' + (PAGE_FILES.length ? '' : '#/, ') + 'https://, mailto: eller tel:';
    if (n.start && n.end && Date.parse(n.end) <= Date.parse(n.start)) return name + ': avpubliceringen måste vara efter publiceringen.';
  }
  return null;
}

saveBtn.onclick = async () => {
  list = list.map(n => ({ ...n, text: n.text.replace(/\s+/g, ' ').trim(), linkLabel: n.linkLabel.trim(), linkHref: n.linkHref.trim() }));
  const p = problem();
  if (p) { setMsg(p, 'bad'); return; }
  saveBtn.disabled = true;
  setMsg('Sparar…');
  try {
    const d = await api('notices_save', { notices: list, base });
    load(d);
    setMsg('Sparat och publicerat.', 'good');
  } catch (x) { setMsg(x.message, 'bad'); saveBtn.disabled = false; }
};

function load(d) {
  // Banners från innan färgvalet fanns får standardfärgen (utan att räknas som en ändring).
  list = d.notices.map(n => COLOR_KEYS.length && !COLORS[n.color] ? { ...n, color: COLOR_KEYS[0] } : n);
  saved = JSON.stringify(list);
  base = d.base;
  render();
}

addEventListener('beforeunload', (e) => { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });
// Status (Schemalagd → Visas nu) uppdateras varje minut.
setInterval(() => { if (!listEl.contains(document.activeElement)) render(); }, 60000);

api('notices').then(load).catch(x => { listEl.innerHTML = `<p class="empty">${esc(x.message)}</p>`; });
</script>
</body>
</html>
