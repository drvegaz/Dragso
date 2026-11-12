<?php
// Modulen "formular": formulär som redaktören bygger och ändrar, och inskick som sparas och mejlas.
// Se functions.php för lagringen, render.php för PHP-sidor och form.js för statiska sidor.
require_once __DIR__ . '/functions.php';

return [
  'title' => 'Formulär',
  'page' => __DIR__ . '/page.php',

  // Statiska sidor (form.js) hämtar formuläret och skickar in det här. PHP-sidor använder render.php.
  'public_api' => function (string $action): void {
    if ($action === 'form') {
      $f = form_get((string)($_GET['id'] ?? ''));
      if (!$f || !$f['active']) json_out(['error' => 'Formuläret finns inte.'], 404);
      json_out(['form' => form_public($f), 'token' => form_token($f['id'])]);
    }
    if ($action === 'form_submit') {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Fel metod'], 405);
      $f = form_get((string)($_POST['cms_form'] ?? ''));
      if (!$f) json_out(['error' => 'Formuläret finns inte.'], 404);
      $r = form_submit($f, $_POST, $_FILES);
      json_out($r['ok'] ? ['ok' => true, 'message' => $f['success']] : ['errors' => $r['errors'], 'token' => form_token($f['id'])], $r['ok'] ? 200 : 422);
    }
  },

  'api' => function (string $action): void {
    if ($action === 'forms') {
      $forms = forms_load();
      json_out(['forms' => $forms, 'base' => forms_version($forms), 'types' => FORM_TYPES]);
    }
    if ($action === 'forms_save') {
      require_write();
      $in = json_in();
      $version = forms_save(is_array($in['forms'] ?? null) ? $in['forms'] : [], (string)($in['base'] ?? ''));
      json_out(['ok' => true, 'forms' => forms_load(), 'base' => $version]);
    }
    if ($action === 'form_entries') {
      json_out(['entries' => (object)form_entries(), 'keepDays' => (int)config()['form_keep_days']]);
    }
    if ($action === 'form_entry') {
      require_write();
      $in = json_in();
      $form = (string)($in['form'] ?? '');
      $id = (string)($in['id'] ?? '');
      if (!empty($in['delete'])) {
        form_entry_delete($form, $id);
        json_out(['ok' => true]);
      }
      $e = form_entry_read($form, $id);
      $e['status'] = ($in['status'] ?? '') === 'hanterad' ? 'hanterad' : 'ny';
      form_entry_write($e);
      json_out(['ok' => true, 'entry' => $e]);
    }
    // Bild till ett inskick: api.php?action=form_file&form=…&file=…
    if ($action === 'form_file') {
      $form = (string)($_GET['form'] ?? '');
      $file = (string)($_GET['file'] ?? '');
      if (!preg_match(FORM_ID_PATTERN, $form) || !preg_match('/^\d{8}-\d{6}-[a-f0-9]{6}-\d{1,2}\.jpg$/', $file)
        || !is_file($path = FORMS_ENTRY_DIR . "/$form/$file")) json_out(['error' => 'Bilden finns inte.'], 404);
      header('Content-Type: image/jpeg');
      header('X-Content-Type-Options: nosniff');
      header('Cache-Control: private, max-age=3600');
      header('Content-Disposition: inline; filename="' . $file . '"');
      readfile($path);
      exit;
    }
  },
];
