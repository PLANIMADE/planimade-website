<?php
/**
 * PLANIGAMES — Vorschlagsbox (flat-file, moderiert, mit Upvotes).
 * GET            → freigegebene Vorschläge (nach Stimmen sortiert).
 * POST (text)    → neuer Vorschlag, landet unmoderiert im Admin.
 * POST (upvote)  → Stimme für einen Vorschlag (Mehrfach-Schutz im Browser).
 * Speicherung: data/suggestions.json (per data/.htaccess nicht abrufbar).
 */
require __DIR__ . '/admin/lib.php';
header('Content-Type: application/json; charset=utf-8');
$file = PG_DATA_DIR . '/suggestions.json';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $out = [];
  foreach ((array) pg_load_json($file) as $s) {
    if (!is_array($s) || empty($s['approved'])) continue;
    $out[] = ['id' => $s['id'] ?? '', 'text' => $s['text'] ?? '', 'votes' => (int) ($s['votes'] ?? 0), 'status' => $s['status'] ?? 'open'];
  }
  usort($out, fn($a, $b) => $b['votes'] <=> $a['votes']);
  echo json_encode(['suggestions' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$list = pg_load_json($file); if (!is_array($list)) $list = [];

// ---- Upvote ----
if (isset($_POST['upvote'])) {
  $id = (string) $_POST['upvote'];
  $ok = false;
  foreach ($list as &$s) if (($s['id'] ?? '') === $id && !empty($s['approved'])) { $s['votes'] = (int) ($s['votes'] ?? 0) + 1; $ok = true; }
  unset($s);
  if ($ok) pg_save_json($file, $list);
  echo json_encode(['ok' => $ok]);
  exit;
}

// ---- Neuer Vorschlag ----
$studio = pg_load_json(PG_DATA_DIR . '/studio.json');
if (isset($studio['suggestions']['enabled']) && !$studio['suggestions']['enabled']) { echo '{"error":"Vorschläge sind deaktiviert."}'; exit; }
$text = trim((string) ($_POST['text'] ?? ''));
$hp   = (string) ($_POST['website'] ?? '');
if ($hp !== '') { echo '{"ok":true}'; exit; }
if (mb_strlen($text) < 4) { echo '{"error":"Bitte einen etwas längeren Vorschlag eingeben."}'; exit; }

$ipHash = pg_ip_hash();
foreach ($list as $s) {
  if (($s['ip'] ?? '') === $ipHash && (time() - strtotime((string) ($s['date'] ?? '0'))) < 30) {
    echo '{"error":"Bitte kurz warten, bevor du erneut einreichst."}'; exit;
  }
}
$list[] = ['id' => bin2hex(random_bytes(5)), 'text' => mb_substr(strip_tags($text), 0, 200),
  'votes' => 1, 'status' => 'open', 'approved' => false, 'date' => date('c'), 'ip' => $ipHash];
pg_save_json($file, $list);
echo '{"ok":true}';
