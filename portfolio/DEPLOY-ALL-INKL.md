# Portfolio auf all-inkl veröffentlichen

Schritt für Schritt vom lokalen Ordner zur fertigen Website. Rechne beim
ersten Mal mit etwa 30 Minuten – danach dauert ein Update knapp 2 Minuten.

---

## Was du brauchst

| Was | Wofür | Woher |
|---|---|---|
| Node.js 20 oder neuer | baut Website und Dashboard | <https://nodejs.org> |
| PHP 8.2 oder neuer | nur für die lokale Vorschau | <https://www.php.net> |
| FTP-Programm | Upload zum Webspace | FileZilla, Cyberduck, Transmit |
| all-inkl-Paket | Hosting | ab „PrivatPlus" ausreichend |

Auf dem Server selbst brauchst du **kein** Node.js – dort läuft nur PHP.

---

## Schritt 1 – Lokal einrichten

```bash
cd portfolio
npm run setup                       # installiert Frontend + Dashboard
php api/scripts/setup.php --email=deine@mail.de --password='MindestensZehnZeichen' --demo
```

Der letzte Befehl legt die Datenbank an, erzeugt deinen Zugang und spielt vier
Beispielprojekte ein (`--demo` weglassen, wenn du leer starten willst).

**Lokal ansehen** – drei Terminals:

```bash
npm run dev:api      # PHP-Backend   → http://127.0.0.1:8787
npm run dev:web      # Website       → http://127.0.0.1:4321
npm run dev:admin    # Dashboard     → http://127.0.0.1:5173/admin/
```

---

## Schritt 2 – KAS vorbereiten (all-inkl)

1. Im **KAS** einloggen: <https://kas.all-inkl.com>
2. **Domain** → deine Domain anlegen oder auswählen. Merke dir das
   Verzeichnis, meist `/www/htdocs/wXXXXXXX/deine-domain.de`.
3. **Software → PHP-Version**: auf **PHP 8.2 oder höher** stellen.
   Wichtig: Betriebsart **FastCGI** (Standard) beibehalten.
4. **Domain → SSL-Schutz**: kostenloses **Let's-Encrypt-Zertifikat** aktivieren.
   Erst danach greift die HTTPS-Weiterleitung aus der `.htaccess`.
5. **FTP → FTP-Benutzer**: Zugang anlegen (oder vorhandenen notieren).
6. **E-Mail**: eine Adresse der eigenen Domain anlegen, z. B.
   `website@deine-domain.de`. Sie wird gleich als Absender fürs Kontaktformular
   gebraucht – all-inkl verwirft Mails mit fremden Absendern.

---

## Schritt 3 – Konfiguration hinterlegen

Kopiere `api/.env.example.php` nach `api/.env.php` und trage deine Werte ein:

```php
return [
    'app_env'   => 'production',
    'site_url'  => 'https://deine-domain.de',
    'mail_from' => 'website@deine-domain.de',   // Adresse DEINER Domain
    'mail_to'   => 'deine@mail.de',             // wohin Anfragen gehen
];
```

Diese Datei ist bewusst nicht im Git-Repository – sie gehört nur auf den Server
(und in deinen lokalen Ordner).

---

## Schritt 4 – Bauen

```bash
npm run build
```

Danach liegt alles Fertige im Ordner **`deploy/`**:

```
deploy/
  index.html, work/, about/, contact/ …   die Website
  _astro/                                 Styles, Skripte, Schriften
  admin/                                  das Dashboard
  api/                                    PHP-Backend
  uploads/                                Medien
  .htaccess                               Weiterleitungen, Cache, Sicherheit
```

**Vorher lokal testen** (zeigt exakt das Ergebnis von oben):

```bash
php -S 127.0.0.1:8080 -t deploy scripts/preview.php
```

---

## Schritt 5 – Hochladen

Verbinde dich per FTP und lade den **Inhalt** von `deploy/` in das
Domain-Verzeichnis (nicht den Ordner `deploy` selbst).

> **Wichtig ab dem zweiten Upload:**
> `uploads/` und `api/storage/` **nicht** mit hochladen bzw. nicht überschreiben.
> Dort liegen deine hochgeladenen Dateien und die Datenbank mit allen Projekten,
> Nachrichten und Statistiken.

Rechte prüfen (im FTP-Programm per Rechtsklick → Dateirechte):

| Ordner | Rechte |
|---|---|
| `uploads/` | 755 (oder 775) – muss beschreibbar sein |
| `api/storage/` | 755 (oder 775) – muss beschreibbar sein |

---

## Schritt 6 – Einrichtung im Browser

Rufe **einmalig** auf:

```
https://deine-domain.de/api/scripts/setup.web.php
```

Dort E-Mail und Passwort eintragen → **Einrichten**. Das Skript legt die
Datenbank an und prüft die Schreibrechte.

**Danach die Datei `api/scripts/setup.web.php` per FTP löschen.**
(Sie sperrt sich zwar selbst, sobald ein Zugang existiert – weg ist trotzdem
sicherer.)

Wenn dein Paket SSH bietet, geht es auch ohne Browser:

```bash
ssh sshXXXXX@deine-domain.de
cd /www/htdocs/wXXXXXXX/deine-domain.de
php8.2 api/scripts/setup.php --email=deine@mail.de --password='…'
```

---

## Schritt 7 – Fertig einrichten

1. **Dashboard öffnen:** `https://deine-domain.de/admin/`
2. **Einstellungen → Rechtliches** vollständig ausfüllen
   (Anschrift, Telefon, ggf. USt-IdNr.). In Deutschland ist ein vollständiges
   Impressum Pflicht.
3. **Einstellungen → Profil**: Vorstellungstext, Verfügbarkeit, Social-Links.
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
