<!DOCTYPE html>
<html lang="de" class="select-none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/seo.php'; pg_seo_game(); ?>
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
    <link rel="stylesheet" href="assets/planigames.css?v=37">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="assets/app.js?v=37" defer></script>
</head>
<body data-page="game" class="bg-[#050505] text-zinc-100 font-sans antialiased overflow-x-hidden noise-bg grain-overlay">

    <div data-shell="header"></div>

    <!-- Hier rendert app.js die frei konfigurierten Blöcke des Spiels -->
    <main data-game-root></main>

    <!-- Patch Notes des Spiels (wird ausgeblendet, falls keine vorhanden) -->
    <section data-game-patchnotes-section class="relative px-6 py-20 md:py-28">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-end justify-between mb-8 reveal">
                <h2 class="font-display text-3xl md:text-5xl font-extrabold text-white" data-i18n="patchnotes">Patch Notes</h2>
                <a href="devlog.php" class="font-mono text-[11px] uppercase tracking-widest text-zinc-500 hover:text-white" data-i18n="all_games_arrow">Alle →</a>
            </div>
            <div data-game-patchnotes></div>
        </div>
    </section>

    <div data-shell="footer"></div>
</body>
</html>
