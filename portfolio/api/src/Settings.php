<?php

declare(strict_types=1);

namespace App;

/**
 * Frei editierbare Inhalte, die nicht zu einem Projekt gehören:
 * Vorstellung, Skills, Verfügbarkeits-Badge, Social-Links, SEO-Texte.
 *
 * Werte liegen als JSON in einer Key-Value-Tabelle – so kommen neue Felder
 * ohne Migration dazu.
 */
final class Settings
{
    public function __construct(private Database $db, private array $config = []) {}

    public function all(): array
    {
        $rows = $this->db->all('SELECT key, value FROM settings');
        $out = self::defaults();

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['value'], true);
            $out[$row['key']] = $decoded === null && $row['value'] !== 'null' ? $row['value'] : $decoded;
        }

        // Gespeichert werden nur Medien-IDs; das Frontend braucht fertige URLs.
        $out['hero'] = $this->resolveHeroMedia($out['hero'] ?? []);

        return $out;
    }

    /** Ergänzt zur Video- und Poster-ID die vollständigen Mediendaten. */
    private function resolveHeroMedia(array $hero): array
    {
        $hero['video'] = null;
        $hero['poster'] = null;

        if (!isset($this->config['uploads_url'])) {
            return $hero;
        }

        $ids = array_filter([
            'video' => $hero['mediaId'] ?? null,
            'poster' => $hero['posterId'] ?? null,
        ]);

        foreach ($ids as $key => $id) {
            $row = $this->db->first('SELECT * FROM media WHERE id = ?', [(int) $id]);
            if ($row !== null) {
                $hero[$key] = Media::present($row, $this->config);
            }
        }

        return $hero;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    /** Speichert nur die übergebenen Schlüssel – alles andere bleibt unberührt. */
    public function save(array $values): array
    {
        $allowed = array_keys(self::defaults());

        foreach ($values as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            // Aufgelöste Mediendaten nicht zurückschreiben – gespeichert
            // werden nur die IDs, alles andere kommt beim Lesen dazu.
            if ($key === 'hero' && is_array($value)) {
                unset($value['video'], $value['poster']);
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
            $this->db->run(
                'INSERT INTO settings (key, value, updated_at) VALUES (:key, :value, :updated_at)
                 ON CONFLICT(key) DO UPDATE SET value = :value, updated_at = :updated_at',
                ['key' => $key, 'value' => $encoded, 'updated_at' => gmdate('c')]
            );
        }

        $all = $this->all();
        $this->writeAppearanceFile($all);

        return $all;
    }

    /**
     * Schreibt die Anzeige-Vorgaben als winzige JavaScript-Datei.
     *
     * Grund: Das gewünschte Farbschema muss feststehen, *bevor* der Browser
     * das erste Pixel zeichnet – sonst blitzt kurz das falsche Design auf.
     * Ein API-Aufruf wäre dafür zu spät, also legt das Backend die Vorgabe
     * als statische Datei ab, die der Seitenkopf direkt lädt.
     *
     * Sie liegt bewusst in `uploads/`: Der Ordner ist beschreibbar und wird
     * bei einem erneuten Upload nicht überschrieben.
     */
    public function writeAppearanceFile(?array $settings = null): bool
    {
        $path = $this->config['uploads_path'] ?? null;
        if ($path === null) {
            return false;
        }

        $settings ??= $this->all();
        $appearance = $settings['appearance'] ?? [];
        $theme = in_array($appearance['defaultTheme'] ?? 'system', ['light', 'dark', 'system'], true)
            ? $appearance['defaultTheme']
            : 'system';
        $cursor = ($settings['features']['cursor'] ?? true) === true;

        // Bei "system" entscheidet die Einstellung des Betriebssystems,
        // sonst gilt die feste Vorgabe. Eine eigene Wahl des Besuchers
        // (localStorage) hat in beiden Fällen Vorrang.
        $fallback = $theme === 'system'
            ? "(matchMedia('(prefers-color-scheme: light)').matches?'light':'dark')"
            : "'" . $theme . "'";

        $js = "/* Automatisch erzeugt – Änderungen im Dashboard überschreiben diese Datei. */\n"
            . "(function(){var r=document.documentElement;try{"
            . "r.dataset.theme=localStorage.getItem('dm-theme')||{$fallback};"
            . ($cursor
                ? "if(localStorage.getItem('dm-cursor')!=='off'&&matchMedia('(pointer:fine)').matches){r.dataset.cursor='custom';}else{delete r.dataset.cursor;}"
                : "delete r.dataset.cursor;")
            . "}catch(e){r.dataset.theme={$fallback};}})();\n";

        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            return false;
        }

        return @file_put_contents($path . '/theme.js', $js) !== false;
    }

    /** Struktur und Startwerte – gleichzeitig die Whitelist beim Speichern. */
    public static function defaults(): array
    {
        return [
            'name' => 'Dominic Majewski',
            'role' => '3D Artist · Motion Designer',
            'location' => 'Deutschland · remote weltweit',
            'tagline' => 'Ich baue digitale Welten, die man nicht mehr wegklicken will.',
            'intro' => "Hi, ich bin Dominic. Seit Jahren bewege ich mich zwischen Blender-Viewport, Unreal-Engine-Sequencer und Schnittfenster – und genau in dieser Mischung entsteht meine Arbeit: fotoreale Renderings, begehbare Echtzeit-Welten und cinematische Bewegtbilder.\n\nMich interessiert dabei weniger der Effekt an sich als die Frage dahinter: Was soll jemand fühlen, wenn er das hier sieht? Von dort aus baue ich rückwärts – Licht, Rhythmus, Timing, Typo.",

            // Anzeige-Vorgaben für neue Besucher. Wer selbst umschaltet,
            // behält seine Wahl – diese Werte gelten nur beim ersten Besuch.
            'appearance' => [
                'defaultTheme' => 'light',
            ],

            // Kopfbereich der Startseite: große Typografie oder Showreel.
            'hero' => [
                'mode' => 'type',       // 'type' oder 'showreel'
                'mediaId' => null,      // Video (MP4/WebM)
                'posterId' => null,     // Standbild, solange das Video lädt
                'overlay' => 55,        // Abdunklung in Prozent, damit Schrift lesbar bleibt
                'showTitle' => true,    // Name zusätzlich über dem Video
            ],

            // Das Status-Feld ist standardmäßig aus. Wer es einschaltet,
            // formuliert selbst, was dort steht.
            'availability' => [
                'visible' => false,
                'status' => 'open',
                'label' => 'Offen für gemeinsame Projekte',
                'detail' => '',
            ],
            'email' => 'hello.dominicmajewski@gmail.com',
            'phone' => '',
            'socials' => [
                ['label' => 'Instagram', 'url' => ''],
                ['label' => 'ArtStation', 'url' => ''],
                ['label' => 'LinkedIn', 'url' => ''],
                ['label' => 'GitHub', 'url' => ''],
            ],
            'skills' => [
                [
                    'title' => '3D & Rendering',
                    'description' => 'Modelling, Shading, Lighting und Rendering in Blender – von der Produktaufnahme bis zur kompletten Szene.',
                    'items' => ['Blender', 'Cycles / EEVEE', 'Substance', 'Hard-Surface', 'Lookdev'],
                ],
                [
                    'title' => 'Realtime & Unreal',
                    'description' => 'Interaktive Echtzeit-Umgebungen, Kamerafahrten und Simulationen in der Unreal Engine 5.',
                    'items' => ['Unreal Engine 5', 'Sequencer', 'Niagara', 'Blueprints', 'Lumen'],
                ],
                [
                    'title' => 'Motion & Schnitt',
                    'description' => 'Cinematischer Schnitt, Grading, Sound-Design und Motion Graphics für Kampagnen und Social Media.',
                    'items' => ['After Effects', 'Premiere Pro', 'DaVinci Resolve', 'Grading', 'Sound-Design'],
                ],
                [
                    'title' => 'Grafik & Layout',
                    'description' => 'Ganzheitliches Branding, kompromisslose Typografie und präzise Raster – digital wie gedruckt.',
                    'items' => ['Branding', 'Typografie', 'Layout', 'Print', 'Social Assets'],
                ],
            ],
            // Software-Kompetenzen mit Selbsteinschätzung (0–100).
            'expertise' => [
                ['name' => 'Blender', 'level' => 95, 'note' => '', 'group' => '3D & Rendering'],
                ['name' => 'Unreal Engine 5', 'level' => 80, 'note' => '', 'group' => '3D & Rendering'],
                ['name' => 'Substance', 'level' => 70, 'note' => '', 'group' => '3D & Rendering'],
                ['name' => 'After Effects', 'level' => 85, 'note' => '', 'group' => 'Motion & Schnitt'],
                ['name' => 'Premiere Pro', 'level' => 85, 'note' => '', 'group' => 'Motion & Schnitt'],
                ['name' => 'DaVinci Resolve', 'level' => 75, 'note' => '', 'group' => 'Motion & Schnitt'],
                ['name' => 'Photoshop', 'level' => 85, 'note' => '', 'group' => 'Grafik & Layout'],
                ['name' => 'Illustrator', 'level' => 70, 'note' => '', 'group' => 'Grafik & Layout'],
                ['name' => 'InDesign', 'level' => 65, 'note' => '', 'group' => 'Grafik & Layout'],
            ],

            // Interaktiver Lebenslauf.
            'resume' => [
                'headline' => 'Werdegang',
                'summary' => 'Stationen, Projekte und Ausbildung im Überblick.',
                // Bewusst als erkennbare Platzhalter angelegt – bitte im
                // Dashboard durch die echten Stationen ersetzen.
                'timeline' => [
                    [
                        'period' => 'seit 2022',
                        'title' => 'Beispieleintrag – bitte ersetzen',
                        'org' => 'Selbstständig',
                        'location' => '',
                        'description' => 'Beschreibe hier kurz, woran du in dieser Zeit gearbeitet hast.',
                        'type' => 'work',
                        'tags' => ['Blender', 'Unreal Engine'],
                    ],
                    [
                        'period' => '2019 — 2022',
                        'title' => 'Beispieleintrag – bitte ersetzen',
                        'org' => 'Firma oder Agentur',
                        'location' => '',
                        'description' => 'Rolle, Verantwortung, größte Projekte.',
                        'type' => 'work',
                        'tags' => [],
                    ],
                    [
                        'period' => '2016 — 2019',
                        'title' => 'Beispieleintrag – bitte ersetzen',
                        'org' => 'Schule, Hochschule oder Ausbildung',
                        'location' => '',
                        'description' => 'Abschluss und Schwerpunkte.',
                        'type' => 'education',
                        'tags' => [],
                    ],
                ],
                'languages' => [
                    ['name' => 'Deutsch', 'level' => 'Muttersprache'],
                    ['name' => 'Englisch', 'level' => 'Verhandlungssicher'],
                ],
                'facts' => [
                    ['label' => 'Erfahrung', 'value' => '8+ Jahre'],
                    ['label' => 'Arbeitsweise', 'value' => 'Remote & vor Ort'],
                    ['label' => 'Antwortzeit', 'value' => 'unter 24 h'],
                ],
            ],

            'process' => [
                ['title' => 'Briefing', 'description' => 'Wir klären Ziel, Zielgruppe und Rahmen. Ehrlich – auch wenn die Antwort mal "das braucht ihr nicht" lautet.'],
                ['title' => 'Konzept', 'description' => 'Moodboard, Referenzen, Look-Richtung. Bevor irgendetwas gerendert wird, steht das Bild im Kopf.'],
                ['title' => 'Produktion', 'description' => 'Modelling, Animation, Schnitt oder Code – mit festen Zwischenständen statt Blackbox.'],
                ['title' => 'Feinschliff', 'description' => 'Grading, Sound, Performance, Details. Der Teil, der den Unterschied macht.'],
            ],
            'seo' => [
                'title' => 'Dominic Majewski – 3D, Realtime & Motion Design',
                'description' => 'Portfolio von Dominic Majewski: fotorealistische 3D-Renderings in Blender, Echtzeit-Szenen in Unreal Engine 5 und cinematischer Schnitt.',
                'keywords' => '3D Artist, Blender, Unreal Engine, Motion Design, Videoschnitt, Portfolio',
            ],
            'legal' => [
                'company' => 'Dominic Majewski',
                'street' => '',
                'city' => '',
                'email' => 'hello.dominicmajewski@gmail.com',
                'phone' => '',
                'vatId' => '',
            ],
            'features' => [
                'sound' => true,
                'cursor' => true,
                'analytics' => true,
                'easterEgg' => true,
            ],
        ];
    }
}
