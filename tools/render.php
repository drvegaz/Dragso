<?php
// Renderar en sida till stdout, som om den hade hämtats via webben. Används av export.php.
//   php tools/render.php <sida> [språk]      t.ex. php tools/render.php camping en
$page = $argv[1] ?? 'index';
$lang = $argv[2] ?? '';
if (!preg_match('/^[a-z0-9-]+$/', $page) || ($lang !== '' && !preg_match('/^[a-z]{2}$/', $lang))) { fwrite(STDERR, "Ogiltig sida eller språk\n"); exit(1); }
$root = dirname(__DIR__);
chdir($root);
$_SERVER['SCRIPT_NAME'] = "/$page.php";
$_SERVER['REQUEST_URI'] = ($lang ? "/$lang" : '') . '/' . ($page === 'index' ? '' : $page);
if ($lang !== '') $_GET['lang'] = $lang;
require "$root/$page.php";
