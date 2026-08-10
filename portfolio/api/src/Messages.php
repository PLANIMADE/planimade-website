<?php

declare(strict_types=1);

namespace App;

/**
 * Kontaktanfragen: landen in der Datenbank (Posteingang im Dashboard) und
 * zusätzlich – falls möglich – per Mail im Postfach.
 *
 * Die Datenbank ist bewusst die primäre Ablage: Mailversand über
 * Shared-Hosting scheitert gelegentlich still, eine Anfrage darf aber
 * niemals verloren gehen.
 */
final class Messages
{
    public function __construct(
        private Database $db,
        private Security $security,
        private array $config
    ) {}

    public function submit(array $input): array
    {
        // Honeypot: echte Menschen füllen dieses versteckte Feld nie aus.
        if (trim((string) ($input['website'] ?? '')) !== '') {
            return ['ok' => true];
        }

        $key = 'contact:' . $this->security->ipHash();
        if (!$this->security->attempt($key, (int) $this->config['contact_max_per_hour'], 3600)) {
            Http::error('Es wurden gerade sehr viele Anfragen von hier gesendet. Bitte später erneut versuchen.', 429);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $body = trim((string) ($input['message'] ?? $input['body'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if (mb_strlen($body) < 10) {
            $errors['message'] = 'Bitte kurz beschreiben, worum es geht (mind. 10 Zeichen).';
        }
        if ($errors !== []) {
            Http::error('Bitte die markierten Felder prüfen.', 422, ['fields' => $errors]);
        }

        $id = $this->db->insert('messages', [
            'name' => mb_substr($name, 0, 120),
            'email' => mb_substr($email, 0, 190),
            'subject' => mb_substr(trim((string) ($input['subject'] ?? '')), 0, 190),
            'budget' => mb_substr(trim((string) ($input['budget'] ?? '')), 0, 60),
            'body' => mb_substr($body, 0, 8000),
            'status' => 'new',
            'ip_hash' => $this->security->ipHash(),
            'created_at' => gmdate('c'),
        ]);

        $this->notify($id);

        return ['ok' => true, 'id' => $id];
    }

    public function list(?string $status = null): array
    {
        $where = $status !== null && $status !== '' && $status !== 'all' ? 'WHERE status = :status' : '';
        $params = $where !== '' ? ['status' => $status] : [];
        $rows = $this->db->all("SELECT * FROM messages {$where} ORDER BY created_at DESC LIMIT 500", $params);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'email' => $r['email'],
            'subject' => $r['subject'],
            'budget' => $r['budget'],
            'body' => $r['body'],
            'status' => $r['status'],
            // 1 = übergeben, -1 = abgelehnt, -2 = nicht versucht, 0 = älter als diese Zählung
            'notified' => (int) ($r['notified'] ?? 0),
            'createdAt' => $r['created_at'],
        ], $rows);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['new', 'read', 'archived'], true)) {
            Http::error('Unbekannter Status.', 422);
        }
        $this->db->update('messages', ['status' => $status], 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM messages WHERE id = ?', [$id]);
    }

    public function unreadCount(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM messages WHERE status = 'new'");
    }

    /**
     * Schickt die Anfrage zusätzlich per Mail.
     *
     * Das Ergebnis wird an der Nachricht festgehalten – sonst scheitert der
     * Versand lautlos und man sieht im Posteingang eine Nachricht, ohne zu
     * ahnen, dass im Postfach nie etwas ankam.
     *
     *  1 = übergeben, -1 = vom Server abgelehnt, -2 = gar nicht versucht
     */
    private function notify(int $id): void
    {
        $message = $this->db->first('SELECT * FROM messages WHERE id = ?', [$id]);
        if ($message === null) {
            return;
        }

        $mailer = new Mailer($this->config);
        if ($mailer->hindernis() !== '') {
            $this->db->update('messages', ['notified' => -2], 'id = :id', ['id' => $id]);

            return;
        }

        $zeilen = [
            'Name:    ' . $message['name'],
            'E-Mail:  ' . $message['email'],
            'Betreff: ' . ($message['subject'] !== '' ? $message['subject'] : '–'),
        ];

        if (($message['budget'] ?? '') !== '') {
            $zeilen[] = 'Budget:  ' . $message['budget'];
        }

        $zeilen = array_merge($zeilen, ['', (string) $message['body'], '', '— gesendet über das Kontaktformular']);

        $ergebnis = $mailer->send(
            'Neue Nachricht über das Portfolio von ' . $message['name'],
            implode("\n", $zeilen),
            (string) $message['email']
        );

        $this->db->update('messages', ['notified' => $ergebnis['ok'] ? 1 : -1], 'id = :id', ['id' => $id]);
    }
}
