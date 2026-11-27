# Website-Refresh — Befund & Probedesign

Stand: 27.11.2026 · Prototyp: `prototype.html` (v2, echte Inhalte, Bilder kommen von planigames.com,
nichts wird gespeichert). Dieser Ordner liegt bewusst außerhalb von `planigames/` und wird nie hochgeladen.
Ansehen: `prototype.html` im Browser öffnen (braucht Internet für Bilder & Schrift) – unten rechts
(am Handy oben) öffnet „Probedesign v2“ alle Ansichten.
Interaktive Vorschau: als private Seite in Claude veröffentlicht (Bilder verkleinert);
neu bauen mit `build-vorschau.py` (Aufruf steht oben im Skript). Der erste Entwurf liegt als `prototype-v1.html` daneben.

## Befund (aktuelle Website, v54)

**Was gut ist:** dunkler Look mit Studio-Orange, eigene Akzentfarbe je Spiel, starke Headline
„Unsere Ideen. Eure Abenteuer.“, flexibler Block-Baukasten, Deutsch/Englisch.

**Was die Seite schwächer wirken lässt:**

| Bereich | Befund |
|---|---|
| Spiele | Startseite zeigt nur kleine Karten mit Titel-Text – die Spiele-Logos kommen nicht vor. FRONTLINERS erscheint doppelt („Aktuelles Projekt“ + Raster), das Raster endet mit einem verwaisten „Browser-Demos“-Kärtchen. |
| Rhythmus | Viel leerer Schwarzraum, jeder Abschnitt gleich aufgebaut; unterschiedliche Containerbreiten (Devlog-Liste schmaler als der Rest). |
| Studio-Werte | Emoji-Icons wirken uneinheitlich. |
| Spielseiten | Zwei identische Trailer-Platzhalter hintereinander, Warteliste/Vorschläge/Roadmap ohne „Bühne“, kein Steckbrief (Genre, Spieler, Plattform). |
| Devlog | Beiträge ohne Bild zeigen leere Raster-Platzhalter; auf der Startseite nur eine Textliste. |
| Anmeldungen | Newsletter im Footer **und** als eigener Block, Community-Block separat. |
| Schwebendes | „Unterstützen“-Button, Abzeichen und Cursor-Ring liegen über Inhalten. |
| Technik | Tailwind wird live im Browser erzeugt (Play-CDN – laut Hersteller nicht für Produktion, langsamer Erstaufbau). Schriften von zwei Anbietern. Sehr große Bilder (Wobbly-Wizards-Cover 6,9 MB PNG, Logo 1,1 MB PNG). |

**Inhalte, die du direkt im Admin anpassen solltest:**
- Devlog-Beitrag „Wobbly Test Log“ (Text: „TEST“) ist öffentlich sichtbar.
- Wobbly Wizards ist laut Devlog pausiert, Status sagt aber „In Entwicklung“; die Roadmap-Termine
  (2025 Q3, 2026 Q1) liegen in der Vergangenheit; das Presse-Kit nennt Wobbly Wizards als „Aktuelles Spiel“.
- Der Button „Discord beitreten“ führt auf planigames.com statt zum Discord-Server.
- FRONTLINERS hat noch keinen Wishlist-/Steam-Link.

## Konzept v2 „Ruhe & Wucht“

Rückmeldung zu v1: „wirkt etwas viel und unüberlegt – muss durchdachter und cooler sein“.
v2 ist deshalb von Grund auf neu und folgt fünf festen Regeln:

1. **Eine Schrift, drei Stimmen.** Archivo (wie heute schon): schmal & riesig für Titel, normal für Text,
   breit & klein für Beschriftungen. (v1: drei Schriften von zwei Anbietern)
2. **Ein Akzent.** Orange nur für die wichtigste Aktion einer Seite (Playtest, Eintragen).
3. **Ein Raster.** 12 Spalten; Beschriftungen stehen links, Inhalte beginnen immer in Spalte 4.
   Feine Linien statt Kästen, Karten, Pillen und Abzeichen.
4. **Bild – Ruhe – Bild.** Große Bildflächen wechseln sich mit ruhigen Textabschnitten ab.
5. **Bewegung nur mit Bedeutung.** Bildwechsel im Spiele-Index, sanftes Einblenden, Kopfbild läuft beim
   Scrollen leicht langsamer mit. Kein Laufband, keine Partikel, kein Cursor-Ring.

**Startseite (5 statt 11 Abschnitte):** Spiele-Index (die drei Spiele als große Titel – das Bild wechselt
beim Drüberfahren bzw. automatisch) · Studio & Team · FRONTLINERS im Fokus (Playtest) · Devlog (3 Beiträge)
· „Bleib dran“ + Footer.

**Spielseite:** Kopfbild mit Logo, Satz, Playtest-Button und Steckbrief · Über das Spiel · Einblicke
(Videos/Galerie) · Was dich erwartet · Roadmap als „Erledigt / In Arbeit / Als Nächstes“ (ohne veraltete
Termine) bzw. Hinweis bei pausierten Spielen · Mitmachen (Warteliste; Ideen & Abstimmung aufklappbar) ·
FAQ · Devlog · „Nächstes Spiel“ als großer Bild-Schriftzug.

**Weitere Seiten:** Spiele-Übersicht („Drei Welten.“ + große Bildflächen + Prototypen & Demos),
Devlog („Aus der Werkstatt.“ mit Filter), Beitrag (Lesespalte mit Datum/Spiel/Lesezeit am Rand).

**Bewusst weggelassen:** Laufband, Kennzahlen-Zähler, Werte-Kacheln mit Icons, Abschnitts-Navigation,
doppelte Anmeldeblöcke, schwebende Buttons. Team-Fotos einheitlich schwarz-weiß, farbig beim Drüberfahren.

## Was im Admin dazukäme (bestehende Daten bleiben gültig)

- Status „Pausiert“ mit kurzem Hinweistext.
- Spiel-Steckbrief (optional): Spieler, Plattform, Genre – ohne Angaben wird die Leiste ausgeblendet.
- „Im Fokus“ auf der Startseite: Spiel + Bild wählbar.
- Kopfzeilen-Link „Playtest“: Text + Ziel wählbar.
- Studio-Kennzahlen optional (sonst automatisch aus Gründungsjahr, Spielen und Team).
- Video-Block: mehrere Videos mit Vorschaubild und Titel.

Roadmap, Warteliste, Vorschläge, FAQ und Devlog nutzen die vorhandenen Daten.

## Phasen (jede einzeln hochladbar, Admin funktioniert durchgehend)

1. **Fundament:** Design-Tokens, eigene CSS-Datei statt Tailwind-Laufzeit (schnellerer Seitenaufbau),
   eine Schrift, Kopfzeile, Footer, Buttons; Bilder beim Upload zusätzlich als WebP.
2. **Startseite:** Spiele-Index, Studio & Team, Fokus, Devlog, „Bleib dran“.
3. **Spielseite:** Kopfbereich + Steckbrief, alle Blöcke im neuen Stil, „Nächstes Spiel“.
4. **Unterseiten:** Spiele-Übersicht, Devlog (Liste + Beitrag), Presse-Kit, Kontakt, Lore, Rechtliches, 404.
5. **Feinschliff:** Animationen, Barrierefreiheit, Ladezeit, englische Texte, Gesamttest.

## Offene Fragen

- Passt die Richtung v2 „Ruhe & Wucht“?
- Kopfzeile: „● Playtest“-Link fest oder im Admin wählbar?
- Team-Fotos schwarz-weiß (farbig beim Drüberfahren) – okay?
- Gibt es einen Discord-Link?
