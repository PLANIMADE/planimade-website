import "./nodes"; // stellt sicher, dass alle Node-Typen registriert sind
import { getNodeSpec, zeroValue } from "./registry";
import type {
  BPEdge,
  BPGraph,
  BPNode,
  BPValue,
  EmittedAction,
  NodeContext,
  RunOptions,
  SimError,
  SimulationResult,
  TraceEntry,
} from "./types";

const DEFAULT_MAX_STEPS = 10_000;

/**
 * Führt einen Blueprint-Graphen aus.
 *
 * Modell (wie echtes Unreal):
 *  - Der weiße **Exec-Flow** treibt die Ausführung von Event zu Event.
 *  - **Data-Pins** werden bei Bedarf rückwärts gezogen (pull-based, lazy).
 *  - Ein **Trace** protokolliert die Reihenfolge für Debugging & Highlighting.
 */
export function runGraph(
  graph: BPGraph,
  options: RunOptions = {},
): SimulationResult {
  const maxSteps = options.maxSteps ?? DEFAULT_MAX_STEPS;

  const nodeById = new Map<string, BPNode>(graph.nodes.map((n) => [n.id, n]));
  const nodeState = new Map<string, unknown>();

  // Variablen initialisieren: Defaults + Overrides.
  const variables: Record<string, BPValue> = {};
  for (const v of graph.variables) variables[v.name] = v.default;
  Object.assign(variables, options.variables ?? {});

  const actions: EmittedAction[] = [];
  const trace: TraceEntry[] = [];
  const firedEdges: string[] = [];
  const errors: SimError[] = [];
  let steps = 0;

  /** Zieht den Wert eines Data-Ausgangs-Pins (rekursiv, mit Zyklusschutz). */
  function evalDataOut(
    nodeId: string,
    pinId: string,
    stack: Set<string>,
  ): BPValue {
    const key = `${nodeId}:${pinId}`;
    if (stack.has(key)) {
      errors.push({
        code: "data_cycle",
        message: `Daten-Zyklus an ${key}`,
        nodeId,
      });
      return null;
    }
    const node = nodeById.get(nodeId);
    const spec = node && getNodeSpec(node.type);
    if (!node || !spec || !spec.pure) return null;

    stack.add(key);
    const ctx = makeContext(node, null, stack);
    const outputs = spec.pure(ctx);
    stack.delete(key);
    return outputs[pinId] ?? null;
  }

  /** Liest einen Data-Eingangs-Pin: folgt der Kante oder nutzt den Default. */
  function readInput(node: BPNode, pinId: string, stack: Set<string>): BPValue {
    const edge = graph.edges.find(
      (e) => e.target === node.id && e.targetHandle === pinId,
    );
    if (edge) return evalDataOut(edge.source, edge.sourceHandle, stack);

    const spec = getNodeSpec(node.type);
    const pin = spec?.dataIn.find((p) => p.id === pinId);
    if (pin?.default !== undefined) return pin.default;
    return zeroValue(pin?.type ?? "object");
  }

  function makeContext(
    node: BPNode,
    execIn: string | null,
    stack: Set<string>,
  ): NodeContext {
    return {
      node,
      execIn,
      input: (pinId) => readInput(node, pinId, stack),
      getVar: (name) => variables[name] ?? null,
      setVar: (name, value) => {
        variables[name] = value;
      },
      getState: <T,>() => nodeState.get(node.id) as T | undefined,
      setState: <T,>(s: T) => {
        nodeState.set(node.id, s);
      },
      emit: (action) => actions.push(action),
    };
  }

  /** Folgt dem Exec-Flow ab einem Node, der über `enteredPin` betreten wurde. */
  function runExec(nodeId: string, enteredPin: string | null): void {
    if (steps++ > maxSteps) {
      if (!errors.some((e) => e.code === "infinite_loop")) {
        errors.push({
          code: "infinite_loop",
          message: "Mögliche Endlosschleife – Ausführung abgebrochen.",
          nodeId,
        });
      }
      return;
    }

    const node = nodeById.get(nodeId);
    if (!node) return;
    const spec = getNodeSpec(node.type);
    if (!spec) {
      errors.push({
        code: "unknown_node",
        message: `Unbekannter Node-Typ "${node.type}".`,
        nodeId,
      });
      return;
    }

    const ctx = makeContext(node, enteredPin, new Set());
    const result = spec.exec ? spec.exec(ctx) : null;
    const outPins = result == null ? [] : Array.isArray(result) ? result : [result];

    const entry: TraceEntry = { order: trace.length, nodeId };
    if (outPins.length === 1) entry.firedExecOut = outPins[0];
    trace.push(entry);

    for (const outPin of outPins) {
      const outgoing = graph.edges.filter(
        (e) => e.source === nodeId && e.sourceHandle === outPin,
      );
      for (const edge of outgoing) {
        firedEdges.push(edge.id);
        runExec(edge.target, edge.targetHandle);
      }
    }
  }

  // Trigger: passende Event-Nodes finden und auslösen.
  const eventNodes = graph.nodes.filter((n) => {
    const spec = getNodeSpec(n.type);
    if (spec?.kind !== "event") return false;
    if (!options.trigger) return true;
    return n.type === options.trigger || n.id === options.trigger;
  });
  for (const ev of eventNodes) runExec(ev.id, null);

  return { actions, variables, trace, firedEdges, errors };
}

/** Bequemer Helfer: nur die ausgelösten Aktions-Kinds (für Tests/Grader). */
export function actionKinds(result: SimulationResult): string[] {
  return result.actions.map((a) => a.kind);
}

export type { BPEdge };
