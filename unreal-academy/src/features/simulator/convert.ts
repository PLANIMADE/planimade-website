import type { Edge, Node } from "reactflow";
import { getNodeSpec } from "@/engine";
import type { BPEdge, BPGraph, BPNode } from "@/engine";

/** Daten, die jede React-Flow-Node trägt. */
export interface RFNodeData {
  specType: string;
  /** Instanzwerte (Literal, Variablenname, Operator …). */
  values: Record<string, unknown>;
}

/** Engine-Graph → React-Flow-Knoten/Kanten. */
export function toReactFlow(graph: BPGraph): {
  nodes: Node<RFNodeData>[];
  edges: Edge[];
} {
  const nodes = graph.nodes.map<Node<RFNodeData>>((n) => ({
    id: n.id,
    type: "bp",
    position: n.position,
    data: { specType: n.type, values: { ...(n.data ?? {}) } },
  }));

  const edges = graph.edges.map<Edge>((e) => ({
    id: e.id,
    source: e.source,
    sourceHandle: e.sourceHandle,
    target: e.target,
    targetHandle: e.targetHandle,
    type: "smoothstep",
  }));

  return { nodes, edges };
}

/** React-Flow-Knoten/Kanten → Engine-Graph (für Simulation & Grading). */
export function toGraph(
  nodes: Node<RFNodeData>[],
  edges: Edge[],
  variables: BPGraph["variables"],
): BPGraph {
  const bpNodes: BPNode[] = nodes.map((n) => ({
    id: n.id,
    type: n.data.specType,
    position: n.position,
    data: n.data.values,
  }));

  const bpEdges: BPEdge[] = edges.map((e) => ({
    id: e.id,
    source: e.source,
    sourceHandle: e.sourceHandle ?? "",
    target: e.target,
    targetHandle: e.targetHandle ?? "",
  }));

  return { nodes: bpNodes, edges: bpEdges, variables };
}

/** Ist ein Handle ein Exec-Pin? (für Verbindungs-Validierung & Styling) */
export function isExecHandle(
  specType: string,
  handle: string,
  side: "source" | "target",
): boolean {
  const spec = getNodeSpec(specType);
  if (!spec) return false;
  return (side === "source" ? spec.execOut : spec.execIn).includes(handle);
}
