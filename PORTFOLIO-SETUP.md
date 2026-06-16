# PLANIMADE® – Admin-Dashboard einrichten (Decap CMS)

So bekommst du ein echtes Login-Dashboard, in dem du Portfolio-Videos **im Browser hochladen** und verwalten kannst – ohne Code anzufassen. Hosting ist kostenlos (Netlify).

Das ist eine **einmalige** Einrichtung. Danach pflegst du alles unter `deine-seite.netlify.app/admin/`.

---

## Was schon vorbereitet ist
- `admin/` – das Dashboard (Decap CMS)
- `admin/config.yml` – die Felder (Titel, Kategorie, Video, Vorschaubild)
- `data/portfolio.json` – hier speichert das Dashboard deine Projekte
- Die Startseite lädt die Projekte automatisch aus dieser Datei. Lokal (per Doppelklick) zeigt sie weiterhin das Showreel als Fallback.

---

## Schritt 1 – Code zu GitHub
1. Lege dir einen kostenlosen Account auf **https://github.com** an (falls noch nicht vorhanden).
2. Erstelle ein neues, **privates oder öffentliches Repository**, z. B. `planimade-website`.
3. Lade den Inhalt dieses Ordners hoch:
   - Einfach im Browser: „Add file → Upload files" und den **gesamten Ordnerinhalt** (inkl. `index.html`, `media/`, `admin/`, `data/`) hineinziehen.
   - Oder per Git (falls du es kennst):
     ```
     git init
     git add .
     git commit -m "PLANIMADE Website"
     git branch -M main
     git remote add origin https://github.com/DEIN-NAME/planimade-website.git
     git push -u origin main
     ```

## Schritt 2 – Bei Netlify deployen
1. Account auf **https://www.netlify.com** anlegen (mit GitHub einloggen ist am einfachsten).
2. **Add new site → Import an existing project → GitHub** → dein Repo auswählen.
3. Build-Einstellungen leer lassen:
   - **Build command:** *(leer)*
   - **Publish directory:** `.` (Punkt = Hauptordner)
4. **Deploy** klicken. Du bekommst eine URL wie `https://xyz.netlify.app`.

## Schritt 3 – Login aktivieren (Netlify Identity + Git Gateway)
1. Im Netlify-Dashboard deines Sites: **Integrations / Identity** → **Enable Identity**.
2. Unter **Identity → Registration**: auf **Invite only** stellen (nur eingeladene Personen können rein).
3. Unter **Identity → Services → Git Gateway**: **Enable Git Gateway**.
4. **Identity → Invite users** → deine E-Mail eintragen → Einladung senden.
5. E-Mail öffnen → Einladung annehmen → Passwort vergeben.

## Schritt 4 – Loslegen
- Öffne **`https://deine-seite.netlify.app/admin/`**
- Mit deiner E-Mail + Passwort einloggen.
- **Portfolio → Projekte**: neues Projekt anlegen, Video hochladen, Titel/Kategorie eintragen, **Publish**.
- Nach ein paar Sekunden ist es live auf der Startseite. Reihenfolge per Drag & Drop – der erste Eintrag wird groß angezeigt.

---

## Tipps
- **Videoformat:** Am besten web-optimiertes **MP4 (H.264)** oder **WebM** hochladen, nicht riesige Originaldateien. Schick mir neue Clips, dann konvertiere ich sie dir passend (wie das Showreel).
- **Große Videodateien:** Für viele/sehr große Videos lohnt sich später eine Medien-Anbindung wie **Cloudinary** statt direktem Git-Upload – sag Bescheid, dann richte ich das ein.
- **Eigene Domain:** In Netlify unter **Domain settings** kannst du `planimade.de` o. Ä. verbinden.
- **Kontakt-Mail:** In `index.html` ist aktuell `hello.dominicmajewski@gmail.com` als Empfänger hinterlegt (Suche: `CONTACT_EMAIL`). Bei Bedarf anpassen.

## Lokal testen
Doppelklick auf `index.html` funktioniert weiterhin – dort wird das Showreel als Fallback gezeigt. Das Dashboard (`/admin`) funktioniert nur auf der gehosteten Netlify-Seite.
