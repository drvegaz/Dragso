#!/bin/sh
# Bygger public/ för Vercel av design/index.html.
# Sidan saknar <!doctype>/<head> (artifact-format), så de läggs till här.
set -e
rm -rf public
mkdir -p public
cp -r design/img public/img
{
  printf '%s\n' '<!doctype html>' '<html lang="sv">' '<head>' \
    '<meta charset="utf-8">' \
    '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' \
    '<meta name="robots" content="noindex, nofollow">' \
    '<style>:root{color-scheme:light}body{margin:0}[hidden]{display:none!important}</style>' \
    '</head>' '<body>'
  cat design/index.html
  printf '%s\n' '</body>' '</html>'
} > public/index.html
echo "public/ klar"
