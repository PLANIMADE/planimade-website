<!DOCTYPE html>
<html lang="de" class="select-none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/seo.php'; pg_seo_post(); ?>
    <meta name="theme-color" content="#08080c">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🎮%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;1,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/planigames.css?v=57">
    <link rel="stylesheet" href="assets/site.css?v=57">
    <link rel="stylesheet" href="assets/tailwind.css?v=57">
    <script src="https://cdn.jsdelivr.net/npm/marked@15.0.12/marked.min.js" defer></script>
    <script src="assets/app.js?v=57" defer></script>
</head>
<body data-page="devlog" class="bg-[#08080c] text-zinc-100 font-sans antialiased overflow-x-hidden noise-bg grain-overlay">

    <div data-shell="header"></div>

    <!-- Einzelner Beitrag (per ?slug=) — anfangs versteckt -->
    <article data-devlog="single" hidden></article>

    <!-- Listenansicht -->
    <main data-devlog="list-wrap">
        <section class="pg-pghead"><div class="pg-wrap">
            <span class="pg-kicker" data-i18n="devlog_kicker">Entwicklungstagebuch</span>
            <h1 class="pg-h1" data-i18n="devlog_title">Devlog &amp; Patch Notes</h1>
            <p class="pg-lead" data-i18n="devlog_sub">Updates, Patchnotes und Blicke hinter die Kulissen. Frisch aus der Werkstatt.</p>
            <nav class="pg-filters" data-devlog="filters" aria-label="Filter" hidden></nav>
        </div></section>
        <section class="pg-sec pg-sec-tight"><div class="pg-wrap">
            <div data-devlog="list" class="pg-posts"></div>
        </div></section>
    </main>

    <div data-shell="footer"></div>
</body>
</html>
