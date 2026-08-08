<?php

declare(strict_types=1);

namespace App;

/** Kundenstimmen – klein gehalten, aber im Dashboard voll pflegbar. */
final class Testimonials
{
    public function __construct(private Database $db, private array $config) {}

    public function list(bool $includeHidden = false): array
    {
        $where = $includeHidden ? '' : "WHERE t.status = 'published'";
        $rows = $this->db->all(
            "SELECT t.*, m.path AS avatar_path, m.thumb_path AS avatar_thumb, m.alt AS avatar_alt
             FROM testimonials t LEFT JOIN media m ON m.id = t.avatar_id
             {$where} ORDER BY t.position ASC, t.id DESC"
        );

        $base = rtrim((string) $this->config['uploads_url'], '/');

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'author' => $r['author'],
            'role' => $r['role'],
            'company' => $r['company'],
            'quote' => $r['quote'],
            'status' => $r['status'],
            'position' => (int) $r['position'],
            'avatarId' => $r['avatar_id'] === null ? null : (int) $r['avatar_id'],
            'avatarUrl' => empty($r['avatar_path']) ? null : $base . '/' . ltrim((string) ($r['avatar_thumb'] ?: $r['avatar_path']), '/'),
        ], $rows);
    }

    public function save(?int $id, array $input): array
    {
        $data = [
            'author' => mb_substr(trim((string) ($input['author'] ?? '')), 0, 120),
            'role' => mb_substr(trim((string) ($input['role'] ?? '')), 0, 120),
            'company' => mb_substr(trim((string) ($input['company'] ?? '')), 0, 120),
            'quote' => mb_substr(trim((string) ($input['quote'] ?? '')), 0, 2000),
            'avatar_id' => empty($input['avatarId']) ? null : (int) $input['avatarId'],
            'status' => in_array($input['status'] ?? 'published', ['published', 'hidden'], true) ? $input['status'] : 'published',
            'position' => (int) ($input['position'] ?? 0),
        ];

        if ($data['author'] === '' || $data['quote'] === '') {
            Http::error('Name und Zitat werden gebraucht.', 422);
        }

        if ($id === null) {
            $data['created_at'] = gmdate('c');
            $id = $this->db->insert('testimonials', $data);
        } else {
            $this->db->update('testimonials', $data, 'id = :where_id', ['where_id' => $id]);
        }

        foreach ($this->list(true) as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return [];
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM testimonials WHERE id = ?', [$id]);
    }
}
