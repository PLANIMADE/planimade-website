# PLANIGAMES — Website & Admin (für All-Inkl / KAS)

Eine eigenständige Website für dein Indie-Studio **PLANIGAMES** mit
Studio-Vorstellung, Spielen, frei baubaren Game-Seiten und Devlog/Patch
Notes. Inhalte pflegst du komplett im Browser über ein **eigenes
PHP-Dashboard**, das direkt auf deinem All-Inkl-Webspace läuft — ohne
GitHub, ohne Netlify, ohne Code anzufassen.

---

## Was drin ist

| Datei / Ordner            | Wofür                                                            |
|---------------------------|-----------------------------------------------------------------|
| `index.html`              | Studio-Startseite                                               |
| `games.html`              | Übersicht aller Spiele                                          |
| `game.html`               | Detailseite eines Spiels — aus frei platzierbaren **Blöcken**   |
| `devlog.html`             | Devlog & Patch Notes (Liste + Einzelbeitrag)                   |
| `admin/`                  | **PHP-Login-Dashboard** (das CMS)                              |
| `admin/schema.php`        | Definiert alle Felder & den Block-Baukasten (Single Source)    |
| `data/*.json`             | Deine Inhalte (Studio, Spiele, Patch Notes)                   |
| `data/auth.php`           | Wird beim ersten Login erzeugt (Passwort-Hash) — bleibt am Server |
| `media/`                  | Hochgeladene Bilder & Videos                                  |
| `assets/`                 | Gemeinsames CSS & JavaScript der Website                       |

---

## Schritt 1 – Dateien auf All-Inkl hochladen (FTP / KAS)

1. In **KAS** (https://kas.all-inkl.com) einloggen.
2. FTP-Zugangsdaten findest du unter **FTP → FTP-Accounts** (oder einen
   neuen anlegen). Damit verbindest du dich z. B. mit **FileZilla**:
   - Server: dein FTP-Host (z. B. `wXXXXXX.kasserver.com`)
   - Benutzer / Passwort: dein FTP-Account
3. Lade den **kompletten Inhalt des Ordners `planigames/`** in das
   Web-Verzeichnis der Domain `planigames.de` hoch. Das ist meist
   `/` bzw. der in KAS unter **Domain → planigames.de** eingestellte
   **Dokumenten-Pfad** (oft etwas wie `/planigames.de/`).
   > Wichtig: Es soll **der Inhalt** von `planigames/` direkt im
   > Domain-Root liegen — also `index.html`, `admin/`, `data/`, `media/`,
   > `assets/` direkt dort. Nicht den Ordner `planigames` selbst.
4. PHP muss für die Domain aktiv sein (bei All-Inkl Standard; ggf. unter
   **Domain → PHP-Version** eine aktuelle Version wählen, z. B. PHP 8.x).

## Schritt 2 – Schreibrechte prüfen

Das Dashboard schreibt deine Inhalte direkt in `data/` und Uploads nach
`media/`. Bei All-Inkl sind selbst hochgeladene Dateien normalerweise
schreibbar. Falls Speichern/Upload fehlschlägt, im FTP-Programm die
**Schreibrechte (CHMOD) auf 755 (Ordner) bzw. 644 (Dateien)** setzen —
für `data/` und `media/` ggf. **775**.

## Schritt 3 – Admin einrichten

1. Öffne **`https://planigames.de/admin/`**.
2. Beim **allerersten Mal** legst du dein **Passwort** fest (min. 8
   Zeichen). Es wird verschlüsselt in `data/auth.php` gespeichert.
3. Danach loggst du dich immer mit diesem Passwort ein.

> Passwort vergessen? Lösche per FTP die Datei `data/auth.php` — beim
> nächsten Aufruf von `/admin/` kannst du ein neues Passwort setzen.

## Schritt 4 – Loslegen

- **Studio & Startseite:** Texte, Über-uns, Team, Kontakt, Logo, Footer.
- **Spiele:** neues Spiel anlegen, Slug + Akzentfarbe wählen, Seite aus
  **Blöcken** bauen (Drag-frei per ↑/↓ sortieren, ✕ löschen, ▾ einklappen).
- **Devlog & Patch Notes:** Beiträge schreiben, Spiel zuordnen, Version
  setzen.
- **Speichern** → die Änderung ist **sofort live** auf der Website.

---

## Der „Website-Builder" für Game-Seiten

Jede Game-Seite (`game.html?slug=…`) besteht aus **Blöcken**, die du im
Dashboard frei hinzufügst, sortierst und füllst:

**Hero**, **Textabschnitt**, **Feature-Kacheln**, **Bildergalerie**,
**Trailer/Video** (YouTube-Link oder Datei), **Zitate/Reviews**,
**Kennzahlen**, **Roadmap** (Zeitstrahl), **Call-to-Action**, **Abstand**.

Jedes Spiel hat eine eigene **Akzentfarbe** — so bekommt jede Game-Welt
ihren eigenen Look, während der Studio-Rahmen edel schwarz bleibt.

---

## Gut zu wissen

- **Design / Schriften:** Die Seite lädt Tailwind & Fonts per CDN beim
  Besucher — funktioniert auf All-Inkl ohne weiteres Setup.
- **Bilder/Videos:** Web-optimiert hochladen (komprimierte PNG/JPG, MP4/
  WebM). Sehr große Trailer besser über YouTube einbinden (Block „Trailer").
  Maximale Upload-Größe richtet sich nach der PHP-Einstellung
  `upload_max_filesize` (in KAS unter PHP-Einstellungen anpassbar).
- **Eigenes Logo:** Unter **Studio → Logo** ein transparentes PNG/SVG
  hochladen — ersetzt automatisch den Schriftzug in Kopf- und Fußzeile.
- **Sicherheit:** `/admin/` ist passwortgeschützt (Session + CSRF). Für
  zusätzlichen Schutz kannst du in KAS unter **Tools → Verzeichnisschutz**
  den Ordner `admin/` zusätzlich mit HTTP-Auth absichern.
- **Newsletter-Formular:** Das Anmeldeformular nutzt aktuell Netlify Forms
  und funktioniert auf All-Inkl **nicht** automatisch. Sag Bescheid, dann
  baue ich dir einen kleinen PHP-Mailversand (an deine Adresse) oder binde
  einen Dienst (z. B. Mailchimp/Brevo) ein.
- **Backups:** Deine Inhalte stecken komplett in `data/*.json` — einfach
  per FTP herunterladen = fertiges Backup.

## Lokal testen (optional)

Im Ordner `planigames/` einen lokalen PHP-Server starten:

```
php -S localhost:8080
```

Dann `http://localhost:8080` (Website) bzw. `http://localhost:8080/admin/`
(Dashboard). So funktionieren sowohl die JSON-Inhalte als auch das Admin
wie auf dem echten Server.
