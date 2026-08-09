<?php

/**
 * Einrichtung über den Browser – für den Fall, dass kein SSH-Zugang genutzt wird.
 *
 * Aufruf: https://deine-domain.de/api/scripts/setup.web.php
 *
 * Sicherheitsnetz, doppelt: Sobald ein Zugang existiert, verweigert das
 * Skript den Dienst – und nach erfolgreicher Einrichtung löscht es sich
 * selbst. Damit bleibt kein offener Einrichtungsdialog im Netz stehen, ohne
 * dass jemand daran denken muss.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;

$db = new Database($config);
$hasUser = (int) $db->value('SELECT COUNT(*) FROM users') > 0;

$done = false;
$error = null;
$removable = false;
$log = [];

if (!$hasUser && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $result = portfolio_setup(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            isset($_POST['demo'])
        );
        $log = $result['log'];
        $done = true;

        // Aufräumen: Die Datei hat ihren Zweck erfüllt. Das Löschen passiert
        // bewusst erst nach der Antwort – verschwindet die Datei mitten im
        // Aufruf, liefern manche Server statt der Bestätigung einen 404.
        $removable = is_writable(__FILE__);
        if ($removable) {
            register_shutdown_function(static function (): void {
                @unlink(__FILE__);
            });
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Portfolio einrichten</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 2rem;
         background: #08080b; color: #e4e4e7;
         font: 15px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
  .card { width: 100%; max-width: 30rem; background: #101014; border: 1px solid #26262e;
          border-radius: 1rem; padding: 2rem; }
  h1 { margin: 0 0 .25rem; font-size: 1.25rem; letter-spacing: -0.02em; }
  p.lead { margin: 0 0 1.5rem; color: #a1a1aa; font-size: .875rem; }
  label { display: block; margin: 1rem 0 .375rem; font-size: .8125rem; color: #a1a1aa; }
  input[type=email], input[type=password] { width: 100%; padding: .7rem .8rem; border-radius: .5rem;
          border: 1px solid #2e2e38; background: #16161c; color: #fff; font-size: .9375rem; }
  input:focus { outline: 2px solid #a855f7; outline-offset: 1px; border-color: transparent; }
  .check { display: flex; gap: .5rem; align-items: flex-start; margin-top: 1.25rem; font-size: .8125rem; color: #a1a1aa; }
  button { width: 100%; margin-top: 1.5rem; padding: .8rem; border: 0; border-radius: .5rem;
           background: #a855f7; color: #fff; font-weight: 600; font-size: .9375rem; cursor: pointer; }
  button:hover { background: #9333ea; }
  .msg { padding: .8rem 1rem; border-radius: .5rem; font-size: .875rem; margin-bottom: 1rem; }
  .err { background: #2a1215; border: 1px solid #7f1d1d; color: #fca5a5; }
  .ok  { background: #0f2019; border: 1px solid #14532d; color: #86efac; }
  ul { padding-left: 1.1rem; margin: .5rem 0 0; font-size: .8125rem; color: #a1a1aa; }
  code { background: #1c1c22; padding: .1rem .35rem; border-radius: .25rem; font-size: .8125rem; }
</style>
</head>
<body>
<div class="card">
  <h1>Portfolio einrichten</h1>
  <p class="lead">Legt Datenbank, Admin-Zugang und Grundeinstellungen an.</p>

  <?php if ($error !== null): ?>
    <div class="msg err"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
  <?php endif; ?>

  <?php if ($hasUser && !$done): ?>
    <div class="msg ok">Die Einrichtung ist bereits abgeschlossen.</div>
    <p class="lead">Bitte diese Datei jetzt löschen: <code>api/scripts/setup.web.php</code><br>
       Danach geht es weiter unter <a href="/admin/" style="color:#a855f7">/admin/</a>.</p>
  <?php elseif ($done): ?>
    <div class="msg ok">Einrichtung abgeschlossen.</div>
    <ul><?php foreach ($log as $line): ?><li><?= htmlspecialchars($line, ENT_QUOTES) ?></li><?php endforeach; ?></ul>
    <?php if ($removable): ?>
      <p class="lead" style="margin-top:1.25rem">
        Diese Einrichtungsseite hat sich selbst gelöscht – es bleibt nichts offen stehen.<br>
        Weiter geht es unter <a href="/admin/" style="color:#a855f7">/admin/</a>.
      </p>
    <?php else: ?>
      <p class="lead" style="margin-top:1.25rem">
        <strong style="color:#fca5a5">Bitte von Hand löschen:</strong> <code>api/scripts/setup.web.php</code>
        – das Selbstlöschen hat nicht geklappt, vermutlich wegen der Dateirechte.<br>
        Danach einloggen unter <a href="/admin/" style="color:#a855f7">/admin/</a>.
      </p>
    <?php endif; ?>
  <?php else: ?>
    <form method="post">
      <label for="email">E-Mail (Login)</label>
      <input id="email" type="email" name="email" required autocomplete="username">

      <label for="password">Passwort (mind. 10 Zeichen)</label>
      <input id="password" type="password" name="password" required minlength="10" autocomplete="new-password">

      <label class="check"><input type="checkbox" name="demo" checked> Beispielprojekte anlegen (später im Dashboard löschbar)</label>

      <button type="submit">Einrichten</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
