# Website-Refresh — Befund & Probedesign

> **Entschieden am 27.11.2026: v7 „Kino trifft Ordnung“ wird umgesetzt** (inkl. weichem Bildübergang am Handy).
> Grundlage für alle Phasen unten ist `prototype.html` in diesem Stand.

Stand: 27.11.2026 · Prototyp: `prototype.html` (v7 „Kino trifft Ordnung“, echte Inhalte, Bilder kommen von planigames.com,
nichts wird gespeichert). Dieser Ordner liegt bewusst außerhalb von `planigames/` und wird nie hochgeladen.
Ansehen: `prototype.html` im Browser öffnen (braucht Internet für Bilder & Schrift) – unten rechts
(am Handy oben) öffnet „Probedesign v7“ alle Ansichten.
Interaktive Vorschau: als private Seite in Claude veröffentlicht (gleicher Link wie bisher, Bilder verkleinert);
neu bauen mit `build-vorschau.py` (Aufruf steht oben im Skript). Frühere Entwürfe: `prototype-v1.html` und Git-Verlauf.

## Befund (aktuelle Website, v54)

**Was gut ist:** dunkler Look mit Studio-Orange, eigene Akzentfarbe je Spiel, starke Headline
„Unsere Ideen. Eure Abenteuer.“, flexibler Block-Baukasten, Deutsch/Englisch.

**Was die Seite schwächer wirken lässt:**

| Bereich | Befund |
|---|---|
| Spiele | FRONTLINERS erscheint doppelt („Aktuelles Projekt“ + Raster), das Raster endet mit einem verwaisten „Browser-Demos“-Kärtchen ohne Bild. |
| Rhythmus | Viel leerer Schwarzraum zwischen den Abschnitten; unterschiedliche Containerbreiten (Devlog-Liste schmaler als der Rest). |
| Spielseiten | Zwei riesige Trailer-Blöcke untereinander, Roadmap als lange Liste, viel Leerraum. |
| Devlog | Beiträge ohne Bild zeigen leere Raster-Platzhalter. |
| Anmeldungen | Newsletter-Block, Community-Block **und** Newsletter-Feld im Footer – dreimal fast dasselbe. |
| Schwebendes | „Unterstützen“-Button, Abzeichen, Cookie-Hinweis und Cursor-Ring liegen über Inhalten. |
| Technik | Tailwind wird live im Browser erzeugt (Play-CDN – laut Hersteller nicht für Produktion, langsamer Erstaufbau). Sehr große Bilder (Wobbly-Wizards-Cover 6,9 MB PNG, Logo 1,1 MB PNG). |

**Inhalte, die du direkt im Admin anpassen solltest:**
- Devlog-Beitrag „Wobbly Test Log“ (Text: „TEST“) ist öffentlich sichtbar.
- Wobbly Wizards ist laut Devlog pausiert, Status sagt aber „In Entwicklung“; die Roadmap-Termine
  (2025 Q3, 2026 Q1) liegen in der Vergangenheit; das Presse-Kit nennt Wobbly Wizards als „Aktuelles Spiel“.
- Der Button „Discord beitreten“ führt auf planigames.com statt zum Discord-Server.
- FRONTLINERS hat noch keinen Wishlist-/Steam-Link.

## Verlauf der Entwürfe

v1 „Cinematic Studio“ → „wirkt etwas viel und unüberlegt“ · v2 „Ruhe & Wucht“ → zu glatt, Charme fehlt ·
v3 „Charme“ (Maskottchen raus auf Wunsch) → „Ich mag das Design nicht“ · Abstimmung: **Live-Seite auffrischen**,
gestört hat „fühlt sich fremd an“ und „riesige Schrift & Leere“ · v4 „Aufgefrischt“ → „sie soll halt auch durchdacht sein“ ·
v5 „Durchdacht“ → zu wenig verändert, zu große Schrift auf der Startseite, neue Schrift gefällt nicht (lieber Montserrat),
Fortschrittsanzeige überzeugt nicht · v6 „Ruhig & hochwertig“ (Montserrat, moderate Größen) → schön aufgeräumt, aber
**ohne Charakter**; Bilder im Kopfbereich passen am Handy nicht gut. Wunsch: **v1 und v6 mischen** – der
**Durchlauf durch die Spiele im Kopfbereich von v1 war cool, aber der Text muss sich passend zum Spiel ändern** ·
**v7 „Kino trifft Ordnung“.**

## Design v7 „Kino trifft Ordnung“

**Idee:** Charakter und Kino-Gefühl aus v1 (große Spielbilder, Spielfarben, Filmkorn, Studio-Details),
Ordnung und Ruhe aus v6 (eine Schrift, moderate Größen, feste Abläufe, sorgfältige Abstände).

| Thema | Entscheidung |
|---|---|
| Kopfbereich Startseite | **Spiele-Durchlauf:** alle 7 s das nächste Spiel – Bild, Logo, Status, Genre, Satz, Beschreibung und Buttons wechseln **gemeinsam**; Hauptbutton in der Farbe des Spiels („Zum Spiel“), zweiter Button passend (Trailer, sonst Playtest). Darunter drei Spiel-Kacheln mit Ladebalken, Zähler 01/03 und Pause-Knopf. Hält an bei Maus darüber, Tastatur-Fokus, verdecktem Tab oder wenn man weggescrollt hat; Wischen am Handy, Pfeiltasten am Rechner; bei „weniger Bewegung“ kein automatischer Wechsel. |
| Bilder am Handy/Tablet | Bild **ganz oben in voller Breite** (nicht mehr hinter dem Text), mit eigenem Bildausschnitt je Spiel; Logos stehen in allen Folien auf derselben Linie an der Bildkante, Text darunter – alle Folien gleich aufgebaut, nichts verrutscht. |
| Schrift | **Montserrat** für alles (400–800). Größte Überschrift 38 px (Startseite), Unterseiten max. 48 px. |
| Farben | Schwarz + Studio-Orange; **jede Spielseite und Spielkarte in ihrer eigenen Spielfarbe** (FRONTLINERS Gelb-Orange, Fall of Kings Rot, Wobbly Wizards Orange). |
| Charakter-Details | Filmkorn auf großen Bildern, farbiges Glühen hinter Karten/Videos, nummerierte Abschnitts-Kicker mit Linie („01 — Unsere Spiele“), große Anführungszeichen beim Team, Laufband mit ✦, gestrichelte Demo-Box. |
| Spielseite | Kopfbereich mit Steckbrief-Leiste (Genre · Spieler · Plattform · Status); **Abschnitts-Menü** klebt oben und zeigt, wo man ist; Kopfzeile blendet sich beim Runterscrollen aus. |
| Weggelassen | Maskottchen, Fortschrittsbalken/Prozente, Riesenschrift, Konzept-Notizen. |

**Startseite:** Spiele-Durchlauf → Laufband → 01 Unsere Spiele (3 Karten + Demos) → 02 Über uns
(„Unsere Ideen. Eure Abenteuer.“, Text, Kennzahlen, Bildcollage, 6 Werte) → 03 Team (Porträts) →
04 Devlog (großer Beitrag + 3 kleine) → Newsletter + YouTube/TikTok → Footer.

**Spielseite (feste Reihenfolge, fehlende Blöcke entfallen, Nummern zählen automatisch):** Kopfbereich → Abschnitts-Menü →
Über das Spiel (+ Demo) → Einblicke (2 Videos oder Video + 3 Bilder) → Features → Roadmap-Zeitleiste (bei „Pausiert“
Hinweis + Link) → Mitmachen (Anmeldung + Ideen/Abstimmung) → FAQ → Devlog zum Spiel → Weitere Spiele.

**Unterseiten:** Spiele (Karten + Demo-Downloads), Devlog (neuester Beitrag groß, Filter nach Spiel), Beitrag
(Lesespalte, Zwischenüberschriften, „Mehr zum Spiel“ + „Nächster Beitrag“).

**Geprüft:** 59 Klick-Tests (u. a. Durchlauf, Pause, Wischen, Tastatur, Sprungmarken, Formulare, Filter) und Darstellung
auf iPhone SE/13, Pixel 7, iPad Mini, 320/820/960/1024/1440 px – nichts ragt über den Rand.

## Was im Admin dazukäme (bestehende Daten bleiben gültig)

- Status „Pausiert“ (+ kurzer Hinweistext); der nächste Schritt ergibt sich automatisch aus dem Status.
- Spiel-Steckbrief (frei benennbare Zeilen, z. B. Genre · Spieler · Plattform) – ohne Angaben keine Leiste.
- Je Spiel: Kurzbeschreibung + Genre-Zeile für Karte und Durchlauf, Bild für den Durchlauf (Standard: Titelbild)
  mit Bildausschnitt für Desktop und Handy (wie der vorhandene Fokuspunkt).
- Reihenfolge/Auswahl der Spiele im Durchlauf (Standard: alle sichtbaren Spiele in Listen-Reihenfolge).
- Spielseite: Blöcke werden in der festen Reihenfolge angezeigt (Reihenfolge im Admin muss niemand mehr pflegen).

## Phasen (jede einzeln hochladbar, Admin funktioniert durchgehend)

Hochladen wie immer: alles aus `planigames/` außer `data/` und `media/` (siehe PLANIGAMES-SETUP.md).
Man kann jede Phase einzeln hochladen oder mehrere zusammen.

1. **Fundament – erledigt (27.11.2026):** neue Stildatei `assets/site.css`; Montserrat überall (alte Schriften
   Clash Display/Archivo/JetBrains entfernt); ruhigere Überschriften auf allen noch alten Seiten;
   neue Kopfzeile (Ring-Logo + Schriftzug, Spiele · Studio · Devlog · Kontakt, DE/EN, „Unterstützen“,
   „Zum Playtest“ → Warteliste des aktuellen Projekts; blendet sich beim Runterscrollen aus) und neues Handy-Menü;
   neuer Footer (Spiele-Liste aus den Daten, Studio, Kontakt, Kanäle) und „Bleib dran“-Block (Newsletter + Folge uns)
   auf Spiele-, Devlog-, Kontakt- und Presse-Seite; „Unterstützen“ sitzt in Kopfzeile/Footer statt schwebend;
   eigener Mauszeiger-Ersatz und Scroll-Balken entfernt; Discord erscheint automatisch (Startseite, „Folge uns“,
   Footer), sobald im Admin unter *Studio → Community-Banner* ein echter Einladungslink steht.
   Geändert: alle Seiten-Dateien (Kopfbereich), `assets/app.js`, `assets/planigames.css`, `assets/site.css` (neu),
   `admin/schema.php` (nur Hinweistexte).
2. **Startseite – erledigt (28.11.2026):** Spiele-Durchlauf oben (startet mit dem „Aktuellen Projekt“ = FRONTLINERS;
   Bild, Logo, Status, Genre, Satz, Beschreibung und Buttons wechseln gemeinsam; Pause, Wischen, Pfeiltasten;
   am Handy Bild oben in voller Breite mit weichem Übergang) → Laufband → 01 Unsere Spiele (Karten aller Spiele mit
   Titelbild, Spiele ohne Bild wie „Browser-Demos“ als schmale Zeile) → 02 Über uns (Claim, Text, Kennzahlen
   aus den Daten, Bild-Collage, Werte) → 03 Team (Klick öffnet das Profil) → 04 Devlog (großer + 3 kleine Beiträge)
   → ein Newsletter-Block mit Kanälen statt drei Anmeldungen. Alles kommt aus den Admin-Daten; fehlen Angaben,
   wird sinnvoll ergänzt (z. B. Beschreibung = erster Satz aus „Über das Spiel“).
   **Neu im Admin:** Status „Pausiert“ (Auswahl, Übersicht, Cockpit, Website); je Spiel unter *Darstellung →
   Startseite & Spielkarten*: Genre-Zeile, Kurzbeschreibung, Bild im Durchlauf; unter *Studio & Startseite*:
   bis zu 3 Bilder für „Über uns“; Hinweistexte an das neue Design angepasst. Bestehende Daten bleiben gültig.
   Discord (sobald verlinkt) erscheint im „Folge uns“-Bereich mit Überschrift, Text und Button-Text des
   Community-Banners. Seitentitel & Google-Beschreibung der Startseite neutral formuliert (statt „Aktuell entsteht
   Wobbly Wizards“).
   Geändert: `index.html`, `assets/app.js`, `assets/site.css`, `admin/schema.php`, `admin/view-games.php`,
   `admin/index.php`, `admin/lib.php` (nur Versionsnummer), `admin/assets/admin.css` (Schild „Pausiert“).
3. **Spielseite – erledigt (28.11.2026):** Kopfbereich wie im Probedesign (Brotkrumen, Status + Genre, Logo,
   Satz, nächster Schritt: Wishlist → Warteliste „Für den Playtest eintragen“/„Zur Beta anmelden“/„Benachrichtigt
   werden“ → „Demo spielen“; dazu „Trailer ansehen“), Steckbrief-Leiste, mitlaufendes Abschnitts-Menü und feste
   Reihenfolge: Über das Spiel → Selbst spielen → Technik → Einblicke (Video + Bilder-Raster, Großansicht) →
   Features (+ Welt-Teaser) → Stimmen → Countdown → Roadmap (Zeitleiste, bei „Pausiert“ Hinweis statt alter
   Termine) → Mitmachen (Warteliste mit Platz & Empfehlungslink, Vorschläge mit Abstimmung, Store-Widget,
   Aufruf-Banner nur mit echtem Link) → FAQ → Devlog → Weitere Spiele. Alle bisherigen Blocktypen bleiben nutzbar;
   „Abstand“-Blöcke entfallen (Abstände macht das Design). YouTube & Web-Demos laden weiter erst nach Zustimmung.
   **Neu im Admin:** *Darstellung → Steckbrief (Spielseite)* (bis zu 4 Zeilen, sonst die Kennzahlen),
   *Einstellungen → Hinweis bei „Pausiert“*; Hinweistexte (Reihenfolge, Handy-Ansicht) angepasst.
   „Kopfbereich gestalten“ (Logo-Platzierung) arbeitet unverändert mit der neuen Seite.
4. **Unterseiten – erledigt (28.11.2026):** alle übrigen Seiten im neuen Look mit ruhigem Seitenkopf.
   *Spiele:* Karten wie auf der Startseite, Sammlungen ohne Titelbild (Browser-Demos) als Kasten mit allen Downloads,
   Zähler „3 Spiele · 1 Demo-Sammlung“. *Devlog:* Filter nur für Spiele mit Beiträgen (als Links, teilbar),
   neuester Beitrag groß, Sprechblase mit Kommentar-Anzahl, ab 13 Beiträgen „Weitere Beiträge laden“; bei einem
   Spiel-Filter füllt „Alle Beiträge im Devlog“ eine halbe Reihe. *Beitrag:* Brotkrumen, Titel, Spiel-Schild,
   Datum + Lesezeit, Titelbild, Text, Schlagworte (ohne Doppelte), Reaktionen und Teilen (Symbole statt Emojis),
   Kommentare (Anfangsbuchstabe als Bild, „wird geprüft“-Vorschau, ältere nachladen), unten „Mehr zum Spiel“ und
   „Nächster Beitrag“; Wishlist-Kasten nur mit echtem Steam-Link. *Kontakt:* Formular mit Datenschutz-Hinweis,
   daneben E-Mail und Press Kit; ist das Formular im Admin aus, steht dort die E-Mail-Adresse. *Press Kit:*
   Fakten, Downloads mit Dateityp, Presse-Kontakt, je Spiel Logo + Titelbild zum Herunterladen; ist es im Admin aus,
   erscheint „nicht verfügbar“. *Lore:* neue Überschriften/Knöpfe, ohne Titelbild kompakter Kopf. *Rechtliches:*
   Reiter Impressum/Datenschutz; auf Englisch Hinweis, dass die Rechtstexte auf Deutsch gelten. *404* und alle
   „nicht gefunden“-Fälle (Spiel, Beitrag, Welt) einheitlich; die 404-Seite funktioniert auch bei tieferen Adressen.
   Geändert: `games.html`, `devlog.php`, `404.html`, `assets/app.js`, `assets/site.css`.
5. **Feinschliff – erledigt (28.11.2026), Version 56:**
   *Schneller Seitenaufbau:* Das Tailwind-Programm aus dem Internet (rechnete die Gestaltung bei jedem Besuch im
   Browser aus) ist ersetzt durch die feste, 9 KB kleine Datei `assets/tailwind.css` – gebaut mit Tailwind 3.4.17
   (dieselbe Version wie vorher) und denselben Einstellungen; Bauanleitung in `design/website-redesign/tailwind/`.
   Automatischer Vorher/Nachher-Vergleich aller Elemente und Eigenschaften auf 52 Seitenzuständen (Computer + Handy,
   Menü, Team-Profil, Abzeichen, Formulare, Cookie-Hinweis): identisch. Die Markdown-Bibliothek lädt jetzt in fester
   Version (15.0.12, wie bisher) und ohne den Seitenaufbau zu blockieren.
   *Schnellere Bilder (WebP):* Beim Hochladen entsteht zu jedem JPG/PNG automatisch `name.jpg.webp`
   (Fotos/Bilder ohne Transparenz in hoher Qualität, Logos/Freisteller verlustfrei; Handy-Fotos richtig gedreht);
   die `.htaccess` liefert sie unter derselben Adresse an Browser, die WebP können (mit `Vary: Accept`), sonst das
   Original. Im Admin unter *Medien* einmalig „⚡ Jetzt für … ältere Bilder erstellen“ (läuft in Etappen). In der
   Testumgebung mit den echten Bildern: 28 Bilder zusammen 28 MB kleiner (z. B. Titelbild 7 MB → 0,2 MB).
   Kopien erscheinen in keiner Liste (Medien, Auswahl, Suche, Größen-Hinweis) und werden beim Löschen mitgelöscht.
   Mit echtem Apache geprüft (Auslieferung, Vary, Datenschutz-Regeln für `data/` unverändert 37/37).
   *Englische Seite:* Meldungen nach dem Absenden (Newsletter, Kontakt, Kommentare, Warteliste, Vorschläge),
   Cookie-Hinweis und Bedien-Beschriftungen jetzt auch auf Englisch.
   Geändert: alle Seiten-Dateien (Kopfbereich), `assets/app.js`, `assets/tailwind.css` (neu), `.htaccess`,
   `admin/lib.php`, `admin/index.php`, `PLANIGAMES-SETUP.md`.

6. **Feedback-Runde – erledigt (28.11.2026), Version 57:**
   *Durchlauf & Laufband* spielen immer automatisch – auch wenn das Gerät „Bewegung reduzieren“ meldet (genau das
   war bei euch eingestellt und hat beides angehalten); der Pause-Knopf bleibt, das Laufband hält bei Mauskontakt.
   *Knöpfe im Durchlauf* je Spiel frei wählbar (Admin → Spiel → Darstellung → „Durchlauf: Video & Knöpfe“):
   Zum Spiel, Steam-Wishlist, Trailer, Playtest/Warteliste, Demo, Devlog, Welt-Seite, eigener Link oder keiner –
   mit eigenem Text; „Automatisch“ = bisheriges Verhalten. *Video im Durchlauf*: je Spiel Bild oder Video
   (MP4/WebM, stumm, Schleife). *Knöpfe im Kopfbereich der Spielseite* ebenso wählbar (Hero-Block).
   *Knopf oben rechts*: Admin → Studio & Startseite → „Knopf oben rechts (Kopfzeile)“ (Playtest, Wishlist,
   Spielseite oder Trailer eines Spiels, eigener Link, aus; eigener Text). *Über uns*: drei feste Bildplätze mit
   Formatangabe und Bildausschnitt (🎯 Fokus) statt automatisch gewählter Bilder; eine früher angelegte Bilderliste
   wird übernommen. *Icons statt Emojis*: gezeichnete Linien-Icons (wie im Probedesign) bei Werten, Feature-Kacheln,
   Download-Plattformen, Pause-Hinweis, Team-Ort und Admin-Link; im Admin per Klick aus 52 Icons wählbar, alte
   Emojis werden automatisch passend übersetzt. *Spielseite*: Abschnitte erscheinen genau in der Reihenfolge der
   Seiten-Blöcke im Admin; direkt aufeinanderfolgende Blöcke derselben Art werden zu einem Abschnitt, ein einzelnes
   Banner/Widget/Welt-Teaser steht ohne Überschrift genau an seiner Stelle. *Press Kit*: eigener Menüpunkt
   „Spielbetrieb → Press Kit“ (Einleitung, Kontakt, Fakten, Downloads, Auswahl der Spiele mit Kurztext,
   Logo/Titelbild an/aus, Trailer-Link und weiteren Dateien; PDFs sind jetzt als Upload erlaubt). Felder wie
   „Eigener Link“ erscheinen im Admin nur, wenn sie gebraucht werden.
   Geändert: alle Seiten-Dateien (Version 57), `assets/app.js`, `assets/site.css`, `admin/schema.php`, `admin/lib.php`,
   `admin/index.php`, `admin/view-sections.php`, `admin/assets/admin.css`, `admin/assets/admin.js`.

**Hinweis Hintergrund-Effekt:** Im Admin steht unter *Studio → Hintergrund & Atmosphäre* noch „Nur Partikel, viel,
lebhaft“. Die aufsteigenden Partikel gibt es in der Design-Vorschau nicht – für denselben ruhigen Look dort
„Aus / minimal“ wählen (Einstellung bleibt euch überlassen, die Funktion selbst ist unverändert).

## Offene Fragen

- ~~Durchlauf-Start~~ → entschieden: immer mit FRONTLINERS (aktuelles Projekt).
- ~~Discord~~ → entschieden: Link kommt später in den Admin; die Website zeigt Discord automatisch, sobald er eingetragen ist.
