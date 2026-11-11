<?php
// Modulen "oppettider": ordinarie öppettider och avvikande öppettider (site.json → hours, hoursExceptions).
// Sparas och publiceras direkt. Körs via cms/page.php, som redan har kontrollerat inloggningen.
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Öppettider – <?= h(config()['site_name']) ?></title>
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
  h2{font-size:19px;margin:16px 0 0}
  .lead{color:#6B7280;margin:4px 0 0}
  .card{background:#fff;border:1px solid #E4E0D9;padding:16px 20px 20px}
  .card.off .preview{opacity:.45}
  .groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}
  .top{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin:0 0 12px}
  .top .no{font-weight:700}
  .tools{display:flex;gap:4px;margin-left:auto}
  .check{display:flex;gap:8px;align-items:center;font-weight:600;cursor:pointer}
  .check input{width:18px;height:18px;accent-color:#2563EB}
  .badge{font-size:12px;font-weight:600;padding:2px 8px;border-radius:999px}
  .st-on{background:#DCFCE7;color:#166534}.st-wait{background:#FEF3C7;color:#92400E}.st-off{background:#F3F4F6;color:#6B7280}.st-week{background:#DBEAFE;color:#1E40AF}
  .preview{background:#F5F3EF;color:#404040;border:1px solid #E4E0D9;padding:10px 16px;font-weight:500;line-height:1.4;margin:8px 0 0}
  .preview a{color:var(--accent);font-weight:700}
  label{display:block;font-weight:600;font-size:14px;margin:0 0 4px}
  input[type=text],input[type=date],input[type=datetime-local],textarea{width:100%;height:40px;padding:0 10px;border:1px solid #CFC9BF;font:inherit;background:#fff}
  textarea{height:auto;min-height:60px;padding:8px 10px;resize:vertical}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;margin:12px 0 0}
  .rows{display:grid;gap:6px;margin:0 0 8px}
  .r{display:grid;grid-template-columns:1fr 1fr 38px;gap:6px;align-items:center}
  .r.head{font-size:13px;color:#6B7280;font-weight:600}
  .r .x{width:38px;padding:0;color:#B91C1C}
  .hint{color:#6B7280;font-size:13px;margin:8px 0 0}
  .btn{height:38px;padding:0 14px;border:1px solid #D1D5DB;background:#fff;color:#1F2937;font:inherit;font-weight:600;cursor:pointer;border-radius:6px}
  .btn:hover:not(:disabled){background:#F3F4F6}
  .btn.pri{background:#2563EB;border-color:#2563EB;color:#fff}
  .btn.pri:hover:not(:disabled){background:#1D4ED8}
  .btn.del{color:#B91C1C}
  .btn:disabled{opacity:.45;cursor:default}
  .btn.sm{height:32px;font-size:14px}
  .empty{background:#fff;border:1px dashed #CFC9BF;padding:24px;text-align:center;color:#6B7280}
  .bannerbox{margin:14px 0 0;padding:12px 14px;background:#FAFAF9;border:1px solid #F3F4F6}
  .save{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid #E4E0D9;box-shadow:0 -6px 20px rgba(0,0,0,.08)}
  .save div{max-width:860px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px}
  .save .msg{flex:1;min-width:200px;font-size:14px;color:#6B7280}
  .save .msg.bad{color:#B91C1C}.save .msg.good{color:#166534}.save .msg.warn{color:#B45309;font-weight:600}
  @media (max-width:560px){.grid{grid-template-columns:1fr}.tools{margin-left:0;width:100%}}
</style>
</head>
<body>
<header><div><strong>Öppettider</strong><a href="<?= h(edit_url()) ?>">← Till redigeringsläget</a></div></header>
<main>
  <div>
    <h1>Öppettider</h1>
    <p class="lead">Ordinarie öppettider och avvikelser, till exempel helgdagar och lov. Ändringarna syns på webbplatsen så fort du sparar.</p>
  </div>
  <h2>Ordinarie öppettider</h2>
  <div class="groups" id="groups"><p class="empty">Hämtar…</p></div>
  <div><button class="btn" type="button" id="addGroup">+ Ny grupp</button></div>

  <h2>Avvikande öppettider</h2>
  <p class="lead" style="margin:0">Avvikelserna visas vid öppettiderna den vecka (måndag–söndag) som dagarna infaller, så länge de är publicerade. Kryssa i <em>Visa som banner</em> för att också visa dem överst på alla sidor.</p>
  <div id="list"><p class="empty">Hämtar…</p></div>
  <div><button class="btn" type="button" id="add">+ Ny avvikelse</button></div>
</main>
<div class="save"><div>
  <span class="msg" id="msg" role="status"></span>
  <button class="btn" type="button" id="undo" disabled>Ångra ändringar</button>
  <button class="btn pri" type="button" id="save" disabled>Spara och publicera</button>
</div></div>
<script>
const csrf = <?= json_encode(csrf_token()) ?>;
const MAX = 20, GROUPS_MAX = 4, ROWS_MAX = 10, DAYS_MAX = 21;
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const pad = (n) => String(n).padStart(2, '0');
const toLocal = (iso) => { if (!iso) return ''; const d = new Date(iso); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`; };
const fromLocal = (v) => v ? new Date(v).toISOString() : '';
const groupsEl = document.getElementById('groups'), listEl = document.getElementById('list'), msg = document.getElementById('msg');
const saveBtn = document.getElementById('save'), undoBtn = document.getElementById('undo'), addBtn = document.getElementById('add'), addGroupBtn = document.getElementById('addGroup');
let hours = [], list = [], saved = '', base = '';

async function api(action, body) {
  const r = await fetch('api.php?action=' + action, { method: body ? 'POST' : 'GET',
    headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const d = await r.json().catch(() => ({ error: 'Oväntat svar från servern.' }));
  if (r.status === 401) { location.href = 'index.php'; throw new Error(d.error); }
  if (!r.ok) throw new Error(d.error || 'Något gick fel.');
  return d;
}

// Samma regler som webbplatsen: tomt slut = dagen efter sista avvikande dagen.
const lastDay = (x) => x.days.map(d => d.date).filter(Boolean).sort().pop();
const endOf = (x) => x.end ? Date.parse(x.end) : (lastDay(x) ? new Date(lastDay(x) + 'T00:00:00').getTime() + 86400000 : Infinity);
// Samma format som webbplatsen: "fre 30 okt".
const shortDate = (iso) => { const d = new Date(iso + 'T12:00:00'); return ['sön', 'mån', 'tis', 'ons', 'tor', 'fre', 'lör'][d.getDay()] + ' ' + d.getDate() + ' ' + ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'][d.getMonth()]; };
const defaultBanner = (x) => (x.title || 'Avvikande öppettider') + ': ' + x.days.filter(d => d.date).map(d => shortDate(d.date) + ' ' + d.time).join(', ');
function status(x) {
  const now = Date.now();
  if (!x.enabled) return ['Inaktiv', 'off'];
  if (x.start && Date.parse(x.start) > now) return ['Schemalagd', 'wait'];
  if (endOf(x) <= now) return ['Utgången', 'off'];
  return ['Publicerad', 'on'];
}
// Visas den i sidfoten den här veckan?
function thisWeek(x) {
  const d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() - (d.getDay() + 6) % 7);
  const from = d.getTime(), to = from + 7 * 86400000;
  return x.days.some(day => { const t = new Date(day.date + 'T12:00:00').getTime(); return t >= from && t < to; });
}

const state = () => JSON.stringify({ hours, list });
const dirty = () => state() !== saved;
function setMsg(text, cls = '') { msg.textContent = text; msg.className = 'msg ' + cls; }
function updateBar() {
  saveBtn.disabled = undoBtn.disabled = !dirty();
  addBtn.disabled = list.length >= MAX;
  addGroupBtn.disabled = hours.length >= GROUPS_MAX;
  if (dirty()) setMsg('Du har ändringar som inte är sparade.', 'warn');
  else if (msg.classList.contains('warn')) setMsg('');
}

function renderGroups() {
  groupsEl.innerHTML = hours.map((g, i) => `<section class="card" data-g="${i}">
    <label for="gt${i}">Rubrik</label>
    <input id="gt${i}" type="text" data-k="title" maxlength="80" value="${esc(g.title)}" style="margin:0 0 12px">
    <div class="rows">
      <div class="r head"><span>Dag</span><span>Tid</span><span></span></div>
      ${g.rows.map((r, n) => `<div class="r" data-r="${n}">
        <input type="text" data-c="0" maxlength="40" value="${esc(r[0])}" aria-label="Dag, rad ${n + 1}" placeholder="Måndag">
        <input type="text" data-c="1" maxlength="60" value="${esc(r[1])}" aria-label="Tid, rad ${n + 1}" placeholder="08:30–16:00">
        <button class="btn x" type="button" data-m="delrow" aria-label="Ta bort rad ${n + 1}" ${g.rows.length < 2 ? 'disabled' : ''}>✕</button></div>`).join('')}
    </div>
    <div class="top" style="margin:0">
      <button class="btn sm" type="button" data-m="addrow" ${g.rows.length >= ROWS_MAX ? 'disabled' : ''}>+ Rad</button>
      <div class="tools"><button class="btn sm del" type="button" data-m="delgroup" ${hours.length < 2 ? 'disabled' : ''}>Ta bort gruppen</button></div>
    </div>
  </section>`).join('');
}

function renderList() {
  listEl.innerHTML = list.length ? list.map((x, i) => {
    const [st, cls] = status(x);
    const week = cls === 'on' && thisWeek(x);
    return `<section class="card${cls === 'on' ? '' : ' off'}" data-i="${i}" style="margin-bottom:16px">
      <div class="top">
        <span class="no">Avvikelse ${i + 1}</span>
        <label class="check"><input type="checkbox" data-k="enabled" ${x.enabled ? 'checked' : ''}> Aktiv</label>
        <span class="badge st-${cls}">${st}</span><span class="badge st-week" ${week ? '' : 'hidden'}>Visas vid öppettiderna nu</span>
        <div class="tools">
          <button class="btn" type="button" data-m="up" aria-label="Flytta upp" ${i === 0 ? 'disabled' : ''}>↑</button>
          <button class="btn" type="button" data-m="down" aria-label="Flytta ner" ${i === list.length - 1 ? 'disabled' : ''}>↓</button>
          <button class="btn del" type="button" data-m="del">Ta bort</button>
        </div>
      </div>
      <label for="t${i}">Rubrik</label>
      <input id="t${i}" type="text" data-k="title" maxlength="80" value="${esc(x.title)}" placeholder="Till exempel Allhelgonahelgen" style="margin:0 0 12px">
      <div class="rows">
        <div class="r head"><span>Datum</span><span>Tid eller Stängt</span><span></span></div>
        ${x.days.map((d, n) => `<div class="r" data-d="${n}">
          <input type="date" data-c="date" value="${esc(d.date)}" aria-label="Datum, dag ${n + 1}">
          <input type="text" data-c="time" maxlength="60" value="${esc(d.time)}" aria-label="Tid, dag ${n + 1}" placeholder="Stängt eller 08:30–12:00">
          <button class="btn x" type="button" data-m="delday" aria-label="Ta bort dag ${n + 1}" ${x.days.length < 2 ? 'disabled' : ''}>✕</button></div>`).join('')}
      </div>
      <button class="btn sm" type="button" data-m="addday" ${x.days.length >= DAYS_MAX ? 'disabled' : ''}>+ Dag</button>
      <div class="grid">
        <div><label for="s${i}">Publiceras</label><input id="s${i}" type="datetime-local" data-k="start" value="${toLocal(x.start)}"></div>
        <div><label for="e${i}">Avpubliceras</label><input id="e${i}" type="datetime-local" data-k="end" value="${toLocal(x.end)}"></div>
      </div>
      <p class="hint">Tom publicering = direkt. Tom avpublicering = när sista dagen är slut.</p>
      <div class="bannerbox">
        <label class="check"><input type="checkbox" data-k="banner" ${x.banner ? 'checked' : ''}> Visa som banner överst på sidan</label>
        ${x.banner ? `<label for="b${i}" style="margin-top:10px">Bannertext (valfri)</label>
          <textarea id="b${i}" data-k="bannerText" maxlength="300" rows="2" placeholder="${esc(defaultBanner(x))}">${esc(x.bannerText)}</textarea>
          <div class="preview" aria-hidden="true">${esc(x.bannerText || defaultBanner(x))} <a>Se öppettider →</a></div>
          <p class="hint">Bannern visas så länge avvikelsen är publicerad. Lämnas texten tom används rubriken och dagarna.</p>` : ''}
      </div>
    </section>`;
  }).join('') : '<p class="empty">Inga avvikande öppettider. Tryck <strong>+ Ny avvikelse</strong> för att lägga till.</p>';
}

function render() { renderGroups(); renderList(); updateBar(); }

// Fälten uppdaterar datan direkt. Bara när något påverkar status eller förhandsvisning ritas kortet om (vid change),
// så att markören inte hoppar medan man skriver.
groupsEl.addEventListener('input', (e) => {
  const g = hours[+e.target.closest('[data-g]').dataset.g];
  if (e.target.dataset.k === 'title') g.title = e.target.value;
  else if (e.target.dataset.c) g.rows[+e.target.closest('[data-r]').dataset.r][+e.target.dataset.c] = e.target.value;
  updateBar();
});
groupsEl.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-m]');
  if (!b) return;
  const i = +b.closest('[data-g]').dataset.g, m = b.dataset.m;
  if (m === 'addrow') hours[i].rows.push(['', '']);
  if (m === 'delrow') hours[i].rows.splice(+b.closest('[data-r]').dataset.r, 1);
  if (m === 'delgroup') { if (!confirm('Ta bort gruppen "' + hours[i].title + '"?')) return; hours.splice(i, 1); }
  render();
});
addGroupBtn.onclick = () => { hours.push({ title: '', rows: [['', '']] }); render(); groupsEl.querySelector('.card:last-child input').focus(); };

listEl.addEventListener('input', (e) => {
  const card = e.target.closest('[data-i]');
  if (!card) return;
  const x = list[+card.dataset.i], k = e.target.dataset.k, c = e.target.dataset.c;
  if (c) x.days[+e.target.closest('[data-d]').dataset.d][c] = e.target.value;
  else if (k === 'enabled' || k === 'banner') x[k] = e.target.checked;
  else if (k === 'start' || k === 'end') x[k] = fromLocal(e.target.value);
  else if (k) x[k] = e.target.value;
  if (e.target.type === 'checkbox') renderList();
  else refreshCard(card, x);
  updateBar();
});
// Status och bannerförhandsvisning utan att rita om fälten.
function refreshCard(card, x) {
  const [st, cls] = status(x);
  const badge = card.querySelector('.badge');
  badge.textContent = st;
  badge.className = 'badge st-' + cls;
  card.classList.toggle('off', cls !== 'on');
  const week = card.querySelector('.st-week');
  if (week) week.hidden = !(cls === 'on' && thisWeek(x));
  const pv = card.querySelector('.preview'), ta = card.querySelector('textarea');
  if (pv) pv.firstChild.textContent = (x.bannerText || defaultBanner(x)) + ' ';
  if (ta) ta.placeholder = defaultBanner(x);
}

listEl.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-m]');
  if (!b) return;
  const i = +b.closest('[data-i]').dataset.i, m = b.dataset.m, x = list[i];
  if (m === 'del') { if (!confirm('Ta bort avvikelse ' + (i + 1) + '?')) return; list.splice(i, 1); }
  else if (m === 'addday') {
    // Nästa dag efter den sista, som förslag.
    const last = lastDay(x);
    let next = '';
    if (last) { const d = new Date(last + 'T12:00:00'); d.setDate(d.getDate() + 1); next = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }
    x.days.push({ date: next, time: x.days[x.days.length - 1]?.time || '' });
  }
  else if (m === 'delday') x.days.splice(+b.closest('[data-d]').dataset.d, 1);
  else { const j = m === 'up' ? i - 1 : i + 1; [list[i], list[j]] = [list[j], list[i]]; }
  render();
});

addBtn.onclick = () => {
  list.push({ id: 'h' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6), title: '', days: [{ date: '', time: 'Stängt' }],
    enabled: true, start: '', end: '', banner: false, bannerText: '' });
  render();
  listEl.querySelector('.card:last-child input[type=text]').focus();
};

undoBtn.onclick = () => {
  if (!confirm('Ta bort alla ändringar sedan du senast sparade?')) return;
  ({ hours, list } = JSON.parse(saved));
  render();
};

function problem() {
  for (const [i, g] of hours.entries()) {
    const name = 'Ordinarie öppettider, grupp ' + (i + 1);
    if (!g.title) return name + ' saknar rubrik.';
    if (g.rows.some(r => !r[0] || !r[1])) return name + ' har en rad utan dag eller tid.';
  }
  for (const [i, x] of list.entries()) {
    const name = 'Avvikelse ' + (i + 1);
    if (!x.title) return name + ' saknar rubrik.';
    if (x.days.some(d => !d.date)) return name + ' har en dag utan datum.';
    if (x.days.some(d => !d.time)) return name + ' har en dag utan tid (skriv t.ex. Stängt).';
    if (new Set(x.days.map(d => d.date)).size !== x.days.length) return name + ' har samma datum två gånger.';
    if (x.start && x.end && Date.parse(x.end) <= Date.parse(x.start)) return name + ': avpubliceringen måste vara efter publiceringen.';
  }
  return null;
}

saveBtn.onclick = async () => {
  const clean = (s) => s.replace(/\s+/g, ' ').trim();
  hours = hours.map(g => ({ title: clean(g.title), rows: g.rows.map(r => [clean(r[0]), clean(r[1])]) }));
  list = list.map(x => ({ ...x, title: clean(x.title), bannerText: clean(x.bannerText),
    days: x.days.map(d => ({ date: d.date, time: clean(d.time) })).sort((a, b) => a.date < b.date ? -1 : 1) }));
  const p = problem();
  if (p) { render(); setMsg(p, 'bad'); return; }
  saveBtn.disabled = true;
  setMsg('Sparar…');
  try {
    load(await api('hours_save', { hours, exceptions: list, base }));
    setMsg('Sparat och publicerat.', 'good');
  } catch (x) { setMsg(x.message, 'bad'); saveBtn.disabled = false; }
};

function load(d) {
  hours = d.hours;
  list = d.exceptions;
  saved = state();
  base = d.base;
  render();
}

addEventListener('beforeunload', (e) => { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });
// Status (Schemalagd → Publicerad) uppdateras varje minut.
setInterval(() => { if (!listEl.contains(document.activeElement)) renderList(); }, 60000);

api('hours').then(load).catch(x => { groupsEl.innerHTML = listEl.innerHTML = `<p class="empty">${esc(x.message)}</p>`; });
</script>
</body>
</html>
