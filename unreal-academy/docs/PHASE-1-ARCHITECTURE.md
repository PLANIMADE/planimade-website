# Unreal Academy — Phase 1: Architektur & Systemdesign

> **Vision:** „Duolingo für Unreal Engine.“
> Eine spielerische, interaktive Lernplattform, auf der Nutzer Unreal Engine
> durch das **Bauen** von Systemen lernen — nicht durch passives Zuschauen.

Dieses Dokument ist der vollständige Bauplan (Phase 1). Es legt fest, *was*
gebaut wird und *warum*. Code folgt ab Phase 3.

---

## 1. Tech-Stack (Entscheidung & Begründung)

| Schicht | Wahl | Begründung |
|---|---|---|
| **Frontend** | **Next.js 14 (App Router) + React 18 + TypeScript** | SSR/SSG für SEO & schnelle Landingpage, RSC für Performance, ein Stack für Web + Mobile-Web. Riesiges Ökosystem für Node-Editoren. |
| **Styling** | **Tailwind CSS + CSS Variables (Design Tokens)** | Mobile-First, schnelles Prototyping, konsistentes Theming (Sci-Fi/Unreal-Look). |
| **Animation** | **Framer Motion** | Gamified Microinteractions (XP-Pop, Streak-Flames, Node-Snaps). |
| **State** | **Zustand** (Client) + **TanStack Query** (Server-State) | Leichtgewichtig, ideal für Simulator-State; Query cached Missionen/Progress. |
| **Blueprint-Simulator** | **React Flow (xyflow)** als Render-/Interaktions-Layer + **eigene Graph-Engine** für Ausführung | React Flow liefert Drag&Drop, Pan/Zoom, Verbindungslinien, Mobile-Touch. Die Ausführungslogik bauen wir selbst (entkoppelt vom UI). |
| **Backend** | **Supabase** (Postgres + Auth + Realtime + Storage + RLS) | Relationales Datenmodell passt perfekt zu Skill-Trees/Progression. Row-Level-Security schützt Nutzerdaten ohne eigenes Backend. Open-Source, kein Vendor-Lock wie bei Firebase. |
| **Auth** | Supabase Auth (E-Mail, OAuth Google/Discord) | Discord-Login ist bei Gamedev-Zielgruppe Gold wert. |
| **Hosting** | Vercel (Frontend) + Supabase Cloud (Backend) | Null-Config-Deploys, Edge-CDN. |
| **Tests** | Vitest (Unit, Graph-Engine) + Playwright (E2E) | Die Graph-Engine ist Kern-Logik → muss strikt getestet werden. |

**Warum Next.js statt Flutter?** Der Blueprint-Simulator ist ein
Node-Graph-Editor — dafür ist das Web-Ökosystem (React Flow) drastisch reifer
als alles in Flutter. PWA + responsive deckt „mobile & desktop“ ab; eine native
App kann später via Capacitor/Expo nachgezogen werden, ohne die Logik neu zu bauen.

**Warum Supabase statt Firebase?** Progression, Skill-Trees, Missionen und
Voraussetzungen sind **relational** (Graph aus Knoten/Kanten). Postgres + SQL +
RLS modelliert das natürlich; Firestore würde uns in Denormalisierung zwingen.

---

## 2. Architektur-Überblick (Schichten)

```
┌─────────────────────────────────────────────────────────────┐
│  PRESENTATION (Next.js / React / Tailwind / Framer Motion)   │
│  Screens: Home · Learn-Map · Mission · Simulator · Profile   │
└───────────────┬─────────────────────────────┬───────────────┘
                │                             │
┌───────────────▼───────────┐   ┌─────────────▼────────────────┐
│  DOMAIN / FEATURE LAYER    │   │  BLUEPRINT SIMULATOR (Core)  │
│  · Progression Engine      │   │  · Node Registry             │
│  · Mission Runner          │   │  · Graph Model (nodes/edges) │
│  · Gamification (XP/Streak)│   │  · Execution Engine          │
│  · Skill-Tree Resolver     │   │  · Validator / Grader        │
└───────────────┬───────────┘   └─────────────┬────────────────┘
                │                             │
┌───────────────▼─────────────────────────────▼───────────────┐
│  DATA ACCESS (TanStack Query · Supabase Client · Zod schemas)│
└───────────────────────────┬─────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────┐
│  SUPABASE  ·  Postgres (+RLS)  ·  Auth  ·  Storage  ·  Realtime│
└──────────────────────────────────────────────────────────────┘
```

**Leitprinzip:** Die **Graph-Engine ist UI-frei**. React Flow rendert nur; die
Ausführung (`runGraph`) läuft auf einem reinen Datenmodell. So ist sie testbar,
portierbar (auch native) und serverseitig validierbar.

---

## 3. Datenmodelle (Postgres-Schema, konzeptionell)

### 3.1 Content (vom Team kuratiert, read-only für Nutzer)

```
tracks            -- Lernpfade (z.B. "Gameplay Programmer")
  id, slug, title, description, icon, color, order_index, tier

units             -- Abschnitte innerhalb eines Tracks (Duolingo-„Sektionen")
  id, track_id, title, order_index

missions          -- Einzelne Lerneinheit (statt "Lektion")
  id, unit_id, slug, title, brief, type, difficulty,
  xp_reward, order_index, estimated_minutes

mission_steps     -- Schritte/Aufgaben innerhalb einer Mission
  id, mission_id, kind, prompt, order_index, config(jsonb)
  -- kind: 'concept' | 'build' | 'debug' | 'quiz' | 'reorder'

blueprint_templates -- Start-/Lösungs-Graphen für Simulator-Aufgaben
  id, mission_step_id, initial_graph(jsonb), solution_spec(jsonb),
  palette(jsonb)        -- erlaubte Nodes für diese Aufgabe

skill_nodes        -- Knoten im Skill-Tree
  id, track_id, title, description, position, mission_id?

skill_edges        -- Voraussetzungen (Skill A schaltet Skill B frei)
  id, from_node, to_node
```

### 3.2 Nutzer & Fortschritt (RLS: nur eigene Zeilen)

```
profiles           -- 1:1 zu auth.users
  id, username, avatar, created_at,
  total_xp, current_streak, longest_streak, last_active_date,
  rank (abgeleitet), selected_track_id

mission_progress
  id, user_id, mission_id, status, score, attempts,
  best_graph(jsonb), completed_at
  -- status: 'locked' | 'available' | 'in_progress' | 'completed' | 'mastered'

step_progress
  id, user_id, mission_step_id, passed, attempts

xp_events          -- Append-only Audit-Log für XP (Quelle der Wahrheit)
  id, user_id, amount, source, mission_id?, created_at

streak_log
  id, user_id, date  (unique pro Tag)

badges / user_badges      -- Auszeichnungen
daily_challenges / user_daily_challenge   -- tägliche Aufgabe
sandbox_projects   -- frei gebaute Graphen
  id, user_id, name, graph(jsonb), updated_at
```

### 3.3 Kern-Enums

- **mission.type:** `concept`, `build`, `debug`, `boss` (Unit-Abschlussprüfung)
- **difficulty / tier:** `beginner`, `intermediate`, `advanced`, `expert`
- **Ränge:** Blueprint Anfänger → Logic Builder → Gameplay Designer →
  System Architect → Unreal Wizard (Schwellen über `total_xp`).

---

## 4. Blueprint-Simulator — Architektur (Kernfeature)

### 4.1 Graph-Datenmodell

```ts
type BPNode = {
  id: string;
  type: string;          // Verweis auf Node Registry, z.B. "branch"
  position: { x: number; y: number };
  data: Record<string, unknown>;   // z.B. Variablen-Wert, Literale
};

type BPEdge = {
  id: string;
  source: string; sourceHandle: string;   // "exec-out" | "out-bool" ...
  target: string; targetHandle: string;
};

type BPGraph = { nodes: BPNode[]; edges: BPEdge[]; variables: BPVariable[] };
```

### 4.2 Pin-Modell (wie echtes Unreal)

Zwei Kategorien von Verbindungen:
- **Exec-Pins** (weißer Pfeil): steuern den *Ablauf* (Event → Branch → Action).
- **Data-Pins** (typisiert/farbig): Werte (bool, int, float, string, object).

Der Validator erzwingt Typkompatibilität (bool→bool) und verhindert
Exec↔Data-Mischverbindungen — wie die echte Engine.

### 4.3 Node Registry (deklarativ, erweiterbar)

Jeder Node-Typ wird als Spezifikation registriert:

```ts
defineNode({
  type: "branch",
  title: "Branch",
  category: "Flow Control",
  execIn: ["in"],
  execOut: ["true", "false"],
  dataIn: [{ id: "condition", type: "bool" }],
  dataOut: [],
  evaluate: (ctx) => ctx.getInput("condition") ? "true" : "false",
});
```

MVP-Node-Set: `Event BeginPlay`, `Event ActionPressed`, `Branch`, `Sequence`,
`Print String`, `Set/Get Variable`, `Compare (==,>,<)`, `Gate`, `Bool Literal`,
`OpenDoor`/`PlaySound`/`ToggleLight` (Demo-Aktionen).

### 4.4 Execution Engine

Reine Funktion `runGraph(graph): SimulationResult`:
1. Findet Start-Events (Trigger).
2. Folgt **Exec-Flow** Knoten für Knoten.
3. Für jeden Knoten: zieht **Data-Pins** rückwärts (pull-based lazy eval) und
   ruft `evaluate()`.
4. Sammelt einen **Trace** (welcher Pin feuerte wann) → für die Debugging-View
   und die Highlight-Animation.
5. Liefert Endzustand (Variablen, ausgelöste Aktionen) zurück.

### 4.5 Grader / Validator

Statt exakten Graph-Vergleich: **Verhaltensbasiert**. Die `solution_spec`
definiert Testfälle:

```
{ given: { HasKey: true },  expect: { actions: ["OpenDoor"] } }
{ given: { HasKey: false }, expect: { actions: [] } }
```

Der Grader führt den Nutzer-Graphen gegen alle Fälle aus. Bestanden = alle
Fälle korrekt. **Vorteil:** mehrere richtige Lösungswege werden akzeptiert —
genau wie in echtem Gameplay-Code.

### 4.6 Debugging-Modus

Mission liefert einen kaputten Graphen + eine Fehlerklasse
(`wrong_connection`, `missing_node`, `inverted_condition`). Der Trace zeigt rot,
wo der Flow „falsch abbiegt“. Nach Lösung: erklärender Text (Warum? Wie erkennen?
Wie vermeiden?) aus `mission_step.config`.

---

## 5. Gamification-System

| Mechanik | Regel | Speicherort |
|---|---|---|
| **XP** | Pro abgeschlossenem Step/Mission; Bonus für 1. Versuch & „Mastered“. | `xp_events` (append-only), Summe → `profiles.total_xp` |
| **Streak** | +1 pro Tag mit ≥1 abgeschlossener Aufgabe; Reset bei Lücke. | `streak_log`, `profiles.current_streak` |
| **Level/Rang** | Schwellenkurve über `total_xp` → Rang abgeleitet. | berechnet (View/Funktion) |
| **Badges** | Trigger-Events (z.B. „7-Tage-Streak“, „Erste Debug-Mission“). | `user_badges` |
| **Daily Challenge** | 1 rotierende Aufgabe/Tag, XP-Bonus. | `daily_challenges` |
| **Fortschrittsbalken** | Pro Unit/Track = completed/total Missionen. | berechnet |

**Integritätsprinzip:** XP wird **serverseitig** vergeben (Supabase RPC/Edge
Function nach Grader-Erfolg), nie blind vom Client — verhindert Cheating.

---

## 6. Skill-Tree-System

- Jeder **Track** (z.B. „Gameplay Programmer“) ist ein gerichteter Graph aus
  `skill_nodes` + `skill_edges`.
- Ein Node ist **freigeschaltet**, wenn alle Vorgänger `completed` sind.
- Spezialisierungen: Gameplay Programmer · Technical Artist · UI Designer ·
  VFX Artist · Multiplayer Developer · AI Designer.
- Resolver-Funktion berechnet client- *und* serverseitig aus
  `mission_progress`, welche Nodes `locked/available/completed/mastered` sind
  (gleiche Logik wie Mission-Status → eine Source of Truth).

---

## 7. UX-Flow (High-Level)

```
Onboarding → "Wähle dein Ziel" (Skill-Track) → Platzierungs-Mini-Quiz (optional)
   → LEARN MAP (Duolingo-Pfad, vertikal, mobil scrollbar)
       → Mission öffnen
           → Step 1: Concept (kurz, visuell)
           → Step 2: Build (Simulator) / Debug / Quiz
           → ▶ Simulation → Grader → XP-Pop + Streak-Update
       → zurück zur Map (nächster Node freigeschaltet)
   → Bottom-Nav: Learn · Skills · Sandbox · Profile
```

**Mobile-First:** Bottom-Tab-Navigation, vertikale Lernkarte, Simulator mit
Pinch-Zoom & großen Touch-Targets. Palette als Bottom-Sheet auf kleinen Screens.

---

## 8. Design-System (Sci-Fi / Unreal-Look)

- **Dark-First**, tiefes Anthrazit/Blauschwarz, Neon-Akzente.
- **Node-Farbcode** an Unreal angelehnt: Exec = weiß, bool = rot, float = grün,
  int = türkis, Event = rot, Function = blau.
- **Tokens** als CSS-Variablen (`--bp-bool`, `--accent`, `--xp-gold` …).
- **Microinteractions:** Node-Snap, Pin-Glow beim Verbinden, XP-Zähler-Tick,
  Streak-Flammen-Puls — alle über Framer Motion, reduzierbar via
  `prefers-reduced-motion`.

---

## 9. Ordnerstruktur (Ziel ab Phase 3)

```
unreal-academy/
├─ docs/                      # Architektur, MVP, Design
├─ src/
│  ├─ app/                    # Next.js Routes (learn, mission, sandbox, profile)
│  ├─ components/             # UI (Button, Card, ProgressBar, XPBadge …)
│  ├─ features/
│  │  ├─ simulator/           # React-Flow-Layer + Node-UI
│  │  ├─ progression/         # XP, Streak, Rang
│  │  ├─ missions/            # Mission Runner
│  │  └─ skilltree/
│  ├─ engine/                 # UI-freie Graph-Engine (Registry, runGraph, grader)
│  │  ├─ nodes/               # Node-Definitionen
│  │  ├─ runtime.ts
│  │  └─ grader.ts
│  ├─ lib/                    # supabase client, query hooks, utils
│  ├─ data/                   # Seed-Content (Tracks, Missionen) als TS/JSON
│  └─ styles/                 # tokens.css, tailwind
├─ supabase/                  # migrations, RLS policies, seed.sql
└─ tests/                     # engine unit tests, e2e
```

---

## 10. Sicherheit & Skalierung

- **RLS**: Nutzer sehen/ändern nur eigene `*_progress`, `profiles`,
  `sandbox_projects`. Content-Tabellen sind global lesbar, nicht schreibbar.
- **Server-Grading**: XP/Completion nur via geprüfte RPC.
- **Content as Data**: Missionen/Nodes sind Daten, kein Code → neue Inhalte ohne
  Deploy, später CMS-fähig.
- **Engine entkoppelt**: erlaubt späteren Native-Client (Capacitor) ohne Rewrite.

---

## Phase-1-Zusammenfassung

✅ **Tech-Stack** festgelegt & begründet (Next.js + Supabase + React Flow + eigene Graph-Engine).
✅ **Schichtenarchitektur** definiert (UI · Domain · Engine · Data · Supabase).
✅ **Datenmodell** entworfen (Content + Progress + Gamification, RLS-fähig).
✅ **Blueprint-Simulator** spezifiziert (Pin-Modell, Node-Registry, Execution Engine, verhaltensbasierter Grader, Debugging).
✅ **Gamification** (XP/Streak/Rang/Badges/Daily) + **Skill-Trees** + **UX-Flow** + **Design-System** geplant.

➡️ **Nächster Schritt (Phase 2):** das **MVP** ausplanen — konkreter Scope,
3 Lernpfade, 20–30 Missionen, Daily Challenge, Simulator-Umfang, Mobile-UI.

**Soll ich mit Phase 2 (MVP-Planung) starten?**
