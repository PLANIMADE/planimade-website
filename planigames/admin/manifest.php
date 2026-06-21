<?php
/**
 * PLANIGAMES Admin — dynamisches Web-App-Manifest (nur fürs Backend).
 * Macht das Admin am Handy „zum Home-Bildschirm hinzufügbar" → schneller Zugriff.
 * Nutzt das im Admin gesetzte App-Icon (studio.pwa.appIcon), sonst Standard-Icons.
 */
require __DIR__ . '/lib.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$s    = pg_load_json(PG_DATA_DIR . '/studio.json');
$pwa  = is_array($s['pwa'] ?? null) ? $s['pwa'] : [];
$base = trim((string) ($pwa['appName'] ?? '')) ?: (trim((string) ($s['name'] ?? '')) ?: 'PLANIGAMES');
$name = $base . ' Admin';
$icon = trim((string) ($pwa['appIcon'] ?? ''));

$mime = function ($path) {
  $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
  return ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml','gif'=>'image/gif'][$ext] ?? 'image/png';
};

if ($icon !== '') {
  $t = $mime($icon);
  $icons = [
    ['src'=>$icon, 'sizes'=>'any', 'type'=>$t, 'purpose'=>'any'],
    ['src'=>$icon, 'sizes'=>'any', 'type'=>$t, 'purpose'=>'maskable'],
  ];
} else {
  $icons = [
    ['src'=>'../assets/icon-192.png', 'sizes'=>'192x192', 'type'=>'image/png', 'purpose'=>'any'],
    ['src'=>'../assets/icon-512.png', 'sizes'=>'512x512', 'type'=>'image/png', 'purpose'=>'any'],
    ['src'=>'../assets/icon-512-maskable.png', 'sizes'=>'512x512', 'type'=>'image/png', 'purpose'=>'maskable'],
  ];
}

echo json_encode([
  'name'             => $name,
  'short_name'       => mb_substr($base, 0, 12) . ' ⚙',
  'description'      => 'Admin / Redaktion – ' . $base,
  'id'               => './',
  'start_url'        => 'index.php',
  'scope'            => './',
  'display'          => 'standalone',
  'background_color' => '#0a0a0b',
  'theme_color'      => '#0a0a0b',
  'lang'             => 'de',
  'icons'            => $icons,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
