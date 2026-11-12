# Laddar upp Dragsö-sajten med WinSCP. (Inte provkörd än – läs förhandsvisningen noga första gången.)
#
#   powershell -File tools\deploy.ps1                         förhandsvisning mot Strato (ändrar ingenting)
#   powershell -File tools\deploy.ps1 -Skarpt                 laddar upp på riktigt
#   powershell -File tools\deploy.ps1 -ForstaGangen           första uppladdningen, se nedan
#   powershell -File tools\deploy.ps1 -Mal onecom …           samma sak mot one.com (dragso.se) i stället
#
# Mål (fyll i webroten för varje mål nedan; den finns i webbhotellets kontrollpanel):
#   strato   testservern hos Strato.  Sparad webbplats i WinSCP: "strato", mappen /kunder/dragso = dragso.arcticweb.se.
#   onecom   dragso.se hos one.com.    Sparad webbplats i WinSCP: "dragso".
# Lösenorden sparas bara i WinSCP, aldrig här eller i git.
#
# Bara nya och ändrade filer laddas upp, och ingenting tas bort på servern.
# ALDRIG uppladdat – det levande innehållet och inställningarna finns bara på servern:
#   cms/storage/          användare, historik, översättningar, ordlista, omgångar, formulärinskick
#   cms/site/config.php   användare och SMTP (se -ForstaGangen)
#   data/                 innehållet och språkfilerna som redaktören publicerar
#   uploads/              bilder som laddats upp i CMS:et
# Plus sådant som inte hör hemma på servern: .git, tools/, design/, export/, public/, router.php, Vercel-filer, *.md.
#
# -ForstaGangen laddar dessutom upp, en gång:
#   data/site.json                 startinnehållet
#   cms/storage/i18n/              översättningar, ordlista och språkinställningar (inga användare eller inskick)
#   cms/site/config.server.php     som cms/site/config.php på servern, om filen finns. Gör den från
#                                  config.exempel.php: rätt site_url och en användare med ett tillfälligt lösenord
#                                  och 'must_change' => true. Filen ligger utanför git.
param([switch]$Skarpt, [switch]$ForstaGangen, [ValidateSet('strato', 'onecom')][string]$Mal = 'strato')
$ErrorActionPreference = 'Stop'

$targets = @{
  strato = @{ Session = 'strato';        Remote = '/kunder/dragso'; Name = 'Strato (test, dragso.arcticweb.se)' }
  onecom = @{ Session = 'dragso';        Remote = ''; Name = 'dragso.se (one.com)' }   # t.ex. '/customers/…/webroots/www'
}
$t = $targets[$Mal]
if (-not $t.Remote) { throw "Fyll i Remote för '$Mal' i tools\deploy.ps1 (webroten på servern) först." }

$winscp = @("$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com", "$env:ProgramFiles\WinSCP\WinSCP.com", "${env:ProgramFiles(x86)}\WinSCP\WinSCP.com") |
  Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $winscp) { throw 'Hittar inte WinSCP. Installera det (winget install WinSCP.WinSCP).' }

$local = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$remote = $t.Remote.TrimEnd('/')
if (-not $remote) { $remote = '/' }

# Filmasker i WinSCP: "|" inleder det som utesluts, "/" i slutet = mapp. Masker med sökväg börjar med */.
$exclude = @(
  '.git/', '.vscode/', '.claude/', 'tools/', 'design/', 'export/', 'public/', 'uploads/', 'storage/',
  '*/cms/site/config.php', '*/cms/site/config.server.php', '*/data/site.js', '*/data/site.*.js', '*/data/site.*.json', '*/data/languages.*',
  'router.php', 'build.sh', 'vercel.json', 'analys.html',
  '*.md', '.gitignore', '.gitattributes', '*.bak', '*.tmp', 'Thumbs.db', 'desktop.ini'
)
if (-not $ForstaGangen) { $exclude += '*/data/site.json' }
$mask = $exclude -join '; '

$mode = if ($Skarpt -or $ForstaGangen) { '' } else { '-preview' }
$commands = @('option batch abort', 'option confirm off', "open $($t.Session)",
  "synchronize remote $mode -criteria=either -filemask=`"| $mask`" `"$local`" `"$remote`"")
$serverConfig = Join-Path $local 'cms\site\config.server.php'
if ($ForstaGangen) {
  $commands += "synchronize remote -criteria=either `"$local\cms\storage\i18n`" `"$($remote.TrimEnd('/'))/cms/storage/i18n`""
  if (Test-Path $serverConfig) { $commands += "put `"$serverConfig`" `"$($remote.TrimEnd('/'))/cms/site/config.php`"" }
}
$commands += 'exit'

Write-Host "Mål: $($t.Name), $remote" -ForegroundColor Cyan
Write-Host ($(if ($ForstaGangen) { 'Första uppladdningen, med startinnehåll, översättningar och server-config …' }
  elseif ($Skarpt) { 'Laddar upp …' } else { 'Förhandsvisning – inget laddas upp. Kör med -Skarpt för att ladda upp.' })) -ForegroundColor Cyan
if ($ForstaGangen) {
  if (-not (Test-Path $serverConfig)) { Write-Host 'OBS: cms/site/config.server.php saknas – då finns ingen config.php på servern och CMS:et startar inte.' -ForegroundColor Yellow }
  if ((Read-Host 'Det här skriver över data/site.json, översättningarna och config.php på servern. Skriv JA för att fortsätta') -ne 'JA') { return }
}

# Kommandona går via en skriptfil, så att citattecknen i filmasken kommer fram oförändrade till WinSCP.
$script = Join-Path $env:TEMP 'dragso-deploy.winscp'
[IO.File]::WriteAllLines($script, $commands, [Text.UTF8Encoding]::new($false))
try {
  & $winscp /script="$script" | Where-Object { $_ -notmatch '^(Söker efter|Ansluter till|Autentiserar|Använder användarnamn|Autentisering med|Autentiserad|Startar sessionen|Sessionen startad|Aktiv session|I skript ska|open sftp|open ftp|batch |confirm )' }
  if ($LASTEXITCODE -ne 0) { throw "WinSCP avslutades med fel ($LASTEXITCODE)." }
} finally {
  Remove-Item $script -ErrorAction SilentlyContinue
}
if ($ForstaGangen) { Write-Host 'Logga sedan in i CMS:et och publicera en gång, så skapas data/site.js och språkfilerna.' -ForegroundColor Yellow }
