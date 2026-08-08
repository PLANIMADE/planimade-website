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
    public function __construct(private Database $db) {}

    public function all(): array
    {
        $rows = $this->db->all('SELECT key, value FROM settings');
        $out = self::defaults();

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['value'], true);
            $out[$row['key']] = $decoded === null && $row['value'] !== 'null' ? $row['value'] : $decoded;
        }

        return $out;
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
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
            $this->db->run(
                'INSERT INTO settings (key, value, updated_at) VALUES (:key, :value, :updated_at)
                 ON CONFLICT(key) DO UPDATE SET value = :value, updated_at = :updated_at',
                ['key' => $key, 'value' => $encoded, 'updated_at' => gmdate('c')]
            );
        }

        return $this->all();
    }

    /** Struktur und Startwerte – gleichzeitig die Whitelist beim Speichern. */
    public static function defaults(): array
    {
        return [
            'name' => 'Dominic Majewski',
            'role' => '3D Artist · Motion Designer · Web Developer',
            'location' => 'Deutschland · remote weltweit',
            'tagline' => 'Ich baue digitale Welten, die man nicht mehr wegklicken will.',
            'intro' => "Hi, ich bin Dominic. Seit Jahren bewege ich mich zwischen Blender-Viewport, Unreal-Engine-Sequencer, Schnittprogramm und Code-Editor – und genau in dieser Mischung entsteht meine Arbeit: fotoreale Renderings, cinematische Bewegtbilder und Websites, die sich schnell und handgemacht anfühlen.\n\nMich interessiert dabei weniger der Effekt an sich als die Frage dahinter: Was soll jemand fühlen, wenn er das hier sieht? Von dort aus baue ich rückwärts – Licht, Rhythmus, Timing, Typo.",
            'availability' => [
                'status' => 'open',
                'label' => 'Offen für neue Projekte',
                'detail' => 'Nächster freier Slot ab sofort',
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
                    'title' => 'Web & Code',
                    'description' => 'Handgeschriebene, schnelle Websites – ohne Baukasten-Ballast, dafür mit sauberem Code.',
                    'items' => ['Astro', 'TypeScript', 'Tailwind', 'GSAP', 'PHP'],
                ],
            ],
            'process' => [
                ['title' => 'Briefing', 'description' => 'Wir klären Ziel, Zielgruppe und Rahmen. Ehrlich – auch wenn die Antwort mal "das braucht ihr nicht" lautet.'],
                ['title' => 'Konzept', 'description' => 'Moodboard, Referenzen, Look-Richtung. Bevor irgendetwas gerendert wird, steht das Bild im Kopf.'],
                ['title' => 'Produktion', 'description' => 'Modelling, Animation, Schnitt oder Code – mit festen Zwischenständen statt Blackbox.'],
                ['title' => 'Feinschliff', 'description' => 'Grading, Sound, Performance, Details. Der Teil, der den Unterschied macht.'],
            ],
            'seo' => [
                'title' => 'Dominic Majewski – 3D, Motion Design & Web Development',
                'description' => 'Portfolio von Dominic Majewski: fotorealistische 3D-Renderings in Blender, Echtzeit-Szenen in Unreal Engine 5, cinematischer Schnitt und handgeschriebene Websites.',
                'keywords' => '3D Artist, Blender, Unreal Engine, Motion Design, Videoschnitt, Webentwicklung, Portfolio',
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
