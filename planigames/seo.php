<?php
/**
 * PLANIGAMES — SEO-Helfer für serverseitige Meta-Tags & strukturierte Daten.
 * Wird von game.php, devlog.php und sitemap.php eingebunden.
 * So sehen geteilte Links (Discord/Bluesky/X) korrekten Titel, Text & Bild –
 * auch ohne JavaScript (Social-Crawler führen kein JS aus).
 */

function pg_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pg_seo_base() {
    $s = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $h = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'planigames.com');
    return $s . '://' . $h;
}
function pg_seo_abs($p) {
    $p = trim((string)$p);
    if ($p === '' || strncmp($p, 'data:', 5) === 0) return '';     // data-URIs nicht als OG-Bild
    if (preg_match('#^https?://#i', $p)) return $p;
    return pg_seo_base() . '/' . ltrim($p, '/');
}
function pg_seo_json($file) {
    if (!is_file($file)) return [];
    $j = json_decode(file_get_contents($file), true);
    return is_array($j) ? $j : [];
}
function pg_seo_lang() { return (($_GET['lang'] ?? '') === 'en') ? 'en' : 'de'; }
function pg_seo_clip($s, $n = 180) {
    $s = trim(preg_replace('/\s+/', ' ', strip_tags((string)$s)));
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
}

function pg_seo_studio() { return pg_seo_json(__DIR__ . '/data/studio.json'); }

function pg_seo_org() {
    $s = pg_seo_studio();
    $sameAs = array_values(array_filter(
        array_map(fn($x) => $x['url'] ?? '', $s['socials'] ?? []),
        fn($u) => $u && $u !== '#'
    ));
    $org = [
        '@context' => 'https://schema.org', '@type' => 'Organization',
        'name' => $s['name'] ?? 'PLANIGAMES', 'url' => pg_seo_base() . '/',
        'logo' => pg_seo_base() . '/media/og.jpg',
    ];
    if (!empty($s['email'])) $org['email'] = $s['email'];
    if ($sameAs) $org['sameAs'] = $sameAs;
    return $org;
}

/* Englisches Overlay (falls vorhanden) auf ein Element legen */
function pg_seo_en($file, $matchKey, $matchVal, $listKey) {
    if (pg_seo_lang() !== 'en') return null;
    $en = pg_seo_json(preg_replace('/\.json$/', '.en.json', $file));
    foreach (($en[$listKey] ?? []) as $x) if (($x[$matchKey] ?? '') === $matchVal) return $x;
    return null;
}

function pg_seo_head($o) {
    $img = $o['image'] ?: (pg_seo_base() . '/media/og.jpg');
    $type = $o['type'] ?? 'website';
    echo "\n    <title>" . pg_e($o['title']) . "</title>\n";
    echo '    <meta name="description" content="' . pg_e($o['desc']) . "\">\n";
    echo '    <link rel="canonical" href="' . pg_e($o['url']) . "\">\n";
    echo '    <meta property="og:site_name" content="PLANIGAMES">' . "\n";
    echo '    <meta property="og:type" content="' . pg_e($type) . "\">\n";
    echo '    <meta property="og:title" content="' . pg_e($o['title']) . "\">\n";
    echo '    <meta property="og:description" content="' . pg_e($o['desc']) . "\">\n";
    echo '    <meta property="og:url" content="' . pg_e($o['url']) . "\">\n";
    echo '    <meta property="og:image" content="' . pg_e($img) . "\">\n";
    echo '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '    <meta name="twitter:title" content="' . pg_e($o['title']) . "\">\n";
    echo '    <meta name="twitter:description" content="' . pg_e($o['desc']) . "\">\n";
    echo '    <meta name="twitter:image" content="' . pg_e($img) . "\">\n";
}
function pg_seo_jsonld($data) {
    echo '    <script type="application/ld+json">'
       . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}

/* ---- Game-Seite ---- */
function pg_seo_game() {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'] ?? ''));
    $studio = pg_seo_studio(); $name = $studio['name'] ?? 'PLANIGAMES'; $base = pg_seo_base();
    $games = pg_seo_json(__DIR__ . '/data/games.json')['games'] ?? [];
    $g = null; foreach ($games as $x) if (($x['slug'] ?? '') === $slug) { $g = $x; break; }
    if (!$g) {
        pg_seo_head(['title' => 'Spiele — ' . $name, 'desc' => pg_seo_clip($studio['tagline'] ?? ''), 'url' => $base . '/games.html', 'image' => '']);
        pg_seo_jsonld(pg_seo_org()); return;
    }
    if ($en = pg_seo_en(__DIR__ . '/data/games.json', 'slug', $slug, 'games')) {
        if (!empty($en['title'])) $g['title'] = $en['title'];
        if (!empty($en['tagline'])) $g['tagline'] = $en['tagline'];
    }
    $title = $g['title'] . ' — ' . $name;
    $desc = pg_seo_clip($g['tagline'] ?? '');
    $url = $base . '/game.php?slug=' . $slug . (pg_seo_lang() === 'en' ? '&lang=en' : '');
    $img = pg_seo_abs($g['cover'] ?? '');
    pg_seo_head(['title' => $title, 'desc' => $desc, 'url' => $url, 'image' => $img]);
    $vg = ['@context' => 'https://schema.org', '@type' => 'VideoGame', 'name' => $g['title'],
        'description' => $desc, 'url' => $url, 'applicationCategory' => 'Game',
        'author' => ['@type' => 'Organization', 'name' => $name],
        'publisher' => ['@type' => 'Organization', 'name' => $name]];
    if ($img) $vg['image'] = $img;
    pg_seo_jsonld($vg);
    pg_seo_jsonld(pg_seo_org());
}

/* ---- Devlog / Patch Note ---- */
function pg_seo_post() {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'] ?? ''));
    $studio = pg_seo_studio(); $name = $studio['name'] ?? 'PLANIGAMES'; $base = pg_seo_base();
    if ($slug === '') {
        pg_seo_head(['title' => 'Devlog & Patch Notes — ' . $name,
            'desc' => 'Entwicklungstagebuch, Updates und Patch Notes von ' . $name . '.',
            'url' => $base . '/devlog.php', 'image' => '']);
        pg_seo_jsonld(pg_seo_org()); return;
    }
    $posts = pg_seo_json(__DIR__ . '/data/patchnotes.json')['posts'] ?? [];
    $p = null; foreach ($posts as $x) if (($x['slug'] ?? '') === $slug) { $p = $x; break; }
    if (!$p) {
        pg_seo_head(['title' => 'Devlog — ' . $name, 'desc' => '', 'url' => $base . '/devlog.php', 'image' => '']);
        pg_seo_jsonld(pg_seo_org()); return;
    }
    if ($en = pg_seo_en(__DIR__ . '/data/patchnotes.json', 'slug', $slug, 'posts')) {
        if (!empty($en['title'])) $p['title'] = $en['title'];
        if (!empty($en['excerpt'])) $p['excerpt'] = $en['excerpt'];
    }
    $title = $p['title'] . ' — ' . $name;
    $desc = pg_seo_clip(($p['excerpt'] ?? '') ?: ($p['body'] ?? ''));
    $url = $base . '/devlog.php?slug=' . $slug;
    $img = pg_seo_abs($p['cover'] ?? '');
    pg_seo_head(['title' => $title, 'desc' => $desc, 'url' => $url, 'image' => $img, 'type' => 'article']);
    $art = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $p['title'],
        'description' => $desc, 'url' => $url,
        'author' => ['@type' => 'Organization', 'name' => $name],
        'publisher' => ['@type' => 'Organization', 'name' => $name,
            'logo' => ['@type' => 'ImageObject', 'url' => $base . '/media/og.jpg']]];
    if (!empty($p['date'])) $art['datePublished'] = $p['date'];
    if ($img) $art['image'] = $img;
    pg_seo_jsonld($art);
}
