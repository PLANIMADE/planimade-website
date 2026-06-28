/**
 * Graph-Engine – Kern-Datentypen.
 * Diese Engine ist bewusst UI-frei: kein React, kein DOM. So ist sie
 * testbar, serverseitig nutzbar und später nativ portierbar.
 */

/** Werte, die durch Data-Pins fließen können. */
export type BPValue = boolean | number | string | null;

/** Datentyp eines Pins. "exec" = weißer Ablauf-Pin, Rest = typisierte Werte. */
export type PinType =
  | "exec"
  | "bool"
  | "int"
  | "float"
  | "string"
  | "object";

/** Ein typisierter Daten-Pin (Ein- oder Ausgang). */
export interface DataPin {
  id: string;
  type: Exclude<PinType, "exec">;
  label?: string;
  /** Fallback-Wert, falls der Pin nicht verbunden ist. */
  default?: BPValue;
}

/** Kategorie eines Node-Typs (steuert u.a. Ausführungsverhalten & Farbe). */
export type NodeKind = "event" | "flow" | "pure" | "action" | "variable";

/** Eine vom Runtime an Node-Verhalten übergebene Ausführungs-Schnittstelle. */
export interface NodeContext {
  /** Die konkrete Node-Instanz (inkl. `data` für Literale/Variablennamen). */
  node: BPNode;
  /** Über welchen Exec-Eingangs-Pin wurde der Node betreten (nur Exec-Nodes). */
  execIn: string | null;
  /** Liest den Wert eines Data-Eingangs-Pins (pull-based, lazy). */
  input: (dataPinId: string) => BPValue;
  /** Liest eine Graph-Variable. */
  getVar: (name: string) => BPValue;
  /** Setzt eine Graph-Variable. */
  setVar: (name: string, value: BPValue) => void;
  /** Persistenter Zustand pro Node-Instanz (z.B. Gate offen/zu). */
  getState: <T = unknown>() => T | undefined;
  setState: <T = unknown>(state: T) => void;
  /** Löst einen sichtbaren Seiteneffekt aus (OpenDoor, Print …). */
  emit: (action: EmittedAction) => void;
}

/** Rückgabe eines Exec-Nodes: nächster/nächste Exec-Out-Pin(s) oder Ende. */
export type ExecResult = string | string[] | null;

/** Deklarative Spezifikation eines Node-Typs (Registry-Eintrag). */
export interface NodeSpec {
  type: string;
  title: string;
  category: string;
  kind: NodeKind;
  /** IDs der Exec-Eingangs-Pins (leer bei Events/pure Nodes). */
  execIn: string[];
  /** IDs der Exec-Ausgangs-Pins. */
  execOut: string[];
  dataIn: DataPin[];
  dataOut: DataPin[];
  /** Pure/Variable-Nodes: berechnet Data-Ausgänge aus Data-Eingängen. */
  pure?: (ctx: NodeContext) => Record<string, BPValue>;
  /** Exec-Nodes: Seiteneffekte + Wahl des nächsten Exec-Out-Pins. */
  exec?: (ctx: NodeContext) => ExecResult;
  /**
   * Schleifen-/Steuer-Nodes (z.B. ForLoop): steuern selbst, wie oft welcher
   * Exec-Out-Pin feuert. `run(pin)` führt den an diesem Ausgang hängenden Zweig
   * sofort aus. Ist `loop` gesetzt, ersetzt es die normale exec-Auswertung.
   */
  loop?: (ctx: NodeContext, run: (execOutPin: string) => void) => void;
}

/** Eine Node-Instanz im Graphen. */
export interface BPNode {
  id: string;
  type: string;
  position: { x: number; y: number };
  /** Instanzdaten: Literalwert, Variablenname, Operator … */
  data?: Record<string, unknown>;
}

/** Eine gerichtete Verbindung zwischen zwei Pins. */
export interface BPEdge {
  id: string;
  source: string;
  sourceHandle: string;
  target: string;
  targetHandle: string;
}

/** Eine im Graphen deklarierte Variable. */
export interface BPVariable {
  name: string;
  type: Exclude<PinType, "exec">;
  default: BPValue;
}

/** Der vollständige Blueprint-Graph. */
export interface BPGraph {
  nodes: BPNode[];
  edges: BPEdge[];
  variables: BPVariable[];
}

/** Ein sichtbarer Seiteneffekt der Simulation (für Bühne + Grader). */
export interface EmittedAction {
  kind: string;
  payload?: Record<string, BPValue>;
}

/** Eintrag im Ausführungs-Trace (für Debugging-View & Highlighting). */
export interface TraceEntry {
  order: number;
  nodeId: string;
  /** Über welchen Exec-Out-Pin der Node weitergeleitet hat. */
  firedExecOut?: string;
}

/** Ein Laufzeit-/Validierungsfehler. */
export interface SimError {
  code:
    | "infinite_loop"
    | "unknown_node"
    | "data_cycle"
    | "type_mismatch"
    | "exec_to_data";
  message: string;
  nodeId?: string;
  edgeId?: string;
}

/** Ergebnis eines Simulationslaufs. */
export interface SimulationResult {
  actions: EmittedAction[];
  variables: Record<string, BPValue>;
  trace: TraceEntry[];
  /** IDs der Exec-Kanten, die gefeuert haben (für Highlighting). */
  firedEdges: string[];
  errors: SimError[];
}

/** Optionen für einen Simulationslauf. */
export interface RunOptions {
  /** Startwerte für Variablen (überschreiben die Defaults). */
  variables?: Record<string, BPValue>;
  /** Welches Event auslösen (Node-`type` oder Node-`id`). Default: alle Events. */
  trigger?: string;
  /** Sicherung gegen Endlosschleifen. */
  maxSteps?: number;
}
