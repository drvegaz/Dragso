<?php
// JSON-API för redigeringsläget. Alla skrivande anrop kräver inloggning + CSRF-token.
// Moduler (se modules() i lib.php) får svara på egna anrop före och efter inloggningskontrollen.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$action = $_GET['action'] ?? '';

try {
  // Modulernas öppna anrop (t.ex. ett quiz för besökare, eller en sida där koden i länken är behörigheten).
  // De körs före start_session(), så att besökare inte får någon cookie.
  foreach (modules() as $mod) if (isset($mod['public_api'])) $mod['public_api']($action);

  start_session();

  if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Fel metod'], 405);
    if (login_attempts() >= 5) json_out(['error' => 'För många försök. Vänta 15 minuter och försök igen.'], 429);
    $in = json_in();
    $user = find_user((string)($in['user'] ?? ''));
    $rec = $user ? user_record($user) : null;
    if (!$rec || !password_verify((string)($in['password'] ?? ''), $rec['hash'])) {
      login_attempts(true);
      usleep(400000);
      json_out(['error' => 'Fel användarnamn eller lösenord.'], 401);
    }
    login_attempts(false);
    session_regenerate_id(true);
    $_SESSION = ['user' => $user, 'seen' => time(), 'must_change' => (bool)$rec['must_change']];
    json_out(['ok' => true, 'mustChange' => (bool)$rec['must_change'], 'csrf' => csrf_token()]);
  }

  // Redigeringsläget hämtar här både inloggningen och det som skiljer webbplatserna åt.
  if ($action === 'session') {
    $user = current_user();
    json_out(['user' => $user, 'csrf' => $user ? csrf_token() : null,
      'menu' => $user ? module_menu() : [], 'linkLists' => config()['link_lists'], 'pages' => (object)config()['pages'], 'help' => 'cms/hjalp.php']);
  }

  if ($action === 'forgot') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Fel metod'], 405);
    if (login_attempts(null, 'forgot') >= 5) json_out(['error' => 'För många försök. Vänta 15 minuter och försök igen.'], 429);
    login_attempts(true, 'forgot');
    send_reset_link((string)(json_in()['email'] ?? ''));
    json_out(['ok' => true]);
  }

  if ($action === 'reset') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Fel metod'], 405);
    if (login_attempts(null, 'reset') >= 5) json_out(['error' => 'För många försök. Vänta 15 minuter och försök igen.'], 429);
    $in = json_in();
    $user = user_for_reset((string)($in['token'] ?? ''));
    if (!$user) {
      login_attempts(true, 'reset');
      json_out(['error' => 'Länken är ogiltig eller för gammal. Begär en ny via "Glömt lösenord".'], 400);
    }
    set_password($user, (string)($in['password'] ?? ''));
    json_out(['ok' => true]);
  }

  // Första inloggningen: nytt lösenord krävs innan redigeringsläget öppnas.
  if ($action === 'change_password') {
    $user = pending_user() ?? current_user();
    if (!$user) json_out(['error' => 'Du är inte inloggad.'], 401);
    require_write();
    set_password($user, (string)(json_in()['password'] ?? ''));
    session_regenerate_id(true);
    unset($_SESSION['must_change']);
    json_out(['ok' => true]);
  }

  // Utloggning ska fungera även för den som ännu inte bytt sitt första lösenord.
  if ($action === 'logout') {
    if (pending_user() ?? current_user()) require_write();
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true]);
  }

  if (!current_user()) json_out(['error' => 'Du är inte inloggad.'], 401);

  if ($action === 'load') {
    json_out(load_site());
  }

  if ($action === 'history') {
    json_out(['items' => list_history()]);
  }

  // Modulerna anropar själva require_write() för det som ändrar data.
  foreach (modules() as $mod) if (isset($mod['api'])) $mod['api']($action);

  // Resten ändrar data.
  require_write();

  if ($action === 'save') {
    $in = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $version = with_site_lock(function () use ($in) {
      $cur = load_site();
      if (($in['base'] ?? '') !== $cur['version'])
        json_out(['error' => 'Någon annan har sparat ändringar sedan du öppnade sidan. Ladda om och gör om dina ändringar.'], 409);
      validate_change($cur['site'], $in['site'] ?? null);
      backup_current(current_user());
      return write_site($in['site']);
    });
    ensure_youtube_thumbs($in['site']);
    json_out(['ok' => true, 'version' => $version]);
  }

  if ($action === 'restore') {
    $site = json_decode(file_get_contents(history_file((string)(json_in()['id'] ?? ''))), true, 512, JSON_THROW_ON_ERROR);
    $version = with_site_lock(function () use ($site) {
      backup_current(current_user());
      return write_site($site);
    });
    ensure_youtube_thumbs($site);
    json_out(['ok' => true, 'version' => $version, 'site' => $site]);
  }

  if ($action === 'upload') {
    require __DIR__ . '/upload.php';
    json_out(['ok' => true, 'path' => handle_upload($_FILES['file'] ?? null)]);
  }

  json_out(['error' => 'Okänd åtgärd'], 404);
} catch (InvalidArgumentException $e) {
  json_out(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
  error_log('[cms] ' . $e);
  json_out(['error' => 'Något gick fel på servern.'], 500);
}
