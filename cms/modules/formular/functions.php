<?php
// Modulen "formular": formulär som besökare fyller i (offertförfrågan, felanmälan, intresseanmälan …).
// Gemensamt för redigeringen (module.php, page.php) och webbplatsens sidor (render.php, form.js).
//
//   cms/storage/formular.json             formulären: fält, texter, val, mottagare (redigeras i CMS:et)
//   cms/site/formular-start.json          webbplatsens startformulär, används tills någon har sparat
//   cms/storage/formular/<formulär>/      inskicken (<id>.json) och deras bilder (<id>-<n>.jpg)
//   cms/storage/formular-nyckel.txt       hemlig nyckel för tidsstämpeln i formulären
declare(strict_types=1);

const FORMS_FILE = STORAGE_DIR . '/formular.json';
const FORMS_START = SITE_DIR . '/formular-start.json';
const FORMS_ENTRY_DIR = STORAGE_DIR . '/formular';
const FORMS_MAX = 20;
const FORM_FIELDS_MAX = 30;
const FORM_OPTIONS_MAX = 40;
const FORM_RECIPIENTS_MAX = 5;
const FORM_TYPES = ['text', 'textarea', 'email', 'tel', 'select', 'image'];
const FORM_TEXT_MAX = ['text' => 200, 'textarea' => 3000, 'email' => 200, 'tel' => 30, 'select' => 200];
// Gäller bara när webbläsaren inte har krympt fotot (form.js gör det före uppladdningen).
const FORM_IMAGE_MAX = 20 * 1024 * 1024;
// Större bilder (i pixlar) än så här får servern inte minne att packa upp.
const FORM_IMAGE_PIXELS = 50_000_000;
const FORM_IMAGE_WIDTH = 1920;
const FORM_ID_PATTERN = '/^[a-z][a-z0-9-]{0,39}$/';
const FORM_FIELD_ID_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';
const FORM_ENTRY_PATTERN = '/^\d{8}-\d{6}-[a-f0-9]{6}$/';
// Besökarens skräpfälla: dolt fält som bara robotar fyller i.
const FORM_HONEYPOT = 'webbplats';
// Max inskick per IP-adress och kvart (alla formulär tillsammans).
const FORM_SUBMITS_MAX = 5;

// page = sidfilen formuläret visas på (en nyckel i config 'pages', '' = ingen sida). intro = text ovanför
// formuläret när det läggs sist på sidan.
const FORM_DEFAULTS = ['id' => '', 'title' => '', 'active' => true, 'page' => '', 'intro' => '', 'recipients' => [], 'subject' => '',
  'button' => 'Skicka', 'success' => 'Tack! Vi har tagit emot ditt meddelande.', 'fields' => []];
const FORM_FIELD_DEFAULTS = ['id' => '', 'type' => 'text', 'label' => '', 'help' => '', 'placeholder' => '',
  'required' => false, 'options' => [], 'dynamic' => false];

// ---- Formulären ----

function forms_load(): array {
  $file = is_file(FORMS_FILE) ? FORMS_FILE : (is_file(FORMS_START) ? FORMS_START : null);
  if (!$file) return [];
  $list = json_decode((string)file_get_contents($file), true);
  return is_array($list) && array_is_list($list) ? array_map('form_normalize', array_filter($list, 'is_array')) : [];
}

function form_get(string $id): ?array {
  foreach (forms_load() as $f) if ($f['id'] === $id) return $f;
  return null;
}

// Bara kända nycklar, i fast ordning. Typerna kontrolleras av validate_forms().
function form_normalize(array $f): array {
  $out = [];
  foreach (FORM_DEFAULTS as $k => $d) $out[$k] = array_key_exists($k, $f) ? $f[$k] : $d;
  if (is_array($out['fields'])) $out['fields'] = array_map(function ($x) {
    if (!is_array($x)) return $x;
    $o = [];
    foreach (FORM_FIELD_DEFAULTS as $k => $d) $o[$k] = array_key_exists($k, $x) ? $x[$k] : $d;
    return $o;
  }, $out['fields']);
  return $out;
}

function form_text($v, int $max, string $where, bool $required = true, bool $multiline = false): void {
  $ctrl = $multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
  if (!is_string($v) || mb_strlen($v) > $max || preg_match($ctrl, $v))
    throw new InvalidArgumentException("Ogiltig text i $where (högst $max tecken)");
  if ($required && trim($v) === '') throw new InvalidArgumentException("Fyll i $where");
}

function validate_forms($list): void {
  if (!is_array($list) || !array_is_list($list)) throw new InvalidArgumentException('Ogiltig lista med formulär');
  if (count($list) > FORMS_MAX) throw new InvalidArgumentException('Max ' . FORMS_MAX . ' formulär');
  $ids = [];
  foreach ($list as $i => $f) {
    if (!is_array($f) || array_keys($f) !== array_keys(FORM_DEFAULTS)) throw new InvalidArgumentException('Felaktiga fält i formulär ' . ($i + 1));
    $where = is_string($f['title']) && trim($f['title']) !== '' ? '"' . $f['title'] . '"' : 'formulär ' . ($i + 1);
    if (!is_string($f['id']) || !preg_match(FORM_ID_PATTERN, $f['id']) || isset($ids[$f['id']]))
      throw new InvalidArgumentException("Ogiltigt eller dubblerat id i $where");
    $ids[$f['id']] = true;
    form_text($f['title'], 80, "namnet ($where)");
    if (!is_bool($f['active'])) throw new InvalidArgumentException("Ogiltig status i $where");
    if (!is_string($f['page']) || ($f['page'] !== '' && !isset(config()['pages'][$f['page']])))
      throw new InvalidArgumentException("Okänd sida i $where");
    form_text($f['intro'], 1000, "ingressen ($where)", false, true);
    if (!is_array($f['recipients']) || !array_is_list($f['recipients']) || count($f['recipients']) > FORM_RECIPIENTS_MAX)
      throw new InvalidArgumentException("Högst " . FORM_RECIPIENTS_MAX . " mottagare i $where");
    foreach ($f['recipients'] as $r)
      if (!is_string($r) || !filter_var($r, FILTER_VALIDATE_EMAIL) || mb_strlen($r) > 200)
        throw new InvalidArgumentException('Ogiltig e-postadress "' . (is_string($r) ? $r : '') . "\" i mottagarna ($where)");
    form_text($f['subject'], 150, "ämnesraden ($where)");
    form_text($f['button'], 60, "knapptexten ($where)");
    form_text($f['success'], 500, "tackmeddelandet ($where)", true, true);
    if (!is_array($f['fields']) || !array_is_list($f['fields']) || !$f['fields'] || count($f['fields']) > FORM_FIELDS_MAX)
      throw new InvalidArgumentException("$where ska ha 1–" . FORM_FIELDS_MAX . ' fält');
    $fids = [];
    foreach ($f['fields'] as $n => $x) {
      $w = 'fält ' . ($n + 1) . " ($where)";
      if (!is_array($x) || array_keys($x) !== array_keys(FORM_FIELD_DEFAULTS)) throw new InvalidArgumentException("Felaktiga uppgifter i $w");
      if (!is_string($x['id']) || !preg_match(FORM_FIELD_ID_PATTERN, $x['id']) || isset($fids[$x['id']]) || $x['id'] === FORM_HONEYPOT)
        throw new InvalidArgumentException("Ogiltig eller dubblerad kod i $w");
      $fids[$x['id']] = true;
      if (!in_array($x['type'], FORM_TYPES, true)) throw new InvalidArgumentException("Okänd typ i $w");
      form_text($x['label'], 120, "rubriken ($w)");
      form_text($x['help'], 300, "hjälptexten ($w)", false);
      form_text($x['placeholder'], 200, "exempeltexten ($w)", false);
      if (!is_bool($x['required']) || !is_bool($x['dynamic'])) throw new InvalidArgumentException("Ogiltigt val i $w");
      if ($x['dynamic'] && $x['type'] !== 'select') throw new InvalidArgumentException("Bara listval kan hämtas från webbplatsen ($w)");
      if (!is_array($x['options']) || !array_is_list($x['options']) || count($x['options']) > FORM_OPTIONS_MAX)
        throw new InvalidArgumentException("Högst " . FORM_OPTIONS_MAX . " val i $w");
      if ($x['type'] === 'select' && !$x['dynamic'] && !$x['options']) throw new InvalidArgumentException("Lägg till minst ett val i $w");
      foreach ($x['options'] as $o) form_text($o, 120, "valen ($w)");
      if (count(array_unique($x['options'])) !== count($x['options'])) throw new InvalidArgumentException("Samma val finns två gånger i $w");
    }
  }
}

// Sparar formulären. $base = kontrollsumman när sidan öppnades, så att två personer inte skriver över varandra.
function forms_save(array $list, string $base): string {
  $list = array_map(fn($f) => is_array($f) ? form_normalize($f) : $f, $list);
  validate_forms($list);
  return with_site_lock(function () use ($list, $base) {
    if ($base !== forms_version(forms_load()))
      json_out(['error' => 'Någon annan har ändrat formulären sedan du öppnade sidan. Ladda om och gör om dina ändringar.'], 409);
    if (!is_dir(STORAGE_DIR)) mkdir(STORAGE_DIR, 0775, true);
    if (is_file(FORMS_FILE)) copy(FORMS_FILE, FORMS_FILE . '.bak');
    atomic_write(FORMS_FILE, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return forms_version($list);
  });
}

function forms_version(array $list): string {
  return sha1(json_encode($list, JSON_UNESCAPED_UNICODE));
}

// Det besökarens webbläsare får veta om ett formulär (inte mottagare eller ämnesrad).
function form_public(array $f): array {
  return ['id' => $f['id'], 'title' => $f['title'], 'intro' => $f['intro'], 'button' => $f['button'], 'success' => $f['success'],
    'fields' => array_map(fn($x) => array_diff_key($x, ['dynamic' => 1]), $f['fields'])];
}

// ---- Tidsstämpel: stoppar robotar som skickar direkt, utan cookie ----

function form_secret(): string {
  $file = STORAGE_DIR . '/formular-nyckel.txt';
  $key = is_file($file) ? trim((string)file_get_contents($file)) : '';
  if (strlen($key) < 32) {
    if (!is_dir(STORAGE_DIR)) mkdir(STORAGE_DIR, 0775, true);
    $key = bin2hex(random_bytes(32));
    file_put_contents($file, $key, LOCK_EX);
  }
  return $key;
}

function form_token(string $id): string {
  $t = (string)time();
  return $t . '.' . substr(hash_hmac('sha256', "$id|$t", form_secret()), 0, 32);
}

// null = godkänd, annars ett meddelande till besökaren.
function form_token_error(string $id, string $token): ?string {
  [$t, $sig] = array_pad(explode('.', $token, 2), 2, '');
  if (!ctype_digit($t) || !hash_equals(substr(hash_hmac('sha256', "$id|$t", form_secret()), 0, 32), $sig))
    return 'Formuläret kunde inte kontrolleras. Ladda om sidan och försök igen.';
  $age = time() - (int)$t;
  if ($age < 3) return 'Formuläret skickades för snabbt. Vänta någon sekund och försök igen.';
  if ($age > 2 * 86400) return 'Sidan har varit öppen länge. Kontrollera uppgifterna och skicka igen.';
  return null;
}

// ---- Inskick ----

// Valen i ett listfält: [värde => text]. Fasta val har texten som värde; dynamiska kommer från webbplatsen.
function form_options(array $field, array $dynamic): array {
  if ($field['dynamic']) return array_map('strval', $dynamic[$field['id']] ?? []);
  return array_combine($field['options'], $field['options']) ?: [];
}

// Tar emot ett inskick. $post/$files = $_POST/$_FILES, $dynamic = [fält-id => [värde => text]] för dynamiska listval.
// Returnerar ['ok' => bool, 'errors' => [...], 'values' => [fält-id => värde]] – värdena för att fylla i formuläret igen.
function form_submit(array $form, array $post, array $files, array $dynamic = []): array {
  $values = [];
  foreach ($form['fields'] as $x) if ($x['type'] !== 'image') $values[$x['id']] = is_string($post[$x['id']] ?? null) ? $post[$x['id']] : '';
  $fail = fn(array $errors) => ['ok' => false, 'errors' => $errors, 'values' => $values];

  if (!$form['active']) return $fail(['Formuläret tar inte emot några inskick just nu.']);
  // Robot: låtsas att allt gick bra.
  if (trim((string)($post[FORM_HONEYPOT] ?? '')) !== '') return ['ok' => true, 'errors' => [], 'values' => []];
  if ($e = form_token_error($form['id'], (string)($post['cms_t'] ?? ''))) return $fail([$e]);

  $errors = [];
  $rows = [];
  $images = [];
  foreach ($form['fields'] as $x) {
    $label = $x['label'];
    // Rubriken inom citattecken i felmeddelandena, eftersom den ofta är en fråga ("Vad gäller det?").
    $q = rtrim($label, ' :');
    if ($x['type'] === 'image') {
      $file = $files[$x['id']] ?? null;
      if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        if ($x['required']) $errors[] = "Bifoga en bild i \"$q\".";
        continue;
      }
      $img = form_image($file, $err);
      if ($err) { $errors[] = "\"$q\": $err"; continue; }
      $images[] = ['label' => $label, 'data' => $img];
      continue;
    }
    if (!mb_check_encoding($values[$x['id']], 'UTF-8')) {
      $values[$x['id']] = '';
      $errors[] = "\"$q\" innehåller otillåtna tecken.";
      continue;
    }
    $v = str_replace("\r\n", "\n", trim($values[$x['id']]));
    if ($x['type'] !== 'textarea') $v = preg_replace('/\s+/u', ' ', $v);
    $values[$x['id']] = $v;
    if ($x['type'] === 'select') {
      $opts = form_options($x, $dynamic);
      if (!array_key_exists($v, $opts)) {
        if ($v === '' && !$x['required']) { $rows[] = [$x, '']; continue; }
        $errors[] = "Välj ett alternativ i \"$q\".";
        continue;
      }
      $rows[] = [$x, $opts[$v]];
      continue;
    }
    if ($v === '') {
      if ($x['required']) $errors[] = "Fyll i \"$q\".";
      $rows[] = [$x, ''];
      continue;
    }
    $max = FORM_TEXT_MAX[$x['type']];
    if (mb_strlen($v) > $max) { $errors[] = "\"$q\" får vara högst $max tecken."; continue; }
    if (preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $v)) { $errors[] = "\"$q\" innehåller otillåtna tecken."; continue; }
    if ($x['type'] === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Ange en giltig e-postadress.'; continue; }
    if ($x['type'] === 'tel' && !preg_match('/^[0-9+\-() ]{5,30}$/', $v)) { $errors[] = 'Ange ett giltigt telefonnummer.'; continue; }
    $rows[] = [$x, $v];
  }
  if ($errors) return $fail($errors);
  if (login_attempts(null, 'form') >= FORM_SUBMITS_MAX)
    return $fail(['Det har kommit många inskick från din anslutning. Vänta en kvart och försök igen.']);
  login_attempts(true, 'form');

  // Spara först, så att inget går förlorat om mejlet inte kommer fram.
  $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
  $dir = FORMS_ENTRY_DIR . '/' . $form['id'];
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  $entry = ['id' => $id, 'form' => $form['id'], 'title' => $form['title'], 'created' => date('c'), 'status' => 'ny',
    'values' => array_map(fn($r) => ['id' => $r[0]['id'], 'label' => $r[0]['label'], 'type' => $r[0]['type'], 'value' => $r[1]], $rows),
    'files' => [], 'mailed' => false];
  foreach ($images as $n => $img) {
    $file = "$id-" . ($n + 1) . '.jpg';
    file_put_contents("$dir/$file", $img['data']);
    $entry['files'][] = ['label' => $img['label'], 'file' => $file];
  }
  form_entry_write($entry);

  $entry['mailed'] = form_mail($form, $entry, $images);
  form_entry_write($entry);
  return ['ok' => true, 'errors' => [], 'values' => []];
}

function form_mail(array $form, array $entry, array $images): bool {
  if (!$form['recipients']) return false;
  $by = [];
  $replyTo = '';
  foreach ($entry['values'] as $v) {
    $by[$v['id']] = $v['value'];
    if ($replyTo === '' && $v['type'] === 'email' && $v['value'] !== '') $replyTo = $v['value'];
  }
  $subject = preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', fn($m) => preg_replace('/\s+/u', ' ', $by[$m[1]] ?? ''), $form['subject']);
  $subject = mb_substr(trim(preg_replace('/\(\s*\)|\s{2,}/u', ' ', $subject)), 0, 200);
  $lines = ["Nytt inskick från formuläret \"{$form['title']}\" på webbplatsen.", ''];
  foreach ($entry['values'] as $v) {
    $val = $v['value'] === '' ? '(ej ifyllt)' : $v['value'];
    $lines[] = $v['type'] === 'textarea' ? "{$v['label']}:\n$val\n" : "{$v['label']}: $val";
  }
  if ($images) $lines[] = "\nBifogade bilder: " . count($images);
  $url = rtrim(config()['site_url'], '/');
  if ($url !== '') $lines[] = "\nAlla inskick finns också i CMS:et: $url/cms/page.php?m=formular";
  if ($replyTo !== '') $lines[] = 'Svara på det här mejlet för att svara avsändaren.';
  $att = array_map(fn($img, $n) => ['name' => $form['id'] . '-bild-' . ($n + 1) . '.jpg', 'mime' => 'image/jpeg', 'data' => $img['data']], $images, array_keys($images));
  $ok = false;
  foreach ($form['recipients'] as $to) {
    $sent = send_mail($to, $subject, implode("\n", $lines), ['reply_to' => $replyTo, 'attachments' => $att]);
    if (!$sent) error_log("[cms] Kunde inte mejla inskick {$entry['id']} till $to");
    $ok = $ok || $sent;
  }
  return $ok;
}

// Kontrollerar en uppladdad bild, vänder den rätt, skalar ned den och returnerar den som JPEG.
function form_image(array $file, ?string &$err): ?string {
  $err = null;
  $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
  if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > FORM_IMAGE_MAX) {
    $err = 'Bilden är för stor (max ' . (FORM_IMAGE_MAX >> 20) . ' MB).';
    return null;
  }
  $tmp = (string)($file['tmp_name'] ?? '');
  if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) { $err = 'Bilden kunde inte laddas upp. Försök igen.'; return null; }
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
  $size = @getimagesize($tmp);
  if (!$size) { $err = 'Filen är inte en bild som går att läsa (JPG, PNG eller WEBP).'; return null; }
  if ($size[0] * $size[1] > FORM_IMAGE_PIXELS) { $err = 'Bilden har för hög upplösning. Prova att ta en skärmbild av den och skicka den i stället.'; return null; }
  // En mobilbild på 12–48 megapixlar behöver mycket minne när den packas upp.
  $mem = (string)ini_get('memory_limit');
  $bytes = (int)$mem * (['k' => 1 << 10, 'm' => 1 << 20, 'g' => 1 << 30][strtolower(substr($mem, -1))] ?? 1);
  if ($mem !== '-1' && $bytes < (384 << 20) && function_exists('ini_set')) @ini_set('memory_limit', '384M');
  $src = match ($mime) {
    'image/jpeg' => @imagecreatefromjpeg($tmp),
    'image/png' => @imagecreatefrompng($tmp),
    'image/webp' => @imagecreatefromwebp($tmp),
    default => false,
  };
  if (!$src) { $err = 'Filen är inte en bild som går att läsa (JPG, PNG eller WEBP).'; return null; }
  // Mobilbilder ligger ofta på sidan med en EXIF-uppgift om hur de ska vändas.
  if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
    $o = (int)(@exif_read_data($tmp)['Orientation'] ?? 1);
    $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
    if ($deg && ($r = imagerotate($src, $deg, 0))) $src = $r;
  }
  $w = imagesx($src);
  $hgt = imagesy($src);
  $nw = min($w, FORM_IMAGE_WIDTH);
  $nh = max(1, (int)round($hgt * $nw / $w));
  $out = imagecreatetruecolor($nw, $nh);
  imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
  imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
  ob_start();
  imagejpeg($out, null, 85);
  return (string)ob_get_clean();
}

function form_entry_file(string $form, string $id): string {
  if (!preg_match(FORM_ID_PATTERN, $form) || !preg_match(FORM_ENTRY_PATTERN, $id)) throw new InvalidArgumentException('Ogiltigt inskick');
  return FORMS_ENTRY_DIR . "/$form/$id.json";
}

function form_entry_write(array $entry): void {
  atomic_write(form_entry_file($entry['form'], $entry['id']), json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function form_entry_read(string $form, string $id): array {
  $f = form_entry_file($form, $id);
  $e = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
  if (!is_array($e)) throw new InvalidArgumentException('Inskicket finns inte längre');
  return $e;
}

function form_entry_delete(string $form, string $id): void {
  $e = form_entry_read($form, $id);
  foreach ($e['files'] ?? [] as $x) if (preg_match('/^[\w-]+\.jpg$/', $x['file'])) @unlink(FORMS_ENTRY_DIR . "/$form/{$x['file']}");
  @unlink(form_entry_file($form, $id));
}

// Alla inskick, nyast först, grupperade per formulär. Gamla inskick rensas bort först.
function form_entries(): array {
  $keep = max(1, (int)config()['form_keep_days']);
  $limit = date('Ymd', time() - $keep * 86400);
  $out = [];
  foreach (glob(FORMS_ENTRY_DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $form = basename($dir);
    if (!preg_match(FORM_ID_PATTERN, $form)) continue;
    $files = glob("$dir/*.json") ?: [];
    rsort($files);
    foreach ($files as $f) {
      $id = basename($f, '.json');
      if (!preg_match(FORM_ENTRY_PATTERN, $id)) continue;
      if (substr($id, 0, 8) < $limit) { form_entry_delete($form, $id); continue; }
      $e = json_decode((string)file_get_contents($f), true);
      if (is_array($e)) $out[$form][] = $e;
    }
  }
  return $out;
}
