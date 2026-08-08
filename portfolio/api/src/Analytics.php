<?php

declare(strict_types=1);

namespace App;

/**
 * Eigene, cookiefreie Statistik.
 *
 * Es werden keine IPs, keine Cookies und keine Fingerprints gespeichert –
 * nur ein täglich rotierender Hash, mit dem sich wiederkehrende Aufrufe
 * innerhalb eines Tages zusammenfassen lassen. Damit ist die Auswertung
 * ohne Cookie-Banner nutzbar.
 */
final class Analytics
{
    private const TYPES = ['pageview', 'project_view', 'contact_open', 'showreel_play', 'download', 'scroll_depth'];

    public function __construct(private Database $db, private Security $security) {}

    public function track(array $input): void
    {
        $type = (string) ($input['type'] ?? 'pageview');
        if (!in_array($type, self::TYPES, true)) {
            return;
        }

        $this->db->insert('analytics', [
            'type' => $type,
            'path' => mb_substr((string) ($input['path'] ?? '/'), 0, 190),
            'project_id' => isset($input['projectId']) && $input['projectId'] !== '' ? (int) $input['projectId'] : null,
            'referrer' => mb_substr($this->cleanReferrer((string) ($input['referrer'] ?? '')), 0, 190),
            'device' => in_array($input['device'] ?? '', ['mobile', 'tablet', 'desktop'], true) ? $input['device'] : '',
            'day' => gmdate('Y-m-d'),
            'visitor' => $this->security->visitorHash(),
            // Nur für die Lesetiefe belegt: 25, 50, 75 oder 100 Prozent.
            'value' => isset($input['value']) ? max(0, min(100, (int) $input['value'])) : null,
            'created_at' => gmdate('c'),
        ]);

        // Datensparsamkeit: Rohdaten älter als 400 Tage fliegen raus.
        if (random_int(1, 200) === 1) {
            $this->db->run('DELETE FROM analytics WHERE day < ?', [gmdate('Y-m-d', strtotime('-400 days'))]);
        }
    }

    public function summary(int $days = 30): array
    {
        $since = gmdate('Y-m-d', strtotime("-{$days} days"));

        $perDay = $this->db->all(
            "SELECT day,
                    COUNT(*) AS views,
                    COUNT(DISTINCT visitor) AS visitors
             FROM analytics
             WHERE day >= ? AND type = 'pageview'
             GROUP BY day ORDER BY day ASC",
            [$since]
        );

        $topPages = $this->db->all(
            "SELECT path, COUNT(*) AS views
             FROM analytics WHERE day >= ? AND type = 'pageview'
             GROUP BY path ORDER BY views DESC LIMIT 10",
            [$since]
        );

        $topProjects = $this->db->all(
            "SELECT p.id, p.title, p.slug, COUNT(a.id) AS views
             FROM analytics a JOIN projects p ON p.id = a.project_id
             WHERE a.day >= ? AND a.type = 'project_view'
             GROUP BY p.id ORDER BY views DESC LIMIT 10",
            [$since]
        );

        $referrers = $this->db->all(
            "SELECT referrer, COUNT(*) AS views
             FROM analytics WHERE day >= ? AND referrer != ''
             GROUP BY referrer ORDER BY views DESC LIMIT 8",
            [$since]
        );

        $devices = $this->db->all(
            "SELECT device, COUNT(*) AS views
             FROM analytics WHERE day >= ? AND device != ''
             GROUP BY device ORDER BY views DESC",
            [$since]
        );

        // Lesetiefe: Wie viele der Leute, die eine Case-Study geöffnet haben,
        // sind bis zum Ende gescrollt? Aussagekräftiger als reine Aufrufe.
        $readDepth = $this->db->all(
            "SELECT p.id, p.title, p.slug,
                    COUNT(DISTINCT CASE WHEN a.type = 'project_view' THEN a.visitor END) AS opened,
                    COUNT(DISTINCT CASE WHEN a.type = 'scroll_depth' AND a.value >= 75 THEN a.visitor END) AS finished
             FROM projects p
             LEFT JOIN analytics a ON a.project_id = p.id AND a.day >= ?
             WHERE p.deleted_at IS NULL
             GROUP BY p.id
             HAVING opened > 0
             ORDER BY opened DESC
             LIMIT 10",
            [$since]
        );

        return [
            'range' => ['days' => $days, 'since' => $since],
            'readDepth' => array_map(static function (array $row): array {
                $opened = (int) $row['opened'];
                $finished = (int) $row['finished'];

                return [
                    'id' => (int) $row['id'],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'opened' => $opened,
                    'finished' => $finished,
                    'share' => $opened > 0 ? (int) round($finished / $opened * 100) : 0,
                ];
            }, $readDepth),
            'totals' => [
                'views' => (int) $this->db->value("SELECT COUNT(*) FROM analytics WHERE day >= ? AND type = 'pageview'", [$since]),
                'visitors' => (int) $this->db->value("SELECT COUNT(DISTINCT visitor) FROM analytics WHERE day >= ? AND type = 'pageview'", [$since]),
                'projectViews' => (int) $this->db->value("SELECT COUNT(*) FROM analytics WHERE day >= ? AND type = 'project_view'", [$since]),
            ],
            'perDay' => $this->fillGaps($perDay, $days),
            'topPages' => $this->intify($topPages, 'views'),
            'topProjects' => $this->intify($topProjects, 'views'),
            'referrers' => $this->intify($referrers, 'views'),
            'devices' => $this->intify($devices, 'views'),
        ];
    }

    /** Tage ohne Aufrufe mit Nullwerten auffüllen – sonst lügt das Diagramm. */
    private function fillGaps(array $rows, int $days): array
    {
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = [
                'day' => $row['day'],
                'views' => (int) $row['views'],
                'visitors' => (int) $row['visitors'],
            ];
        }

        $out = [];
        for ($i = $days; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', strtotime("-{$i} days"));
            $out[] = $byDay[$day] ?? ['day' => $day, 'views' => 0, 'visitors' => 0];
        }

        return $out;
    }

    private function intify(array $rows, string $key): array
    {
        return array_map(static function (array $row) use ($key): array {
            $row[$key] = (int) $row[$key];
            if (isset($row['id'])) {
                $row['id'] = (int) $row['id'];
            }

            return $row;
        }, $rows);
    }

    /** Nur die Domain behalten – Query-Parameter können personenbezogen sein. */
    private function cleanReferrer(string $referrer): string
    {
        if ($referrer === '') {
            return '';
        }
        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', $host) ?? '' : '';
    }
}
