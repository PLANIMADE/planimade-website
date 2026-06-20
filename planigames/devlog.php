<!DOCTYPE html>
<html lang="de" class="select-none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/seo.php'; pg_seo_post(); ?>
    <meta name="theme-color" content="#050505">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🎮%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@600,700,800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: {
            sans: ['Archivo', 'system-ui', 'sans-serif'],
            mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            display: ['"Clash Display"', 'Archivo', 'sans-serif'],
        } } } };
    </script>
    <link rel="stylesheet" href="assets/planigames.css?v=16">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="assets/app.js?v=16" defer></script>
</head>
<body data-page="devlog" class="bg-[#050505] text-zinc-100 font-sans antialiased overflow-x-hidden noise-bg grain-overlay">

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
