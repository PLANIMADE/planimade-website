# Unreal Academy — Lehrplan bis zur Selbstständigkeit

> **Ziel:** Nach Abschluss kann ein:e Lernende:r **eigenständig Blueprints in der
> echten Unreal Engine erstellen — ohne Hilfe.** Von der Idee über die Logik bis
> zur Umsetzung im echten Editor.

Dieses Dokument ist der Nordstern. Es definiert, **was „können" bedeutet**, in
welchen Stufen wir dahin kommen, und — ehrlich — **was die App leistet und wo der
echte Unreal-Editor anfängt.**

---

## Leitprinzip: Konzept → Übung → echte Engine

Eine reine Klick-App macht niemanden im echten Editor selbstständig. Deshalb hat
jede Kern-Fähigkeit **drei Schritte**:

1. **Verstehen** (concept) – kurz & visuell.
2. **Bauen im Simulator** (build/debug) – verhaltensbasiert geprüft. Hier lernst
   du das *Denken* gefahrlos und mit sofortigem Feedback.
3. **Echt nachbauen** (bridge) – eine Schritt-für-Schritt-Anleitung „Mach das
   jetzt im echten Unreal" (mit Screenshots). Du reproduzierst dieselbe Logik im
   echten Editor.

Am Ende stehen **Capstone-Projekte ohne Anleitung** – der Beweis, dass du es
selbst kannst.

---

## Definition of Done — „selbstständig" heißt konkret

Du giltst als selbstständig, wenn du **ohne Anleitung** kannst:

- [ ] Den Editor bedienen: Blueprint anlegen, Event Graph, Variablen/Funktionen,
      Compile & Save, Fehler lesen.
- [ ] Logik bauen: Events, Branch, Schleifen, Variablen, Math, Boolean.
- [ ] Wiederverwenden: Funktionen/Macros mit Parametern & Rückgabe.
- [ ] Mit Daten arbeiten: Arrays, Enums, Structs.
- [ ] Zeit steuern: Delay, Timeline, Tick (und wann was).
- [ ] Blueprints kommunizieren lassen: Casting, Interfaces, Referenzen.
- [ ] Mit der Welt interagieren: Input, Overlap/Kollision, Actor spawnen.
- [ ] Ein kleines Gameplay-System **von Grund auf selbst** bauen (Capstone).
- [ ] Einen fremden Blueprint **lesen & debuggen**.

---

## Die Stufen

### ✅ Stufe 1 — Fundamente (fertig: Pfade A–E, 40 Missionen)
Das Denken in Blueprints: Events, Variablen, Branch, Schleifen, Logik/Mathe,
Debugging, erste Mini-Systeme. **Status: live.**

### 🔜 Stufe 2 — Werkzeuge des Scriptings
Damit „mehr Inhalt" nicht „mehr vom Gleichen" ist, kommen neue Simulator-Bausteine:

| Pfad | Thema | Neue Bausteine (Simulator) |
|---|---|---|
| F | Funktionen & Wiederverwendung | Function-/Macro-Nodes (Parameter, Rückgabe) |
| G | Listen & Daten | Array (Add/Get/Length/ForEach), Enum, Struct |
| H | Zeit & Ablauf | Delay, Timeline, Tick, Switch, While |

*Engineering-Hinweis: Diese brauchen echte Engine-Erweiterungen (Sub-Graphen für
Funktionen, Array-Werttyp, ein Zeit-/Tick-Modell). Größere, aber machbare Schritte.*

### 🔜 Stufe 3 — Echte Unreal-Konzepte
Jetzt wird aus Logik „Unreal":

| Pfad | Thema |
|---|---|
| I | Actors, Components, Self & Referenzen |
| J | Kommunikation: Casting & Interfaces |
| K | Spieler-Input & Interaktion (Tasten, Overlap, Spawnen) |
| L | Komplette Mini-Systeme (Health, Pickups, Punktestand, Türen) |

*Teilweise simulierbar, teilweise als geführte Konzept-Lektion mit Editor-Bezug.*

### 🔜 Stufe 4 — Brücke zum echten Editor (entscheidend für „ohne Hilfe")
| Pfad | Thema |
|---|---|
| M | Der Unreal-Editor: Orientierung, Viewport, My Blueprint, Compile/Save |
| N | „Vom Simulator in die echte Engine" — jede Kernübung echt nachbauen |
| O | **Capstone**: eigene Systeme komplett selbst (ohne Anleitung) bauen |

Hier liegt der eigentliche Übergang zur Selbstständigkeit: angeleitetes
Nachbauen → freies Bauen → Capstone als Nachweis.

### ⭐ Stufe 5 — Weiterführend (optional)
UI/UMG, KI-Grundlagen (Behavior Trees), Animation-Blueprints, einfache
Multiplayer-Konzepte. Vertiefung über das „Selbstständig"-Ziel hinaus.

---

## Was die App leistet — und was nicht (ehrlich)

- **Leistet:** das *Denken*, *Lesen* und *Debuggen* von Blueprints; alle gängigen
  Logik-Muster; Motivation & Übung mit sofortigem Feedback; angeleitetes
  Nachbauen im echten Editor.
- **Leistet die App allein nicht:** den echten 3D-Editor *ersetzen*. Echte
  Selbstständigkeit entsteht durch die **Bridge- und Capstone-Übungen** im
  echten Unreal — die App führt konsequent dorthin.

---

## Fortschritt

| Stufe | Pfade | Status |
|---|---|---|
| 1 Fundamente | A–E | ✅ live (40 Missionen) |
| 2 Scripting-Werkzeuge | F–H | geplant |
| 3 Unreal-Konzepte | I–L | geplant |
| 4 Brücke zum Editor | M–O | geplant |
| 5 Weiterführend | … | optional |

*Dieses Dokument wird mit jedem Schritt aktualisiert.*
