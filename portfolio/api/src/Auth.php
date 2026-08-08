<?php

declare(strict_types=1);

namespace App;

/**
 * Login per Session-Token im HttpOnly-Cookie.
 *
 * Das Token liegt nur als Hash in der Datenbank – wer die Datei in die Hände
 * bekäme, könnte sich damit trotzdem nicht einloggen. Schreibende Zugriffe
 * verlangen zusätzlich den CSRF-Token im Header `X-CSRF-Token`.
 */
final class Auth
{
    private ?array $user = null;
    private ?array $session = null;
    private bool $resolved = false;

    public function __construct(
        private Database $db,
        private Security $security,
        private array $config
    ) {}

    public function login(string $email, string $password): array
    {
        $throttleKey = 'login:' . $this->security->ipHash();
        if (!$this->security->attempt($throttleKey, (int) $this->config['login_max_attempts'], (int) $this->config['login_decay_minutes'] * 60)) {
            Http::error('Zu viele Login-Versuche. Bitte in ein paar Minuten erneut probieren.', 429);
        }

        $user = $this->db->first('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            // Gleiche Antwort für "Benutzer unbekannt" und "Passwort falsch".
            Http::error('E-Mail oder Passwort ist falsch.', 401);
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        $this->security->clear($throttleKey);
        $this->db->update('users', ['last_login_at' => gmdate('c')], 'id = :id', ['id' => $user['id']]);

        return $this->startSession($user);
    }

    public function startSession(array $user): array
    {
        $this->purgeExpired();

        $token = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(24));
        $expires = time() + (int) $this->config['session_lifetime'];

        $this->db->insert('sessions', [
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token),
            'csrf_token' => $csrf,
            'ip_hash' => $this->security->ipHash(),
            'user_agent' => Http::userAgent(),
            'created_at' => gmdate('c'),
            'expires_at' => gmdate('c', $expires),
        ]);

        Http::cookie($this->config['session_cookie'], $token, $expires);

        return [
            'user' => $this->publicUser($user),
            'csrfToken' => $csrf,
        ];
    }

    public function logout(): void
    {
        $token = $_COOKIE[$this->config['session_cookie']] ?? '';
        if ($token !== '') {
            $this->db->run('DELETE FROM sessions WHERE token_hash = ?', [hash('sha256', $token)]);
        }
        Http::cookie($this->config['session_cookie'], '', time() - 3600);
    }

    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;

        $token = $_COOKIE[$this->config['session_cookie']] ?? '';
        if ($token === '') {
            return null;
        }

        $row = $this->db->first(
            'SELECT s.id AS session_id, s.csrf_token, s.expires_at, u.*
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = ?',
            [hash('sha256', $token)]
        );

        if ($row === null) {
            return null;
        }

        if (strtotime($row['expires_at']) < time()) {
            $this->db->run('DELETE FROM sessions WHERE id = ?', [$row['session_id']]);

            return null;
        }

        $this->session = ['id' => $row['session_id'], 'csrf_token' => $row['csrf_token']];

        return $this->user = $row;
    }

    /** Erzwingt einen eingeloggten Benutzer, sonst 401. */
    public function requireUser(): array
    {
        $user = $this->user();
        if ($user === null) {
            Http::error('Nicht eingeloggt.', 401);
        }

        return $user;
    }

    /** Zusätzlich zum Login: CSRF-Token prüfen (für alle schreibenden Routen). */
    public function requireWrite(): array
    {
        $user = $this->requireUser();
        $sent = Http::header('X-CSRF-Token');

        if ($sent === '' || !hash_equals((string) $this->session['csrf_token'], $sent)) {
            Http::error('Sicherheits-Token ungültig. Bitte Seite neu laden.', 419);
        }

        return $user;
    }

    public function csrfToken(): ?string
    {
        $this->user();

        return $this->session['csrf_token'] ?? null;
    }

    public function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'lastLoginAt' => $user['last_login_at'] ?? null,
        ];
    }

    public function changePassword(array $user, string $current, string $new): void
    {
        if (!password_verify($current, $user['password_hash'])) {
            Http::error('Aktuelles Passwort ist falsch.', 422);
        }
        if (mb_strlen($new) < 10) {
            Http::error('Das neue Passwort muss mindestens 10 Zeichen haben.', 422);
        }

        $this->db->update(
            'users',
            ['password_hash' => password_hash($new, PASSWORD_DEFAULT)],
            'id = :id',
            ['id' => $user['id']]
        );

        // Alle anderen Sitzungen beenden – die aktuelle bleibt bestehen.
        $this->db->run(
            'DELETE FROM sessions WHERE user_id = ? AND id != ?',
            [$user['id'], $this->session['id'] ?? 0]
        );
    }

    private function purgeExpired(): void
    {
        $this->db->run('DELETE FROM sessions WHERE expires_at < ?', [gmdate('c')]);
    }
}
