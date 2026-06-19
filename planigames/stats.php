<?php
/**
 * PLANIGAMES — datenschutzfreundliche Statistik.
 * Zählt NUR aggregierte Seitenaufrufe (kein Cookie, keine IP, keine
 * personenbezogenen Daten). Daher ohne Einwilligung zulässig.
 * Speichert in data/stats.json.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$file = __DIR__ . '/data/stats.json';
$page = (string) ($_POST['p'] ?? $_GET['p'] ?? '');
$page = preg_replace('/[^a-z0-9:_\-]/i', '', substr($page, 0, 60));
if ($page === '') $page = 'unbekannt';

// Einfache Bot-Bremse: nur zählen, wenn kein offensichtlicher Bot-UA
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor/i', $ua)) { echo '{"ok":true}'; exit; }

$fp = @fopen($file, 'c+');
if ($fp) {
  flock($fp, LOCK_EX);
  $s = json_decode(stream_get_contents($fp) ?: '{}', true);
  if (!is_array($s)) $s = [];
  $day = date('Y-m-d');
  $s['total'] = ($s['total'] ?? 0) + 1;
  $s['days'][$day]  = ($s['days'][$day] ?? 0) + 1;
  $s['pages'][$page] = ($s['pages'][$page] ?? 0) + 1;
  // Tage auf die letzten 120 begrenzen
  if (!empty($s['days']) && count($s['days']) > 130) { ksort($s['days']); $s['days'] = array_slice($s['days'], -120, null, true); }
  ftruncate($fp, 0); rewind($fp);
  fwrite($fp, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  flock($fp, LOCK_UN); fclose($fp);
}
echo '{"ok":true}';
