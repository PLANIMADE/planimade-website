<?php
/**
 * PLANIGAMES — Newsletter-Anmeldung (läuft auf All-Inkl, ohne Netlify).
 * Nimmt die E-Mail entgegen, speichert sie in data/subscribers.json und
 * schickt optional eine Benachrichtigung an die Studio-Adresse.
 * Die Abonnentenliste ist per data/.htaccess vor dem Web geschützt.
 */
$DATA = __DIR__ . '/data';

/* ---- Abmeldung (GET, aus dem Newsletter-Footer-Link) ---- */
if (($_GET['action'] ?? '') === 'unsubscribe') {
  require __DIR__ . '/admin/lib.php';
  $email = trim($_GET['e'] ?? '');
  $token = $_GET['t'] ?? '';
  header('Content-Type: text/html; charset=utf-8');
  $valid = filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(pg_unsub_token($email), (string)$token);
  $msg = 'Dieser Abmeldelink ist ungültig oder abgelaufen.';
  if ($valid) {
    $file = $DATA . '/subscribers.json';
    $list = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    $new = array_values(array_filter($list, fn($r) => strcasecmp($r['email'] ?? '', $email) !== 0));
    @file_put_contents($file, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    $msg = 'Du wurdest erfolgreich abgemeldet. Schade, dass du gehst! 🧡';
  }
  echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
     . '<title>Newsletter abmelden · PLANIGAMES</title><meta name="robots" content="noindex">'
     . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#050505;color:#ececf0;'
     . 'font-family:system-ui,Arial,sans-serif;text-align:center;padding:2rem}'
     . '.c{max-width:430px}h1{font-size:1.4rem}a{display:inline-block;margin-top:1.5rem;color:#ff8a2b;text-decoration:none;'
     . 'border:1px solid rgba(255,255,255,.2);padding:.7rem 1.4rem;border-radius:999px}</style></head><body><div class="c">'
     . '<div style="font-size:2.4rem;margin-bottom:1rem">📭</div><h1>' . htmlspecialchars($msg, ENT_QUOTES) . '</h1>'
     . '<a href="index.html">Zur Startseite</a></div></body></html>';
  exit;
}

header('Content-Type: application/json; charset=utf-8');

$studio = is_file($DATA . '/studio.json') ? json_decode(file_get_contents($DATA . '/studio.json'), true) : [];
$cfg = $studio['newsletter'] ?? [];

// Newsletter deaktiviert?
if (isset($cfg['enabled']) && !$cfg['enabled']) {
  http_response_code(403); echo json_encode(['error' => 'Anmeldung derzeit nicht möglich.']); exit;
}

// Honeypot (Bots füllen versteckte Felder aus)
if (!empty($_POST['website'])) { echo json_encode(['ok' => true]); exit; }

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422); echo json_encode(['error' => 'Bitte gib eine gültige E-Mail-Adresse ein.']); exit;
}
$email = mb_substr($email, 0, 180);

$file = $DATA . '/subscribers.json';
$fp = @fopen($file, 'c+');
if ($fp === false) {
  // Verzeichnis evtl. nicht schreibbar
  http_response_code(500); echo json_encode(['error' => 'Speichern nicht möglich. Bitte später erneut versuchen.']); exit;
}
flock($fp, LOCK_EX);
$raw = stream_get_contents($fp);
$list = json_decode($raw ?: '[]', true);
if (!is_array($list)) $list = [];

$exists = false;
foreach ($list as $row) {
  if (isset($row['email']) && strcasecmp($row['email'], $email) === 0) { $exists = true; break; }
}
if (!$exists) {
  $list[] = [
    'email'  => $email,
    'date'   => date('c'),
    'source' => mb_substr(trim($_POST['source'] ?? ''), 0, 60),
  ];
  ftruncate($fp, 0); rewind($fp);
  fwrite($fp, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

  // Optionale Benachrichtigung ans Studio
  $notify = $cfg['notifyEmail'] ?? ($studio['email'] ?? '');
  if ($notify && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
    $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'planigames.de');
    @mail($notify, 'Neue Newsletter-Anmeldung', "Neue Anmeldung: {$email}\nZeit: " . date('c'),
          "From: no-reply@{$host}\r\nContent-Type: text/plain; charset=utf-8");
  }
}
flock($fp, LOCK_UN); fclose($fp);

echo json_encode([
  'ok'      => true,
  'message' => $cfg['successMessage'] ?? 'Danke! Du bist dabei. 🧡',
]);
