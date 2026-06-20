<?php
/**
 * PLANIGAMES — Devlog-Kommentare (flat-file, moderiert).
 * GET  ?slug=…  → liefert NUR freigegebene Kommentare (ohne IP/E-Mail).
 * POST          → neuer Kommentar, landet unmoderiert (approved=false) im Admin.
 * Speicherung: data/comments.json (per data/.htaccess nicht direkt abrufbar).
 */
require __DIR__ . '/admin/lib.php';
header('Content-Type: application/json; charset=utf-8');

$file = PG_DATA_DIR . '/comments.json';
$slug = preg_replace('/[^a-z0-9\-]/i', '', substr((string) ($_GET['slug'] ?? $_POST['slug'] ?? ''), 0, 80));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  // Anzahl freigegebener Kommentare je Beitrag (für die Devlog-Übersicht)
  if (isset($_GET['counts'])) {
    $counts = [];
    foreach ((array) pg_load_json($file) as $c) {
      if (!is_array($c) || empty($c['approved'])) continue;
      $sl = $c['slug'] ?? ''; if ($sl !== '') $counts[$sl] = ($counts[$sl] ?? 0) + 1;
    }
    echo json_encode(['counts' => (object) $counts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
  // Öffentlich: nur freigegebene Kommentare dieses Beitrags, ohne personenbez. Felder
  $out = [];
  foreach ((array) pg_load_json($file) as $c) {
    if (!is_array($c) || empty($c['approved'])) continue;
    if ($slug !== '' && ($c['slug'] ?? '') !== $slug) continue;
    $out[] = ['name' => $c['name'] ?? '', 'body' => $c['body'] ?? '', 'date' => $c['date'] ?? ''];
  }
  echo json_encode(['comments' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// ---- Neuer Kommentar ----
// Kommentare global aktivierbar (Standard: an), per studio.comments.enabled
$studio = pg_load_json(PG_DATA_DIR . '/studio.json');
if (isset($studio['comments']['enabled']) && !$studio['comments']['enabled']) { echo '{"error":"Kommentare sind deaktiviert."}'; exit; }

$name = trim((string) ($_POST['name'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));
$hp   = (string) ($_POST['website'] ?? ''); // Honeypot

if ($hp !== '') { echo '{"ok":true}'; exit; }                       // Bot: still „ok", aber verwerfen
if ($slug === '' || mb_strlen($name) < 2 || mb_strlen($body) < 2) { echo '{"error":"Bitte Name und Kommentar ausfüllen."}'; exit; }

// einfache Flut-Bremse: max. 1 Kommentar pro 20 s je IP
$ipHash = pg_ip_hash();
$list = pg_load_json($file); if (!is_array($list)) $list = [];
foreach ($list as $c) {
  if (($c['ip'] ?? '') === $ipHash && (time() - strtotime((string) ($c['date'] ?? '0'))) < 20) {
    echo '{"error":"Bitte kurz warten, bevor du erneut kommentierst."}'; exit;
  }
}

$list[] = [
  'id'       => bin2hex(random_bytes(5)),
  'slug'     => $slug,
  'name'     => mb_substr(strip_tags($name), 0, 60),
  'body'     => mb_substr(strip_tags($body), 0, 3000),
  'date'     => date('c'),
  'approved' => false,
  'ip'       => $ipHash,
];
pg_save_json($file, $list);
echo '{"ok":true}';
