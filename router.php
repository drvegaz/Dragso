<?php
// Bara för utveckling, gör samma sak som .htaccess:  php -S localhost:8766 router.php
// (PHP:s inbyggda server läser inte .htaccess.)
$uri = urldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/(includes|cms/(site|storage))/|^/data/site(\.[a-z]{2})?\.json$|^/router\.php$#', $uri)) { http_response_code(403); exit('Förbjudet.'); }
if ($uri !== '/' && is_file(__DIR__ . $uri)) return false;
// Mappar med index.php (t.ex. /cms/) visas som vanligt; utan snedstreck först till /cms/, så att relativa länkar fungerar.
if ($uri !== '/' && is_file(__DIR__ . rtrim($uri, '/') . '/index.php')) {
  if (!str_ends_with($uri, '/')) { header('Location: ' . $uri . '/', true, 301); exit; }
  return false;
}
if (preg_match('#^/([a-z]{2})(?:/([a-z0-9-]*))?/?$#', $uri, $m)) { $_GET['lang'] = $m[1]; $page = ($m[2] ?? '') ?: 'index'; }
elseif (preg_match('#^/([a-z0-9-]*)/?$#', $uri, $m)) $page = $m[1] ?: 'index';
else $page = '';
if ($page === '' || !is_file(__DIR__ . "/$page.php")) { http_response_code(404); exit('Sidan finns inte.'); }
$_SERVER['SCRIPT_NAME'] = "/$page.php";
chdir(__DIR__);
require __DIR__ . "/$page.php";
