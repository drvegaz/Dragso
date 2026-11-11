<?php
// Modulen "oppettider": ordinarie och avvikande öppettider. Ligger i site.json:
//   hours           [{ title, rows: [[dag, tid], …] }, …]                  – ordinarie, en grupp per kolumn
//   hoursExceptions [{ id, title, days: [{ date, time }], enabled, start, end, banner, bannerText }, …]
// Webbplatsen ritar dem själv: avvikelser vars dagar ligger i innevarande vecka visas vid öppettiderna,
// och de med banner = true visas som banner så länge de är publicerade (start–end, tomt slut = efter sista dagen).

const HOURS_GROUPS_MAX = 4;
const HOURS_ROWS_MAX = 10;
const HOURS_EXC_MAX = 20;
const HOURS_DAYS_MAX = 21;
const HOURS_EXC_KEYS = ['id', 'title', 'days', 'enabled', 'start', 'end', 'banner', 'bannerText'];

function hours_text($v, int $max, string $where, bool $required = true): void {
  if (!is_string($v) || mb_strlen($v) > $max || preg_match('/[\x00-\x1F\x7F]/', $v))
    throw new InvalidArgumentException("Ogiltig text i $where (högst $max tecken)");
  if ($required && trim($v) === '') throw new InvalidArgumentException("Tom text i $where");
}

function validate_hours($list): void {
  if (!is_array($list) || !array_is_list($list) || !$list) throw new InvalidArgumentException('Ogiltiga öppettider');
  if (count($list) > HOURS_GROUPS_MAX) throw new InvalidArgumentException('Max ' . HOURS_GROUPS_MAX . ' grupper med öppettider');
  foreach ($list as $i => $g) {
    $where = 'öppettider ' . ($i + 1);
    if (!is_array($g) || count($g) !== 2 || !array_key_exists('title', $g) || !array_key_exists('rows', $g))
      throw new InvalidArgumentException("Felaktiga fält i $where");
    hours_text($g['title'], 80, "rubriken ($where)");
    if (!is_array($g['rows']) || !array_is_list($g['rows']) || !$g['rows'] || count($g['rows']) > HOURS_ROWS_MAX)
      throw new InvalidArgumentException("$where ska ha 1–" . HOURS_ROWS_MAX . ' rader');
    foreach ($g['rows'] as $n => $r) {
      if (!is_array($r) || !array_is_list($r) || count($r) !== 2) throw new InvalidArgumentException("Felaktig rad i $where");
      hours_text($r[0], 40, 'rad ' . ($n + 1) . " ($where)");
      hours_text($r[1], 60, 'rad ' . ($n + 1) . " ($where)");
    }
  }
}

function hours_time(string $v, string $where): ?DateTimeImmutable {
  if ($v === '') return null;
  $d = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $v) ?: DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339, $v);
  if (!$d) throw new InvalidArgumentException("Ogiltig tid i $where");
  return $d;
}

function validate_hours_exceptions($list): void {
  if (!is_array($list) || !array_is_list($list)) throw new InvalidArgumentException('Ogiltig lista med avvikande öppettider');
  if (count($list) > HOURS_EXC_MAX) throw new InvalidArgumentException('Max ' . HOURS_EXC_MAX . ' avvikelser');
  $ids = [];
  foreach ($list as $i => $x) {
    $where = 'avvikelse ' . ($i + 1);
    if (!is_array($x) || array_is_list($x) || array_diff(array_keys($x), HOURS_EXC_KEYS) || array_diff(HOURS_EXC_KEYS, array_keys($x)))
      throw new InvalidArgumentException("Felaktiga fält i $where");
    if (!is_string($x['id']) || !preg_match('/^[a-z0-9]{1,20}$/', $x['id']) || isset($ids[$x['id']]))
      throw new InvalidArgumentException("Ogiltigt id i $where");
    $ids[$x['id']] = true;
    if (!is_bool($x['enabled']) || !is_bool($x['banner'])) throw new InvalidArgumentException("Ogiltig status i $where");
    hours_text($x['title'], 80, "rubriken ($where)");
    hours_text($x['bannerText'], 300, "bannertexten ($where)", false);
    if (!is_array($x['days']) || !array_is_list($x['days']) || !$x['days'] || count($x['days']) > HOURS_DAYS_MAX)
      throw new InvalidArgumentException("$where ska ha 1–" . HOURS_DAYS_MAX . ' dagar');
    $dates = [];
    foreach ($x['days'] as $n => $d) {
      $w = 'dag ' . ($n + 1) . " ($where)";
      if (!is_array($d) || array_is_list($d) || array_diff(array_keys($d), ['date', 'time']) || count($d) !== 2)
        throw new InvalidArgumentException("Felaktiga fält i $w");
      $dt = is_string($d['date']) ? DateTimeImmutable::createFromFormat('!Y-m-d', $d['date']) : false;
      if (!$dt || $dt->format('Y-m-d') !== $d['date']) throw new InvalidArgumentException("Ogiltigt datum i $w");
      if (isset($dates[$d['date']])) throw new InvalidArgumentException("Datumet {$d['date']} finns två gånger i $where");
      $dates[$d['date']] = true;
      hours_text($d['time'], 60, "tiden ($w)");
    }
    $s = hours_time($x['start'], "publiceringen ($where)");
    $e = hours_time($x['end'], "avpubliceringen ($where)");
    if ($s && $e && $e <= $s) throw new InvalidArgumentException("Avpubliceringen måste vara efter publiceringen i $where");
  }
}

return [
  'title' => 'Öppettider',
  'page' => __DIR__ . '/page.php',
  'lists' => ['hours' => 'validate_hours', 'hoursExceptions' => 'validate_hours_exceptions'],
  // Fält som översätts (modulen sprak). rows = [dag, tid]; tiden följer med, så att t.ex. "Stängt" kan översättas.
  'translate' => ['hours.#.title', 'hours.#.rows.#.#', 'hoursExceptions.#.title', 'hoursExceptions.#.bannerText', 'hoursExceptions.#.days.#.time'],
  // base = kontrollsumma av öppettiderna när sidan öppnades, så att två personer inte skriver över varandra.
  'api' => function (string $action): void {
    $pick = fn(array $site) => ['hours' => $site['hours'] ?? [], 'exceptions' => $site['hoursExceptions'] ?? []];
    if ($action === 'hours') {
      $d = $pick(load_site()['site']);
      json_out($d + ['base' => sha1(json_encode($d))]);
    }
    if ($action === 'hours_save') {
      require_write();
      $in = json_in();
      $d = ['hours' => $in['hours'] ?? null, 'exceptions' => $in['exceptions'] ?? null];
      with_site_lock(function () use ($in, $d, $pick) {
        $site = load_site()['site'];
        if (($in['base'] ?? '') !== sha1(json_encode($pick($site))))
          json_out(['error' => 'Någon annan har ändrat öppettiderna sedan du öppnade sidan. Ladda om och gör om dina ändringar.'], 409);
        validate_hours($d['hours']);
        validate_hours_exceptions($d['exceptions']);
        backup_current(current_user());
        $site['hours'] = $d['hours'];
        $site['hoursExceptions'] = $d['exceptions'];
        write_site($site);
      });
      json_out($d + ['base' => sha1(json_encode($d))]);
    }
  },
];
