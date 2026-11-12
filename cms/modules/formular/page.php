<?php
// Modulen "formular": inkomna ärenden och redigering av formulären. Körs via cms/page.php (inloggning är kontrollerad).
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Formulär – <?= h(config()['site_name']) ?></title>
<link rel="icon" href="../favicon.ico?v=2" sizes="any">
<link rel="icon" href="../favicon.svg?v=2" type="image/svg+xml">
<style>
  <?= brand_css() ?>
  *{box-sizing:border-box}
  body{margin:0;background:#F5F3EF;color:#1F2937;font:15px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif}
  header{background:#404040;color:#fff}
  header div{max-width:900px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between}
  header a{color:#fff}
  main{max-width:900px;margin:0 auto;padding:24px 16px 120px;display:grid;gap:16px}
  h1{font-size:24px;margin:0}
  .lead{color:#6B7280;margin:4px 0 0}
  .tabs{display:flex;gap:4px;border-bottom:2px solid #E4E0D9}
  .tabs button{border:0;background:none;font:inherit;font-weight:600;padding:10px 16px;cursor:pointer;color:#6B7280;border-bottom:3px solid transparent;margin-bottom:-2px}
  .tabs button[aria-selected=true]{color:#1F2937;border-color:var(--accent)}
  .bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
  .chip{height:34px;padding:0 12px;border:1px solid #D1D5DB;background:#fff;border-radius:999px;font:inherit;font-size:14px;font-weight:600;cursor:pointer}
  .chip[aria-pressed=true]{background:#1F2937;border-color:#1F2937;color:#fff}
  .count{display:inline-block;min-width:20px;padding:0 6px;margin-left:6px;border-radius:999px;background:var(--accent);color:#fff;font-size:12px;line-height:20px;text-align:center}
  .card{background:#fff;border:1px solid #E4E0D9;padding:16px 20px 20px}
  .entry{background:#fff;border:1px solid #E4E0D9}
  .entry.new{border-left:4px solid var(--accent)}
  .entry > button{all:unset;display:grid;grid-template-columns:auto 1fr auto;gap:4px 12px;align-items:center;width:100%;box-sizing:border-box;padding:12px 16px;cursor:pointer}
  .entry > button:focus-visible{outline:2px solid var(--accent);outline-offset:-2px}
  .entry .when{font-weight:600;white-space:nowrap}
  .entry .sum{color:#4B5563;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .entry .body{padding:0 16px 16px;border-top:1px solid #F3F4F6}
  dl{display:grid;grid-template-columns:minmax(120px,200px) 1fr;gap:6px 16px;margin:12px 0}
  dt{color:#6B7280;font-weight:600}
  dd{margin:0;white-space:pre-line;overflow-wrap:anywhere}
  .imgs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}
  .imgs img{height:120px;border:1px solid #E4E0D9;display:block}
  .badge{font-size:12px;font-weight:600;padding:2px 8px;border-radius:999px;white-space:nowrap}
  .st-ny{background:#FEE2E2;color:#991B1B}.st-hanterad{background:#DCFCE7;color:#166534}.st-warn{background:#FEF3C7;color:#92400E}
  .top{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin:0 0 12px}
  .top .no{font-weight:700}
  .tools{display:flex;gap:4px;margin-left:auto}
  .check{display:flex;gap:8px;align-items:center;font-weight:600;cursor:pointer;margin:0}
  .check input{width:18px;height:18px;accent-color:var(--accent)}
  label{display:block;font-weight:600;font-size:14px;margin:0 0 4px}
  input[type=text],select,textarea{width:100%;height:40px;padding:0 10px;border:1px solid #CFC9BF;font:inherit;background:#fff}
  textarea{height:auto;min-height:80px;padding:8px 10px;resize:vertical}
  .top select{width:auto}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px}
  .full{grid-column:1/-1}
  .hint{color:#6B7280;font-size:13px;margin:6px 0 0}
  .hint code{background:#F3F4F6;padding:1px 5px;cursor:pointer}
  .btn{height:38px;padding:0 14px;border:1px solid #D1D5DB;background:#fff;color:#1F2937;font:inherit;font-weight:600;cursor:pointer;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center}
  .btn:hover:not(:disabled){background:#F3F4F6}
  .btn.pri{background:#2563EB;border-color:#2563EB;color:#fff}
  .btn.pri:hover:not(:disabled){background:#1D4ED8}
  .btn.del{color:#B91C1C}
  .btn:disabled{opacity:.45;cursor:default}
  .btn.sm{height:32px;font-size:14px;padding:0 10px}
  .empty{background:#fff;border:1px dashed #CFC9BF;padding:24px;text-align:center;color:#6B7280;margin:0}
  h2{font-size:18px;margin:8px 0 0}
  .save{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid #E4E0D9;box-shadow:0 -6px 20px rgba(0,0,0,.08)}
  .save div{max-width:900px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px}
  .save .msg{flex:1;min-width:200px;font-size:14px;color:#6B7280}
  .save .msg.bad{color:#B91C1C}.save .msg.good{color:#166534}.save .msg.warn{color:#B45309;font-weight:600}
  [hidden]{display:none!important}
  @media (max-width:600px){.grid{grid-template-columns:1fr}.tools{margin-left:0}dl{grid-template-columns:1fr}dd{margin-bottom:6px}.entry > button{grid-template-columns:1fr auto}.entry .sum{grid-column:1/-1}}
</style>
</head>
<body>
<header><div><strong>Formulär</strong><a href="<?= h(edit_url()) ?>">← Till redigeringsläget</a></div></header>
<main>
  <div>
    <h1>Formulär</h1>
    <p class="lead">Se vad besökarna har skickat in och ändra formulären: fält, texter, val och vem som får mejlen.</p>
  </div>
  <div class="tabs" role="tablist">
    <button type="button" role="tab" id="tabIn" aria-selected="true">Inkommet</button>
    <button type="button" role="tab" id="tabEd" aria-selected="false">Ändra formulär</button>
  </div>

  <section id="inbox">
    <div class="bar" id="formChips"></div>
    <div class="bar" style="margin:12px 0">
      <label for="filter" style="margin:0">Visa</label>
      <select id="filter" style="width:auto"><option value="">Alla</option><option value="ny">Nya</option><option value="hanterad">Hanterade</option></select>
    </div>
    <div id="entries" style="display:grid;gap:8px"><p class="empty">Hämtar…</p></div>
    <p class="hint" id="keep"></p>
  </section>

  <section id="editor" hidden>
    <div class="bar">
      <select id="pick" style="width:auto;min-width:200px" aria-label="Formulär"></select>
      <button class="btn" type="button" id="addForm">+ Nytt formulär</button>
      <button class="btn del" type="button" id="delForm">Ta bort formuläret</button>
    </div>
    <div id="form" style="display:grid;gap:16px;margin-top:16px"></div>
  </section>
</main>
<div class="save" id="saveBar" hidden><div>
  <span class="msg" id="msg" role="status"></span>
  <button class="btn" type="button" id="undo" disabled>Ångra ändringar</button>
  <button class="btn pri" type="button" id="save" disabled>Spara</button>
</div></div>
<script>
const csrf = <?= json_encode(csrf_token()) ?>;
const PAGES = <?= json_encode((object)config()['pages'], JSON_UNESCAPED_UNICODE) ?>;
const TYPES = { text: 'Kort text', textarea: 'Lång text', email: 'E-post', tel: 'Telefon', select: 'Lista med val', image: 'Foto' };
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const $ = (id) => document.getElementById(id);
let forms = [], base = '', saved = '', entries = {}, current = 0, inboxForm = '', open = new Set();

async function api(action, body, query = '') {
  const r = await fetch('api.php?action=' + action + query, { method: body ? 'POST' : 'GET',
    headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const d = await r.json().catch(() => ({ error: 'Oväntat svar från servern.' }));
  if (r.status === 401) { location.href = 'index.php'; throw new Error(d.error); }
  if (!r.ok) throw new Error(d.error || 'Något gick fel.');
  return d;
}

// ---- Flikar ----
function showTab(ed) {
  $('tabIn').setAttribute('aria-selected', !ed); $('tabEd').setAttribute('aria-selected', ed);
  $('inbox').hidden = ed; $('editor').hidden = !ed; $('saveBar').hidden = !ed;
  history.replaceState(null, '', ed ? '#andra' : '#');
}
$('tabIn').onclick = () => showTab(false);
$('tabEd').onclick = () => showTab(true);

// ---- Inkommet ----
const MONTHS = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
const when = (iso) => { const d = new Date(iso); return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`; };
const formTitle = (id) => forms.find(f => f.id === id)?.title || entries[id]?.[0]?.title || id;

function renderInbox() {
  const ids = [...new Set([...forms.map(f => f.id), ...Object.keys(entries)])];
  if (!ids.includes(inboxForm)) inboxForm = ids.find(id => (entries[id] || []).some(e => e.status === 'ny')) || ids[0] || '';
  $('formChips').innerHTML = ids.map(id => {
    const n = (entries[id] || []).filter(e => e.status === 'ny').length;
    return `<button type="button" class="chip" data-f="${esc(id)}" aria-pressed="${id === inboxForm}">${esc(formTitle(id))}${n ? `<span class="count">${n}</span>` : ''}</button>`;
  }).join('');
  const filter = $('filter').value;
  const list = (entries[inboxForm] || []).filter(e => !filter || e.status === filter);
  if (!list.length) { $('entries').innerHTML = `<p class="empty">${filter === 'ny' ? 'Inga nya inskick.' : 'Inga inskick ännu.'}</p>`; return; }
  $('entries').innerHTML = list.map(e => {
    const sum = e.values.filter(v => v.value && v.type !== 'textarea').slice(0, 3).map(v => v.value).join(' · ');
    const email = e.values.find(v => v.type === 'email' && v.value)?.value;
    const isOpen = open.has(e.id);
    const val = (v) => !v.value ? '<span style="color:#9CA3AF">(ej ifyllt)</span>'
      : v.type === 'email' ? `<a href="mailto:${esc(v.value)}">${esc(v.value)}</a>`
      : v.type === 'tel' ? `<a href="tel:${esc(v.value.replace(/[^\d+]/g, ''))}">${esc(v.value)}</a>` : esc(v.value);
    return `<article class="entry${e.status === 'ny' ? ' new' : ''}" data-id="${esc(e.id)}">
      <button type="button" data-act="toggle" aria-expanded="${isOpen}">
        <span class="when">${when(e.created)}</span><span class="sum">${esc(sum)}</span>
        <span>${e.mailed ? '' : '<span class="badge st-warn" title="Mejlet till mottagarna gick inte iväg – inskicket finns bara här">Ej mejlat</span> '}<span class="badge st-${e.status}">${e.status === 'ny' ? 'Ny' : 'Hanterad'}</span></span>
      </button>
      ${isOpen ? `<div class="body">
        <dl>${e.values.map(v => `<dt>${esc(v.label)}</dt><dd>${val(v)}</dd>`).join('')}</dl>
        ${e.files.length ? `<div class="imgs">${e.files.map(f => { const u = `api.php?action=form_file&form=${encodeURIComponent(e.form)}&file=${encodeURIComponent(f.file)}`; return `<a href="${u}" target="_blank" rel="noopener"><img src="${u}" alt="${esc(f.label)}"></a>`; }).join('')}</div>` : ''}
        <div class="bar">
          <button class="btn sm" type="button" data-act="status">${e.status === 'ny' ? 'Markera som hanterad' : 'Markera som ny'}</button>
          ${email ? `<a class="btn sm" href="mailto:${esc(email)}?subject=${encodeURIComponent('Sv: ' + e.title)}">Svara med e-post</a>` : ''}
          <button class="btn sm del" type="button" data-act="delete" style="margin-left:auto">Ta bort</button>
        </div>
      </div>` : ''}
    </article>`;
  }).join('');
}

$('formChips').onclick = (ev) => { const b = ev.target.closest('[data-f]'); if (b) { inboxForm = b.dataset.f; renderInbox(); } };
$('filter').onchange = renderInbox;
$('entries').onclick = async (ev) => {
  const b = ev.target.closest('[data-act]'); if (!b) return;
  const id = b.closest('[data-id]').dataset.id, list = entries[inboxForm], e = list.find(x => x.id === id);
  try {
    if (b.dataset.act === 'toggle') { open.has(id) ? open.delete(id) : open.add(id); }
    if (b.dataset.act === 'status') Object.assign(e, (await api('form_entry', { form: e.form, id, status: e.status === 'ny' ? 'hanterad' : 'ny' })).entry);
    if (b.dataset.act === 'delete') {
      if (!confirm('Ta bort inskicket? Det går inte att ångra.')) return;
      await api('form_entry', { form: e.form, id, delete: true });
      list.splice(list.indexOf(e), 1);
    }
  } catch (err) { alert(err.message); }
  renderInbox();
};

async function loadInbox() {
  try {
    const d = await api('form_entries');
    entries = d.entries;
    $('keep').textContent = `Inskick sparas i ${d.keepDays} dagar och tas sedan bort automatiskt.`;
    renderInbox();
  } catch (e) { $('entries').innerHTML = `<p class="empty">${esc(e.message)}</p>`; }
}

// ---- Ändra formulär ----
const state = () => JSON.stringify(forms);
const dirty = () => state() !== saved;
function setMsg(text, cls = '') { $('msg').textContent = text; $('msg').className = 'msg ' + cls; }
function updateBar() {
  $('save').disabled = $('undo').disabled = !dirty();
  $('delForm').disabled = !forms.length;
  if (dirty()) setMsg('Du har ändringar som inte är sparade.', 'warn');
  else if ($('msg').classList.contains('warn')) setMsg('');
}
function slug(s, sep) {
  return s.toLowerCase().replace(/[åä]/g, 'a').replace(/ö/g, 'o').replace(/é/g, 'e').replace(/[^a-z0-9]+/g, sep).replace(new RegExp(`^\\${sep}+|\\${sep}+$`, 'g'), '').slice(0, 30);
}
function uniqueId(wanted, taken, sep, fallback) {
  let id = wanted && /^[a-z]/.test(wanted) ? wanted : fallback + (wanted ? sep + wanted : '');
  let n = 2, out = id;
  while (taken.includes(out)) out = id + sep + n++;
  return out;
}
const newField = (type = 'text') => ({ id: '', type, label: '', help: '', placeholder: '', required: false, options: type === 'select' ? ['Alternativ 1'] : [], dynamic: false });

function renderPick() {
  $('pick').innerHTML = forms.map((f, i) => `<option value="${i}"${i === current ? ' selected' : ''}>${esc(f.title || 'Nytt formulär')}</option>`).join('');
  $('pick').hidden = !forms.length;
}

function fieldCard(x, i, n) {
  const ph = x.type !== 'image' && !(x.type === 'select' && x.dynamic);
  return `<section class="card" data-i="${i}">
    <div class="top"><span class="no">${i + 1}.</span>
      <select data-k="type" aria-label="Typ av fält" ${x.dynamic ? 'disabled' : ''}>${Object.entries(TYPES).map(([k, t]) => `<option value="${k}"${k === x.type ? ' selected' : ''}>${t}</option>`).join('')}</select>
      <label class="check"><input type="checkbox" data-k="required"${x.required ? ' checked' : ''}> Måste fyllas i</label>
      <div class="tools">
        <button class="btn sm" type="button" data-act="up" ${i ? '' : 'disabled'} aria-label="Flytta upp">↑</button>
        <button class="btn sm" type="button" data-act="down" ${i < n - 1 ? '' : 'disabled'} aria-label="Flytta ned">↓</button>
        <button class="btn sm del" type="button" data-act="remove" ${n > 1 ? '' : 'disabled'}>Ta bort</button>
      </div>
    </div>
    <div class="grid">
      <div><label>Rubrik</label><input type="text" data-k="label" maxlength="120" value="${esc(x.label)}"></div>
      ${ph ? `<div><label>${x.type === 'select' ? 'Text innan man valt' : 'Exempeltext i rutan'}</label><input type="text" data-k="placeholder" maxlength="200" value="${esc(x.placeholder)}"></div>` : '<div></div>'}
      <div class="full"><label>Hjälptext under fältet</label><input type="text" data-k="help" maxlength="300" value="${esc(x.help)}"></div>
      ${x.type === 'select' ? (x.dynamic
        ? '<p class="hint full">Valen i listan hämtas automatiskt från webbplatsen.</p>'
        : `<div class="full"><label>Val (ett per rad)</label><textarea data-k="options" rows="${Math.min(10, x.options.length + 1)}">${esc(x.options.join('\n'))}</textarea></div>`) : ''}
    </div>
    ${x.id ? `<p class="hint">Kod i ämnesraden: <code>{${esc(x.id)}}</code></p>` : ''}
  </section>`;
}

function renderForm() {
  renderPick();
  const f = forms[current];
  if (!f) { $('form').innerHTML = '<p class="empty">Det finns inga formulär. Skapa ett med "+ Nytt formulär".</p>'; updateBar(); return; }
  const codes = f.fields.filter(x => x.id && x.type !== 'image').map(x => `<code data-code="${esc(x.id)}" title="Lägg till i ämnesraden">{${esc(x.id)}}</code>`).join(' ');
  $('form').innerHTML = `<section class="card" id="settings">
    <div class="top"><span class="no">Inställningar</span>
      <label class="check" style="margin-left:auto"><input type="checkbox" data-s="active"${f.active ? ' checked' : ''}> Tar emot inskick</label></div>
    <div class="grid">
      <div><label for="sTitle">Namn</label><input id="sTitle" type="text" data-s="title" maxlength="80" value="${esc(f.title)}"></div>
      <div><label for="sButton">Text på knappen</label><input id="sButton" type="text" data-s="button" maxlength="60" value="${esc(f.button)}"></div>
      ${Object.keys(PAGES).length ? `<div class="full"><label for="sPage">Visas på sidan</label>
        <select id="sPage" data-s="page"><option value="">Ingen sida (formuläret visas inte)</option>
          ${Object.entries(PAGES).map(([file, name]) => `<option value="${esc(file)}"${file === f.page ? ' selected' : ''}>${esc(name)}</option>`).join('')}</select>
        <p class="hint">Formuläret läggs längst ner på sidan, med namnet som rubrik och ingressen under. Sidor som har en egen plats för formuläret visar det där i stället.</p></div>
      <div class="full"><label for="sIntro">Ingress (frivillig)</label><textarea id="sIntro" data-s="intro" rows="2" maxlength="1000">${esc(f.intro)}</textarea></div>` : ''}
      <div class="full"><label for="sRec">Skicka till (en e-postadress per rad)</label>
        <textarea id="sRec" data-s="recipients" rows="2">${esc(f.recipients.join('\n'))}</textarea>
        <p class="hint">Inskicken sparas alltid under Inkommet, även om mejlet inte kommer fram.</p></div>
      <div class="full"><label for="sSubj">Ämnesrad i mejlet</label><input id="sSubj" type="text" data-s="subject" maxlength="150" value="${esc(f.subject)}">
        ${codes ? `<p class="hint">Klicka på en kod för att lägga in svaret i ämnesraden: ${codes}</p>` : ''}</div>
      <div class="full"><label for="sOk">Tack-text efter inskick</label><textarea id="sOk" data-s="success" rows="2" maxlength="500">${esc(f.success)}</textarea></div>
    </div>
    ${f.id ? `<p class="hint">Formulärets kod: <code>${esc(f.id)}</code></p>` : ''}
  </section>
  <h2>Fält</h2>
  <div id="fields" style="display:grid;gap:12px">${f.fields.map((x, i) => fieldCard(x, i, f.fields.length)).join('')}</div>
  <div class="bar"><button class="btn" type="button" id="addField">+ Nytt fält</button></div>`;
  $('addField').onclick = () => { f.fields.push(newField()); renderForm(); $('fields').lastElementChild.querySelector('[data-k=label]').focus(); };
  updateBar();
}

const lines = (s) => s.split(/\n/).map(t => t.trim()).filter(Boolean);
$('form').addEventListener('input', (ev) => {
  const f = forms[current], t = ev.target;
  if (t.dataset.s) {
    const k = t.dataset.s;
    f[k] = k === 'active' ? t.checked : k === 'recipients' ? lines(t.value.replace(/[,;\s]+/g, '\n')) : t.value;
    if (k === 'title') renderPick();
  } else if (t.dataset.k) {
    const x = f.fields[+t.closest('[data-i]').dataset.i], k = t.dataset.k;
    if (k === 'type') { x.type = t.value; if (x.type === 'select' && !x.options.length) x.options = ['Alternativ 1']; if (x.type !== 'select') x.options = []; renderForm(); return; }
    x[k] = k === 'required' ? t.checked : k === 'options' ? lines(t.value) : t.value;
  }
  updateBar();
});
$('form').addEventListener('click', (ev) => {
  const code = ev.target.closest('[data-code]');
  if (code) { const s = $('sSubj'); s.value = (s.value + ' {' + code.dataset.code + '}').trim(); s.dispatchEvent(new Event('input', { bubbles: true })); return; }
  const b = ev.target.closest('[data-act]'); if (!b) return;
  const list = forms[current].fields, i = +b.closest('[data-i]').dataset.i;
  if (b.dataset.act === 'up') [list[i - 1], list[i]] = [list[i], list[i - 1]];
  if (b.dataset.act === 'down') [list[i + 1], list[i]] = [list[i], list[i + 1]];
  if (b.dataset.act === 'remove') { if (!confirm(`Ta bort fältet "${list[i].label || 'utan rubrik'}"?`)) return; list.splice(i, 1); }
  renderForm();
});
$('pick').onchange = () => { current = +$('pick').value; renderForm(); };
$('addForm').onclick = () => {
  const title = prompt('Vad ska formuläret heta?', 'Kontakt'); if (!title) return;
  forms.push({ id: '', title: title.trim(), active: true, page: '', intro: '', recipients: [], subject: title.trim() + ': {namn}', button: 'Skicka',
    success: 'Tack! Vi har tagit emot ditt meddelande och hör av oss så snart vi kan.',
    fields: [{ ...newField(), id: 'namn', label: 'Namn', required: true }, { ...newField('email'), id: 'epost', label: 'E-post', required: true },
      { ...newField('textarea'), id: 'meddelande', label: 'Meddelande', required: true }] });
  current = forms.length - 1; renderForm();
};
$('delForm').onclick = () => {
  const f = forms[current]; if (!f) return;
  if (!confirm(`Ta bort formuläret "${f.title}"? Sidor som visar det slutar visa formuläret. Redan inkomna inskick finns kvar.`)) return;
  forms.splice(current, 1); current = 0; renderForm();
};
$('undo').onclick = () => { if (confirm('Ångra alla ändringar sedan du sparade?')) { forms = JSON.parse(saved); current = Math.min(current, forms.length - 1); renderForm(); } };
$('save').onclick = async () => {
  // Nya formulär och fält får en kod utifrån namnet. Befintliga koder ändras aldrig (de används i ämnesraden och på sidorna).
  const formIds = forms.map(f => f.id).filter(Boolean);
  for (const f of forms) {
    if (!f.id) { f.id = uniqueId(slug(f.title, '-'), formIds, '-', 'formular'); formIds.push(f.id); }
    const ids = f.fields.map(x => x.id).filter(Boolean).concat('webbplats');
    for (const x of f.fields) if (!x.id) { x.id = uniqueId(slug(x.label, '_'), ids, '_', 'falt'); ids.push(x.id); }
  }
  $('save').disabled = true; setMsg('Sparar…');
  try {
    const d = await api('forms_save', { forms, base });
    forms = d.forms; base = d.base; saved = state();
    renderForm(); setMsg('Sparat. Ändringarna syns på webbplatsen direkt.', 'good');
  } catch (e) { setMsg(e.message, 'bad'); updateBar(); $('save').disabled = false; }
};
addEventListener('beforeunload', (ev) => { if (dirty()) ev.preventDefault(); });

(async () => {
  try {
    const d = await api('forms');
    forms = d.forms; base = d.base; saved = state();
    renderForm();
  } catch (e) { $('form').innerHTML = `<p class="empty">${esc(e.message)}</p>`; }
  loadInbox();
  if (location.hash === '#andra') showTab(true);
})();
</script>
</body>
</html>
