<?php
// Inställningar för Dragsös CMS – EXEMPEL utan lösenord. Den riktiga filen är cms/site/config.php,
// som aldrig läggs i git (användare med lösenordshashar, SMTP). Kopiera hit ändringar som inte är hemliga.
return [
  'site_name' => 'Dragsö Camping & Stugby',
  // Lokalt: http://localhost:8766 (php -S localhost:8766 router.php). På servern: https://www.dragso.se
  'site_url' => 'https://www.dragso.se',
  'logo' => 'assets/img/logo.png',
  'accent' => '#ad3328',
  'accent_dark' => '#8f2a21',
  'session_name' => 'cms_dragso',
  'contact_person' => 'Mattias',
  'users' => [
    // 'namn' => ['hash' => '$2y$12$…', 'email' => 'namn@example.se', 'must_change' => true],
  ],
  'mail_from' => 'noreply@dragso.se',
  'smtp' => ['host' => '', 'port' => 587, 'user' => '', 'pass' => ''],

  'modules' => ['banners', 'oppettider', 'sprak'],
  // Sidfilerna (sidväljaren i redigeringsläget och grupperna i översättningen).
  'pages' => [
    'index.php' => 'Startsida',
    'boende.php' => 'Boende',
    'orlogsbyn.php' => 'Örlogsbyn',
    'stugsviter.php' => 'Stugsviter',
    'varldsarvsbyn.php' => 'Världsarvsbyn',
    'fiskebyn.php' => 'Fiskebyn',
    'skargardsbyn-4.php' => 'Skärgårdsbyn, fyra bäddar',
    'skargardsbyn-3.php' => 'Skärgårdsbyn, tre bäddar',
    'strandstugan.php' => 'Strandstugan',
    'dragsostugan.php' => 'Dragsöstugan',
    'husbaten.php' => 'Husbåten',
    'rum.php' => 'Vandrarhemsrum',
    'camping.php' => 'Camping',
    'stallplatser.php' => 'Ställplatser',
    'aktiviteter.php' => 'Aktiviteter',
    'hitta-hit.php' => 'Hitta hit',
    'kontakt.php' => 'Kontakt',
    'jul-nyar.php' => 'Jul och nyår',
    'integritet.php' => 'Integritet och cookies',
  ],
  // Vilken del av site.json som hör till vilken sida (grupperna i översättningen).
  'page_content' => [
    'index.php' => 'pages.start',
    'boende.php' => 'pages.boende',
    'orlogsbyn.php' => 'pages.orlogsbyn',
    'stugsviter.php' => 'pages.stugsviter',
    'varldsarvsbyn.php' => 'pages.varldsarvsbyn',
    'fiskebyn.php' => 'pages.fiskebyn',
    'skargardsbyn-4.php' => 'pages.skargardsbyn-4',
    'skargardsbyn-3.php' => 'pages.skargardsbyn-3',
    'strandstugan.php' => 'pages.strandstugan',
    'dragsostugan.php' => 'pages.dragsostugan',
    'husbaten.php' => 'pages.husbaten',
    'rum.php' => 'pages.rum',
    'camping.php' => 'pages.camping',
    'stallplatser.php' => 'pages.stallplatser',
    'aktiviteter.php' => 'pages.aktiviteter',
    'hitta-hit.php' => 'pages.hitta-hit',
    'kontakt.php' => 'pages.kontakt',
    'jul-nyar.php' => 'pages.jul-nyar',
    'integritet.php' => 'pages.integritet',
  ],

  // Standardlistan i cms/lib.php plus imageAlt (bildbeskrivningar).
  'editable_keys' => ['title', 'eyebrow', 'lead', 'text', 'label', 'intro', 'body', 'list', 'sub', 'note',
    'q', 'a', 'quote', 'name', 'summary', 'role', 'phone', 'email', 'value', 'price', 'rows', 'tag', 'badge', 'address', 'imageAlt'],
  'image_keys' => ['image'],
  'image_dirs' => ['assets/img', 'uploads'],
  // Knappar och korta texter som syns på många sidor.
  'open_roots' => ['ui'],
  'link_lists' => ['footer.links'],

  // Språk: svenska är huvudspråket. "value" (t.ex. "2,8 km") översätts, så att decimaltecknet blir rätt.
  'language' => 'sv',
  'translate_skip_keys' => ['phone', 'email', 'price', 'address'],
];
