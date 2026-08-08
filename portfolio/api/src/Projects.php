<?php

declare(strict_types=1);

namespace App;

/**
 * Alles rund um Projekte/Case-Studies: lesen, schreiben, sortieren.
 *
 * Nach außen wird bewusst camelCase geliefert, damit sich die Daten im
 * Frontend natürlich anfühlen; in der Datenbank bleibt es snake_case.
 */
final class Projects
{
    private const JSON_FIELDS = ['tools', 'tags', 'links', 'metrics', 'palette'];

    private const WRITABLE = [
        'title', 'subtitle', 'summary', 'body', 'category', 'client', 'role',
        'year', 'accent', 'status', 'featured', 'cover_id', 'preview_id',
        'model_id', 'before_id', 'after_id', 'display', 'card_format',
    ];

    public function __construct(private Database $db, private array $config) {}

    /** @return array<int, array<string, mixed>> */
    public function list(bool $includeDrafts = false, ?string $category = null, ?int $limit = null): array
    {
        // Papierkorb-Einträge tauchen in keiner normalen Liste auf.
        $where = ($includeDrafts ? '1 = 1' : "status = 'published'") . ' AND deleted_at IS NULL';
        $params = [];

        if ($category !== null && $category !== '' && $category !== 'all') {
            $where .= ' AND lower(category) = :category';
            $params['category'] = mb_strtolower($category);
        }

        $sql = "SELECT * FROM projects WHERE {$where} ORDER BY position ASC, id DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }

        $rows = $this->db->all($sql, $params);
        $galleries = $this->galleriesFor(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $mediaMap = $this->mediaMapFor($rows);

        return array_map(
            fn (array $row): array => $this->present($row, $galleries[(int) $row['id']] ?? [], $mediaMap),
            $rows
        );
    }

    public function findBySlug(string $slug, bool $includeDrafts = false): ?array
    {
        $row = $this->db->first('SELECT * FROM projects WHERE slug = ? AND deleted_at IS NULL', [$slug]);
        if ($row === null) {
            return null;
        }
        if (!$includeDrafts && $row['status'] !== 'published') {
            return null;
        }

        $galleries = $this->galleriesFor([(int) $row['id']]);

        return $this->present($row, $galleries[(int) $row['id']] ?? [], $this->mediaMapFor([$row]));
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->first('SELECT * FROM projects WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }

        $galleries = $this->galleriesFor([$id]);

        return $this->present($row, $galleries[$id] ?? [], $this->mediaMapFor([$row]));
    }

    public function create(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            Http::error('Ein Titel wird gebraucht.', 422);
        }

        $now = gmdate('c');
        $data = array_merge($this->mapInput($input), [
            'title' => $title,
            'slug' => $this->uniqueSlug((string) ($input['slug'] ?? $title)),
            'position' => (int) ($this->db->value('SELECT COALESCE(MAX(position), 0) + 1 FROM projects') ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (self::JSON_FIELDS as $field) {
            $data[$field] = $this->encodeJson($input[$this->camel($field)] ?? $input[$field] ?? []);
        }

        if (($data['status'] ?? 'draft') === 'published') {
            $data['published_at'] = $now;
        }

        $id = $this->db->insert('projects', $data);
        $this->syncGallery($id, $input['gallery'] ?? null);

        return $this->findById($id) ?? [];
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->db->first('SELECT * FROM projects WHERE id = ?', [$id]);
        if ($existing === null) {
            Http::error('Projekt nicht gefunden.', 404);
        }

        $data = $this->mapInput($input);
        $data['updated_at'] = gmdate('c');

        if (isset($input['slug']) && trim((string) $input['slug']) !== '') {
            $data['slug'] = $this->uniqueSlug((string) $input['slug'], $id);
        }

        foreach (self::JSON_FIELDS as $field) {
            $value = $input[$this->camel($field)] ?? $input[$field] ?? null;
            if ($value !== null) {
                $data[$field] = $this->encodeJson($value);
            }
        }

        // Erstveröffentlichung festhalten – spätere Updates ändern das Datum nicht.
        if (($data['status'] ?? $existing['status']) === 'published' && $existing['published_at'] === null) {
            $data['published_at'] = gmdate('c');
        }

        $this->db->update('projects', $data, 'id = :where_id', ['where_id' => $id]);

        if (array_key_exists('gallery', $input)) {
            $this->syncGallery($id, $input['gallery']);
        }

        return $this->findById($id) ?? [];
    }

    /**
     * Verschiebt ein Projekt in den Papierkorb. Es verschwindet sofort von der
     * Website, bleibt aber wiederherstellbar – Löschen soll kein Unfall sein.
     */
    public function delete(int $id): void
    {
        $this->db->update('projects', ['deleted_at' => gmdate('c')], 'id = :where_id', ['where_id' => $id]);
        $this->purgeExpired();
    }

    public function restore(int $id): ?array
    {
        $this->db->update('projects', ['deleted_at' => null], 'id = :where_id', ['where_id' => $id]);

        return $this->findById($id);
    }

    /** Endgültig löschen – nur aus dem Papierkorb heraus möglich. */
    public function purge(int $id): void
    {
        $this->db->run('DELETE FROM projects WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function trash(): array
    {
        $rows = $this->db->all('SELECT * FROM projects WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC');
        $mediaMap = $this->mediaMapFor($rows);
        $days = (int) ($this->config['trash_days'] ?? 30);

        return array_map(function (array $row) use ($mediaMap, $days): array {
            $project = $this->present($row, [], $mediaMap);
            $project['deletedAt'] = $row['deleted_at'];
            $project['purgeAt'] = gmdate('c', strtotime((string) $row['deleted_at']) + $days * 86400);

            return $project;
        }, $rows);
    }

    /** Räumt den Papierkorb auf: älter als `trash_days` wird endgültig entfernt. */
    public function purgeExpired(): int
    {
        $days = (int) ($this->config['trash_days'] ?? 30);
        $cutoff = gmdate('c', time() - $days * 86400);

        $stmt = $this->db->run('DELETE FROM projects WHERE deleted_at IS NOT NULL AND deleted_at < ?', [$cutoff]);

        return $stmt->rowCount();
    }

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds): void
    {
        $this->db->transaction(function (Database $db) use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                $db->run('UPDATE projects SET position = ? WHERE id = ?', [$index + 1, (int) $id]);
            }
        });
    }

    public function trackView(int $id): void
    {
        $this->db->run('UPDATE projects SET views = views + 1 WHERE id = ?', [$id]);
    }

    /** @return array<int, string> */
    public function categories(): array
    {
        $rows = $this->db->all(
            "SELECT DISTINCT category FROM projects
             WHERE status = 'published' AND category != '' AND deleted_at IS NULL
             ORDER BY category"
        );

        return array_map(static fn (array $r): string => (string) $r['category'], $rows);
    }

    // ------------------------------------------------------------------
    // Interna
    // ------------------------------------------------------------------

    private function mapInput(array $input): array
    {
        $map = [
            'title' => 'title', 'subtitle' => 'subtitle', 'summary' => 'summary',
            'body' => 'body', 'category' => 'category', 'client' => 'client',
            'role' => 'role', 'year' => 'year', 'accent' => 'accent',
            'status' => 'status', 'featured' => 'featured',
            'display' => 'display', 'cardFormat' => 'card_format',
            'coverId' => 'cover_id', 'previewId' => 'preview_id',
            'modelId' => 'model_id', 'beforeId' => 'before_id', 'afterId' => 'after_id',
        ];

        $data = [];
        foreach ($map as $key => $column) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];

            $data[$column] = match ($column) {
                'year' => $value === null || $value === '' ? null : (int) $value,
                'featured' => (int) (bool) $value,
                'status' => in_array($value, ['draft', 'published'], true) ? $value : 'draft',
                'display' => in_array($value, ['cover', 'contain'], true) ? $value : 'cover',
                'card_format' => in_array($value, ['landscape', 'square', 'portrait'], true) ? $value : 'landscape',
                'cover_id', 'preview_id', 'model_id', 'before_id', 'after_id' => $value === null || $value === '' ? null : (int) $value,
                default => is_string($value) ? trim($value) : (string) $value,
            };
        }

        return array_intersect_key($data, array_flip(self::WRITABLE));
    }

    private function syncGallery(int $projectId, mixed $gallery): void
    {
        if (!is_array($gallery)) {
            return;
        }

        $this->db->transaction(function (Database $db) use ($projectId, $gallery): void {
            $db->run('DELETE FROM project_media WHERE project_id = ?', [$projectId]);
            foreach (array_values($gallery) as $index => $item) {
                $mediaId = (int) ($item['mediaId'] ?? $item['media_id'] ?? 0);
                if ($mediaId <= 0) {
                    continue;
                }
                $db->insert('project_media', [
                    'project_id' => $projectId,
                    'media_id' => $mediaId,
                    'caption' => trim((string) ($item['caption'] ?? '')),
                    'layout' => in_array($item['layout'] ?? 'full', ['full', 'half', 'wide'], true) ? $item['layout'] : 'full',
                    'position' => $index + 1,
                ]);
            }
        });
    }

    /** @param array<int, int> $projectIds @return array<int, array<int, array>> */
    private function galleriesFor(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->all(
            "SELECT pm.project_id, pm.caption, pm.layout, pm.position, m.*
             FROM project_media pm JOIN media m ON m.id = pm.media_id
             WHERE pm.project_id IN ({$placeholders})
             ORDER BY pm.position ASC",
            $projectIds
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['project_id']][] = [
                'caption' => $row['caption'],
                'layout' => $row['layout'],
                'media' => Media::present($row, $this->config),
            ];
        }

        return $out;
    }

    /** Lädt alle in den Projekten referenzierten Medien in einem Rutsch. */
    private function mediaMapFor(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            foreach (['cover_id', 'preview_id', 'model_id', 'before_id', 'after_id'] as $key) {
                if (!empty($row[$key])) {
                    $ids[(int) $row[$key]] = true;
                }
            }
        }
        if ($ids === []) {
            return [];
        }

        $ids = array_keys($ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $media = $this->db->all("SELECT * FROM media WHERE id IN ({$placeholders})", $ids);

        $map = [];
        foreach ($media as $item) {
            $map[(int) $item['id']] = Media::present($item, $this->config);
        }

        return $map;
    }

    private function present(array $row, array $gallery, array $mediaMap): array
    {
        // SQLite liefert je nach Spalte int oder string zurück – beides zulassen.
        $media = static fn (int|string|null $id): ?array => $id === null || $id === '' ? null : ($mediaMap[(int) $id] ?? null);

        return [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'subtitle' => $row['subtitle'],
            'summary' => $row['summary'],
            'body' => $row['body'],
            'category' => $row['category'],
            'client' => $row['client'],
            'role' => $row['role'],
            'year' => $row['year'] === null ? null : (int) $row['year'],
            'tools' => $this->decodeJson($row['tools']),
            'tags' => $this->decodeJson($row['tags']),
            'links' => $this->decodeJson($row['links']),
            'metrics' => $this->decodeJson($row['metrics']),
            'palette' => $this->decodeJson($row['palette'] ?? '[]'),
            'accent' => $row['accent'],
            // Wie das Titelbild gezeigt wird: formatfüllend oder vollständig.
            'display' => $row['display'] ?? 'cover',
            'cardFormat' => $row['card_format'] ?? 'landscape',
            'status' => $row['status'],
            'featured' => (bool) $row['featured'],
            'position' => (int) $row['position'],
            'views' => (int) $row['views'],
            'cover' => $media($row['cover_id']),
            'preview' => $media($row['preview_id']),
            'model' => $media($row['model_id']),
            'before' => $media($row['before_id']),
            'after' => $media($row['after_id']),
            'gallery' => $gallery,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
            'publishedAt' => $row['published_at'],
        ];
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'projekt';
        }

        $slug = $base;
        $suffix = 2;
        while (true) {
            $existing = $this->db->first('SELECT id FROM projects WHERE slug = ?', [$slug]);
            if ($existing === null || ($ignoreId !== null && (int) $existing['id'] === $ignoreId)) {
                return $slug;
            }
            $slug = $base . '-' . $suffix++;
        }
    }

    private function encodeJson(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($v): bool => $v !== ''));
        }

        return json_encode(is_array($value) ? array_values($value) : [], JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function decodeJson(?string $raw): array
    {
        $decoded = json_decode($raw ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }

    private function camel(string $snake): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $snake))));
    }
}
