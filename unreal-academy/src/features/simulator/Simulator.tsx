"use client";

import { useCallback, useMemo, useState } from "react";
import ReactFlow, {
  addEdge,
  Background,
  BackgroundVariant,
  Controls,
  ReactFlowProvider,
  useEdgesState,
  useNodesState,
  type Connection,
  type Edge,
  type Node,
} from "reactflow";
import "reactflow/dist/style.css";
import {
  runGraph,
  type BPGraph,
  type EmittedAction,
  type NodeSpec,
  type SimulationResult,
} from "@/engine";
import { BlueprintNode } from "./BlueprintNode";
import { Palette } from "./Palette";
import { Stage } from "./Stage";
import { isExecHandle, toGraph, toReactFlow, type RFNodeData } from "./convert";

const nodeTypes = { bp: BlueprintNode };

export interface SimulatorProps {
  initialGraph: BPGraph;
  /** Erlaubte Nodes (Palette-Filter pro Mission). */
  allowedNodes?: string[];
  /** Callback nach jedem Lauf (z.B. für Mission-Grading). */
  onRun?: (graph: BPGraph, result: SimulationResult) => void;
}

let nodeSeq = 1000;

function styleEdge(edge: Edge, isExec: boolean, fired = false): Edge {
  return {
    ...edge,
    type: "smoothstep",
    animated: fired,
    style: {
      stroke: fired
        ? "rgb(var(--accent))"
        : isExec
          ? "rgb(var(--pin-exec))"
          : "rgb(var(--muted))",
      strokeWidth: isExec ? 2.5 : 1.5,
    },
  };
}

function SimulatorInner({ initialGraph, allowedNodes, onRun }: SimulatorProps) {
  const initial = useMemo(() => toReactFlow(initialGraph), [initialGraph]);
  const [nodes, setNodes, onNodesChange] = useNodesState<RFNodeData>(
    initial.nodes,
  );
  const [edges, setEdges, onEdgesChange] = useEdgesState(
    initial.edges.map((e) => {
      const src = initial.nodes.find((n) => n.id === e.source);
      const exec = src
        ? isExecHandle(src.data.specType, e.sourceHandle ?? "", "source")
        : false;
      return styleEdge(e, exec);
    }),
  );

  const [actions, setActions] = useState<EmittedAction[]>([]);
  const [hasRun, setHasRun] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [paletteOpen, setPaletteOpen] = useState(false);

  /** Verbindung validieren: Exec nur mit Exec, Daten nur mit Daten. */
  const isValidConnection = useCallback(
    (c: Connection) => {
      if (!c.source || !c.target || c.source === c.target) return false;
      const src = nodes.find((n) => n.id === c.source);
      const tgt = nodes.find((n) => n.id === c.target);
      if (!src || !tgt) return false;
      const sExec = isExecHandle(src.data.specType, c.sourceHandle ?? "", "source");
      const tExec = isExecHandle(tgt.data.specType, c.targetHandle ?? "", "target");
      return sExec === tExec;
    },
    [nodes],
  );

  const onConnect = useCallback(
    (c: Connection) => {
      const src = nodes.find((n) => n.id === c.source);
      const exec = src
        ? isExecHandle(src.data.specType, c.sourceHandle ?? "", "source")
        : false;
      setEdges((eds) => {
        // Ein Daten-Eingang / Exec-Ausgang hat genau eine Verbindung.
        const cleaned = eds.filter((e) => {
          if (exec) return !(e.source === c.source && e.sourceHandle === c.sourceHandle);
          return !(e.target === c.target && e.targetHandle === c.targetHandle);
        });
        const id = `${c.source}.${c.sourceHandle}->${c.target}.${c.targetHandle}`;
        return addEdge(styleEdge({ ...c, id } as Edge, exec), cleaned);
      });
    },
    [nodes, setEdges],
  );

  const addNode = useCallback(
    (spec: NodeSpec) => {
      const id = `n${nodeSeq++}`;
      const node: Node<RFNodeData> = {
        id,
        type: "bp",
        position: { x: 120 + Math.random() * 80, y: 80 + Math.random() * 120 },
        data: { specType: spec.type, values: {} },
      };
      setNodes((nds) => nds.concat(node));
    },
    [setNodes],
  );

  const run = useCallback(() => {
    const graph = toGraph(nodes, edges, initialGraph.variables);
    const result = runGraph(graph);
    setActions(result.actions);
    setHasRun(true);
    setError(result.errors[0]?.message ?? null);

    // Gefeuerte Exec-Kanten hervorheben.
    const fired = new Set(result.firedEdges);
    setEdges((eds) =>
      eds.map((e) => {
        const src = nodes.find((n) => n.id === e.source);
        const exec = src
          ? isExecHandle(src.data.specType, e.sourceHandle ?? "", "source")
          : false;
        return styleEdge(e, exec, fired.has(e.id));
      }),
    );

    onRun?.(graph, result);
  }, [nodes, edges, initialGraph.variables, setEdges, onRun]);

  const reset = useCallback(() => {
    setNodes(initial.nodes);
    setEdges(
      initial.edges.map((e) => {
        const src = initial.nodes.find((n) => n.id === e.source);
        const exec = src
          ? isExecHandle(src.data.specType, e.sourceHandle ?? "", "source")
          : false;
        return styleEdge(e, exec);
      }),
    );
    setActions([]);
    setHasRun(false);
    setError(null);
  }, [initial, setNodes, setEdges]);

  return (
    <div className="grid h-full grid-rows-[1fr] gap-3 lg:grid-cols-[180px_1fr_300px]">
      {/* Palette */}
      <div className="hidden rounded-xl border border-border bg-surface/40 p-3 lg:block">
        <Palette onAdd={addNode} allowed={allowedNodes} />
      </div>

      {/* Canvas */}
      <div className="relative min-h-[420px] overflow-hidden rounded-xl border border-border">
        <ReactFlow
          nodes={nodes}
          edges={edges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          isValidConnection={isValidConnection}
          nodeTypes={nodeTypes}
          fitView
          minZoom={0.3}
          maxZoom={2}
          zoomOnPinch
          zoomOnDoubleClick={false}
          panOnScroll={false}
          proOptions={{ hideAttribution: true }}
        >
          <Background variant={BackgroundVariant.Dots} gap={24} color="rgb(var(--border))" />
          <Controls className="!border-border !bg-surface" />
        </ReactFlow>

        {/* Run-Leiste */}
        <div className="pointer-events-none absolute inset-x-0 top-0 flex items-center justify-between p-3">
          <div className="pointer-events-auto flex gap-2">
            <button
              onClick={run}
              className="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-bg shadow-glow transition hover:brightness-110"
            >
              ▶ Simulieren
            </button>
            <button
              onClick={reset}
              className="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-muted transition hover:text-text"
            >
              ↺ Reset
            </button>
          </div>
          {error && (
            <div className="pointer-events-auto rounded-lg border border-danger/50 bg-danger/15 px-3 py-1.5 text-xs text-danger">
              {error}
            </div>
          )}
        </div>

        {/* Mobile: Palette als aufklappbares Bottom-Sheet */}
        <button
          onClick={() => setPaletteOpen(true)}
          className="absolute bottom-3 right-3 rounded-full bg-accent px-4 py-2.5 text-sm font-semibold text-bg shadow-glow transition hover:brightness-110 lg:hidden"
        >
          ＋ Nodes
        </button>

        {paletteOpen && (
          <div className="absolute inset-0 z-10 flex flex-col justify-end lg:hidden">
            {/* Abdunkler */}
            <button
              aria-label="Schließen"
              onClick={() => setPaletteOpen(false)}
              className="absolute inset-0 bg-bg/60"
            />
            <div className="relative max-h-[60%] overflow-auto rounded-t-2xl border-t border-border bg-surface p-4 shadow-node">
              <div className="mb-3 flex items-center justify-between">
                <span className="text-sm font-semibold text-text">Node hinzufügen</span>
                <button
                  onClick={() => setPaletteOpen(false)}
                  className="text-muted transition hover:text-text"
                  aria-label="Schließen"
                >
                  ✕
                </button>
              </div>
              <Palette
                onAdd={(spec) => {
                  addNode(spec);
                  setPaletteOpen(false);
                }}
                allowed={allowedNodes}
              />
            </div>
          </div>
        )}
      </div>

      {/* Bühne */}
      <div className="rounded-xl border border-border bg-surface/40 p-3">
        <Stage actions={actions} hasRun={hasRun} />
      </div>
    </div>
  );
}

export function Simulator(props: SimulatorProps) {
  return (
    <ReactFlowProvider>
      <SimulatorInner {...props} />
    </ReactFlowProvider>
  );
}
