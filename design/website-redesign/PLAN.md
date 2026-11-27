# Website-Refresh — Befund & Probedesign

Stand: 27.11.2026 · Prototyp: `prototype.html` (v3, echte Inhalte, Bilder kommen von planigames.com,
nichts wird gespeichert). Dieser Ordner liegt bewusst außerhalb von `planigames/` und wird nie hochgeladen.
Ansehen: `prototype.html` im Browser öffnen (braucht Internet für Bilder & Schrift) – unten rechts
(am Handy oben) öffnet „Probedesign v3“ alle Ansichten.
Interaktive Vorschau: als private Seite in Claude veröffentlicht (Bilder verkleinert, Clash Display eingebettet);
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

## Konzept v3 „Ruhe, Wucht & Charme“

Rückmeldungen: v1 „wirkt etwas viel und unüberlegt“ → v2 „Ruhe & Wucht“ (klar und cool, aber zu glatt) →
„Es muss aber bitte den Charm des Game Studios behalten“ → **v3: die ruhige Struktur aus v2 plus eine
bewusst gesetzte Charme-Schicht.** v1 und v2 liegen im Git-Verlauf bzw. als `prototype-v1.html`.

**Struktur (aus v2):**
1. **Ein Raster.** 12 Spalten; Beschriftungen stehen links, Inhalte beginnen immer in Spalte 4. Feine Linien statt Kästen.
2. **Bild – Ruhe – Bild.** Große Bildflächen wechseln sich mit ruhigen Textabschnitten ab.
3. **Orange für die wichtigste Aktion** (Playtest, Eintragen).
4. **Weniger Abschnitte:** Startseite 5 statt 11 (Spiele-Index · Studio & Team · FRONTLINERS im Fokus · Devlog ·
   „Bleib dran“); Spielseite: Kopfbild mit Steckbrief · Über das Spiel · Einblicke · Was dich erwartet · Roadmap
   (Erledigt / In Arbeit / Als Nächstes) bzw. „pausiert“-Hinweis · Mitmachen · FAQ · Devlog · Nächstes Spiel.

**Charme (v3) – jeweils an einer bestimmten Stelle statt überall:**
- **Eure Schrift:** Clash Display für Überschriften (wie auf der Live-Seite), Archivo für Text und Beschriftungen.
- **Freundliche Formen:** runde Ecken, Pillen-Buttons, abgerundete Bild-Bühnen.
- **Eure Gesichter:** Team in Farbe mit Sprüchen und Orten – und der Zauberer (euer Maskottchen) als Vierter im Team.
- **Eure Stimme:** Laufband „Original Worlds ✦ Handcrafted ✦ Indie & Proud ✦ Unreal Engine <3“, Werte mit
  Augenzwinkern, ein Spruch im Steckbrief jedes Spiels („Wege zu scheitern: ∞“, „Untote: Jap, auch die“),
  „Sag Hallo!“, „Mit 🧡 und zu viel Koffein gemacht“.
- **Zwei Sticker:** „Indie & Proud“ und „Playtest offen!“.
- **Spielereien wie in einem Spiel:** Glut im Startbild (in der Farbe des aktiven Spiels), „Wobbly Wizards“ wackelt
  beim Drüberfahren, der Zauberer schaut mit Wackelaugen über den Footer und redet beim Anstupsen, Erfolge 🏆 mit
  Konfetti, Konami-Code (↑↑↓↓←→←→BA, am Handy 5× aufs Logo) → Zauberer-Modus.

Maskottchen, Erfolge, Konami-Code, Glut-Partikel und Laufband gibt es auf der Live-Seite schon – v3 setzt sie
gezielt ein, statt sie über den Inhalt schweben zu lassen.

## Was im Admin dazukäme (bestehende Daten bleiben gültig)

- Status „Pausiert“ mit kurzem Hinweistext.
- Spiel-Steckbrief (optional): Spieler, Plattform, Genre – ohne Angaben wird die Leiste ausgeblendet.
- „Im Fokus“ auf der Startseite: Spiel + Bild wählbar.
- Kopfzeilen-Link „Playtest“: Text + Ziel wählbar.
- Steckbrief-Felder frei benennbar (für den Spruch je Spiel) · Sticker-Texte · Maskottchen als Team-Karte an/aus.
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

- Passt die Mischung aus Ruhe und Charme – lieber mehr oder weniger Spielerei?
- Darf der Zauberer als „Vierter im Team“ auftauchen – und hat er einen Namen?
- Kopfzeile: „● Playtest“-Link fest oder im Admin wählbar?
- Gibt es einen Discord-Link?
