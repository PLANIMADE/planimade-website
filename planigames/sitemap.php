<?php
/**
 * PLANIGAMES — dynamische Sitemap.
 * Listet alle festen Seiten + jedes Spiel + jeden Devlog-Beitrag.
 * Erreichbar als /sitemap.php oder (per .htaccess-Rewrite) /sitemap.xml
 */
require __DIR__ . '/seo.php';
header('Content-Type: application/xml; charset=utf-8');

$base = pg_seo_base();
$urls = [];
$add = function ($loc, $lastmod = null, $prio = '0.6') use (&$urls, $base) {
    $urls[] = ['loc' => $base . $loc, 'lastmod' => $lastmod, 'prio' => $prio];
};

$add('/', null, '1.0');
$add('/games.html', null, '0.8');
$add('/devlog.php', null, '0.7');
$add('/rechtliches.html?doc=impressum', null, '0.3');
$add('/rechtliches.html?doc=datenschutz', null, '0.3');

foreach ((pg_seo_json(__DIR__ . '/data/games.json')['games'] ?? []) as $g) {
    if (!empty($g['slug'])) $add('/game.php?slug=' . rawurlencode($g['slug']), null, '0.9');
}
foreach ((pg_seo_json(__DIR__ . '/data/patchnotes.json')['posts'] ?? []) as $p) {
    if (!empty($p['slug'])) $add('/devlog.php?slug=' . rawurlencode($p['slug']), $p['date'] ?? null, '0.6');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n    <loc>" . pg_e($u['loc']) . "</loc>\n";
    if (!empty($u['lastmod'])) echo "    <lastmod>" . pg_e(substr($u['lastmod'], 0, 10)) . "</lastmod>\n";
    echo "    <priority>" . pg_e($u['prio']) . "</priority>\n  </url>\n";
}
echo "</urlset>\n";
