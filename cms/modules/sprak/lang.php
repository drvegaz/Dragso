<?php
// Språk på webbplatser som ritas med PHP (modulen sprak). Läs in den överst på varje sida:
//
//   require __DIR__ . '/cms/modules/sprak/lang.php';
//   $site = cms_site();             // innehållet på sidans språk: site.json eller data/site.<kod>.json
//   cms_lang()                      // språket i adressen ("en" för /en/camping), annars huvudspråket
//   cms_url('camping')              // "/camping" på huvudspråket, "/en/camping" på engelska; '' = startsidan
//   cms_url('camping', 'de')        // samma sida på ett annat språk
//   cms_hreflang()                  // <link rel="alternate" hreflang=…> för sidan på alla språk som visas
//   cms_languages()                 // [{ code, name, main?, url, current }] för en språkväljare
//
// Adresserna: webbplatsens .htaccess skickar /en/camping till camping.php?lang=en och /en/ till index.php?lang=en
// (se README). Sidans namn är filnamnet utan .php, och index.php är startsidan.
// Ett dolt eller okänt språk skickas vidare till samma sida på huvudspråket, och så gör redigeringsläget (?edit),
// eftersom texterna alltid redigeras på huvudspråket.
declare(strict_types=1);
require_once __DIR__ . '/../../lib.php';

function cms_main_lang(): string {
  return (string)config()['language'];
}

// Huvudspråket och de språk som visas på webbplatsen (data/languages.json, skrivs av modulen).
function cms_languages_all(): array {
  static $l = null;
  if ($l !== null) return $l;
  $f = ROOT . '/data/languages.json';
  $l = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
  if (!$l) $l = [['code' => cms_main_lang(), 'name' => cms_main_lang(), 'main' => true]];
  return $l;
}

// Sidan som körs: "camping" för camping.php, '' för index.php.
function cms_page(): string {
  $p = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
  return $p === 'index' ? '' : $p;
}

function cms_lang(): string {
  static $lang = null;
  if ($lang !== null) return $lang;
  $main = cms_main_lang();
  $want = is_string($_GET['lang'] ?? null) ? $_GET['lang'] : '';
  if ($want === '' || $want === $main) return $lang = $main;
  if (!in_array($want, array_column(cms_languages_all(), 'code'), true) || isset($_GET['edit'])) {
    $q = $_GET;
    unset($q['lang']);
    header('Location: ' . cms_url(cms_page(), $main) . ($q ? '?' . http_build_query($q) : ''), true, 302);
    exit;
  }
  return $lang = $want;
}

function cms_url(?string $page = null, ?string $lang = null): string {
  $page ??= cms_page();
  $lang ??= cms_lang();
  return ($lang === cms_main_lang() ? '' : '/' . $lang) . '/' . $page;
}

// Innehållet på sidans språk. Saknas språkfilen (t.ex. precis efter att språket dolts) används huvudspråket.
function cms_site(): array {
  static $s = null;
  if ($s !== null) return $s;
  $lang = cms_lang();
  $f = $lang === cms_main_lang() ? SITE_JSON : ROOT . "/data/site.$lang.json";
  if (!is_file($f)) $f = SITE_JSON;
  return $s = json_decode((string)file_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
}

function cms_languages(): array {
  return array_map(fn($l) => $l + ['url' => cms_url(null, $l['code']), 'current' => $l['code'] === cms_lang()], cms_languages_all());
}

// Länkar till sidan på alla språk som visas, och x-default = huvudspråket. Tomt om det bara finns ett språk.
function cms_hreflang(): string {
  $all = cms_languages_all();
  if (count($all) < 2) return '';
  $base = rtrim(config()['site_url'], '/');
  $out = '';
  foreach ($all as $l) $out .= '<link rel="alternate" hreflang="' . h($l['code']) . '" href="' . h($base . cms_url(null, $l['code'])) . "\">\n";
  return $out . '<link rel="alternate" hreflang="x-default" href="' . h($base . cms_url(null, cms_main_lang())) . "\">\n";
}
