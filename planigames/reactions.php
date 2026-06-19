<?php
/**
 * PLANIGAMES — Emoji-Reaktionen für Devlog-Beiträge (flat-file, ohne Login).
 * GET ?slug=…           -> aktuelle Zähler als JSON
 * POST slug, add, remove -> Zähler anpassen, neue Zähler zurück
 * Keine Cookies, keine personenbezogenen Daten. Speichert data/reactions.json.
 */
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/data/reactions.json';
$ALLOWED = ['👍', '🔥', '💜', '😂', '🎉'];
$slug = preg_replace('/[^a-z0-9\-_]/i', '', substr((string) ($_REQUEST['slug'] ?? ''), 0, 80));
if ($slug === '') { echo '{}'; exit; }

function pg_react_load($file){ $d = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : []; return is_array($d) ? $d : []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $add = (string) ($_POST['add'] ?? '');
  $remove = (string) ($_POST['remove'] ?? '');
  $fp = @fopen($file, 'c+');
  if ($fp) {
    flock($fp, LOCK_EX);
    $all = json_decode(stream_get_contents($fp) ?: '{}', true);
    if (!is_array($all)) $all = [];
    $cur = isset($all[$slug]) && is_array($all[$slug]) ? $all[$slug] : [];
    if (in_array($remove, $ALLOWED, true)) $cur[$remove] = max(0, (int) ($cur[$remove] ?? 0) - 1);
    if (in_array($add, $ALLOWED, true))    $cur[$add]    = (int) ($cur[$add] ?? 0) + 1;
    $cur = array_filter($cur, fn($v) => $v > 0);
    $all[$slug] = $cur;
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);
    echo json_encode((object) $cur); exit;
  }
  echo '{}'; exit;
}

$all = pg_react_load($file);
echo json_encode((object) (isset($all[$slug]) && is_array($all[$slug]) ? $all[$slug] : []));
