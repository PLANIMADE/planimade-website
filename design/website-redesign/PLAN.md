# Website-Refresh — Befund & Probedesign

Stand: 27.11.2026 · Prototyp: `prototype.html` (v5 „Durchdacht“, echte Inhalte, Bilder kommen von planigames.com,
nichts wird gespeichert). Dieser Ordner liegt bewusst außerhalb von `planigames/` und wird nie hochgeladen.
Ansehen: `prototype.html` im Browser öffnen (braucht Internet für Bilder & Schrift) – unten rechts
(am Handy oben) öffnet „Probedesign v5“ alle Ansichten, die Konzept-Übersicht und die
blauen Konzept-Notizen (an jedem Abschnitt: Besucherfrage + Begründung).
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
v3 „Charme“ (Maskottchen raus auf Wunsch) → „Ich mag das Design nicht“ · Abstimmung: **Live-Seite auffrischen**,
gestört hat „fühlt sich fremd an“ und „riesige Schrift & Leere“, bleiben sollen **Farben & Schrift** ·
v4 „Aufgefrischt“ (Live-Seite, nur aufgeräumt) → „sie soll halt auch durchdacht sein“ ·
**v5 „Durchdacht“: Optik von v4, Aufbau nach einem Konzept.**

## Konzept v5 „Durchdacht“ (ausführlich in der Vorschau unter „Konzept-Übersicht“)

**Ziele:** 1. FRONTLINERS-Playtest-Anmeldungen (später Wishlists) · 2. Vertrauen ins Studio (echtes Team,
sichtbarer Fortschritt) · 3. Community aufbauen (Newsletter, YouTube, TikTok).
**Für wen:** Spieler:innen (sehen & mitmachen) · Community (kommt für Neuigkeiten wieder) · Presse/Partner (Fakten, Kontakt).

**Startseite – 5 Fragen in dieser Reihenfolge:**
1. *Wer seid ihr – und was kann ich jetzt tun?* Studio-Claim wie live + Karte „Jetzt aktuell“ (FRONTLINERS-Playtest,
   Fortschritt) – der einzige orangefarbene Button im ersten Bildschirm.
2. *Was macht ihr?* Drei gleichwertige Spielkarten, jede mit Status und **nächstem Schritt**; Browser-Demos als eigene Zeile.
3. *Tut sich da was?* Devlog direkt nach den Spielen (Fortschritt schafft Vertrauen), jede Zeile mit Bild.
4. *Wer steckt dahinter?* Studio-Text, 6 Werte kompakt, Team – eine Geschichte in einem Abschnitt.
5. *Wie bleibe ich dran?* Eine Anmeldung + Kanäle.

**Spielseite – immer dieselbe Reihenfolge:** Was ist das? (Logo, Satz, Hauptbutton) → Worum geht's? (Text + Steckbrief)
→ Wie sieht's aus? (Videos + Bilder) → Was erwartet mich? (Features) → Wie weit seid ihr? (Roadmap + Fortschritt)
→ Wie kann ich mitmachen? (Anmeldung + Ideen + ggf. Demo) → Noch Fragen? (FAQ) → Was ist neu? (Patch Notes)
→ Was gibt's noch? (andere Spiele). Blöcke ohne Inhalt entfallen einfach.

**Regeln:** ein Hauptbutton pro Abschnitt mit konkretem Verb · der Status bestimmt den nächsten Schritt
(Angekündigt → Playtest, In Entwicklung ohne Steam-Seite → Warteliste, Pausiert → „Benachrichtigen“) ·
keine Sackgassen (Beitrag → Spiel, Spiel → andere Spiele) · Inhalte widersprechen sich nicht ·
gleiche Dinge sehen gleich aus (eine Spielkarte, Abschnittskopf „Kicker · Titel · ein Satz“, eine Breite).

**Optik (aus v4, wie live):** Schwarz + Studio-Orange/Gold, Clash Display + Archivo + JetBrains Mono, normale
Schriftgrößen, wenig Leerraum, Kopfzeile mit Schriftzug PLANI**GAMES**, „☕ Unterstützen“ fest statt schwebend.
Cookie-Hinweis, Erfolge, Kommentare und Reaktionen bleiben unverändert (im Probedesign weggelassen).

## Was im Admin dazukäme (bestehende Daten bleiben gültig)

- Status „Pausiert“ (+ kurzer Hinweistext); der nächste Schritt ergibt sich automatisch aus dem Status.
- Spiel-Steckbrief (frei benennbare Zeilen, z. B. Genre · Spieler · Plattform) – ohne Angaben keine Liste.
- „Jetzt aktuell“ auf der Startseite: Spiel wählbar (Fortschritt kommt aus dessen Roadmap).
- Spielseite: Blöcke werden in der festen Reihenfolge angezeigt (Reihenfolge im Admin muss niemand mehr pflegen).

## Phasen (jede einzeln hochladbar, Admin funktioniert durchgehend)

1. **Fundament:** eigene CSS-Datei statt Tailwind-Laufzeit (schnellerer Seitenaufbau), Abstände & Breiten
   vereinheitlichen, Kopfzeile + Footer; Bilder beim Upload zusätzlich als WebP.
2. **Startseite:** „Jetzt aktuell“, Spielkarten mit nächstem Schritt, Devlog vor Studio, eine Anmeldung.
3. **Spielseite:** feste Reihenfolge, Steckbrief, Medien zusammen, Mitmachen an einem Ort, „Pausiert“-Logik.
4. **Unterseiten:** Spiele-Übersicht, Devlog (Liste + Beitrag), Presse-Kit, Kontakt, Lore, Rechtliches, 404.
5. **Feinschliff:** Handy-Ansichten, Ladezeit, englische Texte, Gesamttest.

## Offene Fragen

- Passt der Plan (Ziele, Reihenfolge, Regeln) – oder setzt ihr andere Schwerpunkte?
- Soll „Jetzt aktuell“ immer FRONTLINERS zeigen oder im Admin wählbar sein?
- Gibt es einen Discord-Link (sonst bleiben YouTube & TikTok im Community-Block)?
