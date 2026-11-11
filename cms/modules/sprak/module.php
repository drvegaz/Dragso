<?php
// Modulen "sprak": flera språk. Se README och PLAN-sprak.md i cms-core.
//
// Huvudspråket (config 'language') är det som skrivs i redigeringsläget och ligger i data/site.json.
// Redaktören lägger till språk och översätter fält för fält på cms/page.php?m=sprak.
//
// Arbetsmaterial (inte publikt):
//   cms/storage/i18n/languages.json   [{ code, name, published, added, by }]
//   cms/storage/i18n/<kod>.json       { "<nyckel>": { text, source, status: "approved", by, at } }
//   cms/storage/i18n/history/         tidigare versioner av <kod>.json (högst en per tio minuter)
// Publicerat (byggs om efter varje skrivning av site.json och varje sparad översättning):
//   data/site.<kod>.json / .js        huvudspråket med godkända översättningar ovanpå (window.SITE, window.SITE_LANG)
//   data/languages.json / .js         huvudspråket + språk som visas: [{ code, name, main? }] (window.SITE_LANGUAGES)
//
// Nyckel = sökvägen i site.json, men poster i listor som har ett id adresseras med "@id" i stället för index
// (notices.@abc.text), så att översättningen följer med när listan ändras. "source" = huvudspråkets text när
// fältet översattes; skiljer den sig från texten nu är fältet ändrat. Saknas en översättning visas huvudspråket.

const I18N_DIR = STORAGE_DIR . '/i18n';
const I18N_LANGS = I18N_DIR . '/languages.json';
const I18N_HISTORY_KEEP = 50;
const I18N_TEXT_MAX = 5000;

// Språk som går att lägga till: kod (ISO 639-1) => namn på språket självt.
const I18N_LANGUAGES = [
  'sv' => 'Svenska', 'en' => 'English', 'de' => 'Deutsch', 'da' => 'Dansk', 'nb' => 'Norsk', 'fi' => 'Suomi',
  'nl' => 'Nederlands', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano', 'pl' => 'Polski',
  'pt' => 'Português', 'cs' => 'Čeština', 'et' => 'Eesti', 'lv' => 'Latviešu', 'lt' => 'Lietuvių',
  'uk' => 'Українська', 'ru' => 'Русский', 'zh' => '中文', 'ja' => '日本語',
];

function i18n_main(): string {
  $c = config()['language'];
  return isset(I18N_LANGUAGES[$c]) ? $c : 'sv';
}

function i18n_read(string $file, $default) {
  return is_file($file) ? (json_decode((string)file_get_contents($file), true) ?? $default) : $default;
}

function i18n_write(string $file, $data): void {
  if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
  atomic_write($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function i18n_languages(): array {
  return i18n_read(I18N_LANGS, []);
}

function i18n_lang(string $code): array {
  foreach (i18n_languages() as $l) if ($l['code'] === $code) return $l;
  throw new InvalidArgumentException('Språket finns inte. Ladda om sidan.');
}

function i18n_file(string $code): string {
  if (!preg_match('/^[a-z]{2}$/', $code)) throw new InvalidArgumentException('Ogiltig språkkod.');
  return I18N_DIR . "/$code.json";
}

function i18n_translations(string $code): array {
  return i18n_read(i18n_file($code), []);
}

// ---- Fälten som översätts ----

// Samma fält som redaktören får ändra (editable_keys / open_roots), utom bilder och translate_skip_keys,
// plus modulernas egna fält ('translate' => ['notices.#.linkLabel', …], * = valfri nyckel, # = valfritt index).
function i18n_translatable(array $path): bool {
  $c = config();
  $key = last_key($path);
  if (in_array($key, $c['image_keys'], true)) return false;
  foreach (modules() as $mod) foreach ($mod['translate'] ?? [] as $p) if (path_matches($path, $p)) return true;
  if (in_array($key, $c['translate_skip_keys'], true)) return false;
  return in_array($path[0] ?? null, $c['open_roots'], true) || in_array($key, $c['editable_keys'], true);
}

// Alla översättningsbara texter i site.json, i ordning: [nyckel => ['path' => 'a.0.b', 'source' => text]].
function i18n_fields(array $site): array {
  $out = [];
  $walk = function ($node, array $path, array $keyPath) use (&$walk, &$out): void {
    if (is_array($node)) {
      $list = array_is_list($node);
      foreach ($node as $k => $v) {
        $seg = $list && is_array($v) && is_string($v['id'] ?? null) && preg_match('/^[\w-]{1,40}$/', $v['id']) ? '@' . $v['id'] : $k;
        $walk($v, [...$path, $k], [...$keyPath, $seg]);
      }
      return;
    }
    if (!is_string($node) || trim($node) === '' || !$path || !i18n_translatable($path)) return;
    $out[implode('.', $keyPath)] = ['path' => implode('.', $path), 'source' => $node];
  };
  $walk($site, [], []);
  return $out;
}

// En post kan ha bara ett förslag från AI (suggestion) utan godkänd text – då saknas översättningen fortfarande.
function i18n_status(?array $t, string $source): string {
  if (!$t || !isset($t['text'])) return 'missing';
  return $t['source'] === $source ? 'done' : 'changed';
}

// Samma lista, t.ex. links.0.label och links.3.label (index → #).
function i18n_pattern(string $key): string {
  return preg_replace('/(^|\.)\d+(?=\.|$)/', '$1#', $key);
}

// Poster i listor utan id adresseras med index, så när de flyttas hamnar översättningen på fel post.
// En översättning vars fält saknas eller har fått en annan källtext flyttas därför till det fält i samma lista
// som nu har just den källtexten, om det finns precis ett sådant och det inte redan har en översättning som
// stämmer. Annars ligger den kvar (ändrad eller vilande), så att den kommer tillbaka om fältet gör det,
// t.ex. när en äldre version återställs.
function i18n_reconcile(array $fields, array $tr): array {
  $fits = fn(string $k) => isset($fields[$k], $tr[$k]) && $fields[$k]['source'] === ($tr[$k]['source'] ?? null);
  $loose = array_filter(array_keys($tr), fn($k) => !$fits($k));
  if (!$loose) return [$tr, false];
  $free = [];
  foreach ($fields as $k => $f) if (!$fits($k)) $free[$f['source']][] = $k;
  $moves = [];
  foreach ($loose as $k) {
    $cand = array_values(array_filter($free[$tr[$k]['source'] ?? ''] ?? [], fn($c) => $c !== $k && i18n_pattern($c) === i18n_pattern($k)));
    if (count($cand) === 1) $moves[$k] = $cand[0];
  }
  // Två översättningar till samma fält: låt bli. Målets egen översättning stämmer aldrig (annars vore det inte
  // ledigt); flyttas den inte själv försvinner den (den finns kvar i historiken).
  $targets = array_count_values($moves);
  $moves = array_filter($moves, fn($to) => $targets[$to] === 1);
  if (!$moves) return [$tr, false];
  $out = $tr;
  foreach (array_keys($moves) as $from) unset($out[$from]);
  foreach ($moves as $from => $to) $out[$to] = $tr[$from];
  return [$out, true];
}

function i18n_stats(array $fields, array $tr): array {
  $s = ['total' => count($fields), 'done' => 0, 'changed' => 0, 'missing' => 0, 'suggestions' => 0];
  foreach ($fields as $k => $f) {
    $s[i18n_status($tr[$k] ?? null, $f['source'])]++;
    if (isset($tr[$k]['suggestion'])) $s['suggestions']++;
  }
  return $s;
}

// ---- Grupper (sidor) ----

// Webbplatser med sidfiler ('pages' i config): vilka data-cms som står på vilken sida. Hämtas över HTTP från
// site_url och sparas i tio minuter. Fält som inte hittas hamnar under "Övrigt".
function i18n_page_map(): array {
  $pages = config()['pages'];
  $base = rtrim(config()['site_url'], '/');
  // PHP:s inbyggda server svarar på en fråga i taget och skulle vänta på sig själv.
  if (!$pages || $base === '' || PHP_SAPI === 'cli-server') return [];
  $cache = I18N_DIR . '/sidor.json';
  $c = i18n_read($cache, null);
  if (is_array($c) && ($c['at'] ?? 0) > time() - 600) return $c['map'];
  $map = [];
  foreach (array_keys($pages) as $file) {
    $html = fetch_url("$base/$file");
    if (!$html || !preg_match_all('/data-cms(?:-alt)?="([^"]+)"/', $html, $m)) continue;
    foreach ($m[1] as $p) $map[html_entity_decode($p, ENT_QUOTES)] ??= $file;
  }
  i18n_write($cache, ['at' => time(), 'map' => $map]);
  return $map;
}

// [gruppid => ['title', 'url', 'hash']] i ordning, och gruppid per fält. url + hash öppnar sidan i redigeringsläget.
function i18n_groups(array $fields, array $site): array {
  $files = config()['pages'];
  $prefixes = config()['page_content'];
  $map = $files && !$prefixes ? i18n_page_map() : [];
  $groups = [];
  $of = [];
  foreach ($fields as $k => $f) {
    if ($files && $prefixes) {
      $file = '';
      foreach ($prefixes as $pf => $prefix) if (isset($files[$pf]) && str_starts_with($f['path'] . '.', $prefix . '.')) { $file = $pf; break; }
      $id = $file;
      $g = $file === '' ? ['title' => 'Det som syns på alla sidor (meny, sidfot, knappar)', 'url' => edit_url(), 'hash' => '']
        : ['title' => $files[$file], 'url' => '../' . $file . '?edit', 'hash' => ''];
    } elseif ($files) {
      $file = $map[$f['path']] ?? '';
      $id = $file;
      $g = $file === '' ? ['title' => 'Övrigt', 'url' => edit_url(), 'hash' => '']
        : ['title' => $files[$file], 'url' => '../' . $file . '?edit', 'hash' => ''];
    } elseif (preg_match('/^pages\.([^.]+)\./', $f['path'], $m)) {
      $id = 'pages.' . $m[1];
      $g = ['title' => (string)($site['pages'][$m[1]]['title'] ?? $m[1]), 'url' => edit_url(), 'hash' => '#/' . $m[1]];
    } else {
      $id = '';
      $g = ['title' => 'Startsida och det som syns på alla sidor', 'url' => edit_url(), 'hash' => ''];
    }
    $groups[$id] ??= $g;
    $of[$k] = $id;
  }
  // Det gemensamma först, sedan sidorna i den ordning de förekommer.
  if (isset($groups[''])) $groups = ['' => $groups['']] + $groups;
  return [$groups, $of];
}

// ---- Publicering ----

function i18n_set(array &$site, string $path, string $value): void {
  $ref = &$site;
  foreach (explode('.', $path) as $k) $ref = &$ref[ctype_digit($k) ? (int)$k : $k];
  $ref = $value;
}

function i18n_unpublish(string $code): void {
  foreach (['json', 'js'] as $ext) @unlink(ROOT . "/data/site.$code.$ext");
}

// Bygger om alla språkfiler i data/. Körs inom with_site_lock().
function i18n_publish_all(array $site): void {
  $fields = i18n_fields($site);
  $main = i18n_main();
  $pub = [['code' => $main, 'name' => I18N_LANGUAGES[$main], 'main' => true]];
  foreach (i18n_languages() as $l) {
    $code = $l['code'];
    [$tr, $moved] = i18n_reconcile($fields, i18n_translations($code));
    if ($moved) i18n_write(i18n_file($code), $tr);
    if (empty($l['published'])) { i18n_unpublish($code); continue; }
    $out = $site;
    foreach ($tr as $k => $t) {
      if (($t['status'] ?? '') === 'approved' && isset($fields[$k])) i18n_set($out, $fields[$k]['path'], (string)$t['text']);
    }
    atomic_write(ROOT . "/data/site.$code.json", encode_site($out));
    atomic_write(ROOT . "/data/site.$code.js", site_js($out, 'window.SITE_LANG = ' . json_encode($code) . ";\n"));
    $pub[] = ['code' => $code, 'name' => $l['name']];
  }
  $json = json_encode($pub, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  atomic_write(ROOT . '/data/languages.json', $json);
  atomic_write(ROOT . '/data/languages.js', "// GENERERAD av cms/ (modulen sprak).\nwindow.SITE_LANGUAGES = $json;\n");
}

// Föregående version av översättningarna, högst en kopia per tio minuter.
function i18n_backup(string $code): void {
  $f = i18n_file($code);
  if (!is_file($f)) return;
  $dir = I18N_DIR . '/history';
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  $files = glob("$dir/$code-*.json") ?: [];
  rsort($files);
  if ($files && filemtime($files[0]) > time() - 600) return;
  copy($f, "$dir/$code-" . date('Ymd-His') . '.json');
  foreach (array_slice($files, I18N_HISTORY_KEEP - 1) as $old) @unlink($old);
}

function i18n_check_text(string $t): void {
  if (trim($t) === '') throw new InvalidArgumentException('Översättningen är tom.');
  if (mb_strlen($t) > I18N_TEXT_MAX) throw new InvalidArgumentException('Översättningen är för lång (högst ' . I18N_TEXT_MAX . ' tecken).');
  if (preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $t)) throw new InvalidArgumentException('Översättningen innehåller otillåtna tecken.');
}

// ---- Ordlista och inställningar för AI-översättning ----
// cms/storage/i18n/ordlista.json: { about, tone: { <kod>: text }, entries: [{ id, term, type, fixed: { <kod>: text }, explain, note }] }
// type: keep = översätts inte, fixed = fast översättning per språk, explain = förklaring som hjälper AI:n.
// Tills någon har sparat används cms/site/ordlista-start.json (samma format), annars config 'translate_about'.

const I18N_GLOSSARY = I18N_DIR . '/ordlista.json';
const I18N_GLOSSARY_MAX = 300;

// Språkens namn på svenska, till instruktionen ("från svenska till tyska").
const I18N_NAMES_SV = [
  'sv' => 'svenska', 'en' => 'engelska', 'de' => 'tyska', 'da' => 'danska', 'nb' => 'norska', 'fi' => 'finska',
  'nl' => 'nederländska', 'fr' => 'franska', 'es' => 'spanska', 'it' => 'italienska', 'pl' => 'polska',
  'pt' => 'portugisiska', 'cs' => 'tjeckiska', 'et' => 'estniska', 'lv' => 'lettiska', 'lt' => 'litauiska',
  'uk' => 'ukrainska', 'ru' => 'ryska', 'zh' => 'kinesiska', 'ja' => 'japanska',
];

function i18n_glossary(): array {
  $g = i18n_read(I18N_GLOSSARY, null) ?? i18n_read(SITE_DIR . '/ordlista-start.json', null) ?? [];
  return ['about' => (string)($g['about'] ?? config()['translate_about'] ?? ''), 'tone' => (array)($g['tone'] ?? []),
    'entries' => array_values((array)($g['entries'] ?? []))];
}

function i18n_validate_glossary($g): array {
  $text = function ($v, int $max, string $where, bool $required = false): string {
    if (!is_string($v) || mb_strlen($v) > $max || preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $v))
      throw new InvalidArgumentException("Ogiltig text i $where (högst $max tecken).");
    if ($required && trim($v) === '') throw new InvalidArgumentException("$where är tom.");
    return trim($v);
  };
  if (!is_array($g) || !is_array($g['entries'] ?? null) || !array_is_list($g['entries'])) throw new InvalidArgumentException('Ogiltig ordlista.');
  if (count($g['entries']) > I18N_GLOSSARY_MAX) throw new InvalidArgumentException('Högst ' . I18N_GLOSSARY_MAX . ' ord i ordlistan.');
  $out = ['about' => $text($g['about'] ?? '', 1000, 'beskrivningen'), 'tone' => [], 'entries' => []];
  foreach ((array)($g['tone'] ?? []) as $code => $t) {
    if (!isset(I18N_LANGUAGES[$code])) continue;
    if (($t = $text($t, 500, 'tonen för ' . I18N_LANGUAGES[$code])) !== '') $out['tone'][$code] = $t;
  }
  $ids = [];
  foreach ($g['entries'] as $i => $e) {
    $where = 'ord ' . ($i + 1);
    if (!is_array($e) || !is_string($e['id'] ?? null) || !preg_match('/^[a-z0-9]{1,20}$/', $e['id']) || isset($ids[$e['id']]))
      throw new InvalidArgumentException("Ogiltigt id i $where.");
    $ids[$e['id']] = true;
    $type = $e['type'] ?? '';
    if (!in_array($type, ['keep', 'fixed', 'explain'], true)) throw new InvalidArgumentException("Välj vad som gäller för $where.");
    $x = ['id' => $e['id'], 'term' => $text($e['term'] ?? '', 200, "ordet ($where)", true), 'type' => $type,
      'fixed' => [], 'explain' => $text($e['explain'] ?? '', 300, "förklaringen ($where)"), 'note' => $text($e['note'] ?? '', 200, "kommentaren ($where)")];
    foreach ((array)($e['fixed'] ?? []) as $code => $t) {
      if (isset(I18N_LANGUAGES[$code]) && ($t = $text($t, 200, "översättningen ($where)")) !== '') $x['fixed'][$code] = $t;
    }
    if ($type === 'explain' && $x['explain'] === '') throw new InvalidArgumentException("Skriv en förklaring för \"{$x['term']}\".");
    if ($type === 'fixed' && !$x['fixed']) throw new InvalidArgumentException("Skriv minst en översättning för \"{$x['term']}\".");
    $out['entries'][] = $x;
  }
  return $out;
}

// Ordlistan som rader i instruktionen, för ett språk.
function i18n_glossary_lines(array $g, string $code): string {
  $lines = [];
  foreach ($g['entries'] as $e) {
    $note = $e['note'] !== '' ? " ({$e['note']})" : '';
    if ($e['type'] === 'keep') $lines[] = "  - {$e['term']}: översätts inte$note";
    elseif ($e['type'] === 'explain') $lines[] = "  - {$e['term']}: betyder {$e['explain']}$note";
    elseif (isset($e['fixed'][$code])) $lines[] = "  - {$e['term']}: skriv \"{$e['fixed'][$code]}\"$note";
  }
  return $lines ? implode("\n", $lines) : '  (inga)';
}

// ---- AI-översättning: omgångar, instruktion och inläsning ----
// En omgång = en kopierad instruktion. cms/storage/i18n/omgangar/<id>.json: { id, code, created, by, items: { t1: { key, source, previous? } } }
// AI:n ser bara de korta id:na (t1, t2 …), så den kan inte förstöra sökvägarna.

const I18N_BATCH_DIR = I18N_DIR . '/omgangar';
const I18N_BATCH_DAYS = 30;
const I18N_PART_CHARS = 12000;   // ungefär hur mycket text (JSON) en del får innehålla

function i18n_template(): string {
  $own = SITE_DIR . '/sprak-instruktion.txt';
  return (string)file_get_contents(is_file($own) ? $own : __DIR__ . '/instruktion.txt');
}

// Skapar en eller flera omgångar för fälten och returnerar instruktionerna: [['id', 'count', 'prompt'], …].
function i18n_make_batches(string $code, array $keys, array $fields, array $tr, array $groups, array $of): array {
  if (!is_dir(I18N_BATCH_DIR)) mkdir(I18N_BATCH_DIR, 0775, true);
  foreach (glob(I18N_BATCH_DIR . '/*.json') ?: [] as $old) if (filemtime($old) < time() - I18N_BATCH_DAYS * 86400) @unlink($old);
  $main = i18n_main();
  // Dela upp så att varje del håller sig under I18N_PART_CHARS.
  $parts = [[]];
  $size = 0;
  foreach ($keys as $k) {
    $f = $fields[$k];
    $item = ['page' => $groups[$of[$k]]['title'] ?? '', $main => $f['source']];
    $t = $tr[$k] ?? null;
    if (i18n_status($t, $f['source']) === 'changed') { $item['previous_' . $main] = $t['source']; $item['previous'] = $t['text']; }
    $len = strlen(json_encode($item, JSON_UNESCAPED_UNICODE));
    if ($size + $len > I18N_PART_CHARS && $parts[count($parts) - 1]) { $parts[] = []; $size = 0; }
    $parts[count($parts) - 1][$k] = $item;
    $size += $len;
  }
  $g = i18n_glossary();
  $c = config();
  $stamp = $code . '-' . date('md') . '-' . bin2hex(random_bytes(2));
  $out = [];
  foreach ($parts as $n => $part) {
    $id = $stamp . (count($parts) > 1 ? '-' . ($n + 1) : '');
    $items = [];
    $texts = [];
    $i = 0;
    foreach ($part as $k => $item) {
      $tid = 't' . (++$i);
      $items[$tid] = ['key' => $k, 'source' => $fields[$k]['source']];
      $texts[$tid] = $item;
    }
    i18n_write(I18N_BATCH_DIR . "/$id.json", ['id' => $id, 'code' => $code, 'created' => date('c'), 'by' => current_user(), 'items' => $items]);
    $tone = $g['tone'][$code] ?? 'Vänlig och enkel ton, som på ' . I18N_NAMES_SV[$main] . '.';
    $prompt = strtr(i18n_template(), [
      '{site_name}' => $c['site_name'], '{site_url}' => $c['site_url'], '{site_about}' => $g['about'],
      '{main_name}' => I18N_NAMES_SV[$main], '{main_code}' => $main,
      '{lang_name}' => I18N_NAMES_SV[$code], '{lang_code}' => $code, '{tone}' => $tone,
      '{glossary}' => i18n_glossary_lines($g, $code), '{batch}' => $id, '{part}' => 'del ' . ($n + 1) . ' av ' . count($parts),
      '{texts}' => json_encode($texts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $out[] = ['id' => $id, 'count' => count($items), 'prompt' => $prompt];
  }
  return $out;
}

// Läser svaret från AI:n, förlåtande. Returnerar [data, noteringar]. Se test/sprak/ i cms-core för riktiga svar.
function i18n_parse_answer(string $raw): array {
  if (str_contains($raw, 'SÅ HÄR SKA DU SVARA'))
    throw new InvalidArgumentException('Det här är instruktionen, inte svaret från AI:n. Kopiera svaret i chatten i stället.');
  $notes = [];
  $body = preg_replace(['/^\x{FEFF}/u', '/```(?:json)?/i'], '', $raw);
  // ChatGPT:s kopieringsknapp ger Markdown: "\" sist på raden och &#x20; för inledande mellanslag.
  if (preg_match('/\\\\\r?\n/', $body) || preg_match('/^(?:&#x20;|&#32;|&nbsp;)/mi', $body)) {
    $body = preg_replace('/\\\\(\r?\n)/', '$1', $body);
    $body = preg_replace_callback('/^((?:&#x20;|&#32;|&nbsp;)+)/mi', fn($m) => str_repeat(' ', substr_count($m[1], ';')), $body);
    $notes[] = 'Svaret var kopierat som Markdown och har städats.';
  }
  $a = strpos($body, '{');
  $b = strrpos($body, '}');
  if ($a === false || $b === false || $b < $a) throw new InvalidArgumentException('Hittar inget svar att läsa in. Kopiera hela svaret från AI:n och klistra in det här.');
  if (trim(substr($body, 0, $a)) !== '' || trim(substr($body, $b + 1)) !== '') $notes[] = 'Text före eller efter svaret togs bort.';
  $json = substr($body, $a, $b - $a + 1);
  $data = json_decode($json, true);
  // Markdown kan också ha lagt "\" före tecken som _ * [ ], vilket inte är giltig JSON.
  if (!is_array($data)) $data = json_decode(preg_replace('/\\\\([_*\[\]()#+\-.!`>~|])/', '$1', $json), true);
  if (!is_array($data) || !is_array($data['texts'] ?? null))
    throw new InvalidArgumentException('Svaret gick inte att läsa. Kontrollera att du har kopierat hela svaret, eller be AI:n svara igen med bara JSON.');
  return [$data, $notes];
}

// Varningar för ett förslag: det som instruktionen säger ska behållas men saknas.
function i18n_check_suggestion(string $source, string $text, array $g, string $code): array {
  $w = [];
  if (substr_count($source, "\n") !== substr_count($text, "\n")) $w[] = 'Radbrytningarna skiljer sig från originalet.';
  preg_match_all('/\d+/', $source, $m);
  $miss = array_values(array_filter(array_unique($m[0]), fn($n) => !str_contains($text, $n)));
  if ($miss) $w[] = 'Siffror saknas: ' . implode(', ', $miss) . '.';
  if (mb_strlen($source) <= 30 && mb_strlen($text) > mb_strlen($source) * 2.5 + 5) $w[] = 'Kort text har blivit lång.';
  preg_match_all('/\{[^{}\s]{1,30}\}/u', $source, $m);
  $miss = array_values(array_filter(array_unique($m[0]), fn($p) => !str_contains($text, $p)));
  if ($miss) $w[] = 'Platshållare saknas: ' . implode(', ', $miss) . '. De ska stå kvar exakt.';
  // Hela ord: "Artilleristen" ska inte träffa "Kustartilleristen".
  $word = fn(string $term, string $in, bool $ci) => (bool)preg_match('/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u' . ($ci ? 'i' : ''), $in);
  foreach ($g['entries'] as $e) {
    if (!$word($e['term'], $source, true)) continue;
    if ($e['type'] === 'keep' && !$word($e['term'], $text, false)) $w[] = "\"{$e['term']}\" ska inte översättas.";
    if ($e['type'] === 'fixed' && isset($e['fixed'][$code]) && mb_stripos($text, $e['fixed'][$code]) === false)
      $w[] = "\"{$e['term']}\" ska bli \"{$e['fixed'][$code]}\".";
  }
  return $w;
}

// ---- Svar till sidan ----

function i18n_overview(): array {
  $fields = i18n_fields(load_site()['site']);
  $langs = array_map(fn($l) => $l + i18n_stats($fields, i18n_translations($l['code'])), i18n_languages());
  $taken = array_merge([i18n_main()], array_column($langs, 'code'));
  $available = [];
  foreach (I18N_LANGUAGES as $code => $name) if (!in_array($code, $taken, true)) $available[] = ['code' => $code, 'name' => $name];
  return ['main' => ['code' => i18n_main(), 'name' => I18N_LANGUAGES[i18n_main()]], 'languages' => $langs, 'available' => $available];
}

function i18n_field_out(string $key, array $f, ?array $t, string $group): array {
  $status = i18n_status($t, $f['source']);
  $s = $t['suggestion'] ?? null;
  return ['key' => $key, 'path' => $f['path'], 'group' => $group, 'source' => $f['source'], 'text' => $t['text'] ?? '',
    'status' => $status, 'oldSource' => $status === 'changed' ? $t['source'] : null, 'by' => $t['by'] ?? null, 'at' => $t['at'] ?? null,
    'suggestion' => $s ? ['text' => $s['text'], 'at' => $s['at'], 'by' => $s['by'], 'warnings' => $s['warnings'] ?? [],
      'sourceChanged' => $s['source'] !== $f['source']] : null];
}

return [
  'title' => 'Språk',
  'page' => __DIR__ . '/page.php',
  'after_save' => function (array $site): void { i18n_publish_all($site); },
  'api' => function (string $action): void {
    if (!str_starts_with($action, 'i18n_')) return;

    if ($action === 'i18n_overview') json_out(i18n_overview());

    if ($action === 'i18n_fields') {
      $code = (string)($_GET['code'] ?? '');
      $lang = i18n_lang($code);
      $site = load_site()['site'];
      $fields = i18n_fields($site);
      [$tr] = i18n_reconcile($fields, i18n_translations($code));
      [$groups, $of] = i18n_groups($fields, $site);
      $out = [];
      foreach ($fields as $k => $f) $out[] = i18n_field_out($k, $f, $tr[$k] ?? null, $of[$k]);
      $g = [];
      foreach ($groups as $id => $x) $g[] = ['id' => $id] + $x;
      json_out(['code' => $code, 'name' => $lang['name'], 'groups' => $g, 'fields' => $out] + i18n_stats($fields, $tr));
    }

    if ($action === 'i18n_glossary') {
      $g = i18n_glossary();
      json_out($g + ['base' => sha1(json_encode($g)), 'languages' => i18n_languages(), 'main' => i18n_main()]);
    }

    require_write();
    $in = json_in();
    $code = (string)($in['code'] ?? '');

    if ($action === 'i18n_glossary_save') {
      $g = i18n_validate_glossary($in);
      with_site_lock(function () use ($g, $in): void {
        if (($in['base'] ?? '') !== sha1(json_encode(i18n_glossary())))
          json_out(['error' => 'Någon annan har ändrat ordlistan sedan du öppnade sidan. Ladda om och gör om dina ändringar.'], 409);
        i18n_write(I18N_GLOSSARY, $g);
      });
      json_out($g + ['base' => sha1(json_encode($g)), 'languages' => i18n_languages(), 'main' => i18n_main()]);
    }

    // Instruktion till AI:n för de valda fälten: scope = todo (saknas + ändrade), changed, all, eller g:<gruppid> (en sida).
    if ($action === 'i18n_ai_prompt') {
      $lang = i18n_lang($code);
      $scope = (string)($in['scope'] ?? 'todo');
      $site = load_site()['site'];
      $fields = i18n_fields($site);
      [$tr] = i18n_reconcile($fields, i18n_translations($code));
      [$groups, $of] = i18n_groups($fields, $site);
      $keys = array_values(array_filter(array_keys($fields), fn($k) => match (true) {
        $scope === 'todo' => i18n_status($tr[$k] ?? null, $fields[$k]['source']) !== 'done',
        $scope === 'changed' => i18n_status($tr[$k] ?? null, $fields[$k]['source']) === 'changed',
        $scope === 'all' => true,
        default => 'g:' . $of[$k] === $scope,
      }));
      if (!$keys) throw new InvalidArgumentException('Det finns inga texter att översätta i det du har valt.');
      json_out(['parts' => i18n_make_batches($code, $keys, $fields, $tr, $groups, $of), 'name' => $lang['name']]);
    }

    // Svaret från AI:n blir förslag. En godkänd översättning skrivs aldrig över – förslaget läggs bredvid.
    if ($action === 'i18n_ai_import') {
      [$data, $notes] = i18n_parse_answer((string)($in['text'] ?? ''));
      $id = (string)($data['batch'] ?? '');
      $file = I18N_BATCH_DIR . "/$id.json";
      if (!preg_match('/^[a-z]{2}-[\w-]{1,30}$/', $id) || !is_file($file))
        throw new InvalidArgumentException('Svaret hör inte till någon instruktion som finns kvar (omgångar sparas i ' . I18N_BATCH_DAYS . ' dagar). Kopiera instruktionen igen.');
      $batch = i18n_read($file, []);
      if ($batch['code'] !== $code) throw new InvalidArgumentException('Svaret gäller ' . (I18N_LANGUAGES[$batch['code']] ?? $batch['code']) . ', inte ' . (I18N_LANGUAGES[$code] ?? $code) . '. Välj det språket och läs in igen.');
      if (($data['lang'] ?? $code) !== $code) $notes[] = 'Svaret angav språket "' . $data['lang'] . '", men instruktionen gällde ' . $code . '.';
      $report = with_site_lock(function () use ($code, $batch, $data): array {
        i18n_lang($code);
        $fields = i18n_fields(load_site()['site']);
        [$tr] = i18n_reconcile($fields, i18n_translations($code));
        $g = i18n_glossary();
        $r = ['imported' => 0, 'missing' => [], 'unknown' => [], 'gone' => 0, 'invalid' => 0, 'sourceChanged' => 0, 'warnings' => 0];
        foreach ($batch['items'] as $tid => $item) if (!array_key_exists($tid, $data['texts'])) $r['missing'][] = $tid;
        foreach ($data['texts'] as $tid => $text) {
          $item = $batch['items'][$tid] ?? null;
          if (!$item) { $r['unknown'][] = (string)$tid; continue; }
          $f = $fields[$item['key']] ?? null;
          if (!$f) { $r['gone']++; continue; }
          $text = is_string($text) ? trim(str_replace("\r\n", "\n", $text)) : '';
          if (!str_contains($f['source'], "\n")) $text = preg_replace('/\s*\n\s*/', ' ', $text);
          try { i18n_check_text($text); } catch (InvalidArgumentException $e) { $r['invalid']++; continue; }
          $warn = i18n_check_suggestion($item['source'], $text, $g, $code);
          if ($item['source'] !== $f['source']) $r['sourceChanged']++;
          if ($warn) $r['warnings']++;
          $tr[$item['key']] = ($tr[$item['key']] ?? []);
          $tr[$item['key']]['suggestion'] = ['text' => $text, 'source' => $item['source'], 'at' => date('c'), 'by' => current_user(),
            'batch' => $batch['id'], 'warnings' => $warn];
          $r['imported']++;
        }
        i18n_backup($code);
        i18n_write(i18n_file($code), $tr);
        return $r;
      });
      json_out($report + ['notes' => $notes, 'total' => count($batch['items'])]);
    }

    // Avvisa förslag (en eller flera). Den godkända texten, om någon, står kvar.
    if ($action === 'i18n_reject') {
      $keys = array_map('strval', (array)($in['keys'] ?? []));
      with_site_lock(function () use ($code, $keys): void {
        i18n_lang($code);
        $tr = i18n_translations($code);
        foreach ($keys as $k) {
          if (!isset($tr[$k]['suggestion'])) continue;
          unset($tr[$k]['suggestion']);
          if (!$tr[$k]) unset($tr[$k]);
        }
        i18n_write(i18n_file($code), $tr);
      });
      json_out(['ok' => true]);
    }

    // Godkänn förslagen som de är. Källtexten blir den som förslaget gjordes för, så ett fält som har ändrats
    // sedan instruktionen kopierades visas fortfarande som ändrat.
    if ($action === 'i18n_approve') {
      $keys = array_map('strval', (array)($in['keys'] ?? []));
      $n = with_site_lock(function () use ($code, $keys): int {
        i18n_lang($code);
        $site = load_site()['site'];
        $fields = i18n_fields($site);
        [$tr] = i18n_reconcile($fields, i18n_translations($code));
        $n = 0;
        foreach ($keys as $k) {
          $s = $tr[$k]['suggestion'] ?? null;
          if (!$s || !isset($fields[$k])) continue;
          $tr[$k] = ['text' => $s['text'], 'source' => $s['source'], 'status' => 'approved', 'by' => current_user(), 'at' => date('c')];
          $n++;
        }
        i18n_backup($code);
        i18n_write(i18n_file($code), $tr);
        i18n_publish_all($site);
        return $n;
      });
      json_out(['ok' => true, 'approved' => $n]);
    }

    if ($action === 'i18n_language_add') {
      if (!isset(I18N_LANGUAGES[$code]) || $code === i18n_main()) throw new InvalidArgumentException('Det språket går inte att lägga till.');
      with_site_lock(function () use ($code): void {
        $langs = i18n_languages();
        if (in_array($code, array_column($langs, 'code'), true)) throw new InvalidArgumentException('Språket finns redan.');
        $langs[] = ['code' => $code, 'name' => I18N_LANGUAGES[$code], 'published' => false, 'added' => date('c'), 'by' => current_user()];
        i18n_write(I18N_LANGS, $langs);
        i18n_publish_all(load_site()['site']);
      });
      json_out(i18n_overview());
    }

    if ($action === 'i18n_language_publish') {
      $published = ($in['published'] ?? null) === true;
      with_site_lock(function () use ($code, $published): void {
        i18n_lang($code);
        $langs = array_map(fn($l) => $l['code'] === $code ? ['published' => $published] + $l : $l, i18n_languages());
        i18n_write(I18N_LANGS, $langs);
        i18n_publish_all(load_site()['site']);
      });
      json_out(i18n_overview());
    }

    // Översättningarna ligger kvar i storage, så att de kommer tillbaka om språket läggs till igen.
    if ($action === 'i18n_language_remove') {
      with_site_lock(function () use ($code): void {
        i18n_lang($code);
        i18n_write(I18N_LANGS, array_values(array_filter(i18n_languages(), fn($l) => $l['code'] !== $code)));
        i18n_unpublish($code);
        i18n_publish_all(load_site()['site']);
      });
      json_out(i18n_overview());
    }

    // Ett fält i taget. Tom text tar bort översättningen (huvudspråket visas). Samma anrop ska användas av
    // redigering direkt på den översatta sidan senare.
    if ($action === 'i18n_save') {
      $key = (string)($in['key'] ?? '');
      $text = str_replace("\r\n", "\n", (string)($in['text'] ?? ''));
      $field = with_site_lock(function () use ($code, $key, $text): array {
        i18n_lang($code);
        $site = load_site()['site'];
        $fields = i18n_fields($site);
        $f = $fields[$key] ?? null;
        if (!$f) throw new InvalidArgumentException('Texten finns inte längre på webbplatsen. Ladda om sidan.');
        if (!str_contains($f['source'], "\n")) $text = preg_replace('/\s*\n\s*/', ' ', $text);
        $text = trim($text);
        [$tr] = i18n_reconcile($fields, i18n_translations($code));
        if ($text === '') unset($tr[$key]);
        else {
          i18n_check_text($text);
          $tr[$key] = ['text' => $text, 'source' => $f['source'], 'status' => 'approved', 'by' => current_user(), 'at' => date('c')];
        }
        i18n_backup($code);
        i18n_write(i18n_file($code), $tr);
        i18n_publish_all($site);
        return ['field' => i18n_field_out($key, $f, $tr[$key] ?? null, ''), 'stats' => i18n_stats($fields, $tr)];
      });
      json_out(['ok' => true] + $field);
    }

    json_out(['error' => 'Okänd åtgärd'], 404);
  },
];
