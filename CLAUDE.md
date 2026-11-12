# Dragsö Camping – ny webbplats (förstudie)

Mattias funderar på att bygga om webbplatsen för Dragsö Camping & Stugby i Karlskrona (en kompis äger campingen).
Inget är bestämt eller byggt än. `analys.html` är analysen som skickas till ägaren (skriven för en icke-teknisk läsare).
Analysen gjordes 2026-11-11 utifrån det som syns publikt.

## Nuläge

- **Webbplats:** https://www.dragso.se – WordPress hos one.com (PHP 8.2, `usercontent.one`), tema "Ronneby" (DFD),
  WPBakery, Slider Revolution, WP Rocket, Complianz (cookies), SureForms, Google Site Kit. Cirka 88 sidor
  (`/wp-json/wp/v2/pages` är öppet). Startsidan: 33 css + 30 js, men snabb (TTFB < 0,1 s) tack vare WP Rocket.
- **Engelska:** separat domän dragsocamping.com. Systersajter som länkas: havsviken.se, ronnebyhavscamping.se, halloweek.se.
- **Bokning:** https://boka.dragso.se – **TurBo2an från paxess** ("Turbo online" i titeln), egen server
  (IIS/ASP.NET), frontend jQuery + Vue + Bootstrap Material + Google Maps. Visas på dragso.se/boka-online/ som iframe;
  övriga bokningsknappar länkar direkt dit.

## Bokningens API (odokumenterat, publikt – används av bokningssidan själv)

- `GET /api/pool/loadPools` → `{status, pools:[…]}`: 17 kategorier med `poolId, title, subtitle, image, description,
  price, flexPrice, flexPriceInfo, discountKr, discountPercent, newPrice, beds, extraBeds, campingTomtBredd,
  campingTomtLangd, poolCategory, bookingPossible, bookByMap, objectNumberMandatory, nonBookableReason1/2,
  caravanEnabled, extraPools` m.fl.
- `/api/pool/poolAvailable`, `/api/pool/loadPoolCalendar` – tillgänglighet/kalender.
- `/api/map/getMap`, `/api/map/getAvailablePools` – områdeskarta och lediga platser (platsval på karta).
- Sökformuläret: `poolType` (0 camping, 1 stugor, 3 rum, 4 övrigt), `arrival`, `departure`, `adults`, `childs`,
  `discountCode`. Sidan läser URL-parametrar (UrlSearchParams-polyfill) – **otestat** om en djuplänk med förvald
  kategori/datum fungerar.
- En testkategori "Kanot test" (poolId KONF, 1 200 kr) syns publikt – nämnt för ägaren.

**Använd inte API:t i en ny sajt utan att ägaren har fått OK från paxess.** Gör bara läsande anrop, få och
försiktigt; det är kundens leverantörs system.

## Rekommendation (i analysen)

1. Ny webbplats + bokningen som i dag (länk/iframe) – rekommenderat.
2. Som 1 + aktuella priser per stug-/platssida och "Boka"-knapp med rätt kategori – kräver paxess OK.
3. Byta bokningssystem – ägarens affärsbeslut, inte del av webbprojektet.

Öppna frågor till ägaren: avtalet med paxess (dokumentation/widget?), engelska sajten och systersajterna,
vilka av de 88 sidorna som behövs, vem som uppdaterar i dag.

## Designförslag

Beslut 2026-11-11: nivå 1 (ny sajt, bokningen orörd). `design/index.html` är förslaget: en fil med fyra vyer via hash
(#start, #boende, #orlogsbyn, #camping), bilder i `design/img/` hämtade från dragso.se. Publicerad som artifact
https://claude.ai/artifact/7HZ6WSghh4ZHsHBLKB1ZBS (privat tills Mattias delar den). Alla Boka-knappar → boka.dragso.se.

Git: github.com/drvegaz/Dragso (privat, push via HTTPS – SSH-nyckel saknas). **Vercel** (noindex) visar en statisk
export av den riktiga sajten i roten (`/`, `/en/…`) och designförslaget under `/forslag/`. Vercel kör inte PHP, så
exporten görs lokalt: `php tools/export.php` → `export/` (i git), sedan bygger `build.sh` `public/`. Kör exporten
efter ändringar i sidor, innehåll eller översättningar, innan push. Redigering fungerar inte på Vercel.

## Sajten (påbörjad 2026-11-12)

Beslut: PHP-sidor som ritas på servern (som PIFAST), för egna adresser per sida och språk (SEO). Byggs innan ägaren
svarat på designen. 19 sidor (adresser `/`, `/boende`, …, `/en/camping`): start, boende, camping, ställplatser,
aktiviteter, hitta hit, kontakt, jul och nyår, integritet, och tio stugsidor (Örlogsbyn, Stugsviter, Världsarvsbyn,
Fiskebyn, Skärgårdsbyn 4/3, Strandstugan, Dragsöstugan, Husbåten, Vandrarhemsrum). **Sidorna står på ett ställe:**
`pages` och `page_content` i `cms/site/config.php` (och `config.exempel.php`); `dr_url()`, sitemap och exporten läser
därifrån. Ny sida: lägg till filen + `pages.<namn>` i site.json + raderna i båda config-filerna.
- Stugsidor: `includes/cabin.php` är mallen; sidfilen är tre rader (`$cabin = 'fiskebyn'; require …`). Husbåten har
  `bookByPhone` (bokas bara via telefon/e-post).

- `data/site.json`: allt innehåll, också knappar (`ui`, med platshållare `{pris}`, `{telefon}`). Sökvägarna används som
  `data-cms`. `includes/site.php`: `dr_text()`, `dr_img()` (bildbeskrivning i `<bild>Alt`), `dr_price()`, `dr_url()`.
- `includes/partials/header.php` / `footer.php`, `assets/css/site.css` (från designförslaget), `assets/js/site.js`.
- Språk: cms-core-modulen `sprak` (`lang.php`), `.htaccess` gör `/en/…`. Engelska är översatt (754 texter, via
  AI-flödet) men bara lokalt – `data/site.en.*` och `cms/storage/` ligger inte i git. Första uppladdningen tar med
  dem (`deploy.ps1 -ForstaGangen`).
- Typsnitten ligger i `assets/fonts/` (latin + latin-ext), inte Google Fonts – integritetssidan säger att inget hämtas
  från andra företag. Lägg inte tillbaka externa typsnitt, statistik eller inbäddningar utan att ändra den sidan.
- `.htaccess`: www + HTTPS, 16 regler som skickar dagens 88 WordPress-adresser vidare (301), `/sitemap.xml` →
  `sitemap.php`, språk och sidor utan `.php`. `router.php` gör inte omdirigeringarna (bara Apache).
- `tools/deploy.ps1` (WinSCP, sparad webbplats "dragso", fyll i `$remote` först) – inte provkörd.
- cms-core 1.8.0 synkat in i `cms/` (i git, som PIFAST). `cms/site/config.php` är lokal och utanför git;
  `cms/site/config.exempel.php` har samma inställningar utan lösenord. `cms/site/ordlista-start.json` = startordlistan.
- **Köra lokalt:** `php -S localhost:8766 router.php` i mappen, CMS på `http://localhost:8766/cms/` (test / testlosen123,
  bara i den lokala config.php). `router.php` gör samma sak som `.htaccess` för PHP:s inbyggda server.
- Kvar innan publicering: ägarens OK på design och texter (särskilt integritetssidan och priserna), årets
  julerbjudande (2024 års kod JUL2024 togs inte med), WinSCP-inloggning och `$remote`, riktig config på servern
  (användare, SMTP), test av omdirigeringarna på Apache, dragsocamping.com (väntar på ägaren, troligen → /en/).

## Om vi bygger

- Grund: **cms-core** (`C:\Web\cms-core`, github.com/drvegaz/cms-core), samma som PIFAST (`C:\Web\pifast-static`,
  PHP-sidor som ritas på servern) och Trossö (`C:\Web\Trosso\Trossö Trafikskola ombyggnad - CMS`, statisk sajt med
  `window.SITE`). Moduler: banners, oppettider, sprak; formular (bokningsförfrågan, felanmälan) och anvandare passar senare.
  Flera språk: modulen `sprak`, plan i `C:\Web\cms-core\PLAN-sprak.md`, AI-testet i `C:\Web\cms-core\test\sprak\`.
  Dragsö: engelska först på /en/; "Camping & Stugby" översätts (blev "Camping & Cabins").
- Synka kärnan med `php C:\Web\cms-core\sync.php "<webbplatsens mapp>"`, ändra aldrig i `cms/` direkt (utom `cms/site/`).
- Varje cms-core-uppdatering på en sajt får en rad under "Nytt i CMS:et" i sajtens `cms/site/hjalp.html`.
- Uppladdning görs med WinSCP och ett `tools/deploy.ps1` som i pifast/Trossö: förhandsvisning först, aldrig
  `cms/storage/`, `cms/site/config.php`, levande data eller uppladdade bilder. Fråga alltid innan något laddas upp.
  dragso.se ligger också hos one.com, men Mattias har ingen WinSCP-inloggning sparad för den än.
- Lösenord, SMTP och liknande hamnar aldrig i git eller i chatten.
