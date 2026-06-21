<?php
/**
 * PLANIGAMES — Beta-Warteliste mit Empfehlungslink (flat-file).
 * Jeder Bereich (scope) hat seine eigene Warteliste & sein eigenes Ranking:
 *   scope = Spiel-Slug (Gameseite) oder "" (Startseite/global).
 * GET [?scope=…]              → { total }   (Gesamtzahl im Bereich)
 * GET ?status=TOKEN[&scope=…] → { ok, position, referrals, code, total }  (eigener Stand)
 * POST (email[, ref][, scope])→ Eintragen; gibt Position + Empfehlungscode + Token zurück.
 * Speicherung: data/waitlist.json (per data/.htaccess nicht abrufbar).
 */
require __DIR__ . '/admin/lib.php';
header('Content-Type: application/json; charset=utf-8');
$file = PG_DATA_DIR . '/waitlist.json';

function pg_wl_scope($raw){ return preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $raw)); }
/* Nur Einträge desselben Bereichs (für Ranking/Zählung). */
function pg_wl_scoped($list, $scope){
  return array_values(array_filter($list, fn($e) => is_array($e) && !empty($e['email']) && pg_wl_scope($e['scope'] ?? '') === $scope));
}
function pg_wl_referrals($scoped, $code){
  if ($code === '') return 0;
  $n = 0; foreach ($scoped as $e) if (($e['ref'] ?? '') === $code) $n++;
  return $n;
}
/* Reihenfolge: Empfehlungen absteigend, dann Anmeldedatum aufsteigend. */
function pg_wl_ranked($scoped){
  usort($scoped, function ($a, $b) use ($scoped) {
    $ra = pg_wl_referrals($scoped, $a['code'] ?? '');
    $rb = pg_wl_referrals($scoped, $b['code'] ?? '');
    if ($ra !== $rb) return $rb <=> $ra;
    return strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
  });
  return $scoped;
}
function pg_wl_position($scoped, $code){
  $i = 0; foreach (pg_wl_ranked($scoped) as $e) { $i++; if (($e['code'] ?? '') === $code) return $i; }
  return 0;
}

$list  = pg_load_json($file); if (!is_array($list)) $list = [];
$scope = pg_wl_scope($_REQUEST['scope'] ?? '');
$scoped = pg_wl_scoped($list, $scope);
$total = count($scoped);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $token = (string) ($_GET['status'] ?? '');
  if ($token !== '') {
    foreach ($scoped as $e) {
      if (($e['token'] ?? '') === $token) {
        echo json_encode(['ok' => true, 'position' => pg_wl_position($scoped, $e['code']),
          'referrals' => pg_wl_referrals($scoped, $e['code']), 'code' => $e['code'], 'total' => $total],
          JSON_UNESCAPED_SLASHES);
        exit;
      }
    }
    echo json_encode(['ok' => false, 'total' => $total]); exit;
  }
  echo json_encode(['total' => $total]); exit;
}

/* ---- Eintragen ---- */
// Die Warteliste gibt es ausschließlich als Spiel-Seiten-Block (scope=Spiel-Slug).
// Eine Startseiten-Variante (scope="") existiert nicht mehr.
if ($scope === '') { echo '{"error":"Die Warteliste ist nur auf den Spiel-Seiten verfügbar."}'; exit; }
if ((string) ($_POST['website'] ?? '') !== '') { echo '{"ok":true}'; exit; }   // Honeypot
$email = trim((string) ($_POST['email'] ?? ''));
$ref   = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($_POST['ref'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo '{"error":"Bitte gib eine gültige E-Mail-Adresse ein."}'; exit; }
$email = mb_substr($email, 0, 180);

// Bereits in DIESEM Bereich eingetragen? → bestehenden Stand zurückgeben (idempotent)
foreach ($scoped as $e) {
  if (isset($e['email']) && strcasecmp($e['email'], $email) === 0) {
    echo json_encode(['ok' => true, 'already' => true, 'position' => pg_wl_position($scoped, $e['code']),
      'referrals' => pg_wl_referrals($scoped, $e['code']), 'code' => $e['code'], 'token' => $e['token'] ?? '', 'total' => $total],
      JSON_UNESCAPED_SLASHES);
    exit;
  }
}

// Rate-Limit pro IP (über alle Bereiche)
$ipHash = pg_ip_hash();
foreach ($list as $e) {
  if (($e['ip'] ?? '') === $ipHash && (time() - strtotime((string) ($e['date'] ?? '0'))) < 20) {
    echo '{"error":"Bitte kurz warten und erneut versuchen."}'; exit;
  }
}

// Eindeutigen Empfehlungscode erzeugen (global eindeutig)
do { $code = bin2hex(random_bytes(4)); $dup = false; foreach ($list as $e) if (($e['code'] ?? '') === $code) $dup = true; } while ($dup);
// Ref nur akzeptieren, wenn er im selben Bereich existiert und nicht der eigene Code ist
$refValid = $ref !== '' && $ref !== $code;
if ($refValid) { $found = false; foreach ($scoped as $e) if (($e['code'] ?? '') === $ref) $found = true; $refValid = $found; }

$token = bin2hex(random_bytes(16));
$entry = ['email' => $email, 'code' => $code, 'ref' => $refValid ? $ref : '',
  'scope' => $scope, 'token' => $token, 'date' => date('c'), 'ip' => $ipHash];
$list[] = $entry;
pg_save_json($file, $list);

$scoped[] = $entry;
$total = count($scoped);
echo json_encode(['ok' => true, 'position' => pg_wl_position($scoped, $code),
  'referrals' => 0, 'code' => $code, 'token' => $token, 'total' => $total], JSON_UNESCAPED_SLASHES);
