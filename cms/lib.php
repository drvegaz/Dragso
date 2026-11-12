<?php
// Gemensamma funktioner för CMS:et: lagring, versionering, validering, moduler och session.
// Den här filen tillhör kärnan (cms-core) och skrivs över vid uppdatering. Allt som är unikt för
// en webbplats ligger i cms/site/ (inställningar, hjälpsida, egna moduler).
declare(strict_types=1);

const CMS_DIR = __DIR__;
const ROOT = __DIR__ . '/..';
const SITE_DIR = __DIR__ . '/site';
const STORAGE_DIR = __DIR__ . '/storage';
const SITE_JSON = ROOT . '/data/site.json';
const SITE_JS = ROOT . '/data/site.js';
const HISTORY_DIR = STORAGE_DIR . '/history';
const UPLOAD_DIR = ROOT . '/uploads/cms';
const HISTORY_KEEP = 100;

// storage/ laddas aldrig upp (där ligger det levande), så på en ny server saknas skyddsfilen och allt där
// – användare, inloggningsförsök, formulärinskick – kunde läsas via webben. Kärnan skapar den därför själv.
(function (): void {
  $f = STORAGE_DIR . '/.htaccess';
  if (is_file($f)) return;
  if (!is_dir(STORAGE_DIR) && !@mkdir(STORAGE_DIR, 0775, true)) return;
  if (@file_put_contents($f, "# Historik, användare och inloggningsförsök ska aldrig nås via webben.\nRequire all denied\n") === false)
    error_log('[cms] kunde inte skapa ' . $f);
})();

// Standardvärden för cms/site/config.php. Webbplatsens egna värden gäller före dessa.
const CONFIG_DEFAULTS = [
  'site_name' => 'Webbplatsen',
  'site_url' => '',
  'logo' => '',                  // Sökväg från webbplatsens rot, t.ex. assets/logo.svg.
  'accent' => '#2563EB',         // Färg på knappar och ramar i inloggning och modulsidor.
  'accent_dark' => '#1D4ED8',
  'timezone' => 'Europe/Stockholm',
  'session_name' => 'cms',
  'session_idle_minutes' => 120,
  'users' => [],
  'mail_from' => '',
  'smtp' => null,
  'modules' => [],
  'contact_person' => '',        // Visas i hjälpen: "hör av dig till …".
  // Fält (sista icke-numeriska nyckeln i sökvägen) som redaktören får ändra.
  // Allt annat – länkar, ikoner, id:n, flaggor – måste vara oförändrat vid sparning.
  'editable_keys' => ['title', 'eyebrow', 'lead', 'text', 'label', 'intro', 'body', 'list', 'sub', 'note',
    'q', 'a', 'quote', 'name', 'summary', 'role', 'phone', 'email', 'value', 'price', 'rows', 'tag', 'badge', 'address'],
  'image_keys' => ['image', 'hero', 'photo'],
  'image_dirs' => ['assets/images', 'uploads'],
  // Toppnivåer i site.json där alla textfält är redigerbara, t.ex. 'ui' för knapptexter och rubriker.
  'open_roots' => [],
  // Länklistor som redaktören får lägga till, ändra, flytta och ta bort i. * = valfri nyckel, # = valfritt index.
  'link_lists' => [],
  // Hämta förhandsbilder för YouTube-länkar vid publicering (sparas i uploads/cms/youtube/).
  'youtube_thumbs' => false,
  // Webbplatser med flera sidfiler (t.ex. PHP-sidor): ['index.php' => 'Startsida', 'om.php' => 'Om oss'].
  // Sidväljaren i redigeringsläget byter då fil, och den första är startsidan. Tom = sidorna i site.json (#/nyckel).
  'pages' => [],
  // Modulen banners: färger att välja mellan, t.ex. ['red' => ['label' => 'Röd', 'bg' => '#dc2626', 'fg' => '#fff']].
  // Den första är standard. Webbplatsen ritar färgen själv utifrån nyckeln (notices[].color). Tom = inget färgval.
  'banner_colors' => [],
  // Modulen formular: hur många dagar inskick sparas innan de rensas bort automatiskt.
  'form_keep_days' => 365,
  // Modulen sprak: huvudspråket (det som skrivs i redigeringsläget). Ändras inte när sajten väl är igång.
  'language' => 'sv',
  // Modulen sprak: redigerbara fält som inte översätts (sista nyckeln i sökvägen). Tal och bilder översätts aldrig.
  'translate_skip_keys' => ['phone', 'email', 'price', 'value', 'address'],
  // Modulen sprak: kort beskrivning av webbplatsen till AI-instruktionen, tills redaktören har ändrat den i CMS:et.
  'translate_about' => '',
  // Modulen sprak, webbplatser med sidfiler: vilken del av site.json som hör till vilken sida, t.ex.
  // ['index.php' => 'pages.start', 'camping.php' => 'pages.camping']. Resten grupperas som "syns på alla sidor".
  // Tom = sidorna hämtas över HTTP från site_url och läses efter data-cms (långsammare, inte med php -S).
  'page_content' => [],
];

function config(): array {
  static $c = null;
  if ($c === null) $c = (require SITE_DIR . '/config.php') + CONFIG_DEFAULTS;
  return $c;
}

// Adressen till redigeringsläget, sett från cms/. $page = en fil i 'pages' (annars startsidan).
function edit_url(string $page = ''): string {
  $pages = config()['pages'];
  return '../' . (isset($pages[$page]) ? $page : (array_key_first($pages) ?? 'index.html')) . '?edit';
}

// Historik och filnamn i webbplatsens tidszon oavsett serverns inställning.
date_default_timezone_set(config()['timezone']);

// ---- Moduler ----
// En modul är en mapp med module.php som returnerar en array:
//   'title' => namn i menyn "Mer" i redigeringsläget (utelämnas om modulen saknar egen sida)
//   'page'  => PHP-fil med modulens sida, öppnas via cms/page.php?m=<namn>
//   'lists' => [sökvägsmönster => valideringsfunktion] för listor som modulen får skriva fritt i site.json
//   'public_api' => function (string $action): void – anropas före inloggningskontrollen
//   'api'   => function (string $action): void – anropas för inloggade. Svara med json_out(), annars return.
//   'after_save' => function (array $site): void – efter varje skrivning av site.json (inom with_site_lock(),
//                   så den får inte själv anropa with_site_lock() eller write_site()).
// Webbplatsens egna moduler (cms/site/modules/) gäller före kärnans (cms/modules/).

function modules(): array {
  static $m = null;
  if ($m !== null) return $m;
  $m = [];
  foreach (config()['modules'] as $name) {
    if (!preg_match('/^[a-z0-9-]+$/', $name)) throw new RuntimeException("Ogiltigt modulnamn: $name");
    $dir = is_dir(SITE_DIR . "/modules/$name") ? SITE_DIR . "/modules/$name" : CMS_DIR . "/modules/$name";
    if (!is_file("$dir/module.php")) throw new RuntimeException("Modulen $name finns inte");
    $m[$name] = (require "$dir/module.php") + ['name' => $name, 'dir' => $dir];
  }
  return $m;
}

// Menyn "Mer" i redigeringsläget.
function module_menu(): array {
  $out = [];
  foreach (modules() as $name => $mod) if (!empty($mod['page'])) $out[] = ['label' => $mod['title'] ?? $name, 'href' => "cms/page.php?m=$name"];
  return $out;
}

// ---- Innehåll ----

function load_site(): array {
  $raw = file_get_contents(SITE_JSON);
  return ['site' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR), 'version' => sha1($raw)];
}

function encode_site(array $site): string {
  return json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function atomic_write(string $file, string $data): void {
  $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
  if (file_put_contents($tmp, $data, LOCK_EX) === false) throw new RuntimeException('Kunde inte skriva ' . basename($file));
  if (!rename($tmp, $file)) { @unlink($tmp); throw new RuntimeException('Kunde inte ersätta ' . basename($file)); }
}

// JS-filen som sidan läser in: window.SITE = {…}; plus valfria rader efter.
// "@" skrivs som @ så att insamlingsbottar inte hittar e-postadresserna (webbläsaren läser det som vanligt).
function site_js(array $site, string $extra = ''): string {
  return "// GENERERAD av cms/ – ändra innehållet via redigeringsläget (?edit).\nwindow.SITE = "
    . str_replace('@', '\\u0040', json_encode($site, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR)) . ";\n" . $extra;
}

// Skriver både JSON-källan och JS-filen som sidan läser in. Körs alltid med with_site_lock().
// Efteråt får modulerna veta det ('after_save'), t.ex. så att sprak kan bygga om språkfilerna.
// Ett fel där stoppar inte publiceringen, utan loggas.
function write_site(array $site): string {
  $json = encode_site($site);
  atomic_write(SITE_JSON, $json);
  atomic_write(SITE_JS, site_js($site));
  foreach (modules() as $name => $mod) {
    if (!isset($mod['after_save'])) continue;
    try { $mod['after_save']($site); } catch (Throwable $e) { error_log("[cms] after_save i $name: $e"); }
  }
  return sha1($json);
}

// Kör $fn med ensamrätt på site.json, så att två som sparar samtidigt inte skriver över varandra.
function with_site_lock(callable $fn) {
  if (!is_dir(STORAGE_DIR)) mkdir(STORAGE_DIR, 0775, true);
  $fp = fopen(STORAGE_DIR . '/site.lock', 'c');
  flock($fp, LOCK_EX);
  try {
    return $fn();
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

function backup_current(string $user): void {
  if (!is_dir(HISTORY_DIR)) mkdir(HISTORY_DIR, 0775, true);
  $user = preg_replace('/[^a-z0-9]/i', '', $user) ?: 'okand';
  copy(SITE_JSON, HISTORY_DIR . '/' . date('Ymd-His') . '-' . $user . '.json');
  $files = glob(HISTORY_DIR . '/*.json');
  rsort($files);
  foreach (array_slice($files, HISTORY_KEEP) as $old) @unlink($old);
}

function list_history(): array {
  $files = glob(HISTORY_DIR . '/*.json') ?: [];
  rsort($files);
  return array_map(function ($f) {
    $id = basename($f, '.json');
    [$d, $t, $user] = array_pad(explode('-', $id, 3), 3, '');
    $ts = DateTime::createFromFormat('Ymd His', "$d $t");
    return ['id' => $id, 'date' => $ts ? $ts->format('Y-m-d H:i') : $id, 'user' => $user];
  }, $files);
}

function history_file(string $id): string {
  if (!preg_match('/^\d{8}-\d{6}-[a-z0-9]+$/i', $id)) throw new InvalidArgumentException('Ogiltig version');
  $f = HISTORY_DIR . '/' . $id . '.json';
  if (!is_file($f)) throw new InvalidArgumentException('Versionen finns inte');
  return $f;
}

// ---- Validering ----

function last_key(array $path): string {
  for ($i = count($path) - 1; $i >= 0; $i--) if (!is_int($path[$i])) return (string)$path[$i];
  return '';
}

// Mönster som 'pages.*.blocks.#.links': * = valfri nyckel, # = valfritt index, '' = roten.
function path_matches(array $path, string $pattern): bool {
  $segs = $pattern === '' ? [] : explode('.', $pattern);
  if (count($segs) !== count($path)) return false;
  foreach ($segs as $i => $s) {
    $p = $path[$i];
    if ($s === '*' ? !is_string($p) : ($s === '#' ? !is_int($p) : (string)$p !== $s)) return false;
  }
  return true;
}

// Listor som redaktören får lägga till och ta bort i: länklistorna från config plus modulernas listor.
function free_lists(): array {
  $out = array_fill_keys(config()['link_lists'], 'validate_links');
  foreach (modules() as $mod) $out += $mod['lists'] ?? [];
  return $out;
}

function valid_image_path(string $v): bool {
  if (str_contains($v, '..') || str_contains($v, '\\') || str_contains($v, '//')) return false;
  if (!preg_match('#^[^/][^\x00-\x1F]*\.(jpe?g|png|webp|gif|svg)$#i', $v)) return false;
  $dirs = array_merge(config()['image_dirs'], ['uploads/cms']);
  foreach ($dirs as $d) if (str_starts_with($v, rtrim($d, '/') . '/')) return is_file(ROOT . '/' . $v);
  return false;
}

// Jämför ny version mot nuvarande. Strukturen (nycklar, listlängder, typer) måste vara identisk
// och bara redigerbara fält får skilja sig. Kastar InvalidArgumentException med en läsbar sökväg.
function validate_change($old, $new, array $path = []): void {
  foreach (free_lists() as $pattern => $validator) {
    if (path_matches($path, $pattern)) { $validator($new); return; }
  }
  $where = $path ? implode('.', $path) : '(roten)';
  // Ett objekt som saknar en fri lista får en (redaktören har t.ex. lagt till sin första länk).
  if (is_array($old) && is_array($new) && $old && !array_is_list($old)) {
    foreach (array_keys(free_lists()) as $pattern) {
      $segs = explode('.', $pattern);
      $key = array_pop($segs);
      if (!path_matches($path, implode('.', $segs))) continue;
      if (array_key_exists($key, $new) && !array_key_exists($key, $old)) $old[$key] = [];
      if (array_key_exists($key, $old) && !array_key_exists($key, $new)) $new[$key] = [];
    }
  }
  if (is_array($old)) {
    if (!is_array($new) || array_is_list($old) !== array_is_list($new) || count($old) !== count($new))
      throw new InvalidArgumentException("Strukturen har ändrats vid $where");
    foreach ($old as $k => $v) {
      if (!array_key_exists($k, $new)) throw new InvalidArgumentException("Fält saknas vid $where.$k");
      validate_change($v, $new[$k], array_merge($path, [$k]));
    }
    return;
  }
  if (gettype($old) !== gettype($new)) throw new InvalidArgumentException("Fel typ vid $where");
  if ($old === $new) return;

  $key = last_key($path);
  $isOpen = in_array($path[0] ?? null, config()['open_roots'], true);
  if (in_array($key, config()['image_keys'], true)) {
    if (!valid_image_path($new)) throw new InvalidArgumentException("Ogiltig bild vid $where");
    return;
  }
  if (!$isOpen && !in_array($key, config()['editable_keys'], true)) throw new InvalidArgumentException("Fältet $where får inte ändras");
  if (is_int($new)) {
    if ($new < 0 || $new > 10000000) throw new InvalidArgumentException("Orimligt värde vid $where");
    return;
  }
  if (!is_string($new)) throw new InvalidArgumentException("Fältet $where får inte ändras");
  if (trim($new) === '') throw new InvalidArgumentException("Tom text vid $where");
  if (mb_strlen($new) > 5000) throw new InvalidArgumentException("För lång text vid $where");
  if (preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $new)) throw new InvalidArgumentException("Otillåtna tecken vid $where");
}

// Länkar som redaktören skriver in: sidor på webbplatsen, webbadresser, e-post och telefon – inget annat.
// Sidfiler (t.ex. "offert.php" eller "index.php#kontakt") godkänns bara om de står i config 'pages'.
function valid_href(string $h): bool {
  if (preg_match('#^([\w\-]+\.(?:php|html?))(?:\?[\w=&%.\-]*)?(?:\#[\w\-]*)?$#', $h, $m)) return isset(config()['pages'][$m[1]]);
  return (bool)preg_match('#^(\#/[\w\-/]*|\#[\w\-]+|https?://[^\s"<>]+|mailto:[^\s"<>]+|tel:[\d+\- ]+)$#u', $h);
}

const LINKS_MAX = 8;

function validate_links($list): void {
  if (!is_array($list) || !array_is_list($list)) throw new InvalidArgumentException('Ogiltig lista med länkar');
  if (count($list) > LINKS_MAX) throw new InvalidArgumentException('Max ' . LINKS_MAX . ' länkar per avsnitt');
  foreach ($list as $i => $l) {
    $where = 'länk ' . ($i + 1);
    if (!is_array($l) || array_is_list($l) || array_keys($l) !== ['label', 'href'])
      throw new InvalidArgumentException("Felaktiga fält i $where");
    if (!is_string($l['label']) || trim($l['label']) === '' || mb_strlen($l['label']) > 80 || preg_match('/[\x00-\x1F\x7F]/', $l['label']))
      throw new InvalidArgumentException("Ogiltig länktext i $where");
    if (!is_string($l['href']) || mb_strlen($l['href']) > 500 || !valid_href($l['href']))
      throw new InvalidArgumentException("Ogiltig adress i $where (använd t.ex. #/priser, https://…, mailto:… eller tel:…)");
  }
}

// ---- YouTube-filmer ----
// Länkar till YouTube kan visas som filmrutor. Förhandsbilden sparas lokalt så att besökarens
// webbläsare inte kontaktar Google förrän filmen startas.
const YT_DIR = UPLOAD_DIR . '/youtube';

function youtube_id(string $href): ?string {
  return preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/))([\w-]{11})#', $href, $m) ? $m[1] : null;
}

function fetch_url(string $url): ?string {
  $d = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 6]]));
  if ($d === false && function_exists('curl_init')) {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_FAILONERROR => true]);
    $d = curl_exec($c);
    // curl_close() behövs inte sedan PHP 8.0 och ger en varning från 8.5.
  }
  return is_string($d) && $d !== '' ? $d : null;
}

// Alla YouTube-adresser i fält som heter href, var de än ligger i innehållet.
function youtube_ids($data, array &$ids = []): array {
  if (is_array($data)) foreach ($data as $k => $v) {
    if ($k === 'href' && is_string($v) && ($id = youtube_id($v))) $ids[$id] = true;
    else youtube_ids($v, $ids);
  }
  return array_keys($ids);
}

// Hämtar förhandsbilder som saknas. Misslyckas det visas filmen ändå, med mörk bakgrund bakom play-knappen.
function ensure_youtube_thumbs(array $site): void {
  if (!config()['youtube_thumbs']) return;
  foreach (youtube_ids($site) as $id) {
    if (is_file(YT_DIR . "/$id.jpg")) continue;
    foreach (['maxresdefault', 'hqdefault'] as $q) {
      $img = ($raw = fetch_url("https://i.ytimg.com/vi/$id/$q.jpg")) ? @imagecreatefromstring($raw) : false;
      if (!$img) continue;
      $w = min(960, imagesx($img));
      $out = imagescale($img, $w, (int)round(imagesy($img) * $w / imagesx($img)));
      if (!is_dir(YT_DIR)) mkdir(YT_DIR, 0775, true);
      imagejpeg($out, YT_DIR . "/$id.jpg", 82);
      break;
    }
  }
}

// ---- Session & svar ----

function start_session(): void {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
  session_name(config()['session_name']);
  session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
  session_start();
  $idle = config()['session_idle_minutes'] * 60;
  if (isset($_SESSION['user']) && time() - ($_SESSION['seen'] ?? 0) > $idle) $_SESSION = [];
  $_SESSION['seen'] = time();
}

// Inloggad men måste byta lösenord innan något annat är tillåtet.
// En användare som har tagits bort i CMS:et blir utloggad direkt.
function current_user(): ?string {
  $user = empty($_SESSION['must_change']) ? ($_SESSION['user'] ?? null) : null;
  return $user !== null && user_record($user) ? $user : null;
}

function pending_user(): ?string {
  return !empty($_SESSION['must_change']) ? ($_SESSION['user'] ?? null) : null;
}

// ---- Användare ----

const USERS_STATE = STORAGE_DIR . '/users.json';
const PASSWORD_MIN = 10;
const RESET_TTL = 3600;
const INVITE_TTL = 7 * 86400;

function users_state(): array {
  return is_file(USERS_STATE) ? (json_decode((string)file_get_contents(USERS_STATE), true) ?: []) : [];
}

// Användare finns på två ställen: i config.php (läggs in för hand) och i storage/users.json med
// 'cms' => true (skapade i CMS:et, modulen anvandare). users.json har också lösenordsbyten och
// återställningar för alla användare.
function user_names(): array {
  $cms = array_keys(array_filter(users_state(), fn($s) => !empty($s['cms'])));
  return array_values(array_unique(array_merge(array_map('strval', array_keys(config()['users'])), $cms)));
}

function user_record(string $user): ?array {
  $c = config()['users'][$user] ?? null;
  $s = users_state()[$user] ?? [];
  if ($c === null && empty($s['cms'])) return null;
  $r = $c === null ? ['hash' => ''] : (is_string($c) ? ['hash' => $c] : $c);
  $r += ['email' => null, 'must_change' => false];
  return array_merge($r, $s);
}

// Hittar användarnamnet utifrån användarnamn eller e-postadress.
function find_user(string $login): ?string {
  $login = strtolower(trim($login));
  if ($login === '') return null;
  foreach (user_names() as $name) {
    if ($login === $name || strtolower(user_record($name)['email'] ?? '') === $login) return $name;
  }
  return null;
}

function update_user_state(string $user, array $changes): void {
  if (!is_dir(dirname(USERS_STATE))) mkdir(dirname(USERS_STATE), 0775, true);
  $fp = fopen(USERS_STATE . '.lock', 'c');
  flock($fp, LOCK_EX);
  try {
    $all = users_state();
    $all[$user] = array_filter(array_merge($all[$user] ?? [], $changes), fn($v) => $v !== null);
    atomic_write(USERS_STATE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

function set_password(string $user, string $password): void {
  $rec = user_record($user);
  if (mb_strlen($password) < PASSWORD_MIN) throw new InvalidArgumentException('Lösenordet måste vara minst ' . PASSWORD_MIN . ' tecken.');
  if ($rec && password_verify($password, $rec['hash'])) throw new InvalidArgumentException('Välj ett annat lösenord än det nuvarande.');
  update_user_state($user, ['hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change' => false,
    'reset_hash' => null, 'reset_expires' => null, 'reset_kind' => null, 'changed' => date('c')]);
}

// Ny engångslänk där användaren väljer lösenord. En tidigare länk slutar gälla.
// $kind = 'reset' (glömt lösenord) eller 'invite' (ny användare, välkomsttext på sidan).
function issue_password_link(string $user, string $kind, int $ttl): string {
  $token = bin2hex(random_bytes(32));
  update_user_state($user, ['reset_hash' => hash('sha256', $token), 'reset_expires' => time() + $ttl, 'reset_kind' => $kind]);
  return rtrim(config()['site_url'], '/') . '/cms/index.php?reset=' . $token;
}

// Skapar en engångslänk (giltig en timme) och mejlar den. Returnerar om mejlet gick iväg – men "Glömt lösenord"
// visar samma svar oavsett, så att ingen kan ta reda på vilka adresser som finns.
function send_reset_link(string $login): bool {
  $user = find_user($login);
  $rec = $user ? user_record($user) : null;
  if (!$rec || empty($rec['email'])) return false;
  $link = issue_password_link($user, 'reset', RESET_TTL);
  $name = config()['site_name'];
  $body = "Hej!\n\nNågon (förhoppningsvis du) har bett om att få byta lösenord till redigeringsläget på webbplatsen för $name.\n\n"
    . "Klicka på länken för att välja ett nytt lösenord. Länken fungerar en gång och i en timme:\n$link\n\n"
    . "Ditt användarnamn är: $user\n\nHar du inte bett om detta kan du strunta i mejlet – ditt lösenord är oförändrat.\n";
  $sent = send_mail($rec['email'], "Byt lösenord – $name", $body);
  if (!$sent) error_log("[cms] Kunde inte skicka återställningsmejl till $user");
  return $sent;
}

// Inbjudan till en ny användare (eller en ny inbjudan om den förra gick ut). Länken gäller i 7 dagar.
// Returnerar länken så att den som bjöd in kan skicka den själv om mejlet inte går fram.
function send_invite(string $user, string $by): array {
  $rec = user_record($user);
  if (!$rec || empty($rec['email'])) throw new InvalidArgumentException('Användaren saknar e-postadress.');
  $link = issue_password_link($user, 'invite', INVITE_TTL);
  $name = config()['site_name'];
  $body = "Hej!\n\n$by har lagt till dig som redaktör för webbplatsen $name.\n\n"
    . "Klicka på länken för att välja ditt lösenord. Länken fungerar en gång och gäller i 7 dagar:\n$link\n\n"
    . "Ditt användarnamn är: $user\n\nSedan loggar du in på " . rtrim(config()['site_url'], '/') . "/cms/\n";
  $sent = send_mail($rec['email'], "Välkommen som redaktör – $name", $body);
  if (!$sent) error_log("[cms] Kunde inte skicka inbjudan till $user");
  return ['link' => $link, 'sent' => $sent];
}

// Börjar med en bokstav, så att namnet aldrig blir en siffernyckel i users.json.
const USERNAME_PATTERN = '/^[a-z][a-z0-9._-]{1,29}$/';

// Ny användare i storage/users.json. Lösenordet väljer hen själv via inbjudningslänken.
function create_user(string $user, string $email, string $by): array {
  $user = strtolower(trim($user));
  $email = trim($email);
  if (!preg_match(USERNAME_PATTERN, $user))
    throw new InvalidArgumentException('Användarnamnet ska vara 2–30 tecken och börja med en bokstav: små bokstäver a–z, siffror, punkt, bindestreck eller understreck.');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) throw new InvalidArgumentException('Ogiltig e-postadress.');
  if (find_user($user)) throw new InvalidArgumentException('Användarnamnet finns redan.');
  if (find_user($email)) throw new InvalidArgumentException('E-postadressen används redan av en annan användare.');
  update_user_state($user, ['cms' => true, 'email' => $email, 'hash' => '', 'must_change' => true,
    'created' => date('c'), 'created_by' => $by]);
  return send_invite($user, $by);
}

// Bara användare som skapats i CMS:et kan tas bort här – de i config.php tas bort i filen.
function delete_user(string $user): void {
  if (empty(users_state()[$user]['cms'])) throw new InvalidArgumentException('Den här användaren finns i inställningsfilen och kan inte tas bort här.');
  $fp = fopen(USERS_STATE . '.lock', 'c');
  flock($fp, LOCK_EX);
  try {
    $all = users_state();
    unset($all[$user]);
    atomic_write(USERS_STATE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

// Brödtext och bilagor som MIME: [rubrikrader, kropp].
// $attachments = [['name' => 'foto.jpg', 'mime' => 'image/jpeg', 'data' => …], …]
function mail_parts(string $body, array $attachments): array {
  $b64 = fn(string $s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
  $text = $b64(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body)));
  if (!$attachments) return [['Content-Type: text/plain; charset=utf-8', 'Content-Transfer-Encoding: base64'], $text];
  $bound = 'cms-' . bin2hex(random_bytes(12));
  $out = "--$bound\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n$text\r\n";
  foreach ($attachments as $a) {
    $file = preg_replace('/[^\w.\-]/', '_', (string)$a['name']);
    $mime = preg_match('#^[\w.+-]+/[\w.+-]+$#', (string)$a['mime']) ? $a['mime'] : 'application/octet-stream';
    $out .= "--$bound\r\nContent-Type: $mime; name=\"$file\"\r\nContent-Transfer-Encoding: base64\r\n"
      . "Content-Disposition: attachment; filename=\"$file\"\r\n\r\n" . $b64((string)$a['data']) . "\r\n";
  }
  return [["Content-Type: multipart/mixed; boundary=\"$bound\""], $out . "--$bound--"];
}

// Skickar via SMTP med inloggning om 'smtp' finns i config.php (rekommenderas), annars via PHP:s mail().
// $opt: 'reply_to' => e-postadress, 'attachments' => se mail_parts().
function send_mail(string $to, string $subject, string $body, array $opt = []): bool {
  $from = config()['mail_from'];
  $name = str_replace(["\r", "\n", '"'], '', config()['site_name']);
  $to = str_replace(["\r", "\n"], '', $to);
  $subject = mb_encode_mimeheader(str_replace(["\r", "\n"], ' ', $subject), 'UTF-8');
  $replyTo = (string)($opt['reply_to'] ?? '');
  $replyTo = $replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $replyTo) ? $replyTo : '';
  [$partHeaders, $partBody] = mail_parts($body, $opt['attachments'] ?? []);
  $common = array_merge($replyTo !== '' ? ["Reply-To: <$replyTo>"] : [], ['MIME-Version: 1.0'], $partHeaders);
  $smtp = config()['smtp'] ?? null;
  if (!$smtp || empty($smtp['pass'])) {
    $headers = implode("\r\n", array_merge(['From: ' . mb_encode_mimeheader($name, 'UTF-8') . " <$from>"], $common));
    return @mail($to, $subject, $partBody, $headers, "-f$from");
  }
  $domain = substr(strrchr($from, '@'), 1);
  $message = implode("\r\n", array_merge([
    'Date: ' . date('r'),
    'From: ' . mb_encode_mimeheader($name, 'UTF-8') . " <$from>",
    "To: <$to>",
    "Subject: $subject",
    'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domain>",
  ], $common, ['', $partBody]));
  try {
    smtp_send($smtp, $from, $to, $message);
    return true;
  } catch (Throwable $e) {
    error_log('[cms] SMTP: ' . $e->getMessage());
    return false;
  }
}

// Minimal SMTP-klient: STARTTLS (port 587) eller TLS direkt (port 465), AUTH LOGIN, ett mottagare.
function smtp_send(array $c, string $from, string $to, string $message): void {
  $port = (int)($c['port'] ?? 587);
  $host = ($port === 465 ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $port;
  $fp = @stream_socket_client($host, $errno, $errstr, 15);
  if (!$fp) throw new RuntimeException("Kunde inte ansluta till {$c['host']}:$port ($errstr)");
  stream_set_timeout($fp, 20);
  $read = function () use ($fp): array {
    $text = '';
    while (($line = fgets($fp, 1024)) !== false) {
      $text .= $line;
      if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return [(int)substr($text, 0, 3), trim($text)];
  };
  // $label visas i felmeddelandet i stället för raden, så att lösenord och meddelande aldrig loggas.
  $cmd = function (?string $line, array $ok, ?string $label = null) use ($fp, $read): string {
    if ($line !== null) fwrite($fp, $line . "\r\n");
    [$code, $text] = $read();
    if (!in_array($code, $ok, true)) throw new RuntimeException(($label ?? $line ?? 'Anslutning') . " → $text");
    return $text;
  };
  $ehloName = gethostname() ?: 'localhost';
  try {
    $cmd(null, [220]);
    $ehlo = $cmd("EHLO $ehloName", [250]);
    if ($port !== 465) {
      if (!str_contains($ehlo, 'STARTTLS')) throw new RuntimeException('Servern erbjuder inte kryptering (STARTTLS).');
      $cmd('STARTTLS', [220]);
      if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT))
        throw new RuntimeException('TLS-förhandlingen misslyckades.');
      $cmd("EHLO $ehloName", [250]);
    }
    $cmd('AUTH LOGIN', [334]);
    $cmd(base64_encode($c['user']), [334], 'Användarnamn');
    $cmd(base64_encode($c['pass']), [235], 'Inloggning (fel användarnamn eller lösenord?)');
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    // Rader som börjar med punkt dubbleras enligt SMTP (kan inte hända med base64, men för säkerhets skull).
    $cmd(preg_replace('/^\./m', '..', $message) . "\r\n.", [250], 'Meddelandet');
    fwrite($fp, "QUIT\r\n");
  } finally {
    fclose($fp);
  }
}

// Returnerar användarnamnet för en giltig återställningskod, annars null.
function user_for_reset(string $token): ?string {
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
  $h = hash('sha256', $token);
  foreach (users_state() as $user => $s) {
    if (isset($s['reset_hash'], $s['reset_expires']) && hash_equals($s['reset_hash'], $h) && $s['reset_expires'] > time()
      && user_record((string)$user)) return (string)$user;
  }
  return null;
}

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}

// Anrop som ändrar något måste vara POST med giltig CSRF-token.
function require_write(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))
    json_out(['error' => 'Ogiltig begäran. Ladda om sidan.'], 403);
}

function json_in(): array {
  return json_decode(file_get_contents('php://input'), true) ?: [];
}

function json_out(array $data, int $status = 200): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// Enkel spärr mot lösenordsgissning: max 5 misslyckade försök per IP på 15 minuter.
// $bucket skiljer inloggning från t.ex. "Glömt lösenord" så de inte låser varandra.
function login_attempts(?bool $failed = null, string $bucket = 'login'): int {
  $file = STORAGE_DIR . '/login-attempts.json';
  if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
  $ip = ($bucket === 'login' ? '' : "$bucket:") . ($_SERVER['REMOTE_ADDR'] ?? '?');
  $data = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
  $now = time();
  foreach ($data as $k => $list) {
    $data[$k] = array_values(array_filter($list, fn($t) => $now - $t < 900));
    if (!$data[$k]) unset($data[$k]);
  }
  if ($failed === true) $data[$ip][] = $now;
  if ($failed === false) unset($data[$ip]);
  file_put_contents($file, json_encode($data), LOCK_EX);
  return count($data[$ip] ?? []);
}

// Färger och namn till CMS-sidornas HTML.
function brand_css(): string {
  $c = config();
  $col = fn($v) => preg_match('/^#[0-9a-f]{3,8}$/i', $v) ? $v : '#2563EB';
  return ':root{--accent:' . $col($c['accent']) . ';--accent-dark:' . $col($c['accent_dark']) . '}';
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
