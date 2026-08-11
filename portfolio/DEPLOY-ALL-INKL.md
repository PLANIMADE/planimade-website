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
   aktivieren. Das klappt erst, wenn die Domain öffentlich auflöst – ein
   Zertifikat wird nur für erreichbare Domains ausgestellt.

   Meldet das KAS „Für diese Domain wurden keine DNS-Informationen gefunden",
   ist die Domain noch **nicht registriert** oder ihre Nameserver zeigen
   woandershin. Im KAS eine Domain anzulegen registriert sie nicht – das ist
   ein eigener Schritt unter **Domain → Domain bestellen**. Nach der
   Registrierung dauert es bis zu 24 Stunden.

   Solange kannst du alles schon über die **Übergangsdomain** ansehen, die
   im KAS neben der Domain steht (`…kasserver.com`). Sie ist von der
   HTTPS-Weiterleitung ausgenommen und funktioniert auch ohne Zertifikat.
5. **FTP → FTP-Benutzer**: Zugang anlegen oder vorhandenen notieren.
6. **E-Mail**: ein Postfach der eigenen Domain anlegen, etwa
   `website@deine-domain.de`, und Passwort sowie Servername notieren
   (steht im KAS beim Postfach, meist `wXXabcde.kasserver.com`).
   all-inkl verwirft Mails mit fremden Absendern – und über das Postfach
   erfährt man wenigstens, warum, wenn doch etwas schiefgeht.

### 2. Hochladen

ZIP entpacken und den **Inhalt** des Ordners per FTP in das
Domain-Verzeichnis laden – nicht den Ordner selbst.

Um versteckte Dateien musst du dich nicht kümmern: Die `.htaccess`-Dateien
beginnen mit einem Punkt und werden von vielen FTP-Programmen ausgelassen.
Der Server legt fehlende beim ersten Aufruf selbst an – die Vorlagen dafür
liegen als gewöhnliche Textdateien in `api/assets/server/`.

Danach im FTP-Programm per Rechtsklick die Rechte prüfen:

| Ordner | Rechte |
|---|---|
| `uploads/` | 755 (oder 775) – muss beschreibbar sein |
| `api/storage/` | 755 (oder 775) – muss beschreibbar sein |

### 3. Dashboard aufrufen

```
https://deine-domain.de/admin/
```

Beim ersten Aufruf steht dort kein Login, sondern ein Formular zum **Anlegen
deines Zugangs** – die Datenbank existiert ja noch nicht. E-Mail und Passwort
eintragen, fertig: Du bist direkt angemeldet. Ab dem zweiten Mal erscheint an
derselben Stelle der normale Login.

Es gibt also genau **eine Adresse**, die du dir merken musst.

### 4. Alles Weitere im Dashboard

Es gibt **keine Datei, die du anfassen musst** – auch Adresse und Mailversand
stehen im Dashboard:

**Einstellungen → SEO → Adresse & Versand**

| Feld | Was hinein gehört |
|---|---|
| Adresse der Website | `https://deine-domain.de` (ohne Schrägstrich am Ende) |
| Absender für Kontaktmails | `website@deine-domain.de` – Adresse deiner Domain |
| Anfragen weiterleiten an | wohin Nachrichten gehen sollen |
| Mailserver / Port | `wXXabcde.kasserver.com`, Port 587 |
| Postfach / Passwort | Zugangsdaten desselben Postfachs |

Bleiben die Felder leer, funktioniert die Seite trotzdem: Die Adresse leitet
der Server dann aus der Anfrage ab, und Anfragen stehen ohnehin immer unter
„Nachrichten" im Dashboard.

### Bewerbungs-Radar

Im Dashboard unter **Bewerbungen** – hinter demselben Login, nirgends öffentlich
verlinkt. Beim ersten Aufruf liest er die mitgelieferte Liste aus
`api/assets/bewerbung/agenturen.json` ein. Diese Datei bleibt von Hand pflegbar;
„Nachschub aus Datei" ergänzt später Hinzugekommenes, ohne Notizen anzufassen.

Unterlagen (Lebenslauf, Mappe, Zeugnisse) landen in `uploads/bewerbung/` und sind
öffentlich abrufbar – der Ordner überlebt jeden erneuten Upload der Website. Die
kurze Adresse dorthin regelt die `.htaccess`:

```
https://deine-domain.de/bewerbung/dateien/lebenslauf.pdf
```

Verschickt wird über ein Postfach, das im Reiter „Anschreiben" eingetragen wird –
bei Gmail `smtp.gmail.com`, Port 587, mit einem **App-Passwort** aus dem
Google-Konto (Zwei-Faktor-Anmeldung muss aktiv sein). Optional legt ein
IMAP-Zugang jede verschickte Bewerbung im Ordner „Gesendet" ab; bei Gmail ist das
nicht nötig, dort passiert es von selbst.

**Zum Mailversand:** Ohne Postfach übergibt die Seite die Nachricht dem
Mailprogramm des Servers und erfährt nie, was daraus wird – nicht angenommen,
im Spam gelandet oder verworfen sieht von außen alles gleich aus. Mit
hinterlegtem Postfach meldet sich die Seite am Mailserver an, und jeder
Fehler steht im Klartext unter **System**. Danach einmal „Testmail senden".

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
| Kontaktformular sendet keine Mail | Unter **System → Mailversand** steht, welcher Absender und Empfänger verwendet werden. Ist kein Postfach hinterlegt, trage eines ein (Einstellungen → SEO) – dann nennt „Testmail senden" den genauen Grund. Die Anfrage steht in jedem Fall unter „Nachrichten". |
| Mail kommt an, aber im Spam | Absender muss eine Adresse **deiner** Domain sein und als Postfach existieren. Mit hinterlegtem Postfach passen Absender und Umschlag zusammen, das ist die halbe Miete. |
| `/work/mein-projekt` zeigt 404 | Die `.htaccess` im Hauptverzeichnis fehlte und wurde noch nicht ergänzt – einmal die Startseite aufrufen, dann legt der Server sie an. Bleibt es dabei, steht der Grund im Systemcheck. |
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
