<?php

/**
 * Einrichtungslogik für die Kommandozeile (`setup.php`) – gedacht fürs
 * lokale Entwickeln. Auf dem Server läuft die Einrichtung über `/admin/`:
 * Solange kein Zugang existiert, zeigt das Dashboard ein Formular zum
 * Anlegen. `portfolio_seed_demo()` teilen sich beide Wege.
 */

declare(strict_types=1);

use App\Database;
use App\Settings;
use App\Str;

$config = require dirname(__DIR__) . '/config.php';

spl_autoload_register(static function (string $class) : void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

/**
 * @return array{log: array<int, string>, created: bool}
 */
function portfolio_setup(string $email, string $password, bool $withDemo = false, bool $force = false): array
{
    global $config;

    $log = [];
    $db = new Database($config);
    $log[] = '✓ Datenbank bereit (' . $config['storage_path'] . '/portfolio.sqlite)';

    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Keine gültige E-Mail-Adresse.');
    }
    if (mb_strlen($password) < 10) {
        throw new RuntimeException('Das Passwort muss mindestens 10 Zeichen haben.');
    }

    $existing = $db->first('SELECT id FROM users WHERE email = ?', [$email]);
    $created = false;

    if ($existing === null) {
        $userCount = (int) $db->value('SELECT COUNT(*) FROM users');
        if ($userCount > 0 && !$force) {
            throw new RuntimeException('Es existiert bereits ein Zugang. Mit --force überschreiben oder die vorhandene E-Mail verwenden.');
        }
        $db->insert('users', [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'name' => 'Dominic Majewski',
            'created_at' => gmdate('c'),
        ]);
        $created = true;
        $log[] = '✓ Admin-Zugang angelegt: ' . $email;
    } elseif ($force) {
        $db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $existing['id']]);
        $db->run('DELETE FROM sessions WHERE user_id = ?', [$existing['id']]);
        $log[] = '✓ Passwort für ' . $email . ' neu gesetzt (alle Sitzungen beendet)';
    } else {
        $log[] = '• Zugang ' . $email . ' existiert bereits – unverändert gelassen (--force setzt das Passwort neu)';
    }

    $settings = new Settings($db, $config);
    if ($db->value('SELECT COUNT(*) FROM settings') === 0) {
        $settings->save(Settings::defaults());
        $log[] = '✓ Grundeinstellungen und Profiltexte eingespielt';
    }

    // Legt uploads/theme.js an – die Farbschema-Vorgabe für neue Besucher.
    $settings->writeAppearanceFile();

    foreach ([$config['uploads_path'], $config['uploads_path'] . '/' . gmdate('Y/m')] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
    $log[] = is_writable($config['uploads_path'])
        ? '✓ Upload-Ordner beschreibbar'
        : '! Upload-Ordner NICHT beschreibbar – bitte Rechte auf 755/775 setzen: ' . $config['uploads_path'];

    if ($withDemo && (int) $db->value('SELECT COUNT(*) FROM projects') === 0) {
        portfolio_seed_demo($db);
        $log[] = '✓ 4 Beispielprojekte angelegt (im Dashboard löschbar)';
    }

    return ['log' => $log, 'created' => $created];
}

/**
 * Erzeugt ein abstraktes Platzhalter-Cover in der Akzentfarbe des Projekts.
 *
 * Damit sieht das Portfolio direkt nach der Einrichtung nach etwas aus –
 * echte Bilder ersetzen die Platzhalter später einfach im Dashboard.
 */
function portfolio_generate_cover(Database $db, string $accentHex, string $seedText): ?int
{
    global $config;

    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }

    $relativeDir = gmdate('Y/m');
    $dir = rtrim($config['uploads_path'], '/') . '/' . $relativeDir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    [$ar, $ag, $ab] = sscanf(ltrim($accentHex, '#'), '%2x%2x%2x') ?: [168, 85, 247];
    $seed = crc32($seedText);

    // Klein rechnen, groß skalieren: das Weichzeichnen der Skalierung ist Teil des Looks.
    $small = imagecreatetruecolor(200, 125);
    for ($y = 0; $y < 125; $y++) {
        for ($x = 0; $x < 200; $x++) {
            $u = $x / 200;
            $v = $y / 125;

            // Überlagerte Sinuswellen ergeben weiche, wolkige Strukturen.
            $wave = sin($u * 6.0 + ($seed % 100) / 12) * cos($v * 4.5 + ($seed % 57) / 9);
            $radial = 1.0 - min(1.0, sqrt((($u - 0.35) ** 2) * 1.6 + (($v - 0.4) ** 2) * 2.2));
            $intensity = max(0.0, min(1.0, 0.35 * $wave + 0.85 * $radial));

            $r = (int) max(0, min(255, 8 + $ar * $intensity * 0.9));
            $g = (int) max(0, min(255, 8 + $ag * $intensity * 0.9));
            $b = (int) max(0, min(255, 14 + $ab * $intensity * 0.95));

            imagesetpixel($small, $x, $y, imagecolorallocate($small, $r, $g, $b));
        }
    }

    $large = imagecreatetruecolor(1600, 1000);
    imagecopyresampled($large, $small, 0, 0, 0, 0, 1600, 1000, 200, 125);
    imagedestroy($small);

    $name = App\Str::slug($seedText) . '-cover-' . substr(md5((string) $seed), 0, 6);
    imagejpeg($large, $dir . '/' . $name . '.jpg', 86);

    $thumb = imagecreatetruecolor(640, 400);
    imagecopyresampled($thumb, $large, 0, 0, 0, 0, 640, 400, 1600, 1000);
    $thumbName = $name . '-thumb.' . (function_exists('imagewebp') ? 'webp' : 'jpg');
    if (function_exists('imagewebp')) {
        imagewebp($thumb, $dir . '/' . $thumbName, 82);
    } else {
        imagejpeg($thumb, $dir . '/' . $thumbName, 82);
    }

    imagedestroy($thumb);
    imagedestroy($large);

    return $db->insert('media', [
        'filename' => $name . '.jpg',
        'path' => $relativeDir . '/' . $name . '.jpg',
        'thumb_path' => $relativeDir . '/' . $thumbName,
        'mime' => 'image/jpeg',
        'kind' => 'image',
        'size' => (int) (filesize($dir . '/' . $name . '.jpg') ?: 0),
        'width' => 1600,
        'height' => 1000,
        'alt' => 'Abstraktes Platzhalter-Cover für ' . $seedText,
        'created_at' => gmdate('c'),
    ]);
}

function portfolio_seed_demo(Database $db): void
{
    $demo = [
        [
            'title' => 'Orbit — Produktfilm',
            'subtitle' => 'Vollständig in Blender gerendertes Kampagnen-Cinematic',
            'category' => '3D & Rendering',
            'client' => 'Beispielkunde GmbH',
            'role' => 'Konzept, Modelling, Lookdev, Rendering, Schnitt',
            'year' => 2025,
            'summary' => 'Ein 45-sekündiger Produktfilm, komplett im Rechner entstanden: kein Set, kein Dreh, volle Kontrolle über jedes Licht.',
            'body' => "## Ausgangslage\nDas Produkt existierte zum Zeitpunkt der Kampagne nur als CAD-Datei. Trotzdem sollte es sich im Film anfassbar anfühlen.\n\n## Umsetzung\nRetopologie der CAD-Daten, anschließend Lookdev mit gemessenen Materialien und ein Lichtsetup, das sich an klassischer Studiofotografie orientiert. Gerendert in Cycles, gefinished im Grading.\n\n## Ergebnis\nEin Master-Film plus 6 Social-Cutdowns aus derselben Szene – ohne einen einzigen weiteren Drehtag.",
            'tools' => ['Blender', 'Cycles', 'DaVinci Resolve', 'After Effects'],
            'tags' => ['Produktfilm', '3D', 'Kampagne'],
            'metrics' => [['label' => 'Renderzeit', 'value' => '38 h'], ['label' => 'Cutdowns', 'value' => '6']],
            'accent' => '#a855f7',
            'featured' => 1,
        ],
        [
            'title' => 'Nebula — Echtzeit-Showroom',
            'subtitle' => 'Begehbare Unreal-Engine-5-Umgebung für Messe und Web',
            'category' => 'Realtime & Unreal',
            'client' => 'Interner Showcase',
            'role' => 'Environment Art, Blueprints, Sequencer',
            'year' => 2025,
            'summary' => 'Ein interaktiver Showroom, durch den Besucher selbst laufen – auf der Messe am Touchscreen, danach als gerenderter Film.',
            'body' => "## Idee\nStatt Renderings zu zeigen, sollten Besucher den Raum selbst erkunden können.\n\n## Technik\nLumen für dynamische Beleuchtung, Nanite für die Geometrie, ein schlankes Blueprint-Setup für die Navigation. Aus derselben Szene entstand über den Sequencer der Trailer.",
            'tools' => ['Unreal Engine 5', 'Lumen', 'Nanite', 'Blueprints'],
            'tags' => ['Realtime', 'Interaktiv', 'Messe'],
            'metrics' => [['label' => 'Framerate', 'value' => '60 fps'], ['label' => 'Fläche', 'value' => '1.200 m²']],
            'accent' => '#3b82f6',
            'featured' => 1,
        ],
        [
            'title' => 'Signal — Social-Kampagne',
            'subtitle' => 'Schnitt, Motion Design und Sound für 12 Spots',
            'category' => 'Motion & Schnitt',
            'client' => 'Beispiel Media',
            'role' => 'Schnitt, Motion Design, Sound-Design, Grading',
            'year' => 2024,
            'summary' => 'Zwölf Spots in drei Formaten aus einem gemeinsamen Rohmaterial – mit einer Bildsprache, die auch stummgeschaltet funktioniert.',
            'body' => "## Aufgabe\nEine Kampagne, die im Feed in den ersten 1,5 Sekunden hängen bleibt – auch ohne Ton.\n\n## Lösung\nTypografie als Hauptdarsteller, harte Schnitte auf den Beat, konsequentes Grading über alle Spots hinweg.",
            'tools' => ['Premiere Pro', 'After Effects', 'DaVinci Resolve'],
            'tags' => ['Social', 'Motion', 'Schnitt'],
            'metrics' => [['label' => 'Spots', 'value' => '12'], ['label' => 'Formate', 'value' => '9:16 · 1:1 · 16:9']],
            'accent' => '#f97316',
            'featured' => 0,
        ],
        [
            'title' => 'Atlas — Website & Interface',
            'subtitle' => 'Handgeschriebene Site mit eigenem Redaktionssystem',
            'category' => 'Web & Code',
            'client' => 'Beispiel Studio',
            'role' => 'Design, Frontend, Backend',
            'year' => 2024,
            'summary' => 'Eine Website ohne Baukasten: statisch ausgeliefert, in unter einer Sekunde da, mit einem Dashboard, das auch ohne Technikwissen bedienbar ist.',
            'body' => "## Anspruch\nSchnell, eigenständig, wartbar – und redaktionell selbst pflegbar.\n\n## Aufbau\nStatisches Frontend, schlanke API dahinter, Inhalte im eigenen Dashboard. Kein Plugin-Zoo, keine Update-Pflicht.",
            'tools' => ['Astro', 'TypeScript', 'Tailwind', 'PHP', 'SQLite'],
            'tags' => ['Web', 'Interface', 'CMS'],
            'metrics' => [['label' => 'Lighthouse', 'value' => '100'], ['label' => 'Ladezeit', 'value' => '0,4 s']],
            'accent' => '#22d3ee',
            'featured' => 0,
        ],
    ];

    $now = gmdate('c');
    foreach (array_values($demo) as $index => $project) {
        $coverId = portfolio_generate_cover($db, $project['accent'], $project['title']);

        $db->insert('projects', [
            'cover_id' => $coverId,
            'slug' => Str::slug($project['title']),
            'title' => $project['title'],
            'subtitle' => $project['subtitle'],
            'summary' => $project['summary'],
            'body' => $project['body'],
            'category' => $project['category'],
            'client' => $project['client'],
            'role' => $project['role'],
            'year' => $project['year'],
            'tools' => json_encode($project['tools'], JSON_UNESCAPED_UNICODE),
            'tags' => json_encode($project['tags'], JSON_UNESCAPED_UNICODE),
            'links' => '[]',
            'metrics' => json_encode($project['metrics'], JSON_UNESCAPED_UNICODE),
            'accent' => $project['accent'],
            'status' => 'published',
            'featured' => $project['featured'],
            'position' => $index + 1,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $now,
        ]);
    }

    $db->insert('testimonials', [
        'author' => 'Beispiel Kundin',
        // Platzhalter-Zitat – im Dashboard unter „Kundenstimmen" austauschbar.
        'role' => 'Marketingleitung',
        'company' => 'Beispiel GmbH',
        'quote' => 'Vom ersten Moodboard bis zum finalen Master war klar, wo wir stehen – und das Ergebnis lag über dem, was wir uns vorgestellt hatten.',
        'status' => 'published',
        'position' => 1,
        'created_at' => $now,
    ]);
}
