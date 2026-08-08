<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Dünne PDO-Schicht über SQLite inklusive selbstlaufender Migrationen.
 *
 * SQLite ist auf all-inkl ohne Einrichtung verfügbar: eine Datei, kein
 * Datenbank-Server, kein Passwort-Handling. Für ein Portfolio mit ein paar
 * hundert Projekten und Zugriffen ist das mehr als ausreichend – und ein
 * Backup ist ein simpler Datei-Download.
 */
final class Database
{
    private PDO $pdo;

    public function __construct(private array $config)
    {
        $dir = $config['storage_path'];
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Storage-Verzeichnis konnte nicht angelegt werden: ' . $dir);
        }

        $this->pdo = new PDO('sqlite:' . $dir . '/portfolio.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // WAL hält Lesezugriffe auch während Schreibvorgängen schnell.
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');

        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_NUM);

        return $row === false ? null : $row[0];
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );
        $this->run($sql, $data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): void
    {
        $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($data)));
        $this->run("UPDATE {$table} SET {$sets} WHERE {$where}", array_merge($data, $whereParams));
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Migrationen laufen bei jedem Request, sind aber idempotent und billig.
     * Neue Migration = neuer Eintrag am Ende des Arrays. Nie bestehende ändern.
     */
    private function migrate(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
        $applied = $this->pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($this->migrations() as $name => $sql) {
            if (in_array($name, $applied, true)) {
                continue;
            }
            $this->pdo->exec($sql);
            $this->run('INSERT INTO migrations (name, applied_at) VALUES (?, ?)', [$name, gmdate('c')]);
        }
    }

    /** @return array<string, string> */
    private function migrations(): array
    {
        return [
            '001_users' => <<<'SQL'
                CREATE TABLE users (
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    email         TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    name          TEXT NOT NULL DEFAULT '',
                    created_at    TEXT NOT NULL,
                    last_login_at TEXT
                );
            SQL,

            '002_sessions' => <<<'SQL'
                CREATE TABLE sessions (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    token_hash TEXT NOT NULL UNIQUE,
                    csrf_token TEXT NOT NULL,
                    ip_hash    TEXT NOT NULL DEFAULT '',
                    user_agent TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL,
                    expires_at TEXT NOT NULL
                );
                CREATE INDEX idx_sessions_expires ON sessions(expires_at);
            SQL,

            '003_media' => <<<'SQL'
                CREATE TABLE media (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    filename   TEXT NOT NULL,
                    path       TEXT NOT NULL,
                    thumb_path TEXT,
                    mime       TEXT NOT NULL,
                    kind       TEXT NOT NULL DEFAULT 'image',
                    size       INTEGER NOT NULL DEFAULT 0,
                    width      INTEGER,
                    height     INTEGER,
                    alt        TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL
                );
            SQL,

            '004_projects' => <<<'SQL'
                CREATE TABLE projects (
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    slug          TEXT NOT NULL UNIQUE,
                    title         TEXT NOT NULL,
                    subtitle      TEXT NOT NULL DEFAULT '',
                    summary       TEXT NOT NULL DEFAULT '',
                    body          TEXT NOT NULL DEFAULT '',
                    category      TEXT NOT NULL DEFAULT '',
                    client        TEXT NOT NULL DEFAULT '',
                    role          TEXT NOT NULL DEFAULT '',
                    year          INTEGER,
                    tools         TEXT NOT NULL DEFAULT '[]',
                    tags          TEXT NOT NULL DEFAULT '[]',
                    links         TEXT NOT NULL DEFAULT '[]',
                    metrics       TEXT NOT NULL DEFAULT '[]',
                    cover_id      INTEGER REFERENCES media(id) ON DELETE SET NULL,
                    preview_id    INTEGER REFERENCES media(id) ON DELETE SET NULL,
                    model_id      INTEGER REFERENCES media(id) ON DELETE SET NULL,
                    before_id     INTEGER REFERENCES media(id) ON DELETE SET NULL,
                    after_id      INTEGER REFERENCES media(id) ON DELETE SET NULL,
                    accent        TEXT NOT NULL DEFAULT '#a855f7',
                    status        TEXT NOT NULL DEFAULT 'draft',
                    featured      INTEGER NOT NULL DEFAULT 0,
                    position      INTEGER NOT NULL DEFAULT 0,
                    views         INTEGER NOT NULL DEFAULT 0,
                    created_at    TEXT NOT NULL,
                    updated_at    TEXT NOT NULL,
                    published_at  TEXT
                );
                CREATE INDEX idx_projects_status ON projects(status, position);
            SQL,

            '005_project_media' => <<<'SQL'
                CREATE TABLE project_media (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                    media_id   INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,
                    caption    TEXT NOT NULL DEFAULT '',
                    layout     TEXT NOT NULL DEFAULT 'full',
                    position   INTEGER NOT NULL DEFAULT 0
                );
                CREATE INDEX idx_project_media ON project_media(project_id, position);
            SQL,

            '006_messages' => <<<'SQL'
                CREATE TABLE messages (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    name       TEXT NOT NULL,
                    email      TEXT NOT NULL,
                    subject    TEXT NOT NULL DEFAULT '',
                    budget     TEXT NOT NULL DEFAULT '',
                    body       TEXT NOT NULL,
                    status     TEXT NOT NULL DEFAULT 'new',
                    ip_hash    TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL
                );
                CREATE INDEX idx_messages_status ON messages(status, created_at DESC);
            SQL,

            '007_settings' => <<<'SQL'
                CREATE TABLE settings (
                    key        TEXT PRIMARY KEY,
                    value      TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );
            SQL,

            '008_analytics' => <<<'SQL'
                CREATE TABLE analytics (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    type       TEXT NOT NULL,
                    path       TEXT NOT NULL DEFAULT '',
                    project_id INTEGER,
                    referrer   TEXT NOT NULL DEFAULT '',
                    device     TEXT NOT NULL DEFAULT '',
                    day        TEXT NOT NULL,
                    visitor    TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL
                );
                CREATE INDEX idx_analytics_day ON analytics(day, type);
            SQL,

            '009_rate_limits' => <<<'SQL'
                CREATE TABLE rate_limits (
                    key      TEXT PRIMARY KEY,
                    hits     INTEGER NOT NULL DEFAULT 0,
                    reset_at INTEGER NOT NULL
                );
            SQL,

            '010_testimonials' => <<<'SQL'
                CREATE TABLE testimonials (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    author     TEXT NOT NULL,
                    role       TEXT NOT NULL DEFAULT '',
                    company    TEXT NOT NULL DEFAULT '',
                    quote      TEXT NOT NULL,
                    avatar_id  INTEGER REFERENCES media(id) ON DELETE SET NULL,
                    status     TEXT NOT NULL DEFAULT 'published',
                    position   INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL
                );
            SQL,
        ];
    }
}
