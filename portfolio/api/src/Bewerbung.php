<?php

declare(strict_types=1);

namespace App;

/**
 * Bewerbungs-Radar: Agenturliste, Stellen, Anschreiben und Versand.
 *
 * Das Werkzeug war vorher eine einzelne HTML-Datei, die ihren Stand im
 * Browser ablegte – auf dem Rechner ein anderer als auf dem Handy. Hier
 * liegt alles auf dem Server, hinter demselben Login wie das übrige
 * Dashboard.
 *
 * Zwei Quellen, absichtlich getrennt:
 *
 *  – Die **Stammdaten** (Name, Stadt, Schwerpunkte, Adresse) kommen aus
 *    `assets/bewerbung/agenturen.json`. Diese Datei bleibt von Hand
 *    pflegbar; „Nachschub einlesen" ergänzt daraus, was in der Datenbank
 *    fehlt, und fasst Bestehendes nicht an.
 *  – Der **Stand** (Status, Notiz, Kontaktdatum) gehört in die Datenbank.
 *    Er darf nie von einem Dateiupdate überschrieben werden.
 *
 * Eigene Einträge, die im Dashboard angelegt werden, tragen `quelle =
 * 'eigen'` und werden vom Nachschub in Ruhe gelassen.
 */
final class Bewerbung
{
    private const STATI_AGENTUR = ['Offen', 'Kontaktiert', 'Antwort erhalten', 'Gespräch', 'Absage', 'Zusage'];
    private const STATI_STELLE = ['Offen', 'Beworben', 'Gespräch', 'Absage', 'Abgelaufen', 'Passt nicht'];

    public function __construct(private Database $db, private array $config) {}

    // ------------------------------------------------------------------ Lesen

    /** Alles, was die Oberfläche für den ersten Aufbau braucht. */
    public function alles(): array
    {
        $this->erstbefuellung();
        $stamm = $this->datei();

        return [
            'regionen' => $stamm['regionen'] ?? [],
            'links' => $stamm['links'] ?? [],
            'agenturen' => $this->eintraege('agentur'),
            'stellen' => $this->eintraege('stelle'),
            'statiAgentur' => self::STATI_AGENTUR,
            'statiStelle' => self::STATI_STELLE,
            'vorlage' => $this->vorlage(),
            'versand' => $this->versandOhneGeheimnis(),
            'dateien' => $this->dateien(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function eintraege(string $typ): array
    {
        $rows = $this->db->all(
            'SELECT * FROM bewerbung_eintraege WHERE typ = ? ORDER BY rowid ASC',
            [$typ]
        );

        return array_map(static function (array $row): array {
            $daten = json_decode((string) $row['daten'], true);

            return array_merge(is_array($daten) ? $daten : [], [
                'id' => $row['id'],
                'quelle' => $row['quelle'],
                'status' => $row['status'],
                'notiz' => $row['notiz'],
                'kontaktAm' => $row['kontakt_am'],
                'gesendetAm' => $row['gesendet_am'],
            ]);
        }, $rows);
    }

    // --------------------------------------------------------------- Schreiben

    /** Status, Notiz und Kontaktdatum – das, was beim Arbeiten entsteht. */
    public function merken(string $id, array $input): array
    {
        $row = $this->db->first('SELECT * FROM bewerbung_eintraege WHERE id = ?', [$id]);
        if ($row === null) {
            Http::error('Eintrag nicht gefunden.', 404);
        }

        $erlaubt = $row['typ'] === 'stelle' ? self::STATI_STELLE : self::STATI_AGENTUR;
        $felder = ['updated_at' => gmdate('c')];

        if (array_key_exists('status', $input)) {
            $wert = (string) $input['status'];
            $felder['status'] = in_array($wert, $erlaubt, true) ? $wert : $row['status'];
        }
        if (array_key_exists('notiz', $input)) {
            $felder['notiz'] = mb_substr(trim((string) $input['notiz']), 0, 4000);
        }
        if (array_key_exists('kontaktAm', $input)) {
            $wert = trim((string) $input['kontaktAm']);
            $felder['kontakt_am'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) === 1 ? $wert : '';
        }

        $this->db->update('bewerbung_eintraege', $felder, 'id = :id', ['id' => $id]);

        return $this->eintrag($id);
    }

    /** Eine selbst angelegte Agentur oder Stelle. */
    public function anlegen(array $input): array
    {
        $typ = ($input['typ'] ?? 'agentur') === 'stelle' ? 'stelle' : 'agentur';
        $id = ($typ === 'stelle' ? 'j-' : '') . 'eigen-' . bin2hex(random_bytes(4));

        $grundform = $typ === 'stelle'
            ? ['role' => '', 'co' => '', 'loc' => '', 'd' => 0, 'tags' => [], 'url' => null, 'note' => '']
            : ['n' => '', 'c' => '', 'r' => '', 'd' => 0, 'u' => '', 'e' => '', 'p' => '', 'f' => [], 'flag' => ''];

        $this->db->insert('bewerbung_eintraege', [
            'id' => $id,
            'typ' => $typ,
            'daten' => json_encode(
                array_merge($grundform, $this->stammdaten($input, $typ)),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'quelle' => 'eigen',
            'status' => 'Offen',
            'notiz' => '',
            'kontakt_am' => '',
            'updated_at' => gmdate('c'),
        ]);

        return $this->eintrag($id);
    }

    /**
     * Mehrere Einträge auf einmal – für den Import aus einer Tabelle.
     *
     * Doppelte werden übersprungen statt angelegt: Wer eine erweiterte Liste
     * ein zweites Mal einliest, will nicht jede Agentur doppelt im Verteiler
     * haben. Verglichen wird über Name und Ort, weil eine Tabelle keine
     * Kennungen mitbringt.
     *
     * @return array{neu: int, uebersprungen: int}
     */
    public function anlegenViele(array $zeilen): array
    {
        $vorhanden = [];
        foreach ($this->eintraege('agentur') as $eintrag) {
            $vorhanden[$this->kennung((string) ($eintrag['n'] ?? ''), (string) ($eintrag['c'] ?? ''))] = true;
        }

        $neu = 0;
        $uebersprungen = 0;

        foreach ($zeilen as $zeile) {
            if (!is_array($zeile)) {
                continue;
            }

            $name = trim((string) ($zeile['n'] ?? ''));
            if ($name === '') {
                $uebersprungen++;
                continue;
            }

            $schluessel = $this->kennung($name, (string) ($zeile['c'] ?? ''));
            if (isset($vorhanden[$schluessel])) {
                $uebersprungen++;
                continue;
            }

            $this->anlegen(array_merge($zeile, ['typ' => 'agentur']));
            $vorhanden[$schluessel] = true;
            $neu++;
        }

        return ['neu' => $neu, 'uebersprungen' => $uebersprungen];
    }

    /** Name und Ort auf das Wesentliche gekürzt – für den Dublettenabgleich. */
    private function kennung(string $name, string $ort): string
    {
        $roh = mb_strtolower($name . '|' . preg_replace('/\(.*$/', '', $ort));

        return preg_replace('/[^a-z0-9|]/u', '', $roh) ?? $roh;
    }

    /** Stammdaten eines Eintrags ändern – auch bei denen aus der Datei. */
    public function bearbeiten(string $id, array $input): array
    {
        $row = $this->db->first('SELECT * FROM bewerbung_eintraege WHERE id = ?', [$id]);
        if ($row === null) {
            Http::error('Eintrag nicht gefunden.', 404);
        }

        $alt = json_decode((string) $row['daten'], true);
        $neu = array_merge(is_array($alt) ? $alt : [], $this->stammdaten($input, (string) $row['typ']));

        $this->db->update(
            'bewerbung_eintraege',
            [
                'daten' => json_encode($neu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => gmdate('c'),
            ],
            'id = :id',
            ['id' => $id]
        );

        return $this->eintrag($id);
    }

    public function loeschen(string $id): void
    {
        $this->db->run('DELETE FROM bewerbung_eintraege WHERE id = ?', [$id]);
    }

    /**
     * Nimmt die Felder an, die es gibt – und nur die.
     *
     * „Und nur die" ist hier wörtlich zu nehmen: Wer beim Nachtragen einer
     * E-Mail-Adresse nur dieses eine Feld schickt, darf nicht Name, Ort und
     * Schwerpunkte verlieren. Deshalb wird jedes Feld übersprungen, das gar
     * nicht mitkam – ein leeres Feld löscht, ein fehlendes nicht.
     *
     * Die Kürzel stammen aus der ursprünglichen Datei (`n` für Name, `c`
     * für Stadt …). Sie bleiben, damit die JSON-Datei unverändert weiter
     * gepflegt werden kann.
     */
    private function stammdaten(array $input, string $typ): array
    {
        $felder = $typ === 'stelle'
            ? ['role' => 300, 'co' => 300, 'loc' => 300, 'url' => 500, 'note' => 2000]
            : ['n' => 300, 'c' => 300, 'r' => 20, 'u' => 500, 'e' => 190, 'p' => 300, 'flag' => 500];
        $listen = $typ === 'stelle' ? ['tags'] : ['f'];

        $daten = [];

        foreach ($felder as $key => $max) {
            if (array_key_exists($key, $input)) {
                $daten[$key] = mb_substr(trim((string) $input[$key]), 0, $max);
            }
        }

        foreach ($listen as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $wert = $input[$key];
            if (is_string($wert)) {
                $wert = explode(',', $wert);
            }

            $daten[$key] = array_values(array_filter(array_map(
                static fn ($e): string => mb_substr(trim((string) $e), 0, 80),
                (array) $wert
            )));
        }

        if (array_key_exists('d', $input)) {
            $daten['d'] = max(0, (int) $input['d']);
        }

        // Beim Anlegen fehlt sonst die Grundausstattung – eine Agentur ohne
        // `f` würde später in der Oberfläche stolpern.
        if ($typ === 'stelle') {
            $daten += ['tags' => [], 'url' => null, 'note' => ''];
        }

        return $daten;
    }

    private function eintrag(string $id): array
    {
        $eintrag = $this->vielleichtEintrag($id);
        if ($eintrag === null) {
            Http::error('Eintrag nicht gefunden.', 404);
        }

        return $eintrag;
    }

    private function vielleichtEintrag(string $id): ?array
    {
        $row = $this->db->first('SELECT * FROM bewerbung_eintraege WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }

        $daten = json_decode((string) $row['daten'], true);

        return array_merge(is_array($daten) ? $daten : [], [
            'id' => $row['id'],
            'quelle' => $row['quelle'],
            'status' => $row['status'],
            'notiz' => $row['notiz'],
            'kontaktAm' => $row['kontakt_am'],
            'gesendetAm' => $row['gesendet_am'],
        ]);
    }

    // ------------------------------------------------------------ Stammdatei

    private function datei(): array
    {
        $pfad = __DIR__ . '/../assets/bewerbung/agenturen.json';
        if (!is_file($pfad)) {
            return [];
        }

        $daten = json_decode((string) file_get_contents($pfad), true);

        return is_array($daten) ? $daten : [];
    }

    /** Beim ersten Aufruf wandert die mitgelieferte Liste in die Datenbank. */
    private function erstbefuellung(): void
    {
        $vorhanden = (int) $this->db->value('SELECT COUNT(*) FROM bewerbung_eintraege');
        if ($vorhanden > 0) {
            return;
        }

        $this->nachschub();
    }

    /**
     * Ergänzt, was in der Datei steht und in der Datenbank fehlt.
     *
     * Bestehende Einträge bleiben unangetastet – dort hängen Notizen und
     * Status dran, und die sind mehr wert als eine aktualisierte Adresse.
     *
     * @return array{neu: int}
     */
    public function nachschub(): array
    {
        $stamm = $this->datei();
        $bekannt = $this->db->all('SELECT id FROM bewerbung_eintraege');
        $ids = array_column($bekannt, 'id');
        $neu = 0;

        foreach ([['agenturen', 'agentur'], ['stellen', 'stelle']] as [$feld, $typ]) {
            foreach ($stamm[$feld] ?? [] as $eintrag) {
                $id = (string) ($eintrag['id'] ?? '');
                if ($id === '' || in_array($id, $ids, true)) {
                    continue;
                }

                unset($eintrag['id']);
                $this->db->insert('bewerbung_eintraege', [
                    'id' => $id,
                    'typ' => $typ,
                    'daten' => json_encode($eintrag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'quelle' => 'datei',
                    'status' => 'Offen',
                    'notiz' => '',
                    'kontakt_am' => '',
                    'updated_at' => gmdate('c'),
                ]);
                $neu++;
            }
        }

        return ['neu' => $neu];
    }

    /**
     * Übernimmt eine JSON-Sicherung aus der alten Einzeldatei.
     *
     * Dort lagen Status, Notiz und Datum unter der Kennung des Eintrags –
     * genau die Kennungen, die auch hier gelten. Übernommen wird nur, was
     * zugeordnet werden kann; alles andere wird gezählt und gemeldet.
     *
     * @return array{übernommen: int, unbekannt: int}
     */
    public function importieren(array $sicherung): array
    {
        $this->erstbefuellung();
        $uebernommen = 0;
        $unbekannt = 0;

        foreach ([['agenturen', self::STATI_AGENTUR], ['stellen', self::STATI_STELLE]] as [$feld, $stati]) {
            foreach ((array) ($sicherung[$feld] ?? []) as $id => $stand) {
                if (!is_array($stand)) {
                    continue;
                }

                $row = $this->db->first('SELECT id FROM bewerbung_eintraege WHERE id = ?', [(string) $id]);
                if ($row === null) {
                    $unbekannt++;
                    continue;
                }

                $status = (string) ($stand['status'] ?? 'Offen');
                $datum = trim((string) ($stand['date'] ?? $stand['kontaktAm'] ?? ''));

                $this->db->update(
                    'bewerbung_eintraege',
                    [
                        'status' => in_array($status, $stati, true) ? $status : 'Offen',
                        'notiz' => mb_substr((string) ($stand['note'] ?? $stand['notiz'] ?? ''), 0, 4000),
                        'kontakt_am' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) === 1 ? $datum : '',
                        'updated_at' => gmdate('c'),
                    ],
                    'id = :id',
                    ['id' => (string) $id]
                );
                $uebernommen++;
            }
        }

        if (isset($sicherung['vorlage']) && is_array($sicherung['vorlage'])) {
            $this->vorlageSpeichern($sicherung['vorlage']);
        }

        return ['übernommen' => $uebernommen, 'unbekannt' => $unbekannt];
    }

    // ------------------------------------------------------------- Konfiguration

    private function konfig(string $key, array $standard = []): array
    {
        $row = $this->db->first('SELECT value FROM bewerbung_konfig WHERE key = ?', [$key]);
        if ($row === null) {
            return $standard;
        }

        $wert = json_decode((string) $row['value'], true);

        return is_array($wert) ? array_merge($standard, $wert) : $standard;
    }

    private function konfigSpeichern(string $key, array $wert): void
    {
        $this->db->run(
            'INSERT INTO bewerbung_konfig (key, value, updated_at) VALUES (:key, :value, :updated_at)
             ON CONFLICT(key) DO UPDATE SET value = :value, updated_at = :updated_at',
            [
                'key' => $key,
                'value' => json_encode($wert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'updated_at' => gmdate('c'),
            ]
        );
    }

    public function vorlage(): array
    {
        return $this->konfig('vorlage', ['subj' => '', 'body' => '', 'att' => '']);
    }

    public function vorlageSpeichern(array $input): array
    {
        $vorlage = [
            'subj' => mb_substr(trim((string) ($input['subj'] ?? '')), 0, 300),
            'body' => mb_substr((string) ($input['body'] ?? ''), 0, 20000),
            'att' => mb_substr(trim((string) ($input['att'] ?? '')), 0, 500),
        ];
        $this->konfigSpeichern('vorlage', $vorlage);

        return $vorlage;
    }

    /** Zugangsdaten des Postfachs – das Passwort verlässt den Server nie. */
    public function versandOhneGeheimnis(): array
    {
        $v = $this->versand();
        $v['passwort'] = '';
        $v['imapPasswort'] = '';
        $v['hatPasswort'] = ($this->versand()['passwort'] ?? '') !== '';

        return $v;
    }

    private function versand(): array
    {
        return $this->konfig('versand', [
            'absender' => '',
            'absenderName' => '',
            'host' => '',
            'port' => 587,
            'benutzer' => '',
            'passwort' => '',
            'sicherheit' => 'auto',
            // Optional: Kopie im Gesendet-Ordner. Google legt selbst ab,
            // die meisten anderen Anbieter nicht.
            'imapHost' => '',
            'imapPort' => 993,
            'imapBenutzer' => '',
            'imapPasswort' => '',
            'imapOrdner' => 'Sent',
        ]);
    }

    public function versandSpeichern(array $input): array
    {
        $alt = $this->versand();
        $neu = [
            'absender' => mb_substr(trim((string) ($input['absender'] ?? '')), 0, 190),
            'absenderName' => mb_substr(trim((string) ($input['absenderName'] ?? '')), 0, 120),
            'host' => mb_substr(trim((string) ($input['host'] ?? '')), 0, 190),
            'port' => max(1, min(65535, (int) ($input['port'] ?? 587))),
            'benutzer' => mb_substr(trim((string) ($input['benutzer'] ?? '')), 0, 190),
            // Leeres Feld heißt „unverändert" – sonst müsste das Passwort
            // bei jedem Speichern neu eingetippt werden.
            'passwort' => ($input['passwort'] ?? '') !== '' ? (string) $input['passwort'] : ($alt['passwort'] ?? ''),
            'sicherheit' => in_array($input['sicherheit'] ?? 'auto', ['auto', 'tls', 'ssl', 'none'], true)
                ? (string) $input['sicherheit']
                : 'auto',
            'imapHost' => mb_substr(trim((string) ($input['imapHost'] ?? '')), 0, 190),
            'imapPort' => max(1, min(65535, (int) ($input['imapPort'] ?? 993))),
            'imapBenutzer' => mb_substr(trim((string) ($input['imapBenutzer'] ?? '')), 0, 190),
            'imapPasswort' => ($input['imapPasswort'] ?? '') !== ''
                ? (string) $input['imapPasswort']
                : ($alt['imapPasswort'] ?? ''),
            'imapOrdner' => mb_substr(trim((string) ($input['imapOrdner'] ?? 'Sent')), 0, 120) ?: 'Sent',
        ];

        $this->konfigSpeichern('versand', $neu);

        return $this->versandOhneGeheimnis();
    }

    // ------------------------------------------------------------------ Versand

    /**
     * Verschickt das Anschreiben an die übergebenen Einträge.
     *
     * Bewusst nacheinander und mit Ergebnis je Empfänger: Bei zwanzig
     * Bewerbungen will man wissen, welche durchging und welche nicht –
     * eine Sammelmeldung „drei Fehler" hilft niemandem.
     *
     * @return array{ergebnisse: array<int, array{id: string, name: string, ok: bool, meldung: string}>}
     */
    public function senden(array $ids): array
    {
        $versand = $this->versand();
        $absender = trim((string) $versand['absender']);
        $vorlage = $this->vorlage();

        if ($absender === '' || $versand['host'] === '' || $versand['benutzer'] === '') {
            Http::error('Es ist kein Postfach hinterlegt. Unter „Versand" eintragen.', 422);
        }
        if (trim($vorlage['body']) === '') {
            Http::error('Das Anschreiben ist leer.', 422);
        }

        $smtp = new Smtp(
            (string) $versand['host'],
            (int) $versand['port'],
            (string) $versand['benutzer'],
            (string) $versand['passwort'],
            (string) $versand['sicherheit']
        );

        $ergebnisse = [];

        foreach ($ids as $id) {
            // Bewusst nicht über `eintrag()`: Das würde bei einer unbekannten
            // Kennung abbrechen und damit den ganzen Durchlauf beenden. Bei
            // zwanzig Bewerbungen wäre das der schlechteste Zeitpunkt dafür.
            $eintrag = $this->vielleichtEintrag((string) $id);
            if ($eintrag === null) {
                $ergebnisse[] = ['id' => (string) $id, 'name' => (string) $id, 'ok' => false, 'meldung' => 'Eintrag nicht gefunden.'];
                continue;
            }

            $adresse = trim((string) ($eintrag['e'] ?? ''));
            $name = (string) ($eintrag['n'] ?? $eintrag['co'] ?? $id);

            if ($adresse === '' || filter_var($adresse, FILTER_VALIDATE_EMAIL) === false) {
                $ergebnisse[] = ['id' => $id, 'name' => $name, 'ok' => false, 'meldung' => 'Keine E-Mail-Adresse hinterlegt.'];
                continue;
            }

            $mail = [
                'from' => $absender,
                'fromName' => (string) ($versand['absenderName'] ?: $absender),
                'to' => $adresse,
                'subject' => $this->platzhalter($vorlage['subj'], $eintrag),
                'body' => $this->platzhalter($vorlage['body'], $eintrag),
                'replyTo' => '',
            ];

            $ergebnis = $smtp->send($mail);

            if ($ergebnis['ok']) {
                $this->db->update(
                    'bewerbung_eintraege',
                    [
                        'status' => $eintrag['status'] === 'Offen' ? 'Kontaktiert' : $eintrag['status'],
                        'kontakt_am' => date('Y-m-d'),
                        'gesendet_am' => gmdate('c'),
                        'updated_at' => gmdate('c'),
                    ],
                    'id = :id',
                    ['id' => $id]
                );

                $this->ablegen($mail);
            }

            $ergebnisse[] = [
                'id' => (string) $id,
                'name' => $name,
                'ok' => $ergebnis['ok'],
                'meldung' => $ergebnis['message'],
            ];
        }

        return ['ergebnisse' => $ergebnisse];
    }

    /**
     * Setzt Platzhalter im Anschreiben ein.
     *
     * Wer keine benutzt, bekommt seinen Text unverändert – so war es
     * vorher, und so bleibt es. Wer welche benutzt, verschickt nicht
     * hundertmal denselben anonymen Brief: Eine Bewerbung, die die Agentur
     * beim Namen nennt, landet seltener im Papierkorb als „Guten Tag".
     */
    public function platzhalter(string $text, array $eintrag): string
    {
        if (!str_contains($text, '{{')) {
            return $text;
        }

        $ort = (string) ($eintrag['c'] ?? '');
        $werte = [
            'agentur' => (string) ($eintrag['n'] ?? $eintrag['co'] ?? ''),
            // Aus „Wuppertal (Bornberg 25, 42109)" wird „Wuppertal".
            'ort' => trim(preg_replace('/\s*\(.*$/', '', $ort) ?? $ort),
            'ansprechpartner' => (string) ($eintrag['p'] ?? ''),
            'schwerpunkte' => implode(', ', (array) ($eintrag['f'] ?? [])),
        ];

        $werte['anrede'] = $this->anrede($werte['ansprechpartner']);

        foreach ($werte as $name => $wert) {
            $text = str_replace(['{{' . $name . '}}', '{{ ' . $name . ' }}'], $wert, $text);
        }

        return $text;
    }

    /**
     * Baut die Anrede aus dem Feld „Ansprechpartner".
     *
     * Das Feld ist ein Sammelbecken: Mal steht dort eine Person, mal drei
     * Geschäftsführer mit Kürzel dahinter, mal „Inhabergeführt" oder
     * „Bewerbungen über Jobs-Seite". Persönlich angeredet wird nur, was
     * eindeutig eine einzelne Person ist – im Zweifel die allgemeine Form.
     *
     * Eine Bewerbung, die mit „Sehr geehrte:r Rob Fährmann, Süleyman
     * Kayaalp, Marc Freudenhammer (GF)" beginnt, ist schlimmer als eine
     * ohne Namen.
     */
    private function anrede(string $feld): string
    {
        $allgemein = 'Sehr geehrte Damen und Herren';

        // Zusätze in Klammern weg: „(GF)", „(Geschäftsführung)" …
        $person = trim(preg_replace('/\s*\(.*$/u', '', $feld) ?? $feld);
        if ($person === '') {
            return $allgemein;
        }

        // Mehrere Personen – dafür gibt es keine saubere Einzelanrede.
        if (preg_match('/[,;&]|\bund\b|\+/iu', $person) === 1) {
            return $allgemein;
        }

        // Keine Person, sondern eine Rolle oder ein Hinweis.
        if (preg_match('/^(Inhaber|Gesch|Bewerbung|Team|Kontakt|Personal|HR|Recruit|Sekretariat|Info|Zentrale)/iu', $person) === 1) {
            return $allgemein;
        }

        // „Vorname Nachname" oder „Vorname Zweitname Nachname" – mehr Wörter
        // sind eher ein Satz als ein Name.
        $woerter = preg_split('/\s+/u', $person) ?: [];
        if (count($woerter) < 2 || count($woerter) > 3) {
            return $allgemein;
        }

        return 'Sehr geehrte:r ' . $person;
    }

    /** Testmail an die eigene Adresse – prüft die Zugangsdaten. */
    public function versandTest(): array
    {
        $versand = $this->versand();
        if ($versand['host'] === '' || $versand['benutzer'] === '') {
            return ['ok' => false, 'message' => 'Es ist kein Postfach hinterlegt.'];
        }

        $smtp = new Smtp(
            (string) $versand['host'],
            (int) $versand['port'],
            (string) $versand['benutzer'],
            (string) $versand['passwort'],
            (string) $versand['sicherheit']
        );

        $ziel = trim((string) $versand['absender']) ?: (string) $versand['benutzer'];

        return $smtp->send([
            'from' => $ziel,
            'fromName' => (string) ($versand['absenderName'] ?: $ziel),
            'to' => $ziel,
            'subject' => 'Testmail aus dem Bewerbungs-Radar',
            'body' => "Wenn diese Nachricht ankommt, funktioniert der Versand.\n\nGesendet: " . gmdate('c') . "\n",
            'replyTo' => '',
        ]);
    }

    /**
     * Legt eine verschickte Mail im Gesendet-Ordner ab.
     *
     * Nur wenn ein IMAP-Zugang hinterlegt ist. Google macht das von selbst,
     * die meisten anderen Anbieter nicht – ohne diesen Schritt hätte man
     * dort keine Kopie im eigenen Mailprogramm.
     */
    private function ablegen(array $mail): void
    {
        $versand = $this->versand();
        if (trim((string) $versand['imapHost']) === '') {
            return;
        }

        $imap = new Imap(
            (string) $versand['imapHost'],
            (int) $versand['imapPort'],
            (string) ($versand['imapBenutzer'] ?: $versand['benutzer']),
            (string) ($versand['imapPasswort'] ?: $versand['passwort'])
        );

        $imap->ablegen((string) $versand['imapOrdner'], Smtp::nachrichtText($mail));
    }

    // ----------------------------------------------------------------- Dateien

    /** Ordner für Lebenslauf, Mappe und Zeugnisse – öffentlich abrufbar. */
    private function dateiPfad(): string
    {
        return rtrim((string) $this->config['uploads_path'], '/') . '/bewerbung';
    }

    public function dateien(): array
    {
        $ordner = $this->dateiPfad();
        if (!is_dir($ordner)) {
            return [];
        }

        $basis = rtrim((string) $this->config['uploads_url'], '/') . '/bewerbung/';

        // Zum Kopieren taugt nur eine vollständige Adresse. Steht im
        // Dashboard keine, wird sie aus der laufenden Anfrage gebildet –
        // besser als ein Link, der ohne Domain im Anschreiben landet.
        $seite = rtrim((string) ($this->config['site_url'] ?? ''), '/');
        if ($seite === '' && ($_SERVER['HTTP_HOST'] ?? '') !== '') {
            $schema = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $seite = $schema . '://' . $_SERVER['HTTP_HOST'];
        }

        $dateien = [];
        foreach (scandir($ordner) ?: [] as $name) {
            if ($name === '.' || $name === '..' || !is_file($ordner . '/' . $name)) {
                continue;
            }

            $dateien[] = [
                'name' => $name,
                'groesse' => (int) filesize($ordner . '/' . $name),
                'url' => $basis . rawurlencode($name),
                // Die kurze, vorzeigbare Adresse – die Umschreibung in der
                // .htaccess führt sie auf dieselbe Datei.
                'kurz' => $seite . '/bewerbung/dateien/' . rawurlencode($name),
            ];
        }

        return $dateien;
    }

    public function dateiHochladen(array $datei): array
    {
        if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Http::error('Datei konnte nicht empfangen werden.', 422);
        }

        $name = $this->sauberername((string) ($datei['name'] ?? 'datei'));
        $ordner = $this->dateiPfad();

        if (!is_dir($ordner) && !@mkdir($ordner, 0775, true) && !is_dir($ordner)) {
            Http::error('Der Ordner für Bewerbungsdateien ließ sich nicht anlegen.', 500);
        }

        if (!@move_uploaded_file((string) $datei['tmp_name'], $ordner . '/' . $name)
            && !@rename((string) $datei['tmp_name'], $ordner . '/' . $name)) {
            Http::error('Datei konnte nicht gespeichert werden.', 500);
        }

        return ['dateien' => $this->dateien()];
    }

    public function dateiLoeschen(string $name): array
    {
        $sauber = $this->sauberername($name);
        $pfad = $this->dateiPfad() . '/' . $sauber;

        if (is_file($pfad)) {
            @unlink($pfad);
        }

        return ['dateien' => $this->dateien()];
    }

    /**
     * Aus dem Dateinamen wird eine Adresse – deshalb bleibt nur, was in
     * einer Adresse nicht stört. Punkte am Anfang und Schrägstriche fliegen
     * raus, damit niemand aus dem Ordner ausbrechen kann.
     */
    private function sauberername(string $name): string
    {
        $name = basename($name);
        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $stamm = pathinfo($name, PATHINFO_FILENAME);

        $stamm = Str::slug($stamm);
        if ($stamm === '') {
            $stamm = 'datei';
        }
        if (!in_array($endung, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], true)) {
            Http::error('Erlaubt sind PDF, JPG, PNG und Word-Dateien.', 422);
        }

        return $stamm . '.' . $endung;
    }
}
