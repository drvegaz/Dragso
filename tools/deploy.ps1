# Laddar upp Dragsö-sajten till one.com med WinSCP. (Inte provkörd än – kontrollera förhandsvisningen noga första gången.)
#
#   powershell -File tools\deploy.ps1                      visar vad som skulle laddas upp (ändrar ingenting)
#   powershell -File tools\deploy.ps1 -Skarpt              laddar upp på riktigt
#   powershell -File tools\deploy.ps1 -ForstaGangen        första uppladdningen: också startinnehållet och översättningarna
#
# Kräver WinSCP och en sparad webbplats i WinSCP som heter "dragso" (med lösenord), och sökvägen till webbroten
# på servern i $remote nedan (finns i one.com:s kontrollpanel, t.ex. /customers/…/webroots/www).
# Bara nya och ändrade filer laddas upp, och ingenting tas bort på servern.
#
# ALDRIG uppladdat – det levande innehållet och inställningarna finns bara på servern:
#   cms/storage/          användare, historik, översättningar, ordlista, omgångar
#   cms/site/config.php   användare, SMTP-lösenord (läggs dit för hand, utgå från config.exempel.php)
#   data/                 innehållet och språkfilerna som redaktören publicerar
#   uploads/              bilder som laddats upp i CMS:et
# Med -ForstaGangen laddas data/site.json och cms/storage/i18n/ (översättningar och ordlista) upp en gång,
# så att sajten startar med det innehåll och den engelska som finns lokalt.
# Plus sådant som inte hör hemma på servern: .git, tools/, design/, export/, public/, router.php, Vercel-filer, *.md.
param([switch]$Skarpt, [switch]$ForstaGangen)
$ErrorActionPreference = 'Stop'

$remote = ''   # t.ex. '/customers/x/y/z/abc123/webroots/www'
if (-not $remote) { throw 'Fyll i $remote i tools\deploy.ps1 (webroten på servern) först.' }

$winscp = @("$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com", "$env:ProgramFiles\WinSCP\WinSCP.com", "${env:ProgramFiles(x86)}\WinSCP\WinSCP.com") |
  Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $winscp) { throw 'Hittar inte WinSCP. Installera det (winget install WinSCP.WinSCP).' }

$local = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

# Filmasker i WinSCP: "|" inleder det som utesluts, "/" i slutet = mapp. Masker med sökväg börjar med */.
$exclude = @(
  '.git/', '.vscode/', '.claude/', 'tools/', 'design/', 'export/', 'public/', 'uploads/', 'storage/',
  '*/cms/site/config.php', '*/data/site.js', '*/data/site.*.js', '*/data/site.*.json', '*/data/languages.*',
  'router.php', 'build.sh', 'vercel.json', 'analys.html',
  '*.md', '.gitignore', '.gitattributes', '*.bak', '*.tmp', 'Thumbs.db', 'desktop.ini'
)
if (-not $ForstaGangen) { $exclude += '*/data/site.json' }
$mask = $exclude -join '; '

$mode = if ($Skarpt -or $ForstaGangen) { '' } else { '-preview' }
$commands = @('option batch abort', 'option confirm off', 'open dragso',
  "synchronize remote $mode -criteria=either -filemask=`"| $mask`" `"$local`" `"$remote`"")
if ($ForstaGangen) {
  # Översättningar, ordlista och språkinställningar (men inga användare eller historik).
  $commands += "synchronize remote -criteria=either `"$local\cms\storage\i18n`" `"$remote/cms/storage/i18n`""
}
$commands += 'exit'

Write-Host ($(if ($ForstaGangen) { 'Första uppladdningen till dragso.se, med startinnehåll och översättningar …' }
  elseif ($Skarpt) { 'Laddar upp till dragso.se …' } else { 'Förhandsvisning – inget laddas upp. Kör med -Skarpt för att ladda upp.' })) -ForegroundColor Cyan
if ($ForstaGangen -and (Read-Host 'Det här skriver över data/site.json på servern. Skriv JA för att fortsätta') -ne 'JA') { return }

# Kommandona går via en skriptfil, så att citattecknen i filmasken kommer fram oförändrade till WinSCP.
$script = Join-Path $env:TEMP 'dragso-deploy.winscp'
[IO.File]::WriteAllLines($script, $commands, [Text.UTF8Encoding]::new($false))
try {
  & $winscp /script="$script" | Where-Object { $_ -notmatch '^(Söker efter|Ansluter till|Autentiserar|Använder användarnamn|Autentisering med|Autentiserad|Startar sessionen|Sessionen startad|Aktiv session|I skript ska|open sftp|batch |confirm )' }
  if ($LASTEXITCODE -ne 0) { throw "WinSCP avslutades med fel ($LASTEXITCODE)." }
} finally {
  Remove-Item $script -ErrorAction SilentlyContinue
}
Write-Host 'Kom ihåg: efter första uppladdningen, publicera en gång i CMS:et (eller öppna Språk) så skapas data/site.js och språkfilerna.' -ForegroundColor Yellow
