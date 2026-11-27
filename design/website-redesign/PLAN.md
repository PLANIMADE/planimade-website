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
2. **Startseite:** Spiele-Durchlauf (startet immer mit FRONTLINERS), Abschnitte und Karten wie im Probedesign,
   eine Anmeldung statt drei.
3. **Spielseite:** feste Reihenfolge, Steckbrief-Leiste, Abschnitts-Menü, Medien-Raster, Roadmap-Zeitleiste,
   Mitmachen an einem Ort, „Pausiert“-Logik.
4. **Unterseiten:** Spiele-Übersicht, Devlog (Liste + Beitrag), Presse-Kit, Kontakt, Lore, Rechtliches, 404.
5. **Feinschliff:** Tailwind-Laufzeit entfernen (schnellerer Seitenaufbau), Bilder beim Upload zusätzlich als WebP,
   Handy-Ansichten, Ladezeit, englische Texte, Gesamttest.

**Hinweis Hintergrund-Effekt:** Im Admin steht unter *Studio → Hintergrund & Atmosphäre* noch „Nur Partikel, viel,
lebhaft“. Die aufsteigenden Partikel gibt es in der Design-Vorschau nicht – für denselben ruhigen Look dort
„Aus / minimal“ wählen (Einstellung bleibt euch überlassen, die Funktion selbst ist unverändert).

## Offene Fragen

- ~~Durchlauf-Start~~ → entschieden: immer mit FRONTLINERS (aktuelles Projekt).
- ~~Discord~~ → entschieden: Link kommt später in den Admin; die Website zeigt Discord automatisch, sobald er eingetragen ist.
