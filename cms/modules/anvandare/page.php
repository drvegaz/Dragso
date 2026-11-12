<?php
// Modulen "anvandare": lista, bjud in och ta bort redaktörer. Körs via cms/page.php, som redan har kontrollerat inloggningen.
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="referrer" content="no-referrer">
<title>Användare – <?= h(config()['site_name']) ?></title>
<link rel="icon" href="../favicon.ico?v=2" sizes="any">
<link rel="icon" href="../favicon.svg?v=2" type="image/svg+xml">
<style>
  <?= brand_css() ?>
  *{box-sizing:border-box}
  body{margin:0;background:#F5F3EF;color:#1F2937;font:15px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif}
  header{background:#404040;color:#fff}
  header div{max-width:860px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between}
  header a{color:#fff}
  main{max-width:860px;margin:0 auto;padding:24px 16px 80px;display:grid;gap:16px}
  h1{font-size:24px;margin:0}
  h2{font-size:18px;margin:0 0 12px}
  .lead{color:#6B7280;margin:4px 0 0}
  .card{background:#fff;border:1px solid #E4E0D9;padding:16px 20px 20px}
  .grid{display:grid;grid-template-columns:1fr 1.4fr auto;gap:12px;align-items:end}
  @media (max-width:640px){.grid{grid-template-columns:1fr}}
  label{display:block;font-weight:600;font-size:14px;margin:0 0 4px}
  input[type=text],input[type=email]{width:100%;height:40px;padding:0 10px;border:1px solid #CFC9BF;font:inherit;background:#fff}
  .hint{color:#6B7280;font-size:13px;margin:8px 0 0}
  .btn{height:40px;padding:0 14px;border:1px solid #D1D5DB;background:#fff;color:#1F2937;font:inherit;font-weight:600;cursor:pointer;border-radius:6px;white-space:nowrap}
  .btn:hover:not(:disabled){background:#F3F4F6}
  .btn.pri{background:#2563EB;border-color:#2563EB;color:#fff}
  .btn.pri:hover:not(:disabled){background:#1D4ED8}
  .btn.del{color:#B91C1C}
  .btn.sm{height:34px;font-size:14px}
  .btn:disabled{opacity:.45;cursor:default}
  .msg{margin:12px 0 0;font-size:14px}
  .msg.bad{color:#B91C1C}.msg.good{color:#166534}
  .linkbox{margin:12px 0 0;padding:12px 14px;background:#EFF6FF;border:1px solid #BFDBFE}
  .linkbox p{margin:0 0 8px;font-size:14px}
  .linkbox div{display:flex;gap:8px}
  .linkbox input{flex:1;min-width:0;height:36px;padding:0 8px;border:1px solid #BFDBFE;font:13px ui-monospace,Consolas,monospace;background:#fff}
  .list{display:grid;gap:8px}
  .row{background:#fff;border:1px solid #E4E0D9;padding:12px 16px;display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center}
  .row .who{flex:1;min-width:200px}
  .row .name{font-weight:700}
  .row .meta{color:#6B7280;font-size:13px}
  .badge{display:inline-block;font-size:12px;font-weight:600;padding:1px 8px;border-radius:999px;margin-left:6px;vertical-align:1px}
  .st-active{background:#DCFCE7;color:#166534}.st-invited{background:#DBEAFE;color:#1E40AF}.st-expired{background:#FEE2E2;color:#991B1B}.st-new{background:#FEF3C7;color:#92400E}.st-me{background:#F3F4F6;color:#4B5563}
  .tools{display:flex;gap:6px;flex-wrap:wrap}
  .empty{background:#fff;border:1px dashed #CFC9BF;padding:24px;text-align:center;color:#6B7280}
</style>
</head>
<body>
<header><div><strong>Användare</strong><a href="<?= h(edit_url()) ?>">← Till redigeringsläget</a></div></header>
<main>
  <div>
    <h1>Användare</h1>
    <p class="lead">Alla som kan logga in och redigera webbplatsen. Alla användare kan göra samma saker.</p>
  </div>

  <section class="card">
    <h2>Lägg till användare</h2>
    <form id="add" class="grid" autocomplete="off">
      <div><label for="n">Användarnamn</label><input id="n" type="text" name="name" maxlength="30" placeholder="t.ex. anna" autocapitalize="none" spellcheck="false" required></div>
      <div><label for="e">E-post</label><input id="e" type="email" name="email" maxlength="200" placeholder="anna@exempel.se" required></div>
      <button class="btn pri" type="submit">Lägg till och skicka länk</button>
    </form>
    <p class="hint">Användaren får ett mejl med en länk där hen väljer sitt eget lösenord (minst <?= PASSWORD_MIN ?> tecken). Länken gäller i 7 dagar. Användarnamnet: små bokstäver, siffror, punkt eller bindestreck.</p>
    <p class="msg" id="msg" role="status"></p>
    <div class="linkbox" id="linkbox" hidden>
      <p id="linktext"></p>
      <div><input id="link" type="text" readonly aria-label="Inbjudningslänk"><button class="btn sm" type="button" id="copy">Kopiera</button></div>
    </div>
  </section>

  <section>
    <h2>Alla användare</h2>
    <div class="list" id="list"><p class="empty">Hämtar…</p></div>
  </section>
</main>
<script>
const csrf = <?= json_encode(csrf_token()) ?>;
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const listEl = document.getElementById('list'), msg = document.getElementById('msg'), form = document.getElementById('add');
const STATUS = { active: 'Aktiv', invited: 'Inbjuden', expired: 'Inbjudan har gått ut', new: 'Har inte valt lösenord' };
const date = (iso) => iso ? new Date(typeof iso === 'number' ? iso * 1000 : iso).toLocaleDateString('sv-SE', { day: 'numeric', month: 'short', year: 'numeric' }) : '';

async function api(action, body) {
  const r = await fetch('api.php?action=' + action, { method: body ? 'POST' : 'GET',
    headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const d = await r.json().catch(() => ({ error: 'Oväntat svar från servern.' }));
  if (r.status === 401) { location.href = 'index.php'; throw new Error(d.error); }
  if (!r.ok) throw new Error(d.error || 'Något gick fel.');
  return d;
}

function setMsg(text, cls = '') { msg.textContent = text; msg.className = 'msg ' + cls; }

// Visar inbjudningslänken så att den kan skickas på annat sätt om mejlet inte kommer fram.
function showLink(d, who) {
  const box = document.getElementById('linkbox');
  box.hidden = !d.link;
  if (!d.link) return;
  document.getElementById('linktext').textContent = d.sent
    ? `Mejlet till ${who.email} är skickat. Kommer det inte fram kan du skicka länken själv:`
    : `Mejlet till ${who.email} kunde inte skickas. Kopiera länken och skicka den själv, t.ex. i ett sms:`;
  document.getElementById('link').value = d.link;
}

function render(users) {
  listEl.innerHTML = users.length ? users.map(u => {
    const waiting = u.status !== 'active';
    const meta = [u.email || 'Ingen e-post',
      u.cms ? (u.createdBy ? `tillagd av ${u.createdBy} ${date(u.created)}` : '') : 'finns i inställningsfilen',
      u.status === 'invited' && u.expires ? `länken gäller till ${date(u.expires)}` : ''].filter(Boolean);
    return `<div class="row" data-name="${esc(u.name)}" data-email="${esc(u.email)}">
      <div class="who"><span class="name">${esc(u.name)}</span><span class="badge st-${u.status}">${STATUS[u.status]}</span>${u.me ? '<span class="badge st-me">Du</span>' : ''}
        <div class="meta">${meta.map(esc).join(' · ')}</div></div>
      <div class="tools">
        ${u.email && !u.me ? `<button class="btn sm" type="button" data-act="link">${waiting ? 'Skicka ny inbjudan' : 'Skicka länk för nytt lösenord'}</button>` : ''}
        ${u.cms && !u.me ? '<button class="btn sm del" type="button" data-act="delete">Ta bort</button>' : ''}
      </div>
    </div>`;
  }).join('') : '<p class="empty">Inga användare.</p>';
}

form.onsubmit = async (e) => {
  e.preventDefault();
  const btn = form.querySelector('button');
  const who = { name: document.getElementById('n').value.trim().toLowerCase(), email: document.getElementById('e').value.trim() };
  btn.disabled = true; setMsg('Lägger till…'); showLink({});
  try {
    const d = await api('user_create', who);
    render(d.users);
    setMsg(`${who.name} är tillagd.`, 'good');
    showLink(d, who);
    form.reset();
  } catch (x) { setMsg(x.message, 'bad'); }
  btn.disabled = false;
};

listEl.addEventListener('click', async (e) => {
  const b = e.target.closest('button[data-act]');
  if (!b) return;
  const row = b.closest('.row'), who = { name: row.dataset.name, email: row.dataset.email };
  if (b.dataset.act === 'delete' && !confirm(`Ta bort ${who.name}? Hen kan inte längre logga in.`)) return;
  b.disabled = true; showLink({});
  try {
    if (b.dataset.act === 'delete') {
      render((await api('user_delete', { name: who.name })).users);
      setMsg(`${who.name} är borttagen.`, 'good');
    } else {
      const d = await api('user_link', { name: who.name });
      render(d.users);
      if (d.reset) setMsg(`En länk för nytt lösenord är skickad till ${who.email}. Den gäller i en timme.`, 'good');
      else { setMsg(`En ny inbjudan är skapad för ${who.name}. Den tidigare länken gäller inte längre.`, 'good'); showLink(d, who); }
    }
    msg.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  } catch (x) { setMsg(x.message, 'bad'); b.disabled = false; }
});

document.getElementById('copy').onclick = async () => {
  const input = document.getElementById('link'), btn = document.getElementById('copy');
  try { await navigator.clipboard.writeText(input.value); } catch (x) { input.select(); document.execCommand('copy'); }
  btn.textContent = 'Kopierad!';
  setTimeout(() => { btn.textContent = 'Kopiera'; }, 2000);
};

api('users').then(d => render(d.users)).catch(x => { listEl.innerHTML = `<p class="empty">${esc(x.message)}</p>`; });
</script>
</body>
</html>
