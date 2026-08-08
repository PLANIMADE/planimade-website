<?php

declare(strict_types=1);

namespace App;

/**
 * Server-seitiges Ausliefern der Case-Study-Seiten.
 *
 * Das Astro-Build erzeugt eine statische Hülle (`/work/detail/index.html`).
 * Für `/work/mein-projekt` liefert PHP genau diese Hülle aus, injiziert aber
 * Titel, Meta-Tags, JSON-LD und die Projektdaten. Ergebnis: Inhalte sind
 * sofort live (kein Re-Build nötig) und Google/LinkedIn sehen trotzdem
 * vollwertige Seiten statt einer leeren Shell.
 */
final class Pages
{
    public function __construct(
        private Projects $projects,
        private Settings $settings,
        private Analytics $analytics,
        private array $config
    ) {}

    public function project(string $slug): never
    {
        $project = $this->projects->findBySlug(Str::slug($slug));
        if ($project === null) {
            $this->notFound();
        }

        $shell = $this->shell('work/detail/index.html');
        if ($shell === null) {
            Http::error('Die Seite wurde noch nicht gebaut. Bitte zuerst `npm run build` ausführen und hochladen.', 503);
        }

        $this->projects->trackView((int) $project['id']);
        $this->analytics->track([
            'type' => 'project_view',
            'path' => '/work/' . $project['slug'],
            'projectId' => $project['id'],
            'referrer' => Http::header('Referer'),
        ]);

        $settings = $this->settings->all();
        $siteUrl = $this->baseUrl();
        $name = (string) ($settings['name'] ?? 'Portfolio');

        $title = sprintf('%s – %s | %s', $project['title'], $project['category'] !== '' ? $project['category'] : 'Case Study', $name);
        $description = Str::excerpt($project['summary'] !== '' ? $project['summary'] : $project['body'], 180);
        $image = $project['cover']['url'] ?? '';
        if ($image !== '' && !str_starts_with($image, 'http')) {
            $image = $siteUrl . $image;
        }
        $canonical = $siteUrl . '/work/' . $project['slug'];

        $meta = $this->metaTags($title, $description, $image, $canonical, 'article');
        $meta .= $this->jsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $project['title'],
            'headline' => $project['title'],
            'description' => $description,
            'url' => $canonical,
            'image' => $image !== '' ? $image : null,
            'dateCreated' => $project['createdAt'],
            'datePublished' => $project['publishedAt'],
            'keywords' => implode(', ', array_merge($project['tags'], $project['tools'])),
            'creator' => ['@type' => 'Person', 'name' => $name],
        ]);

        $payload = json_encode(['project' => $project, 'settings' => $settings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?: '{}';
        $meta .= "\n<script id=\"project-data\" type=\"application/json\">{$payload}</script>";

        $html = $this->stripGenericSeo($shell);
        $html = preg_replace('#<title>.*?</title>#is', '<title>' . Str::escape($title) . '</title>', $html, 1) ?? $html;
        $html = str_replace('</head>', $meta . "\n</head>", $html);

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=0, must-revalidate');
        echo $html;
        exit;
    }

    /**
     * Die Hülle bringt allgemeine Meta-Tags mit. Vor dem Einsetzen der
     * projektspezifischen Angaben müssen die raus – sonst stünden zwei
     * Beschreibungen und ein falsches Canonical in der Seite.
     */
    private function stripGenericSeo(string $html): string
    {
        $patterns = [
            '#<meta\s+name="description"[^>]*>#i',
            '#<meta\s+property="og:[^"]*"[^>]*>#i',
            '#<meta\s+name="twitter:[^"]*"[^>]*>#i',
            '#<link\s+rel="canonical"[^>]*>#i',
        ];

        return preg_replace($patterns, '', $html) ?? $html;
    }

    /**
     * Basis-URL: bevorzugt aus der Konfiguration, sonst aus der aktuellen
     * Anfrage. So funktionieren Canonical-Links und Sitemap auch dann,
     * wenn `site_url` noch nicht gesetzt wurde.
     */
    private function baseUrl(): string
    {
        $configured = rtrim((string) $this->config['site_url'], '/');
        if ($configured !== '') {
            return $configured;
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return (Http::isHttps() ? 'https://' : 'http://') . $host;
    }

    public function sitemap(): never
    {
        $siteUrl = $this->baseUrl();
        $urls = [
            ['loc' => $siteUrl . '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => $siteUrl . '/work/', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $siteUrl . '/about/', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => $siteUrl . '/contact/', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ];

        foreach ($this->projects->list() as $project) {
            $urls[] = [
                'loc' => $siteUrl . '/work/' . $project['slug'],
                'lastmod' => substr((string) $project['updatedAt'], 0, 10),
                'priority' => '0.8',
                'changefreq' => 'monthly',
            ];
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            echo "  <url>\n";
            foreach ($url as $key => $value) {
                if ($value !== '' && $value !== null) {
                    echo "    <{$key}>" . Str::escape((string) $value) . "</{$key}>\n";
                }
            }
            echo "  </url>\n";
        }
        echo '</urlset>';
        exit;
    }

    private function notFound(): never
    {
        $shell = $this->shell('404.html') ?? $this->shell('404/index.html');
        http_response_code(404);

        if ($shell === null) {
            Http::error('Projekt nicht gefunden.', 404);
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $shell;
        exit;
    }

    private function shell(string $relativePath): ?string
    {
        $path = rtrim((string) $this->config['public_path'], '/') . '/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function metaTags(string $title, string $description, string $image, string $canonical, string $type): string
    {
        $tags = [
            ['name' => 'description', 'content' => $description],
            ['property' => 'og:type', 'content' => $type],
            ['property' => 'og:title', 'content' => $title],
            ['property' => 'og:description', 'content' => $description],
            ['property' => 'og:url', 'content' => $canonical],
            ['name' => 'twitter:card', 'content' => 'summary_large_image'],
            ['name' => 'twitter:title', 'content' => $title],
            ['name' => 'twitter:description', 'content' => $description],
        ];

        if ($image !== '') {
            $tags[] = ['property' => 'og:image', 'content' => $image];
            $tags[] = ['name' => 'twitter:image', 'content' => $image];
        }

        $html = "\n<link rel=\"canonical\" href=\"" . Str::escape($canonical) . "\">";
        foreach ($tags as $tag) {
            $attribute = isset($tag['property']) ? 'property' : 'name';
            $html .= sprintf(
                "\n<meta %s=\"%s\" content=\"%s\">",
                $attribute,
                $tag[$attribute],
                Str::escape($tag['content'])
            );
        }

        return $html;
    }

    private function jsonLd(array $data): string
    {
        $clean = array_filter($data, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?: '{}';

        return "\n<script type=\"application/ld+json\">{$json}</script>";
    }
}
