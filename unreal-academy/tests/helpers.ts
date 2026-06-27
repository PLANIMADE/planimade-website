import type { BPEdge, BPGraph, BPNode, BPVariable } from "@/engine";

/** Kleine Builder, um Test-Graphen kompakt & lesbar zu beschreiben. */

let idc = 0;
export const uid = (p = "n") => `${p}${idc++}`;

export function node(
  id: string,
  type: string,
  data?: Record<string, unknown>,
): BPNode {
  return { id, type, position: { x: 0, y: 0 }, data };
}

export function edge(
  source: string,
  sourceHandle: string,
  target: string,
  targetHandle: string,
): BPEdge {
  return {
    id: `${source}.${sourceHandle}->${target}.${targetHandle}`,
    source,
    sourceHandle,
    target,
    targetHandle,
  };
}

export function graph(
  nodes: BPNode[],
  edges: BPEdge[],
  variables: BPVariable[] = [],
): BPGraph {
  return { nodes, edges, variables };
}
