# Unreal Academy — Phase 2: MVP-Plan

> **Ziel des MVP:** klein im Umfang, **groß im Eindruck**.
> Ein Nutzer öffnet die App, baut innerhalb von 60 Sekunden seinen ersten
> funktionierenden Blueprint, sieht eine Simulation laufen, bekommt XP — und
> *will weitermachen*.

Dieses Dokument schneidet aus der Phase-1-Vision exakt das heraus, was im MVP
gebaut wird (und was bewusst **nicht**).

---

## 1. MVP-Leitsatz & Erfolgskriterien

**Der „Aha-Moment“:** Nodes verbinden → ▶ → *„Die Tür öffnet sich, weil ich es
gebaut habe.“*

Das MVP gilt als erfolgreich, wenn:
- ✅ ein neuer Nutzer **ohne Erklärung** die erste Build-Mission löst,
- ✅ der Blueprint-Simulator auf **Handy & Desktop** flüssig läuft,
- ✅ XP, Streak und Fortschritt **sichtbar & motivierend** sind,
- ✅ mind. **3 Lernpfade** mit zusammen **20–30 Missionen** spielbar sind,
- ✅ Fortschritt einen **Reload übersteht** (Supabase, mit lokalem Fallback).

---

## 2. MVP-Scope: drin vs. draußen

### ✅ Im MVP

| Bereich | Umfang |
|---|---|
| **Blueprint-Simulator** | Drag&Drop, Verbinden (Exec + Data), ▶ Simulation, Trace-Highlight, Fehlerfeedback, Reset. ~14 Node-Typen. |
| **Missionstypen** | `concept` (kurz), `build` (Simulator), `debug` (kaputtes BP reparieren), `quiz` (Multiple-Choice / Reorder). |
| **3 Lernpfade** | Blueprint Basics · Gameplay Systems · Debugging Lab. |
| **Missionen** | **24 Missionen** (8 pro Pfad), in Units gruppiert. |
| **XP-System** | XP pro Step/Mission, Erst-Versuch-Bonus, „Mastered“. |
| **Streak** | Tages-Streak mit Flammen-Anzeige. |
| **Rang/Level** | 5 Ränge über XP-Schwellen. |
| **Daily Challenge** | 1 rotierende Aufgabe/Tag mit XP-Bonus. |
| **Fortschritt** | Lernkarte (Duolingo-Pfad), Unit-/Track-Progressbars, Mission-Status. |
| **Profil** | XP, Rang, Streak, Badges (Basis-Set). |
| **Mobile UI** | Bottom-Nav, vertikale Karte, Touch-Simulator. |
| **Auth/Persistenz** | Supabase (E-Mail + Discord/Google); **Gast-Modus** mit LocalStorage-Fallback. |

### ❌ Bewusst NICHT im MVP (später)

- Voller Sandbox-Editor mit Speichern/Teilen (nur read-only „Playground“-Stub).
- Skill-Tree-**Visualisierung** als Graph (MVP nutzt lineare Karte; Daten-Modell
  ist aber schon Tree-fähig).
- Multiplayer/Replication, Niagara, Materials, C++/GAS-Inhalte.
- Animation-/AI-/UMG-Tiefe (Intermediate+-Themen kommen nach MVP).
- Leaderboards, Freunde, Ligen.
- CMS-Oberfläche (Content bleibt im MVP als versionierte Daten im Repo).

---

## 3. Die 3 Lernpfade & 24 Missionen

> Typ-Legende: 🟦 concept · 🟩 build · 🟥 debug · 🟨 quiz · 👑 boss

### Pfad A — „Blueprint Basics“ (Beginner)
*Ziel: Oberfläche, Events, Variablen, Branch, Flow verstehen.*

| # | Mission | Typ | Lernziel | XP |
|---|---|---|---|---|
| A1 | Was ist ein Blueprint? | 🟦 concept | Nodes, Pins, Exec-Flow | 10 |
| A2 | Dein erstes Event | 🟩 build | BeginPlay → Print String | 20 |
| A3 | Variablen einführen | 🟦 concept | bool/int/float, Get/Set | 10 |
| A4 | Die Tür mit dem Schlüssel | 🟩 build | `HasKey` → Branch → OpenDoor | 30 |
| A5 | Wahr oder Falsch? | 🟨 quiz | Branch-Logik prüfen | 15 |
| A6 | Lampe an/aus | 🟩 build | Toggle-Variable + Set | 25 |
| A7 | Kaputter Lichtschalter | 🟥 debug | invertierte Bedingung fixen | 30 |
| A8 | Prüfung: Zutritts-System | 👑 boss | Branch + Variable kombinieren | 50 |

### Pfad B — „Gameplay Systems“ (Beginner→Intermediate)
*Ziel: kleine Systeme bauen (Zähler, Health, Trigger).*

| # | Mission | Typ | Lernziel | XP |
|---|---|---|---|---|
| B1 | Was ist Gameplay-Logik? | 🟦 concept | Systeme aus Nodes denken | 10 |
| B2 | Münzen zählen | 🟩 build | int + Add (Score erhöhen) | 25 |
| B3 | Vergleiche Werte | 🟦 concept | `==`, `>`, `<` | 10 |
| B4 | Health unter Null? | 🟩 build | Compare → Branch → „Dead“ | 30 |
| B5 | Schaden nehmen | 🟩 build | Health - Damage, clampen | 35 |
| B6 | Kaputtes Health-System | 🟥 debug | falsche Connection finden | 35 |
| B7 | Türöffner mit Counter | 🟩 build | „3 Münzen → öffne Tor“ | 40 |
| B8 | Prüfung: Mini-Combat | 👑 boss | Health + Compare + Aktion | 55 |

### Pfad C — „Debugging Lab“ (übergreifend)
*Ziel: Fehler erkennen & reparieren — der Debug-USP.*

| # | Mission | Typ | Lernziel | XP |
|---|---|---|---|---|
| C1 | Wie liest man einen Flow? | 🟦 concept | Trace & Highlighting lesen | 10 |
| C2 | Der fehlende Node | 🟥 debug | fehlenden Branch ergänzen | 25 |
| C3 | Falsche Verbindung | 🟥 debug | Exec-Linie korrigieren | 25 |
| C4 | Vertauschte Pins | 🟥 debug | true/false-Zweige tauschen | 30 |
| C5 | Endlos-Falle | 🟥 debug | fehlerhafte Logik entwirren | 35 |
| C6 | Logik-Quiz | 🟨 quiz | Fehlerursache benennen | 15 |
| C7 | Doppelter Trigger | 🟥 debug | Gate richtig einsetzen | 35 |
| C8 | Prüfung: Bug-Hunt | 👑 boss | 3 Fehler in einem BP fixen | 60 |

**Daily Challenge:** rotiert täglich eine der `build`/`debug`-Missionen als
Sonderaufgabe mit **+50 % XP**.

---

## 4. MVP-Node-Set (Simulator)

Genau diese ~14 Nodes reichen für alle 24 Missionen:

**Events:** `Event BeginPlay`, `Event ActionPressed`
**Flow:** `Branch`, `Sequence`, `Gate`
**Variablen:** `Get Var`, `Set Var`, `Bool Literal`, `Int Literal`
**Logik/Math:** `Compare (==,>,<)`, `Add (int)`, `NOT (bool)`
**Aktionen (Demo, sichtbar in der Bühne):** `OpenDoor`, `ToggleLight`,
`Print String`, `SetDead`

Jede Aktion hat eine kleine **visuelle Bühne** (Tür/Lampe/Konsole/Figur), damit
die Simulation *sichtbar* etwas bewirkt — der entscheidende Wow-Faktor.

---

## 5. Gamification-Werte (MVP-Tuning)

| Element | Wert |
|---|---|
| Step abgeschlossen | 5–10 XP |
| Mission abgeschlossen | 20–60 XP (s. Tabellen) |
| Erst-Versuch-Bonus | +20 % |
| „Mastered“ (boss bestanden) | +50 % |
| Daily Challenge | +50 % |
| Streak-Anzeige | Flamme + Tageszahl, Reset bei Lücke |

**Ränge (XP-Schwellen):**
`0 — Blueprint Anfänger` · `150 — Logic Builder` · `400 — Gameplay Designer` ·
`800 — System Architect` · `1500 — Unreal Wizard`.

**Badge-Set (MVP):** „Erster Blueprint“, „Bug-Jäger“ (erste Debug-Mission),
„3-Tage-Streak“, „Pfad-Meister“ (Track 100 %), „Erst-Versuch-Profi“ (5×).

---

## 6. Screens (MVP)

```
1. Onboarding      → "Wähle dein Ziel" (Pfad A/B/C) → Gast oder Login
2. Learn Map       → vertikale Karte mit Mission-Nodes (locked/available/done)
3. Mission Player  → Step-Sequenz: concept → build/debug/quiz → Ergebnis
4. Simulator       → Canvas + Palette + ▶ + Trace/Feedback (in Mission eingebettet)
5. Daily Challenge → Einstieg vom Home/Top-Banner
6. Profile         → XP, Rang, Streak, Badges, Pfad-Fortschritt
   Bottom-Nav:  Learn · Daily · Profile   (Sandbox als „Coming soon"-Stub)
```

**Mobile-First-Regeln:** alles per Daumen erreichbar, Palette als Bottom-Sheet,
Pinch-Zoom im Canvas, XP-/Streak-Feedback als Overlay-Animation.

---

## 7. Datenbedarf des MVP (auf Phase-1-Schema gemappt)

- `tracks`: 3 Zeilen (A/B/C).
- `units`: ~2 pro Track (Lernteil + Prüfung).
- `missions`: 24.
- `mission_steps`: ~50–70 (1–3 pro Mission).
- `blueprint_templates`: für jede `build`/`debug`-Mission (initial_graph,
  palette, solution_spec mit Testfällen).
- `profiles`, `mission_progress`, `xp_events`, `streak_log`, `badges` aktiv.
- Skip im MVP: `skill_nodes/edges` (Tree-Viz später), `sandbox_projects`.

Content wird als **versionierte TS/JSON-Seeds** im Repo gepflegt
(`src/data/`) und nach Supabase geseedet — so ist das MVP auch **offline/als
Gast** demobar.

---

## 8. Build-Reihenfolge (Brücke zu Phase 3)

Damit früh etwas Sichtbares läuft, bauen wir „Engine-first, vertikal“:

1. **Projekt-Setup** (Next.js, TS, Tailwind, Tokens, Ordnerstruktur).
2. **Design-System-Basis** (Button, Card, ProgressBar, XPBadge, Layout/Nav).
3. **Graph-Engine** (Node-Registry, Modell, `runGraph`, Grader) — *UI-frei, getestet*.
4. **Simulator-UI** (React Flow + Node-Komponenten + ▶ + Trace).
5. **Mission Player** (Step-Sequenz, Grader-Anbindung, Ergebnis-Screen).
6. **Progression** (XP/Streak/Rang, lokal → dann Supabase).
7. **Content-Seed** (3 Pfade, 24 Missionen) + Learn Map.
8. **Daily Challenge + Profil + Politur** (Animationen, Mobile-Feinschliff).

> Reihenfolge so gewählt, dass nach **Schritt 4** bereits die „Tür-Mission“
> spielbar demonstrierbar ist — der stärkste Eindruck so früh wie möglich.

---

## Phase-2-Zusammenfassung

✅ **MVP-Scope** klar abgegrenzt (drin/draußen).
✅ **3 Lernpfade** mit **24 konkreten Missionen** (Titel, Typ, Lernziel, XP) definiert.
✅ **Simulator-Node-Set** (14 Nodes) festgelegt — deckt alle Missionen ab.
✅ **Gamification-Werte** getunt (XP, Ränge, Badges, Daily, Streak).
✅ **Screens & Mobile-First-Regeln** + **Datenbedarf** + **Build-Reihenfolge** für Phase 3.

➡️ **Nächster Schritt (Phase 3, Schritt 1):** **Projekt-Setup** —
Next.js + TypeScript + Tailwind + Design-Tokens + Ordnerstruktur im
`unreal-academy/`-Ordner, lauffähig starten.

**Soll ich mit Phase 3, Schritt 1 (Projekt-Setup) beginnen?**
