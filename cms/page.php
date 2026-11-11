<?php
// Öppnar en moduls sida: cms/page.php?m=<namn>. Modulens filer nås aldrig direkt via webben,
// och sidan körs härifrån – så relativa adresser i den (api.php, ../index.html) utgår från cms/. Länka tillbaka med edit_url().
declare(strict_types=1);
require __DIR__ . '/lib.php';
start_session();
if (!current_user()) { header('Location: index.php'); exit; }
$module = modules()[(string)($_GET['m'] ?? '')] ?? null;
if (!$module || empty($module['page'])) { http_response_code(404); exit('Sidan finns inte.'); }
require $module['page'];
