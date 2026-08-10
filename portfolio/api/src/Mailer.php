<?php

declare(strict_types=1);

namespace App;

/**
 * Mailversand – eine Stelle für alle, die verschicken.
 *
 * Vorher stand derselbe Aufruf zweimal im Code (Kontaktformular und
 * Testmail). Beide waren leicht verschieden, und keiner setzte den
 * Umschlag-Absender. Genau daran scheitert Mailversand auf geteilten
 * Webspaces am häufigsten:
 *
 * Eine Mail hat zwei Absender. Den, der im Programm steht (`From:`), und
 * den, den der Server beim Zustellen nennt (der Umschlag). Ohne Angabe
 * nimmt PHP für den Umschlag das Systemkonto des Webspaces – etwa
 * `w01abcde@server42.example`. Der Empfänger prüft dann, ob dieser Server
 * überhaupt für die Domain im Umschlag verschicken darf (SPF), und ob
 * Umschlag und `From:` zusammenpassen (DMARC). Bei Gmail heißt „passt
 * nicht" im besten Fall Spam-Ordner, im schlechteren: stillschweigend
 * verworfen. Die Mail ist dann raus, kommt aber nirgends an – und genau so
 * sah es aus.
 *
 * Deshalb geht der Absender hier als fünfter Parameter mit (`-f`).
 */
final class Mailer
{
    public function __construct(private array $config) {}

    public function absender(): string
    {
        return trim((string) ($this->config['mail_from'] ?? ''));
    }

    public function empfaenger(): string
    {
        return trim((string) ($this->config['mail_to'] ?? ''));
    }

    /**
     * Was dem Versand im Weg steht – leerer Text heißt: bereit.
     *
     * Wird an zwei Stellen gebraucht: im Systemcheck, damit man es sieht,
     * bevor die erste Anfrage hereinkommt, und beim Verschicken selbst.
     */
    /** Ist ein eigenes Postfach hinterlegt, geht die Mail darüber. */
    public function ueberPostfach(): bool
    {
        return trim((string) ($this->config['smtp_host'] ?? '')) !== ''
            && trim((string) ($this->config['smtp_user'] ?? '')) !== '';
    }

    public function hindernis(): string
    {
        if (($this->config['mail_enabled'] ?? true) !== true) {
            return 'Der Mailversand ist unter Einstellungen → Adresse & Versand abgeschaltet.';
        }
        if ($this->empfaenger() === '') {
            return 'Es ist kein Empfänger hinterlegt (Einstellungen → Adresse & Versand).';
        }
        if ($this->absender() === '') {
            return 'Es ist kein Absender hinterlegt und keiner aus der Adresse der Website ableitbar.';
        }
        if (!$this->ueberPostfach() && !function_exists('mail')) {
            return 'Diese PHP-Installation kann keine Mails versenden. Bitte ein Postfach hinterlegen.';
        }

        return '';
    }

    /**
     * Verschickt eine Mail an die hinterlegte Adresse.
     *
     * @return array{ok: bool, message: string}
     */
    public function send(string $betreff, string $text, string $antwortAn = ''): array
    {
        $hindernis = $this->hindernis();
        if ($hindernis !== '') {
            return ['ok' => false, 'message' => $hindernis];
        }

        $to = $this->empfaenger();
        $from = $this->absender();

        // Der verlässliche Weg zuerst: Über ein angemeldetes Postfach
        // antwortet der Mailserver auf jeden Schritt, und ein „abgelehnt"
        // ist ein echtes Abgelehnt mit Begründung.
        if ($this->ueberPostfach()) {
            $smtp = new Smtp(
                trim((string) $this->config['smtp_host']),
                (int) ($this->config['smtp_port'] ?? 587),
                trim((string) $this->config['smtp_user']),
                (string) ($this->config['smtp_pass'] ?? ''),
                (string) ($this->config['smtp_security'] ?? 'auto')
            );

            return $smtp->send([
                'from' => $from,
                'fromName' => 'Portfolio',
                'to' => $to,
                'subject' => $betreff,
                'body' => $text,
                'replyTo' => $antwortAn !== '' && filter_var($antwortAn, FILTER_VALIDATE_EMAIL) !== false ? $antwortAn : '',
            ]);
        }

        $headers = [
            'MIME-Version: 1.0',
            'From: Portfolio <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        // Antworten sollen beim Absender der Anfrage landen, nicht beim
        // Postfach der Website.
        if ($antwortAn !== '' && filter_var($antwortAn, FILTER_VALIDATE_EMAIL) !== false) {
            $headers[] = 'Reply-To: ' . $antwortAn;
        }

        $betreffKodiert = '=?UTF-8?B?' . base64_encode($betreff) . '?=';
        $kopf = implode("\r\n", $headers);

        // Erst mit Umschlag-Absender. Manche Hoster verbieten den fünften
        // Parameter (`mail.force_extra_parameters`); dann wird der Aufruf
        // ohne ihn wiederholt, statt gar nichts zu verschicken.
        $ok = @mail($to, $betreffKodiert, $text, $kopf, '-f' . $from);
        if (!$ok) {
            $ok = @mail($to, $betreffKodiert, $text, $kopf);
        }

        return $ok
            ? ['ok' => true, 'message' => 'Die Mail wurde an ' . $to . ' übergeben.']
            : [
                'ok' => false,
                'message' => 'Der Server hat die Mail abgelehnt. Der Absender ' . $from
                    . ' muss als E-Mail-Adresse im Hosting-Konto angelegt sein.',
            ];
    }
}
