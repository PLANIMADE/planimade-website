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
            $value = $decoded === null && $row['value'] !== 'null' ? $row['value'] : $decoded;

            $out[$row['key']] = is_array($value) && isset($out[$row['key']]) && is_array($out[$row['key']])
                ? self::mergeDeep($out[$row['key']], $value)
                : $value;
        }

        // Gespeichert werden nur Medien-IDs; das Frontend braucht fertige URLs.
        $out['hero'] = $this->resolveMedia($out['hero'] ?? [], ['mediaId' => 'video', 'posterId' => 'poster']);
        $out['portrait'] = $this->resolveMedia($out['portrait'] ?? [], ['mediaId' => 'image']);
        $out['logo'] = $this->resolveMedia($out['logo'] ?? [], ['mediaId' => 'image']);

        return $out;
    }

    /**
     * Ersetzt Medien-IDs durch die vollständigen Mediendaten.
     *
     * @param array<string, string> $map ID-Feld → Zielfeld
     */
    private function resolveMedia(array $group, array $map): array
    {
        foreach ($map as $target) {
            $group[$target] = null;
        }

        if (!isset($this->config['uploads_url'])) {
            return $group;
        }

        foreach ($map as $idField => $target) {
            $id = $group[$idField] ?? null;
            if (empty($id)) {
                continue;
            }

            $row = $this->db->first('SELECT * FROM media WHERE id = ?', [(int) $id]);
            if ($row !== null) {
                $group[$target] = Media::present($row, $this->config);
            }
        }

        return $group;
    }

    /**
     * Führt gespeicherte Werte mit den Standardwerten zusammen.
     *
     * Wichtig für zwei Fälle:
     *  – Ein Teil-Speichern (etwa nur ein Text) darf die übrigen Felder
     *    derselben Gruppe nicht löschen.
     *  – Kommen bei einem Update neue Felder dazu, erscheinen sie auch bei
     *    bestehenden Installationen mit ihrem Standardwert.
     *
     * Nummerierte Listen (Projekte, Sprachen, Social-Links …) werden dagegen
     * komplett ersetzt – sonst ließe sich kein Eintrag mehr löschen.
     */
    private static function mergeDeep(array $defaults, array $stored): array
    {
        if (array_is_list($stored)) {
            return $stored;
        }

        foreach ($stored as $key => $value) {
            $defaults[$key] = is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])
                ? self::mergeDeep($defaults[$key], $value)
                : $value;
        }

        return $defaults;
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
            if (($key === 'portrait' || $key === 'logo') && is_array($value)) {
                unset($value['image']);
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

    /**
     * Legt die im Dashboard gepflegten Werte über die Dateikonfiguration.
     *
     * So greifen alle bestehenden Stellen (Kontaktformular, Sitemap,
     * Systemcheck) unverändert auf `$config` zu, ohne zu wissen, woher der
     * Wert stammt. Leere Felder ändern nichts – die Datei behält dann das
     * letzte Wort.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function applyToConfig(array $config, array $settings): array
    {
        $site = $settings['site'] ?? [];

        foreach ([['url', 'site_url'], ['mailTo', 'mail_to'], ['mailFrom', 'mail_from']] as [$from, $to]) {
            $value = trim((string) ($site[$from] ?? ''));
            if ($value !== '') {
                $config[$to] = $to === 'site_url' ? rtrim($value, '/') : $value;
            }
        }

        if (array_key_exists('mailEnabled', $site)) {
            $config['mail_enabled'] = (bool) $site['mailEnabled'];
        }

        return $config;
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

            // Laufband unter dem Kopfbereich der Startseite.
            'marquee' => [
                'Blender',
                'Unreal Engine 5',
                'Cinema-Grading',
                'Motion Design',
                'Produktvisualisierung',
                'Echtzeit-Rendering',
                'Videoschnitt',
                'Lookdev',
            ],

            /**
             * Bewerbungs-Lebenslauf (/lebenslauf/).
             *
             * Nutzt Werdegang, Kompetenzen und Sprachen aus `resume`, ergänzt
             * um die Angaben, die in eine Bewerbung gehören, aber nichts auf
             * der öffentlichen Seite verloren haben.
             */
            'cv' => [
                'profile' => '',
                // Akzentfarbe des Dokuments als Hex-Wert. Leer = die Farbe
                // der Website. Auf dunklem Papier wird sie automatisch
                // aufgehellt, sonst verschwände sie im Untergrund.
                'accent' => '',
                // Papierfarbe des Dokuments: 'light' oder 'dark'. Unabhängig
                // vom Farbschema der Website – das Blatt ist ein eigenes
                // Medium und soll überall gleich aussehen.
                'theme' => 'light',
                'includePhoto' => true,
                'includeProjects' => true,
                'includeExpertise' => true,
                'details' => [
                    ['label' => 'Anschrift', 'value' => ''],
                    ['label' => 'Telefon', 'value' => ''],
                    ['label' => 'Geburtsjahr', 'value' => ''],
                ],
                'footer' => '',
            ],

            // Porträtbild für die Lebenslauf-Seite.
            'portrait' => [
                'mediaId' => null,
                'caption' => '',
            ],

            /**
             * Adresse und Mailversand.
             *
             * Diese Werte standen früher in `api/.env.php` und mussten per
             * FTP eingetragen werden. Jetzt stehen sie hier, damit sich das
             * Portfolio ohne einen einzigen Dateizugriff einrichten lässt.
             * Ist ein Feld leer, gilt weiterhin der Wert aus der Datei –
             * bestehende Installationen ändern sich also nicht.
             */
            'site' => [
                // Vollständige Adresse ohne Schrägstrich am Ende. Leer lassen:
                // dann leitet der Server sie aus der Anfrage ab.
                'url' => '',
                // Empfänger für Anfragen aus dem Kontaktformular.
                'mailTo' => '',
                // Absender. Muss bei all-inkl eine Adresse der eigenen Domain
                // sein, sonst verwirft der Mailserver die Nachricht.
                'mailFrom' => '',
                'mailEnabled' => true,
            ],

            /**
             * Eigenes Logo statt des Monogramms.
             *
             * Steht in der Navigation, im Fuß des Lebenslaufs und auf dem
             * Sperrbildschirm. Ohne Logo bilden die Initialen aus dem Namen
             * das Monogramm – es gibt also nie eine Lücke.
             */
            'logo' => [
                'mediaId' => null,
                'alt' => '',
            ],

            /**
             * Alle festen Texte der Website.
             *
             * Der Schlüssel steht im HTML als `data-text="…"`. Ist ein Wert
             * leer, bleibt der im Build hinterlegte Standardtext stehen – die
             * Seite kann also nie durch eine leere Eingabe kaputtgehen.
             */
            'texts' => [
                // Startseite
                'home.hero.button' => 'Arbeiten ansehen',
                'home.hero.scroll' => 'Scrollen',
                'home.intro.label' => 'Kurz vorgestellt',
                'home.intro.headline' => 'Zwischen Viewport, Sequencer und Schnittfenster.',
                'home.stats.1.value' => '8+',
                'home.stats.1.label' => 'Jahre Praxis',
                'home.stats.2.value' => '4',
                'home.stats.2.label' => 'Disziplinen',
                'home.stats.3.value' => '24 h',
                'home.stats.3.label' => 'Antwortzeit',
                'home.work.label' => 'Ausgewählte Arbeiten',
                'home.work.headline' => 'Projekte, die geblieben sind',
                'home.work.lead' => 'Eine Auswahl aus 3D, Echtzeit und Bewegtbild. Fahre über eine Karte für die Vorschau, klicke für die ganze Geschichte dahinter.',
                'home.work.action' => 'Alle Projekte',
                'home.skills.label' => 'Was ich mache',
                'home.skills.headline' => 'Vier Disziplinen, ein Ergebnis',
                'home.skills.lead' => 'Weil alles aus einer Hand kommt, muss niemand zwischen Gewerken übersetzen – und der Look bleibt vom ersten bis zum letzten Frame derselbe.',
                'home.process.label' => 'Ablauf',
                'home.process.headline' => 'Wie ein Projekt läuft',
                'home.testimonials.label' => 'Rückmeldungen',
                'home.testimonials.headline' => 'Was Kund:innen sagen',
                'home.cta.label' => 'Nächster Schritt',
                'home.cta.headline' => 'Lass uns etwas bauen.',
                'home.cta.lead' => 'Ob fertiges Briefing oder erste vage Idee – schreib mir, was du vorhast. Der Rest ergibt sich im Gespräch.',
                'home.cta.button' => 'Projekt anfragen',

                // Arbeiten
                'work.label' => 'Portfolio',
                'work.headline' => 'Alles, was bisher entstanden ist.',
                'work.lead' => 'Filtere nach Disziplin oder scroll dich einfach durch. Jedes Projekt hat eine eigene Seite mit Hintergrund, Werkzeugen und Ergebnis.',

                // Über mich
                'about.label' => 'Lebenslauf',
                'about.print' => 'Als PDF speichern',
                'about.aside.location' => 'Standort',
                'about.aside.status' => 'Status',
                'about.aside.languages' => 'Sprachen',
                'about.aside.contact' => 'Erreichbar',
                'about.aside.elsewhere' => 'Woanders',
                'about.timeline.label' => 'Werdegang',
                'about.timeline.headline' => 'Stationen',
                'about.timeline.lead' => 'Beruflicher Weg, Ausbildung und größere Projekte – filterbar nach Art.',
                'about.expertise.label' => 'Kompetenzen',
                'about.expertise.headline' => 'Womit ich täglich arbeite',
                'about.expertise.lead' => 'Selbsteinschätzung – Balken sagen weniger als Projekte, aber sie ordnen ein.',
                'about.skills.label' => 'Disziplinen',
                'about.skills.headline' => 'Vier Disziplinen',
                'about.process.label' => 'Ablauf',
                'about.process.headline' => 'Wie ich arbeite',
                'about.cta.label' => 'Zusammenarbeit',
                'about.cta.headline' => 'Klingt passend?',
                'about.cta.button' => 'Kontakt aufnehmen',

                // Kontakt
                'contact.label' => 'Kontakt',
                'contact.headline' => 'Erzähl mir von deinem Projekt.',
                'contact.lead' => 'Je konkreter, desto besser – aber eine grobe Idee reicht völlig für den Anfang. Ich melde mich in der Regel innerhalb von 24 Stunden mit einer ehrlichen Einschätzung.',
                'contact.direct' => 'Direkt schreiben',
                'contact.form.button' => 'Anfrage senden',

                // Fußzeile
                'footer.headline' => 'Projekt besprechen',
                'footer.lead' => 'Erzähl mir kurz, woran du arbeitest. Ich melde mich in der Regel innerhalb von 24 Stunden – auch wenn ich gerade keine Kapazität habe.',
                'footer.nav' => 'Navigation',
                'footer.elsewhere' => 'Woanders',

                // Fehlerseite
                'notFound.headline' => '404',
                'notFound.lead' => 'Diese Seite existiert nicht (mehr). Vielleicht hilft ein Blick in die Arbeiten.',
                'notFound.button' => 'Zur Startseite',
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
