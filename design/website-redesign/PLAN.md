# Website-Refresh — Befund & Probedesign

Stand: 27.11.2026 · Prototyp: `prototype.html` (v6 „Ruhig & hochwertig“, echte Inhalte, Bilder kommen von planigames.com,
nichts wird gespeichert). Dieser Ordner liegt bewusst außerhalb von `planigames/` und wird nie hochgeladen.
Ansehen: `prototype.html` im Browser öffnen (braucht Internet für Bilder & Schrift) – unten rechts
(am Handy oben) öffnet „Probedesign v6“ alle Ansichten.
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
v5 „Durchdacht“ (Aufbau nach Konzept) → „nicht gerade besser“: zu wenig verändert, **zu große Schrift auf der Startseite**,
**die neue Schrift gefällt nicht – lieber z. B. Montserrat**, die **Fortschrittsanzeige für FRONTLINERS** überzeugt nicht ·
**v6 „Ruhig & hochwertig“: wirklich neues Design, eine bekannte Schrift, moderate Größen, keine Spielereien.**

## Design v6 „Ruhig & hochwertig“

**Grundsätze:** schön, aufgeräumt, nichts wirkt komisch – lieber klassisch-hochwertig als experimentell.
Dunkel + Studio-Orange bleiben; neu sind Schrift, Größen, Abstände und alle Bausteine.

| Thema | Entscheidung |
|---|---|
| Schrift | **Montserrat** für alles (Google Fonts, 400–800) – keine zweite Schrift, keine Spezialschrift. |
| Größen | Startseiten-Überschrift max. 56 px (Handy 30–34 px), Abschnittstitel max. 38 px, Fließtext 16–17 px. Keine Riesentitel. |
| Farben | Hintergrund #0b0b10, Abschnitte abwechselnd #101017; Orange #ff7d1a (Verlauf #ff8f33 → #ff6a00) nur für Buttons, Links, Kicker. |
| Abschnittsköpfe | immer gleich: kleiner orangefarbener Kicker · Titel · ein Satz, **linksbündig** an der Rasterkante; Link zur Unterseite rechts daneben. |
| Rhythmus | Abschnitte wechseln automatisch den Hintergrund (auch wenn ein Spiel Blöcke weglässt), gleiche Breite (1200 px), gleiche Abstände. |
| Karten | eine Spielkarte (Bild + Spiel-Logo darauf, Status, Genre, Satz, „Zum Spiel“), eine Devlog-Karte, eine Werte-Karte – überall gleich. |
| Bewegung | nur dezent: sanftes Einblenden, leichter Zoom bei Bildern, langsamer Bild-Zoom im Kopfbereich. Inhalte sind immer sichtbar. |
| Weggelassen | Maskottchen, Fortschrittsbalken/Prozentwerte, Konzept-Notizen, Partikel, riesige Schrift. |

**Startseite:** Kopfbereich mit Bild (Claim wie live, zwei Buttons) → dezentes Laufband → „Drei Welten, ein Studio“
(3 Spielkarten + Browser-Demos) → Über uns (Text, 3 Kennzahlen, Bildcollage) → Was uns antreibt (6 Werte) →
Team (3 Köpfe) → Devlog (3 Karten) → Newsletter + YouTube/TikTok → Footer.

**Spielseite (feste Reihenfolge, fehlende Blöcke entfallen):** Kopfbereich (Status, Logo, Satz, nächster Schritt, Trailer)
→ Über das Spiel + Steckbrief (+ Demo) → Einblicke (2 Videos nebeneinander oder Video + 3 Bilder als Raster)
→ Features → Roadmap als Zeitleiste (ohne Prozent; bei „Pausiert“ Hinweis + Link zum Beitrag) → Mitmachen
(Anmeldung + Ideen/Abstimmung) → FAQ → Devlog zum Spiel (+ Karte „Alle Beiträge“) → Weitere Spiele.

**Unterseiten:** Spiele (Karten + Demo-Downloads), Devlog (neuester Beitrag groß, Filter nach Spiel), Beitrag
(Lesespalte, Zwischenüberschriften, „Mehr zum Spiel“ + „Nächster Beitrag“).

**Handy & Tablet:** Werte und Team als kompakte Listen statt riesiger Kacheln, einzelne letzte Karte am Tablet
quer über die volle Breite, nichts ragt über den Rand (geprüft auf iPhone SE/13, Pixel 7, iPad Mini, 820 px, Desktop).

## Was im Admin dazukäme (bestehende Daten bleiben gültig)

- Status „Pausiert“ (+ kurzer Hinweistext); der nächste Schritt ergibt sich automatisch aus dem Status.
- Spiel-Steckbrief (frei benennbare Zeilen, z. B. Genre · Spieler · Plattform) – ohne Angaben keine Liste.
- Kurzbeschreibung + Genre-Zeile für die Spielkarte.
- Spielseite: Blöcke werden in der festen Reihenfolge angezeigt (Reihenfolge im Admin muss niemand mehr pflegen).

## Phasen (jede einzeln hochladbar, Admin funktioniert durchgehend)

1. **Fundament:** eigene CSS-Datei statt Tailwind-Laufzeit (schnellerer Seitenaufbau), Montserrat, Farben,
   Abstände & Breiten vereinheitlichen, Kopfzeile + Footer; Bilder beim Upload zusätzlich als WebP.
2. **Startseite:** neue Abschnitte und Karten wie im Probedesign, eine Anmeldung statt drei.
3. **Spielseite:** feste Reihenfolge, Steckbrief, Medien-Raster, Roadmap-Zeitleiste, Mitmachen an einem Ort, „Pausiert“-Logik.
4. **Unterseiten:** Spiele-Übersicht, Devlog (Liste + Beitrag), Presse-Kit, Kontakt, Lore, Rechtliches, 404.
5. **Feinschliff:** Handy-Ansichten, Ladezeit, englische Texte, Gesamttest.

## Offene Fragen

- Passt die Richtung von v6 (Montserrat, ruhig, moderate Größen) – oder soll an einzelnen Stellen mehr Wucht rein?
- Gibt es einen Discord-Link (sonst bleiben YouTube & TikTok im Newsletter-Block)?
