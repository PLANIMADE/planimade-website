import { describe, expect, it } from "vitest";
import { actionKinds, runGraph } from "@/engine";
import { edge, graph, node } from "../helpers";

/** Tests für die Intermediate-Bausteine: ForLoop, DoOnce, AND/OR, Sub/Mul, Clamp. */

describe("ForLoop", () => {
  it("feuert den loop-Zweig (last-first+1)× und danach completed", () => {
    const g = graph(
      [
        node("ev", "event.beginPlay"),
        node("last", "literal.int", { value: 2 }),
        node("fl", "flow.forLoop"),
        node("pr", "action.print"),
        node("done", "action.toggleLight"),
      ],
      [
        edge("ev", "then", "fl", "in"),
        edge("last", "value", "fl", "last"),
        edge("fl", "loop", "pr", "in"),
        edge("fl", "completed", "done", "in"),
      ],
    );
    // First=0 (Default), Last=2 → 3 Durchläufe, dann completed.
    expect(actionKinds(runGraph(g))).toEqual([
      "Print",
      "Print",
      "Print",
      "ToggleLight",
    ]);
  });

  it("stellt den aktuellen Index am Data-Ausgang bereit", () => {
    const g = graph(
      [
        node("ev", "event.beginPlay"),
        node("last", "literal.int", { value: 3 }),
        node("fl", "flow.forLoop"),
        node("set", "var.set", { varName: "Last" }),
      ],
      [
        edge("ev", "then", "fl", "in"),
        edge("last", "value", "fl", "last"),
        edge("fl", "loop", "set", "in"),
        edge("fl", "index", "set", "value"),
      ],
      [{ name: "Last", type: "int", default: -1 }],
    );
    // Letzter geschriebener Index = 3.
    expect(runGraph(g).variables.Last).toBe(3);
  });
});

describe("DoOnce", () => {
  it("lässt den Ablauf nur einmal durch, auch bei mehrfachem Trigger", () => {
    const g = graph(
      [
        node("ev", "event.beginPlay"),
        node("seq", "flow.sequence"),
        node("once", "flow.doOnce"),
        node("pr", "action.print"),
      ],
      [
        edge("ev", "then", "seq", "in"),
        edge("seq", "then0", "once", "in"),
        edge("seq", "then1", "once", "in"),
        edge("once", "then", "pr", "in"),
      ],
    );
    expect(actionKinds(runGraph(g))).toEqual(["Print"]);
  });
});

describe("Logik & Mathe (pure)", () => {
  // Hilfsgraph: Branch über eine bool-Quelle -> Open Door bei true.
  function gateBy(boolNodeId: string, extra: ReturnType<typeof node>[], edges: ReturnType<typeof edge>[]) {
    return graph(
      [
        node("ev", "event.beginPlay"),
        node("br", "flow.branch"),
        node("door", "action.openDoor"),
        ...extra,
      ],
      [
        edge("ev", "then", "br", "in"),
        edge(boolNodeId, "out", "br", "condition"),
        edge("br", "true", "door", "in"),
        ...edges,
      ],
    );
  }

  it("AND: nur true&true öffnet", () => {
    const mk = (a: boolean, b: boolean) =>
      gateBy(
        "and",
        [
          node("la", "literal.bool", { value: a }),
          node("lb", "literal.bool", { value: b }),
          node("and", "logic.and"),
        ],
        [edge("la", "value", "and", "a"), edge("lb", "value", "and", "b")],
      );
    expect(actionKinds(runGraph(mk(true, true)))).toEqual(["OpenDoor"]);
    expect(actionKinds(runGraph(mk(true, false)))).toEqual([]);
  });

  it("OR: true|false öffnet", () => {
    const g = gateBy(
      "or",
      [
        node("la", "literal.bool", { value: true }),
        node("lb", "literal.bool", { value: false }),
        node("or", "logic.or"),
      ],
      [edge("la", "value", "or", "a"), edge("lb", "value", "or", "b")],
    );
    expect(actionKinds(runGraph(g))).toEqual(["OpenDoor"]);
  });

  it("Subtract & Multiply rechnen korrekt", () => {
    const sub = graph(
      [
        node("ev", "event.beginPlay"),
        node("a", "literal.int", { value: 30 }),
        node("b", "literal.int", { value: 12 }),
        node("sub", "math.sub"),
        node("set", "var.set", { varName: "R" }),
      ],
      [
        edge("ev", "then", "set", "in"),
        edge("a", "value", "sub", "a"),
        edge("b", "value", "sub", "b"),
        edge("sub", "diff", "set", "value"),
      ],
      [{ name: "R", type: "int", default: 0 }],
    );
    expect(runGraph(sub).variables.R).toBe(18);

    const mul = graph(
      [
        node("ev", "event.beginPlay"),
        node("a", "literal.int", { value: 6 }),
        node("b", "literal.int", { value: 7 }),
        node("mul", "math.mul"),
        node("set", "var.set", { varName: "R" }),
      ],
      [
        edge("ev", "then", "set", "in"),
        edge("a", "value", "mul", "a"),
        edge("b", "value", "mul", "b"),
        edge("mul", "product", "set", "value"),
      ],
      [{ name: "R", type: "int", default: 0 }],
    );
    expect(runGraph(mul).variables.R).toBe(42);
  });

  it("Clamp hält den Wert in den Grenzen", () => {
    const mk = (value: number) =>
      graph(
        [
          node("ev", "event.beginPlay"),
          node("v", "literal.int", { value }),
          node("cl", "math.clamp"), // min 0, max 100 (Defaults)
          node("set", "var.set", { varName: "H" }),
        ],
        [
          edge("ev", "then", "set", "in"),
          edge("v", "value", "cl", "value"),
          edge("cl", "result", "set", "value"),
        ],
        [{ name: "H", type: "int", default: -999 }],
      );
    expect(runGraph(mk(-5)).variables.H).toBe(0);
    expect(runGraph(mk(150)).variables.H).toBe(100);
    expect(runGraph(mk(42)).variables.H).toBe(42);
  });
});
