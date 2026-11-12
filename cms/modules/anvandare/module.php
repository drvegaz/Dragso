<?php
// Modulen "anvandare": lista, lägga till och ta bort redaktörer. Alla inloggade får göra allt (inga roller).
// Nya användare sparas i storage/users.json och får en inbjudningslänk där de väljer sitt lösenord.
// Användare i config.php visas också, men kan bara tas bort i filen. Själva logiken finns i lib.php.

function users_admin_list(): array {
  $me = current_user();
  $out = [];
  foreach (user_names() as $name) {
    $r = user_record($name);
    $waiting = $r['must_change'] || $r['hash'] === '';
    $invite = ($r['reset_kind'] ?? '') === 'invite';
    $status = !$waiting ? 'active' : ($invite ? (($r['reset_expires'] ?? 0) > time() ? 'invited' : 'expired') : 'new');
    $out[] = ['name' => $name, 'email' => $r['email'] ?? '', 'cms' => !empty($r['cms']), 'me' => $name === $me, 'status' => $status,
      'created' => $r['created'] ?? null, 'createdBy' => $r['created_by'] ?? null, 'expires' => $invite ? ($r['reset_expires'] ?? null) : null];
  }
  usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
  return $out;
}

return [
  'title' => 'Användare',
  'page' => __DIR__ . '/page.php',
  'api' => function (string $action): void {
    if ($action === 'users') json_out(['users' => users_admin_list()]);
    if (!in_array($action, ['user_create', 'user_link', 'user_delete'], true)) return;

    require_write();
    $in = json_in();
    $me = current_user();
    if ($action === 'user_create') {
      $r = create_user((string)($in['name'] ?? ''), (string)($in['email'] ?? ''), $me);
      json_out($r + ['users' => users_admin_list()]);
    }
    $name = (string)($in['name'] ?? '');
    $rec = user_record($name);
    if (!$rec) json_out(['error' => 'Användaren finns inte.'], 404);
    if ($action === 'user_link') {
      // Har användaren inte valt lösenord än: ny inbjudan (länken visas). Annars: vanlig "glömt lösenord"-länk
      // till användarens egen e-post, som inte visas här – så att ingen kan ta över någon annans konto.
      if ($rec['must_change'] || $rec['hash'] === '') json_out(send_invite($name, $me) + ['users' => users_admin_list()]);
      if (empty($rec['email'])) json_out(['error' => 'Användaren saknar e-postadress.'], 422);
      if (!send_reset_link($name)) json_out(['error' => 'Mejlet kunde inte skickas. Försök igen senare, eller be användaren använda "Glömt lösenord" på inloggningssidan.'], 502);
      json_out(['sent' => true, 'reset' => true, 'users' => users_admin_list()]);
    }
    if ($action === 'user_delete') {
      if ($name === $me) json_out(['error' => 'Du kan inte ta bort dig själv.'], 422);
      delete_user($name);
      json_out(['users' => users_admin_list()]);
    }
  },
];
