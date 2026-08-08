# Portfolio — Dominic Majewski

Persönliches Portfolio mit eigenem Redaktionssystem. Gebaut für klassisches
Webhosting (all-inkl): statisches Frontend, schlanke PHP-API, kein
Node.js-Server, keine laufenden Fremdkosten.

```
portfolio/
├── web/      Öffentliche Website      (Astro · Tailwind v4 · GSAP · WebGL)
├── admin/    Dashboard                (React · Vite · Tailwind v4)
├── api/      Backend                  (PHP 8 · SQLite, ohne Composer)
├── server/   .htaccess-Vorlagen       (Apache/all-inkl)
├── scripts/  Build- und Vorschau-Werkzeuge
└── deploy/   Ergebnis des Builds      (wird erzeugt, nicht bearbeiten)
```

---

## Warum dieser Aufbau

| Entscheidung | Grund |
|---|---|
| **Statisches Frontend** | Ausgeliefert wird fertiges HTML – nichts muss zur Laufzeit gerendert werden. Schnell und suchmaschinenfreundlich. |
| **Inhalte per API** | Änderungen im Dashboard sind sofort live, ohne neuen Build und ohne Deploy. |
| **PHP + SQLite** | Läuft auf jedem all-inkl-Paket. Keine Datenbank einzurichten, ein Backup ist ein Dateidownload. |
| **Kein Composer, kein Framework** | Das Backend ist reines PHP – ein FTP-Upload genügt, nichts kann „veralten". |
| **Case-Studies über PHP** | `/work/<slug>` liefert PHP mit passenden Meta-Tags aus. Google und Link-Vorschauen sehen echte Inhalte, obwohl die Seite statisch gebaut ist. |
| **Selbst gehostete Schriften** | Keine Verbindung zu Google Fonts – in Deutschland auch rechtlich der ruhigere Weg. |

---

## Loslegen

```bash
npm run setup                                       # Abhängigkeiten
php api/scripts/setup.php --email=… --password=… --demo   # Datenbank + Zugang
```

Drei Terminals für die Entwicklung:

```bash
npm run dev:api      # http://127.0.0.1:8787   PHP-API
npm run dev:web      # http://127.0.0.1:4321   Website
npm run dev:admin    # http://127.0.0.1:5173/admin/   Dashboard
```

Produktionsnahe Vorschau des fertigen Builds:

```bash
npm run build
php -S 127.0.0.1:8080 -t deploy scripts/preview.php
```

Veröffentlichen: **[DEPLOY-ALL-INKL.md](DEPLOY-ALL-INKL.md)**

---

## Was das Frontend kann

- **WebGL-Hero** – strömendes Rauschen, das auf Maus und Scroll reagiert
  (~4 KB eigener Shader statt einer 3D-Bibliothek), mit sauberem Rückfall auf
  einen CSS-Verlauf
- **Eigener Mauszeiger** mit Zustandswechsel, magnetische Buttons
- **Projektraster** mit Kategoriefiltern und stummer Video-Vorschau beim Hover
- **Case-Studies** mit Markdown-Text, Kennzahlen, Bildstrecke,
  Vorher/Nachher-Vergleich und drehbarem 3D-Modell (GLB)
- **Command-Palette** (⌘K / Strg+K) über Seiten, Projekte und Aktionen
- **Hell/Dunkel-Umschalter**, dezente Interface-Sounds (standardmäßig aus)
- **Osterei**: Konami-Code oder das Wort „render" tippen
- **Rücksichtsvoll**: respektiert `prefers-reduced-motion`, funktioniert mit
  Tastatur, Schwergewichte (three.js, Markdown-Parser) werden nur bei Bedarf geladen

## Was das Dashboard kann

- Projekte anlegen, bearbeiten, per **Drag & Drop** sortieren, als Entwurf halten
- **Medienbibliothek** mit Mehrfach-Upload, Drag & Drop und automatischen
  WebP-Vorschaubildern
- **Posteingang** für Kontaktanfragen inklusive Status und Direktantwort
- **Kundenstimmen** pflegen
- **Einstellungen**: Profiltexte, Verfügbarkeits-Badge, Fähigkeiten, Ablauf,
  Social-Links, SEO, Impressumsdaten
- **Statistik** ohne Cookies und ohne Drittanbieter – Aufrufe, Besucher,
  beliebteste Projekte, Herkunft, Geräte
- **Backup** als JSON mit einem Klick

---

## API im Überblick

Alle Antworten sind JSON. Schreibende Zugriffe brauchen die Login-Sitzung
(HttpOnly-Cookie) **und** den Header `X-CSRF-Token`.

| Methode | Route | Zugriff | Zweck |
|---|---|---|---|
| `GET` | `/api/health` | offen | Statusprüfung |
| `GET` | `/api/projects` | offen | Veröffentlichte Projekte + Kategorien |
| `GET` | `/api/projects/{slug}` | offen | Einzelnes Projekt |
| `GET` | `/api/settings` | offen | Profil- und Seitentexte |
| `GET` | `/api/testimonials` | offen | Kundenstimmen |
| `POST` | `/api/contact` | offen | Kontaktanfrage (Limit: 5/Stunde) |
| `POST` | `/api/events` | offen | Aufruf zählen (cookiefrei) |
| `POST` | `/api/auth/login` | offen | Anmelden |
| `GET` | `/api/auth/me` | offen | Sitzung prüfen |
| `POST/PUT/DELETE` | `/api/projects…` | Login | Projekte verwalten |
| `POST` | `/api/projects-reorder` | Login | Reihenfolge speichern |
| `GET/POST/PATCH/DELETE` | `/api/media…` | Login | Medien verwalten |
| `GET/PATCH/DELETE` | `/api/messages…` | Login | Posteingang |
| `PUT` | `/api/settings` | Login | Einstellungen speichern |
| `GET` | `/api/stats` | Login | Auswertung |
| `GET` | `/api/export` | Login | Vollständiges Backup |

---

## Datenschutz

Die Website setzt **keine Cookies** und bindet **keine Dienste Dritter** ein.
Für die Reichweitenmessung wird pro Aufruf gespeichert: Seite, Datum, grobe
Gerätekategorie und die verweisende Domain – dazu ein täglich wechselnder,
nicht umkehrbarer Hash, um wiederkehrende Aufrufe eines Tages
zusammenzufassen. Rohdaten löschen sich nach 400 Tagen selbst. Deshalb kommt
die Seite ohne Cookie-Banner aus.

Im Browser-Speicher landen nur die eigenen Anzeigeeinstellungen
(Farbschema, Ton, Cursor).

---

## Technisches Kleingedrucktes

- **Ausgeliefert werden** rund 50 KB gzip an JavaScript für die Startseite;
  three.js (~180 KB gzip) nur auf Seiten mit 3D-Modell.
- **Bilder**: Beim Upload entsteht automatisch eine WebP-Vorschau (640 px).
  Das Raster lädt die Vorschau, die Detailseite das Original.
- **Datenbank**: SQLite im WAL-Modus, Migrationen laufen selbsttätig beim
  ersten Aufruf nach einem Update.
- **Typprüfung**: `npm run check` prüft Frontend und Dashboard.
