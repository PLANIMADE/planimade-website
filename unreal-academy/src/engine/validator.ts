import { getNodeSpec } from "./registry";
import type { BPGraph, PinType, SimError } from "./types";

/**
 * Leichter Struktur-Validator (für UI-Feedback beim Verbinden).
 * Prüft, dass Kanten kompatible Pins verbinden – wie die echte Engine:
 *  - Exec darf nur mit Exec verbunden werden.
 *  - Data-Typen müssen kompatibel sein ("object" ist generisch/akzeptiert alles).
 */
function pinType(
  nodeType: string,
  handle: string,
  side: "source" | "target",
): PinType | undefined {
  const spec = getNodeSpec(nodeType);
  if (!spec) return undefined;
  const isExec =
    (side === "source" ? spec.execOut : spec.execIn).includes(handle);
  if (isExec) return "exec";
  const pins = side === "source" ? spec.dataOut : spec.dataIn;
  return pins.find((p) => p.id === handle)?.type;
}

function compatible(a: PinType, b: PinType): boolean {
  if (a === "exec" || b === "exec") return a === b;
  if (a === "object" || b === "object") return true; // generisch
  if ((a === "int" || a === "float") && (b === "int" || b === "float"))
    return true; // numerisch kompatibel
  return a === b;
}

export function validateGraph(graph: BPGraph): SimError[] {
  const errors: SimError[] = [];
  const byId = new Map(graph.nodes.map((n) => [n.id, n]));

  for (const edge of graph.edges) {
    const src = byId.get(edge.source);
    const tgt = byId.get(edge.target);
    if (!src || !tgt) continue;

    const a = pinType(src.type, edge.sourceHandle, "source");
    const b = pinType(tgt.type, edge.targetHandle, "target");
    if (!a || !b) continue;

    if ((a === "exec") !== (b === "exec")) {
      errors.push({
        code: "exec_to_data",
        message: "Exec-Pin kann nicht mit Daten-Pin verbunden werden.",
        edgeId: edge.id,
      });
    } else if (!compatible(a, b)) {
      errors.push({
        code: "type_mismatch",
        message: `Typen passen nicht: ${a} ↔ ${b}.`,
        edgeId: edge.id,
      });
    }
  }
  return errors;
}
