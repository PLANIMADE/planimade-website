# Admin-Redesign — Befund & Plan

Stand: 27.11.2026 · Prototyp: `prototype.html` (Beispieldaten, nichts wird gespeichert).
Dieser Ordner liegt bewusst außerhalb von `planigames/` und wird nie hochgeladen.

## Abgleich Live ↔ Repo
- Frontend live v50, Admin-Assets v58 — identisch mit Branch `claude/planigames-studio-site-4hngfe`.
- Alle 23 öffentlich abrufbaren Code-Dateien byte-identisch. Kein anderer Branch enthält Planigames-Änderungen.
- Live-Inhalte: 4 Spiele (FRONTLINERS `warcats`, Fall of Kings, Wobbly Wizards, Browser-Demos), Team 3, Devlog 5.

## Sicherheitsfund (behoben in Commit a996c14, Upload nötig)
`data/` wird nie hochgeladen → die live liegende `data/.htaccess` war veraltet. Öffentlich abrufbar waren u. a.
`mail.json` (SMTP/IMAP-Passwort), `waitlist.json` (338 × E-Mail + IP), `contacts.json`, `keys.json` (17 freie Keys),
`mail_sent.json`, `activity.json`, `suggestions.json`, `comments.json`, `board.json`, `trash.json`.
Fix: Whitelist in der **Root-`.htaccess`** (geht bei jedem Deployment mit) + `data/.htaccess` als Whitelist.
Mit Apache 2.4 getestet (3 Szenarien × 25 Prüfungen inkl. Umgehungsversuchen).
To-dos: Root-`.htaccess` + einmalig `data/.htaccess` hochladen · Postfach-Passwort ändern · 17 Keys ersetzen ·
Zugriffslogs prüfen · DSGVO-Meldepflicht (Art. 33, 72 h) klären.

## Ursachen der gemeldeten Probleme
| Problem | Ursache (Code) |
|---|---|
| Slug ändern = „neue Seite, alles weg" | `pg_trash_capture` vergleicht per Slug → alte Version landet im Papierkorb. Vorschläge/Warteliste (`scope`) und Board-Karten (`game`) hängen am alten Slug. Alte URL zeigt per Fallback das **erste** Spiel (`games[0]`). |
| Logo-Platzierung „Glücksspiel" | (1) Leere Zahlenfelder werden als `0` gespeichert → „Standard" setzt `{x:0,y:0}`, Logo springt in die Ecke. (2) Picker-Größe 20 % × Skalierung vs. live `clamp(64px, 18vh, 150px) × Skalierung` → bis ~70 % Abweichung. (3) Picker 16:9 vs. Hero ~2:1, Fokuspunkt des Covers ignoriert, Tagline/Buttons unsichtbar. (4) Logo-Dateien mit viel transparentem Rand (FRONTLINERS-SVG: 76 %). Live-Befund: FRONTLINERS überdeckt auf 1366×768 das „Demo verfügbar"-Badge. |
| Mail ohne Fett/Kursiv | `nl2br(htmlspecialchars())` — alles wird zu Klartext. |
| Keine Anhänge senden | Einteilige `text/html`-Mail, kein Multipart, keine Text-Alternative (auch schlecht für Spam-Score). |
| Keine Anhänge empfangen | `pg_imap_part` liest nur Textteile, Anhänge werden ignoriert. |
| Spiele alle aufgeklappt | Auto-Einklappen erst ab > 4 Einträgen; alle Spiele in einem Riesen-Formular. |
| Nur eine Signatur | `mail.json` kennt genau einen Satz `sig_*`-Felder. |

Weitere Notizen: Wobbly-Wizards-Cover ist ein 6,9-MB-PNG; Wobbly-Wizards-Logo ein 1,1-MB-PNG (16:9-Leinwand).

## Konzept (siehe Prototyp)
- **Navigation:** Seitenleiste nach Arbeitsbereichen (Inhalte · Community · Spielbetrieb · Planung & Werkzeuge · System),
  Suche/Befehlspalette (⌘K), mobil Tab-Leiste unten + Menü.
- **Cockpit:** „Heute wichtig" (priorisierte To-dos aus allen Bereichen), Kennzahlen mit Verlauf, Spiele-Karten,
  Aktivität, System & Sicherheit, Übersetzungsstand.
- **Spiele:** Liste links (Suche, Filter, Sortieren per Ziehen) → Editor rechts mit Tabs
  Übersicht · Seiten-Aufbau · Darstellung · Community · Einstellungen. Blöcke ausblenden statt löschen.
- **Web-Adresse ändern:** geschützt, eigener Dialog, zieht Warteliste/Vorschläge/Board/EN mit um, alte Adresse leitet per 301 weiter.
- **Logo platzieren (WYSIWYG):** echte Hero-Geometrie je Bildschirmgröße (Laptop/Full HD/1440p/Handy), echte Texte,
  Ampel pro Größe, Messung über sichtbare Pixel, Einrasten in der Mitte, „Standard" funktioniert wieder.
  Beim Upload transparenten Rand automatisch zuschneiden.
- **Postfach:** 3-spaltig, Anhänge empfangen/herunterladen, Editor mit Formatierung (sicher gefiltert),
  Anhänge bis 10 MB, Vorlagen, Entwürfe.
- **Signaturen:** beliebig viele (Studio + pro Person), Auswahl beim Schreiben, Standard pro Admin,
  „Aus Team-Profilen übernehmen".
- **Spiel-Inbox (Live-Ops):** später, wenn das externe Tool steht. Das Design im Prototyp hat dem Nutzer gefallen —
  als Vorlage für die Integration behalten (Server-Proxy für den Spiel-API-Schlüssel wie beim Vault, Protokoll).

## Phasen (jede einzeln hochladbar, Datenformate bleiben kompatibel)
1. Fundament: neues Design-System + App-Shell (Seitenleiste, Kopfzeile, ⌘K, mobile Tab-Leiste) für alle bestehenden Seiten.
2. Cockpit.
3. Spiele: Master-Detail, Web-Adresse mit Daten-Umzug + Weiterleitungen, Papierkorb über stabile ID,
   Zahlenfeld-Fix, WYSIWYG-Logo-Platzierung, Auto-Zuschnitt beim Upload.
4. Postfach: Formatierung + Filter, Multipart-Versand mit Anhängen + Text-Alternative, Anhänge empfangen, Signaturen.
5. Übrige Bereiche in neuer Optik (Community, Planung, Werkzeuge, System).
6. Spiel-Inbox anbinden (sobald API/Tool feststehen).
