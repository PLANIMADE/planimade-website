<?php

declare(strict_types=1);

namespace App;

/**
 * Mailversand über ein richtiges Postfach statt über `mail()`.
 *
 * Warum das sein muss: `mail()` übergibt die Nachricht an das Mailprogramm
 * des Servers und weiß danach nichts mehr. Ob sie angenommen, verworfen oder
 * als Spam einsortiert wurde, erfährt man nicht – „true" heißt nur
 * „übergeben". Auf geteilten Webspaces kommt dazu, dass der Server als
 * Absender sein Systemkonto einträgt; Empfänger wie Gmail prüfen das gegen
 * die Domain im Absenderfeld und werfen die Mail bei Nichtübereinstimmung
 * weg. Man sitzt dann vor einem Formular, das sich meldet „ist raus", und
 * einem Postfach, in dem nie etwas ankommt.
 *
 * Mit einem eigenen Postfach ist das anders: Hier wird angemeldet, und der
 * Mailserver antwortet auf jeden Schritt. Geht etwas schief, steht der Grund
 * im Dashboard statt in einem Logfile, das niemand liest.
 *
 * Bewusst ohne Fremdbibliothek: Auf diesem Webspace gibt es keinen Composer.
 * Gebraucht wird ohnehin nur der einfache Fall – eine Nachricht, ein
 * Empfänger, reiner Text.
 */
final class Smtp
{
    private const TIMEOUT = 15;

    /** @var resource|null */
    private $verbindung = null;

    private string $protokoll = '';

    public function __construct(
        private string $host,
        private int $port,
        private string $benutzer,
        private string $passwort,
        /** 'ssl' = von Anfang an verschlüsselt (Port 465), 'tls' = STARTTLS (587). */
        private string $sicherheit = 'auto'
    ) {}

    /**
     * @param array{from: string, fromName: string, to: string, subject: string, body: string, replyTo: string} $mail
     * @return array{ok: bool, message: string}
     */
    public function send(array $mail): array
    {
        $ssl = $this->sicherheit === 'ssl' || ($this->sicherheit === 'auto' && $this->port === 465);
        $adresse = ($ssl ? 'ssl://' : '') . $this->host . ':' . $this->port;

        $fehlerNr = 0;
        $fehlerText = '';
        $verbindung = @stream_socket_client(
            $adresse,
            $fehlerNr,
            $fehlerText,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['SNI_enabled' => true]])
        );

        if ($verbindung === false) {
            return $this->fehler('Keine Verbindung zu ' . $this->host . ':' . $this->port . ' (' . $fehlerText . ').');
        }

        $this->verbindung = $verbindung;
        stream_set_timeout($this->verbindung, self::TIMEOUT);

        try {
            $this->erwarte('220');
            $this->befehl('EHLO ' . $this->helo(), '250');

            if (!$ssl && $this->sicherheit !== 'none') {
                $this->befehl('STARTTLS', '220');
                if (!@stream_socket_enable_crypto($this->verbindung, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return $this->fehler('Die Verschlüsselung (STARTTLS) ließ sich nicht aufbauen.');
                }
                // Nach dem Umschalten beginnt die Sitzung von vorn.
                $this->befehl('EHLO ' . $this->helo(), '250');
            }

            $this->befehl('AUTH LOGIN', '334');
            $this->befehl(base64_encode($this->benutzer), '334');
            $this->befehl(base64_encode($this->passwort), '235');

            $this->befehl('MAIL FROM:<' . $mail['from'] . '>', '250');
            $this->befehl('RCPT TO:<' . $mail['to'] . '>', '250');
            $this->befehl('DATA', '354');
            $this->schreibe(self::nachrichtText($mail) . "\r\n.\r\n");
            $this->erwarte('250');
            $this->befehl('QUIT', '221');
        } catch (\RuntimeException $e) {
            return $this->fehler($e->getMessage());
        } finally {
            if (is_resource($this->verbindung)) {
                fclose($this->verbindung);
            }
            $this->verbindung = null;
        }

        return ['ok' => true, 'message' => 'Der Mailserver hat die Nachricht angenommen.'];
    }

    // ------------------------------------------------------------------

    /**
     * Baut die fertige Nachricht.
     *
     * Öffentlich und statisch, weil dieselbe Nachricht ein zweites Mal
     * gebraucht wird: Was verschickt wurde, soll unverändert im
     * Gesendet-Ordner landen (siehe `Imap`).
     *
     * @param array{from: string, fromName: string, to: string, subject: string, body: string, replyTo: string} $mail
     */
    public static function nachrichtText(array $mail): string
    {
        $kopf = [
            'Date: ' . date('r'),
            'From: ' . self::kodiere($mail['fromName']) . ' <' . $mail['from'] . '>',
            'To: <' . $mail['to'] . '>',
            'Subject: ' . self::kodiere($mail['subject']),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::eigenerHost() . '>',
        ];

        if ($mail['replyTo'] !== '') {
            $kopf[] = 'Reply-To: <' . $mail['replyTo'] . '>';
        }

        // Eine Zeile, die nur aus einem Punkt besteht, würde den Text
        // vorzeitig beenden – deshalb wird sie verdoppelt.
        $text = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $mail['body']));
        $text = str_replace("\n", "\r\n", (string) $text);

        return implode("\r\n", $kopf) . "\r\n\r\n" . $text;
    }

    private static function kodiere(string $text): string
    {
        return preg_match('/[^\x20-\x7e]/', $text) === 1
            ? '=?UTF-8?B?' . base64_encode($text) . '?='
            : $text;
    }

    private function helo(): string
    {
        return self::eigenerHost();
    }

    private static function eigenerHost(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return preg_replace('/[^a-zA-Z0-9.\-]/', '', explode(':', $host)[0]) ?: 'localhost';
    }

    private function befehl(string $befehl, string $erwartet): void
    {
        $this->schreibe($befehl . "\r\n");
        $this->erwarte($erwartet);
    }

    private function schreibe(string $text): void
    {
        if (!is_resource($this->verbindung) || @fwrite($this->verbindung, $text) === false) {
            throw new \RuntimeException('Die Verbindung zum Mailserver ist abgebrochen.');
        }
    }

    /** Liest die Antwort und prüft den Code. Mehrzeilige Antworten enden ohne Bindestrich. */
    private function erwarte(string $code): void
    {
        $antwort = '';

        while (is_resource($this->verbindung)) {
            $zeile = fgets($this->verbindung, 1024);
            if ($zeile === false) {
                throw new \RuntimeException('Der Mailserver hat nicht geantwortet.');
            }

            $antwort .= $zeile;
            if (strlen($zeile) < 4 || $zeile[3] !== '-') {
                break;
            }
        }

        $this->protokoll .= $antwort;

        if (!str_starts_with($antwort, $code)) {
            throw new \RuntimeException('Der Mailserver antwortete: ' . trim($antwort));
        }
    }

    /** @return array{ok: false, message: string} */
    private function fehler(string $text): array
    {
        return ['ok' => false, 'message' => $text];
    }
}
