<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
start_session();
$reset = preg_match('/^[a-f0-9]{64}$/', $_GET['reset'] ?? '') ? $_GET['reset'] : '';
// En lösenordslänk visas även för den som är inloggad (t.ex. om man testar en inbjudan i samma webbläsare).
// Efter inloggningen: tillbaka till sidan man kom från (?page=fil.php), om den finns i 'pages'.
$editUrl = edit_url(is_string($_GET['page'] ?? null) ? $_GET['page'] : '');
if (current_user() && !$reset) { header('Location: ' . $editUrl); exit; }
// Vems länken är och om det är en inbjudan (välkomsttext) eller "glömt lösenord".
$resetUser = $reset ? user_for_reset($reset) : null;
$invite = $resetUser && (user_record($resetUser)['reset_kind'] ?? '') === 'invite';
$view = $reset ? 'reset' : (pending_user() ? 'change' : 'login');
$csrf = pending_user() ? csrf_token() : '';
$c = config();
// Loggan om den finns, annars webbplatsens namn.
$logo = $c['logo'] ? '<img src="../' . h($c['logo']) . '" alt="' . h($c['site_name']) . '">' : '<p class="name">' . h($c['site_name']) . '</p>';
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="referrer" content="no-referrer">
<title>Logga in – <?= h($c['site_name']) ?></title>
<link rel="icon" href="../favicon.ico?v=2" sizes="any">
<link rel="icon" href="../favicon.svg?v=2" type="image/svg+xml">
<style>
  <?= brand_css() ?>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;background:#F5F3EF;color:#404040;font:16px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif}
  form{width:100%;max-width:380px;background:#fff;border:1px solid #E4E0D9;border-top:6px solid var(--accent);padding:32px}
  form[hidden]{display:none}
  img{height:44px;display:block;margin-bottom:24px}
  .name{font-weight:700;font-size:18px;margin:0 0 24px}
  h1{font-size:20px;margin:0 0 20px}
  p.info{font-size:14px;margin:-8px 0 20px}
  label{display:block;font-weight:600;font-size:14px;margin:0 0 6px}
  input{width:100%;height:46px;padding:0 12px;border:1px solid #CFC9BF;font:inherit;margin-bottom:16px}
  input:focus{outline:2px solid var(--accent);outline-offset:1px}
  button{width:100%;height:48px;border:0;background:var(--accent);color:#fff;font:inherit;font-weight:700;cursor:pointer}
  button:hover{background:var(--accent-dark)}
  button:disabled{opacity:.6;cursor:default}
  /* Väntmarkören bara medan anropet pågår – inte på en knapp som är avstängd för att allt är klart. */
  form.busy button{cursor:wait}
  .err{color:#B91C1C;font-size:14px;min-height:21px;margin:12px 0 0}
  .err.ok,.ok{color:#166534;font-size:14px;margin:12px 0 0}
  .help{display:block;margin-top:8px;font-size:14px;color:var(--accent)}
</style>
</head>
<body>
<form id="login" data-view="login" hidden>
  <?= $logo ?>
  <h1>Redigera webbplatsen</h1>
  <label for="u">Användarnamn eller e-post</label>
  <input id="u" name="user" autocomplete="username" required>
  <label for="p">Lösenord</label>
  <input id="p" name="password" type="password" autocomplete="current-password" required>
  <button>Logga in</button>
  <p class="err" role="alert"></p>
  <a class="help" href="#" data-go="forgot">Glömt lösenord?</a>
  <a class="help" href="hjalp.php">Så redigerar du hemsidan →</a>
</form>

<form id="change" data-view="change" hidden>
  <?= $logo ?>
  <h1>Välj ett nytt lösenord</h1>
  <p class="info">Första gången du loggar in behöver du byta till ett eget lösenord (minst <?= PASSWORD_MIN ?> tecken).</p>
  <label for="c1">Nytt lösenord</label>
  <input id="c1" name="password" type="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN ?>" required>
  <label for="c2">Upprepa nytt lösenord</label>
  <input id="c2" name="repeat" type="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN ?>" required>
  <button>Spara och fortsätt</button>
  <p class="err" role="alert"></p>
  <a class="help" href="#" id="logout">Logga ut</a>
</form>

<form id="forgot" data-view="forgot" hidden>
  <?= $logo ?>
  <h1>Glömt lösenord</h1>
  <p class="info">Skriv din e-postadress så skickar vi en länk där du kan välja ett nytt lösenord.</p>
  <label for="fe">E-post</label>
  <input id="fe" name="email" type="email" autocomplete="email" required>
  <button>Skicka länk</button>
  <p class="err" role="alert"></p>
  <a class="help" href="#" data-go="login">← Tillbaka till inloggningen</a>
</form>

<form id="reset" data-view="reset" hidden>
  <?= $logo ?>
<?php if ($reset && !$resetUser): ?>
  <h1>Länken fungerar inte</h1>
  <p class="info">Den har redan använts eller gått ut. Be om en ny via <a href="index.php">Glömt lösenord</a>, eller be den som bjöd in dig att skicka en ny inbjudan.</p>
</form>
<?php else: ?>
  <h1><?= $invite ? 'Välkommen!' : 'Välj ett nytt lösenord' ?></h1>
  <p class="info"><?php if ($invite): ?>Välj ett lösenord för användarnamnet <strong><?= h((string)$resetUser) ?></strong> (minst <?= PASSWORD_MIN ?> tecken). Sedan loggar du in med det.<?php else: ?>Minst <?= PASSWORD_MIN ?> tecken.<?php endif ?></p>
  <input name="username" autocomplete="username" value="<?= h((string)$resetUser) ?>" hidden>
  <label for="r1">Nytt lösenord</label>
  <input id="r1" name="password" type="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN ?>" required>
  <label for="r2">Upprepa nytt lösenord</label>
  <input id="r2" name="repeat" type="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN ?>" required>
  <button>Spara lösenord</button>
  <p class="err" role="alert"></p>
  <a class="help" href="index.php">← Till inloggningen</a>
</form>
<?php endif ?>
<script>
let csrf = <?= json_encode($csrf) ?>;
const token = <?= json_encode($reset) ?>;
const editUrl = () => <?= json_encode($editUrl) ?> + (new URLSearchParams(location.search).get('hash') || '');

function show(view) {
  document.querySelectorAll('form').forEach(f => { f.hidden = f.dataset.view !== view; });
  const first = document.querySelector(`form[data-view="${view}"] input:not([hidden])`);
  if (first) first.focus();
}
document.querySelectorAll('[data-go]').forEach(a => a.addEventListener('click', (e) => { e.preventDefault(); show(a.dataset.go); }));

async function post(action, data, headers = {}) {
  const r = await fetch('api.php?action=' + action, { method: 'POST',
    headers: { 'Content-Type': 'application/json', ...headers }, body: JSON.stringify(data) });
  const d = await r.json();
  if (!r.ok) throw new Error(d.error || 'Något gick fel.');
  return d;
}

// Gemensam hantering: knapp av, felruta tömd, fel visas.
function handle(id, fn) {
  const f = document.getElementById(id);
  f.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const btn = f.querySelector('button'), err = f.querySelector('.err');
    btn.disabled = true; err.textContent = ''; err.classList.remove('ok');
    f.classList.add('busy');
    try { await fn(f, err); } catch (e) { err.textContent = e.message; btn.disabled = false; }
    finally { f.classList.remove('busy'); }
  });
}

function samePasswords(f) {
  if (f.password.value !== f.repeat.value) throw new Error('Lösenorden är inte likadana.');
}

handle('login', async (f) => {
  const d = await post('login', { user: f.user.value, password: f.password.value });
  if (d.mustChange) { csrf = d.csrf; show('change'); return; }
  location.href = editUrl();
});

handle('change', async (f) => {
  samePasswords(f);
  await post('change_password', { password: f.password.value }, { 'X-CSRF-Token': csrf });
  location.href = editUrl();
});

document.getElementById('logout').addEventListener('click', async (e) => {
  e.preventDefault();
  await post('logout', {}, { 'X-CSRF-Token': csrf }).catch(() => {});
  location.replace('index.php');
});

handle('forgot', async (f, err) => {
  await post('forgot', { email: f.email.value });
  err.classList.add('ok');
  err.textContent = 'Om adressen finns hos oss har vi skickat en länk. Kolla din inkorg (och skräpposten).';
  // Knappen går att trycka igen, t.ex. om man skrev fel adress.
  f.querySelector('button').disabled = false;
});

handle('reset', async (f, err) => {
  samePasswords(f);
  await post('reset', { token, password: f.password.value });
  history.replaceState(null, '', 'index.php');
  f.querySelectorAll('input, button').forEach(el => { el.disabled = true; });
  err.classList.add('ok');
  err.innerHTML = 'Lösenordet är sparat. <a href="index.php">Logga in</a>';
});

show(<?= json_encode($view) ?>);
</script>
</body>
</html>
