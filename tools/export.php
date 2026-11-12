<?php
// Statisk export av sajten till export/, för att visa den där PHP inte finns (Vercel).
//   php tools/export.php
// Varje sida renderas på huvudspråket och på alla språk som visas (data/languages.json):
//   export/index.html, export/camping.html, export/en/index.html, export/en/camping.html …
// Vercel visar /camping och /en/camping med cleanUrls. Redigering fungerar inte i exporten.
declare(strict_types=1);
$root = dirname(__DIR__);
$out = "$root/export";
$siteUrl = 'https://www.dragso.se';

require "$root/cms/lib.php";
// Sidorna från 'pages' i cms/site/config.php (samma lista som sidväljaren i redigeringsläget).
$pages = array_map(fn($f) => basename($f, '.php'), array_keys(config()['pages']));
$local = rtrim(config()['site_url'], '/');
$main = config()['language'];
$langs = array_column(json_decode((string)@file_get_contents("$root/data/languages.json"), true) ?: [['code' => $main]], 'code');

$rm = function (string $dir) use (&$rm): void {
  if (!is_dir($dir)) return;
  foreach (array_diff(scandir($dir), ['.', '..']) as $f) is_dir("$dir/$f") ? $rm("$dir/$f") : unlink("$dir/$f");
  rmdir($dir);
};
$copy = function (string $from, string $to) use (&$copy): void {
  if (!is_dir($to)) mkdir($to, 0775, true);
  foreach (array_diff(scandir($from), ['.', '..']) as $f) is_dir("$from/$f") ? $copy("$from/$f", "$to/$f") : copy("$from/$f", "$to/$f");
};
$rm($out);
mkdir($out, 0775, true);
$copy("$root/assets", "$out/assets");

$php = escapeshellarg(PHP_BINARY);
$n = 0;
foreach ($langs as $lang) {
  $dir = $lang === $main ? $out : "$out/$lang";
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  foreach ($pages as $page) {
    $html = shell_exec("$php " . escapeshellarg("$root/tools/render.php") . ' ' . escapeshellarg($page) . ($lang === $main ? '' : ' ' . escapeshellarg($lang)));
    if (!is_string($html) || !str_contains($html, '</html>')) { fwrite(STDERR, "Fel vid $lang/$page\n"); exit(1); }
    // PHP-fel: "<b>Warning</b>:" i webbläsarformat, "Warning:" eller "PHP Warning:" från kommandoraden.
    if (preg_match('#(<b>|^|\n)(PHP )?(Warning|Notice|Deprecated|Fatal error)(</b>)?:#', $html)) { fwrite(STDERR, "PHP-fel i $lang/$page\n"); exit(1); }
    // Redigeringsläget finns inte i exporten, och adresserna ska peka på den riktiga domänen.
    $html = preg_replace('#^<script>[^\n]*cms/editor\.js[^\n]*\n#m', '', $html);
    $html = str_replace($local, $siteUrl, $html);
    file_put_contents("$dir/$page.html", $html);
    $n++;
  }
}
echo "Exporterade $n sidor (" . implode(', ', $langs) . ") till export/.\n";
