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

## Om vi bygger

- Tänkt grund: **cms-core** (`C:\Web\cms-core`, github.com/drvegaz/cms-core), samma som PIFAST (`C:\Web\pifast-static`,
  PHP-sidor som ritas på servern) och Trossö (`C:\Web\Trosso\Trossö Trafikskola ombyggnad - CMS`, statisk sajt med
  `window.SITE`). Moduler som passar: formular (bokningsförfrågan, felanmälan), banners, oppettider, anvandare.
  cms-core saknar stöd för flera språk – avgörs innan start.
- Synka kärnan med `php C:\Web\cms-core\sync.php "<webbplatsens mapp>"`, ändra aldrig i `cms/` direkt (utom `cms/site/`).
- Varje cms-core-uppdatering på en sajt får en rad under "Nytt i CMS:et" i sajtens `cms/site/hjalp.html`.
- Uppladdning görs med WinSCP och ett `tools/deploy.ps1` som i pifast/Trossö: förhandsvisning först, aldrig
  `cms/storage/`, `cms/site/config.php`, levande data eller uppladdade bilder. Fråga alltid innan något laddas upp.
  dragso.se ligger också hos one.com, men Mattias har ingen WinSCP-inloggning sparad för den än.
- Lösenord, SMTP och liknande hamnar aldrig i git eller i chatten.
