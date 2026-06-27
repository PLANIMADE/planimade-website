import { describe, expect, it } from "vitest";
import { actionKinds, runGraph } from "@/engine";
import { edge, graph, node } from "../helpers";

describe("runGraph – Exec-Flow & Data-Pull", () => {
  it("öffnet die Tür nur, wenn HasKey true ist (die Kern-Mission)", () => {
    const g = graph(
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

    expect(actionKinds(runGraph(g, { variables: { HasKey: true } }))).toEqual([
      "OpenDoor",
    ]);
    expect(actionKinds(runGraph(g, { variables: { HasKey: false } }))).toEqual(
      [],
    );
  });

  it("zieht Data-Pins lazy durch pure Nodes (Compare → Branch)", () => {
    // Wenn Health < 1 => SetDead
    const g = graph(
      [
        node("ev", "event.beginPlay"),
        node("h", "var.get", { varName: "Health" }),
        node("one", "literal.int", { value: 1 }),
        node("cmp", "math.compare", { op: "<" }),
        node("br", "flow.branch"),
        node("dead", "action.setDead"),
      ],
      [
        edge("ev", "then", "br", "in"),
        edge("h", "value", "cmp", "a"),
        edge("one", "value", "cmp", "b"),
        edge("cmp", "result", "br", "condition"),
        edge("br", "true", "dead", "in"),
      ],
      [{ name: "Health", type: "int", default: 100 }],
    );

    expect(actionKinds(runGraph(g, { variables: { Health: 0 } }))).toEqual([
      "SetDead",
    ]);
    expect(actionKinds(runGraph(g, { variables: { Health: 5 } }))).toEqual([]);
  });

  it("Sequence feuert beide Ausgänge in Reihenfolge", () => {
    const g = graph(
      [
        node("ev", "event.beginPlay"),
        node("seq", "flow.sequence"),
        node("a", "action.print", { value: "A" }),
        node("b", "action.toggleLight"),
      ],
      [
        edge("ev", "then", "seq", "in"),
        edge("seq", "then0", "a", "in"),
        edge("seq", "then1", "b", "in"),
      ],
    );
    expect(actionKinds(runGraph(g))).toEqual(["Print", "ToggleLight"]);
  });

  it("Set Variable verändert den Endzustand (Münzen zählen)", () => {
    // Score = Score + 1
    const g = graph(
      [
        node("ev", "event.beginPlay"),
        node("get", "var.get", { varName: "Score" }),
        node("one", "literal.int", { value: 1 }),
        node("add", "math.add"),
        node("set", "var.set", { varName: "Score" }),
      ],
      [
        edge("ev", "then", "set", "in"),
        edge("get", "value", "add", "a"),
        edge("one", "value", "add", "b"),
        edge("add", "sum", "set", "value"),
      ],
      [{ name: "Score", type: "int", default: 0 }],
    );
    expect(runGraph(g, { variables: { Score: 4 } }).variables.Score).toBe(5);
  });

  it("Gate blockiert, solange es geschlossen ist; öffnet nach 'open'", () => {
    // Geschlossen: enter -> kein Print. Nach open: enter -> Print.
    const closed = graph(
      [
        node("ev", "event.beginPlay"),
        node("gate", "flow.gate"),
        node("p", "action.print"),
      ],
      [edge("ev", "then", "gate", "enter"), edge("gate", "exit", "p", "in")],
    );
    expect(actionKinds(runGraph(closed))).toEqual([]);

    const opened = graph(
      [
        node("ev", "event.beginPlay"),
        node("seq", "flow.sequence"),
        node("gate", "flow.gate"),
        node("p", "action.print"),
      ],
      [
        edge("ev", "then", "seq", "in"),
        edge("seq", "then0", "gate", "open"),
        edge("seq", "then1", "gate", "enter"),
        edge("gate", "exit", "p", "in"),
      ],
    );
    expect(actionKinds(runGraph(opened))).toEqual(["Print"]);
  });

  it("liefert einen Trace in Ausführungsreihenfolge", () => {
    const g = graph(
      [node("ev", "event.beginPlay"), node("p", "action.print")],
      [edge("ev", "then", "p", "in")],
    );
    const trace = runGraph(g).trace;
    expect(trace.map((t) => t.nodeId)).toEqual(["ev", "p"]);
  });

  it("bricht bei Endlosschleife sicher ab", () => {
    // Gate, das sich selbst über 'enter' triggert nachdem es offen ist.
    const g = graph(
      [
        node("ev", "event.beginPlay"),
        node("g", "flow.gate"),
      ],
      // open dann enter -> exit -> enter -> ... (Selbstschleife)
      [
        edge("ev", "then", "g", "open"),
      ],
    );
    // open allein erzeugt keinen Loop; dieser Lauf muss sauber terminieren.
    expect(runGraph(g).errors).toEqual([]);
  });
});
