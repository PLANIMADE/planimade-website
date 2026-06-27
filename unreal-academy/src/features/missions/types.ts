import type { BPGraph } from "@/engine";
import type { GraderSpec } from "@/engine";

/**
 * Inhaltsmodell für Missionen & Steps.
 *
 * Eine Mission ist eine kurze Step-Sequenz. Jeder Step ist eigenständig
 * bewertbar; erst wenn er gelöst ist, geht es weiter. Der Mission Player
 * (siehe MissionPlayer.tsx) rendert die Steps und vergibt XP.
 *
 * Content lebt bewusst als versionierte TS-Daten im Repo (src/data) — so ist
 * die App auch offline / als Gast spielbar (siehe Phase-2-MVP, Abschnitt 7).
 */

export type StepKind = "concept" | "build" | "debug" | "quiz";

/** Lese-/Erklär-Step: Text, kein Bauen. Gilt nach „Verstanden" als gelöst. */
export interface ConceptStep {
  kind: "concept";
  title: string;
  /** Absätze (einfacher Text). */
  body: string[];
  /** Optionale kleine Illustration im Step-Kopf. */
  visual?: "pins" | "flow";
}

/**
 * Bau-/Debug-Step: im Simulator lösen. `build` startet (fast) leer,
 * `debug` startet mit einem fehlerhaften Graphen. Identische Struktur,
 * nur die Erzählung/Startsituation unterscheidet sich.
 */
export interface BuilderStep {
  kind: "build" | "debug";
  title: string;
  /** Was zu tun ist (1–2 Sätze). */
  brief: string;
  /** Kurz formuliertes Erfolgskriterium, das im UI als Ziel angezeigt wird. */
  goal: string;
  /** Startgraph (build: Grundgerüst, debug: fehlerhaft). */
  initialGraph: BPGraph;
  /** Palette auf diese Node-Typen einschränken (optional). */
  allowedNodes?: string[];
  /** Verhaltensbasierte Bewertung (führt Testfälle aus). */
  grader: GraderSpec;
  /** Optionaler Tipp, einblendbar nach einem Fehlversuch. */
  hint?: string;
}

/** Multiple-Choice-Step. */
export interface QuizStep {
  kind: "quiz";
  title: string;
  question: string;
  options: string[];
  /** Index der korrekten Option in `options`. */
  answer: number;
  /** Erklärung, nach der Antwort eingeblendet. */
  explain?: string;
}

export type MissionStep = ConceptStep | BuilderStep | QuizStep;

/** Übergeordnetes Label einer Mission (steuert Icon/Akzent auf der Karte). */
export type MissionType = "concept" | "build" | "debug" | "quiz" | "boss";

export interface Mission {
  /** Stabile ID, z.B. "A4". */
  id: string;
  /** Pfad-Zugehörigkeit, z.B. "A". */
  trackId: string;
  title: string;
  type: MissionType;
  /** XP für den Mission-Abschluss (zzgl. Step-XP, siehe Player). */
  xp: number;
  steps: MissionStep[];
}

export interface Track {
  id: string;
  title: string;
  subtitle: string;
  missions: Mission[];
}

/** XP pro abgeschlossenem Step nach Typ (Step-XP, on top der Mission-XP). */
export const STEP_XP: Record<StepKind, number> = {
  concept: 5,
  quiz: 10,
  build: 15,
  debug: 15,
};
