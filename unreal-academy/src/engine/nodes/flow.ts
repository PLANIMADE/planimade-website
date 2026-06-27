import { defineNode } from "../registry";

/** Ablaufsteuerung: Branch, Sequence, Gate. */

export const branch = defineNode({
  type: "flow.branch",
  title: "Branch",
  category: "Flow Control",
  kind: "flow",
  execIn: ["in"],
  execOut: ["true", "false"],
  dataIn: [{ id: "condition", type: "bool", label: "Condition", default: false }],
  dataOut: [],
  exec: (ctx) => (ctx.input("condition") ? "true" : "false"),
});

export const sequence = defineNode({
  type: "flow.sequence",
  title: "Sequence",
  category: "Flow Control",
  kind: "flow",
  execIn: ["in"],
  execOut: ["then0", "then1"],
  dataIn: [],
  dataOut: [],
  // Feuert beide Ausgänge nacheinander.
  exec: () => ["then0", "then1"],
});

/**
 * Gate: stateful. Über "open"/"close" wird der Durchlass gesteuert,
 * über "enter" fließt der Ablauf nur weiter, wenn das Gate offen ist.
 */
export const gate = defineNode({
  type: "flow.gate",
  title: "Gate",
  category: "Flow Control",
  kind: "flow",
  execIn: ["enter", "open", "close"],
  execOut: ["exit"],
  dataIn: [],
  dataOut: [],
  exec: (ctx) => {
    const isOpen = ctx.getState<boolean>() ?? false;
    switch (ctx.execIn) {
      case "open":
        ctx.setState(true);
        return null;
      case "close":
        ctx.setState(false);
        return null;
      case "enter":
      default:
        return isOpen ? "exit" : null;
    }
  },
});
