import { describe, expect, it } from "vitest";
import { gradeGraph, validateGraph } from "@/engine";
import { edge, graph, node } from "../helpers";

const doorGraph = graph(
  [
    node("ev", "event.beginPlay"),
    node("get", "var.get", { varName: "HasKey" }),
    node("br", "flow.branch"),
    node("door", "action.openDoor"),
  ],
  [
    edge("ev", "then", "br", "in"),
    edge("get", "value", "br", "condition"),
    edge("br", "true", "door", "in"),
  ],
  [{ name: "HasKey", type: "bool", default: false }],
);

describe("gradeGraph – verhaltensbasiert", () => {
  const spec = {
    cases: [
      { name: "mit Schlüssel", given: { HasKey: true }, expect: { actions: ["OpenDoor"] } },
      { name: "ohne Schlüssel", given: { HasKey: false }, expect: { actions: [] } },
    ],
  };

  it("besteht die korrekte Lösung", () => {
    const result = gradeGraph(doorGraph, spec);
    expect(result.passed).toBe(true);
    expect(result.cases.every((c) => c.passed)).toBe(true);
  });

  it("erkennt eine falsche Lösung (Tür immer offen)", () => {
    // Kondition entfernt: Branch immer true-Zweig? Hier: Tür direkt an Event.
    const broken = graph(
      [node("ev", "event.beginPlay"), node("door", "action.openDoor")],
      [edge("ev", "then", "door", "in")],
      [{ name: "HasKey", type: "bool", default: false }],
    );
    const result = gradeGraph(broken, spec);
    expect(result.passed).toBe(false);
    // Der "ohne Schlüssel"-Fall muss scheitern.
    expect(result.cases[1].passed).toBe(false);
    expect(result.cases[1].detail).toContain("OpenDoor");
  });

  it("akzeptiert einen alternativen Lösungsweg (NOT + false-Zweig)", () => {
    // Gleiches Verhalten über NOT(HasKey) am false-Pfad invertiert.
    const alt = graph(
      [
        node("ev", "event.beginPlay"),
        node("get", "var.get", { varName: "HasKey" }),
        node("n", "logic.not"),
        node("br", "flow.branch"),
        node("door", "action.openDoor"),
      ],
      [
        edge("ev", "then", "br", "in"),
        edge("get", "value", "n", "in"),
        edge("n", "out", "br", "condition"),
        edge("br", "false", "door", "in"), // doppelte Invertierung => gleich
      ],
      [{ name: "HasKey", type: "bool", default: false }],
    );
    expect(gradeGraph(alt, spec).passed).toBe(true);
  });
});

describe("validateGraph – Pin-Kompatibilität", () => {
  it("findet keine Fehler im korrekten Graphen", () => {
    expect(validateGraph(doorGraph)).toEqual([]);
  });

  it("meldet Exec↔Data-Fehlverbindung", () => {
    const bad = graph(
      [node("ev", "event.beginPlay"), node("br", "flow.branch")],
      [edge("ev", "then", "br", "condition")], // exec -> bool
    );
    const errors = validateGraph(bad);
    expect(errors.some((e) => e.code === "exec_to_data")).toBe(true);
  });
});
