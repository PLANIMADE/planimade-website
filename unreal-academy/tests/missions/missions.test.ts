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

/** Klont einen Graphen und ergänzt Nodes/Edges (= „die Lösung bauen"). */
function withSolution(
  base: BPGraph,
  add: { nodes?: BPGraph["nodes"]; edges?: BPGraph["edges"] },
): BPGraph {
  return {
    nodes: [...base.nodes, ...(add.nodes ?? [])],
    edges: [...base.edges, ...(add.edges ?? [])],
    variables: base.variables,
  };
}

describe("Missions – Struktur", () => {
  it("hat eindeutige IDs", () => {
    const ids = allMissions.map((m) => m.id);
    expect(new Set(ids).size).toBe(ids.length);
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

  it("getNextMission verkettet den Pfad und endet sauber", () => {
    expect(getNextMission("A1")?.id).toBe("A2");
    expect(getNextMission("A8")).toBeUndefined();
  });
});

describe("Missions – Bau-/Debug-Missionen sind lösbar", () => {
  it("A2: BeginPlay → Print", () => {
    const step = builderStep("A2");
    expect(gradeGraph(step.initialGraph, step.grader).passed).toBe(false);

    const solved = withSolution(step.initialGraph, {
      edges: [edge("ev", "then", "print", "in")],
    });
    expect(gradeGraph(solved, step.grader).passed).toBe(true);
  });

  it("A4: true → Open Door", () => {
    const step = builderStep("A4");
    expect(gradeGraph(step.initialGraph, step.grader).passed).toBe(false);

    const solved = withSolution(step.initialGraph, {
      edges: [edge("br", "true", "door", "in")],
    });
    expect(gradeGraph(solved, step.grader).passed).toBe(true);
  });

  it("A6: Toggle Light beim Start", () => {
    const step = builderStep("A6");
    expect(gradeGraph(step.initialGraph, step.grader).passed).toBe(false);

    const solved = withSolution(step.initialGraph, {
      nodes: [node("light", "action.toggleLight")],
      edges: [edge("ev", "then", "light", "in")],
    });
    expect(gradeGraph(solved, step.grader).passed).toBe(true);
  });

  it("A7 (debug): Condition verbinden", () => {
    const step = builderStep("A7");
    expect(gradeGraph(step.initialGraph, step.grader).passed).toBe(false);

    const solved = withSolution(step.initialGraph, {
      edges: [edge("get", "value", "br", "condition")],
    });
    expect(gradeGraph(solved, step.grader).passed).toBe(true);
  });

  it("A8 (boss): Tür oder Absage", () => {
    const step = builderStep("A8");
    expect(gradeGraph(step.initialGraph, step.grader).passed).toBe(false);

    const solved = withSolution(step.initialGraph, {
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
    });
    expect(gradeGraph(solved, step.grader).passed).toBe(true);
  });
});
