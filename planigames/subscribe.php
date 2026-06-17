<?php
/**
 * PLANIGAMES — Newsletter-Anmeldung (läuft auf All-Inkl, ohne Netlify).
 * Nimmt die E-Mail entgegen, speichert sie in data/subscribers.json und
 * schickt optional eine Benachrichtigung an die Studio-Adresse.
 * Die Abonnentenliste ist per data/.htaccess vor dem Web geschützt.
 */
header('Content-Type: application/json; charset=utf-8');

$DATA = __DIR__ . '/data';
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
