import type { BPGraph } from "@/engine";

/**
 * Demo-Szenen für Sandbox/Tests. Spätere Missionen (Phase 3 / Content-Seed)
 * liefern eigene `initialGraph` + Grader-Spec.
 */

/** Die Kern-Mission „Öffne die Tür nur mit Schlüssel" – vollständig verdrahtet. */
export const doorScenario: BPGraph = {
  nodes: [
    { id: "ev", type: "event.beginPlay", position: { x: 0, y: 40 } },
    { id: "get", type: "var.get", position: { x: 0, y: 200 }, data: { varName: "HasKey" } },
    { id: "br", type: "flow.branch", position: { x: 260, y: 60 } },
    { id: "door", type: "action.openDoor", position: { x: 540, y: 40 } },
  ],
  edges: [
    { id: "e1", source: "ev", sourceHandle: "then", target: "br", targetHandle: "in" },
    { id: "e2", source: "get", sourceHandle: "value", target: "br", targetHandle: "condition" },
    { id: "e3", source: "br", sourceHandle: "true", target: "door", targetHandle: "in" },
  ],
  variables: [{ name: "HasKey", type: "bool", default: true }],
};

/** Leere Sandbox zum freien Experimentieren. */
export const emptyScenario: BPGraph = {
  nodes: [{ id: "ev", type: "event.beginPlay", position: { x: 40, y: 120 } }],
  edges: [],
  variables: [],
};
