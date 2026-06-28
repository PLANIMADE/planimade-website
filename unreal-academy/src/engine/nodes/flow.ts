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

/**
 * ForLoop: führt den "loop"-Zweig für jeden Index von First bis Last (inkl.)
 * aus, danach einmal "completed". Der aktuelle Index liegt am Data-Ausgang an.
 * Iterationszahl ist gedeckelt (Schutz vor versehentlichen Riesenschleifen).
 */
export const forLoop = defineNode({
  type: "flow.forLoop",
  title: "For Loop",
  category: "Flow Control",
  kind: "flow",
  execIn: ["in"],
  execOut: ["loop", "completed"],
  dataIn: [
    { id: "first", type: "int", label: "First", default: 0 },
    { id: "last", type: "int", label: "Last", default: 0 },
  ],
  dataOut: [{ id: "index", type: "int", label: "Index" }],
  // Der Body liest den aktuellen Index über diesen Data-Ausgang (aus dem State).
  pure: (ctx) => ({ index: ctx.getState<number>() ?? 0 }),
  loop: (ctx, run) => {
    const first = Math.trunc(Number(ctx.input("first")));
    const last = Math.trunc(Number(ctx.input("last")));
    const MAX = 1000;
    let count = 0;
    for (let i = first; i <= last && count < MAX; i++, count++) {
      ctx.setState(i);
      run("loop");
    }
    run("completed");
  },
});

/** DoOnce: lässt den Ablauf nur beim ersten Mal durch, danach blockiert er. */
export const doOnce = defineNode({
  type: "flow.doOnce",
  title: "Do Once",
  category: "Flow Control",
  kind: "flow",
  execIn: ["in"],
  execOut: ["then"],
  dataIn: [],
  dataOut: [],
  exec: (ctx) => {
    const used = ctx.getState<boolean>() ?? false;
    if (used) return null;
    ctx.setState(true);
    return "then";
  },
});
