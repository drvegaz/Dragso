#!/bin/sh
# Bygger public/ för Vercel:
#   /            den statiska exporten av sajten (export/, görs med: php tools/export.php)
#   /forslag/    designförslaget (design/index.html, i artifact-format utan <head>, så det läggs till här)
set -e
rm -rf public
mkdir -p public/forslag
cp -r export/. public/
cp -r design/img public/forslag/img
{
  printf '%s\n' '<!doctype html>' '<html lang="sv">' '<head>' \
    '<meta charset="utf-8">' \
    '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' \
    '<meta name="robots" content="noindex, nofollow">' \
    '<style>:root{color-scheme:light}body{margin:0}[hidden]{display:none!important}</style>' \
    '</head>' '<body>'
  cat design/index.html
  printf '%s\n' '</body>' '</html>'
} > public/forslag/index.html
# /cms/: CMS:et kräver PHP och finns inte här – en kort förklaring i stället för 404.
mkdir -p public/cms
cat > public/cms/index.html <<'HTML'
<!doctype html>
<html lang="sv"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>Redigering – Dragsö</title>
<link rel="stylesheet" href="/assets/fonts/fonts.css"><link rel="stylesheet" href="/assets/css/site.css"></head>
<body><main class="wrap page-head" style="max-width:640px;padding-block:80px">
<h1>Redigeringen finns inte här</h1>
<p class="lead">Det här är en förhandsvisning av den nya webbplatsen. Sidorna visas som de kommer att se ut, men CMS:et (inloggning, redigering, översättning och formulär) behöver en server med PHP och fungerar först när webbplatsen ligger på sitt riktiga webbhotell.</p>
<p><a class="btn btn-boka" href="/">Till startsidan</a></p>
</main></body></html>
HTML
echo "public/ klar"
