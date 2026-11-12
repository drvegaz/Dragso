<?php
// Gemensamt för Dragsös sidor. Innehållet ligger i data/site.json (redigeras med cms-core, ?edit) och
// översättningarna i data/site.<kod>.json (modulen sprak). Varje sida börjar med:
//   require __DIR__ . '/includes/site.php';
// och använder sedan dr_text(), dr_img() osv. Allt som skrivs ut ur innehållet går genom h().
declare(strict_types=1);

require_once __DIR__ . '/../cms/modules/sprak/lang.php';

const DR_BOOKING_URL = 'https://boka.dragso.se/';
const DR_LIVE_URL = 'https://www.dragso.se';

// Bara den riktiga sajten ska hamna hos sökmotorerna. Lokalt och på testservrar (site_url i config.php är något
// annat än www.dragso.se) säger varje sida noindex, både som rubrik här och som <meta> i dr_head().
function dr_is_live(): bool {
  return rtrim(config()['site_url'], '/') === DR_LIVE_URL;
}
if (!dr_is_live() && PHP_SAPI !== 'cli' && !headers_sent()) header('X-Robots-Tag: noindex, nofollow');

// Sidorna står på ett ställe: 'pages' i cms/site/config.php (filnamn => namn). Sidans namn = filnamnet utan .php,
// och index.php är startsidan (''). Länkar till sidor som inte finns där får "#".
function dr_pages(): array {
  return array_map(fn($f) => $f === 'index.php' ? '' : basename($f, '.php'), array_keys(config()['pages']));
}

function dr_site(): array {
  return cms_site();
}

// Värdet på en sökväg i innehållet, t.ex. "pages.start.hero.title" eller "nav.0.label".
function dr_get(string $path) {
  $v = dr_site();
  foreach (explode('.', $path) as $k) {
    if (!is_array($v) || !array_key_exists(ctype_digit($k) ? (int)$k : $k, $v)) return null;
    $v = $v[ctype_digit($k) ? (int)$k : $k];
  }
  return $v;
}

// Texten, säker för HTML. Flerradiga texter får <br>.
function dr_t(string $path, bool $multi = false): string {
  $s = h((string)(dr_get($path) ?? ''));
  return $multi ? nl2br($s, false) : $s;
}

// data-cms-attribut, så att texten går att ändra i redigeringsläget.
function dr_attr(string $path, bool $multi = false): string {
  return ' data-cms="' . h($path) . '"' . ($multi ? ' data-cms-multiline' : '');
}

// Ett element med redigerbar text: dr_text('pages.start.hero.title', 'h1').
function dr_text(string $path, string $tag = 'span', string $attrs = '', bool $multi = false): string {
  return "<$tag$attrs" . dr_attr($path, $multi) . '>' . dr_t($path, $multi) . "</$tag>";
}

function dr_ui(string $key, string $tag = 'span', string $attrs = ''): string {
  return dr_text("ui.$key", $tag, $attrs);
}

// Bild med utbytbar källa och bildbeskrivning. $path = sökvägen till bilden; beskrivningen ligger i <sökväg>Alt.
function dr_img(string $path, string $attrs = '', bool $lazy = true): string {
  $src = (string)(dr_get($path) ?? '');
  $alt = (string)(dr_get($path . 'Alt') ?? '');
  return '<img src="/' . h(ltrim($src, '/')) . '" alt="' . h($alt) . '"' . ($lazy ? ' loading="lazy"' : '')
    . ' data-cms-img="' . h($path) . '" data-cms-alt="' . h($path . 'Alt') . '"' . $attrs . '>';
}

// "från 700 kr" på sidans språk (ui.priceFrom med {pris}). Tusental som språket skriver dem: 2 000, 2,000, 2.000.
// $path = priset i innehållet (t.ex. "pages.orlogsbyn.price"): då blir siffran redigerbar i redigeringsläget.
// (Under redigeringen visas siffran utan mellanrum, t.ex. 2000; efter publicering som vanligt.)
function dr_price(int $price, string $path = ''): string {
  $sep = ['en' => ',', 'de' => '.', 'da' => '.', 'nl' => '.', 'it' => '.', 'es' => '.'][cms_lang()] ?? "\u{00A0}";
  $num = number_format($price, 0, ',', $sep);
  $tpl = (string)dr_get('ui.priceFrom');
  if ($path === '') return h(str_replace('{pris}', $num, $tpl));
  [$before, $after] = array_pad(explode('{pris}', $tpl, 2), 2, '');
  return h($before) . '<span class="price-num"' . dr_attr($path) . '>' . h($num) . '</span>' . h($after);
}

// Adress till en sida på samma språk. Sidor som inte är byggda än får "#".
function dr_url(string $page): string {
  return in_array($page, dr_pages(), true) ? cms_url($page) : '#';
}

function dr_tel(string $phone): string {
  return 'tel:+46' . ltrim((string)preg_replace('/\D+/', '', $phone), '0');
}

// Ersätter {telefon} i en text med telefonnumret.
function dr_fill(string $escaped): string {
  return str_replace('{telefon}', '<a href="' . h(dr_tel((string)dr_get('contact.phone'))) . '">' . dr_t('contact.phone') . '</a>', $escaped);
}

// Rader [[rubrik, värde], …] som definitionslista, t.ex. priser och tider.
function dr_rows(string $path, string $class = 'terms'): string {
  $out = '';
  foreach ((array)dr_get($path) as $i => $r) $out .= '<div>' . dr_text("$path.$i.0", 'dt') . dr_text("$path.$i.1", 'dd') . '</div>';
  return "<dl class=\"$class\">$out</dl>";
}

// Lista med texter ["…", "…"].
function dr_list(string $path, string $class = 'checks'): string {
  $out = '';
  foreach ((array)dr_get($path) as $i => $x) $out .= dr_text("$path.$i", 'li');
  return "<ul class=\"$class\">$out</ul>";
}

// Banners (modulen banners) som är aktiva nu.
function dr_notices(): array {
  $now = time();
  $out = [];
  foreach ((array)dr_get('notices') as $i => $n) {
    if (!is_array($n) || empty($n['enabled']) || trim((string)($n['text'] ?? '')) === '') continue;
    $s = ($n['start'] ?? '') !== '' ? strtotime($n['start']) : false;
    $e = ($n['end'] ?? '') !== '' ? strtotime($n['end']) : false;
    if (($s !== false && $s > $now) || ($e !== false && $e <= $now)) continue;
    $out[$i] = $n;
  }
  return $out;
}

// <head> på alla sidor. $titlePath och $summaryPath pekar på sidans titel och beskrivning i innehållet.
function dr_head(string $titlePath, string $summaryPath): void {
  $title = (string)dr_get($titlePath);
  $name = (string)dr_get('contact.name');
  $full = str_contains($title, 'Dragsö') ? $title : "$title – $name";
  $canonical = rtrim(config()['site_url'], '/') . cms_url();
  ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($full) ?></title>
<?php if (!dr_is_live()): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="description" content="<?= h((string)dr_get($summaryPath)) ?>">
<link rel="canonical" href="<?= h($canonical) ?>">
<?= cms_hreflang() ?>
<meta property="og:title" content="<?= h($full) ?>">
<meta property="og:description" content="<?= h((string)dr_get($summaryPath)) ?>">
<meta property="og:image" content="<?= h(rtrim(config()['site_url'], '/') . '/assets/img/hero-on.jpg') ?>">
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/css/site.css">
<script>if (/[?&]edit\b/.test(location.search)) { for (const f of ['cms/server-sync.js', 'cms/editor.js']) { const s = document.createElement('script'); s.src = '/' + f; s.async = false; document.head.appendChild(s); } }</script>
<?php
}
