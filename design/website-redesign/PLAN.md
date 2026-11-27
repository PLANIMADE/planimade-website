# Website-Refresh — Befund & Probedesign

Stand: 27.11.2026 · Prototyp: `prototype.html` (v4 „Aufgefrischt“, echte Inhalte, Bilder kommen von planigames.com,
nichts wird gespeichert). Dieser Ordner liegt bewusst außerhalb von `planigames/` und wird nie hochgeladen.
Ansehen: `prototype.html` im Browser öffnen (braucht Internet für Bilder & Schrift) – unten rechts
(am Handy oben) öffnet „Probedesign v4“ alle Ansichten.
Interaktive Vorschau: als private Seite in Claude veröffentlicht (Bilder verkleinert, Clash Display eingebettet);
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
v3 „Charme“ (Maskottchen raus auf Wunsch) → „Ich mag das Design nicht“. Abstimmung danach:
**Richtung „Live-Seite auffrischen“** – gestört hat an v1–v3 „fühlt sich fremd an“ und „riesige Schrift & Leere“;
unbedingt bleiben sollen **Farben & Schrift**.

## Konzept v4 „Aufgefrischt“

Die Live-Seite ist die Basis – gleicher Aufbau, gleiche Reihenfolge, gleiche Bausteine, eure Texte 1:1.

**Bleibt wie live:** dunkler Look mit Studio-Orange/Gold-Verlauf, Clash Display + Archivo + JetBrains Mono,
Kopfbereich „Unsere Ideen. Eure Abenteuer.“ mit Abzeichen und Startbild, Laufband, Spielkarten mit Status-Abzeichen,
„Klein. Unabhängig. Besessen von guten Ideen.“ mit den 6 Werte-Kacheln (Emoji), „Die Köpfe dahinter“,
Devlog-Liste, Footer. Spielseiten: alle Blöcke aus dem Admin (Text, Videos, Warteliste, Roadmap, Vorschläge,
Download, Kennzahlen, Galerie, Features, Lore, FAQ, Aufruf, Patch Notes) in der gewohnten Reihenfolge.

**Aufgefrischt:**
1. Etwas kleinere Überschriften und weniger Leerraum → mehr Inhalt pro Bildschirm (Startseite ~30 % kürzer,
   Spielseiten 30–35 % kürzer).
2. Eine Inhaltsbreite für alle Abschnitte.
3. „Aktuelles Projekt“ + „Unsere Spiele“ zusammengelegt: FRONTLINERS groß oben, darunter Fall of Kings,
   Wobbly Wizards und Browser-Demos in einer Reihe (Demos mit eigenem Bild statt leerem Kärtchen).
4. Kopfzeile mit Logo **und** Schriftzug PLANI**GAMES**; „☕ Unterstützen“ steht dort statt schwebend.
5. Newsletter + Community in einem Block, kein zweites Newsletter-Feld im Footer.
6. Devlog-Liste mit Vorschaubildern; Beiträge ohne Bild bekommen das Spiel-Cover mit Logo.
7. Spielseite: Buttons direkt im Kopfbereich (Playtest/Wishlist + Trailer), Videos nebeneinander statt zweimal
   riesig, Roadmap als kompakte Kacheln, Beta-Anmeldung + Wishlist-Aufruf in einem Block.
8. Emoji-Zeilen in Devlog-Beiträgen werden zu Zwischenüberschriften (besser lesbar).
9. Partikel nur im Kopfbereich, kein eigener Mauszeiger; nichts schwebt mehr über dem Inhalt.

Funktionen wie Cookie-Hinweis, Erfolge, Kommentare und Reaktionen bleiben unverändert – sie sind im
Probedesign nur weggelassen, damit man das Aussehen beurteilen kann.

## Was im Admin dazukäme (bestehende Daten bleiben gültig)

- Spiel-Steckbrief (optional, z. B. Genre · Spieler · Plattform) – ohne Angaben keine Leiste.
- Kopfbereich der Spielseite: zweiter Button „Trailer ansehen“ (nutzt den vorhandenen Trailer-Link).

Alles andere nutzt die vorhandenen Felder und Blöcke.

## Phasen (jede einzeln hochladbar, Admin funktioniert durchgehend)

1. **Fundament:** eigene CSS-Datei statt Tailwind-Laufzeit (schnellerer Seitenaufbau), Abstände & Breiten
   vereinheitlichen, Kopfzeile + Footer; Bilder beim Upload zusätzlich als WebP.
2. **Startseite:** Spiele zusammenlegen, Werte/Team/Devlog auffrischen, Mitmach-Block.
3. **Spielseite:** Kopfbereich mit Buttons, Blöcke kompakter (Videos, Roadmap, Beta + Aufruf).
4. **Unterseiten:** Spiele-Übersicht, Devlog (Liste + Beitrag), Presse-Kit, Kontakt, Lore, Rechtliches, 404.
5. **Feinschliff:** Handy-Ansichten, Ladezeit, englische Texte, Gesamttest.

## Offene Fragen

- Passt die Auffrischung so – oder an einzelnen Stellen noch näher an die Live-Seite?
- „☕ Unterstützen“ oben in der Kopfzeile okay?
- Gibt es einen Discord-Link (sonst bleiben YouTube & TikTok im Community-Block)?
