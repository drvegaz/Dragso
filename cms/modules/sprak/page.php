<?php
// Modulen "sprak": språk och översättning. Körs via cms/page.php, som redan har kontrollerat inloggningen.
// Flikar: Språk (lägga till, visa/dölja, ta bort), Översätt (huvudspråket bredvid översättningen, fält för fält,
// och översättning med AI: kopiera instruktion, klistra in svar, granska förslag) och Ordlista (ord, ton, beskrivning).
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Språk – <?= h(config()['site_name']) ?></title>
<link rel="icon" href="../favicon.ico?v=2" sizes="any">
<link rel="icon" href="../favicon.svg?v=2" type="image/svg+xml">
<style>
  <?= brand_css() ?>
  *{box-sizing:border-box}
  body{margin:0;background:#F5F3EF;color:#1F2937;font:15px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif}
  header{background:#404040;color:#fff}
  header div{max-width:1100px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between}
  header a{color:#fff}
  main{max-width:1100px;margin:0 auto;padding:24px 16px 120px;display:grid;gap:16px}
  section{display:grid;gap:12px;align-content:start}
  h1{font-size:24px;margin:0}
  h2{font-size:18px;margin:0}
  .lead{color:#6B7280;margin:4px 0 0;max-width:70ch}
  .tabs{display:flex;gap:4px;border-bottom:1px solid #E4E0D9}
  .tabs a{padding:10px 16px;color:#404040;text-decoration:none;font-weight:600;border-bottom:3px solid transparent;margin-bottom:-1px}
  .tabs a[aria-current=page]{border-color:var(--accent);color:#111827}
  .card{background:#fff;border:1px solid #E4E0D9;padding:16px 20px}
  .lang{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px 16px;align-items:center}
  .lang .name{font-weight:700;font-size:17px}
  .lang .name small{font-weight:400;color:#6B7280;margin-left:6px}
  .lang .tools{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}
  .bar{height:8px;background:#F3F4F6;border-radius:4px;overflow:hidden;display:flex;margin:8px 0 4px;max-width:420px}
  .bar i{display:block;height:100%}
  .bar .d{background:#16A34A}.bar .c{background:#F59E0B}
  .stats{font-size:14px;color:#6B7280}
  .badge{display:inline-block;font-size:12px;font-weight:600;padding:2px 8px;border-radius:999px;vertical-align:middle;margin-left:6px}
  .st-pub{background:#DCFCE7;color:#166534}.st-hid{background:#F3F4F6;color:#6B7280}
  .st-done{background:#DCFCE7;color:#166534}.st-changed{background:#FEF3C7;color:#92400E}.st-missing{background:#FEE2E2;color:#991B1B}
  .st-sugg{background:#DBEAFE;color:#1E40AF}
  .add{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
  select,input[type=search],input[type=text]{height:38px;padding:0 10px;border:1px solid #CFC9BF;font:inherit;background:#fff;max-width:100%}
  textarea{width:100%;padding:8px 10px;border:1px solid #CFC9BF;font:inherit;resize:vertical;background:#fff}
  textarea:focus,input:focus,select:focus{outline:2px solid var(--accent);outline-offset:0}
  label.l{display:block;font-weight:600;font-size:14px;margin:0 0 4px}
  .btn{height:38px;padding:0 14px;border:1px solid #D1D5DB;background:#fff;color:#1F2937;font:inherit;font-weight:600;cursor:pointer;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;white-space:nowrap}
  .btn:hover:not(:disabled){background:#F3F4F6}
  .btn.pri{background:var(--accent);border-color:var(--accent);color:#fff}
  .btn.pri:hover:not(:disabled){background:var(--accent-dark)}
  .btn.del{color:#B91C1C}
  .btn:disabled{opacity:.45;cursor:default}
  .btn.sm{height:30px;padding:0 10px;font-size:13px}
  .empty{background:#fff;border:1px dashed #CFC9BF;padding:24px;text-align:center;color:#6B7280;margin:0}
  .hint{color:#6B7280;font-size:13px;margin:6px 0 0}
  /* AI */
  .ai summary{cursor:pointer;font-weight:700;font-size:17px}
  .ai ol{margin:10px 0 14px;padding-left:20px;color:#374151}
  .ai .parts{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0}
  .ai .done-copy{color:#166534;font-weight:600}
  .report{margin:12px 0 0;padding:12px 14px;background:#F0F9FF;border:1px solid #BAE6FD}
  .report.bad{background:#FEF2F2;border-color:#FECACA}
  .report ul{margin:6px 0 0;padding-left:20px}
  /* Översätt */
  .toolbar{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:center;position:sticky;top:0;z-index:5;background:#F5F3EF;padding:8px 0}
  .filters{display:flex;flex-wrap:wrap;gap:4px}
  .filters button{height:32px;padding:0 12px;border:1px solid #D1D5DB;border-radius:999px;background:#fff;font:inherit;font-size:14px;cursor:pointer}
  .filters button[aria-pressed=true]{background:#1F2937;border-color:#1F2937;color:#fff}
  .toolbar input[type=search]{flex:1;min-width:180px}
  .group{display:grid;gap:0}
  .ghead{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;padding:20px 0 8px;border-bottom:2px solid #1F2937}
  .row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;padding:14px 0;border-bottom:1px solid #E4E0D9}
  .row.saved-flash{background:#F0FDF4}
  .row.has-sugg{background:#F8FAFF}
  .src{white-space:pre-wrap;overflow-wrap:anywhere}
  .lbl{display:block;font-size:12px;color:#6B7280;margin-bottom:2px}
  .diff{margin-top:8px;padding:8px 10px;background:#FFFBEB;border:1px solid #FDE68A;font-size:14px;white-space:pre-wrap;overflow-wrap:anywhere}
  .diff del{background:#FECACA;text-decoration:line-through}
  .diff ins{background:#BBF7D0;text-decoration:none}
  .tgt textarea{min-height:42px}
  .tgt .acts{display:flex;flex-wrap:wrap;gap:6px 8px;align-items:center;margin-top:6px}
  .tgt .acts a.show{margin-left:auto;font-size:13px;color:#4B5563}
  .live{font-size:13px;color:#4B5563;margin:0 0 6px;white-space:pre-wrap;overflow-wrap:anywhere}
  .warn{margin:6px 0 0;padding:6px 10px;background:#FFFBEB;border:1px solid #FDE68A;font-size:13px;color:#92400E;list-style:none}
  .who{font-size:12px;color:#9CA3AF}
  .btn.ghost{border-color:transparent;background:transparent;color:#6B7280;font-weight:500}
  /* Färgade rader (förslag, nyss sparad, undantag): luft åt sidorna, och bakgrunden dras ut lika mycket så att texten står kvar i linje med rubriken. */
  .row.has-sugg,.row.saved-flash,.row.skipped{padding-left:14px;padding-right:14px;margin:0 -14px}
  .row.skipped{background:#FAFAF9}
  .btn.ghost:hover:not(:disabled){color:#1F2937;background:#F3F4F6}
  .row.skipped .src{color:#6B7280}
  /* Ordlista */
  .gl{display:grid;gap:10px}
  .ge{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) minmax(0,2fr) 38px;gap:8px;align-items:start;padding:10px 0;border-top:1px solid #F3F4F6}
  .ge input,.ge select{width:100%}
  .ge .more{display:grid;gap:6px}
  .ge .more label{display:grid;grid-template-columns:90px minmax(0,1fr);gap:6px;align-items:center;font-size:13px;color:#4B5563}
  .ge .x{width:38px;padding:0;justify-content:center;color:#B91C1C}
  .gh{font-size:13px;color:#6B7280;font-weight:600}
  .tone{display:grid;grid-template-columns:120px minmax(0,1fr);gap:8px;align-items:start;margin-top:8px}
  .savebar{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid #E4E0D9;box-shadow:0 -6px 20px rgba(0,0,0,.08);z-index:10}
  .savebar div{max-width:1100px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px}
  .savebar .m{flex:1;min-width:200px;font-size:14px;color:#6B7280}
  .savebar .m.warnm{color:#B45309;font-weight:600}
  .msg{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);max-width:calc(100% - 32px);background:#111827;color:#fff;padding:10px 16px;border-radius:8px;font-size:14px;z-index:20}
  .msg.bad{background:#B91C1C}
  [hidden]{display:none!important}
  @media (max-width:760px){.row{grid-template-columns:1fr;gap:8px}.lang{grid-template-columns:1fr}.lang .tools{justify-content:flex-start}
    .ge{grid-template-columns:minmax(0,1fr) 38px}.ge>*:not(.x):not(:first-child){grid-column:1}.tone{grid-template-columns:1fr}}
</style>
</head>
<body>
<header><div><strong>Språk</strong><a href="<?= h(edit_url()) ?>">← Till redigeringsläget</a></div></header>
<main>
  <div>
    <h1>Språk och översättning</h1>
    <p class="lead">Lägg till språk och översätt webbplatsens texter. Texter som inte är översatta visas på huvudspråket.</p>
  </div>
  <nav class="tabs"><a href="#sprak" id="tab-sprak">Språk</a><a href="#oversatt" id="tab-oversatt">Översätt</a><a href="#ordlista" id="tab-ordlista">Ordlista</a></nav>

  <!-- ===== Språk ===== -->
  <section id="view-sprak" hidden>
    <div id="langs" style="display:grid;gap:12px"><p class="empty">Hämtar…</p></div>
    <div class="card">
      <h2>Lägg till språk</h2>
      <div class="add" style="margin-top:10px">
        <select id="addCode" aria-label="Språk att lägga till"></select>
        <button class="btn pri" type="button" id="addBtn">Lägg till</button>
      </div>
      <p class="hint">Ett nytt språk syns inte på webbplatsen förrän du väljer <em>Visa på webbplatsen</em>. Du kan visa det innan allt är översatt; det som saknas visas då på huvudspråket.</p>
    </div>
  </section>

  <!-- ===== Översätt ===== -->
  <section id="view-oversatt" hidden>
    <details class="card ai" id="ai">
      <summary>Översätt med AI</summary>
      <ol>
        <li>Välj vad som ska översättas och tryck på knappen. Instruktionen kopieras.</li>
        <li>Öppna en <strong>ny chatt</strong> i ChatGPT, Claude, Gemini eller Copilot och klistra in den.</li>
        <li>Kopiera hela svaret (med kopieringsknappen under svaret), klistra in det här under och tryck <strong>Läs in svar</strong>. Svaren blir förslag i listan, och inget syns på webbplatsen förrän du har godkänt dem.</li>
      </ol>
      <div class="add">
        <select id="aiScope" aria-label="Vad ska översättas"></select>
        <button class="btn pri" type="button" id="aiCopy">Kopiera instruktioner till AI</button>
      </div>
      <div id="aiParts"></div>
      <label class="l" for="aiAnswer" style="margin-top:14px">Svaret från AI</label>
      <textarea id="aiAnswer" rows="4" placeholder="Klistra in svaret från AI här …"></textarea>
      <div class="add" style="margin-top:8px"><button class="btn pri" type="button" id="aiRead">Läs in svar</button></div>
      <div id="aiReport"></div>
    </details>
    <div class="toolbar">
      <select id="lang" aria-label="Översätt till"></select>
      <div class="filters" id="filters" role="group" aria-label="Visa">
        <button type="button" data-f="all" aria-pressed="true">Alla</button>
        <button type="button" data-f="suggestion" aria-pressed="false">Förslag</button>
        <button type="button" data-f="missing" aria-pressed="false">Saknas</button>
        <button type="button" data-f="changed" aria-pressed="false">Ändrade</button>
        <button type="button" data-f="done" aria-pressed="false">Klara</button>
        <button type="button" data-f="skipped" aria-pressed="false">Översätts aldrig</button>
      </div>
      <input type="search" id="q" placeholder="Sök i texterna" aria-label="Sök i texterna">
    </div>
    <p class="hint" style="margin:-4px 0 0">Tryck <strong>Översätt aldrig</strong> på texter som ska se likadana ut på alla språk, t.ex. namn, adresser och användarnamn. De visas på huvudspråket, räknas inte som saknade och skickas inte till AI:n. Telefonnummer, e-post- och webbadresser hoppas över automatiskt. Allt går att ångra under <em>Översätts aldrig</em>.</p>
    <div id="fields"><p class="empty">Hämtar…</p></div>
  </section>

  <!-- ===== Ordlista ===== -->
  <section id="view-ordlista" hidden>
    <p class="lead" style="margin:0">Det här följer med instruktionen till AI:n, så att översättningarna blir som ni vill.</p>
    <div class="card">
      <h2>Om webbplatsen</h2>
      <p class="hint" style="margin:2px 0 8px">En eller två meningar om vad webbplatsen handlar om och vem den är till för.</p>
      <textarea id="gAbout" rows="3" maxlength="1000" placeholder="Till exempel: Camping och stugby på en egen ö i Karlskronas skärgård. Gästerna är familjer, husbilsresenärer och par."></textarea>
    </div>
    <div class="card">
      <h2>Ton per språk</h2>
      <p class="hint" style="margin:2px 0 0">Hur texterna ska låta, t.ex. tilltal och hur priser skrivs. Tomt = vänlig och enkel ton, som på huvudspråket.</p>
      <div id="gTone"></div>
    </div>
    <div class="card">
      <h2>Ordlista</h2>
      <p class="hint" style="margin:2px 0 8px">Namn och ord som ska behandlas på ett visst sätt. <em>Översätts inte</em> passar för namn. <em>Fast översättning</em> när ni har bestämt ett ord. <em>Förklaring</em> när ett ord är svårt att förstå (t.ex. pullmansäng: en säng som fälls ner från väggen).</p>
      <div class="gl" id="gList"></div>
      <div style="margin-top:10px"><button class="btn" type="button" id="gAdd">+ Nytt ord</button></div>
    </div>
  </section>
</main>
<div class="savebar" id="gBar" hidden><div>
  <span class="m" id="gMsg" role="status"></span>
  <button class="btn" type="button" id="gUndo" disabled>Ångra ändringar</button>
  <button class="btn pri" type="button" id="gSave" disabled>Spara ordlistan</button>
</div></div>
<div class="msg" id="msg" role="status" hidden></div>
<script>
const csrf = <?= json_encode(csrf_token()) ?>;
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const $ = (id) => document.getElementById(id);
const STATUS = { done: 'Klar', changed: 'Ändrad', missing: 'Saknas' };
const FILTER_NAMES = { all: 'Alla', suggestion: 'Förslag', missing: 'Saknas', changed: 'Ändrade', done: 'Klara', skipped: 'Översätts aldrig' };
const SKIP_REASON = { manual: 'Översätts aldrig', phone: 'Automatiskt: bara siffror, t.ex. ett telefonnummer', email: 'Automatiskt: e-postadress', url: 'Automatiskt: webbadress' };

async function api(action, body, query = '') {
  const r = await fetch('api.php?action=' + action + query, { method: body ? 'POST' : 'GET',
    headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const d = await r.json().catch(() => ({ error: 'Oväntat svar från servern.' }));
  if (r.status === 401) { location.href = 'index.php'; throw new Error(d.error); }
  if (!r.ok) throw new Error(d.error || 'Något gick fel.');
  return d;
}

let msgTimer;
function say(text, bad) {
  const m = $('msg');
  m.textContent = text;
  m.className = 'msg' + (bad ? ' bad' : '');
  m.hidden = false;
  clearTimeout(msgTimer);
  msgTimer = setTimeout(() => { m.hidden = true; }, bad ? 7000 : 3500);
}

// ---------- Flikar: #sprak, #oversatt-<kod>, #ordlista ----------
const TABS = ['sprak', 'oversatt', 'ordlista'];
let lastHash = '';
function route() {
  const h = location.hash.slice(1);
  const tab = TABS.find(t => h.startsWith(t)) || 'sprak';
  for (const t of TABS) {
    $('view-' + t).hidden = t !== tab;
    if (t === tab) $('tab-' + t).setAttribute('aria-current', 'page'); else $('tab-' + t).removeAttribute('aria-current');
  }
  $('gBar').hidden = tab !== 'ordlista';
  if (tab === 'sprak' && overview) renderLangs();
  if (tab === 'oversatt') openLang(h.split('-')[1] || (cur && cur.code) || '');
  if (tab === 'ordlista') openGlossary();
  lastHash = location.hash;
}
const unsaved = () => dirtyCount() > 0 || gDirty();
addEventListener('hashchange', () => {
  if (unsaved() && !confirm('Du har ändringar som inte är sparade. Lämna dem?')) { history.replaceState(null, '', lastHash); return; }
  route();
});
addEventListener('beforeunload', (e) => { if (unsaved()) { e.preventDefault(); e.returnValue = ''; } });

// ---------- Språk ----------
let overview = null;
const mainLower = () => overview.main.name.toLowerCase();
function pct(n, total) { return total ? (100 * n / total) + '%' : '0'; }

function renderLangs() {
  const o = overview;
  const main = `<div class="card"><div class="name" style="font-weight:700;font-size:17px">${esc(o.main.name)} <small style="font-weight:400;color:#6B7280">huvudspråk</small></div>
    <p class="hint" style="margin:4px 0 0">Det här är språket du skriver på i redigeringsläget. De andra språken översätts från det.</p></div>`;
  const list = o.languages.map(l => `<div class="card lang" data-code="${esc(l.code)}">
    <div>
      <div class="name">${esc(l.name)} <span class="badge ${l.published ? 'st-pub' : 'st-hid'}">${l.published ? 'Visas på webbplatsen' : 'Dold'}</span></div>
      <div class="bar" aria-hidden="true"><i class="d" style="width:${pct(l.done, l.total)}"></i><i class="c" style="width:${pct(l.changed, l.total)}"></i></div>
      <div class="stats">${l.done} av ${l.total} klara${l.changed ? ` · ${l.changed} ändrade i ${esc(mainLower())}` : ''}${l.missing ? ` · ${l.missing} saknas` : ''}${l.suggestions ? ` · <strong>${l.suggestions} förslag att granska</strong>` : ''}</div>
    </div>
    <div class="tools">
      <a class="btn pri" href="#oversatt-${esc(l.code)}">Översätt</a>
      <button class="btn" type="button" data-m="publish">${l.published ? 'Dölj' : 'Visa på webbplatsen'}</button>
      <button class="btn del" type="button" data-m="remove">Ta bort</button>
    </div></div>`).join('');
  $('langs').innerHTML = main + (list || '<p class="empty">Inga fler språk än. Lägg till ett nedan.</p>');
  $('addCode').innerHTML = o.available.map(a => `<option value="${esc(a.code)}">${esc(a.name)}</option>`).join('');
  const keep = $('lang').value;
  $('lang').innerHTML = o.languages.map(l => `<option value="${esc(l.code)}">Översätt till ${esc(l.name)}</option>`).join('');
  if (keep) $('lang').value = keep;
}

$('langs').addEventListener('click', async (e) => {
  const b = e.target.closest('button[data-m]');
  if (!b) return;
  const code = b.closest('[data-code]').dataset.code;
  const l = overview.languages.find(x => x.code === code);
  try {
    if (b.dataset.m === 'publish') {
      if (!l.published && l.missing + l.changed > 0 &&
        !confirm(`${l.missing} texter saknar översättning och visas på ${mainLower()}${l.changed ? `, och ${l.changed} är ändrade sedan de översattes` : ''}. Visa ${l.name} på webbplatsen ändå?`)) return;
      overview = await api('i18n_language_publish', { code, published: !l.published });
      say(l.published ? l.name + ' är dold.' : l.name + ' visas nu på webbplatsen.');
    } else {
      if (!confirm(`Ta bort ${l.name}? Språket försvinner från webbplatsen. Översättningarna sparas, så de kommer tillbaka om du lägger till ${l.name} igen.`)) return;
      overview = await api('i18n_language_remove', { code });
      if (cur && cur.code === code) cur = null;
      say(l.name + ' är borttagen.');
    }
    renderLangs();
  } catch (x) { say(x.message, true); }
});

$('addBtn').onclick = async () => {
  const code = $('addCode').value;
  if (!code) return;
  try {
    overview = await api('i18n_language_add', { code });
    renderLangs();
    say(overview.languages.find(l => l.code === code).name + ' är tillagd. Den syns inte på webbplatsen förrän du väljer det.');
  } catch (x) { say(x.message, true); }
};

async function refreshOverview() {
  try { overview = await api('i18n_overview'); } catch (x) { /* visas nästa gång */ }
}

// ---------- Översätt ----------
let cur = null;          // { code, name, groups, fields, … }
let filter = 'all';

// Ord för ord: vad som tagits bort (del) och lagts till (ins) i huvudspråket sedan översättningen.
function diff(a, b) {
  const A = a.split(/(\s+)/), B = b.split(/(\s+)/);
  const n = A.length, m = B.length;
  if (n * m > 400000) return `<del>${esc(a)}</del> <ins>${esc(b)}</ins>`;
  const L = Array.from({ length: n + 1 }, () => new Uint16Array(m + 1));
  for (let i = n - 1; i >= 0; i--) for (let j = m - 1; j >= 0; j--) L[i][j] = A[i] === B[j] ? L[i + 1][j + 1] + 1 : Math.max(L[i + 1][j], L[i][j + 1]);
  let out = '', i = 0, j = 0;
  while (i < n && j < m) {
    if (A[i] === B[j]) { out += esc(A[i]); i++; j++; }
    else if (L[i + 1][j] >= L[i][j + 1]) out += `<del>${esc(A[i++])}</del>`;
    else out += `<ins>${esc(B[j++])}</ins>`;
  }
  while (i < n) out += `<del>${esc(A[i++])}</del>`;
  while (j < m) out += `<ins>${esc(B[j++])}</ins>`;
  return out;
}

function showUrl(f) {
  const g = cur.groups.find(x => x.id === f.group) || { url: '', hash: '' };
  return g.url + '&focus=' + encodeURIComponent(f.path) + g.hash;
}
const rows = (len) => Math.min(12, Math.max(2, Math.ceil(len / 60)));
// Det som står i rutan från början: förslaget om det finns, annars den godkända texten.
const baseline = (f) => f.suggestion ? f.suggestion.text : f.text;

function fieldHtml(f, i) {
  const s = f.suggestion;
  const who = !s && f.at ? `<span class="who">${esc(f.by || '')} ${esc(f.at.slice(0, 10))}</span>` : '';
  const warns = s ? [...(s.sourceChanged ? [`${overview.main.name} har ändrats sedan förslaget gjordes. Jämför med texten till vänster.`] : []), ...s.warnings] : [];
  return `<div class="row${s ? ' has-sugg' : ''}" data-i="${i}">
    <div class="src"><span class="lbl">${esc(overview.main.name)}</span>${esc(f.source)}${f.status === 'changed'
      ? `<div class="diff"><span class="lbl">Ändrat sedan översättningen:</span>${diff(f.oldSource, f.source)}</div>` : ''}</div>
    <div class="tgt"><label class="lbl" for="t${i}">${esc(cur.name)}${s ? ' – förslag från AI' : ''}</label>
      ${s && f.text ? `<p class="live"><span class="lbl">Nu på webbplatsen:</span>${esc(f.text)}</p>` : ''}
      <textarea id="t${i}" rows="${rows(Math.max(f.source.length, baseline(f).length))}" spellcheck="true" lang="${esc(cur.code)}">${esc(baseline(f))}</textarea>
      ${warns.length ? `<ul class="warn">${warns.map(w => `<li>⚠ ${esc(w)}</li>`).join('')}</ul>` : ''}
      <div class="acts">
        ${s ? '<span class="badge st-sugg" style="margin:0">Förslag</span>' : `<span class="badge st-${f.status}" style="margin:0">${STATUS[f.status]}</span>`}
        ${s ? '<button class="btn sm pri" type="button" data-m="approve">Godkänn</button><button class="btn sm" type="button" data-m="reject">Avvisa</button>'
            : '<button class="btn sm pri" type="button" data-m="save" disabled>Spara</button>'}
        ${!s && f.status === 'changed' ? '<button class="btn sm" type="button" data-m="same" title="Översättningen stämmer fortfarande">Oförändrad</button>' : ''}
        ${who}
        <button class="btn sm ghost" type="button" data-m="skip" title="Texten ska se likadan ut på alla språk (t.ex. ett namn). Den visas på ${esc(overview.main.name.toLowerCase())} och skickas inte till AI:n. Går att ångra under Översätts aldrig.">Översätt aldrig</button>
        <a class="show" href="${esc(showUrl(f))}" target="_blank" rel="noopener">Visa på sidan ↗</a>
      </div></div></div>`;
}

// En text som översätts aldrig: visas under filtret "Översätts aldrig", med "Översätt ändå".
function skippedHtml(s, i) {
  const who = s.reason === 'manual' && s.by ? ` · ${esc(s.by)} ${esc((s.at || '').slice(0, 10))}` : '';
  return `<div class="row skipped" data-s="${i}">
    <div class="src"><span class="lbl">${esc(overview.main.name)}</span>${esc(s.source)}</div>
    <div class="tgt"><span class="lbl">${esc(cur.name)}</span><p class="live" style="margin:0">Visas som på ${esc(overview.main.name.toLowerCase())}.</p>
      <div class="acts"><span class="badge st-hid" style="margin:0">${esc(SKIP_REASON[s.reason] || 'Översätts aldrig')}${who}</span>
        <button class="btn sm" type="button" data-m="unskip">Översätt ändå</button>
        <a class="show" href="${esc(showUrl(s))}" target="_blank" rel="noopener">Visa på sidan ↗</a></div></div></div>`;
}

function matches(f, flt) {
  if (flt === 'suggestion') return !!f.suggestion;
  return flt === 'all' || f.status === flt;
}
function visible(f) {
  if (!matches(f, filter)) return false;
  const q = $('q').value.trim().toLowerCase();
  return !q || f.source.toLowerCase().includes(q) || f.text.toLowerCase().includes(q) || (f.suggestion && f.suggestion.text.toLowerCase().includes(q));
}

function renderCounts() {
  for (const b of $('filters').children) {
    const k = b.dataset.f;
    b.textContent = FILTER_NAMES[k] + ' (' + (k === 'skipped' ? (cur.skipped || []).length : cur.fields.filter(f => matches(f, k)).length) + ')';
    b.setAttribute('aria-pressed', k === filter ? 'true' : 'false');
    if (k === 'suggestion') b.hidden = !cur.fields.some(f => f.suggestion) && filter !== 'suggestion';
  }
}

function renderFields() {
  if (!cur) return;
  renderCounts();
  renderScope();
  if (filter === 'skipped') {
    const q = $('q').value.trim().toLowerCase();
    const sk = (cur.skipped || []).map((s, i) => [s, i]).filter(([s]) => !q || s.source.toLowerCase().includes(q));
    const html = cur.groups.map(g => {
      const list = sk.filter(([s]) => s.group === g.id);
      return list.length ? `<div class="group"><div class="ghead"><h2>${esc(g.title)}</h2></div>${list.map(([s, i]) => skippedHtml(s, i)).join('')}</div>` : '';
    }).join('');
    $('fields').innerHTML = html || '<p class="empty">Inga texter är undantagna. Tryck <strong>Översätt aldrig</strong> på en rad för att lägga till.</p>';
    return;
  }
  const html = cur.groups.map(g => {
    const list = cur.fields.map((f, i) => [f, i]).filter(([f]) => f.group === g.id && visible(f));
    if (!list.length) return '';
    const sugg = cur.fields.filter(f => f.group === g.id && f.suggestion).length;
    return `<div class="group" data-g="${esc(g.id)}"><div class="ghead"><h2>${esc(g.title)}</h2>${sugg
      ? `<button class="btn sm" type="button" data-m="approveAll">Godkänn alla förslag (${sugg})</button>` : ''}</div>${list.map(([f, i]) => fieldHtml(f, i)).join('')}</div>`;
  }).join('');
  $('fields').innerHTML = html || `<p class="empty">${{ missing: 'Allt är översatt.', changed: 'Inga texter har ändrats sedan de översattes.', suggestion: 'Inga förslag att granska.' }[filter] || 'Inga texter att visa.'}</p>`;
}

async function loadFields(code) {
  const y = scrollY;
  cur = await api('i18n_fields', null, '&code=' + encodeURIComponent(code));
  renderFields();
  scrollTo(0, y);
}

async function openLang(code) {
  if (!overview) return;
  if (!overview.languages.length) {
    $('fields').innerHTML = '<p class="empty">Lägg till ett språk under fliken <a href="#sprak">Språk</a> först.</p>';
    $('lang').hidden = $('ai').hidden = true;
    return;
  }
  $('lang').hidden = $('ai').hidden = false;
  if (!overview.languages.some(l => l.code === code)) code = overview.languages[0].code;
  $('lang').value = code;
  $('aiCopy').textContent = 'Kopiera instruktioner till AI för översättning till ' + overview.languages.find(l => l.code === code).name;
  if (cur && cur.code === code) { renderFields(); return; }
  $('aiParts').innerHTML = $('aiReport').innerHTML = '';
  $('fields').innerHTML = '<p class="empty">Hämtar…</p>';
  try {
    await loadFields(code);
    const todo = cur.missing + cur.changed;
    $('ai').open = todo > 0 && !cur.fields.some(f => f.suggestion);
  } catch (x) { $('fields').innerHTML = `<p class="empty">${esc(x.message)}</p>`; }
}

$('lang').onchange = () => {
  if (dirtyCount() && !confirm('Du har översättningar som inte är sparade. Lämna dem?')) { $('lang').value = cur.code; return; }
  cur = null;
  history.replaceState(null, '', '#oversatt-' + $('lang').value);
  openLang($('lang').value);
};
$('filters').onclick = (e) => { const b = e.target.closest('button'); if (b) { filter = b.dataset.f; renderFields(); } };
$('q').oninput = () => renderFields();

function dirtyCount() {
  if (!cur) return 0;
  return [...document.querySelectorAll('#fields .row[data-i]')].filter(r => r.querySelector('textarea').value.trim() !== baseline(cur.fields[+r.dataset.i]).trim()).length;
}

$('fields').addEventListener('input', (e) => {
  const row = e.target.closest('.row');
  if (!row || e.target.tagName !== 'TEXTAREA') return;
  const save = row.querySelector('[data-m=save]');
  if (save) save.disabled = e.target.value.trim() === cur.fields[+row.dataset.i].text;
});

function replaceRow(row, i) {
  const tmp = document.createElement('div');
  tmp.innerHTML = fieldHtml(cur.fields[i], i);
  const fresh = tmp.firstElementChild;
  row.replaceWith(fresh);
  fresh.classList.add('saved-flash');
  setTimeout(() => fresh.classList.remove('saved-flash'), 1200);
  renderCounts();
  return fresh;
}

// Spara rutans text som godkänd översättning (även ett ändrat förslag). sameText = "Oförändrad".
async function save(row, sameText) {
  const i = +row.dataset.i, f = cur.fields[i];
  const text = sameText ? f.text : row.querySelector('textarea').value;
  if (!text.trim() && f.text && !confirm('Ta bort översättningen? Texten visas då på ' + mainLower() + '.')) return false;
  row.querySelectorAll('button').forEach(b => b.disabled = true);
  try {
    const d = await api('i18n_save', { code: cur.code, key: f.key, text });
    cur.fields[i] = { ...d.field, group: f.group };
    Object.assign(overview.languages.find(x => x.code === cur.code) || {}, d.stats);
    say(text.trim() ? 'Sparat och publicerat.' : 'Översättningen är borttagen.');
    return replaceRow(row, i);
  } catch (x) {
    say(x.message, true);
    row.querySelectorAll('button').forEach(b => b.disabled = false);
    return false;
  }
}

// Godkänn förslag. Har redaktören ändrat i rutan sparas den texten i stället.
async function approve(row) {
  const i = +row.dataset.i, f = cur.fields[i];
  const ta = row.querySelector('textarea');
  if (ta.value.trim() !== f.suggestion.text.trim()) return save(row);
  row.querySelectorAll('button').forEach(b => b.disabled = true);
  try {
    await api('i18n_approve', { code: cur.code, keys: [f.key] });
    await loadFields(cur.code);
    refreshOverview();
    say('Godkänt och publicerat.');
    return true;
  } catch (x) { say(x.message, true); row.querySelectorAll('button').forEach(b => b.disabled = false); return false; }
}

async function reject(row) {
  const f = cur.fields[+row.dataset.i];
  try {
    await api('i18n_reject', { code: cur.code, keys: [f.key] });
    await loadFields(cur.code);
    refreshOverview();
    say('Förslaget är avvisat.');
  } catch (x) { say(x.message, true); }
}

// Alla förslag i en grupp, utom de som redaktören har ändrat i rutan (de sparas för sig).
async function approveAll(groupEl) {
  const gid = groupEl.dataset.g;
  const g = cur.groups.find(x => x.id === gid);
  const edited = new Set([...groupEl.querySelectorAll('.row.has-sugg')]
    .filter(r => r.querySelector('textarea').value.trim() !== cur.fields[+r.dataset.i].suggestion.text.trim()).map(r => cur.fields[+r.dataset.i].key));
  const list = cur.fields.filter(f => f.group === gid && f.suggestion && !edited.has(f.key));
  if (!list.length) { say('Alla förslag här har ändringar i rutan. Spara dem en och en.', true); return; }
  const warned = list.filter(f => f.suggestion.warnings.length || f.suggestion.sourceChanged).length;
  if (!confirm(`Godkänn ${list.length} förslag på ${g.title}?` + (warned ? `\n\n${warned} av dem har varningar (⚠). Titta gärna på dem först.` : '') +
    (edited.size ? `\n\n${edited.size} förslag som du har ändrat i rutan tas inte med. Spara dem för sig.` : ''))) return;
  try {
    const d = await api('i18n_approve', { code: cur.code, keys: list.map(f => f.key) });
    await loadFields(cur.code);
    refreshOverview();
    say(d.approved + ' förslag är godkända och publicerade.');
  } catch (x) { say(x.message, true); }
}

// Översätt aldrig / Översätt ändå. Gäller fältet på alla språk.
async function setSkip(row, skip) {
  const f = skip ? cur.fields[+row.dataset.i] : cur.skipped[+row.dataset.s];
  if (skip && f.text && !confirm(`Översätt aldrig? Texten visas då på ${mainLower()} på alla språk. Den översättning som finns sparas, och kommer tillbaka om du trycker Översätt ändå.`)) return;
  row.querySelectorAll('button').forEach(b => b.disabled = true);
  try {
    await api('i18n_skip', { key: f.key, skip });
    await loadFields(cur.code);
    refreshOverview();
    say(skip ? 'Texten översätts aldrig.' : 'Texten översätts igen.');
  } catch (x) { say(x.message, true); row.querySelectorAll('button').forEach(b => b.disabled = false); }
}

$('fields').addEventListener('click', (e) => {
  const b = e.target.closest('button[data-m]');
  if (!b) return;
  const m = b.dataset.m;
  if (m === 'approveAll') return approveAll(b.closest('.group'));
  if (m === 'skip' || m === 'unskip') return setSkip(b.closest('.row'), m === 'skip');
  const row = b.closest('.row');
  if (m === 'approve') approve(row);
  else if (m === 'reject') reject(row);
  else save(row, m === 'same');
});

// Ctrl+Enter sparar (eller godkänner) och går till nästa ruta.
$('fields').addEventListener('keydown', async (e) => {
  if (e.key !== 'Enter' || !(e.ctrlKey || e.metaKey) || e.target.tagName !== 'TEXTAREA') return;
  e.preventDefault();
  const row = e.target.closest('.row');
  const all = [...document.querySelectorAll('#fields textarea')];
  const idx = all.indexOf(e.target);
  const f = cur.fields[+row.dataset.i];
  let ok = true;
  if (f.suggestion) ok = await approve(row);
  else if (e.target.value.trim() !== f.text) ok = await save(row);
  if (!ok) return;
  const now = [...document.querySelectorAll('#fields textarea')];
  (now[idx + 1] || now[idx])?.focus();
});

// ---------- Översätt med AI ----------
function renderScope() {
  const keep = $('aiScope').value;
  const n = (fn) => cur.fields.filter(fn).length;
  const todo = n(f => f.status !== 'done'), changed = n(f => f.status === 'changed');
  $('aiScope').innerHTML = `<option value="todo">Saknade och ändrade (${todo})</option>`
    + (changed ? `<option value="changed">Bara ändrade (${changed})</option>` : '')
    + `<option value="all">Alla texter (${cur.fields.length})</option>`
    + cur.groups.map(g => `<option value="g:${esc(g.id)}">Bara ${esc(g.title)} (${n(f => f.group === g.id)})</option>`).join('');
  $('aiScope').value = keep && [...$('aiScope').options].some(o => o.value === keep) ? keep : (todo ? 'todo' : 'all');
}

// Kopiera, med reservvägar. Webbläsare tillåter oftast bara kopiering direkt vid ett klick, så den här ska
// anropas i klicket (inte efter ett anrop till servern).
async function copyText(text) {
  try { await navigator.clipboard.writeText(text); return true; } catch (x) { /* prova nästa sätt */ }
  return copyOld(text);
}

// Det gamla sättet (dold textruta + execCommand). Fungerar i fler lägen, men bara synkront i ett klick.
function copyOld(text) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.setAttribute('readonly', '');
  ta.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0';
  document.body.appendChild(ta);
  ta.select();
  let ok = false;
  try { ok = document.execCommand('copy'); } catch (x) { ok = false; }
  ta.remove();
  return ok;
}

// Kopiera text som servern ännu inte har skickat (Promise<string>), utan att förlora klicket:
// ClipboardItem tar emot ett löfte och skriver när texten kommer.
async function copyPending(textPromise) {
  try {
    if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
      await navigator.clipboard.write([new ClipboardItem({ 'text/plain': textPromise.then(t => new Blob([t], { type: 'text/plain' })) })]);
      return true;
    }
  } catch (x) { /* prova nästa sätt */ }
  try { await navigator.clipboard.writeText(await textPromise); return true; } catch (x) { return false; }
}

// Kunde inte kopieras automatiskt: visa texten markerad överst, så att redaktören kopierar själv.
function showManual(text, label) {
  const box = document.createElement('div');
  box.className = 'report bad';
  box.innerHTML = `<strong>Webbläsaren lät inte sidan kopiera${label ? ' ' + esc(label) : ''}.</strong> Texten nedan är markerad – tryck Ctrl+C (Cmd+C på Mac) och klistra in den i chatten.`;
  const ta = document.createElement('textarea');
  ta.rows = 6;
  ta.readOnly = true;
  ta.value = text;
  ta.style.marginTop = '8px';
  box.appendChild(ta);
  $('aiParts').prepend(box);
  ta.focus();
  ta.select();
}

function partButtons(done) {
  return `<div class="parts">${parts.map((p, i) => `<button class="btn${done.has(i) ? '' : ' pri'}" type="button" data-part="${i}">${done.has(i) ? '✓ Del ' + (i + 1) + ' kopierad' : 'Kopiera del ' + (i + 1) + ' av ' + parts.length} (${p.count} texter)</button>`).join('')}</div>`;
}

let parts = [];
$('aiCopy').onclick = async () => {
  $('aiParts').innerHTML = '<p class="hint">Gör instruktionen …</p>';
  $('aiCopy').disabled = true;
  // Anropet och kopieringen startar i samma klick, så att webbläsaren tillåter kopieringen.
  const req = api('i18n_ai_prompt', { code: cur.code, scope: $('aiScope').value });
  const copied = copyPending(req.then(d => d.parts[0].prompt));
  try {
    const d = await req;
    parts = d.parts;
    const ok = await copied;
    if (parts.length === 1) {
      $('aiParts').innerHTML = ok ? `<p class="done-copy">✓ Instruktionen är kopierad (${parts[0].count} texter). Klistra in den i en ny chatt.</p>` : '';
      if (!ok) showManual(parts[0].prompt);
    } else {
      $('aiParts').innerHTML = `<p class="${ok ? 'done-copy' : 'hint'}">${ok ? '✓ Del 1 av ' + parts.length + ' är kopierad. ' : ''}Det är många texter, så instruktionen är uppdelad i ${parts.length} delar. Klistra in en del i taget, gärna i en ny chatt för varje, och läs in varje svar innan du kopierar nästa.</p>` + partButtons(new Set(ok ? [0] : []));
      if (!ok) showManual(parts[0].prompt, 'del 1');
    }
  } catch (x) { $('aiParts').innerHTML = ''; say(x.message, true); }
  finally { $('aiCopy').disabled = false; }
};
$('aiParts').addEventListener('click', async (e) => {
  const b = e.target.closest('[data-part]');
  if (!b) return;
  const i = +b.dataset.part;
  if (await copyText(parts[i].prompt)) {
    b.textContent = '✓ Del ' + (i + 1) + ' kopierad (' + parts[i].count + ' texter)';
    b.classList.remove('pri');
  } else showManual(parts[i].prompt, 'del ' + (i + 1));
});

$('aiRead').onclick = async () => {
  const text = $('aiAnswer').value;
  if (!text.trim()) { say('Klistra in svaret från AI först.', true); return; }
  $('aiRead').disabled = true;
  $('aiReport').innerHTML = '';
  try {
    const r = await api('i18n_ai_import', { code: cur.code, text });
    const li = [];
    if (r.missing.length) li.push(`${r.missing.length} texter saknades i svaret. De står kvar som tidigare – kopiera instruktionen för <em>Saknade och ändrade</em> igen för att få dem.`);
    if (r.unknown.length) li.push(`${r.unknown.length} svar hörde inte till instruktionen och hoppades över.`);
    if (r.gone) li.push(`${r.gone} texter finns inte längre på webbplatsen.`);
    if (r.invalid) li.push(`${r.invalid} svar var tomma eller ogiltiga.`);
    if (r.sourceChanged) li.push(`${r.sourceChanged} texter har ändrats på ${esc(mainLower())} sedan instruktionen kopierades (markerade med ⚠).`);
    if (r.warnings) li.push(`${r.warnings} förslag har varningar (⚠), t.ex. siffror eller ord i ordlistan som inte stämmer. Titta extra på dem.`);
    r.notes.forEach(n => li.push(esc(n)));
    $('aiReport').innerHTML = `<div class="report"><strong>${r.imported} av ${r.total} förslag är inlästa.</strong> Granska dem i listan och godkänn. Inget syns på webbplatsen förrän du har godkänt.${li.length ? '<ul>' + li.map(x => `<li>${x}</li>`).join('') + '</ul>' : ''}</div>`;
    $('aiAnswer').value = '';
    filter = 'suggestion';
    await loadFields(cur.code);
    refreshOverview();
  } catch (x) {
    $('aiReport').innerHTML = `<div class="report bad">${esc(x.message)}</div>`;
  } finally { $('aiRead').disabled = false; }
};

// ---------- Ordlista ----------
let G = null, gSaved = '', gBase = '';
const gState = () => JSON.stringify({ about: G.about, tone: G.tone, entries: G.entries });
const gDirty = () => !!G && gState() !== gSaved;
const TYPES = { keep: 'Översätts inte', fixed: 'Fast översättning', explain: 'Förklaring' };

async function openGlossary() {
  if (G) { renderGlossary(); return; }
  $('gList').innerHTML = '<p class="empty">Hämtar…</p>';
  try { loadGlossary(await api('i18n_glossary')); } catch (x) { $('gList').innerHTML = `<p class="empty">${esc(x.message)}</p>`; }
}
function loadGlossary(d) {
  G = { about: d.about, tone: Array.isArray(d.tone) ? {} : d.tone, entries: d.entries, languages: d.languages };
  gSaved = gState();
  gBase = d.base;
  renderGlossary();
}

function renderGlossary() {
  $('gAbout').value = G.about;
  $('gTone').innerHTML = G.languages.length ? G.languages.map(l => `<div class="tone"><label class="gh" for="tone-${esc(l.code)}">${esc(l.name)}</label>
    <textarea id="tone-${esc(l.code)}" data-tone="${esc(l.code)}" rows="2" maxlength="500" placeholder="Till exempel: Tilltala läsaren med &quot;du&quot;. Skriv priser som 230 SEK.">${esc(G.tone[l.code] || '')}</textarea></div>`).join('')
    : '<p class="hint">Lägg till ett språk först.</p>';
  $('gList').innerHTML = G.entries.length ? `<div class="ge gh" aria-hidden="true" style="border:0;padding-bottom:0"><span>Ord eller namn</span><span>Gäller</span><span>Översättning, förklaring och kommentar</span><span></span></div>`
    + G.entries.map((e, i) => `<div class="ge" data-e="${i}">
      <input type="text" data-k="term" maxlength="200" value="${esc(e.term)}" aria-label="Ord eller namn" placeholder="Till exempel Örlogsbyn">
      <select data-k="type" aria-label="Gäller">${Object.entries(TYPES).map(([k, v]) => `<option value="${k}" ${e.type === k ? 'selected' : ''}>${v}</option>`).join('')}</select>
      <div class="more">
        ${e.type === 'fixed' ? (G.languages.length ? G.languages.map(l => `<label>${esc(l.name)}<input type="text" data-fixed="${esc(l.code)}" maxlength="200" value="${esc((e.fixed || {})[l.code] || '')}"></label>`).join('') : '<span class="hint">Lägg till ett språk först.</span>') : ''}
        ${e.type === 'explain' ? `<input type="text" data-k="explain" maxlength="300" value="${esc(e.explain)}" aria-label="Förklaring" placeholder="Vad ordet betyder">` : ''}
        <input type="text" data-k="note" maxlength="200" value="${esc(e.note)}" aria-label="Kommentar" placeholder="Kommentar (valfri), t.ex. namn på stugby">
      </div>
      <button class="btn x" type="button" data-m="del" aria-label="Ta bort ${esc(e.term)}">✕</button>
    </div>`).join('') : '<p class="empty">Inga ord än.</p>';
  gUpdate();
}

function gUpdate() {
  const d = gDirty();
  $('gSave').disabled = $('gUndo').disabled = !d;
  if (d) { $('gMsg').textContent = 'Du har ändringar som inte är sparade.'; $('gMsg').className = 'm warnm'; }
  else if ($('gMsg').classList.contains('warnm')) { $('gMsg').textContent = ''; $('gMsg').className = 'm'; }
}

$('gAbout').oninput = () => { G.about = $('gAbout').value; gUpdate(); };
$('gTone').addEventListener('input', (e) => { const c = e.target.dataset.tone; if (c) { G.tone = { ...G.tone, [c]: e.target.value }; gUpdate(); } });
$('gList').addEventListener('input', (e) => {
  const row = e.target.closest('[data-e]');
  if (!row) return;
  const en = G.entries[+row.dataset.e];
  if (e.target.dataset.fixed) en.fixed = { ...(en.fixed || {}), [e.target.dataset.fixed]: e.target.value };
  else if (e.target.dataset.k) en[e.target.dataset.k] = e.target.value;
  if (e.target.dataset.k === 'type') renderGlossary(); else gUpdate();
});
$('gList').addEventListener('click', (e) => {
  const b = e.target.closest('[data-m=del]');
  if (!b) return;
  G.entries.splice(+b.closest('[data-e]').dataset.e, 1);
  renderGlossary();
});
$('gAdd').onclick = () => {
  G.entries.push({ id: 'o' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5), term: '', type: 'keep', fixed: {}, explain: '', note: '' });
  renderGlossary();
  $('gList').querySelector('.ge:last-child input').focus();
};
$('gUndo').onclick = () => {
  if (!confirm('Ta bort alla ändringar sedan du senast sparade?')) return;
  const s = JSON.parse(gSaved);
  Object.assign(G, s);
  renderGlossary();
};
$('gSave').onclick = async () => {
  G.entries = G.entries.filter(e => e.term.trim() || e.explain.trim() || e.note.trim() || Object.values(e.fixed || {}).some(v => v.trim()));
  $('gSave').disabled = true;
  $('gMsg').textContent = 'Sparar…'; $('gMsg').className = 'm';
  try {
    loadGlossary(await api('i18n_glossary_save', { about: G.about, tone: G.tone, entries: G.entries, base: gBase }));
    $('gMsg').textContent = 'Ordlistan är sparad. Den följer med nästa gång du kopierar instruktionen.';
  } catch (x) {
    gUpdate();
    $('gMsg').textContent = x.message; $('gMsg').className = 'm warnm';
  }
};

// ---------- Start ----------
api('i18n_overview').then(o => {
  overview = o;
  renderLangs();
  if (!location.hash) history.replaceState(null, '', overview.languages.length ? '#oversatt' : '#sprak');
  route();
}).catch(x => { $('langs').innerHTML = `<p class="empty">${esc(x.message)}</p>`; route(); });
</script>
</body>
</html>
