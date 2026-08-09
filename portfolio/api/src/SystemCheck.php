<?php

declare(strict_types=1);

namespace App;

/**
 * Selbstdiagnose für den Server.
 *
 * Auf Shared Hosting gehen immer dieselben Dinge schief: falsche PHP-Version,
 * fehlende Bildbibliothek, nicht beschreibbare Ordner, zu kleine Upload-Limits,
 * blockierter Mailversand. Statt in Logdateien zu suchen, zeigt das Dashboard
 * diese Punkte auf einen Blick.
 */
final class SystemCheck
{
    public function __construct(private Database $db, private array $config) {}

    public function run(): array
    {
        return [
            'checks' => [
                $this->phpVersion(),
                $this->extension('pdo_sqlite', 'Datenbank (SQLite)', 'Ohne diese Erweiterung läuft nichts. Im KAS eine andere PHP-Version wählen.'),
                $this->extension('gd', 'Bildbearbeitung (GD)', 'Ohne GD gibt es keine verkleinerten Bilder und keine Vorschaubilder.'),
                $this->webp(),
                $this->fonts(),
                $this->pdfPreview(),
                $this->writable($this->config['uploads_path'], 'Upload-Ordner beschreibbar'),
                $this->writable($this->config['storage_path'], 'Datenbank-Ordner beschreibbar'),
                $this->uploadLimit(),
                $this->siteUrl(),
                $this->mailConfig(),
                $this->https(),
            ],
            'info' => $this->info(),
        ];
    }

    /** Verschickt eine Testmail an die hinterlegte Adresse. */
    public function sendTestMail(): array
    {
        $to = (string) $this->config['mail_to'];
        $from = (string) $this->config['mail_from'];

        if (!function_exists('mail')) {
            return ['ok' => false, 'message' => 'Diese PHP-Installation kann keine Mails versenden.'];
        }
        if ($to === '' || $from === '') {
            return ['ok' => false, 'message' => 'Unter Einstellungen → SEO fehlen Absender oder Empfänger.'];
        }

        $sent = @mail(
            $to,
            '=?UTF-8?B?' . base64_encode('Testmail vom Portfolio') . '?=',
            "Diese Nachricht bestätigt, dass der Mailversand funktioniert.\n\nGesendet: " . gmdate('c'),
            implode("\r\n", [
                'From: Portfolio <' . $from . '>',
                'Content-Type: text/plain; charset=UTF-8',
            ])
        );

        return $sent
            ? ['ok' => true, 'message' => 'Testmail wurde an ' . $to . ' übergeben. Bitte Postfach und Spam-Ordner prüfen.']
            : ['ok' => false, 'message' => 'Der Server hat die Mail abgelehnt. Absender muss eine Adresse der eigenen Domain sein.'];
    }

    // ------------------------------------------------------------------

    private function phpVersion(): array
    {
        $ok = version_compare(PHP_VERSION, '8.2', '>=');

        return $this->result(
            'PHP-Version',
            $ok ? 'ok' : 'error',
            PHP_VERSION,
            $ok ? null : 'Bitte im KAS unter „Software → PHP-Version" auf 8.2 oder höher stellen.'
        );
    }

    private function extension(string $name, string $label, string $hint): array
    {
        $ok = extension_loaded($name);

        return $this->result($label, $ok ? 'ok' : 'error', $ok ? 'vorhanden' : 'fehlt', $ok ? null : $hint);
    }

    private function webp(): array
    {
        $ok = function_exists('imagewebp');

        return $this->result(
            'WebP-Unterstützung',
            $ok ? 'ok' : 'warn',
            $ok ? 'vorhanden' : 'fehlt',
            $ok ? null : 'Verkleinerte Bilder werden als JPEG statt WebP gespeichert – etwas größer, funktioniert aber.'
        );
    }

    private function fonts(): array
    {
        $path = dirname(__DIR__) . '/assets/Archivo-Bold.ttf';
        $ok = is_file($path) && function_exists('imagettftext');

        return $this->result(
            'Schriften für Vorschaubilder',
            $ok ? 'ok' : 'warn',
            $ok ? 'vorhanden' : 'fehlen',
            $ok ? null : 'Ohne die Dateien in api/assets/ entstehen keine eigenen Vorschaubilder pro Projekt.'
        );
    }

    private function pdfPreview(): array
    {
        $ok = class_exists('\Imagick');

        return $this->result(
            'PDF-Seitenvorschau',
            $ok ? 'ok' : 'warn',
            $ok ? 'möglich' : 'nicht möglich',
            $ok ? null : 'Hochgeladene PDFs bekommen kein Vorschaubild der ersten Seite, sondern eine schlichte Kachel. Alles andere funktioniert normal.'
        );
    }

    private function writable(string $path, string $label): array
    {
        $exists = is_dir($path);
        $ok = $exists && is_writable($path);

        return $this->result(
            $label,
            $ok ? 'ok' : 'error',
            $ok ? 'ja' : ($exists ? 'nein' : 'Ordner fehlt'),
            $ok ? null : 'Im FTP-Programm die Rechte auf 755 oder 775 setzen: ' . $path
        );
    }

    private function uploadLimit(): array
    {
        $upload = $this->toBytes((string) ini_get('upload_max_filesize'));
        $post = $this->toBytes((string) ini_get('post_max_size'));
        $effective = min($upload, $post);
        $ok = $effective >= 64 * 1024 * 1024;

        return $this->result(
            'Maximale Dateigröße beim Upload',
            $ok ? 'ok' : 'warn',
            $this->formatBytes($effective),
            $ok ? null : 'Für größere Videos im KAS unter „PHP-Einstellungen" upload_max_filesize und post_max_size erhöhen.'
        );
    }

    private function siteUrl(): array
    {
        $url = (string) $this->config['site_url'];
        $ok = $url !== '';

        return $this->result(
            'Domain hinterlegt',
            $ok ? 'ok' : 'warn',
            $ok ? $url : 'nicht gesetzt',
            $ok ? null : 'Unter Einstellungen → SEO die Adresse der Website eintragen – wird für Sitemap und Link-Vorschauen gebraucht.'
        );
    }

    private function mailConfig(): array
    {
        $from = (string) $this->config['mail_from'];
        $ok = $from !== '';

        return $this->result(
            'Mail-Absender',
            $ok ? 'ok' : 'warn',
            $ok ? $from : 'nicht gesetzt',
            $ok ? null : 'Ohne Absender kommen keine Benachrichtigungen an. Muss eine Adresse der eigenen Domain sein.'
        );
    }

    private function https(): array
    {
        $ok = Http::isHttps();

        return $this->result(
            'Verschlüsselte Verbindung',
            $ok ? 'ok' : 'warn',
            $ok ? 'aktiv' : 'nicht aktiv',
            $ok ? null : 'Im KAS ein kostenloses Let’s-Encrypt-Zertifikat aktivieren.'
        );
    }

    private function info(): array
    {
        $dbFile = $this->config['storage_path'] . '/portfolio.sqlite';
        $uploads = $this->directorySize($this->config['uploads_path']);

        return [
            'databaseSize' => $this->formatBytes(is_file($dbFile) ? (int) filesize($dbFile) : 0),
            'uploadsSize' => $this->formatBytes($uploads),
            'mediaCount' => (int) $this->db->value('SELECT COUNT(*) FROM media'),
            'imagesWithoutVariants' => (int) $this->db->value(
                "SELECT COUNT(*) FROM media WHERE kind = 'image' AND (variants = '{}' OR variants = '' OR variants IS NULL)"
            ),
            // Bilder ohne Beschreibung: schlecht für Screenreader und Bildersuche.
            'imagesWithoutAlt' => (int) $this->db->value(
                "SELECT COUNT(*) FROM media WHERE kind = 'image' AND (alt IS NULL OR trim(alt) = '')"
            ),
            'scheduledProjects' => (int) $this->db->value(
                'SELECT COUNT(*) FROM projects WHERE publish_at IS NOT NULL AND deleted_at IS NULL'
            ),
            'trashCount' => (int) $this->db->value('SELECT COUNT(*) FROM projects WHERE deleted_at IS NOT NULL'),
            'serverTime' => gmdate('c'),
            'memoryLimit' => (string) ini_get('memory_limit'),
            'maxExecutionTime' => (string) ini_get('max_execution_time'),
        ];
    }

    private function result(string $label, string $status, string $value, ?string $hint): array
    {
        return ['label' => $label, 'status' => $status, 'value' => $value, 'hint' => $hint];
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 1) . ' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . ' MB';
        }

        return round($bytes / 1024) . ' KB';
    }

    private function directorySize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $total = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $total += $file->getSize();
            }
        }

        return $total;
    }
}
