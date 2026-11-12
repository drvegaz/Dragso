<?php
// /sitemap.xml (via .htaccess): alla sidor i 'pages' (cms/site/config.php) på alla språk som visas,
// med hreflang-länkar mellan språken. Uppdateras av sig själv när ett språk visas eller döljs.
declare(strict_types=1);
require __DIR__ . '/cms/modules/sprak/lang.php';
header('Content-Type: application/xml; charset=utf-8');
$base = rtrim(config()['site_url'], '/');
$langs = cms_languages_all();
$pages = array_map(fn($f) => $f === 'index.php' ? '' : basename($f, '.php'), array_keys(config()['pages']));
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";
foreach ($pages as $page) {
  foreach ($langs as $l) {
    echo '  <url><loc>' . h($base . cms_url($page, $l['code'])) . "</loc>\n";
    if (count($langs) > 1) foreach ($langs as $a)
      echo '    <xhtml:link rel="alternate" hreflang="' . h($a['code']) . '" href="' . h($base . cms_url($page, $a['code'])) . "\"/>\n";
    echo "  </url>\n";
  }
}
echo "</urlset>\n";
