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

// ═══════════════════════════════════════════════════════════════════════════
// Pfad B — „Gameplay Systems"
// ═══════════════════════════════════════════════════════════════════════════

// B2: Münzen zählen — Coins += 1 (Add). Es fehlt nur die Exec-Verbindung.
const b2Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
    { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "Coins" } },
    { id: "lit", type: "literal.int", position: { x: 20, y: 340 }, data: { value: 1 } },
    { id: "add", type: "math.add", position: { x: 280, y: 240 } },
    { id: "set", type: "var.set", position: { x: 540, y: 60 }, data: { varName: "Coins" } },
  ],
  edges: [
    { id: "d1", source: "get", sourceHandle: "value", target: "add", targetHandle: "a" },
    { id: "d2", source: "lit", sourceHandle: "value", target: "add", targetHandle: "b" },
    { id: "d3", source: "add", sourceHandle: "sum", target: "set", targetHandle: "value" },
    // Fehlt absichtlich: ev.then -> set.in
  ],
  variables: [{ name: "Coins", type: "int", default: 0 }],
};

// B4: Health <= 0? — Compare + Branch -> SetDead. Operator & true-Zweig fehlen.
const b4Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
    { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "Health" } },
    { id: "cmp", type: "math.compare", position: { x: 280, y: 200 } },
    { id: "br", type: "flow.branch", position: { x: 520, y: 60 } },
    { id: "dead", type: "action.setDead", position: { x: 780, y: 40 } },
  ],
  edges: [
    { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
    { id: "d1", source: "get", sourceHandle: "value", target: "cmp", targetHandle: "a" },
    { id: "d2", source: "cmp", sourceHandle: "result", target: "br", targetHandle: "condition" },
    // Fehlt: Operator auf "<=" stellen + br.true -> dead.in
  ],
  variables: [{ name: "Health", type: "int", default: 0 }],
};

// B5: Schaden nehmen — Health + (-Schaden). Es fehlt der Schadenswert (Literal an Add.b).
const b5Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
    { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "Health" } },
    { id: "add", type: "math.add", position: { x: 280, y: 200 } },
    { id: "set", type: "var.set", position: { x: 540, y: 60 }, data: { varName: "Health" } },
  ],
  edges: [
    { id: "e1", source: "ev", sourceHandle: "then", target: "set", targetHandle: "in" },
    { id: "d1", source: "get", sourceHandle: "value", target: "add", targetHandle: "a" },
    { id: "d2", source: "add", sourceHandle: "sum", target: "set", targetHandle: "value" },
    // Fehlt: Literal (-10) -> add.b
  ],
  variables: [{ name: "Health", type: "int", default: 100 }],
};

// B7: Türöffner mit Counter — Coins >= 3 -> Open Door.
const b7Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
    { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "Coins" } },
    { id: "cmp", type: "math.compare", position: { x: 280, y: 200 } },
    { id: "br", type: "flow.branch", position: { x: 520, y: 60 } },
    { id: "door", type: "action.openDoor", position: { x: 780, y: 40 } },
  ],
  edges: [
    { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
    { id: "d1", source: "get", sourceHandle: "value", target: "cmp", targetHandle: "a" },
    { id: "d2", source: "cmp", sourceHandle: "result", target: "br", targetHandle: "condition" },
    // Fehlt: Operator ">=", Literal 3 -> cmp.b, br.true -> door.in
  ],
  variables: [{ name: "Coins", type: "int", default: 0 }],
};

// B8 (Boss): Mini-Combat — von Grund auf. BeginPlay + Get Health liegen bereit.
const b8Initial: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 20, y: 60 } },
    { id: "get", type: "var.get", position: { x: 20, y: 240 }, data: { varName: "Health" } },
  ],
  edges: [],
  variables: [{ name: "Health", type: "int", default: 100 }],
};

const missionsB: Mission[] = [
  {
    id: "B1",
    trackId: "B",
    title: "Was ist Gameplay-Logik?",
    type: "concept",
    xp: 10,
    steps: [
      {
        kind: "concept",
        title: "Systeme aus Nodes denken",
        visual: "flow",
        body: [
          "Gameplay ist nichts anderes als viele kleine Regeln: Wenn X, dann Y. Ein Münzzähler, eine Lebensanzeige, ein Türöffner — alles entsteht aus denselben Bausteinen.",
          "In diesem Pfad baust du echte Mini-Systeme: Werte erhöhen (Add), Werte vergleichen (Compare) und daraus Entscheidungen ableiten (Branch).",
          "Denke in Datenfluss: Ein Wert wird gelesen, verrechnet und wieder gespeichert oder löst eine Aktion aus.",
        ],
      },
    ],
  },
  {
    id: "B2",
    trackId: "B",
    title: "Münzen zählen",
    type: "build",
    xp: 25,
    steps: [
      {
        kind: "build",
        title: "Coins um 1 erhöhen",
        brief:
          "Das System addiert bereits 1 auf Coins (Get → Add → Set). Es läuft nur nicht los. Verbinde BeginPlay mit dem Eingang von Set Coins.",
        goal: "Beim Start steigt Coins um 1.",
        initialGraph: b2Initial,
        allowedNodes: ["event.beginPlay", "var.get", "var.set", "math.add", "literal.int"],
        grader: {
          cases: [
            { name: "0 → 1", given: { Coins: 0 }, expect: { variables: { Coins: 1 } } },
            { name: "5 → 6", given: { Coins: 5 }, expect: { variables: { Coins: 6 } } },
          ],
        },
        hint: "Verbinde den weißen ▷-Ausgang von BeginPlay mit dem ▷-Eingang von Set Coins.",
      },
    ],
  },
  {
    id: "B3",
    trackId: "B",
    title: "Vergleiche Werte",
    type: "concept",
    xp: 10,
    steps: [
      {
        kind: "concept",
        title: "Größer, kleiner, gleich",
        visual: "pins",
        body: [
          "Der Compare-Node vergleicht zwei Zahlen und gibt ein bool zurück: wahr oder falsch.",
          "Über den Operator wählst du die Frage: == (gleich), > (größer), < (kleiner), >= und <=.",
          "Achtung auf Grenzfälle: Health < 0 ist nicht dasselbe wie Health <= 0. Genau hier entstehen die meisten Bugs.",
        ],
      },
    ],
  },
  {
    id: "B4",
    trackId: "B",
    title: "Health unter Null?",
    type: "build",
    xp: 30,
    steps: [
      {
        kind: "build",
        title: "Compare → Branch → Set Dead",
        brief:
          "Stelle den Compare-Operator auf <= (kleiner oder gleich 0) und verbinde den true-Zweig des Branch mit Set Dead.",
        goal: "Bei Health 0 oder weniger wird die Figur dead.",
        initialGraph: b4Initial,
        allowedNodes: ["event.beginPlay", "var.get", "math.compare", "flow.branch", "action.setDead"],
        grader: {
          cases: [
            { name: "0 → tot", given: { Health: 0 }, expect: { actions: ["SetDead"] } },
            { name: "5 → lebt", given: { Health: 5 }, expect: { actions: [] } },
            { name: "-3 → tot", given: { Health: -3 }, expect: { actions: ["SetDead"] } },
          ],
        },
        hint: "Klicke im Compare-Node auf den Operator und wähle <=. Dann ziehe vom true-Ausgang zu Set Dead.",
      },
    ],
  },
  {
    id: "B5",
    trackId: "B",
    title: "Schaden nehmen",
    type: "build",
    xp: 35,
    steps: [
      {
        kind: "build",
        title: "Health verringern",
        brief:
          "Health soll um 10 sinken. Der Add-Node ist verkabelt, aber der Schadenswert fehlt. Füge eine Integer-Zahl mit Wert -10 hinzu und verbinde sie mit dem zweiten Eingang (B) von Add.",
        goal: "Health sinkt beim Start um 10.",
        initialGraph: b5Initial,
        allowedNodes: ["event.beginPlay", "var.get", "var.set", "math.add", "literal.int"],
        grader: {
          cases: [
            { name: "30 → 20", given: { Health: 30 }, expect: { variables: { Health: 20 } } },
            { name: "100 → 90", given: { Health: 100 }, expect: { variables: { Health: 90 } } },
          ],
        },
        hint: "Palette → + Integer. Im Node die Zahl auf -10 stellen, dann den Value-Ausgang mit Add.B verbinden.",
      },
    ],
  },
  {
    id: "B6",
    trackId: "B",
    title: "Kaputtes Health-System",
    type: "debug",
    xp: 35,
    steps: [
      {
        kind: "debug",
        title: "Finde die falsche Verbindung",
        brief:
          "Bei Health 0 sollte die Figur sterben — tut sie aber nicht. Irgendetwas wird falsch verglichen. Korrigiere die Verkabelung.",
        goal: "Bei Health <= 0 wird Set Dead ausgelöst.",
        initialGraph: {
          nodes: [
            { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
            { id: "getH", type: "var.get", position: { x: 20, y: 200 }, data: { varName: "Health" } },
            { id: "getM", type: "var.get", position: { x: 20, y: 320 }, data: { varName: "MaxHealth" } },
            { id: "cmp", type: "math.compare", position: { x: 300, y: 220 }, data: { op: "<=" } },
            { id: "br", type: "flow.branch", position: { x: 540, y: 60 } },
            { id: "dead", type: "action.setDead", position: { x: 800, y: 40 } },
          ],
          edges: [
            { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
            { id: "d2", source: "cmp", sourceHandle: "result", target: "br", targetHandle: "condition" },
            { id: "e3", source: "br", sourceHandle: "true", target: "dead", targetHandle: "in" },
            // BUG: vergleicht MaxHealth statt Health
            { id: "dbug", source: "getM", sourceHandle: "value", target: "cmp", targetHandle: "a" },
          ],
          variables: [
            { name: "Health", type: "int", default: 0 },
            { name: "MaxHealth", type: "int", default: 100 },
          ],
        },
        allowedNodes: ["event.beginPlay", "var.get", "math.compare", "flow.branch", "action.setDead"],
        grader: {
          cases: [
            { name: "Health 0 → tot", given: { Health: 0, MaxHealth: 100 }, expect: { actions: ["SetDead"] } },
            { name: "Health 50 → lebt", given: { Health: 50, MaxHealth: 100 }, expect: { actions: [] } },
          ],
        },
        hint: "Der Compare schaut auf MaxHealth (immer 100). Ziehe stattdessen Get Health auf den Eingang A des Compare.",
      },
    ],
  },
  {
    id: "B7",
    trackId: "B",
    title: "Türöffner mit Counter",
    type: "build",
    xp: 40,
    steps: [
      {
        kind: "build",
        title: "3 Münzen → öffne das Tor",
        brief:
          "Das Tor soll ab 3 Münzen aufgehen. Stelle den Operator auf >=, füge eine Integer-Zahl 3 als Vergleichswert (B) hinzu und verbinde den true-Zweig mit Open Door.",
        goal: "Ab Coins >= 3 öffnet sich das Tor.",
        initialGraph: b7Initial,
        allowedNodes: [
          "event.beginPlay",
          "var.get",
          "math.compare",
          "flow.branch",
          "action.openDoor",
          "literal.int",
        ],
        grader: {
          cases: [
            { name: "3 → offen", given: { Coins: 3 }, expect: { actions: ["OpenDoor"] } },
            { name: "5 → offen", given: { Coins: 5 }, expect: { actions: ["OpenDoor"] } },
            { name: "2 → zu", given: { Coins: 2 }, expect: { actions: [] } },
          ],
        },
        hint: "Operator auf >= stellen. + Integer (Wert 3) → Compare.B. Dann true → Open Door.",
      },
    ],
  },
  {
    id: "B8",
    trackId: "B",
    title: "Prüfung: Mini-Combat",
    type: "boss",
    xp: 55,
    steps: [
      {
        kind: "build",
        title: "Leben oder Sterben",
        brief:
          "Baue das System: Ist Health <= 0, dann Set Dead. Sonst Print String (überlebt). BeginPlay und Get Health liegen bereit.",
        goal: "Health <= 0 → Set Dead, sonst Print String.",
        initialGraph: b8Initial,
        allowedNodes: [
          "event.beginPlay",
          "var.get",
          "math.compare",
          "flow.branch",
          "action.setDead",
          "action.print",
        ],
        grader: {
          cases: [
            { name: "0 → tot", given: { Health: 0 }, expect: { actions: ["SetDead"] } },
            { name: "-5 → tot", given: { Health: -5 }, expect: { actions: ["SetDead"] } },
            { name: "20 → überlebt", given: { Health: 20 }, expect: { actions: ["Print"] } },
          ],
        },
        hint: "Compare (Operator <=) → Branch. true → Set Dead, false → Print String.",
      },
    ],
  },
];

// ═══════════════════════════════════════════════════════════════════════════
// Pfad C — „Debugging Lab"
// ═══════════════════════════════════════════════════════════════════════════

const missionsC: Mission[] = [
  {
    id: "C1",
    trackId: "C",
    title: "Wie liest man einen Flow?",
    type: "concept",
    xp: 10,
    steps: [
      {
        kind: "concept",
        title: "Dem Ablauf folgen",
        visual: "flow",
        body: [
          "Beim Simulieren leuchtet der Weg auf, den der Ablauf tatsächlich genommen hat. So siehst du sofort, wohin die Ausführung gelaufen ist — und wohin nicht.",
          "Debugging heißt: Erwartung mit Realität vergleichen. Hier sollte er abbiegen, tut es aber nicht — warum?",
          "Die häufigsten Fehler: eine fehlende Verbindung, eine Verbindung am falschen Pin, oder ein falscher Operator. Genau die jagst du jetzt.",
        ],
      },
    ],
  },
  {
    id: "C2",
    trackId: "C",
    title: "Der fehlende Node",
    type: "debug",
    xp: 25,
    steps: [
      {
        kind: "debug",
        title: "Ergänze die Entscheidung",
        brief:
          "Die Tür geht immer auf — auch ohne Schlüssel. Es fehlt die Prüfung. Füge einen Branch ein, der HasKey prüft, und leite nur den true-Zweig zur Tür.",
        goal: "Tür öffnet nur, wenn HasKey wahr ist.",
        initialGraph: {
          nodes: [
            { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
            { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "HasKey" } },
            { id: "door", type: "action.openDoor", position: { x: 540, y: 40 } },
          ],
          edges: [
            // BUG: öffnet immer, ohne Prüfung
            { id: "ebug", source: "ev", sourceHandle: "then", target: "door", targetHandle: "in" },
          ],
          variables: [{ name: "HasKey", type: "bool", default: true }],
        },
        allowedNodes: ["event.beginPlay", "var.get", "flow.branch", "action.openDoor"],
        grader: {
          cases: [
            { name: "Mit Schlüssel → offen", given: { HasKey: true }, expect: { actions: ["OpenDoor"] } },
            { name: "Ohne Schlüssel → zu", given: { HasKey: false }, expect: { actions: [] } },
          ],
        },
        hint: "Füge + Branch hinzu. BeginPlay → Branch.in, Get HasKey → Condition, Branch.true → Open Door.",
      },
    ],
  },
  {
    id: "C3",
    trackId: "C",
    title: "Falsche Verbindung",
    type: "debug",
    xp: 25,
    steps: [
      {
        kind: "debug",
        title: "Korrigiere die Exec-Linie",
        brief:
          "Beim Start soll das Licht angehen — stattdessen öffnet sich eine Tür. Die Ablauf-Linie führt zum falschen Node. Leite sie zu Toggle Light um.",
        goal: "Beim Start wird das Licht geschaltet (keine Tür).",
        initialGraph: {
          nodes: [
            { id: "ev", type: "event.beginPlay", position: { x: 20, y: 80 } },
            { id: "door", type: "action.openDoor", position: { x: 360, y: 40 } },
            { id: "light", type: "action.toggleLight", position: { x: 360, y: 180 } },
          ],
          edges: [
            // BUG: zeigt auf die Tür statt aufs Licht
            { id: "ebug", source: "ev", sourceHandle: "then", target: "door", targetHandle: "in" },
          ],
          variables: [],
        },
        allowedNodes: ["event.beginPlay", "action.openDoor", "action.toggleLight"],
        grader: {
          cases: [{ name: "Start → Licht", expect: { actions: ["ToggleLight"] } }],
        },
        hint: "Ziehe vom ▷-Ausgang von BeginPlay neu auf Toggle Light — die alte Verbindung wird ersetzt.",
      },
    ],
  },
  {
    id: "C4",
    trackId: "C",
    title: "Vertauschte Pins",
    type: "debug",
    xp: 30,
    steps: [
      {
        kind: "debug",
        title: "true und false tauschen",
        brief:
          "Mit Schlüssel kommt die Absage, ohne öffnet die Tür — genau verkehrt. Tausche die beiden Zweige des Branch.",
        goal: "HasKey true → Open Door, false → Print String.",
        initialGraph: {
          nodes: [
            { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
            { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "HasKey" } },
            { id: "br", type: "flow.branch", position: { x: 280, y: 60 } },
            { id: "door", type: "action.openDoor", position: { x: 560, y: 20 } },
            { id: "print", type: "action.print", position: { x: 560, y: 180 } },
          ],
          edges: [
            { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
            { id: "d1", source: "get", sourceHandle: "value", target: "br", targetHandle: "condition" },
            // BUG: Zweige vertauscht
            { id: "btrue", source: "br", sourceHandle: "true", target: "print", targetHandle: "in" },
            { id: "bfalse", source: "br", sourceHandle: "false", target: "door", targetHandle: "in" },
          ],
          variables: [{ name: "HasKey", type: "bool", default: true }],
        },
        allowedNodes: ["event.beginPlay", "var.get", "flow.branch", "action.openDoor", "action.print"],
        grader: {
          cases: [
            { name: "Mit Schlüssel → Tür", given: { HasKey: true }, expect: { actions: ["OpenDoor"] } },
            { name: "Ohne Schlüssel → Absage", given: { HasKey: false }, expect: { actions: ["Print"] } },
          ],
        },
        hint: "Ziehe vom true-Ausgang neu auf Open Door und vom false-Ausgang neu auf Print String.",
      },
    ],
  },
  {
    id: "C5",
    trackId: "C",
    title: "Endlos-Falle",
    type: "debug",
    xp: 35,
    steps: [
      {
        kind: "debug",
        title: "Den Kreis auflösen",
        brief:
          "Score soll um 1 steigen, doch der Add-Node bezieht seinen eigenen Ergebnis-Wert als Eingang — ein Kreis. Leite Eingang A stattdessen von Get Score.",
        goal: "Score steigt beim Start um genau 1.",
        initialGraph: {
          nodes: [
            { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
            { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "Score" } },
            { id: "lit", type: "literal.int", position: { x: 20, y: 340 }, data: { value: 1 } },
            { id: "add", type: "math.add", position: { x: 300, y: 240 } },
            { id: "set", type: "var.set", position: { x: 560, y: 60 }, data: { varName: "Score" } },
          ],
          edges: [
            { id: "e1", source: "ev", sourceHandle: "then", target: "set", targetHandle: "in" },
            { id: "d2", source: "lit", sourceHandle: "value", target: "add", targetHandle: "b" },
            { id: "d3", source: "add", sourceHandle: "sum", target: "set", targetHandle: "value" },
            // BUG: Add.a bezieht den eigenen Ausgang -> Daten-Zyklus
            { id: "dbug", source: "add", sourceHandle: "sum", target: "add", targetHandle: "a" },
          ],
          variables: [{ name: "Score", type: "int", default: 0 }],
        },
        allowedNodes: ["event.beginPlay", "var.get", "var.set", "math.add", "literal.int"],
        grader: {
          cases: [
            { name: "0 → 1", given: { Score: 0 }, expect: { variables: { Score: 1 } } },
            { name: "5 → 6", given: { Score: 5 }, expect: { variables: { Score: 6 } } },
          ],
        },
        hint: "Ziehe Get Score neu auf den Eingang A von Add — das ersetzt die Kreis-Verbindung.",
      },
    ],
  },
  {
    id: "C6",
    trackId: "C",
    title: "Logik-Quiz",
    type: "quiz",
    xp: 15,
    steps: [
      {
        kind: "quiz",
        title: "Fehlerursache benennen",
        question:
          "Ein Branch leitet den Ablauf immer über false — egal, was die Variable sagt. Was ist die wahrscheinlichste Ursache?",
        options: [
          "Die Condition ist mit nichts verbunden",
          "Der true-Ausgang ist verbunden",
          "Das Event fehlt komplett",
          "Die Variable ist ein int",
        ],
        answer: 0,
        explain:
          "Ein unverbundener Condition-Eingang liefert den Standardwert false — der Branch geht dann immer über den false-Zweig.",
      },
    ],
  },
  {
    id: "C7",
    trackId: "C",
    title: "Der Grenzfall",
    type: "debug",
    xp: 35,
    steps: [
      {
        kind: "debug",
        title: "Falscher Operator",
        brief:
          "Ab 3 Münzen soll das Tor öffnen — bei genau 3 passiert aber nichts. Der Vergleichs-Operator ist zu streng. Korrigiere ihn.",
        goal: "Coins >= 3 öffnet das Tor (auch bei genau 3).",
        initialGraph: {
          nodes: [
            { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
            { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "Coins" } },
            { id: "lit", type: "literal.int", position: { x: 20, y: 340 }, data: { value: 3 } },
            { id: "cmp", type: "math.compare", position: { x: 300, y: 220 }, data: { op: ">" } },
            { id: "br", type: "flow.branch", position: { x: 540, y: 60 } },
            { id: "door", type: "action.openDoor", position: { x: 800, y: 40 } },
          ],
          edges: [
            { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
            { id: "d1", source: "get", sourceHandle: "value", target: "cmp", targetHandle: "a" },
            { id: "d2", source: "lit", sourceHandle: "value", target: "cmp", targetHandle: "b" },
            { id: "d3", source: "cmp", sourceHandle: "result", target: "br", targetHandle: "condition" },
            { id: "e3", source: "br", sourceHandle: "true", target: "door", targetHandle: "in" },
          ],
          variables: [{ name: "Coins", type: "int", default: 0 }],
        },
        allowedNodes: [
          "event.beginPlay",
          "var.get",
          "math.compare",
          "flow.branch",
          "action.openDoor",
          "literal.int",
        ],
        grader: {
          cases: [
            { name: "3 → offen", given: { Coins: 3 }, expect: { actions: ["OpenDoor"] } },
            { name: "4 → offen", given: { Coins: 4 }, expect: { actions: ["OpenDoor"] } },
            { name: "2 → zu", given: { Coins: 2 }, expect: { actions: [] } },
          ],
        },
        hint: "> schließt die 3 selbst aus. Stelle den Operator auf >=.",
      },
    ],
  },
  {
    id: "C8",
    trackId: "C",
    title: "Prüfung: Bug-Hunt",
    type: "boss",
    xp: 60,
    steps: [
      {
        kind: "debug",
        title: "Drei Fehler, ein Blueprint",
        brief:
          "Bei Health <= 0 soll Set Dead laufen — es sind drei Fehler eingebaut: die Condition ist nicht verbunden, der Operator stimmt nicht, und der true-Zweig führt ins Leere. Repariere alle drei.",
        goal: "Health <= 0 → Set Dead, sonst nichts.",
        initialGraph: {
          nodes: [
            { id: "ev", type: "event.beginPlay", position: { x: 20, y: 40 } },
            { id: "get", type: "var.get", position: { x: 20, y: 220 }, data: { varName: "Health" } },
            { id: "cmp", type: "math.compare", position: { x: 300, y: 200 }, data: { op: ">" } },
            { id: "br", type: "flow.branch", position: { x: 540, y: 60 } },
            { id: "dead", type: "action.setDead", position: { x: 800, y: 40 } },
          ],
          edges: [
            { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
            { id: "d1", source: "get", sourceHandle: "value", target: "cmp", targetHandle: "a" },
            // BUG 1: cmp.result -> br.condition fehlt
            // BUG 2: Operator ist ">" statt "<="
            // BUG 3: br.true -> dead.in fehlt
          ],
          variables: [{ name: "Health", type: "int", default: 0 }],
        },
        allowedNodes: ["event.beginPlay", "var.get", "math.compare", "flow.branch", "action.setDead"],
        grader: {
          cases: [
            { name: "0 → tot", given: { Health: 0 }, expect: { actions: ["SetDead"] } },
            { name: "5 → lebt", given: { Health: 5 }, expect: { actions: [] } },
            { name: "-2 → tot", given: { Health: -2 }, expect: { actions: ["SetDead"] } },
          ],
        },
        hint: "1) Compare-Ergebnis → Branch-Condition. 2) Operator auf <=. 3) true → Set Dead.",
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

export const trackB: Track = {
  id: "B",
  title: "Gameplay Systems",
  subtitle: "Zähler, Health & Trigger bauen",
  missions: missionsB,
};

export const trackC: Track = {
  id: "C",
  title: "Debugging Lab",
  subtitle: "Fehler erkennen & reparieren",
  missions: missionsC,
};

export const tracks: Track[] = [trackA, trackB, trackC];

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
