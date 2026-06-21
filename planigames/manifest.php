<?php
/**
 * PLANIGAMES — dynamisches Web-App-Manifest.
 * Nutzt das im Admin hochgeladene App-Icon (studio.pwa.appIcon); ist keines
 * gesetzt, fällt es auf die mitgelieferten Standard-Icons (Marken-Diamant) zurück.
 */
require __DIR__ . '/admin/lib.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$s    = pg_load_json(PG_DATA_DIR . '/studio.json');
$pwa  = is_array($s['pwa'] ?? null) ? $s['pwa'] : [];
$name = trim((string) ($pwa['appName'] ?? '')) ?: (trim((string) ($s['name'] ?? '')) ?: 'PLANIGAMES');
$icon = trim((string) ($pwa['appIcon'] ?? ''));

$mime = function ($path) {
  $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
  return ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml','gif'=>'image/gif'][$ext] ?? 'image/png';
};

if ($icon !== '') {
  // Eigenes Icon: für „any" und „maskable" verwenden (Größe „any" = tolerant ggü. Upload-Maßen)
  $t = $mime($icon);
  $icons = [
    ['src'=>$icon, 'sizes'=>'any', 'type'=>$t, 'purpose'=>'any'],
    ['src'=>$icon, 'sizes'=>'any', 'type'=>$t, 'purpose'=>'maskable'],
  ];
} else {
  // Standard-Icons (mitgeliefert)
  $icons = [
    ['src'=>'assets/icon-192.png', 'sizes'=>'192x192', 'type'=>'image/png', 'purpose'=>'any'],
    ['src'=>'assets/icon-512.png', 'sizes'=>'512x512', 'type'=>'image/png', 'purpose'=>'any'],
    ['src'=>'assets/icon-512-maskable.png', 'sizes'=>'512x512', 'type'=>'image/png', 'purpose'=>'maskable'],
  ];
}

echo json_encode([
  'name'             => $name,
  'short_name'       => mb_substr($name, 0, 12),
  'description'      => trim((string) ($s['tagline'] ?? '')) ?: 'Indie Game Studio',
  'id'               => '/',
  'start_url'        => '/',
  'scope'            => '/',
  'display'          => 'standalone',
  'background_color' => '#050505',
  'theme_color'      => '#050505',
  'lang'             => 'de',
  'categories'       => ['games', 'entertainment'],
  'icons'            => $icons,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
