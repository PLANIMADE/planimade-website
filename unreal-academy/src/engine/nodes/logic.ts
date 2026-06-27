import { defineNode } from "../registry";

/** Logik & Mathe (pure Nodes). */

export const compare = defineNode({
  type: "math.compare",
  title: "Compare",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "a", type: "float", label: "A", default: 0 },
    { id: "b", type: "float", label: "B", default: 0 },
  ],
  dataOut: [{ id: "result", type: "bool", label: "Result" }],
  pure: (ctx) => {
    const a = Number(ctx.input("a"));
    const b = Number(ctx.input("b"));
    const op = String(ctx.node.data?.op ?? "==");
    let result = false;
    switch (op) {
      case "==":
        result = a === b;
        break;
      case "!=":
        result = a !== b;
        break;
      case ">":
        result = a > b;
        break;
      case "<":
        result = a < b;
        break;
      case ">=":
        result = a >= b;
        break;
      case "<=":
        result = a <= b;
        break;
    }
    return { result };
  },
});

export const add = defineNode({
  type: "math.add",
  title: "Add (int)",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "a", type: "int", label: "A", default: 0 },
    { id: "b", type: "int", label: "B", default: 0 },
  ],
  dataOut: [{ id: "sum", type: "int", label: "Sum" }],
  pure: (ctx) => ({ sum: Number(ctx.input("a")) + Number(ctx.input("b")) }),
});

export const not = defineNode({
  type: "logic.not",
  title: "NOT",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [{ id: "in", type: "bool", label: "In", default: false }],
  dataOut: [{ id: "out", type: "bool", label: "Out" }],
  pure: (ctx) => ({ out: !ctx.input("in") }),
});
