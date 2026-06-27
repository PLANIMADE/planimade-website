import type { BPGraph } from "@/engine";
import type { Mission, Track } from "@/features/missions/types";

/**
 * Content-Seed — Pfad A „Blueprint Basics“.
 *
 * Erste vollständig spielbare Lernreihe (8 Missionen) für den Mission Player.
 * Deckt alle Step-Typen ab: concept · build · quiz · debug · boss.
 * Pfade B (Gameplay Systems) und C (Debugging Lab) folgen im Content-Seed-Schritt.
 *
 * Hinweis zu Graphen: Handles entsprechen den Node-Specs der Engine
 * (z.B. BeginPlay-Ausgang „then“, Branch „in/true/false/condition“).
 * Bau-/Debug-Startgraphen sind bewusst unvollständig bzw. fehlerhaft —
 * die Lösung wird verhaltensbasiert (gradeGraph) geprüft, nicht per Mustergraph.
 */

// ── A2: BeginPlay → Print String ────────────────────────────────────────────
const a2Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 40, y: 80 } },
    { id: "print", type: "action.print", position: { x: 360, y: 80 } },
  ],
  edges: [],
  variables: [],
};

// ── A4: Tür mit Schlüssel (fehlende true→Tür-Verbindung) ─────────────────────
const a4Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
    { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "HasKey" } },
    { id: "br", type: "flow.branch", position: { x: 280, y: 60 } },
    { id: "door", type: "action.openDoor", position: { x: 560, y: 40 } },
  ],
  edges: [
    { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
    { id: "e2", source: "get", sourceHandle: "value", target: "br", targetHandle: "condition" },
    // Fehlt absichtlich: br.true -> door.in
  ],
  variables: [{ name: "HasKey", type: "bool", default: true }],
};

// ── A6: Licht beim Start einschalten (leeres Grundgerüst) ────────────────────
const a6Initial: BPGraph = {
  nodes: [{ id: "ev", type: "event.beginPlay", position: { x: 60, y: 120 } }],
  edges: [],
  variables: [],
};

// ── A7: Kaputter Lichtschalter (Bedingung nicht verbunden) ───────────────────
const a7Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
    { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "IsDark" } },
    { id: "br", type: "flow.branch", position: { x: 280, y: 60 } },
    { id: "light", type: "action.toggleLight", position: { x: 560, y: 40 } },
  ],
  edges: [
    { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
    { id: "e2", source: "br", sourceHandle: "true", target: "light", targetHandle: "in" },
    // Fehlt absichtlich: get.value -> br.condition  → Branch ist „blind“
  ],
  variables: [{ name: "IsDark", type: "bool", default: true }],
};

// ── A8: Boss – Zutritts-System (von Grund auf bauen) ─────────────────────────
const a8Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 20, y: 60 } },
    { id: "get", type: "var.get", position: { x: 20, y: 240 }, data: { varName: "HasKey" } },
  ],
  edges: [],
  variables: [{ name: "HasKey", type: "bool", default: true }],
};

const missionsA: Mission[] = [
  {
    id: "A1",
    trackId: "A",
    title: "Was ist ein Blueprint?",
    type: "concept",
    xp: 10,
    steps: [
      {
        kind: "concept",
        title: "Bauen statt tippen",
        visual: "flow",
        body: [
          "Ein Blueprint ist Unreals visuelle Programmiersprache. Statt Code zu schreiben, verbindest du Bausteine — sogenannte Nodes — mit Linien.",
          "Die weiße Linie ist der Ablauf (Execution): Sie zeigt, in welcher Reihenfolge etwas passiert. Sie startet bei einem Event wie „BeginPlay“ und läuft von Node zu Node.",
          "Farbige Linien transportieren Werte (Daten) — etwa wahr/falsch, Zahlen oder Text. In den nächsten Missionen baust du genau das selbst.",
        ],
      },
    ],
  },
  {
    id: "A2",
    trackId: "A",
    title: "Dein erstes Event",
    type: "build",
    xp: 20,
    steps: [
      {
        kind: "build",
        title: "BeginPlay → Print String",
        brief:
          "Verbinde den Ablauf-Ausgang von „Event BeginPlay“ mit dem Eingang von „Print String“, sodass beim Start eine Nachricht ausgegeben wird.",
        goal: "Beim Start wird Print String ausgelöst.",
        initialGraph: a2Initial,
        allowedNodes: ["event.beginPlay", "action.print"],
        grader: {
          cases: [
            { name: "Start gibt etwas aus", expect: { actions: ["Print"] } },
          ],
        },
        hint: "Ziehe vom weißen ▷-Ausgang von BeginPlay zum weißen ▷-Eingang von Print String.",
      },
    ],
  },
  {
    id: "A3",
    trackId: "A",
    title: "Variablen einführen",
    type: "concept",
    xp: 10,
    steps: [
      {
        kind: "concept",
        title: "Werte merken",
        visual: "pins",
        body: [
          "Eine Variable ist ein Behälter für einen Wert — z.B. ein bool (wahr/falsch), ein int (ganze Zahl) oder ein float (Kommazahl).",
          "Mit „Get Variable“ liest du den Wert aus, mit „Set Variable“ schreibst du ihn neu. So merkt sich dein Blueprint Dinge: hat der Spieler den Schlüssel? Wie viele Münzen?",
          "Gleich entscheidest du mit einer bool-Variable, ob sich eine Tür öffnet.",
        ],
      },
    ],
  },
  {
    id: "A4",
    trackId: "A",
    title: "Die Tür mit dem Schlüssel",
    type: "build",
    xp: 30,
    steps: [
      {
        kind: "build",
        title: "HasKey → Branch → Open Door",
        brief:
          "Der Branch prüft schon die Variable HasKey. Verbinde seinen wahr-Ausgang (true) mit „Open Door“, damit sich die Tür nur mit Schlüssel öffnet.",
        goal: "Mit Schlüssel öffnet sich die Tür, ohne bleibt sie zu.",
        initialGraph: a4Initial,
        allowedNodes: ["event.beginPlay", "var.get", "flow.branch", "action.openDoor"],
        grader: {
          cases: [
            { name: "Mit Schlüssel öffnet die Tür", given: { HasKey: true }, expect: { actions: ["OpenDoor"] } },
            { name: "Ohne Schlüssel bleibt sie zu", given: { HasKey: false }, expect: { actions: [] } },
          ],
        },
        hint: "Der true-Ausgang des Branch muss zum Ablauf-Eingang von Open Door führen.",
      },
    ],
  },
  {
    id: "A5",
    trackId: "A",
    title: "Wahr oder Falsch?",
    type: "quiz",
    xp: 15,
    steps: [
      {
        kind: "quiz",
        title: "Branch-Logik",
        question:
          "Ein Branch bekommt eine Condition, die wahr (true) ist. Welcher Ausgang feuert?",
        options: [
          "Der true-Ausgang",
          "Der false-Ausgang",
          "Beide Ausgänge gleichzeitig",
          "Keiner der Ausgänge",
        ],
        answer: 0,
        explain:
          "Ein Branch ist eine Weiche: Bei einer wahren Bedingung läuft der Ablauf über den true-Ausgang, sonst über false — nie über beide.",
      },
    ],
  },
  {
    id: "A6",
    trackId: "A",
    title: "Lampe an",
    type: "build",
    xp: 25,
    steps: [
      {
        kind: "build",
        title: "Licht beim Start einschalten",
        brief:
          "Füge über die Palette „Toggle Light“ hinzu und verbinde es mit BeginPlay, sodass beim Start das Licht angeht.",
        goal: "Beim Start wird das Licht umgeschaltet.",
        initialGraph: a6Initial,
        allowedNodes: ["event.beginPlay", "action.toggleLight"],
        grader: {
          cases: [
            { name: "Start schaltet das Licht", expect: { actions: ["ToggleLight"] } },
          ],
        },
        hint: "Klicke in der Palette auf „+ Toggle Light“ und verbinde BeginPlay → Toggle Light.",
      },
    ],
  },
  {
    id: "A7",
    trackId: "A",
    title: "Kaputter Lichtschalter",
    type: "debug",
    xp: 30,
    steps: [
      {
        kind: "debug",
        title: "Finde den Fehler",
        brief:
          "Das Licht soll nur angehen, wenn es dunkel ist (IsDark). Es passiert aber nichts. Repariere die Verkabelung.",
        goal: "Bei IsDark = true geht das Licht an, sonst nicht.",
        initialGraph: a7Initial,
        allowedNodes: ["event.beginPlay", "var.get", "flow.branch", "action.toggleLight"],
        grader: {
          cases: [
            { name: "Dunkel → Licht an", given: { IsDark: true }, expect: { actions: ["ToggleLight"] } },
            { name: "Hell → Licht bleibt aus", given: { IsDark: false }, expect: { actions: [] } },
          ],
        },
        hint: "Die Condition des Branch ist mit nichts verbunden — er weiß gar nicht, ob es dunkel ist. Verbinde „Get IsDark“ mit der Condition.",
      },
    ],
  },
  {
    id: "A8",
    trackId: "A",
    title: "Prüfung: Zutritts-System",
    type: "boss",
    xp: 50,
    steps: [
      {
        kind: "build",
        title: "Tür oder Absage",
        brief:
          "Baue das komplette System: Mit Schlüssel (HasKey) öffnet sich die Tür. Ohne Schlüssel soll stattdessen „Print String“ eine Absage ausgeben. BeginPlay und „Get HasKey“ liegen bereit.",
        goal: "HasKey = true → Tür auf. HasKey = false → Print String.",
        initialGraph: a8Initial,
        allowedNodes: [
          "event.beginPlay",
          "var.get",
          "flow.branch",
          "action.openDoor",
          "action.print",
        ],
        grader: {
          cases: [
            { name: "Mit Schlüssel öffnet die Tür", given: { HasKey: true }, expect: { actions: ["OpenDoor"] } },
            { name: "Ohne Schlüssel folgt die Absage", given: { HasKey: false }, expect: { actions: ["Print"] } },
          ],
        },
        hint: "Branch (Condition = HasKey): true → Open Door, false → Print String.",
      },
    ],
  },
];

export const trackA: Track = {
  id: "A",
  title: "Blueprint Basics",
  subtitle: "Events, Variablen, Branch & Flow verstehen",
  missions: missionsA,
};

export const tracks: Track[] = [trackA];

/** Flache Liste aller Missionen (für Routing per ID). */
export const allMissions: Mission[] = tracks.flatMap((t) => t.missions);

export function getMission(id: string): Mission | undefined {
  return allMissions.find((m) => m.id === id);
}

/** Nachfolger-Mission im selben Pfad (für „Weiter“ im Ergebnis-Screen). */
export function getNextMission(id: string): Mission | undefined {
  const i = allMissions.findIndex((m) => m.id === id);
  return i >= 0 ? allMissions[i + 1] : undefined;
}
