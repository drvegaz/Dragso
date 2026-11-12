<?php
// Formulär på sidor som ritas med PHP. Fungerar utan JavaScript.
//
// Redaktören väljer vilken sida (config 'pages') varje formulär visas på. Lägg in på varje sida:
//
//   require __DIR__ . '/cms/modules/formular/render.php';
//   cms_forms_handle($opt);                  // överst, innan något skrivs ut: tar emot inskick på den här sidan
//   …
//   echo cms_form_html('offert', $opt);      // (valfritt) en egen plats för ett visst formulär
//   …
//   echo cms_forms_bottom($opt);             // sist i innehållet: övriga formulär som ska visas här
//
// cms_form_html() visar bara formuläret om det är valt för den här sidan, och cms_forms_bottom() hoppar
// över formulär som redan har visats. Har webbplatsen ingen 'pages' visas formulären där de läggs in.
//
// Alternativ (samma till alla anrop):
//   'options'      => ['fält-id' => [värde => text]]   valen i listfält som hämtas från webbplatsen ("dynamic")
//   'values'       => ['fält-id' => värde]             förifyllda värden, t.ex. från en länk
//   'button_class' => 'btn'                            klass på skicka-knappen
//   'section_class', 'container_class'                 klasser på <section> och <div> runt formulär sist på sidan
//   'cms_path'     => '/cms/'                          adressen till cms/ (för form.js, som krymper foton)
//
// Uppmärkningen: div.cms-form#form-<id> > (div.success | div.errors > ul) + form > div.field > label + input …
// Valfria fält får <span class="optional">(frivilligt)</span> i rubriken, och hjälptexter <small class="help">.
// Robotfällan ligger i div.field.hp – dölj den med CSS (position:absolute;left:-9999px).
//
// Webbplatsens sida får inte själv definiera funktioner som finns i cms/lib.php (t.ex. csrf_token, h).
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib.php';
require_once __DIR__ . '/functions.php';

// Sidfilen som körs, t.ex. 'index.php'.
function cms_current_page(): string {
  return basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
}

// Ska formuläret visas på den här sidan?
function cms_form_here(array $form): bool {
  return !config()['pages'] || $form['page'] === cms_current_page();
}

// Tar emot ett inskick till något av formulären som visas på den här sidan.
function cms_forms_handle(array $opt = []): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && is_string($_POST['cms_form'] ?? null)) cms_form_handle($_POST['cms_form'], $opt);
}

// Tar emot inskicket om det gäller det här formuläret. Lyckas det skickas besökaren tillbaka till sidan
// med ?skickat=<id>, så att en omladdning inte skickar formuläret igen.
function cms_form_handle(string $id, array $opt = []): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['cms_form'] ?? '') !== $id) return;
  $form = form_get($id);
  if (!$form || !cms_form_here($form)) return;
  try {
    $r = form_submit($form, $_POST, $_FILES, $opt['options'] ?? []);
  } catch (Throwable $e) {
    error_log('[cms] Formuläret ' . $id . ': ' . $e);
    $r = ['ok' => false, 'errors' => ['Något gick fel när formuläret skulle skickas. Försök igen, eller kontakta oss på annat sätt.'], 'values' => []];
  }
  if ($r['ok']) {
    $path = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
    header('Location: ' . $path . '?skickat=' . rawurlencode($id) . '#form-' . $id, true, 303);
    exit;
  }
  $GLOBALS['cms_form_result'][$id] = $r;
}

// Alla formulär som ska visas på den här sidan och inte redan har visats, sist i innehållet.
function cms_forms_bottom(array $opt = []): string {
  if (!config()['pages']) return '';
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $out = '';
  foreach (forms_load() as $f) {
    if (!cms_form_here($f) || isset($GLOBALS['cms_form_shown'][$f['id']])) continue;
    $out .= '<section class="cms-form-section ' . $e($opt['section_class'] ?? '') . '" id="sektion-' . $e($f['id']) . '">'
      . '<div class="' . $e($opt['container_class'] ?? '') . '"><h2>' . $e($f['title']) . '</h2>'
      . ($f['intro'] !== '' ? '<p class="intro">' . nl2br($e($f['intro'])) . '</p>' : '')
      . cms_form_html($f['id'], $opt) . '</div></section>';
  }
  return $out;
}

function cms_form_html(string $id, array $opt = []): string {
  $form = form_get($id);
  if (!$form || !cms_form_here($form)) return '';
  $GLOBALS['cms_form_shown'][$id] = true;
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $out = '<div class="cms-form" id="form-' . $e($id) . '">';
  if (($_GET['skickat'] ?? '') === $id) {
    return $out . '<div class="success" role="status">' . nl2br($e($form['success'])) . '</div></div>';
  }
  if (!$form['active']) return $out . '<div class="errors" role="status">Formuläret tar inte emot några inskick just nu.</div></div>';

  $result = $GLOBALS['cms_form_result'][$id] ?? null;
  $values = $result['values'] ?? array_map('strval', $opt['values'] ?? []);
  if ($result && $result['errors']) {
    $out .= '<div class="errors" role="alert"><ul>';
    foreach ($result['errors'] as $err) $out .= '<li>' . $e($err) . '</li>';
    $out .= '</ul></div>';
  }
  $multipart = in_array('image', array_column($form['fields'], 'type'), true);
  $out .= '<form method="post" action="#form-' . $e($id) . '"' . ($multipart ? ' enctype="multipart/form-data"' : '') . '>'
    . '<input type="hidden" name="cms_form" value="' . $e($id) . '">'
    . '<input type="hidden" name="cms_t" value="' . $e(form_token($id)) . '">'
    . '<div class="field hp" aria-hidden="true"><label for="' . $e("f-$id-hp") . '">Lämna tomt</label>'
    . '<input type="text" id="' . $e("f-$id-hp") . '" name="' . FORM_HONEYPOT . '" tabindex="-1" autocomplete="off" value=""></div>';

  foreach ($form['fields'] as $x) {
    $fid = "f-$id-{$x['id']}";
    $req = $x['required'] ? ' required' : '';
    $help = $x['help'] !== '' ? '<small class="help" id="' . $e("$fid-help") . '">' . $e($x['help']) . '</small>' : '';
    $desc = $help ? ' aria-describedby="' . $e("$fid-help") . '"' : '';
    $ph = $x['placeholder'] !== '' ? ' placeholder="' . $e($x['placeholder']) . '"' : '';
    $val = $values[$x['id']] ?? '';
    $out .= '<div class="field field-' . $e($x['type']) . '"><label for="' . $e($fid) . '">' . $e($x['label'])
      . ($x['required'] ? '' : ' <span class="optional">(frivilligt)</span>') . '</label>';
    $name = ' id="' . $e($fid) . '" name="' . $e($x['id']) . '"';
    switch ($x['type']) {
      case 'textarea':
        $out .= "<textarea$name$req$desc$ph maxlength=\"" . FORM_TEXT_MAX['textarea'] . '">' . $e($val) . '</textarea>';
        break;
      case 'select':
        $out .= "<select$name$req$desc>";
        $opts = form_options($x, $opt['options'] ?? []);
        if (!$x['dynamic'] || $x['placeholder'] !== '')
          $out .= '<option value="">' . $e($x['placeholder'] !== '' ? $x['placeholder'] : 'Välj …') . '</option>';
        foreach ($opts as $v => $text)
          $out .= '<option value="' . $e($v) . '"' . ((string)$v === $val ? ' selected' : '') . '>' . $e($text) . '</option>';
        $out .= '</select>';
        break;
      case 'image':
        $out .= "<input type=\"file\"$name$req$desc accept=\"image/jpeg,image/png,image/webp\">";
        break;
      default:
        $type = ['email' => 'email', 'tel' => 'tel'][$x['type']] ?? 'text';
        $ac = ['email' => ' autocomplete="email"', 'tel' => ' autocomplete="tel"'][$x['type']] ?? '';
        $out .= "<input type=\"$type\"$name$req$desc$ph$ac maxlength=\"" . FORM_TEXT_MAX[$x['type']] . '" value="' . $e($val) . '">';
    }
    $out .= $help . '</div>';
  }
  $out .= '<button type="submit" class="' . $e($opt['button_class'] ?? 'btn') . '">' . $e($form['button']) . '</button></form></div>';
  // Krymper foton i webbläsaren innan de skickas (se form.js). En gång per sida.
  if ($multipart && empty($GLOBALS['cms_form_js'])) {
    $GLOBALS['cms_form_js'] = true;
    $out .= '<script src="' . $e($opt['cms_path'] ?? '/cms/') . 'modules/formular/form.js" defer></script>';
  }
  return $out;
}
