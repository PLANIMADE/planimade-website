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
$file = PG_DATA_DIR . '/waitlist.json';

/* ---- Abmelden aus einer Wartelisten-Mail (signierter Link, nur dieser Eintrag) ---- */
if (($_GET['action'] ?? '') === 'leave') {
  $email = trim((string) ($_GET['e'] ?? '')); $sc = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['s'] ?? '')));
  $ok = filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(pg_wl_leave_token($email, $sc), (string) ($_GET['t'] ?? ''));
  $msg = 'Dieser Abmeldelink ist ungültig.';
  if ($ok) {
    $list = pg_load_json($file); $n = 0;
    if (is_array($list)) {
      $keep = [];
      foreach ($list as $e) {
        if (is_array($e) && strcasecmp(trim((string) ($e['email'] ?? '')), $email) === 0 && preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($e['scope'] ?? ''))) === $sc) { $n++; continue; }
        $keep[] = $e;
      }
      if ($n) pg_save_json($file, $keep);
    }
    $msg = $n ? 'Du wurdest von der Warteliste abgemeldet. Danke für dein Interesse! 🧡' : 'Diese Adresse steht nicht (mehr) auf der Warteliste.';
  }
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Warteliste · PLANIGAMES</title><meta name="robots" content="noindex">'
     . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#050505;color:#ececf0;font-family:system-ui,Arial,sans-serif;text-align:center;padding:2rem}.c{max-width:430px}h1{font-size:1.4rem}'
     . 'a{display:inline-block;margin-top:1.5rem;color:#ff8a2b;text-decoration:none;border:1px solid rgba(255,255,255,.2);padding:.7rem 1.4rem;border-radius:999px}</style></head>'
     . '<body><div class="c"><div style="font-size:2.4rem;margin-bottom:1rem">📭</div><h1>' . htmlspecialchars($msg, ENT_QUOTES) . '</h1><a href="index.html">Zur Startseite</a></div></body></html>';
  exit;
}

header('Content-Type: application/json; charset=utf-8');

function pg_wl_scope($raw){
  $s = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $raw));
  // umbenanntes Spiel (alte Adresse aus einer noch offenen Seite) → neue Adresse
  static $map = null;
  if ($map === null) { $r = json_decode((string) @file_get_contents(__DIR__ . '/data/redirects.json'), true); $map = is_array($r['games'] ?? null) ? $r['games'] : []; }
  return ($s !== '' && isset($map[$s]) && is_string($map[$s])) ? $map[$s] : $s;
}
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
        echo json_encode(['ok' => true, 'position' => pg_wl_position($scoped, $e['code'] ?? ''),
          'referrals' => pg_wl_referrals($scoped, $e['code'] ?? ''), 'code' => $e['code'] ?? '', 'total' => $total],
          JSON_UNESCAPED_SLASHES);
        exit;
      }
    }
    echo json_encode(['ok' => false, 'total' => $total]); exit;
  }
  echo json_encode(['total' => $total]); exit;
}

/* ---- Eintragen ---- */
if ((string) ($_POST['website'] ?? '') !== '') { echo '{"ok":true}'; exit; }   // Honeypot zuerst (Bots erhalten still „ok")
// Die Warteliste gibt es ausschließlich als Spiel-Seiten-Block (scope=Spiel-Slug).
// Eine Startseiten-Variante (scope="") existiert nicht mehr.
if ($scope === '') { echo '{"error":"Die Warteliste ist nur auf den Spiel-Seiten verfügbar."}'; exit; }
$email = trim((string) ($_POST['email'] ?? ''));
$ref   = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($_POST['ref'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo '{"error":"Bitte gib eine gültige E-Mail-Adresse ein."}'; exit; }
$email = mb_substr($email, 0, 180);

// Bereits in DIESEM Bereich eingetragen? → bestehenden Stand zurückgeben (idempotent)
foreach ($scoped as $e) {
  if (isset($e['email']) && strcasecmp($e['email'], $email) === 0) {
    echo json_encode(['ok' => true, 'already' => true, 'position' => pg_wl_position($scoped, $e['code'] ?? ''),
      'referrals' => pg_wl_referrals($scoped, $e['code'] ?? ''), 'code' => $e['code'] ?? '', 'token' => $e['token'] ?? '', 'total' => $total],
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
