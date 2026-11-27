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
<body data-page="devlog" class="bg-[#08080c] text-zinc-100 font-sans antialiased overflow-x-hidden noise-bg grain-overlay">

    <div data-shell="header"></div>

    <!-- Einzelner Beitrag (per ?slug=) — anfangs versteckt -->
    <article data-devlog="single" class="hidden"></article>

    <!-- Listenansicht -->
    <div data-devlog="list-wrap">
        <section class="relative px-6 pt-40 pb-12">
            <div class="absolute inset-0 grid-lines opacity-50"></div>
            <div class="relative max-w-5xl mx-auto">
                <div class="font-mono text-[11px] uppercase tracking-widest text-[color:var(--accent)] mb-5 reveal-on" data-i18n="devlog_kicker">Entwicklungstagebuch</div>
                <h1 class="font-display text-5xl md:text-8xl font-extrabold text-white leading-[0.92] reveal-on">
                    <span class="line-mask"><span class="line-inner block" data-i18n="devlog_title">Devlog &amp; Patch Notes</span></span>
                </h1>
                <p class="reveal mt-7 text-lg md:text-xl text-zinc-300 max-w-2xl" data-i18n="devlog_sub">Updates, Patchnotes und Blicke hinter die Kulissen. Frisch aus der Werkstatt.</p>
                <div data-devlog="filters" class="flex flex-wrap gap-2 mt-9 reveal"></div>
            </div>
        </section>

        <section class="relative px-6 pb-28">
            <div class="max-w-5xl mx-auto">
                <div data-devlog="list" class="grid gap-5"></div>
            </div>
        </section>
    </div>

    <div data-shell="footer"></div>
</body>
</html>
