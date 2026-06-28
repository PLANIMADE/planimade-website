import { describe, expect, it } from "vitest";
import { gradeGraph, type BPGraph } from "@/engine";
import { allMissions, getMission, getNextMission } from "@/data/missions";
import type { BuilderStep } from "@/features/missions/types";
import { edge, node } from "../helpers";

/**
 * Diese Tests garantieren, dass keine kaputten/unlösbaren Missionen ausgeliefert
 * werden: Jede Bau-/Debug-Mission muss eine Lösung haben, die den Grader besteht,
 * UND der Startgraph muss vorher fehlschlagen (sonst wäre die Mission trivial).
 */

function builderStep(id: string): BuilderStep {
  const m = getMission(id);
  if (!m) throw new Error(`Mission ${id} fehlt`);
  const step = m.steps[0];
  if (step.kind !== "build" && step.kind !== "debug") {
    throw new Error(`Mission ${id} ist kein Build/Debug-Step`);
  }
  return step;
}

/**
 * Wendet eine „Reparatur"/„Lösung" auf den Startgraphen an: Kanten entfernen,
 * Knoten/Kanten hinzufügen, Knoten-Daten (Operator, Literal) setzen.
 */
function fix(
  base: BPGraph,
  opts: {
    removeEdges?: string[];
    nodes?: BPGraph["nodes"];
    edges?: BPGraph["edges"];
    setData?: Record<string, Record<string, unknown>>;
  },
): BPGraph {
  const remove = new Set(opts.removeEdges ?? []);
  const nodes = base.nodes
    .map((n) =>
      opts.setData?.[n.id]
        ? { ...n, data: { ...(n.data ?? {}), ...opts.setData[n.id] } }
        : n,
    )
    .concat(opts.nodes ?? []);
  const edges = base.edges
    .filter((e) => !remove.has(e.id))
    .concat(opts.edges ?? []);
  return { nodes, edges, variables: base.variables };
}

/** Referenzlösung pro Mission-ID (eine korrekte Lösung von vielen möglichen). */
const solutions: Record<string, (g: BPGraph) => BPGraph> = {
  // Pfad A
  A2: (g) => fix(g, { edges: [edge("ev", "then", "print", "in")] }),
  A4: (g) => fix(g, { edges: [edge("br", "true", "door", "in")] }),
  A6: (g) =>
    fix(g, {
      nodes: [node("light", "action.toggleLight")],
      edges: [edge("ev", "then", "light", "in")],
    }),
  A7: (g) => fix(g, { edges: [edge("get", "value", "br", "condition")] }),
  A8: (g) =>
    fix(g, {
      nodes: [
        node("br", "flow.branch"),
        node("door", "action.openDoor"),
        node("print", "action.print"),
      ],
      edges: [
        edge("ev", "then", "br", "in"),
        edge("get", "value", "br", "condition"),
        edge("br", "true", "door", "in"),
        edge("br", "false", "print", "in"),
      ],
    }),

  // Pfad B
  B2: (g) => fix(g, { edges: [edge("ev", "then", "set", "in")] }),
  B4: (g) =>
    fix(g, { setData: { cmp: { op: "<=" } }, edges: [edge("br", "true", "dead", "in")] }),
  B5: (g) =>
    fix(g, {
      nodes: [node("litB", "literal.int", { value: -10 })],
      edges: [edge("litB", "value", "add", "b")],
    }),
  B6: (g) =>
    fix(g, { removeEdges: ["dbug"], edges: [edge("getH", "value", "cmp", "a")] }),
  B7: (g) =>
    fix(g, {
      setData: { cmp: { op: ">=" } },
      nodes: [node("litB", "literal.int", { value: 3 })],
      edges: [edge("litB", "value", "cmp", "b"), edge("br", "true", "door", "in")],
    }),
  B8: (g) =>
    fix(g, {
      nodes: [
        node("cmp", "math.compare", { op: "<=" }),
        node("br", "flow.branch"),
        node("dead", "action.setDead"),
        node("print", "action.print"),
      ],
      edges: [
        edge("get", "value", "cmp", "a"),
        edge("cmp", "result", "br", "condition"),
        edge("ev", "then", "br", "in"),
        edge("br", "true", "dead", "in"),
        edge("br", "false", "print", "in"),
      ],
    }),

  // Pfad C
  C2: (g) =>
    fix(g, {
      removeEdges: ["ebug"],
      nodes: [node("br", "flow.branch")],
      edges: [
        edge("ev", "then", "br", "in"),
        edge("get", "value", "br", "condition"),
        edge("br", "true", "door", "in"),
      ],
    }),
  C3: (g) =>
    fix(g, { removeEdges: ["ebug"], edges: [edge("ev", "then", "light", "in")] }),
  C4: (g) =>
    fix(g, {
      removeEdges: ["btrue", "bfalse"],
      edges: [edge("br", "true", "door", "in"), edge("br", "false", "print", "in")],
    }),
  C5: (g) =>
    fix(g, { removeEdges: ["dbug"], edges: [edge("get", "value", "add", "a")] }),
  C7: (g) => fix(g, { setData: { cmp: { op: ">=" } } }),
  C8: (g) =>
    fix(g, {
      setData: { cmp: { op: "<=" } },
      edges: [
        edge("cmp", "result", "br", "condition"),
        edge("br", "true", "dead", "in"),
      ],
    }),

  // Pfad D
  D2: (g) => fix(g, { edges: [edge("fl", "loop", "pr", "in")] }),
  D3: (g) => fix(g, { edges: [edge("fl", "loop", "set", "in")] }),
  D5: (g) =>
    fix(g, {
      nodes: [node("and", "logic.and")],
      edges: [
        edge("getK", "value", "and", "a"),
        edge("getS", "value", "and", "b"),
        edge("and", "out", "br", "condition"),
      ],
    }),
  D6: (g) =>
    fix(g, {
      removeEdges: ["dbad"],
      nodes: [node("clamp", "math.clamp")],
      edges: [
        edge("sub", "diff", "clamp", "value"),
        edge("clamp", "result", "set", "value"),
      ],
    }),
  D7: (g) =>
    fix(g, {
      removeEdges: ["ebad"],
      nodes: [node("once", "flow.doOnce")],
      edges: [
        edge("fl", "loop", "once", "in"),
        edge("once", "then", "pr", "in"),
      ],
    }),
  D8: (g) =>
    fix(g, {
      nodes: [
        node("cmp", "math.compare", { op: ">=" }),
        node("lit3", "literal.int", { value: 3 }),
        node("and", "logic.and"),
      ],
      edges: [
        edge("getC", "value", "cmp", "a"),
        edge("lit3", "value", "cmp", "b"),
        edge("getK", "value", "and", "a"),
        edge("cmp", "result", "and", "b"),
        edge("and", "out", "br", "condition"),
        edge("br", "true", "door", "in"),
        edge("br", "false", "print", "in"),
      ],
    }),
};

describe("Missions – Struktur", () => {
  it("hat eindeutige IDs", () => {
    const ids = allMissions.map((m) => m.id);
    expect(new Set(ids).size).toBe(ids.length);
  });

  it("hat 32 Missionen über 4 Pfade", () => {
    expect(allMissions).toHaveLength(32);
  });

  it("Quiz-Antworten zeigen auf gültige Optionen", () => {
    for (const m of allMissions) {
      for (const s of m.steps) {
        if (s.kind === "quiz") {
          expect(s.answer).toBeGreaterThanOrEqual(0);
          expect(s.answer).toBeLessThan(s.options.length);
        }
      }
    }
  });

  it("getNextMission verkettet die Pfade und endet sauber", () => {
    expect(getNextMission("A1")?.id).toBe("A2");
    expect(getNextMission("A8")?.id).toBe("B1");
    expect(getNextMission("C8")?.id).toBe("D1");
    expect(getNextMission("D8")).toBeUndefined();
  });
});

describe("Missions – jeder Bau-/Debug-Startgraph schlägt zunächst fehl", () => {
  for (const m of allMissions) {
    const step = m.steps[0];
    if (step.kind !== "build" && step.kind !== "debug") continue;
    it(`${m.id}: Startgraph besteht den Grader NICHT`, () => {
      expect(gradeGraph(step.initialGraph, step.grader).passed).toBe(false);
    });
  }
});

describe("Missions – Referenzlösung besteht den Grader", () => {
  for (const m of allMissions) {
    const step = m.steps[0];
    if (step.kind !== "build" && step.kind !== "debug") continue;
    const solve = solutions[m.id];
    it(`${m.id}: hat eine lösbare Referenzlösung`, () => {
      expect(solve, `Referenzlösung für ${m.id} fehlt`).toBeDefined();
      const solved = solve(step.initialGraph);
      const result = gradeGraph(solved, step.grader);
      expect(result.passed, JSON.stringify(result.cases)).toBe(true);
    });
  }
});
