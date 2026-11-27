# Website-Refresh — Befund & Probedesign

Stand: 27.11.2026 · Prototyp: `prototype.html` (echte Inhalte, Bilder kommen von planigames.com,
nichts wird gespeichert). Dieser Ordner liegt bewusst außerhalb von `planigames/` und wird nie hochgeladen.
Ansehen: `prototype.html` im Browser öffnen (braucht Internet für Bilder & Schriften) – unten wechselt eine
Leiste zwischen Startseite, Spiele, den drei Spielseiten und dem Devlog.
Interaktive Vorschau: als private Seite in Claude veröffentlicht (Bilder verkleinert, Schrift eingebettet);
neu bauen mit `build-vorschau.py` (Aufruf steht oben im Skript).

## Befund (aktuelle Website, v54)

**Was gut ist:** dunkler Look mit Studio-Orange, eigene Akzentfarbe je Spiel, starke Headline
„Unsere Ideen. Eure Abenteuer.“, flexibler Block-Baukasten, Deutsch/Englisch.

**Was die Seite schwächer wirken lässt:**

| Bereich | Befund |
|---|---|
| Spiele | Startseite zeigt nur kleine Karten mit Titel-Text – die Spiele-Logos kommen nicht vor. FRONTLINERS erscheint doppelt („Aktuelles Projekt“ + Raster), das Raster endet mit einem verwaisten „Browser-Demos“-Kärtchen. |
| Rhythmus | Viel leerer Schwarzraum, jeder Abschnitt gleich aufgebaut; unterschiedliche Containerbreiten (Devlog-Liste schmaler als der Rest). |
| Studio-Werte | Emoji-Icons wirken uneinheitlich. |
| Spielseiten | Zwei identische Trailer-Platzhalter hintereinander, Warteliste/Vorschläge/Roadmap ohne „Bühne“, kein Steckbrief (Genre, Spieler, Plattform), keine Navigation in der langen Seite. |
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

## Konzept „Cinematic Studio“ (siehe Prototyp)

- **Spiele als Bühne.** Startseite mit Spiele-Umschalter im Kopfbereich (Cover wechselt, Logos, Fortschrittsbalken),
  danach jedes Spiel als großes Bühnenbild mit Logo, Status, Steckbrief und Buttons; Demos als eigener Bereich.
- **Klare Dramaturgie.** Nummerierte Kapitel (01 Spiele · 02 Studio · 03 Team · 04 Devlog), eine Containerbreite,
  abwechselnde Anordnung statt gleichförmiger Abschnitte.
- **Studio.** Große Aussage, Kennzahlen (2025 gegründet · 3 Spiele · 3 Gründer), Werte mit eigenen Linien-Icons.
- **Team.** Große Porträts mit Rolle, Zitat und Ort.
- **Devlog.** Magazin-Look (Aufmacher + Liste); Beiträge ohne Bild bekommen Spielfarbe + Logo.
- **Bleib dran.** Newsletter + Community in einem Block; Footer: Spiele · Studio · Rechtliches · Social.
- **Spielseite.** Großer Kopfbereich mit Logo und Steckbrief-Leiste, Abschnitts-Navigation (bleibt oben stehen),
  Über-Teil mit Statement, Feature-Kacheln, Videos (laden erst nach Klick), Roadmap als Zeitstrahl mit Fortschritt,
  „Mitmachen“ (Warteliste + Vorschläge nebeneinander), Selbst spielen, FAQ, Devlog, weitere Spiele.
- **Kopfzeile.** Wortmarke PLANI**GAMES**, Navigation, Sprache, Button „FRONTLINERS Playtest“ (immer sichtbar).
- **Ruhiger.** Partikel, Cursor-Ring, Maskottchen und schwebender Unterstützen-Button zurückhaltend bzw. optional.
- **Handy.** Eigene Anordnung: Umschalter wischbar, Steckbrief 2×2, Zeitstrahl senkrecht, Menü als Vollbild.

## Was im Admin dazukäme (bestehende Daten bleiben gültig)

- Spiel-Steckbrief (optional): Genre, Spieler, Plattform, Release – ohne Angaben wird die Leiste ausgeblendet.
- Status „Pausiert“.
- Studio-Kennzahlen optional (sonst automatisch aus Gründungsjahr, Spielen und Team).
- Werte-Icons aus einem Icon-Set (Emoji bleibt möglich).
- Video-Block: mehrere Videos mit Vorschaubild und Titel.
- Kopfzeilen-Button: Text + Ziel wählbar (z. B. „FRONTLINERS Playtest“).

Alles andere nutzt die vorhandenen Blöcke und Felder.

## Phasen (jede einzeln hochladbar, Admin funktioniert durchgehend)

1. **Fundament:** Design-Tokens, eigene CSS-Datei statt Tailwind-Laufzeit (schnellerer Seitenaufbau),
   Kopfzeile, Footer, Buttons, Karten; Bilder beim Upload zusätzlich als WebP.
2. **Startseite:** Spiele-Umschalter, Spiele-Bühnen, Studio, Team, Devlog-Magazin, „Bleib dran“.
3. **Spielseite:** Kopfbereich + Steckbrief, Abschnitts-Navigation, alle Blöcke im neuen Stil.
4. **Unterseiten:** Spiele-Übersicht, Devlog (Liste + Beitrag), Presse-Kit, Kontakt, Lore, Rechtliches, 404.
5. **Feinschliff:** Animationen, Barrierefreiheit, Ladezeit, englische Texte, Gesamttest.

## Offene Fragen

- Passt die Richtung „Spiele als Bühne“ – oder lieber stärker Studio-zentriert?
- Kopfzeilen-Button fest „FRONTLINERS Playtest“ oder im Admin wählbar?
- Effekte (Partikel, Cursor, Maskottchen): behalten, reduzieren oder weg?
- Gibt es einen Discord-Link?
