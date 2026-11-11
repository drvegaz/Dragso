<?php
// Hjälpsida för att skapa en lösenordshash till config.php (kräver inloggning).
declare(strict_types=1);
require __DIR__ . '/lib.php';
start_session();
if (!current_user()) { header('Location: index.php'); exit; }
$pw = $_POST['pw'] ?? '';
$hash = strlen($pw) >= 10 ? password_hash($pw, PASSWORD_DEFAULT) : null;
?><!DOCTYPE html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Skapa lösenordshash</title>
<body style="font:16px/1.5 system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;color:#404040">
<h1 style="font-size:20px">Skapa lösenordshash</h1>
<p>Skriv ett nytt lösenord (minst 10 tecken). Klistra sedan in hashen i <code>cms/site/config.php</code> under rätt användarnamn.</p>
<form method="post"><input name="pw" type="password" minlength="10" required style="height:40px;width:100%;padding:0 10px;font:inherit"> <button style="margin-top:8px;height:40px;padding:0 16px">Skapa</button></form>
<?php if ($hash): ?><p><strong>Hash:</strong></p><pre style="background:#F5F3EF;padding:12px;white-space:pre-wrap;word-break:break-all"><?= htmlspecialchars($hash) ?></pre><?php endif; ?>
