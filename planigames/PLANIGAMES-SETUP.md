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
| `game.php`               | Detailseite eines Spiels — aus frei platzierbaren **Blöcken**   |
| `devlog.php`             | Devlog & Patch Notes (Liste + Einzelbeitrag)                   |
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

Jede Game-Seite (`game.php?slug=…`) besteht aus **Blöcken**, die du im
Dashboard frei hinzufügst, sortierst und füllst:

**Hero**, **Textabschnitt**, **Feature-Kacheln**, **Bildergalerie**,
**Trailer/Video** (YouTube-Link oder Datei), **Zitate/Reviews**,
**Kennzahlen**, **Roadmap** (Zeitstrahl), **Call-to-Action**, **Abstand**.

Jedes Spiel hat eine eigene **Akzentfarbe** — so bekommt jede Game-Welt
ihren eigenen Look, während der Studio-Rahmen edel schwarz bleibt.

**Spiele-Verwaltung im Dashboard:** links die Spieleliste (Suche, Filter,
Reihenfolge per Ziehen), rechts genau ein Spiel mit den Reitern *Übersicht ·
Seiten-Aufbau · Darstellung · Community · Einstellungen*. Gespeichert wird immer
nur das geöffnete Spiel. Blöcke lassen sich **ausblenden statt löschen**.

- **Web-Adresse (Slug) ändern** nur über *Einstellungen → Ändern…*: Warteliste,
  Vorschläge, Board, Devlog-Zuordnung, Statistik und die englische Fassung ziehen
  automatisch mit um; vorher entsteht eine Sicherheitskopie (Backups → „Vor
  Adress-Umzug“), und die alte Adresse leitet dauerhaft weiter (`data/redirects.json`).
- **Logo platzieren** (*Darstellung*): zeigt die echte Spielseite in Laptop-,
  Full-HD-, 1440p- und Handy-Größe; eine Ampel meldet, ob das Logo Badge, Text,
  Buttons oder das Menü überdeckt. Transparenter Rand wird beim Hochladen
  automatisch entfernt (bzw. per „Rand entfernen“ bei bestehenden Logos).

---

## Mehrsprachigkeit (Deutsch / Englisch) – Deutsch ist die Vorlage

Die Website hat einen **Sprachumschalter (DE/EN)** oben rechts. Gepflegt wird
immer **auf Deutsch** – Englisch folgt automatisch:

1. Du änderst einen deutschen Text und speicherst.
2. Die englische Übersetzung dieses Textes gilt ab sofort als **veraltet** – die
   Website zeigt englischen Besuchern **so lange den aktuellen deutschen Text**,
   bis das Englische nachgezogen ist (nie eine alte, falsche Übersetzung).
3. Ist **„Automatisch nachziehen“** an (Standard), übersetzt das Admin die
   geänderten Texte im Hintergrund neu (unten links erscheint kurz „Englisch wird
   nachgezogen …“). Kostenloser Dienst *MyMemory*, **kein API-Key nötig**.

**Englisch ansehen/anpassen:** in jedem Bereich oben auf **EN** umschalten. Die
Ansicht ist überall gleich: links das deutsche Original, rechts das Englische, je
Text ein Status – *aktuell*, *veraltet*, *fehlt* (selten *ungeprüft*).
- **Neu übersetzen** (je Text) · **✓ Passt** (Übersetzung bleibt, gilt als aktuell)
- **Veraltete & fehlende übersetzen** · **Alles neu aus dem Deutschen übersetzen**
  (legt vorher eine Sicherheitskopie an) · **Alle als geprüft markieren**

Gut zu wissen:
- Englische Texte liegen in `data/*.en.json`; jeder merkt sich unter `_src` einen
  Fingerabdruck des deutschen Textes, aus dem er übersetzt wurde.
- Beim ersten Aufruf des neuen Admins werden alte Übersetzungen einmalig geprüft
  (vorher Sicherheitskopie unter *Backups*): Texte, die offensichtlich nicht mehr
  zum Deutschen passen (z. B. alter Spielname „WARCATS“, andere Zahlen, fehlende
  Absätze), werden als veraltet markiert und neu übersetzt.
- **Gleich in allen Sprachen:** Spielname, Studio-Name, Personennamen, E-Mail-
  Adressen, Links, Bilder, Farben, Zahlen/Jahreszahlen und der Seitenaufbau.
- Ist der Übersetzungsdienst nicht erreichbar (z. B. Tageskontingent), bleibt es
  beim deutschen Text – später geht es automatisch weiter.

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
- **Newsletter:** Das Anmeldeformular läuft über `subscribe.php` direkt auf
  All-Inkl. Anmeldungen landen in `data/subscribers.json` und sind im
  Dashboard unter **Newsletter-Abos** einseh-/exportierbar.
- **SEO:** `sitemap.php` (erreichbar als `/sitemap.xml`) und `robots.txt`
  sind dabei; Game-/Devlog-Seiten (`game.php`/`devlog.php`) liefern korrekte
  Titel & Social-Vorschaubilder. Standard-Vorschaubild: `media/og.jpg`.
- **Backups:** Deine Inhalte stecken komplett in `data/*.json` — einfach
  per FTP herunterladen = fertiges Backup.
- **Postfach:** Verfassen mit Formatierung (fett, kursiv, Listen, Zitat, Link),
  Anhänge senden (zusammen bis 10 MB) und empfangen/herunterladen, Weiterleiten
  inkl. Anhängen, mehrere Signaturen (Studio + pro Person, Standard je Admin).
  Jede Mail geht mit Text- und HTML-Fassung raus (besser für Spamfilter).
- **Datenschutz der Daten:** In `data/` sind per Web nur `studio`, `team`,
  `games`, `patchnotes`, `legal` (+ `.en`) und `redirects.json` abrufbar – alles
  andere sperrt die Root-`.htaccess` (zweite Sperre: `data/.htaccess`). Das
  Cockpit prüft das regelmäßig selbst und warnt, falls etwas offen liegt.

## Lokal testen (optional)

Im Ordner `planigames/` einen lokalen PHP-Server starten:

```
php -S localhost:8080
```

Dann `http://localhost:8080` (Website) bzw. `http://localhost:8080/admin/`
(Dashboard). So funktionieren sowohl die JSON-Inhalte als auch das Admin
wie auf dem echten Server.
