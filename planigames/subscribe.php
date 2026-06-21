<?php
/**
 * PLANIGAMES — Newsletter-Anmeldung (All-Inkl, ohne DB) mit Double-Opt-In.
 * Anmeldung -> Bestätigungsmail -> Klick bestätigt -> Willkommensmail.
 * Liste in data/subscribers.json (per data/.htaccess web-geschützt).
 */
require __DIR__ . '/admin/lib.php';
$DATA = __DIR__ . '/data';
$file = $DATA . '/subscribers.json';

function pg_subs_load($file){ $l = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : []; return is_array($l) ? $l : []; }
function pg_subs_save($file, $list){ @file_put_contents($file, json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX); }
function pg_subs_page($emoji, $msg){
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
     . '<title>Newsletter · PLANIGAMES</title><meta name="robots" content="noindex">'
     . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#050505;color:#ececf0;'
     . 'font-family:system-ui,Arial,sans-serif;text-align:center;padding:2rem}.c{max-width:430px}h1{font-size:1.4rem}'
     . 'a{display:inline-block;margin-top:1.5rem;color:#ff8a2b;text-decoration:none;border:1px solid rgba(255,255,255,.2);padding:.7rem 1.4rem;border-radius:999px}</style>'
     . '</head><body><div class="c"><div style="font-size:2.4rem;margin-bottom:1rem">' . $emoji . '</div>'
     . '<h1>' . htmlspecialchars($msg, ENT_QUOTES) . '</h1><a href="index.html">Zur Startseite</a></div></body></html>';
  exit;
}

$action = $_GET['action'] ?? '';

/* ---- Abmeldung ---- */
if ($action === 'unsubscribe') {
  $email = trim($_GET['e'] ?? ''); $token = $_GET['t'] ?? '';
  $valid = filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(pg_unsub_token($email), (string) $token);
  $msg = 'Dieser Abmeldelink ist ungültig oder abgelaufen.';
  if ($valid) {
    $list = pg_subs_load($file);
    pg_subs_save($file, array_filter($list, fn($r) => strcasecmp($r['email'] ?? '', $email) !== 0));
    $msg = 'Du wurdest erfolgreich abgemeldet. Schade, dass du gehst! 🧡';
  }
  pg_subs_page('📭', $msg);
}

/* ---- Bestätigung (Double-Opt-In) ---- */
if ($action === 'confirm') {
  $email = trim($_GET['e'] ?? ''); $token = $_GET['t'] ?? '';
  $valid = filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(pg_confirm_token($email), (string) $token);
  $msg = 'Dieser Bestätigungslink ist ungültig.';
  if ($valid) {
    $list = pg_subs_load($file); $found = false; $already = false;
    foreach ($list as &$r) {
      if (strcasecmp($r['email'] ?? '', $email) === 0) {
        $found = true; if (!empty($r['confirmed'])) $already = true;
        $r['confirmed'] = true; $r['confirmedAt'] = $r['confirmedAt'] ?? date('c');
      }
    }
    unset($r);
    if ($found) {
      pg_subs_save($file, $list);
      if (!$already) pg_send_welcome_mail($email);
      $msg = 'Super – deine Anmeldung ist bestätigt! 🧡 Willkommen an Bord.';
    } else {
      $msg = 'Diese Adresse ist nicht (mehr) vorgemerkt.';
    }
  }
  pg_subs_page('✅', $msg);
}

/* ---- Anmeldung (POST, JSON) ---- */
header('Content-Type: application/json; charset=utf-8');
$studio = pg_load_json($DATA . '/studio.json');
$cfg = $studio['newsletter'] ?? [];
if (isset($cfg['enabled']) && !$cfg['enabled']) { http_response_code(403); echo json_encode(['error' => 'Anmeldung derzeit nicht möglich.']); exit; }
if (!empty($_POST['website'])) { echo json_encode(['ok' => true]); exit; }  // Honeypot

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo json_encode(['error' => 'Bitte gib eine gültige E-Mail-Adresse ein.']); exit; }
$email = mb_substr($email, 0, 180);
$doubleOptIn = ($cfg['doubleOptIn'] ?? true) !== false;

$fp = @fopen($file, 'c+');
if ($fp === false) { http_response_code(500); echo json_encode(['error' => 'Speichern nicht möglich. Bitte später erneut versuchen.']); exit; }
flock($fp, LOCK_EX);
$list = json_decode(stream_get_contents($fp) ?: '[]', true);
if (!is_array($list)) $list = [];

$existing = null;
foreach ($list as $i => $row) if (isset($row['email']) && strcasecmp($row['email'], $email) === 0) { $existing = $i; break; }

$message = $cfg['successMessage'] ?? 'Danke! Du bist dabei. 🧡';
$doSend = null; // 'confirm' | 'welcome' | null

if ($existing !== null) {
  if (!empty($list[$existing]['confirmed'])) {
    $message = 'Du bist bereits angemeldet – schön, dass du dabei bist! 🧡';
  } else {
    $doSend = $doubleOptIn ? 'confirm' : 'welcome';
    if (!$doubleOptIn) { $list[$existing]['confirmed'] = true; }
    $message = $doubleOptIn ? 'Fast geschafft! Wir haben dir den Bestätigungslink (erneut) geschickt. 📩' : $message;
  }
} else {
  $list[] = ['email' => $email, 'date' => date('c'), 'source' => mb_substr(trim($_POST['source'] ?? ''), 0, 60), 'confirmed' => !$doubleOptIn];
  $doSend = $doubleOptIn ? 'confirm' : 'welcome';
  $message = $doubleOptIn ? 'Fast geschafft! Bitte bestätige den Link in deiner E-Mail. 📩' : $message;

  // Benachrichtigung ans Studio
  $notify = ($cfg['notifyEmail'] ?? '') ?: ($studio['email'] ?? '');
  if ($notify && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
    $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'planigames.com');
    @mail($notify, 'Neue Newsletter-Anmeldung', "Neue Anmeldung: {$email}\nZeit: " . date('c'), "From: no-reply@{$host}\r\nContent-Type: text/plain; charset=utf-8");
  }
}

ftruncate($fp, 0); rewind($fp);
fwrite($fp, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
flock($fp, LOCK_UN); fclose($fp);

if ($doSend === 'confirm') pg_send_confirm_mail($email);
elseif ($doSend === 'welcome') pg_send_welcome_mail($email);

echo json_encode(['ok' => true, 'message' => $message]);
