<?php

declare(strict_types=1);

namespace App;

/**
 * Legt fehlende `.htaccess`-Dateien selbst an.
 *
 * Hintergrund: Diese Dateien beginnen mit einem Punkt und gelten damit als
 * versteckt. Die meisten FTP-Programme übertragen sie standardmäßig nicht.
 * Fehlen sie, ist das Ergebnis besonders tückisch – die Seite lädt, sieht
 * aber halb tot aus, und nichts weist auf die Ursache hin. Schlimmer noch:
 * Ohne sie wären Datenbank und Protokolle über die Adresszeile abrufbar.
 *
 * Deshalb liegen die Inhalte zusätzlich als gewöhnliche Textdateien in
 * `api/assets/server/` – die überträgt jedes FTP-Programm. Beim ersten
 * Aufruf schreibt der Server sie an ihren richtigen Platz.
 *
 * Bewusst nur, was fehlt: Eine vorhandene Datei wird nie überschrieben,
 * damit eigene Anpassungen erhalten bleiben.
 */
final class ServerFiles
{
    /** Vorlage → Zielpfad, relativ zum öffentlichen Verzeichnis. */
    private const DATEIEN = [
        'root.htaccess.txt' => '.htaccess',
        'api.htaccess.txt' => 'api/.htaccess',
        'uploads.htaccess.txt' => 'uploads/.htaccess',
        'storage.htaccess.txt' => 'api/storage/.htaccess',
    ];

    /**
     * @param array<string, mixed> $config
     * @return array<string, bool> Zielpfad → liegt jetzt vor
     */
    public static function ensure(array $config): array
    {
        $public = rtrim((string) ($config['public_path'] ?? ''), '/');
        $vorlagen = __DIR__ . '/../assets/server';
        $stand = [];

        foreach (self::DATEIEN as $vorlage => $ziel) {
            $pfad = $public . '/' . $ziel;

            if (is_file($pfad)) {
                $stand[$ziel] = true;
                continue;
            }

            $quelle = $vorlagen . '/' . $vorlage;
            if (!is_file($quelle)) {
                $stand[$ziel] = false;
                continue;
            }

            $ordner = dirname($pfad);
            if (!is_dir($ordner) && !@mkdir($ordner, 0775, true) && !is_dir($ordner)) {
                $stand[$ziel] = false;
                continue;
            }

            $stand[$ziel] = @copy($quelle, $pfad);
        }

        return $stand;
    }

    /**
     * Schneller Vorabtest für den Normalfall.
     *
     * `ensure()` läuft bei jeder Anfrage mit. Sind alle Dateien da – und das
     * ist der Regelfall –, kostet das vier Dateiabfragen und sonst nichts.
     *
     * @param array<string, mixed> $config
     */
    public static function complete(array $config): bool
    {
        $public = rtrim((string) ($config['public_path'] ?? ''), '/');

        foreach (self::DATEIEN as $ziel) {
            if (!is_file($public . '/' . $ziel)) {
                return false;
            }
        }

        return true;
    }
}
