<?php

declare(strict_types=1);

namespace App;

/**
 * Eine verschickte Mail im Gesendet-Ordner ablegen.
 *
 * IMAP kann nicht verschicken – das macht SMTP. Es kann aber etwas, das
 * SMTP nicht kann: eine fertige Nachricht in einen Ordner des Postfachs
 * legen. Genau das fehlt sonst: Google schreibt per SMTP verschickte Mails
 * von selbst in „Gesendet", die meisten anderen Anbieter nicht. Ohne diesen
 * Schritt stünde eine Bewerbung nirgends im eigenen Mailprogramm – und beim
 * Nachfassen zwei Wochen später wüsste man nicht mehr, was man geschrieben
 * hat.
 *
 * Gebraucht wird davon ein einziger Befehl (`APPEND`), deshalb hier von
 * Hand statt über die PHP-Erweiterung `imap`: Die ist auf geteilten
 * Webspaces oft nicht einkompiliert.
 *
 * Schlägt etwas fehl, ist das kein Grund, den Versand als gescheitert zu
 * melden – die Mail ist dann ja beim Empfänger. Fehler bleiben deshalb
 * hier und werden nur zurückgegeben.
 */
final class Imap
{
    private const TIMEOUT = 15;

    public function __construct(
        private string $host,
        private int $port,
        private string $benutzer,
        private string $passwort
    ) {}

    /** @return array{ok: bool, message: string} */
    public function ablegen(string $ordner, string $nachricht): array
    {
        $adresse = ($this->port === 143 ? '' : 'ssl://') . $this->host . ':' . $this->port;

        $nr = 0;
        $text = '';
        $verbindung = @stream_socket_client($adresse, $nr, $text, self::TIMEOUT);
        if ($verbindung === false) {
            return ['ok' => false, 'message' => 'Keine Verbindung zu ' . $this->host . ' (' . $text . ').'];
        }

        stream_set_timeout($verbindung, self::TIMEOUT);

        try {
            $this->lies($verbindung);

            $this->befehl($verbindung, 'a1', 'LOGIN ' . $this->zitat($this->benutzer) . ' ' . $this->zitat($this->passwort));

            // Der Ordner heißt je nach Anbieter anders. Existiert der
            // angegebene nicht, wird er angelegt – das ist billiger als zu
            // raten, ob er „Sent", „Gesendet" oder „INBOX.Sent" heißt.
            $roh = "\r\n" === substr($nachricht, -2) ? $nachricht : $nachricht . "\r\n";
            $befehl = 'APPEND ' . $this->zitat($ordner) . ' (\\Seen) {' . strlen($roh) . '}';

            fwrite($verbindung, "a2 {$befehl}\r\n");
            $antwort = $this->lies($verbindung);

            if (!str_starts_with(ltrim($antwort), '+')) {
                // Kein „weiter" vom Server: meist ein fehlender Ordner.
                $this->befehl($verbindung, 'a3', 'CREATE ' . $this->zitat($ordner));
                fwrite($verbindung, "a4 {$befehl}\r\n");
                $antwort = $this->lies($verbindung);

                if (!str_starts_with(ltrim($antwort), '+')) {
                    throw new \RuntimeException('Der Server nahm den Ordner nicht an: ' . trim($antwort));
                }
            }

            fwrite($verbindung, $roh);
            $ende = $this->bisAbschluss($verbindung);

            if (!preg_match('/^a\d+ OK/mi', $ende)) {
                throw new \RuntimeException(trim($ende));
            }

            $this->befehl($verbindung, 'a9', 'LOGOUT');
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            fclose($verbindung);
        }

        return ['ok' => true, 'message' => 'Im Ordner ' . $ordner . ' abgelegt.'];
    }

    /** @param resource $verbindung */
    private function befehl($verbindung, string $marke, string $befehl): void
    {
        fwrite($verbindung, "{$marke} {$befehl}\r\n");
        $antwort = $this->bisAbschluss($verbindung, $marke);

        if (!preg_match('/^' . preg_quote($marke, '/') . ' OK/mi', $antwort)) {
            throw new \RuntimeException(trim(explode("\n", trim($antwort))[0] ?? 'Unbekannter Fehler'));
        }
    }

    /** Eine Zeile. @param resource $verbindung */
    private function lies($verbindung): string
    {
        $zeile = fgets($verbindung, 8192);
        if ($zeile === false) {
            throw new \RuntimeException('Der Server hat nicht geantwortet.');
        }

        return $zeile;
    }

    /**
     * Liest, bis die Antwort auf den Befehl abgeschlossen ist.
     *
     * @param resource $verbindung
     */
    private function bisAbschluss($verbindung, string $marke = ''): string
    {
        $alles = '';

        for ($i = 0; $i < 200; $i++) {
            $zeile = fgets($verbindung, 8192);
            if ($zeile === false) {
                break;
            }

            $alles .= $zeile;
            $muster = $marke === '' ? '/^a\d+ (OK|NO|BAD)/i' : '/^' . preg_quote($marke, '/') . ' (OK|NO|BAD)/i';
            if (preg_match($muster, $zeile)) {
                break;
            }
        }

        return $alles;
    }

    private function zitat(string $wert): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $wert) . '"';
    }
}
