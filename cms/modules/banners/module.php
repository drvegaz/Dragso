<?php
// Modulen "banners": meddelanden högst upp på alla sidor. Ligger i site.json under "notices" som en lista
// med { id, text, linkLabel, linkHref, enabled, start, end, color }. Webbplatsen ritar dem själv från window.SITE.notices.
// color = en nyckel i config 'banner_colors' (finns bara om webbplatsen har färger att välja mellan).

const NOTICES_MAX = 10;
const NOTICE_KEYS = ['id', 'text', 'linkLabel', 'linkHref', 'enabled', 'start', 'end'];
const NOTICE_OPTIONAL = ['color'];

// Banners är det enda redaktören får lägga till och ta bort här, så de valideras fritt men strikt.
function validate_notices($list): void {
  if (!is_array($list) || !array_is_list($list)) throw new InvalidArgumentException('Ogiltig lista med banners');
  if (count($list) > NOTICES_MAX) throw new InvalidArgumentException('Max ' . NOTICES_MAX . ' banners');
  $ids = [];
  foreach ($list as $i => $n) {
    $where = 'banner ' . ($i + 1);
    if (!is_array($n) || array_is_list($n) || array_diff(array_keys($n), NOTICE_KEYS, NOTICE_OPTIONAL) || array_diff(NOTICE_KEYS, array_keys($n)))
      throw new InvalidArgumentException("Felaktiga fält i $where");
    if (array_key_exists('color', $n) && !(is_string($n['color']) && ($n['color'] === '' || isset(config()['banner_colors'][$n['color']]))))
      throw new InvalidArgumentException("Okänd färg i $where");
    if (!is_string($n['id']) || !preg_match('/^[a-z0-9]{1,20}$/', $n['id']) || isset($ids[$n['id']]))
      throw new InvalidArgumentException("Ogiltigt id i $where");
    $ids[$n['id']] = true;
    if (!is_bool($n['enabled'])) throw new InvalidArgumentException("Ogiltig aktiv-status i $where");
    foreach (['text' => 500, 'linkLabel' => 80, 'linkHref' => 500, 'start' => 40, 'end' => 40] as $k => $max) {
      if (!is_string($n[$k]) || mb_strlen($n[$k]) > $max || preg_match('/[\x00-\x1F\x7F]/', $n[$k]))
        throw new InvalidArgumentException("Ogiltigt värde ($k) i $where");
    }
    if (trim($n['text']) === '') throw new InvalidArgumentException("Tom text i $where");
    if ($n['linkHref'] !== '' && !valid_href($n['linkHref']))
      throw new InvalidArgumentException("Ogiltig länk i $where (använd en sida på webbplatsen, t.ex. "
        . (config()['pages'] ? array_key_first(config()['pages']) : '#/priser') . ', eller https://…)');
    $t = [];
    foreach (['start', 'end'] as $k) {
      if ($n[$k] === '') continue;
      $d = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $n[$k]) ?: DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339, $n[$k]);
      if (!$d) throw new InvalidArgumentException("Ogiltig tid ($k) i $where");
      $t[$k] = $d;
    }
    if (isset($t['start'], $t['end']) && $t['end'] <= $t['start'])
      throw new InvalidArgumentException("Avpubliceringen måste vara efter publiceringen i $where");
  }
}

return [
  'title' => 'Banners',
  'page' => __DIR__ . '/page.php',
  'lists' => ['notices' => 'validate_notices'],
  // Fält som översätts (modulen sprak).
  'translate' => ['notices.#.text', 'notices.#.linkLabel'],
  // base = kontrollsumma av listan när sidan öppnades, så att två personer inte skriver över varandras
  // banners (andra ändringar på webbplatsen påverkas inte).
  'api' => function (string $action): void {
    if ($action === 'notices') {
      $notices = load_site()['site']['notices'] ?? [];
      json_out(['notices' => $notices, 'base' => sha1(json_encode($notices))]);
    }
    if ($action === 'notices_save') {
      require_write();
      $in = json_in();
      with_site_lock(function () use ($in) {
        $site = load_site()['site'];
        if (($in['base'] ?? '') !== sha1(json_encode($site['notices'] ?? [])))
          json_out(['error' => 'Någon annan har ändrat banners sedan du öppnade sidan. Ladda om och gör om dina ändringar.'], 409);
        validate_notices($in['notices'] ?? null);
        backup_current(current_user());
        $site['notices'] = $in['notices'];
        write_site($site);
      });
      json_out(['notices' => $in['notices'], 'base' => sha1(json_encode($in['notices']))]);
    }
  },
];
