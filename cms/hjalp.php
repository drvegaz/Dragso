<?php
// Hjälpsidan för redaktörer. Varje webbplats har sin egen i cms/site/hjalp.html.
// {site_name}, {logo}, {accent}, {accent_dark} och {contact} ersätts med värden från config.php.
declare(strict_types=1);
require __DIR__ . '/lib.php';
$file = SITE_DIR . '/hjalp.html';
if (!is_file($file)) { http_response_code(404); exit('Hjälpsidan saknas (cms/site/hjalp.html).'); }
$c = config();
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
echo strtr((string)file_get_contents($file), [
  '{site_name}' => h($c['site_name']), '{logo}' => h($c['logo']), '{accent}' => h($c['accent']),
  '{accent_dark}' => h($c['accent_dark']), '{contact}' => h($c['contact_person'] ?: 'den som byggde webbplatsen'),
]);
