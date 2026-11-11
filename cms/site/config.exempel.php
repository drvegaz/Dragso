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
  'pages' => ['index.php' => 'Startsida', 'boende.php' => 'Boende', 'orlogsbyn.php' => 'Örlogsbyn', 'camping.php' => 'Camping'],
  // Vilken del av site.json som hör till vilken sida (grupperna i översättningen).
  'page_content' => ['index.php' => 'pages.start', 'boende.php' => 'pages.boende', 'orlogsbyn.php' => 'pages.orlogsbyn', 'camping.php' => 'pages.camping'],

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
