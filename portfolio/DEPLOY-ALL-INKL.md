# Portfolio auf all-inkl veröffentlichen

Es gibt zwei Wege. Der erste braucht **keinen einzigen Befehl** – nur ein
FTP-Programm und einen Browser. Rechne mit etwa 20 Minuten.

---

## Weg A – Fertiges Paket hochladen (empfohlen)

Du bekommst ein ZIP, in dem die Website, das Dashboard und das PHP-Backend
schon fertig gebaut sind. Node.js brauchst du dafür nicht – weder auf deinem
Rechner noch auf dem Server. Dort läuft nur PHP.

### 1. KAS vorbereiten (all-inkl)

1. Im **KAS** einloggen: <https://kas.all-inkl.com>
2. **Domain** → Domain anlegen oder auswählen. Merke dir das Verzeichnis,
   meist `/www/htdocs/wXXXXXXX/deine-domain.de`.
3. **Software → PHP-Version**: auf **PHP 8.2 oder höher** stellen,
   Betriebsart **FastCGI** (Standard) beibehalten.
4. **Domain → SSL-Schutz**: kostenloses **Let's-Encrypt-Zertifikat**
   aktivieren. Erst danach greift die HTTPS-Weiterleitung aus der `.htaccess`.
5. **FTP → FTP-Benutzer**: Zugang anlegen oder vorhandenen notieren.
6. **E-Mail**: eine Adresse der eigenen Domain anlegen, etwa
   `website@deine-domain.de`. all-inkl verwirft Mails mit fremden Absendern,
   deshalb braucht das Kontaktformular eine eigene.

### 2. Hochladen

ZIP entpacken und den **Inhalt** des Ordners per FTP in das
Domain-Verzeichnis laden – nicht den Ordner selbst.

Danach im FTP-Programm per Rechtsklick die Rechte prüfen:

| Ordner | Rechte |
|---|---|
| `uploads/` | 755 (oder 775) – muss beschreibbar sein |
| `api/storage/` | 755 (oder 775) – muss beschreibbar sein |

### 3. Einmal im Browser einrichten

Rufe auf:

```
https://deine-domain.de/api/scripts/setup.web.php
```

E-Mail und Passwort eintragen, **Einrichten** klicken. Das Skript legt die
Datenbank an, erstellt deinen Zugang, prüft die Schreibrechte – und **löscht
sich anschließend selbst**. Es bleibt also kein offener Einrichtungsdialog
im Netz stehen.

### 4. Alles Weitere im Dashboard

Unter `https://deine-domain.de/admin/` einloggen. Es gibt **keine Datei mehr,
die du anfassen musst** – auch Adresse und Mailversand stehen im Dashboard:

**Einstellungen → SEO → Adresse & Versand**

| Feld | Was hinein gehört |
|---|---|
| Adresse der Website | `https://deine-domain.de` (ohne Schrägstrich am Ende) |
| Absender für Kontaktmails | `website@deine-domain.de` – Adresse deiner Domain |
| Anfragen weiterleiten an | wohin Nachrichten gehen sollen |

Bleiben die Felder leer, funktioniert die Seite trotzdem: Die Adresse leitet
der Server dann aus der Anfrage ab, und Anfragen stehen ohnehin immer unter
„Nachrichten" im Dashboard.

---

## Weg B – Selbst bauen

Nur nötig, wenn du am Code etwas geändert hast.

| Was | Wofür |
|---|---|
| Node.js 20 oder neuer | baut Website und Dashboard |
| PHP 8.2 oder neuer | nur für die lokale Vorschau |

```bash
cd portfolio
npm run setup          # Abhängigkeiten installieren
npm run build          # erzeugt den Ordner deploy/
```

Danach liegt das fertige Paket in **`deploy/`** – der Inhalt entspricht genau
dem ZIP aus Weg A und wird genauso hochgeladen.

**Vorher lokal ansehen:**

```bash
php -S 127.0.0.1:8080 -t deploy scripts/preview.php
```

Zum Entwickeln mit sofortiger Aktualisierung – drei Terminals:

```bash
npm run dev:api      # PHP-Backend   → http://127.0.0.1:8787
npm run dev:web      # Website       → http://127.0.0.1:4321
npm run dev:admin    # Dashboard     → http://127.0.0.1:5173/admin/
```

Eine lokale Datenbank legst du mit `php api/scripts/setup.php
--email=du@example.de --password='MindestensZehnZeichen' --demo` an.

Die Datei `api/.env.php` (Vorlage: `api/.env.example.php`) gibt es weiterhin.
Sie ist optional – Werte aus dem Dashboard haben Vorrang. Sinnvoll ist sie
nur, wenn du etwas festschreiben willst, das nicht im Dashboard stehen soll.

---

## Beim zweiten Upload

> **Wichtig:** `uploads/` und `api/storage/` **nicht** überschreiben.
> Dort liegen deine hochgeladenen Dateien und die Datenbank mit allen
> Projekten, Nachrichten und Statistiken – außerdem die erzeugten
> Vorschaubilder (`uploads/og/`) und die Farbschema-Vorgabe
> (`uploads/theme.js`).

---

## Zum Schluss

0. **Systemcheck ansehen:** `https://deine-domain.de/admin/#/system`
   Dort steht auf einen Blick, ob PHP-Version, Bildbibliothek, Schreibrechte
   und Upload-Limits passen – und was zu tun ist, falls nicht. Gleich danach
   „Testmail senden", um den Mailversand zu prüfen.
1. **Dashboard öffnen:** `https://deine-domain.de/admin/`
2. **Einstellungen → Rechtliches** vollständig ausfüllen
   (Anschrift, Telefon, ggf. USt-IdNr.). In Deutschland ist ein vollständiges
   Impressum Pflicht.
3. **Einstellungen → Profil**: Vorstellungstext, Porträt, Logo, Social-Links.
4. **Projekte**: Beispielprojekte löschen, eigene anlegen.
5. **Kontaktformular testen** – Anfrage abschicken und prüfen, ob sie unter
   „Nachrichten" landet und die Mail ankommt.

---

## Updates einspielen

Wenn du am Design oder Code etwas änderst:

```bash
npm run build
```

Dann per FTP nur diese Ordner/Dateien ersetzen:

- alles außer `uploads/` und `api/storage/`

Inhalte (Projekte, Texte, Bilder) pflegst du ausschließlich im Dashboard –
dafür ist **nie** ein neuer Build nötig, Änderungen sind sofort live.

---

## Backup

**Im Dashboard:** Übersicht → „Backup herunterladen" lädt alle Inhalte als
JSON-Datei.

**Vollständig (empfohlen, monatlich):**

1. `api/storage/portfolio.sqlite` per FTP herunterladen (die komplette Datenbank)
2. Ordner `uploads/` herunterladen (alle Bilder und Videos)

Zum Wiederherstellen beides einfach zurückspielen.

---

## Wenn etwas nicht klappt

| Symptom | Ursache und Lösung |
|---|---|
| Weiße Seite, HTTP 500 | PHP-Version im KAS auf 8.2+ stellen. Details stehen in `api/storage/error.log`. |
| „Upload-Ordner nicht beschreibbar" | Rechte von `uploads/` auf 755/775 setzen. |
| Upload bricht bei großen Videos ab | KAS → PHP-Einstellungen: `upload_max_filesize` und `post_max_size` erhöhen (z. B. 256M). |
| Kontaktformular sendet keine Mail | `mail_from` in `api/.env.php` muss eine Adresse **deiner** Domain sein. Die Anfrage steht trotzdem im Dashboard. |
| `/work/mein-projekt` zeigt 404 | Die `.htaccess` im Hauptverzeichnis fehlt (versteckte Dateien im FTP-Programm einblenden). |
| Dashboard lädt endlos | Browser-Cache leeren; prüfen, ob `https://deine-domain.de/api/health` eine JSON-Antwort liefert. |
| Endlos-Weiterleitung nach dem Upload | SSL noch nicht aktiv. Im KAS Let's Encrypt aktivieren oder den HTTPS-Block in der `.htaccess` vorübergehend auskommentieren. |
| Passwort vergessen | `php api/scripts/setup.php --email=… --password='…' --force` per SSH, oder `api/storage/portfolio.sqlite` löschen und neu einrichten (Achtung: alle Inhalte weg). |

---

## Sicherheit – was bereits eingebaut ist

- Passwörter als bcrypt-Hash, Sitzungs-Token nur als Hash in der Datenbank
- CSRF-Schutz für alle schreibenden Zugriffe
- Brute-Force-Bremse beim Login (8 Versuche / 15 Minuten)
- Spam-Bremse und Honeypot im Kontaktformular
- Datenbank und Logs liegen hinter einer `Require all denied`-Regel
- In `uploads/` kann kein PHP ausgeführt werden
- Keine externen Skripte, keine Cookies von Dritten, Schriften selbst gehostet
