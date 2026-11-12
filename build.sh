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
echo "public/ klar"
