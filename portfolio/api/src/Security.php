<?php

declare(strict_types=1);

namespace App;

/**
 * Rate-Limiting und Hilfsfunktionen zur Anonymisierung.
 *
 * IP-Adressen werden nie im Klartext gespeichert, sondern nur als gesalzener
 * Hash – das hält die Statistik DSGVO-freundlich und reicht völlig aus, um
 * Spam und Brute-Force zu bremsen.
 */
final class Security
{
    public function __construct(private Database $db, private array $config) {}

    /** Gibt true zurück, solange das Limit noch nicht erreicht ist. */
    public function attempt(string $key, int $max, int $decaySeconds): bool
    {
        $now = time();
        $this->db->run('DELETE FROM rate_limits WHERE reset_at < ?', [$now]);

        $row = $this->db->first('SELECT hits, reset_at FROM rate_limits WHERE key = ?', [$key]);
        if ($row === null) {
            $this->db->run(
                'INSERT INTO rate_limits (key, hits, reset_at) VALUES (?, 1, ?)',
                [$key, $now + $decaySeconds]
            );

            return true;
        }

        if ((int) $row['hits'] >= $max) {
            return false;
        }

        $this->db->run('UPDATE rate_limits SET hits = hits + 1 WHERE key = ?', [$key]);

        return true;
    }

    public function clear(string $key): void
    {
        $this->db->run('DELETE FROM rate_limits WHERE key = ?', [$key]);
    }

    /** Stabiler, nicht rückrechenbarer Besucher-Hash (rotiert täglich). */
    public function visitorHash(string $extra = ''): string
    {
        return substr(hash('sha256', implode('|', [
            Http::ip(),
            Http::userAgent(),
            gmdate('Y-m-d'),
            $this->salt(),
            $extra,
        ])), 0, 32);
    }

    public function ipHash(): string
    {
        return substr(hash('sha256', Http::ip() . '|' . $this->salt()), 0, 32);
    }

    /**
     * Einmalig erzeugtes, dauerhaft gespeichertes Salt.
     * Ohne dieses Salt wäre ein IP-Hash trivial zurückzurechnen.
     */
    private function salt(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $file = $this->config['storage_path'] . '/salt.key';
        if (is_file($file)) {
            return $cached = (string) file_get_contents($file);
        }

        $salt = bin2hex(random_bytes(32));
        file_put_contents($file, $salt);
        @chmod($file, 0600);

        return $cached = $salt;
    }
}
