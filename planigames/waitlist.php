<?php
/**
 * PLANIGAMES — Beta-Warteliste mit Empfehlungslink (flat-file).
 * GET                 → { total }   (öffentliche Gesamtzahl)
 * GET ?status=TOKEN   → { ok, position, referrals, code, total }  (eigener Stand)
 * POST (email[, ref]) → Eintragen; gibt Position + eigenen Empfehlungscode + Token zurück.
 * Speicherung: data/waitlist.json (per data/.htaccess nicht abrufbar).
 */
require __DIR__ . '/admin/lib.php';
header('Content-Type: application/json; charset=utf-8');
$file = PG_DATA_DIR . '/waitlist.json';

/* Position = sortiert nach (Empfehlungen absteigend, Anmeldedatum aufsteigend). */
function pg_wl_referrals($list, $code){
  if ($code === '') return 0;
  $n = 0; foreach ($list as $e) if (($e['ref'] ?? '') === $code) $n++;
  return $n;
}
function pg_wl_ranked($list){
  $rows = array_values(array_filter($list, fn($e) => is_array($e) && !empty($e['email'])));
  usort($rows, function ($a, $b) use ($list) {
    $ra = pg_wl_referrals($list, $a['code'] ?? '');
    $rb = pg_wl_referrals($list, $b['code'] ?? '');
    if ($ra !== $rb) return $rb <=> $ra;
    return strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
  });
  return $rows;
}
function pg_wl_position($list, $code){
  $i = 0; foreach (pg_wl_ranked($list) as $e) { $i++; if (($e['code'] ?? '') === $code) return $i; }
  return 0;
}

$list = pg_load_json($file); if (!is_array($list)) $list = [];
$total = count(array_filter($list, fn($e) => is_array($e) && !empty($e['email'])));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $token = (string) ($_GET['status'] ?? '');
  if ($token !== '') {
    foreach ($list as $e) {
      if (($e['token'] ?? '') === $token) {
        echo json_encode(['ok' => true, 'position' => pg_wl_position($list, $e['code']),
          'referrals' => pg_wl_referrals($list, $e['code']), 'code' => $e['code'], 'total' => $total],
          JSON_UNESCAPED_SLASHES);
        exit;
      }
    }
    echo json_encode(['ok' => false, 'total' => $total]); exit;
  }
  echo json_encode(['total' => $total]); exit;
}

/* ---- Eintragen ---- */
$studio = pg_load_json(PG_DATA_DIR . '/studio.json');
if (isset($studio['waitlist']['enabled']) && !$studio['waitlist']['enabled']) { echo '{"error":"Die Warteliste ist derzeit geschlossen."}'; exit; }
if ((string) ($_POST['website'] ?? '') !== '') { echo '{"ok":true}'; exit; }   // Honeypot
$email = trim((string) ($_POST['email'] ?? ''));
$ref   = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($_POST['ref'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo '{"error":"Bitte gib eine gültige E-Mail-Adresse ein."}'; exit; }
$email = mb_substr($email, 0, 180);

// Bereits eingetragen? → bestehenden Stand zurückgeben (idempotent)
foreach ($list as $e) {
  if (is_array($e) && isset($e['email']) && strcasecmp($e['email'], $email) === 0) {
    echo json_encode(['ok' => true, 'already' => true, 'position' => pg_wl_position($list, $e['code']),
      'referrals' => pg_wl_referrals($list, $e['code']), 'code' => $e['code'], 'token' => $e['token'] ?? '', 'total' => $total],
      JSON_UNESCAPED_SLASHES);
    exit;
  }
}

// Rate-Limit pro IP
$ipHash = pg_ip_hash();
foreach ($list as $e) {
  if (($e['ip'] ?? '') === $ipHash && (time() - strtotime((string) ($e['date'] ?? '0'))) < 20) {
    echo '{"error":"Bitte kurz warten und erneut versuchen."}'; exit;
  }
}

// Eindeutigen Empfehlungscode erzeugen
do { $code = bin2hex(random_bytes(4)); $dup = false; foreach ($list as $e) if (($e['code'] ?? '') === $code) $dup = true; } while ($dup);
// Ref nur akzeptieren, wenn er existiert und nicht der eigene Code ist
$refValid = $ref !== '' && $ref !== $code;
if ($refValid) { $found = false; foreach ($list as $e) if (($e['code'] ?? '') === $ref) $found = true; $refValid = $found; }

$token = bin2hex(random_bytes(16));
$list[] = ['email' => $email, 'code' => $code, 'ref' => $refValid ? $ref : '',
  'token' => $token, 'date' => date('c'), 'ip' => $ipHash];
pg_save_json($file, $list);

$total++;
echo json_encode(['ok' => true, 'position' => pg_wl_position($list, $code),
  'referrals' => 0, 'code' => $code, 'token' => $token, 'total' => $total], JSON_UNESCAPED_SLASHES);
