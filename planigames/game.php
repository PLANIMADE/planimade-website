<?php
// Umbenannte Spiele: alte Adresse dauerhaft (301) auf die neue weiterleiten. Unbekannte Adresse → 404.
(function () {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['slug'] ?? '')));
    if ($slug === '') { header('Location: games.html', true, 302); exit; }
    $games = json_decode((string) @file_get_contents(__DIR__ . '/data/games.json'), true);
    if (!is_array($games) || !is_array($games['games'] ?? null)) return;        // Daten nicht lesbar → Seite normal ausliefern
    foreach ($games['games'] as $g) if (is_array($g) && strtolower((string) ($g['slug'] ?? '')) === $slug) return;
    $map = json_decode((string) @file_get_contents(__DIR__ . '/data/redirects.json'), true);
    $to = is_array($map) ? ($map['games'][$slug] ?? '') : '';
    if (is_string($to) && $to !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $to)) {
        $q = $_GET; $q['slug'] = $to;
        header('Location: game.php?' . http_build_query($q), true, 301); exit;
    }
    http_response_code(404);
})();
?><!DOCTYPE html>
<html lang="de" class="select-none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/seo.php'; pg_seo_game(); ?>
    <meta name="theme-color" content="#08080c">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🎮%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;1,500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: {
                sans: ['Montserrat', 'system-ui', 'sans-serif'],
                mono: ['Montserrat', 'system-ui', 'sans-serif'],
                display: ['Montserrat', 'system-ui', 'sans-serif'],
            },
            // Design v7: ruhige Überschriften statt Riesenschrift
            fontSize: {
                '3xl': ['1.75rem', { lineHeight: '2.15rem' }],
                '4xl': ['2rem', { lineHeight: '2.4rem' }],
                '5xl': ['2.375rem', { lineHeight: '1.15' }],
                '6xl': ['2.75rem', { lineHeight: '1.1' }],
                '7xl': ['3rem', { lineHeight: '1.08' }],
                '8xl': ['3.5rem', { lineHeight: '1.05' }],
                '9xl': ['3.75rem', { lineHeight: '1.05' }],
            },
        } } };
    </script>
    <link rel="stylesheet" href="assets/planigames.css?v=55">
    <link rel="stylesheet" href="assets/site.css?v=55">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="assets/app.js?v=55" defer></script>
</head>
<body data-page="game" class="bg-[#08080c] text-zinc-100 font-sans antialiased overflow-x-hidden noise-bg grain-overlay">

    <div data-shell="header"></div>

    <!-- Hier baut app.js die Spielseite (Design v7) aus den Blöcken des Spiels – feste Reihenfolge, fehlende Blöcke entfallen -->
    <main data-game-root id="top"></main>


    <div data-shell="footer"></div>
</body>
</html>
