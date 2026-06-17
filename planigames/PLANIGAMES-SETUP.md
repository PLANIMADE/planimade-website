# PLANIGAMES — Website & Admin-Dashboard

Eine eigenständige Website für dein Indie-Studio **PLANIGAMES** — mit
Studio-Vorstellung, Spielen, frei baubaren Game-Seiten und einem Devlog
für Patch Notes. Inhalte pflegst du komplett im Browser über ein
Login-Dashboard (**Decap CMS**), ohne Code anzufassen — genau wie bei
planimade.de.

Die Seite liegt im Unterordner **`planigames/`** desselben Repos und wird
als **eigene Netlify-Site** veröffentlicht.

---

## Was schon drin ist

| Datei / Ordner            | Wofür                                                            |
|---------------------------|-----------------------------------------------------------------|
| `index.html`              | Studio-Startseite (Hero, aktuelles Spiel, Über uns, Team, Devlog) |
| `games.html`              | Übersicht aller Spiele                                           |
| `game.html`               | Detailseite eines Spiels — aus frei platzierbaren **Blöcken**    |
| `devlog.html`             | Devlog & Patch Notes (Liste + Einzelbeitrag)                    |
| `admin/`                  | Das Login-Dashboard (Decap CMS)                                 |
| `admin/config.yml`        | Definiert alle Felder & den Block-Baukasten                    |
| `data/studio.json`        | Globale Studio-Inhalte                                          |
| `data/games.json`         | Alle Spiele inkl. ihrer Seiten-Blöcke                          |
| `data/patchnotes.json`    | Devlog-/Patchnote-Einträge                                     |
| `assets/`                 | Gemeinsames CSS & JavaScript (Effekte, Renderer)               |
| `media/`                  | Hochgeladene Bilder & Videos                                   |

---

## Der „Website-Builder" für Game-Seiten

Jede Game-Seite (`game.html?slug=…`) wird aus **Blöcken** zusammengesetzt,
die du im Dashboard frei hinzufügst, sortierst (Drag & Drop) und füllst:

- **Hero** – großer Kopfbereich mit Bild/Video, Logo & Buttons
- **Textabschnitt** – Überschrift + formatierter Text (Markdown)
- **Feature-Kacheln** – Icon, Titel, Text
- **Bildergalerie** – Screenshots im Raster
- **Trailer/Video** – YouTube-Link oder eigene Datei
- **Zitate/Reviews** – Pressestimmen & Community-Feedback
- **Kennzahlen** – große Zahlen (z. B. „1–4 Spieler")
- **Roadmap** – Zeitstrahl mit Status (erledigt / in Arbeit / geplant)
- **Call-to-Action** – Banner mit Button
- **Abstand** – Leerraum zum Feinjustieren

Jedes Spiel hat eine eigene **Akzentfarbe** (HEX) — damit bekommt jede
Game-Welt ihren eigenen farbigen Look, während der Studio-Rahmen edel
dunkel bleibt.

---

## Schritt 1 – Code ist schon im Repo

Alles liegt unter `planigames/` im Repo `planimade/planimade-website`.

## Schritt 2 – Eigene Netlify-Site anlegen
1. Auf **https://www.netlify.com** einloggen.
2. **Add new site → Import an existing project → GitHub** → dieses Repo wählen.
3. Build-Einstellungen:
   - **Base directory:** `planigames`
   - **Build command:** *(leer)*
   - **Publish directory:** `planigames` (bzw. `.` wenn Base gesetzt ist)
4. **Deploy** klicken → du bekommst eine URL wie `https://xyz.netlify.app`.

> Tipp: So bleibt planimade.de als eigene Site bestehen — PLANIGAMES ist
> komplett getrennt, nur im selben Repo.

## Schritt 3 – Login aktivieren (Netlify Identity + Git Gateway)
1. In der **PLANIGAMES**-Site: **Identity → Enable Identity**.
2. **Identity → Registration → Invite only**.
3. **Identity → Services → Git Gateway → Enable**.
4. **Identity → Invite users** → deine E-Mail → Einladung annehmen → Passwort setzen.

## Schritt 4 – Loslegen
- Öffne **`https://deine-seite.netlify.app/admin/`** und logge dich ein.
- **Studio-Einstellungen** → Startseite, Über-uns, Team, Kontakt pflegen.
- **Spiele** → neues Spiel anlegen, Slug + Akzentfarbe setzen, Blöcke bauen.
- **Devlog & Patch Notes** → Beiträge schreiben, Spiel zuordnen, Version eintragen.
- **Publish** → nach wenigen Sekunden ist alles live.

---

## Gut zu wissen

- **Branch:** Das Dashboard committet auf den Branch `main`
  (`admin/config.yml` → `backend.branch`). Wenn deine Netlify-Site von einem
  anderen Branch deployt, dort denselben Branch eintragen.
- **Bilder/Videos:** Web-optimiert hochladen (MP4/H.264 bzw. WebM, komprimierte
  PNG/JPG). Sehr große Trailer besser über YouTube einbinden.
- **Eigene Domain:** In Netlify unter **Domain settings** verbinden
  (z. B. `planigames.de`).
- **Newsletter/Wishlist:** Das Anmeldeformular nutzt **Netlify Forms** —
  ohne Zusatz-Setup landen Einträge unter **Forms** im Netlify-Dashboard.
- **Lokales Testen:** Wegen der `fetch`-Aufrufe auf `data/*.json` einen
  kleinen Server starten, z. B. im Ordner `planigames/`:
  `python3 -m http.server 8080` → dann `http://localhost:8080`.
  (Reiner Doppelklick lädt die JSON-Daten browserbedingt nicht.)
- **Slugs:** Slug eines Spiels = Adresse `game.html?slug=<slug>`. Bei Patch
  Notes verbindet das Feld „Spiel (Slug)" den Beitrag mit dem Spiel.
